<?php
/**
 * This file is part of the Composer Merge plugin.
 *
 * Copyright (C) 2015 Bryan Davis, Wikimedia Foundation, and contributors
 *
 * This software may be modified and distributed under the terms of the MIT
 * license. See the LICENSE file for details.
 */

namespace Wikimedia\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Wikimedia\Composer\Merge\ExtraPackage;
use Wikimedia\Composer\Merge\MissingFileException;
use Wikimedia\Composer\Merge\PluginState;

/**
 * Composer plugin that allows merging multiple composer.json files.
 *
 * When installed, this plugin will look for a "merge-plugin" key in the
 * composer configuration's "extra" section. The value for this key is
 * a set of options configuring the plugin.
 *
 * An "include" setting is required. The value of this setting can be either
 * a single value or an array of values. Each value is treated as a glob()
 * pattern identifying additional composer.json style configuration files to
 * merge into the configuration for the current compser execution.
 *
 * The "autoload", "autoload-dev", "conflict", "provide", "replace",
 * "repositories", "require", "require-dev", and "suggest" sections of the
 * found configuration files will be merged into the root package
 * configuration as though they were directly included in the top-level
 * composer.json file.
 *
 * If included files specify conflicting package versions for "require" or
 * "require-dev", the normal Composer dependency solver process will be used
 * to attempt to resolve the conflict. Specifying the 'replace' key as true will
 * change this default behaviour so that the last-defined version of a package
 * will win, allowing for force-overrides of package defines.
 *
 * By default the "extra" section is not merged. This can be enabled by
 * setitng the 'merge-extra' key to true. In normal mode, when the same key is
 * found in both the original and the imported extra section, the version in
 * the original config is used and the imported version is skipped. If
 * 'replace' mode is active, this behaviour changes so the imported version of
 * the key is used, replacing the version in the original config.
 *
 *
 * @code
 * {
 *     "require": {
 *         "wikimedia/composer-merge-plugin": "dev-master"
 *     },
 *     "extra": {
 *         "merge-plugin": {
 *             "include": [
 *                 "composer.local.json"
 *             ]
 *         }
 *     }
 * }
 * @endcode
 *
 * @author Bryan Davis <bd808@bd808.com>
 */
class MergePlugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Offical package name
     */
    const PACKAGE_NAME = 'wikimedia/composer-merge-plugin';

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var PluginState $state
     */
    protected $state;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * Files that have already been processed
     *
     * @var string[] $loadedFiles
     */
    protected $loadedFiles = array();

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->state = new PluginState($this->composer);
        $this->logger = new Logger('merge-plugin', $io);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            InstallerEvents::PRE_DEPENDENCIES_SOLVING => 'onDependencySolve',
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'onInstallUpdateOrDump',
            ScriptEvents::PRE_INSTALL_CMD => 'onInstallUpdateOrDump',
            ScriptEvents::PRE_UPDATE_CMD => 'onInstallUpdateOrDump',
        );
    }

    /**
     * Handle an event callback for an install, update or dump command by
     * checking for "merge-plugin" in the "extra" data and merging package
     * contents if found.
     *
     * @param Event $event
     */
    public function onInstallUpdateOrDump(Event $event)
    {
        $this->state->loadSettings();
        $this->state->setDevMode($event->isDevMode());
        $this->mergeFiles($this->state->getIncludes(), false);
        $this->mergeFiles($this->state->getRequires(), true);

        if ($event->getName() === ScriptEvents::PRE_AUTOLOAD_DUMP) {
            $this->state->setDumpAutoloader(true);
            $flags = $event->getFlags();
            if (isset($flags['optimize'])) {
                $this->state->setOptimizeAutoloader($flags['optimize']);
            }
        }
    }

    /**
     * Find configuration files matching the configured glob patterns and
     * merge their contents with the master package.
     *
     * @param array $patterns List of files/glob patterns
     * @param bool $required Are the patterns required to match files?
     * @throws MissingFileException when required and a pattern returns no
     *      results
     */
    protected function mergeFiles(array $patterns, $required = false)
    {
        $root = $this->composer->getPackage();

        $files = array_map(
            function ($files, $pattern) use ($required) {
                if ($required && !$files) {
                    throw new MissingFileException(
                        "merge-plugin: No files matched required '{$pattern}'"
                    );
                }
                return $files;
            },
            array_map('glob', $patterns),
            $patterns
        );

        foreach (array_reduce($files, 'array_merge', array()) as $path) {
            $this->mergeFile($root, $path);
        }
    }

    /**
     * Read a JSON file and merge its contents
     *
     * @param RootPackageInterface $root
     * @param string $path
     */
    protected function mergeFile(RootPackageInterface $root, $path)
    {
        if (isset($this->loadedFiles[$path])) {
            $this->logger->debug("Already merged <comment>$path</comment>");
            return;
        } else {
            $this->loadedFiles[$path] = true;
        }
        $this->logger->info("Loading <comment>{$path}</comment>...");

        $package = new ExtraPackage($path, $this->composer, $this->logger);
        $package->mergeInto($root, $this->state);

        if ($this->state->recurseIncludes()) {
            $this->mergeFiles($package->getIncludes(), false);
            $this->mergeFiles($package->getRequires(), true);
        }
    }

    /**
     * Handle an event callback for pre-dependency solving phase of an install
     * or update by adding any duplicate package dependencies found during
     * initial merge processing to the request that will be processed by the
     * dependency solver.
     *
     * @param InstallerEvent $event
     */
    public function onDependencySolve(InstallerEvent $event)
    {
        $request = $event->getRequest();
        foreach ($this->state->getDuplicateLinks('require') as $link) {
            $this->logger->info(
                "Adding dependency <comment>{$link}</comment>"
            );
            $request->install($link->getTarget(), $link->getConstraint());
        }
        if ($this->state->isDevMode()) {
            foreach ($this->state->getDuplicateLinks('require-dev') as $link) {
                $this->logger->info(
                    "Adding dev dependency <comment>{$link}</comment>"
                );
                $request->install($link->getTarget(), $link->getConstraint());
            }
        }
    }

    /**
     * Handle an event callback following installation of a new package by
     * checking to see if the package that was installed was our plugin.
     *
     * @param PackageEvent $event
     */
    public function onPostPackageInstall(PackageEvent $event)
    {
        $op = $event->getOperation();
        if ($op instanceof InstallOperation) {
            $package = $op->getPackage()->getName();
            if ($package === self::PACKAGE_NAME) {
                $this->logger->info('composer-merge-plugin installed');
                $this->state->setFirstInstall(true);
                $this->state->setLocked(
                    $event->getComposer()->getLocker()->isLocked()
                );
            }
        }
    }

    /**
     * Handle an event callback following an install or update command. If our
     * plugin was installed during the run then trigger an update command to
     * process any merge-patterns in the current config.
     *
     * @param Event $event
     */
    public function onPostInstallOrUpdate(Event $event)
    {
        // @codeCoverageIgnoreStart
        if ($this->state->isFirstInstall()) {
            $this->state->setFirstInstall(false);
            $this->logger->info(
                '<comment>' .
                'Running additional update to apply merge settings' .
                '</comment>'
            );

            $config = $this->composer->getConfig();

            $preferSource = $config->get('preferred-install') == 'source';
            $preferDist = $config->get('preferred-install') == 'dist';

            $installer = Installer::create(
                $event->getIO(),
                // Create a new Composer instance to ensure full processing of
                // the merged files.
                Factory::create($event->getIO(), null, false)
            );

            $installer->setPreferSource($preferSource);
            $installer->setPreferDist($preferDist);
            $installer->setDevMode($event->isDevMode());
            $installer->setDumpAutoloader($this->state->shouldDumpAutoloader());
            $installer->setOptimizeAutoloader(
                $this->state->shouldOptimizeAutoloader()
            );

            if ($this->state->forceUpdate()) {
                // Force update mode so that new packages are processed rather
                // than just telling the user that composer.json and
                // composer.lock don't match.
                $installer->setUpdate(true);
            }

            $installer->run();
        }
        // @codeCoverageIgnoreEnd
    }
}
// vim:sw=4:ts=4:sts=4:et:

<?php

/**
 * @file
 * Contains \Drupal\quickedit\Plugin\InPlaceEditorBase.
 */

namespace Drupal\quickedit\Plugin;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a base in-place editor implementation.
 *
 * @see \Drupal\quickedit\Annotation\InPlaceEditor
 * @see \Drupal\quickedit\Plugin\InPlaceEditorInterface
 * @see \Drupal\quickedit\Plugin\InPlaceEditorManager
 * @see plugin_api
 */
abstract class InPlaceEditorBase extends PluginBase implements InPlaceEditorInterface {

  /**
   * {@inheritdoc}
   */
  function getMetadata(FieldItemListInterface $items) {
    return array();
  }

}

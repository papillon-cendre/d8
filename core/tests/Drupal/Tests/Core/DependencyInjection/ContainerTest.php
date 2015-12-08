<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\DependencyInjection\ContainerTest.
 */

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Tests\Core\DependencyInjection\Fixture\BarClass;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\DependencyInjection\Container
 * @group DependencyInjection
 */
class ContainerTest extends UnitTestCase {

  /**
   * Tests serialization.
   *
   * @expectedException \AssertionError
   */
  public function testSerialize() {
    $container = new Container();
    serialize($container);
  }

  /**
   * @covers ::set
   */
  public function testSet() {
    $container = new Container();
    $class = new BarClass();
    $container->set('bar', $class);
    // Ensure that _serviceId is set on the object.
    $this->assertEquals('bar', $class->_serviceId);
  }

}

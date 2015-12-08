<?php

/**
 * @file
 * Contains \Drupal\shortcut\Plugin\migrate\destination\EntityShortcutSet.
 */

namespace Drupal\shortcut\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;

/**
 * @MigrateDestination(
 *   id = "entity:shortcut_set"
 * )
 */
class EntityShortcutSet extends EntityConfigBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntity(Row $row, array $old_destination_id_values) {
    $entity = parent::getEntity($row, $old_destination_id_values);
    // Set the "syncing" flag to TRUE, to avoid duplication of default
    // shortcut links
    $entity->setSyncing(TRUE);
    return $entity;
  }
}

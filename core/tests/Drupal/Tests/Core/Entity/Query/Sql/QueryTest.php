<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Entity\Query\Sql\QueryTest.
 */

namespace Drupal\Tests\Core\Entity\Query\Sql;

use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\Query\Sql\Query;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Entity\Query\Sql\Query
 * @group Entity
 */
class QueryTest extends UnitTestCase {

  /**
   * The query object.
   *
   * @var \Drupal\Core\Entity\Query\Sql\Query
   */
  protected $query;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $entity_type = new EntityType(['id' => 'example_entity_query']);
    $conjunction = 'AND';
    $connection = $this->getMockBuilder('Drupal\Core\Database\Connection')->disableOriginalConstructor()->getMock();
    $namespaces = ['Drupal\Core\Entity\Query\Sql'];

    $this->query = new Query($entity_type, $conjunction, $connection, $namespaces);
  }

  /**
   * Tests entity query for entity type without base table.
   *
   * @covers ::prepare
   *
   * @expectedException \Drupal\Core\Entity\Query\QueryException
   * @expectedExceptionMessage No base table for example_entity_query, invalid query.
   */
  public function testNoBaseTable() {
    $this->query->execute();
  }

  /**
   * Tests revision entity query for entity type without revision table.
   *
   * @covers ::prepare
   *
   * @expectedException \Drupal\Core\Entity\Query\QueryException
   * @expectedExceptionMessage No revision table for example_entity_query, invalid query.
   */
  public function testNoRevisionTable() {
    $this->query->allRevisions()->execute();
  }

}

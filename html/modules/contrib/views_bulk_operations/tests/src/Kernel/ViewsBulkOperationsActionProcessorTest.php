<?php

namespace Drupal\Tests\views_bulk_operations\Kernel;

use Drupal\node\NodeInterface;

/**
 * @coversDefaultClass \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor
 * @group views_bulk_operations
 */
class ViewsBulkOperationsActionProcessorTest extends ViewsBulkOperationsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createTestNodes([
      'page' => [
        'count' => 20,
      ],
    ]);
  }

  /**
   * Helper function to assert if node statuses have expected values.
   *
   * @param array $list
   *   VBO processing list.
   * @param bool $exclude
   *   Exclude mode enabled?
   */
  protected function assertNodeStatuses(array $list, $exclude = FALSE) {
    $nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');

    $expected = [
      $exclude ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED,
      $exclude ? NodeInterface::NOT_PUBLISHED : NodeInterface::PUBLISHED,
    ];

    $statuses = [];

    foreach ($this->testNodesData as $id => $lang_data) {
      $node = $nodeStorage->load($id);
      $statuses[$id] = intval($node->status->value);

      // Reset node status.
      $node->status->value = 1;
      $node->save();
    }

    foreach ($statuses as $id => $status) {
      $asserted = FALSE;
      foreach ($list as $item) {
        if ($item[3] == $id) {
          $this->assertEquals($expected[0], $status);
          $asserted = TRUE;
          break;
        }
      }
      if (!$asserted) {
        $this->assertEquals($expected[1], $status);
      }
    }
  }

  /**
   * Tests general functionality of ViewsBulkOperationsActionProcessor.
   *
   * @covers ::getPageList
   * @covers ::populateQueue
   * @covers ::process
   */
  public function testViewsbulkOperationsActionProcessor() {
    $vbo_data = [
      'view_id' => 'views_bulk_operations_test',
      'action_id' => 'views_bulk_operations_simple_test_action',
      'configuration' => [
        'preconfig' => 'test',
      ],
    ];

    // Test executing all view results first.
    $results = $this->executeAction($vbo_data);

    // The default batch size is 10 and there are 20 result rows total
    // (10 nodes, each having a translation), check messages:
    $this->assertEquals('Processed 10 of 20 entities.', $results['messages'][0]);
    $this->assertEquals('Processed 20 of 20 entities.', $results['messages'][1]);
    $this->assertEquals(20, $results['operations']['Test']);

    // For a more advanced test, check if randomly selected entities
    // have been unpublished.
    $vbo_data = [
      'view_id' => 'views_bulk_operations_test',
      'action_id' => 'views_bulk_operations_advanced_test_action',
      'preconfiguration' => [
        'test_preconfig' => 'test',
        'test_config' => 'unpublish',
      ],
    ];

    // Get list of rows to process from different view pages.
    $selection = [0, 3, 6, 8, 15, 16, 18];
    $vbo_data['list'] = $this->getResultsList($vbo_data, $selection);

    // Execute the action.
    $results = $this->executeAction($vbo_data);

    $this->assertNodeStatuses($vbo_data['list']);
  }

  /**
   * Tests exclude mode of ViewsBulkOperationsActionProcessor.
   *
   * @covers ::getPageList
   * @covers ::populateQueue
   * @covers ::process
   * @covers ::initialize
   */
  public function testViewsbulkOperationsActionProcessorExclude() {
    $vbo_data = [
      'view_id' => 'views_bulk_operations_test',
      'action_id' => 'views_bulk_operations_advanced_test_action',
      'exclude_mode' => TRUE,
      'preconfiguration' => [
        'test_preconfig' => 'test',
        'test_config' => 'unpublish',
      ],
    ];

    // Get list of rows to process from different view pages.
    $selection = [1, 2, 4, 18];
    $vbo_data['list'] = $this->getResultsList($vbo_data, $selection);

    // Execute the action.
    $results = $this->executeAction($vbo_data);

    $this->assertNodeStatuses($vbo_data['list'], $vbo_data['exclude_mode']);
  }

}

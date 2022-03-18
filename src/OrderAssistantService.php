<?php

namespace Drupal\orders_assistant;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for dealing with orders.
 *
 * @package Drupal\orders_assistant.
 */
class OrderAssistantService {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection to be used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   User Storage.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * Get orders ids between dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $from
   *   Date from.
   * @param \Drupal\Core\Datetime\DrupalDateTime $to
   *   Date to.
   * @param int|string $uid
   *   Order author id.
   *
   * @return array
   *   Return orders ids.
   */
  protected function getOrders(DrupalDateTime $from, DrupalDateTime $to, $uid = ''): array {
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');

    $orders_query = $order_storage->getQuery();
    $orders_query->condition('state', 'completed')
      ->condition('completed', $from->getTimestamp(), '>=')
      ->condition('completed', $to->getTimestamp(), '<=')
      ->accessCheck(FALSE);

    if ($uid) {
      $orders_query->condition('uid', $uid);
    }

    return $orders_query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getOrdersNumber(DrupalDateTime $from, DrupalDateTime $to): ?int {
    $orders = $this->getOrders($from, $to);

    return count($orders);
  }

  /**
   * {@inheritdoc}
   */
  public function getAverageOrderValue(DrupalDateTime $from, DrupalDateTime $to, string $currency_code = 'USD', $uid = ''): Price {
    $commerce_order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $total_price_value = '0';

    $query = $commerce_order_storage->getAggregateQuery()
      ->condition('state', 'completed')
      ->condition('completed', $from->getTimestamp(), '>=')
      ->condition('completed', $to->getTimestamp(), '<=')
      ->condition('total_price__currency_code', $currency_code)
      ->condition('total_price__number', '', '!=')
      ->aggregate('total_price__number', 'avg')
      ->accessCheck(FALSE);

    if ($uid) {
      $query->condition('uid', $uid);
    }

    $result = $query->execute();

    if (!empty($result[0]['total_price__number_avg'])) {
      $total_price_value = round($result[0]['total_price__number_avg'], 2);
    }

    $total_price = Price::fromArray([
      'number' => $total_price_value,
      'currency_code' => $currency_code,
    ]);

    return $total_price;
  }

  /**
   * {@inheritdoc}
   */
  public function getMostPurchasedEntity(DrupalDateTime $from, DrupalDateTime $to): ?ProductVariation {
    $query = $this->database->select('commerce_order_item', 'coi');

    $query->leftJoin('commerce_order', 'co', 'coi.order_id = co.order_id');

    $query->condition('co.completed', $from->getTimestamp(), '>=');
    $query->condition('co.completed', $to->getTimestamp(), '<=');
    $query->condition('co.state', 'completed');

    $query->addField('coi', 'purchased_entity');
    $query->addExpression('COUNT (*)', 'count');
    $query->groupBy('coi.purchased_entity');
    $query->addExpression('SUM (quantity)', 'sum');
    $query->orderBy('sum', 'DESC');

    $query->range(0, 1);

    $result = $query->execute()->fetchCol();

    if (!empty($result)) {
      $product_variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
      $purchased_entity_id = array_shift($result);

      return $product_variation_storage->load($purchased_entity_id);
    }

    return NULL;
  }

}

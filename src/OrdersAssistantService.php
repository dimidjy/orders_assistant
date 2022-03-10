<?php

namespace Drupal\orders_assistant;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for dealing with orders.
 *
 * @package Drupal\orders_assistant.
 */
class OrdersAssistantService {

  /**
   * User storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The product variation order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productVariationStorage;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   User Storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->productVariationStorage = $entity_type_manager->getStorage('commerce_product_variation');
  }

  /**
   * Get orders entities or ids between dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $from
   *   Date from.
   * @param \Drupal\Core\Datetime\DrupalDateTime $to
   *   Date to.
   * @param bool $return_entities
   *   Return entities or not.
   * @param int|string $uid
   *   Order author id.
   *
   * @return array
   *   Return orders.
   */
  protected function getOrders(DrupalDateTime $from, DrupalDateTime $to, bool $return_entities = FALSE, $uid = ''): array {
    $orders_query = $this->orderStorage->getQuery();
    $orders_query->condition('state', 'completed')
      ->condition('changed', $from->getTimestamp(), '>=')
      ->condition('changed', $to->getTimestamp(), '<=')
      ->accessCheck(FALSE);

    if ($uid) {
      $orders_query->condition('uid', $uid);
    }

    $orders = $orders_query->execute();
    if (!empty($orders)) {
      return $return_entities ? $this->orderStorage->loadMultiple($orders) : $orders;
    }

    return [];
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
    $total_price = Price::fromArray([
      'number' => 0,
      'currency_code' => $currency_code,
    ]);

    $orders = $this->getOrders($from, $to, TRUE, $uid);
    if ($orders) {
      $orders_number = count($orders);

      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      foreach ($orders as $order) {
        $order_total_price = $order->getTotalPrice();
        $total_price = $total_price->add($order_total_price);
      }

      return $total_price->divide($orders_number);
    }

    return $total_price;
  }

  /**
   * Update array for count each purchased entity quantity.
   *
   * @param array $entities_with_quantity_array
   *   Array of entities with ids as key and quantity as value.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item.
   *
   * @return array
   *   Return purchased entities count array.
   */
  protected function updatePurchasedEntitiesQuantity(array $entities_with_quantity_array, OrderItemInterface $order_item): array {
    $purchased_entity_id = $order_item->getPurchasedEntityId();
    $purchased_entity_quantity = $order_item->getQuantity();

    if (!array_key_exists($purchased_entity_id, $entities_with_quantity_array)) {
      $entities_with_quantity_array[$purchased_entity_id] = '0';
    }

    $quantity_string = Calculator::add($purchased_entity_quantity, $entities_with_quantity_array[$purchased_entity_id]);
    $entities_with_quantity_array[$purchased_entity_id] = intval($quantity_string);

    return $entities_with_quantity_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getMostPurchasedEntity(DrupalDateTime $from, DrupalDateTime $to): ?ProductVariation {
    $orders = $this->getOrders($from, $to, TRUE);
    if ($orders) {
      $items_count_array = [];
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      foreach ($orders as $order) {
        $order_items = $order->getItems();
        foreach ($order_items as $order_item) {
          $items_count_array = $this->updatePurchasedEntitiesQuantity($items_count_array, $order_item);
        }
      }

      arsort($items_count_array, SORT_NUMERIC);
      $purchased_entity_id = array_key_first($items_count_array);

      return $this->productVariationStorage->load($purchased_entity_id);
    }

    return NULL;
  }

}

<?php

namespace Drupal\orders_assistant;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Interface for dealing with orders services.
 *
 * @package Drupal\orders_assistant.
 */
interface OrderAssistantServiceInterface {

  /**
   * Count orders by given period.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $from
   *   Date from.
   * @param \Drupal\Core\Datetime\DrupalDateTime $to
   *   Date to.
   *
   * @return int
   *   Return orders number.
   */
  public function getOrdersNumber(DrupalDateTime $from, DrupalDateTime $to): int;

  /**
   * Get average orders value by given period with user filter.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $from
   *   Date from.
   * @param \Drupal\Core\Datetime\DrupalDateTime $to
   *   Date to.
   * @param string $currency_code
   *   Currency code.
   * @param int|string $uid
   *   Order author id.
   *
   * @return \Drupal\commerce_price\Price
   *   Returns average orders value.
   */
  public function getAverageOrderValue(DrupalDateTime $from, DrupalDateTime $to, string $currency_code = 'USD', $uid = ''): Price;

  /**
   * Get most purchased entity by period.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $from
   *   Date from.
   * @param \Drupal\Core\Datetime\DrupalDateTime $to
   *   Date to.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariation
   *   Return purchased entity.
   */
  public function getMostPurchasedEntity(DrupalDateTime $from, DrupalDateTime $to): ?ProductVariation;

}

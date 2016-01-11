<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginManager
 */

namespace Drupal\sms_advanced\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages SMS routing rule types implemented using AnnotatedClassDiscovery.
 */
class SmsRoutingRulePluginManager extends DefaultPluginManager {

  /**
   * Creates a new SmsGatewayPluginManager instance.
   *
   * @param \Traversable $namespaces
   *   The namespaces to search for the gateway plugins.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler for calling module hooks.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SmsRoutingRule', $namespaces, $module_handler, 'Drupal\sms_advanced\Plugin\SmsRoutingRulePluginInterface', 'Drupal\sms_advanced\Annotation\SmsRoutingRule');
    $this->setCacheBackend($cache_backend, 'sms_routing_rule_type');
    $this->alterInfo('sms_routing_rule_type');
  }

}

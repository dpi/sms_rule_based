<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginCollection;
 */

namespace Drupal\sms_advanced\Plugin;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * Provides a container for lazily loading SMS routing rules plugins.
 */
class SmsRoutingRulePluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   */
  protected $pluginKey = 'type';

}

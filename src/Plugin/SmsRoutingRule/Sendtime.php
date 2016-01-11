<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Plugin\SmsRoutingRule\Recipients.
 */

namespace Drupal\sms_advanced\Plugin\SmsRoutingRule;

use Drupal\sms_advanced\Plugin\SmsRoutingRulePluginBase;

/**
 * @SmsRoutingRule(
 *   id = "sendtime",
 *   label = @Translation("Sending time"),
 *   description = @Translation("Time that the SMS message is being sent."),
 * );
 */
class Sendtime extends SmsRoutingRulePluginBase {

  public function getWidget() {
    return array(
      '#type' => 'date',
      '#date_format' => 'Y-m-d',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function match(array $numbers, array $context) {
    return $this->satisfiesExpression(REQUEST_TIME) ? $numbers : array();
  }

}

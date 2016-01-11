<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Plugin\SmsRoutingRule\User.
 */

namespace Drupal\sms_advanced\Plugin\SmsRoutingRule;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\sms_advanced\Plugin\SmsRoutingRulePluginBase;
use Drupal\user\Entity\User as UserEntity;

/**
 * @SmsRoutingRule(
 *   id = "sms_user",
 *   label = @Translation("SMS user"),
 *   description = @Translation("The user that is sending the SMS message."),
 * );
 */
class User extends SmsRoutingRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
//    $users = $this->value ? UserEntity::loadMultiple($this->value) : array();
    $users = $this->getOperand() ? UserEntity::loadMultiple($this->getOperand()) : [];
    $default_value = EntityAutocomplete::getEntityLabels($users);
    return [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
//      '#default_value' => $default_value,
//      '#columns' => 40,
//      '#rows' => 2,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function match(array $numbers, array $context) {
    return $this->satisfiesExpression(UserEntity::load($context['uid'])) ? $numbers : array();
  }

}

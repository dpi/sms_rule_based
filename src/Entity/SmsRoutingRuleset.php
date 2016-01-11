<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Entity\SmsRoutingRuleset
 */

namespace Drupal\sms_advanced\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\sms_advanced\Plugin\SmsRoutingRulePluginCollection;

/**
 * @ConfigEntityType(
 *   id = "sms_advanced_ruleset",
 *   label = @Translation("SMS Routing Ruleset"),
 *   handlers = {
 *     "form" = {
 *       "default" = "\Drupal\sms_advanced\Form\AdvancedRulesetForm",
 *       "delete" = "\Drupal\sms_advanced\Form\AdvancedRulesetDeleteForm",
 *     },
 *     "list_builder" = "\Drupal\sms_advanced\Form\AdvancedRulesetListForm",
 *   },
 *   admin_permission = "administer sms advanced",
 *   config_prefix = "ruleset",
 *   entity_keys = {
 *     "id" = "name",
 *     "weight" = "weight",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/sms_advanced/ruleset/edit/{sms_advanced_ruleset}",
 *     "delete-form" = "/admin/config/sms_advanced/ruleset/delete/{sms_advanced_ruleset}",
 *   },
 * );
 */
class SmsRoutingRuleset extends ConfigEntityBase implements EntityWithPluginCollectionInterface {

  /**
   * The name of the advanced routing ruleset.
   *
   * @var string
   */
  protected $name;

  /**
   * The name of the advanced routing ruleset.
   *
   * @var string
   */
  protected $label;

  /**
   * A description of what the routing ruleset does.
   *
   * @var string
   */
  protected $description;

  /**
   * The weight of the routing ruleset in the stack of rulesets.
   * 
   * @var float
   */
  protected $weight;

  /**
   * Whether this ruleset is enabled to run or not.
   *
   * @var boolean
   */
  protected $enabled;

  /**
   * The list of rules in this ruleset.
   * 
   * @var array
   */
  protected $rules = array();

  /**
   * All the rules must be true for the ruleset to apply.
   *
   * If false, any single rule that passes will allow the ruleset to apply.
   *
   * @var boolean
   */
  protected $_ALL_TRUE_;

  /**
   * Gateway for which this rule applies.
   *
   * @var string
   */
  protected $gateway;

  /**
   * The collection of the SMS routing rules in this ruleset.
   *
   * @var \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

  /**
   * @return \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginCollection
   */
  protected function getPluginCollection() {
    if (!isset($this->pluginCollection)) {
      $this->pluginCollection = new SmsRoutingRulePluginCollection(
        \Drupal::service('plugin.manager.sms_routing_rule'),
        $this->rules
      );
    }
    return $this->pluginCollection;
  }

  /**
   * Gets a SMS routing rule of specified name.
   *
   * @param string $name
   *   The name of the SMS routing rule.
   *
   * @return \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginInterface
   */
  public function getRule($name) {
    return $this->getPluginCollection()->get($name);
  }

  /**
   * Gets all the routing rules in this ruleset.
   *
   * @return \Drupal\sms_advanced\Plugin\SmsRoutingRulePluginCollection
   */
  public function getRules() {
    return $this->getPluginCollection();
  }

  public function addRule(array $rule) {
    $this->rules[$rule['name']] = $rule;
    $this->getPluginCollection()->addInstanceId($rule['name'], $rule);
  }

  public function removeRule($rule_name) {
    unset($this->rules[$rule_name]);
    $this->getPluginCollection()->removeInstanceId($rule_name);
  }

  public function setRules(array $rules) {
    $this->rules = $rules;
    $this->pluginCollection = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['rules' => $this->getPluginCollection()];
  }

}

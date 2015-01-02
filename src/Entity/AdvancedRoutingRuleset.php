<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Entity\AdvancedRoutingRuleset
 */

namespace Drupal\sms_advanced\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * @ConfigEntityType(
 *   id = "sms_advanced_ruleset",
 *   label = @Translation("Advanced Routing Ruleset"),
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
class AdvancedRoutingRuleset extends ConfigEntityBase {

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
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

}

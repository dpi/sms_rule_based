<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Form\AdvancedRulesetForm
 */

namespace Drupal\sms_advanced\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sms_advanced\Entity\AdvancedRoutingRuleset;
use Drupal\sms_advanced\Utility\AdvancedRouting;
use Drupal\sms_country\Utility\CountryCodes;

/**
 * Provides the form for configuring advanced ruleset rules.
 */
class AdvancedRulesetForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\sms_advanced\Entity\AdvancedRoutingRuleset $ruleset */
    $ruleset = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#description' => t('The name for this routing ruleset'),
      '#required' => true,
      '#attributes' => $ruleset->isNew() ? array() : array('disabled' => 'disabled'),
      '#default_value' => $ruleset->get('label'),
    );

    $form['name'] = array(
      '#type' => 'machine_name',
      '#title' => t('Name'),
      '#description' => t('The name for this routing ruleset'),
      '#required' => true,
      '#default_value' => $ruleset->get('name'),
      '#machine_name' => array(
        'source' => ['label'],
        'exists' => [$this, 'rulesetExists'],
      ),
    );

    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable this routing rule'),
      '#default_value' => $ruleset->get('enabled'),
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#description' => t('Description of what this routing ruleset does.'),
      '#default_value' => $ruleset->get('description'),
    );

    $form['routing'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced routing conditions'),
      '#collapsible' => true,
    );

    $form['routing']['_ALL_TRUE_'] = array(
      '#type' => 'checkbox',
      '#title' => t('All conditions must match'),
      '#default_value' => $ruleset->get('_ALL_TRUE_'),
    );

    // The table of all rule types and their values.
    $form['routing']['rules'] = array(
      '#type' => 'table',
      '#header' => array(),
//      '#tree' => true,
    );
    $defaults = $ruleset->get('rules');

    foreach ($this->getRuleTypes() as $type => $element) {
      $title = $element['#title'];
      $description = $element['#description'];
      unset($element['#title'], $element['#description']);

      $form['routing']['rules'][$type] = array(
        'enabled' => array(
          '#type' => 'checkbox',
        ),
        'title' => array(
          '#type' => 'inline_template',
          '#template' => '<div title="{{ description }}">{{ title }}</div>',
          '#context' => ['title' => $title, 'description' => $description],
        ),
        'operator' => array(
          '#type' => 'select',
          '#options' => AdvancedRouting::getOpTypes(),
          '#attributes' => ['title'=>AdvancedRouting::getOpTypesHelp()],
        ),
        'value' => $element + array(
          '#type'=>'textfield',
          '#title'=>ucfirst($type),
          '#title_display' => 'invisible',
          '#attributes' => array('style'=>'width:300px'), // @todo: inline styling
        ),
        'negate' => array(
          '#type' => 'checkbox',
          '#title' =>  $this->t('Negate'),
        ),
      );
      // Set default values if they exist.
      if (isset($defaults[$type])) {
        $form['routing']['rules'][$type]['enabled']['#default_value'] = $defaults[$type]['enabled'];
        $form['routing']['rules'][$type]['operator']['#default_value'] = $defaults[$type]['operator'];
        $form['routing']['rules'][$type]['value']['#default_value'] = $defaults[$type]['value'];
        $form['routing']['rules'][$type]['negate']['#default_value'] = $defaults[$type]['negate'];
      }
    }

    $form['selection'] = array(
      '#type' => 'fieldset',
      '#title' => t('Gateway to route SMS if conditions match'),
      '#collapsible' => true,
    );

    $gateway = $ruleset->get('gateway');
    $form['selection']['gateway'] = array(
      '#type' => 'select',
      '#title' => t('Gateway'),
      '#options' => sms_gateways('names'),
      '#default_value' => isset($gateway) ? $gateway : \Drupal::config('sms.settings')->get('sms_default_gateway'),
    );

    $form['weight'] = array(
      '#type' => 'weight',
      '#title' => t('Weight'),
      '#default_value' => $ruleset->get('weight'),
    );

    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    // In editing mode, add the delete button
    if (!$ruleset->isNew()) {
      $form['actions']['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete'),
        '#submit' => [$this, 'delete'],
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#value'] === $this->t('Save')) {
      // Make a ruleset from the form submissions.
      $new_ruleset = array();
      foreach ($form_state->getValue('rules') as $key => $rule) {
        // @todo Need to optimize this section as the creation is not necessary.
        if ($rule['enabled']) {
          if (empty($rule['value'])) {
            $form_state->setErrorByName("rules[$key][value]", $this->t('No expression assigned to the "@rule" rule.', ['@rule' => ucfirst($key)]));
          }
          $new_ruleset[$key] = array(
            'op' => $rule['operator'],
            'exp' => $rule['value'],
            'neg' => $rule['negate'],
          );
        }
        else {
          // Remove disabled rules.
          $form_state->unsetValue(['rules', $key]);
        }
      }
      if (!count($new_ruleset)) {
        $form_state->setErrorByName('label', $this->t('There must be at least one condition chosen.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('entity.sms_advanced_ruleset.list');
  }

  /**
   * {@inheritdoc}
   */
  function delete(array $form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#value'] == $this->t('Delete')) {
      // Redirect to confirm form for delete.
      $form_state->setRedirect('entity.sms_advanced_ruleset.delete_form', ['advanced_routing_ruleset' => $this->entity->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }
  }

  /**
   * Callback for machine_name validation.
   */
  public function rulesetExists($machine_name) {
    return (bool) AdvancedRoutingRuleset::load($machine_name);
  }

  /**
   * Provides the different rule types.
   *
   * @return array
   *
   * @todo rule types should be implemented as D8 plugins to allow extensibility.
   */
  protected function getRuleTypes() {
    return array(
      'user' => array(
        '#type' => 'textarea',
        '#title' => t('User'),
        '#description' => t('User that is sending the sms message.'),
        '#columns' => 40,
        '#rows' => 2,
      ),
      'sender' => array(
        '#type' => 'textarea',
        '#title' => t('Sender'),
        '#description' => t('The sender of the sms message.'),
        '#columns' => 40,
        '#rows' => 2,
      ),
      'count' => array(
        '#type' => 'textfield',
        '#title' => t('Recipients'),
        '#description' => t('The number of recipients of the sms message.'),
      ),
      /*'time' => array(
        '#type' => 'date',
        '#title' => t('Time'),
        '#date_format' => 'Y-m-d',
        '#description' => t('Time that the sms message is being sent.'),
  //      '#prefix' => '<div style="display:inline-block">',
  //      '#suffix' => '</div>',
      ),*/
      /*'group' => array(
        '#type' => 'textfield',
        '#title' => t('Group'),
      ),*/
      'country' => array(
        '#type' => 'select',
        '#options' => array('0' => '-- Select Country --') + CountryCodes::getCountryCodes(),
        '#title' => t('Country'),
        '#description' => t('The destination country of the sms message'),
        '#columns' => 40,
        '#rows' => 2,
      ),
      'area' => array(
        '#type' => 'textarea',
        '#description' => t('Area code (the 3 digits immediately following the country code)'),
        '#title' => t('Area Code'),
        '#columns' => 40,
        '#rows' => 2,
      ),
      'number' => array(
        '#type' => 'textarea',
        '#title' => t('Number'),
        '#description' => t('The recipient number of the sms message'),
        '#columns' => 40,
        '#rows' => 2,
      ),
    );
  }

}

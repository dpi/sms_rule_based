<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Form\SmsRouteListForm
 */

namespace Drupal\sms_advanced\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class AdvancedRulesetListForm extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_advanced_ruleset_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return array(
      'enabled' => $this->t('Enable'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'rules' => $this->t('Rules'),
      'gateway' => $this->t('Gateway'),
    ) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    return array (
      'enabled' => array(
        '#type' => 'checkbox',
        '#title' => t('@title enabled', array('@title' => $entity->label())),
        '#title_display' => 'invisible',
        '#default_value' => TRUE == $entity->get('enabled'),
      ),
      'name' => ['#markup' => $entity->label()],
      'description' => ['#markup' => $entity->get('description')],
      'rules' => [
        '#theme' => 'sms_advanced_routing_rules',
        '#ruleset' => $entity,
      ],
      'gateway' => ['#markup' => $this->gatewayManager()->getGateway($entity->get('gateway'))->getLabel()],
    ) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $default = \Drupal::config('sms_advanced.settings')->get('enable_advanced_routing');
    $form['enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable advanced routing'),
      '#default_value' => (bool) $default ? $default : false,
      '#weight' => 0,
    );
    // Place the table below the checkbox.
    $form[$this->entitiesKey]['#weight'] = 1;
//    $form[$this->entitiesKey]['#title'] = $this->t('Advanced routing rulesets');
    $footer = array(
      array(
        t('Default Gateway'),
        t('All sms that don\'t match above rules will go through the default.'),
        $this->gatewayManager()->getDefaultGateway()->getLabel(),
        \Drupal::l($this->t('change default gateway'),
          new Url('sms.gateway_admin', [], [
            'query' => ['destination' => UrlHelper::encodePath(\Drupal::request()->getPathInfo())],
        ])),
      ),
    );

    $form['footer'] = array(
      '#type' => 'table',
      '#header' => array(
        [
          'data' => ['#markup' => $this->t('Default gateway')],
          'colspan' => 4,
        ]
      ),
      '#rows' => $footer,
      '#title' => $this->t('Default Gateway'),
      '#weight' => 2,
    );
    $form['actions']['submit']['#value'] = $this->t('Save configuration');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Update weights and status.
    if ($settings = $form_state->getValue($this->entitiesKey)) {
      foreach ($settings as $id => $value) {
        $changed = FALSE;
        if (isset($this->entities[$id]) && $this->entities[$id]->get($this->weightKey) != $value['weight']) {
          // Save entity only when its weight was changed.
          $this->entities[$id]->set($this->weightKey, $value['weight']);
          $changed = TRUE;
        }
        if (isset($this->entities[$id]) && $this->entities[$id]->get('enabled') != $value['enabled']) {
          // Save entity only when its weight was changed.
          $this->entities[$id]->set('enabled', $value['enabled']);
          $changed = TRUE;
        }
        if ($changed) {
          $this->entities[$id]->save();
        }
      }
    }
    $this->configFactory()->getEditable('sms_advanced.settings')->set('enable_advanced_routing', $form_state->getValue('enable'))->save();
  }


  /**
   * Returns the configuration factory service.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected function configFactory() {
    return \Drupal::configFactory();
  }

  /**
   * Returns the SMS gateway manager.
   *
   * @return \Drupal\sms\Gateway\SmsGatewayPluginManagerInterface
   */
  protected function gatewayManager() {
    return \Drupal::service('plugin.manager.sms_gateway');
  }

}

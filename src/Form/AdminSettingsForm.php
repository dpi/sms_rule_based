<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Form\AdminSettingsForm
 */

namespace Drupal\sms_advanced\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_ui_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sms_advanced.settings');
    $sender_id_security = $config->get('sender_id_security');
    $form['sender_id_security'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender ID Security'),
      '#tree' => TRUE,
      '#collapsible' => TRUE,
    );

    $form['sender_id_security']['include_superuser'] = array(
      '#type' => 'checkbox',
      '#title' => t('Include superuser (user #1) in sender id blocking (for testing purposes)'),
      '#cols' => 40,
      '#default_value' => $sender_id_security['include_superuser'],
    );

    $form['sender_id_security']['excluded'] = array(
      '#type' => 'textarea',
      '#title' => t('Excluded sender ids'),
      '#description' => t('Comma separated list of sender ids that are not allowed for general use.
    		Wildcard \'%\' can be used to represent any character of any length.'),
      '#cols' => 40,
      '#default_value' => $sender_id_security['excluded'],
    );

    $form['sender_id_security']['included'] = array(
      '#type' => 'textarea',
      '#title' => t('Sender ids allowed for specific users'),
      '#description' => t('List of specific users and sender ids in the excluded list which they are allowed to use.
    		Format <em>user1: senderID1, senderID2, senderID3; user2: senderIDx, senderIDy, senderIDz, etc; user3, user4, user5: senderID4</em>.
    		Use \'*\' instead of username to apply exception to all users.'),
      '#cols' => 40,
      '#default_value' => $sender_id_security['included'],
    );

    $form['queue'] = array(
      '#type' => 'fieldset',
      '#title' => t('SMS Message Queuing Options'),
      '#tree' => TRUE,
      '#collapsible' => TRUE,
    );

    $queue = $config->get('queue');
    $form['queue']['treshold'] = array(
      '#type' => 'textfield',
      '#title' => t('Queue messages for more than'),
      '#description' => t('Maximum number of sms recipients for which queuing will not be applied (0 means queue always, -1 means never queue).'),
      '#field_suffix' => t('recipients'),
      '#cols' => 40,
      '#default_value' => $queue['treshold'],
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    $this->config('sms_advanced.settings')
      ->set('sender_id_security', $form_state->getValue('sender_id_security'))
      ->set('queue', $form_state->getValue('queue'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sms_advanced.settings'];
  }
}

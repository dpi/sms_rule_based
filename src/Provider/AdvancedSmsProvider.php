<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\AdvancedSmsProvider.
 */

namespace Drupal\sms_advanced\Provider;

use Drupal\sms\Gateway\SmsGatewayPluginInterface;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Provider\DefaultSmsProvider;
use Drupal\sms_advanced\Utility\AdvancedRouting;

/**
 * An advanced SMS service provider.
 *
 * This SMS service provider can be used when more advanced functionality is
 * needed.
 * This provider includes the following additional functionality:
 * - SMS queuing using the built in Drupal Queue System. It also provides an API
 *   for querying the queue status. @see SmsMessageQueueInterface
 * - SMS routing through various gateways based on user-configured routing rules.
 *   It provides a UI for building and managing routing rules.
 */
class AdvancedSmsProvider extends DefaultSmsProvider {

  /**
   * {@inheritdoc}
   */
  protected function preProcess(SmsMessageInterface $sms, array $options, GatewayInterface $gateway) {
    $continue = parent::preProcess($sms, $options, $gateway);
    // Call sms_advanced pre- and post-send hooks.
    // @todo Deprecate this later and use the smsframework hooks.
    return $continue && !in_array(FALSE, sms_ui_invoke('preprocess_sms_advanced_send', $sms, $options), TRUE);
  }

  protected function process(SmsMessageInterface $sms, array $options, GatewayInterface $gateway) {
    $options += $sms->getOptions();
    $routing = static::routeMessage($sms);
    return $this->sendRouted($sms, $routing, $options);
  }

  protected function sendRouted(SmsMessageInterface $sms, array $routing, array $options) {
    $response = [];
    $log_message = [];
    foreach ($routing['routes'] as $gateway_id => $numbers) {
      if ($numbers) {
        $routed_sms = new SmsMessage($sms->getSender(), $numbers, $sms->getMessage(), $sms->getOptions(), $sms->getUid());;
        if ($gateway_id === '__default__') {
          $gateway = $this->gatewayManager->getDefaultGateway();
        }
        else {
          $gateway = $this->gatewayManager->getGateway($gateway_id);
        }

        $sent = $gateway->send($routed_sms, $options);
        if ($sent) {
          $counts[$gateway->getName()] = count($numbers);
          $size = count($sent->getReport());
          $log_message[] = t('@gateway: @size of @counts',
            ['@gateway' => $gateway->getLabel(), '@size' => $size, '@count' => $counts[$gateway->getName()]]);
        }
        if (is_array($sent)) {
          $response += $sent;
        }
      }
    }
    return $response;
  }

  // @todo: change this to protected once all usages of
  // _sms_advanced_process_send() have been converted.
  public static function routeMessage(SmsMessageInterface $sms) {
    $report = array();
    // Implement filtering of numbers based on advanced routing rules.
    if (\Drupal::config('sms_advanced.settings')->get('enable_advanced_routing')) {
      $routing = AdvancedRouting::routeSmsRecipients($sms);
    }
    else {
      $routing = [
        'routes' => [
          '__default__' => $sms->getRecipients(),
        ],
      ];
    }
    return $routing;
  }

  // @todo implement queuing sms later.
  protected function queueSms(SmsMessageInterface $sms, array $options) {
    // We're sending so this message shouldn't be marked as queued anymore.
    if ($sms->queued) {
      unset($sms->queued);
      _sms_ui_set_cached($sms);	// Update the status in the cached copy
    }

    // Implement queuing for items based on number of recipients or explicit
    // specification.
    $queue_config = \Drupal::config('sms_advanced.settings')->get('queue');
    $treshold = isset($queue_config['treshold']) ? $queue_config['treshold'] : 200;
    if ($treshold == -1 || $treshold == 0) {
      $sms->options['queue'] = ($treshold == FALSE);
    }

    if (isset($sms->options['queue'])) {
      $queue_sms = ($sms->options['queue'] == TRUE);
    }
    else {
      $queue_sms = count($sms->recipients) > $treshold;
    }

    if ($queue_sms) {
      // Mark that this sms has been queued for sending.
      // @todo: Consider using a dedicated queue per each message being sent.
      $queue_service = \Drupal::queue('sms_advanced');

      // @todo: Use sms::setQueuedTime() instead of the boolean flag.
      $sms->queued = TRUE;
//      _sms_ui_set_cached($sms);
//      $queue = drupal_queue_get('sms_ui_queue');
//      $queue->createQueue();
      $item = $queue_service->createItem($sms);

      // Create short-term $info object just for logging, we don't want to alter $sms object
      // and we don't need the whole recipients array - just the number of recipients
      $info = clone $sms;
      unset($info->recipients);
      $info->recipients = count($sms->recipients);
      \Drupal::logger('sms_ui')->notice('Queued message for sending: <pre>%sms</pre>', array('%sms'=> var_export($info, TRUE)));
      return TRUE;
    }
    else {
      // If not to be queued, send immediately
      _sms_ui_set_cached($sms);
      return static::routeMessage($sms);
    }
  }


  public static function splitSend($numbers, $message, $options, $uuid, $maxsize=200) {
    // Split the $numbers into smaller batches
    $report = array();
    while (count($numbers) > 0) {
      $slice = array_slice($numbers, 0, $maxsize);
      $numbers = array_slice($numbers, $maxsize);
      if ($slice) {
        // Send split sms and dump report in database
        if ($rep = sms_send(implode(',', $slice), $message, $options)) {
          $report += (array) $rep;
          // Update cached sms
          $sms = &_sms_ui_get_cached($uuid);
          $sms->recipients = (array) $rep + $sms->recipients;
          _sms_ui_set_cached($sms);
        }

        // Insert current progress database
        db_query("INSERT INTO {sms_ui_sent_batch} (uuid, recipients, report) VALUES ('%s', %d, '%s')",
          $uuid, count($slice), serialize($rep));
      }
    }
    return $report;
  }

  // @todo: Implement tokenization of message.
  protected function tokenizeMessage() {
    // Check if message has tokens for optimization
    if (preg_match('/\[[^\]]+\]/', $message) && $number != '' && module_exists('token')) {  // Is tokenized
      if (FALSE) {  // Is tokenized
        $nums = $number;
        $res = db_query(db_rewrite_sql('SELECT * FROM {sms_ui_number} n WHERE mobile IN (%s) AND uid = %d', 'sms_ui_number', 'ctid', 'contacts_info'), $nums, $user->uid);
        while ($contact = db_fetch_object($res)) {
          $msg = token_replace($message, 'sms_ui_bulk', $contact);
          if (($report[] = sms_send($contact->mobile, $msg, $options)) === FALSE) {
            drupal_set_message(t('Sorry, could not send tokenized message to @mobile. Skipping.', (array) $contact), 'warning');
          }
          else {
            // Remove number from original list
            $nums = preg_replace('/(,' . $contact->mobile . ')|(' . $contact->mobile . ',)|(' . $contact->mobile . ')/', '', $nums);
          }
        }
        if (trim($nums, " ,\n\r\t\0\x0B") != '') {
          $msg = preg_replace('/(\[[^\]]*\])/', '', $message);
          if (($report[] = sms_send($nums, $msg, $options)) === FALSE) {
            drupal_set_message(t('Sorry, could not send tokenized message to @mobile. Skipping.', array('@mobile' => $nums)), 'warning');
          }

        }
      }
      else {    // Not tokenized
      }
    }
  }

}

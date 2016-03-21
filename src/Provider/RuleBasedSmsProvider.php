<?php

/**
 * @file
 * Contains \Drupal\sms_rule_based\RuleBasedSmsProvider.
 */

namespace Drupal\sms_rule_based\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Provider\DefaultSmsProvider;
use Drupal\sms_rule_based\Entity\SmsRoutingRuleset;

/**
 * An SMS service provider that routes SMS based on user-configured rules.
 *
 * It provides a UI for building and managing routing rules.
 */
class RuleBasedSmsProvider extends DefaultSmsProvider {

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms, array $options = []) {
    $options += $sms->getOptions();
    $routing = $this->routeMessage($sms);

    $results = [];
    $log_message = [];
    $routed_sms = new SmsMessage($sms->getSender(), [], $sms->getMessage(), $sms->getOptions(), $sms->getUid());
    foreach ($routing['routes'] as $gateway_id => $numbers) {
      if ($numbers) {
        if ($gateway_id === '__default__') {
          $gateway = $this->getDefaultGateway();
        }
        else {
          $gateway = SmsGateway::load($gateway_id);
        }
        $routed_sms->addRecipients($numbers);
        if ($this->preProcess($routed_sms, $options, $gateway)) {
          $this->moduleHandler->invokeAll('sms_send', [$routed_sms, $options, $gateway]);
          // @todo Apply token replacements.
          $result = $results[$gateway_id] = $this->process($routed_sms, $options, $gateway);
          $this->postProcess($routed_sms, $options, $gateway, $result);

          if ($result) {
            $counts[$gateway_id] = count($numbers);
            $size = count($result->getReports());
            $log_message[] = new TranslatableMarkup('@gateway: @size of @counts',
              [
                '@gateway' => $gateway->label(),
                '@size' => $size,
                '@count' => $counts[$gateway_id]
              ]);
          }
        }
        $routed_sms->removeRecipients($numbers);
      }
    }
    return $this->mergeMessageResults($results);
  }

  /**
   * Uses the rule-based routing service to route recipients through SMS gateways.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms
   *
   * @return array
   */
  protected function routeMessage(SmsMessageInterface $sms) {
    if (\Drupal::config('sms_rule_based.settings')->get('enable_rule_based_routing')) {
      // Get rulesets and sort them in order so the first ruleset to match is
      // implemented.
      /** @var \Drupal\sms_rule_based\Entity\SmsRoutingRuleset[] $rulesets */
      $rulesets = SmsRoutingRuleset::loadMultiple();
      // @todo this sorting needs to be checked.
      uasort($rulesets, function(SmsRoutingRuleset $ruleset1, SmsRoutingRuleset $ruleset2) {
        $weight1 = $ruleset1->get('weight');
        $weight2 = $ruleset2->get('weight');
        return ($weight1 > $weight2) ? 1 : ($weight1 == $weight2 ? 0 : -1);
      });
      $routing = \Drupal::service('sms_rule_based.sms_router')->routeSmsRecipients($sms, $rulesets);
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

  /**
   * Merges multiple SMS message results into one result.
   *
   * @param \Drupal\sms\Message\SmsMessageResultInterface[] $results
   *
   * @return \Drupal\sms\Message\SmsMessageResultInterface
   *   A single message result consisting of merger of all those in $results.
   */
  public function mergeMessageResults(array $results) {
    $data = [
      'status' => TRUE,
      'credit_balance' => 0,
      'credits_used' => 0,
      'error_message' => '',
      'reports' => [],
    ];
    foreach ($results as $sms_result) {
      $data['status'] &= $sms_result->getStatus();
      $data['credit_balance'] = $sms_result->getBalance();
      $data['credits_used'] += $sms_result->getCreditsUsed();
      $data['error_message'] = $sms_result->getErrorMessage();
      $data['reports'] += $sms_result->getReports();
    }
    return new SmsMessageResult($data);
  }

}

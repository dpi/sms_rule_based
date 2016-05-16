<?php

namespace Drupal\sms_rule_based\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Event\SmsMessageEvent;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms_rule_based\Entity\SmsRoutingRuleset;

class RuleBasedSmsMessageProcessor implements EventSubscriberInterface {

  public function ruleBased(SmsMessageEvent $event) {
    $result = [];

    foreach ($event->getMessages() as &$sms) {
      $routing = $this->routeMessage($sms);

      $base = $sms instanceof SmsMessageInterface ? $sms->createDuplicate() : (clone $sms);
      $base->removeRecipients($sms->getRecipients());

      foreach ($routing['routes'] as $gateway_id => $numbers) {
        if ($numbers) {
          $new = $base instanceof SmsMessageInterface ? $base->createDuplicate() : (clone $base);
          $new->addRecipients($numbers);
          if ($gateway_id != '__default__') {
            $new->setGateway(SmsGateway::load($gateway_id));;
          }

          $result[] = $new;
        }
      }
    }

    $event->setMessages($result);
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['sms.message.preprocess'][] = ['ruleBased', 1025];
    return $events;
  }

}

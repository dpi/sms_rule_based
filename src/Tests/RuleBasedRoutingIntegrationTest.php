<?php

/**
 * @file
 * Contains \Drupal\sms_rule_based\Tests\RuleBasedRoutingIntegrationTest
 */

namespace Drupal\sms_rule_based\Tests;

use Drupal\Core\Url;
use Drupal\simpletest\WebTestBase;

/**
 * Integration tests for rule-based routing of SMS.
 *
 * @group SMS Rule Based
 */
class RuleBasedRoutingIntegrationTest extends WebTestBase {

  public static $modules = ['sms', 'sms_rule_based'];

  public function testRuleBasedRoutingRules() {
    $user = $this->drupalCreateUser(['administer rule-based routing']);
    $this->drupalLogin($user);
    $this->drupalGet(new Url('entity.sms_routing_ruleset.list'));

    // Enable rule-based routing.
    $this->drupalPostForm(NULL, ['enable' => TRUE], 'Save configuration');

    // Uncomment this when local task and local action block placement is
    // available in tests.
//    $this->clickLinkPartialName('Add ruleset');

    $edit = [
      'label' => trim($this->randomString()),
      'name' => strtolower($this->randomMachineName()),
      'weight' => -2,
      'gateway' => 'log',
      'rules[user][enabled]' => TRUE,
      'rules[user][operator]' => 'EQ',
      'rules[user][value]' => $user->getUsername(),
    ];
    $this->drupalPostForm(new Url('entity.sms_routing_ruleset.add_form'), $edit, 'Save');
    $this->assertResponse(200);

    // Test that posting without rules causes validation errors.
    $edit = [
      'label' => trim($this->randomString()),
      'name' => strtolower($this->randomMachineName()),
      'weight' => -2,
      'gateway' => 'log',
    ];
    $this->drupalPostForm(new Url('entity.sms_routing_ruleset.add_form'), $edit, 'Save');
    $this->assertText('There must be at least one condition chosen.');
  }

}

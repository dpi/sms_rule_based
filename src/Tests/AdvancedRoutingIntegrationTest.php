<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Tests\AdvancedRoutingIntegrationTest
 */

namespace Drupal\sms_advanced\Tests;

use Drupal\Core\Url;
use Drupal\simpletest\WebTestBase;

/**
 * Integration tests for Advanced routing of SMS.
 *
 * @group SMS Advanced
 */
class AdvancedRoutingIntegrationTest extends WebTestBase {

  public static $modules = ['sms', 'sms_advanced'];

  public function testAdvancedRoutingRules() {
    $user = $this->drupalCreateUser(['administer advanced routing']);
    $this->drupalLogin($user);
    $this->drupalGet(new Url('entity.sms_advanced_ruleset.list'));

    // Enable advanced routing.
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
    $this->drupalPostForm(new Url('entity.sms_advanced_ruleset.add_form'), $edit, 'Save');
    $this->assertResponse(200);

    // Test that posting without rules causes validation errors.
    $edit = [
      'label' => trim($this->randomString()),
      'name' => strtolower($this->randomMachineName()),
      'weight' => -2,
      'gateway' => 'log',
    ];
    $this->drupalPostForm(new Url('entity.sms_advanced_ruleset.add_form'), $edit, 'Save');
    $this->assertText('There must be at least one condition chosen.');
  }

}

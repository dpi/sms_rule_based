<?php

/**
 * @file
 * Contains \Drupal\Tests\sms_advanced\Unit\Entity\SmsRoutingRulesetTest
 */

namespace Drupal\Tests\sms_advanced\Unit\Utility;

use Drupal\Component\Uuid\Php;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms_advanced\Entity\SmsRoutingRuleset;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\sms_advanced\Entity\SmsRoutingRuleset
 * @group SMS Advanced
 */
class SmsRoutingRulesetTest extends UnitTestCase {

  protected $entityStorage;

  public function setUp() {
    parent::setUp();

    // Mock the entity manager.
    $this->entityStorage = $this->prophesize(EntityStorageInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_repository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $entity_type_manager->getStorage(NULL)->willReturn($this->entityStorage);
    $entity_type_repository->getEntityTypeFromClass(SmsRoutingRuleset::class)->willReturn(NULL);

    // Set up the container.
    $container = new ContainerBuilder();
    $container->set('uuid', new Php());
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('entity_type.repository', $entity_type_repository->reveal());

    $entity_manager = new EntityManager();
    $entity_manager->setContainer($container);
    $container->set('entity.manager', $entity_manager);
    \Drupal::setContainer($container);
  }

  /**
   * @dataProvider providerAdvancedRoutingRulesets
   */
  public function testAdvancedRoutingRulesets(array $rulesets, array $numbers, array $routed_array) {
    // @todo Need to refactor this test method signature to do more rulesets.
    // Set up return value for entity storage stub.
    $this->entityStorage->loadMultiple(NULL)->will(function() use ($rulesets) {
      $return_val = [];
      foreach ($rulesets as $name => $ruleset) {
        $return_val[$name] = new SmsRoutingRuleset($ruleset, 'sms_advanced_ruleset');
      }
      return $return_val;
    });
    $sms = new SmsMessage('sender', $numbers, 'test message', [], 1);
    $routing = \Drupal\sms_advanced\AdvancedSmsRouting::routeSmsRecipients($sms);
    $this->assertEquals($routing['routes']['42tele'], $routed_array);
    $this->assertNotContains($routed_array[0], $routing['routes']['__default__']);
  }

  public function providerAdvancedRoutingRulesets() {
    return [
      [[$this->rulesets['cdma']], ['2348191234500', '2348101234500', '2348171234500', '2348031234500'], ['2348191234500']],
    ];
  }

  protected $rulesets = [
    "cdma" => [
      "name" => "cdma",
      "enabled" => 1,
      "description" => "",
      "rules" => [
        '_ALL_TRUE_' => TRUE,
        'number' => [
          'op' => 'LK',
          'neg' => FALSE,
          'exp' => '234819%,234704%,234702%,234709%,234707%',
        ],
      ],
      "gateway" => "42tele",
      "weight" => "-4"
    ],
    "debug" => [
      "name" => "debug",
      "enabled" => 1,
      "description" => "Used for quick debugging purposes. Any message with sender id \"debug\" is sent through the debug gateway so as to avoid running down credit.",
      "rules" => [
        '_ALL_TRUE_' => FALSE,
        'sender' => [
          'op' => 'EQ',
          'neg' => '',
          'exp' => 'debug',
        ],
      ],
      "gateway" => "log",
      "weight" => "-8",
    ],
    "yello_spam" => [
      "name" => "yello_spam",
      "enabled" => 0,
      "description" => "Catch the Y'ello spammers and re-route to debug. But only do so when actually sending bulk (recipients > 200), so that they will not get bounced when testing",
      "rules" => [
        '_ALL_TRUE_' => TRUE,
        'sender' => [
          'op' => 'RX',
          'neg' => '',
          'exp' => 'Y[\' "]*[e3][l1]+[o0]+.*5',
        ],
        '' => [
          'op' => '02',
          'neg' => '',
          'exp' => '0',
        ],
      ],
      "gateway" => "debug",
      "weight" => "-2",
    ],
    "airtel" => [
      "name" => "airtel",
      "enabled" => 0,
      "description" => "Use 42 Telecom for Airtel numbers",
      "rules" => [
        '_ALL_TRUE_' => '',
        'number' => [
          'op' => 'LK',
          'neg' => '',
          'exp' => '234708%,234802%,234808%,234812%',
        ],
      ],
      "gateway" => "idigital",
      "weight" => "-1",
    ],
    "etisalat_gateway" => [
      "name" => "etisalat_gateway",
      "enabled" => 0,
      "description" => "",
      "rules" => [
        '_ALL_TRUE_' => '',
        'number' => [
          'op' => 'LK',
          'neg' => '',
          'exp' => '234809%,234817%,234818%',
        ],
      ],
      "gateway" => "idigital",
      "weight" => "-7",
    ],
    "mtn_gateway" => [
      "name" => "mtn_gateway",
      "enabled" => 0,
      "description" => "",
      "rules" => [
        '_ALL_TRUE_' => FALSE,
        'user' => [
          'op' => 'EQ',
          'neg' => '',
          'exp' => 'nattah',
        ],
        'number' => [
          'op' => 'LK',
          'neg' => '',
          'exp' => '234703%,234706%,234803%,234806%,234810%,234813%,234816%,234809%,234817%,234818%',
        ],
      ],
      "gateway" => "idigital",
      "weight" => "-6",
    ],
    "glo_gateway" => [
      "name" => "glo_gateway",
      "enabled" => 0,
      "description" => "",
      "rules" => [
        '_ALL_TRUE_' => '',
        'number' => [
          'op' => 'LK',
          'neg' => '',
          'exp' => '234705%,234805%,234807%,234815%,234811%',
        ],
      ],
      "gateway" => "routesms",
      "weight" => "-3",
    ],
    "spammers" => [
      "name" => "spammers",
      "enabled" => 1,
      "description" => "Route to send spammers to debug gateway",
      "rules" => [
        '_ALL_TRUE_' => '1',
        'user' => [
          'op' => 'IN',
          'neg' => '',
          'exp' => 'Godslovee, gur kimhi',
        ],
        'count' => [
          'op' => 'GT',
          'neg' => '',
          'exp' => '20',
        ],
        'country' => [
          'op' => 'EQ',
          'neg' => '1',
          'exp' => '234',
        ],
      ],
      "gateway" => "debug",
      "weight" => "-9",
    ],
    "international" => [
      "name" => "international",
      "enabled" => 1,
      "description" => "International SMS through 42tele",
      "rules" => [
        '_ALL_TRUE_' => '',
        'country' => [
          'op' => 'EQ',
          'neg' => '1',
          'exp' => '234',
        ],
      ],
      "gateway" => "42tele",
      "weight" => "-5",
    ],
    "routesms_test" => [
      "name" => "routesms_test",
      "enabled" => 1,
      "description" => "Tests routesms gateway by passing all webmaster's traffic through it.",
      "rules" => [
        '_ALL_TRUE_' => '1',
        'user' => [
          'op' => 'EQ',
          'neg' => '',
          'exp' => 'nattah',
        ],
      ],
      "gateway" => "42tele",
      "weight" => "-10",
    ],
    "boku_app" => [
      "name" => "boku_app",
      "enabled" => 1,
      "description" => "Allow BokuApp through Infobip",
      "rules" => [
        '_ALL_TRUE_' => '',
        'user' => [
          'op' => 'EQ',
          'neg' => '',
          'exp' => 'boku',
        ],
      ],
      "gateway" => "infobip",
      "weight" => "-10",
    ],
  ];

}

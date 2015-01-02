<?php

/**
 * @file
 * Contains \Drupal\sms_advanced\Utility\AdvancedRouting
 */

namespace Drupal\sms_advanced\Utility;

use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms_advanced\Entity\AdvancedRoutingRuleset;
use Drupal\sms_country\Utility\CountryCodes;

class AdvancedRouting {

  /**
   * Carries out advanced routing of numbers based on established routing rules.
   *
   * This function takes the recipient numbers of the sms object supplied and
   * returns the numbers that don't match the rules specified in the advanced
   * routing rulesets, while returning matches and the corresponding gateways in
   * the inline array supplied.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms
   *   The sms message object.
   *
   * @return array
   *   An array consisting of the following elements:
   *   - routes: an array keyed by the gateway name that best fits the routing
   *     rules with each value being an array of numbers that would be passed
   *     through that gateway. The list of unrouted numbers (i.e. to be passed
   *     through the default gateway) is keyed by '__default__'.
   *   - order: an array keyed by the ruleset names that were applied and with
   *     the array of matching numbers as the value.
   */
  public static function routeSmsRecipients(SmsMessageInterface $sms) {
    // Get rulesets and sort them in order so the first ruleset to match is implemented.
    /** @var \Drupal\sms_advanced\Entity\AdvancedRoutingRuleset[] $rulesets */
    $rulesets = AdvancedRoutingRuleset::loadMultiple();
    // @todo this sorting needs to be checked.
    uasort($rulesets, function(AdvancedRoutingRuleset $ruleset1, AdvancedRoutingRuleset $ruleset2) {
      $weight1 = $ruleset1->get('weight');
      $weight2 = $ruleset2->get('weight');
      return ($weight1 > $weight2) ? 1 : ($weight1 == $weight2 ? 0 : -1);
    });

    $numbers = $sms->getRecipients();
    $extra = $sms->getOptions();
    $extra['message'] = $sms->getMessage();
    $extra['sender'] = $sms->getSender();

    foreach ($rulesets as $ruleset) {
      if ($ruleset->get('enabled') && $matches = static::matchRoute($ruleset->get('rules'), $numbers, $extra)) {
        if (!isset($routing['routes'][$ruleset->get('gateway')])) {
          $routing['routes'][$ruleset->get('gateway')] = array();
        }
        // New matches should be merged with previous for that gateway.
        $routing['routes'][$ruleset->get('gateway')] += $matches;
        $routing['order'][$ruleset->get('name')] = $matches;
      }
    }
    $routing['routes']['__default__'] = $numbers;
    $routing['order']['__default__'] = $numbers;
    return $routing;
  }

  /**
   * Matches the destination numbers to the correct gateways.
   *
   * This function is the ruleset matching engine. It takes rules and an array
   * of numbers as parameters and returns an array of gateways that would match
   * the numbers based on the rules defined. The numbers that don't match any
   * rule are returned in the __default__ gateway key.
   *
   * @param array $rules
   *   Compacted rules to match.
   * @param array $numbers
   *   List of numbers to be matched against the rules.
   * @param $extra
   *   Additional contextual information.
   *
   * @return array
   *   An array keyed by gateway id and array of recipient numbers to be sent
   *   via that gateway based on specified rulesets.
   */
  public static function matchRoute(array $rules, array &$numbers, $extra) {
    $all_true = !empty($rules['_ALL_TRUE_']);
    unset($rules['_ALL_TRUE_']);

    // Run through all the rules
    if ($all_true) {
      $ret = $numbers;
      foreach ($rules as $type=>$rule) {
        $ret = array_intersect($ret, static::matchRuleType($numbers, $type, $rule['op'], $rule['neg'], $rule['exp'], $extra));
      }
    }
    else {
      $ret = array();
      foreach ($rules as $type => $rule) {
        $ret = array_merge($ret, static::matchRuleType($numbers, $type, $rule['operator'], $rule['negate'], $rule['value'], $extra));
      }
      $ret = array_unique($ret);  // Remove duplicates
    }
    // Remove matching numbers from original array.
    // Use array_values to re-index the array.
    $numbers = array_values(array_diff($numbers, $ret));
    return $ret;
  }

  /**
   * Matches number using a specific rule type.
   *
   * @param array $numbers
   *   An array of numbers to be matched.
   *
   * @param string $type
   *   The rule type to be used for matching.
   *
   * @param string $op
   *   The operation to be used for matching as detailed in evaluateRule().
   *
   * @param bool $neg
   *   Whether the result should be negated (inverted).
   *
   * @param $exp
   *   The rule expression to evaluate.
   *
   * @param array $extra
   *   Additional information needed for evaluation.
   *
   * @return array
   *   The subset of $numbers that match the rule.
   *
   * @see \Drupal\sms_advanced\Utility\AdvancedRouting::evaluateRule()
   */
  protected static function matchRuleType($numbers, $type, $op, $neg, $exp, $extra) {
    if ($type == 'user') {
      return static::evaluateRule(\Drupal::currentUser()->name, $op, $exp, $neg) ? (array) $numbers : array();
    }
    else if ($type == 'sender') {
      return static::evaluateRule($extra['sender'], $op, $exp, $neg) ? (array) $numbers : array();
    }
    else if ($type == 'count') {
      return static::evaluateRule(count($numbers), $op, $exp, $neg) ? (array) $numbers : array();
    }
    else {
      $return_numbers = array();
      foreach ($numbers as $k => $number) {
        if ($type == 'country') {
          if (static::evaluateRule(CountryCodes::getCountryCode($number), $op, $exp, $neg)) $return_numbers[] = $number;
        }
        else if ($type == 'number') {
          if (static::evaluateRule($number, $op, $exp, $neg)) $return_numbers[] = $number;
        }
        else if ($type == 'area') {
          $start = strlen(CountryCodes::getCountryCode($number));
          // This would have worked except for the IN operation where the operand
          // $exp is many in one.
          // if (static::evaluateRule(substr($num, $start, strlen($exp)), $op, $exp, $neg)) $retnums[] = $num;
          // Find a better way to implement variable length prefixes like 7025,
          // 702, 704, etc.
          if (static::evaluateRule(substr($number, $start, 3), $op, $exp, $neg)) {
            $return_numbers[] = $number;
          }
        }
      }
      return $return_numbers;
    }
  }

  /**
   * @param string $param
   *   The parameter to be compared or evaluated.
   *
   * @param string $op
   *   The operation to be used for comparison. It could be on of the following:
   *   - 'EQ': TRUE if $param is equal to $exp
   *   - 'LT': TRUE if $param is less than $exp
   *   - 'LE': TRUE if $param is less than or equal to $exp
   *   - 'GT': TRUE if $param is greater than $exp
   *   - 'GE': TRUE if $param is greater than or equal to $exp
   *   - 'IN': TRUE if $param is found within $exp ($exp would then be a comma-
   *      separated list
   *   - 'LK': TRUE if $param matches a wildcard-type expression in $exp
   *   - 'RX': TRUE if $param matches the regular expression in $exp
   *
   * @param string $exp
   *   The expression or value to be used to compare the parameter.
   *
   * @param bool $neg
   *   Whether the result is to be negated or not.
   *
   * @return bool
   *   The result of the comparison or evaluation.
   */
  protected static function evaluateRule($param, $op, $exp, $neg) {
    $ret = false;
    switch ($op) {
      // Case 1 - 6 should be non case sensitive
      case 'EQ':
        $ret = ($param == $exp);
        break;
      case 'LT':
        $ret = ($param < $exp);
        break;
      case 'LE':
        $ret = ($param <= $exp);
        break;
      case 'GT':
        $ret = ($param > $exp);
        break;
      case 'GE':
        $ret = ($param >= $exp);
        break;
      case 'IN':
      case 'LK':
        $patterns = explode(',', str_replace(' ', '', $exp));
        $ret = false;
        foreach ($patterns as $pattern) {
          // Replace common wildcards with equivalent regular expressions then use regex match
          $exp = str_replace('%', '.*', str_replace('?', '.', $pattern));
          $ret = (preg_match("/$exp/i", $param) == 1);
          if ($ret) {
            break;
          }
        }
        break;
      case 'RX':
        $ret = (preg_match("/$exp/i", $param) == 1);
        break;
    }
    if ($neg && isset($ret)) {
      $ret = !$ret;
    }
    return $ret && true;
  }

  /**
   * Specifies the different operator types.
   */
  public static function getOpTypes() {
    return array(
      'EQ'=>'EQ',
      'LT'=>'LT',
      'LE'=>'LE',
      'GT'=>'GT',
      'GE'=>'GE',
      'IN'=>'IN',
      'LK'=>'LK',
      'RX'=>'RX',
    );
  }

  /**
   * Provides the translated long names of the different operator types.
   */
  public static function getLongOpTypes() {
    return array(
      'EQ'=>t('equal'),
      'LT'=>t('less than'),
      'LE'=>t('less or equal'),
      'GT'=>t('greater than'),
      'GE'=>t('greater or equal'),
      'IN'=>t('any of'),
      'LK'=>t('like'),
      'RX'=>t('regexp'),
    );
  }

  /**
   * Provides a printable help string for the various operator types.
   */
  public static function getOpTypesHelp() {
    return 'EQ: equal to
LT: less than
LE: less than or equal to
GT: greater than
GE: greater than or equal to
IN: any of (comma-separated patterns)
LK: looks like (wildcards % and ?)
RX: full regular expresson';
  }

  /**
   * Compacts ruleset conditions into a format that takes up less space.
   *
   * @param array $rules
   *  An array of ruleset rules. Each rule has the following composition:
   *  - op: The type of comparison operation e.g. =, <, >, >=, <=, etc.
   *  - exp: The expression to compare a given value with.
   *  - neg: TRUE to negate/invert the result
   * In addition, the _ALL_TRUE_ value is a boolean added to the rules array to
   * indicate that all specified rules must match (if TRUE), equivalent to AND
   * logic; or any rule may match (if FALSE) - OR logic equivalent.
   *
   * Rule types are specified as 'user', 'country', 'network', 'number', 'group'.
   *
   * @returns string representing the compacted ruleset condition
   *
   * @todo Implement ruleset rules as D8 plugins.
   */
  public static function compactRulesetRules($rules) {
    $all_true = $rules['_ALL_TRUE_'];
    unset($rules['_ALL_TRUE_']);
    $compacted = ($all_true ? '1' : '0');
    foreach ($rules as $key => $rule) {
      $string = $rule['op'] . ($rule['neg'] ? '1' : '0') . $rule['exp'];
      $compacted .= strlen($key) . '.' . $key . strlen($string) . '.' . $string;
    }
    return $compacted;
  }

  /**
   * Expands a compacted ruleset.
   *
   * @param string $compacted
   *   A compacted version of the ruleset.
   *
   * @return array
   *   An expanded form of the ruleset.
   *
   * @see \Drupal\sms_advanced\Utility\AdvancedRouting::compactRulesetRules()
   */
  public static function expandRulesetRules($compacted) {
    $rules = array();
    $rules['_ALL_TRUE_'] = (substr($compacted, 0, 1) == true);
    $compacted = substr($compacted, 1);
    while ($compacted) {
      $k_pos = strpos($compacted, '.');
      $k_len = intval(substr($compacted, 0, $k_pos));
      $key = substr($compacted, $k_pos + 1, $k_len);

      $s_pos = strpos($compacted, '.', $k_pos + 1);
      $s_len = intval(substr($compacted, $k_pos + 1 + $k_len, $s_pos - $k_pos - 1));
      $string = substr($compacted, $s_pos + 1, $s_len);

      $op = substr($string, 0, 2);
      $neg = (substr($string, 2, 1) == true);
      $exp = substr($string, 3);
      $rules[$key] = array('op' => $op, 'neg' => $neg, 'exp' => $exp);

      $compacted = substr($compacted, $s_pos + $s_len + 1);
    }
    return $rules;
  }

  /**
   * Callback for sorting by weight.
   */
  public static function weightSort($a, $b) {
    if ($a['weight'] == $b['weight']) return 0;
    else return ($a['weight'] > $b['weight']) ? 1 : -1;
  }

}

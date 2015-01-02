<?php

/**
 * Implement queuing for bulk sms
 */
function sms_ui_cron_queue_info() {
  $queues['sms_ui_queue'] = array(
    'worker callback' => '_sms_ui_dequeue_sms',
  );
  return $queues;
}

/**
 * Cron: clean up caches and sms_ui_batch_sent
 */
function _sms_ui_cron() {
  $cname = 'sms_ui_cache:';
  $time = time();
  $caches = db_query("SELECT * FROM {cache} WHERE cid LIKE '%s%%' AND expire < %d", $cname, $time);
  while ($cache = db_fetch_object($caches)) {
    if ($cache->serialized) {
      $cache->data = unserialize($cache->data);
    }
    db_query("DELETE FROM {sms_ui_sent_batch} WHERE uuid = '%s'", str_replace($cname, '', $cache->cid));
  }
  db_query("DELETE FROM {cache} WHERE cid LIKE '%s%%' AND expire < %d", $cname, $time);

  // Clean up the cache of previously sent sms uuids.
  // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/sms_ui.settings.yml and config/schema/sms_ui.settings.yml.
  $past_uuids = \Drupal::config('sms_ui.settings')->get('sms_ui_past_uuids');
  $now = date('YmdH');
  foreach ($past_uuids as $hour => $uuids) {
    if ($hour < $now) {
      unset($past_uuids[$hour]);
    }
  }
  \Drupal::config('sms_ui.settings')->set('sms_ui_past_uuids', $past_uuids)->save();
}

/**
 * Dequeue and dispatch sms
 *
 * @param array $sms
 */
function _sms_ui_dequeue_sms($sms) {
  $user = \Drupal::currentUser();
  // Swap to user who sent the sms
  $_user = $user;
  $user = \Drupal::entityManager()->getStorage('user')->load($sms->uid);

  // Dequeue, send sms and call post-process hooks
  \Drupal::logger('sms_ui')->notice(t('Dequeued message "%msg" for sending', array('%msg' => $sms->message)), []);

  $status = _sms_advanced_process_send($sms);
  $user = $_user;
  return $status;
}

/**
 * Page callback to update status of queued sms
 */
function _sms_ui_queue_status_js($uuid=NULL) {
  require_once './' . drupal_get_path('module', 'sms_ui') . '/sms_ui.forms.inc';
  $sms = &_sms_ui_get_cached($uuid);
  $count = db_result(db_query("SELECT sum(recipients) FROM {sms_ui_sent_batch} WHERE uuid = '%s'", $uuid));
  $progress = $count * 100 / count($sms->recipients);

  $start = time();
  while ($progress < 100 && time() - $start < 600) {	// Break out after 10mins nevertheless
    sleep(10);
    $sms = &_sms_ui_get_cached($uuid);
    $count = db_result(db_query("SELECT sum(recipients) FROM {sms_ui_sent_batch} WHERE uuid = '%s'", $uuid));
    $progress = $count * 100 / count($sms->recipients);
  }

  // Load the form from the Form API cache.
  if (!($cached_form = \Drupal::formBuilder()->getCache($_POST['form_build_id'], $cached_form_state))) {
    form_set_error('form_token', t('Validation error, please try again. If this error persists, please contact the site administrator.'));
    $output = _theme('status_messages');
    print drupal_to_js(array('status' => TRUE, 'data' => $output));
    exit();
  }

  // Prepare form for summary of sending and render it
  $cached_form['summary'] = array (
    '#type' => 'fieldset',
    '#title' => t('Message status'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#prefix' => '<div id="summary">',
    '#suffix' => '</div>',
  );
  $cached_form['summary'] += _sms_ui_sms_status_summary($sms->recipients);
  if ($cached_form['summary']['failure']) $cached_form['summary']['#collapsed'] = FALSE;
  $form_state = array('values' => $_POST);

  // Rebuild form and render section of it
  $form = form_builder($_POST['form_id'], $cached_form, $form_state);
  $output = drupal_render($form['summary']);
  print drupal_to_js(array('status' => TRUE, 'data' => $output, '$sms' => serialize($sms)));
  exit();
}



/**
 * Page callback to get progress of queued messages
 */
function _sms_ui_queue_progress_js($uuid=NULL) {
  $sms = &_sms_ui_get_cached($uuid);
  $count = db_result(db_query("SELECT sum(recipients) FROM {sms_ui_sent_batch} WHERE uuid = '%s'", $uuid));
  print drupal_to_js(array(
    'status' => true,
    'count' => $count,
    'percentage' => intval($count * 100 / count($sms->recipients)),
    'message' => t('Sending message...'),
  ));
  exit();
}

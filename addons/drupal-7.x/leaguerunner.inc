<?php
/**
 * Implements hook_cron_queue_info().
 */
function leaguerunner_cron_queue_info() {
  $queues = array();
  $queues['leaguerunner_queue_notify'] = array( 'worker callback' => 'leaguerunner_queue_notify_worker', 'time' => 15, );
  return $queues;
}

/**
 * Queue worker for sending notification mails.
 */
function leaguerunner_queue_notify_worker($params) {
  drupal_mail('leaguerunner', 'notify', $params['to'], language_default(), $params, $params['from']);
}

/**
 * Implements hook_mail().
 */
function leaguerunner_mail($key, &$message, $params) {
  $language = $message['language'];
  $message['subject'] = $params['subject'];
  $message['body'][] = $params['message'];
}

/**
 * Implements hook_rules_action_info().
 */
function leaguerunner_rules_action_info() {
  return array(
    'leaguerunner_rules_action_mail' => array(
      'label' => t('Send mail'),
      'group' => 'leaguerunner',
      'parameter' => array(
        'to' => array(
          'type' => 'text',
          'label' => t('To'),
          'description' => t('The e-mail address or addresses where the message will be sent to. The formatting of this string must comply with RFC 2822.'),
        ),
        'subject' => array(
          'type' => 'text',
          'label' => t('Subject'),
          'description' => t("The mail's subject."),
        ),
        'message' => array(
          'type' => 'text',
          'label' => t('Message'),
          'description' => t("The mail's message body."),
        ),
        'from' => array(
          'type' => 'text',
          'label' => t('From'),
          'description' => t("The mail's from address. Leave it empty to use the site-wide configured address."),
          'optional' => TRUE,
        ),
      ),
    ),
  );
}

/**
 * Action: Add notification mails to the queue.
 */
function leaguerunner_rules_action_mail($to, $subject, $message, $from = NULL, $settings, RulesState $state, RulesPlugin $element) {
  // Gather params.
  $params = array(
    'to' => str_replace(array("\r", "\n"), '', $to),
    'from' => !empty($from) ? str_replace(array("\r", "\n"), '', $from) : NULL,
    'subject' => $subject,
    'message' => $message,
  );
  // Add to the queue.
  $queue = DrupalQueue::get('leaguerunner_queue_notify');
  $queue->createItem($params);
}
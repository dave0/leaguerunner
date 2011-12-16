<?php
function leaguerunner_admin_form($form, &$form_state) {

  $form['leaguerunner_database'] = array(
    '#type' => 'fieldset',
    '#title' => t('Database Settings'),
    '#description' => t('Database values pre-populated from settings.php if available.  If not, specify them here.')
  );

  // Determine if db info has been added to settings.php
  $db_info = Database::getConnectionInfo('leaguerunner');

  $form['leaguerunner_database']['database_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Database Name'),
    '#description' => t('Schema name where the Leaguerunner tables are stored.'),
    '#default_value' => variable_get('leaguerunner_database_name', $db_info['default']['database']),
  );

  $form['leaguerunner_database']['database_user'] = array(
    '#type' => 'textfield',
    '#title' => t('Database User account'),
    '#description' => t('User name used to access the Leaguerunner schema.'),
    '#default_value' => variable_get('leaguerunner_database_user', $db_info['default']['username']),
  );

  $form['leaguerunner_database']['database_password'] = array(
    '#type' => 'textfield',
    '#title' => t('Database User password'),
    '#description' => t('Password for schema user.'),
    '#default_value' => variable_get('leaguerunner_database_password', $db_info['default']['password']),
  );

  $form['leaguerunner_database']['database_host'] = array(
    '#type' => 'textfield',
    '#title' => t('Database Host'),
    '#description' => t('How Drupal should connect to the Leaguerunner Schema.'),
    '#default_value' => variable_get('leaguerunner_database_host', $db_info['default']['host']),
  );

  $form['leaguerunner_database']['database_driver'] = array (
    '#type' => 'select',
    '#title' => t('Database Driver'),
    '#options' => array(
      'mysql' => 'mysql',
      'pgsql' => 'pgsql',
      'sqlite' => 'sqlite',
    ),
    '#description' => t('Driver to use for connecting to the Datbase Schema.'),
    '#default_value' => variable_get('leaguerunner_database_driver', $db_info['default']['driver']),
  );

  $form['leaguerunner_integration'] = array(
    '#type' => 'fieldset',
    '#title' => t('Integration Settings'),
    '#description' => t('Options available for using Leaguerunner within Drupal.')
  );
  $form['leaguerunner_integration']['leaguerunner_authenticate'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use the Leaguerunner database for account authentication.'),
      '#description' => t('Allows users to sign into the website with their Leaguerunner credentials.  A Drupal account will be created if necessary.'),
      '#default_value' => variable_get('leaguerunner_authenticate', 0),
  );

  $form['leaguerunner_integration']['leaguerunner_email_membership'] = array(
    '#type' => 'checkbox',
    '#title' => t('Email membership when new Posts are made to the front page.'),
    '#description' => t('When checked, any new Posts by approved users will also send Bulk Email to all Leaguerunner accounts.'),
    '#default_value' => variable_get('leaguerunner_email_membership', 0),
  );

  $form['leaguerunner_integration']['leaguerunner_post_types'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Node types'),
      '#description' => t('Which nodes should be sent to the membership?'),
      '#options' => node_type_get_names(),
      '#default_value' => variable_get('leaguerunner_post_types', array('story' => 'story', 'blog' => 'blog')),
  );

  return system_settings_form($form);
}
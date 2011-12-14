<?php
function leaguerunner_admin_form($form, &$form_state) {

  $form['leaguerunner_database'] = array(
    '#type' => 'fieldset',
    '#title' => t('Database Settings'),
    '#description' => t('Database values pre-populated from settings.php if available.  If not, specify them here.')
  );

  // Determine if db info has been added to settings.php
  $db_info = Database::getConnectionInfo('leaguerunner');
  debug($db_info);
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

  $form['leaguerunner_authenticate'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use the Leaguerunner database for account authentication.'),
      '#description' => t('Allows users to sign into the website with their Leaguerunner credentials.  A Drupal account will be created if necessary.'),
      '#default_value' => variable_get('leaguerunner_authenticate', 0),
  );

  return system_settings_form($form);
}
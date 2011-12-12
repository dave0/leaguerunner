<?php
function leaguerunner_admin_form($form, &$form_state) {
  $form['leaguerunner_authenticate'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use the Leaguerunner database for account authentication.'),
    '#description' => t('Allows users to sign into the website with their Leaguerunner credentials.  A Drupal account will be created if necessary.'),
    '#default_value' => variable_get('leaguerunner_authenticate', 0),
	);

  return system_settings_form($form);
}
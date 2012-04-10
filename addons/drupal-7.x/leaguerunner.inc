<?php
/**
 * Test given credentials against LR tables
 */
function _leaguerunner_authentication_check($username, $password) {
  try {
    // pull from LR db
    db_set_active('leaguerunner');
    $lr_password =  db_query("SELECT password FROM {person} WHERE username = :username", array(
      ':username' => $username,
    ))->fetchField();
    db_set_active();

    // LR stored older pswds with just md5, not crypted.  Deal with it accordingly.
    // If crypted, first 12 characters are the salt
    if(substr($lr_password, 0, 3) == '$1$') {
      $hashed_pw = crypt($password, substr($lr_password, 0, 12));
    } else {
      $hashed_pw = md5( $password );
    }
    return ($hashed_pw == $lr_password);
  }
  catch (Exception $e) {
    watchdog('lr-auth', 'Error %error_message.', array('%error_message' => $e->getMessage()), WATCHDOG_NOTICE);
    db_set_active();
    return false;
  }
}

/**
 * Set Cookie and session in LR Database from Drupal Sign-in
 */
function _leaguerunner_set_session($user_id, $sess) {
  try {
    db_set_active('leaguerunner');
    $updated = db_update('person')
      ->fields(array(
      	'session_cookie' => $sess,
      	'client_ip' => $_SERVER['REMOTE_ADDR'],
      ))
      ->expression('last_login', 'NOW()')
      ->condition('user_id', $user_id)
      ->execute();
    db_set_active();
    return true;
  }
  catch (Exception $e) {
    watchdog('lr-auth', 'Error %error_message.', array('%error_message' => $e->getMessage()), WATCHDOG_NOTICE);
    db_set_active();
    return false;
  }
}

/**
 * Retrieve specific LR user id from given account
 */
function _leaguerunner_get_user_id($username) {
  try {
    db_set_active('leaguerunner');
    $lr_id =  db_query("SELECT user_id FROM {person} WHERE username = :username", array(
      ':username' => $username,
    ))->fetchField();
    db_set_active();
    return $lr_id;
  }
  catch (Exception $e) {
    watchdog('lr-auth', 'Error %error_message.', array('%error_message' => $e->getMessage()), WATCHDOG_NOTICE);
    db_set_active();
    return false;
  }
}

/**
* Retrieve specific LR user id from given account
*/
function _leaguerunner_get_fullname($username) {
  try {
    db_set_active('leaguerunner');
    $result =  db_query("SELECT firstname, lastname FROM {person} WHERE username = :username", array(
      ':username' => $username,
    ))->fetchAssoc();
    $lr_fullname = $result['firstname'].' '.$result['lastname'];
    db_set_active();
    return $lr_fullname;
  }
  catch (Exception $e) {
    watchdog('lr-auth', 'Error %error_message.', array('%error_message' => $e->getMessage()), WATCHDOG_NOTICE);
    db_set_active();
    return false;
  }
}
<?php
/**
 * Test given credentials against LR tables
 */
function leaguerunner_authentication_check($username, $password) {
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
    watchdog('lr-auth', 'Error %error_message.', array('%error_message' => $e->faultstring), WATCHDOG_NOTICE);
    return false;
  }
}
<?php
/**
 * Some functions borrowed from Drupal as I attempt to adapt this structure to
 * the Drupal structure.
 */

/**
 * Register a particular module
 * TODO: Some error checking might be nice here...
 * Note that this is not a borrowed Drupal function
 */
function module_register($filename, $modulename) {
  global $_modules;
  $_modules[] = $modulename;
  require_once($filename);
}

// return array of module names 
function module_list() {
  global $_modules;
  return $_modules;
}


// invoke hook $hook of module $name with optional arguments:
function module_invoke($name, $hook, $a1 = NULL, $a2 = NULL, $a3 = NULL, $a4 = NULL) {
  $function = $name ."_". $hook;
  if (function_exists($function)) {
    return $function($a1, $a2, $a3, $a4);
  }
}

// invoke $hook for all appropriate modules:
function module_invoke_all($hook, $a1 = NULL, $a2 = NULL, $a3 = NULL, $a4 = NULL) {
  $return = array();
  foreach (module_list() as $name) {
    $result = module_invoke($name, $hook, $a1, $a2, $a3, $a4);
    if (isset($result)) {
      $return[] = $result;
    }
  }

  return $return;
}

// return 1 if module $name implements hook $hook, 0 otherwise:
function module_hook($name, $hook) {
  return function_exists($name ."_". $hook);
}

?>

<?php
/**
 * Borrowed from Drupal as I attempt to adapt this structure to the Drupal
 * structure.
 */

// invoke hook $hook of module $name with optional arguments:
function module_invoke($name, $hook, $a1 = NULL, $a2 = NULL, $a3 = NULL, $a4 = NULL) {
  $function = $name ."_". $hook;
  if (function_exists($function)) {
    return $function($a1, $a2, $a3, $a4);
  }
}

// return 1 if module $name implements hook $hook, 0 otherwise:
function module_hook($name, $hook) {
  return function_exists($name ."_". $hook);
}

?>

<?php
// $Id$

function db_prefix_tables($sql) {
  global $db_prefix;

  if (is_array($db_prefix)) {
    $prefix = $db_prefix["default"];
    foreach ($db_prefix as $key => $val) {
      if ($key !== "default") {
        $sql = strtr($sql, array("{". $key. "}" => $val. $key));
      }
    }
  }
  else {
    $prefix = $db_prefix;
  }
  return strtr($sql, array("{" => $prefix, "}" => ""));
}

$db_type = substr($DB_URL, 0, strpos($DB_URL, "://"));

if ($db_type == "mysql") {
  include_once "includes/database.mysql.inc";
}
else {
  die("Database $db_type is currently unsupported");
}

db_connect($DB_URL);


?>

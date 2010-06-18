<?php
global $smarty;
global $CONFIG;

require_once( $CONFIG['smarty']['smarty_path'] . '/Smarty.class.php');

$smarty = new Smarty();

$smarty->template_dir = $CONFIG['smarty']['template_dir'];
$smarty->compile_dir  = $CONFIG['smarty']['compile_dir'];
$smarty->cache_dir    = $CONFIG['smarty']['cache_dir'];
#$smarty->config_dir  = $CONFIG['smarty']['config_dir'];

$smarty->register_function('lr_url', 'smarty_lr_url');
function smarty_lr_url($params, &$smarty)
{
  if(empty($params['path'])) {
    $path = NULL;
  } else {
    $path = $params['path'];
  }
  if(empty($params['query'])) {
    $query = NULL;
  } else {
    $query = $params['query'];
  }
  return url( $path, $query);
}

$smarty->register_modifier('utf8', 'smarty_modifier_utf8');
function smarty_modifier_utf8 ($string)
{
	$utf = utf8_encode ($string);
	return $utf;
}

?>

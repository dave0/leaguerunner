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

$smarty->register_function('hidden_fields', 'smarty_hidden_fields');
function smarty_hidden_fields($params, &$smarty)
{
	if( empty ($params['group'] ) ) {
		$params['group'] = 'edit';
	}
	$output = '';
	if(array_key_exists('fields', $params) && !empty($params['fields'])) {
		foreach ($params['fields'] as $name => $value) {
			$output .= "<input type=\"hidden\" name=\"{$params['group']}[$name]\" value=\"" . check_form($value) . "\" />\n";
		}
	}
	return $output;
}

$smarty->register_modifier('utf8', 'smarty_modifier_utf8');
function smarty_modifier_utf8 ($string)
{
	$utf = utf8_encode ($string);
	return $utf;
}

?>

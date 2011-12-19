<?php
global $smarty;
global $CONFIG;

require_once( $CONFIG['smarty']['smarty_path'] . '/Smarty.class.php');

$smarty = new Smarty();

$smarty->setTemplateDir($CONFIG['smarty']['template_dir'])
  ->setCompileDir($CONFIG['smarty']['compile_dir'])
  ->setCacheDir($CONFIG['smarty']['cache_dir'])
  ->setConfigDir($CONFIG['smarty']['config_dir']);

$smarty->registerPlugin('function', 'lr_url', 'smarty_lr_url');
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

$smarty->registerPlugin('function','hidden_fields', 'smarty_hidden_fields');
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

$smarty->registerPlugin('modifier', 'utf8', 'smarty_modifier_utf8');
function smarty_modifier_utf8 ($string)
{
	$utf = utf8_encode ($string);
	return $utf;
}

require_once('includes/fillInFormValues.php');
$smarty->registerPlugin('block','fill_form_values', 'smarty_fill_form_values', false);
/**
 * Smarty {fill_form_values}...{/fill_form_values} extension.
 * Fills in form fields between the tags based on values in Smarty template
 * variables, and shows form errors stored in the template variable
 * "formErrors".
 *
 * @param array $params		Params from smarty template (unused)
 * @param string $content	HTML to filter (it's {...}THIS STUFF{/...}
 * @param Smarty $smarty
 * @return string		$content with form vars set properly.
 */
function smarty_fill_form_values($params, $content, &$smarty)
{
	if ($content === null) {
		return "";
	}

	$vars   = $smarty->getTemplateVars();
	$errors = array();
	if( array_key_exists('formErrors', $vars) ) {
		$errors = $vars['formErrors'];
	}

	return fillInFormValues($content, $vars, $errors);
}

?>

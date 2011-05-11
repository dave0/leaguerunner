<?php
require_once('Handler/gmaps/view.php');
class gmaps_edit extends gmaps_view
{
	private $map_vars = array('fid', 'latitude', 'longitude', 'angle', 'width', 'length', 'zoom', 'num' );
	private $save_vars = array('latitude', 'longitude', 'angle', 'width', 'length', 'zoom');

	function __construct ( $id )
	{
		parent::__construct($id);
		$this->template_name = 'pages/gmaps/edit.tpl';
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field', 'edit', $this->field->fid);
	}

	function process()
	{
		if (! empty ($_POST)) {
			$this->save ($_POST);
			local_redirect(url("field/view/{$this->field->fid}"));
		} else {
			$this->generate_edit_map();
		}
	}

	function save($edit)
	{
		foreach ($this->save_vars as $var) {
			$this->field->set($var, $edit[$var]);
		}

		if( !$this->field->save() ) {
			return error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function generate_edit_map()
	{
		global $CONFIG;

		$this->smarty->assign('gmaps_key', variable_get('gmaps_key', '') );
		$this->smarty->assign('title', "{$this->field->name} ({$this->field->code}) {$this->field->num}");

		// We use these as last-ditch emergency values, if the field has neither
		// a valid lat/long or an address that Google can find.
		$this->smarty->assign('gmaps_key', variable_get('gmaps_key', '') );
		$this->smarty->assign('location_latitude', variable_get('location_latitude', 0));
		$this->smarty->assign('location_longitude', variable_get('location_longitude', 0));

		// TODO: wtf isn't this a JSON object?
		$this->smarty->assign('name',"{$this->field->name} ({$this->field->code}) {$this->field->num}");
		$this->smarty->assign('address', "{$this->field->location_street}, {$this->field->location_city}");
		$this->smarty->assign('full_address', "{$this->field->location_street}, {$this->field->location_city}, {$this->field->location_province}");
		foreach ($this->map_vars as $var) {
			$this->smarty->assign($var, $this->field->{$var});
		}

		// Find other fields at this site
		$otherfields = '';
		$sth = $this->field->find_others_at_site();
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid && $related->length) {
				foreach ($this->map_vars as $var) {
					$otherfields .= "other_{$var}[$related->fid] = {$related->{$var}};\n";
				}
			}
		}
		$this->smarty->assign('otherfields', $otherfields);
	}
}

?>

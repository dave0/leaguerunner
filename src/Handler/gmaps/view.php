<?php
require_once('Handler/FieldHandler.php');
class gmaps_view extends FieldHandler
{
	private $map_vars = array('fid', 'latitude', 'longitude', 'angle', 'width', 'length', 'zoom', 'num');

	function __construct ( $id )
	{
		parent::__construct($id);
		$this->template_name = 'pages/gmaps/view.tpl';
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field', 'view', $this->field->fid);
	}

	function process()
	{
		global $lr_session, $CONFIG;

		if (!$this->field->length) {
			return error_exit('That field has not yet been laid out');
		}

		$this->smarty->assign('gmaps_key', variable_get('gmaps_key', '') );
		$this->smarty->assign('title', "{$this->field->name} ({$this->field->code}) {$this->field->num}");

		if ($lr_session->user) {
			$this->smarty->assign('home_addr', "{$lr_session->user->addr_street}, {$lr_session->user->addr_city}, {$lr_session->user->addr_prov}");
		}

		// TODO: wtf isn't this a JSON object?
		$this->smarty->assign('name',"{$this->field->name} ({$this->field->code}) {$this->field->num}");
		$this->smarty->assign('address', "{$this->field->location_street}, {$this->field->location_city}");
		$this->smarty->assign('full_address', "{$this->field->location_street}, {$this->field->location_city}, {$this->field->location_province}");
		foreach ($this->map_vars as $var) {
			$this->smarty->assign($var, $this->field->{$var});
		}

		// Handle parking
		if ($this->field->parking) {
			$parking = explode ('/', $this->field->parking);
			foreach ($parking as $i => $pt) {
				list($lat,$lng) = explode(',', $pt);
				$variables .= "parking[$i] = new GLatLng($lat, $lng);\n";
			}
		}

		// Find other fields at this site
		$sth = $this->field->find_others_at_site();
		$otherfields = '';
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid && $related->length) {
				// TODO: wtf isn't this a JSON object?
				foreach ($this->map_vars as $var) {
					$otherfields .= "other_{$var}[$related->fid] = {$related->{$var}};\n";
				}
			}
		}
		$this->smarty->assign('otherfields', $otherfields);
	}
}

?>

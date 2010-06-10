<?php
require_once('Handler/FieldHandler.php');

class field_view extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;
		$this->title = "Field: " . $this->field->fullname;

		$rows = array();
		$rows[] = array("Field&nbsp;Name:", $this->field->name);
		$rows[] = array("Field&nbsp;Code:", $this->field->code);
		$rows[] = array("Field&nbsp;Status:", $this->field->status);

		$ratings = field_rating_values();
		$rows[] = array("Field&nbsp;Rating:", $ratings[$this->field->rating]);

		$rows[] = array("Number:", $this->field->num);
		$rows[] = array("Field&nbsp;Region:", $this->field->region);

		if( $this->field->location_street ) {
			$rows[] = array("Address:",
				format_street_address(
					$this->field->location_street,
					$this->field->location_city,
					$this->field->location_province,
					'',
					''));
		}

		$mapurl = null;
		if ($this->field->length) {
			$mapurl = "gmaps/view/{$this->field->fid}";
		} else if ($this->field->location_url) {
			// Useful during transition period from old maps to new
			$mapurl = $this->field->location_url;
		}
		$rows[] = array("Map:",
			$mapurl ? l("Click for map in new window", $mapurl, array('target' => '_new'))
				: "N/A");
		if ($this->field->layout_url) {
			$rows[] = array("Layout:",
				l("Click for field layout diagram in new window", $this->field->layout_url, array('target' => '_new'))
			);
		}

		if( $this->field->permit_url ) {
			$rows[] = array("Field&nbsp;Permit:", $this->field->permit_url);
		}
		$rows[] = array('Driving Directions:', $this->field->driving_directions);
		if( $this->field->parking_details ) {
			$rows[] = array('Parking Details:', "<div class='parking'>{$this->field->parking_details}</div>");
		}
		if( $this->field->transit_directions ) {
			$rows[] = array('Transit Directions:', "<div class='transit'>{$this->field->transit_directions}</div>");
		}
		if( $this->field->biking_directions ) {
			$rows[] = array('Biking Directions:', "<div class='biking'>{$this->field->biking_directions}</div>");
		}
		if( $this->field->washrooms ) {
			$rows[] = array('Public Washrooms:', "<div class='washrooms'>{$this->field->washrooms}</div>");
		}
		if( $this->field->public_instructions ) {
			$rows[] = array("Special Instructions:", $this->field->public_instructions);
		}
		if( $this->field->site_instructions ) {
			if( $lr_session->has_permission('field','view', $this->field->fid, 'site_instructions') ) {
				$rows[] = array("Private Instructions:", $this->field->site_instructions);
			} else {
				$rows[] = array("Private Instructions:", "You must be logged in to see the private instructions for this site.");
			}
		}

		// list other fields at this site
		$sth = $this->field->find_others_at_site();
		$fieldRows = array();
		$header = array("Fields","&nbsp;");
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid)
			{
				$fieldRows[] = array(
					$this->field->code . " $related->num",
					l("view field details page", "field/view/$related->fid")
				);
			}
		}

		if( !empty( $fieldRows ) ) {
			$rows[] = array("Other fields at this site:", "<div class='listtable'>" . table($header,$fieldRows) . "</div>");
		}

		// Add sponsorship details
		$sponsor = '';
		if( $this->field->sponsor ) {
			$sponsor = "<div class='sponsor'>{$this->field->sponsor}</div>";
		}

		return "<div class='pairtable'>" . table(null, $rows, array('alternate-colours' => true)) . "</div>\n$sponsor";
	}
}
?>

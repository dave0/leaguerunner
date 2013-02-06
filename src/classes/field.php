<?php
class Field extends LeaguerunnerObject
{
	function __construct ( $load_mode = LOAD_RELATED_DATA )
	{
		if( $this->latitude ) {
			// We have a latitude value of our own, so flag the layout as existing
			$this->layout_is_set = 1;
		}

		// If we have a parent, override the overridables.
		if( $this->parent_fid ) {
			$parent = Field::load( array('fid' => $this->parent_fid) );
			$this->name = $parent->name;
			$this->code = $parent->code;
			$this->region = $parent->region;

			$this->location_street = $parent->location_street;
			$this->location_city = $parent->location_city;
			$this->location_province = $parent->location_province;

			$this->driving_directions = $parent->driving_directions;
			$this->transit_directions = $parent->transit_directions;
			$this->biking_directions = $parent->biking_directions;
			$this->parking_details = $parent->parking_details;
			$this->washrooms = $parent->washrooms;
			$this->public_instructions = $parent->public_instructions;
			$this->site_instructions = $parent->site_instructions;
			$this->sponsor = $parent->sponsor;
			$this->location_url = $parent->location_url;
			$this->layout_url = $parent->layout_url;
			$this->fullname = join(" ", array($this->name, $this->num));
			$this->is_indoor = $parent->is_indoor;

			// Assume slightly northeast of parent field if no location given.
			if( ! $this->latitude ) {
				$this->latitude = $parent->latitude + 0.0005;
				$this->longitude = $parent->longitude + 0.0005;

				// but flag it as not having layout
				$this->layout_is_set = 0;
			}

			if( ! $this->zoom ) {
				$this->zoom = $parent->zoom;
			}

			// Assume parent field dimensions if none given.
			if( ! $this->width ) {
				$this->width = $parent->width;
				$this->length = $parent->length;
				$this->angle  = $parent->angle;
			}

			// Fields may have their own parking details, or inherit from the parent
			if (! $this->parking ) {
				$this->parking = $parent->parking;
			}
		}

		if( $load_mode == LOAD_OBJECT_ONLY ) {
			return;
		}

		$current_season_id = strtolower(variable_get("current_season",1));
		$current_season = Season::load(array( 'id' => $current_season_id ));

		$permit_dir = join("/", array(strtolower($current_season->season), $current_season->year,'permits'));

		$system_permit_dir = join("/", array(variable_get("league_file_base",'/opt/websites/www.ocua.ca/static-content/leagues'), $permit_dir));

		# Auto-detect the permit URLs
		$this->permit_url = '';
		if (is_dir($system_permit_dir)) {
			if ($dh = opendir($system_permit_dir)) {
				while (($file = readdir($dh)) !== false) {
					if( fnmatch( $this->code . "*", $file) ) {
						$this->permit_url .= l($file, variable_get("league_url_base",'http://www.ocua.ca/leagues') . "/$permit_dir/$file\"") . '<br />';
					}
				}
			}
		}

		if( ! $this->rating ) {
			# If no rating, mark as unknown
			$this->rating = '?';
		}

		return true;
	}

	function save ()
	{
		global $dbh;

		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create field");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
				$fields[] = "$key = ?";
				$fields_data[] = null;
			} else {
				$fields[] = "$key = ?";
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sql = "UPDATE field SET ";
		$sql .= join(", ", $fields);
		$sql .= " WHERE fid = ?";

		$fields_data[] = $this->fid;

		$sth = $dbh->prepare( $sql );
		$sth->execute( $fields_data );
		if(1 != $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		global $dbh;
		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->num ) {
			return false;
		}

		if( ! $this->parent_fid ) {
			if( ! $this->code ) {
				return false;
			}
			if( ! $this->name ) {
				return false;
			}
			$sth = $dbh->prepare('INSERT into field (num, name, code) VALUES(?,?,?)');
			$sth->execute(array($this->num, $this->name, $this->code));
		} else {
			$sth = $dbh->prepare('INSERT into field (num, parent_fid) VALUES(?,?)');
			$sth->execute(array($this->num, $this->parent_fid));
		}

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM field');
		$sth->execute();
		$this->fid = $sth->fetchColumn();

		return true;
	}

	function find_others_at_site ()
	{
		global $dbh;

		$sth = $dbh->prepare('SELECT * FROM field WHERE parent_fid = :fid OR fid = :fid ORDER BY num');
		if( $this->parent_fid ) {
			$sth->execute(array( 'fid' => $this->parent_fid ) );
		} else {
			$sth->execute(array( 'fid' => $this->fid ) );
		}
		return $sth;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	static function query ( $array = array() )
	{
		global $dbh;

		$query = array();
		$params = array();
		$order = '';
		foreach ($array as $key => $value) {
			switch( $key ) {
				case '_extra':
					/* Just slap on any extra query fields desired */
					$query[] = $value;
					break;
				case '_order':
					$order = ' ORDER BY ' . $value;
					break;
				default:
					$query[] = "f.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			f.*,
			1 AS _in_database,
			CONCAT_WS(' ',f.name,f.num) as fullname
			FROM field f
		WHERE " . implode(' AND ',$query) .  $order);

		$sth->execute( $params );
		return $sth;
	}

}

function field_rating_values()
{
	return array(
		'A' => 'A - Field is top-quality',
		'B' => 'B - Field is in good condition',
		'C' => 'C - Field is acceptable for use',
		'D' => 'D - Field is in poor condition',
		'?' => '? - Field is in unknown condition',
	);
}

?>

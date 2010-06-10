<?php

class field_list extends Handler
{
	function __construct ( $type = null )
	{
		$this->closed = ( isset( $type ) && $type == 'closed' );
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','list',$this->closed);
	}

	function process ()
	{
		global $CONFIG;

		$output = '';
		if( $this->closed ) {
			$this->title = 'List Closed Fields';
		} else {
			$this->title = 'List Fields';

			ob_start();
			$retval = @readfile(trim ($CONFIG['paths']['file_path'], '/') . "/data/field_caution.html");
			if (false !== $retval) {
				$output .= ob_get_contents();
			}
			ob_end_clean();
		}

		// TODO: this open/closed crap doesn't work everywhere!
		if( $this->closed ) {
			$status = "AND status = 'closed'";
		} else {
			$status = "AND (status = 'open' OR ISNULL(status))";
		}
		$sth = field_query( array( '_extra' => "ISNULL(parent_fid) $status", '_order' => 'f.region,f.name') );

		$fieldsByRegion = array();
		while($field = $sth->fetch(PDO::FETCH_OBJ) ) {
			if(! array_key_exists( $field->region, $fieldsByRegion) ) {
				$fieldsByRegion[$field->region] = array();
			}
			array_push( $fieldsByRegion[$field->region], array( l($field->name, "field/view/$field->fid") ));
		}

		$fieldColumns = array();
		$header = array();

		if( variable_get('narrow_display', '0') ) {
			$cols = 2;
			for( $i = 0; $i < $cols; ++ $i ) {
				$fieldColumns[$i] = '';
			}

			$i = 0;
			while(list($region,$fields) = each($fieldsByRegion)) {
				$fieldColumns[$i] .= table( array( ucfirst( $region ) ), array( array( table(null, $fields) ) ) );
				$i = ( $i + 1 ) % $cols;
			}
		}
		else {
			while(list($region,$fields) = each($fieldsByRegion)) {
				$fieldColumns[] = table( null, $fields) ;
				$header[] = ucfirst($region);
			}
		}
		$output .= "<div class='fieldlist'>" . table($header, array( $fieldColumns) ) . "</div>";

		return $output;
	}
}
?>

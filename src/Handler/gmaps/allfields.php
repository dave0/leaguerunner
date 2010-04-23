<?php
class gmaps_allfields extends Handler
{
	function has_permission()
	{
		return true;
	}

	function render_header()
	{
		header("Content-type: text/xml");
?>
<markers>
<?php
	}

	function render_footer()
	{
		print  "\n</markers>";
	}

	function render_field( $field )
	{
		print "<marker lat=\"$field->latitude\" lng=\"$field->longitude\" fid=\"$field->fid\">\n";
		print "<balloon><![CDATA[<a href=\"" . url('field/view/'. $field->fid) . "\">$field->name</a> ($field->code)";
		if ($field->length) {
			print "<br/><a href=\"" . url('gmaps/view/'. $field->fid) . "\">Field map and layout</a>";
		}
		print "]]></balloon>\n";
		print "<tooltip>" . htmlentities($field->name) . " ($field->code)</tooltip>\n";
		print "<image>" . url('image/pins/' . $field->code . '.png') . "</image>";
		print "</marker>\n";
	}

	function process()
	{
		$this->render_header();
		$sth = field_query( array( '_extra' => 'ISNULL(f.parent_fid) AND f.status = "open"', '_order' => 'f.fid') );

		while( $field = $sth->fetchObject('Field') ) {
			if(!$field->latitude || !$field->longitude) {
				continue;
			}
			$this->render_field( $field );
		}

		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}

}

?>

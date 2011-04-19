<?php
require_once('Handler/TeamHandler.php');

class team_fieldpreference extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team', 'edit', $this->team->team_id);
	}

	function process ()
	{
		global $dbh;

		$this->title = $this->team->name . " &raquo; Rank Fields";

		$this->template_name = 'pages/team/fieldpreference.tpl';

		$fields = array();
		$selected = array();

		// Load any currently-selected selected fields
		$sth = $dbh->prepare("SELECT r.site_id, f.name, f.region FROM team_site_ranking r LEFT JOIN field f ON (f.fid = r.site_id) WHERE team_id = ? ORDER BY rank ASC");
		$sth->execute( array( $this->team->team_id)  );

		$chosen = array();
		while( $item = $sth->fetch(PDO::FETCH_OBJ) ) {
			$chosen[$item->site_id] = "$item->name ($item->region)";
			array_push($selected, $item->site_id);
		}
		if( count($chosen) > 0 ) {
			$fields['Previously Chosen'] = $chosen;
		}

		// Ugh, now figure out what fields to display
		$sth = $dbh->prepare("SELECT f.fid, f.region, f.name
			FROM field f
			WHERE f.fid IN ( SELECT
				DISTINCT COALESCE(f.parent_fid, f.fid)
				FROM leagueteams lt,
					league_gameslot_availability a,
					gameslot g,
					field f
				WHERE
					f.fid = g.fid
					AND g.slot_id = a.slot_id
					AND a.league_id = lt.league_id
					AND lt.team_id = ?)
			ORDER BY f.region, f.name");
		$sth->execute( array( $this->team->team_id ));
		while($field = $sth->fetch(PDO::FETCH_OBJ) ) {
			if( array_key_exists( $field->fid, $fields['Previously Chosen'] ) ) {
				continue;
			}
			if(! array_key_exists( $field->region, $fields) ) {
				$fields[$field->region] = array();
			}
			$fields[$field->region][$field->fid] = "$field->name ($field->region)";
		}
		$this->smarty->assign('fields', $fields);
		$this->smarty->assign('selected', $selected);
		$this->smarty->assign('team', $this->team);

		$edit = $_POST['edit'];


		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("team/fieldpreference/" . $this->team->team_id));
		}

		return true;
	}

	function check_input_errors ( $edit )
	{
		$errors = array();
		// TODO
		return $errors;
	}

	function perform ($edit = array())
	{
		global $dbh;

		$dbh->beginTransaction();
		$sth = $dbh->prepare("DELETE FROM team_site_ranking WHERE team_id = ?");
		$sth->execute( array($this->team->team_id) );
		$sth = $dbh->prepare("INSERT INTO team_site_ranking (team_id, site_id, rank) VALUES ( ?, ?, ?)");
		$i = 1;
		foreach($edit['fields'] as $field_id) {
			$sth->execute(array( $this->team->team_id, $field_id, $i++) );
		}
		$dbh->commit();
	}
}

?>

<?php
require_once('Handler/LeagueHandler.php');
require_once('Handler/person/search.php');

class league_member extends LeagueHandler
{
	private $player_id;

	function __construct( $id, $player_id = null )
	{
		parent::__construct( $id );
		$this->player_id = $player_id;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		global $lr_session;
		$this->title = "{$this->league->fullname} &raquo; Member Status";

		if( !$this->player_id ) {
			$new_handler = new person_search;
			$new_handler->smarty = &$this->smarty;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->league->fullname] = 'league/member/' .$this->league->league_id;
			$new_handler->extra_where = "(class = 'administrator' OR class = 'volunteer')";
			$new_handler->process();
			$this->template_name = $new_handler->template_name;
			return true;
		}

		if( !$lr_session->is_admin() && $this->player_id == $lr_session->attr_get('user_id') ) {
			error_exit("You cannot add or remove yourself as league coordinator");
		}

		$player = Person::load( array('user_id' => $this->player_id) );

		switch($_GET['edit']['status']) {
			case 'remove':
				if( ! $this->league->remove_coordinator($player) ) {
					error_exit("Failed attempting to remove coordinator from league");
				}
				break;
			default:
				if($player->class != 'administrator' && $player->class != 'volunteer') {
					error_exit("Only volunteer-class players can be made coordinator");
				}
				if( ! $this->league->add_coordinator($player) ) {
					error_exit("Failed attempting to add coordinator to league");
				}
				break;
		}

		if( ! $this->league->save() ) {
			error_exit("Failed attempting to modify coordinators for league");
		}

		local_redirect(url("league/view/" . $this->league->league_id));
	}
}

?>

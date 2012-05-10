<?php
/*
 * session_perm ($path)
 * Check permissions of the current session on th egiven path.
 *
 * This is intended for use in Smarty {if} tags like so:
 * 	{if session_perm("league/manage teams/`$league->league_id`") }
 * 	...
 * 	{/if}
 */
function session_perm($path)
{
        global $lr_session;

        list($module, $action, $a1, $a2) = preg_split("/\//", $path);
        return $lr_session->has_permission( $module, $action, $a1, $a2 );
}


# TODO: big cleanup necessary

function event_permissions ( &$user, $action, $id, $data_field )
{
	global $lr_session;

	switch( $action )
	{
		case 'create':
			// Only admin can create
			break;
		case 'edit':
			// Only admin can edit
			break;
		case 'delete':
			// Only admin can delete
			break;
		case 'view':
		case 'list':
			// Valid players can list
			return $lr_session->is_valid();
	}

	return false;
}

function field_permissions ( &$user, $action, $fid, $data_field )
{
	$public_data = array( 'public_instructions' );
	$member_data = array( 'site_instructions' );

	switch( $action )
	{
		case 'create':
			// Only admin can create
			break;
		case 'edit':
			// Admin and "volunteer" can edit
			if($user && $user->class == 'volunteer') {
				return true;
			}
			break;
		case 'view':
			// Everyone can view, but valid users get extra info
			if($user && $user->is_active()) {
				$viewable_data = array_merge($public_data, $member_data);
			} else {
				$viewable_data = $public_data;
			}
			if( $data_field ) {
				return in_array( $data_field, $viewable_data );
			} else {
				return true;
			}
		case 'list':
			// Everyone can list open fields, only admins can list closed; $fid here is the "closed" bool
			if (!$fid) {
				return true;
			}
			break;
		case 'view bookings':
			// Only valid users can view bookings
			if($user && $user->is_active()) {
				return true;
			}
			break;
		case 'view reports':
		case 'view rankings':
			// Admin and "volunteer" can view field reports and rankings
			if($user && $user->class == 'volunteer') {
				return true;
			}
			break;
	}

	return false;
}

function game_permissions ( &$user, $action, &$game, $extra )
{
	switch($action)
	{
		case 'submit score':
			if($extra) {
				if( $user && $user->is_captain_of($extra->team_id)) {
					// If we have a specific team in mind, this user must be a
					// captain to submit
					return true;
				} else {
					return false;
				}
			}
			if( $user && $user->is_captain_of( $game->home_team )
				|| $user->is_captain_of($game->away_team )) {
				// Otherwise, check that user is captain of one of the teams
				return true;
			}
			if($user && $user->is_coordinator_of($game->league_id)) {
				return true;
			}
			break;
		case 'edit':
		case 'delete':
			return ($user && $user->is_coordinator_of($game->league_id));
			break; // unreached
		case 'view':
			if( $extra == 'spirit' ) {
				return ($user && $user->is_coordinator_of($game->league_id));
			}
			if( $extra == 'submission' ) {
				return ($user && $user->is_coordinator_of($game->league_id));
			}
			return ($user && $user->is_active());
			break; // unreached

	}
	return false;
}

function league_permissions( $user, $action, $id, $data_field = '' )
{
	// TODO: finish this!
	switch($action)
	{
		case 'view':
			switch($data_field) {
				case 'spirit':
				case 'captain emails':
					return ($user && $user->is_coordinator_of($id));
				default:
					return true;
			}
			break;
		case 'list':
			return true;
		case 'edit':
		case 'edit game':
		case 'add game':
		case 'approve scores':
		case 'edit schedule':
		case 'manage teams':
		case 'ratings':
			return ($user && $user->is_coordinator_of($id));
		case 'create':
		case 'delete':
		case 'download':
			// admin only
			break;
	}
	return false;
}

function person_permissions ( &$user, $action, $arg1 = NULL, $arg2 = NULL )
{

	$all_view_fields = array( 'name', 'gender', 'willing_to_volunteer' );
	if (variable_get('dog_questions', 1)) {
		$all_view_fields[] = 'dog';
	}
	$restricted_contact_fields = array( 'email', 'home_phone', 'work_phone', 'mobile_phone' );
	$captain_view_fields = array( 'height', 'skill', 'shirtsize' );

	$self_edit_fields = array_merge(
		$all_view_fields,
		$captain_view_fields,
		$restricted_contact_fields,
		array('birthdate','address','height', 'shirtsize')
	);

	$create_fields = array_merge( $self_edit_fields, array( 'username') );

	$self_view_fields = array('username','birthdate','address','last_login', 'member_id','height','shirtsize');
	$self_view_fields = array_merge($all_view_fields, $restricted_contact_fields, $self_view_fields);

	switch( $action ) {
		case 'create':
			return true;
			break;
		case 'edit':
			if( 'new' == $arg1) {
				// Almost all fields can be edited for new players
				if( $arg2 ) {
					return( in_array( $arg2, $create_fields ) );
				} else {
					return true;
				}
			}
			if( ! $user || ! $user->is_active() ) {
				return false;
			}
			if( $user->user_id == $arg1 ) {
				if( $arg2 ) {
					return( in_array( $arg2, $self_edit_fields ) );
				} else {
					return true;
				}
			}
			break;
		case 'password_change':
			// User can change own password
			if( is_numeric( $arg1 ))  {
				if( $user->user_id == $arg1 ) {
					return true;
				}
			}
			break;
		case 'view':
			if( ! ($user && $user->is_active()) ) {
				return false;
			}

			if( is_numeric( $arg1 )) {
				if( $user->user_id == $arg1 ) {
					// Viewing yourself allowed, most fields
					if( $arg2 ) {
						return( in_array( $arg2, $self_view_fields ) );
					} else {
						return true;
					}
				} elseif ( ! $user->is_player() ) {
					// Name only
					if( $arg2 ) {
						return( in_array( $arg2, array('name') ) );
					} else {
						return true;
					}
				} else {
					// Other user.  Now comes the hard part
					$player = Person::load( array('user_id' => $arg1) );

					// New or locked players cannot be viewed.
					if( $player->status == 'new' || $player->status == 'locked' ) {
						return false;
					}

					$sess_user_teams = implode(",",array_keys($user->teams));
					$viewable_fields = $all_view_fields;

					/* If player is a captain, their email is viewable */
					if( $player->is_a_captain ) {
						// Plus, can view email
						$viewable_fields[] = 'email';
					}

					if($user->is_a_captain) {
						/* If the current user is a team captain, and the requested user is on
						 * their team, they are allowed to view email/phone
						 */
						foreach( $player->teams as $team ) {
							if( $user->is_captain_of( $team->team_id ) &&
								$team->position != 'captain_request' ) {
								/* They are, so publish email and phone */
								$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields, $captain_view_fields);
								break;
							}
						}

						/* If the current user is a team captain, and the requested user is
						 * captain for a "nearby" team, they are allowed to view email/phone
						 */
						if($player->is_a_captain) {
							foreach( $player->teams as $player_team ) {
								if( $player->is_captain_of( $player_team->team_id ) ) {
									foreach( $user->teams as $user_team ) {
										if( $user->is_captain_of( $user_team->team_id ) &&
											$player_team->league_id == $user_team->league_id )
										{
											$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
										}
									}
								}
							}
						}
					}

					/* Coordinator info is viewable */
					if($player->is_a_coordinator) {
						$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
					}

					/* Coordinators get to see phone numbers of the captains they handle */
					if($user->is_a_coordinator && $player->is_a_captain) {
						foreach( $player->teams as $team ) {
							if( $player->is_captain_of( $team->team_id ) &&
								$user->coordinates_league_containing( $team->team_id ) )
							{
								$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
							}
						}
					}

					// Finally, perform the check and return
					if( $arg2 ) {
						return( in_array( $arg2, $viewable_fields ) );
					} else {
						return true;
					}
				}
			}
			break;
		case 'list':
		case 'search':
			if( ! ($user && $user->is_active()) ) {
				return false;
			}
			if( $arg1 ) {
				// Specific searches require admin access
				return false;
			}
			return($user->class != 'visitor');
		case 'approve':
			// administrator-only
		case 'delete':
			// administrator-only
		case 'listnew':
			// administrator-only
		default:
			return false;
	}

	return false;
}

function registration_permissions ( &$user, $action, $id, $registration )
{
	global $lr_session;

	if($action == 'paypal')
		return true;

	if (!$lr_session || !$lr_session->user)
		return false;

	switch( $action )
	{
		case 'view':
		case 'edit':
		case 'addpayment':
		case 'delpayment':
			// Only admin can view details or edit
			break;
		case 'register':
		case 'paypal':		// Paypal PDT with valid player info and currently active user
			// Only admins can register other players
			if( ! is_null($id) ) {
				return ($id == $lr_session->user->user_id || $lr_session->is_admin());
			}
			// Otherwise, only players with completed profiles can register
			return ($lr_session->user->is_active() && $lr_session->is_complete());
		case 'unregister':
			// Players may only unregister themselves from events before paying.
			// TODO: should be $registration->user_can_unregister()
			if($lr_session->user->is_active() && $lr_session->is_complete() && $registration->user_id == $lr_session->user->user_id) {
				if($registration->payment != 'Unpaid' && $registration->payment != 'Pending') {
					// Don't allow user to unregister from paid events themselves -- admin must do it
					return 0;
				}
				return 1;
			}
			return 0;

		case 'history':
			// Players with completed profiles can view their own history
			if ($id) {
				return ($lr_session->is_complete() && $lr_session->user->user_id == $id);
			}
			else {
				return ($lr_session->is_complete());
			}
		case 'statistics':
		case 'download':
			// admin only
			break;
	}

	return false;
}

function team_permissions ( &$user, $action, $id, $data_field )
{
	switch( $action )
	{
		case 'create':
			// Players can create teams at-will
			return ($user && $user->is_active());
		case 'list':
		case 'view':
			// Everyone can list and view if they're a player
			return ($user && $user->is_active());
		case 'view schedule':
			return true;
		case 'edit':
		case 'player rating':
		    if( $data_field == 'home_field' ) {
				return ($user && $user->is_admin());
			}
			return ($user && $user->is_captain_of( $id ) );
		case 'player shirts':
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'email':
			if( $user && $user->is_captain_of( $id ) ) {
				return true;
			}
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'player status':
			if( $user && $user->is_captain_of( $id ) ) {
				// Captain can adjust status of other players
				return true;
			}
			if( $user && $user->user_id == $data_field ) {
				// Player can adjust status of self
				return true;
			}
			break;
		case 'delete':
			if( $user && $user->is_captain_of( $id ) ) {
				return true;
			}
			break;
		case 'move':
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'statistics':
			// admin-only
			break;
		case 'spirit':
			return ($user && $user->is_player_on( $id ));
		case 'viewfieldprefs':
			return ($user && ($user->is_player_on( $id ) || $user->coordinates_league_containing( $id )));
	}
	return false;
}

function gameslot_permissions ( &$user, $action, $arg1 = NULL, $arg2 = NULL )
{
	// Admin-only
	return false;
}

function season_permissions( $user, $action, $id, $data_field = '' )
{
	global $lr_session;

	if (!$lr_session || !$lr_session->user) {
		return false;
	}

	switch($action)
	{
		case 'view':
			return true;
		case 'list':
			return true;
		case 'edit':
		case 'create':
		case 'delete':
			// admin only
			break;
	}
	return false;
}

?>

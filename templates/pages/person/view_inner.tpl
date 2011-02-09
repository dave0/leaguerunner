<img align="right" src="{$person->get_gravatar(120)}" width="120" height="120" />
<div class='pairtable'><table>
  <tr>
    <td>Name:</td>
    <td>{$person->fullname}</td>
  </tr>
  {if session_perm("person/view/`$person->user_id`/username")}
  <tr>
    <td>System Username:</td>
    <td>{$person->username}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/status")}
  <tr>
    <td>Account Status:</td>
    <td>{$person->status|default:Unspecified}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/member_id")}
  <tr>
    <td>Member ID:</td>
    <td>
    {if $person->member_id}
    	{$person->member_id}
    {else}
    	Not a full member.
    {/if}
    </td>
  </tr>
  {/if}
  {if ($person->allow_publish_email == 'Y') || session_perm("person/view/`$person->user_id`/email")}
  <tr>
    <td>Email Address:</td>
    <td><a href="mailto:{$person->email}">{$person->email}</a> ({if $person->allow_publish_email == 'Y'}published{else}private{/if})</td>
  </tr>
  {/if}

  {if $person->home_phone && (($person->publish_home_phone == 'Y') || session_perm("person/view/`$person->user_id`/home_phone"))}
  <tr>
    <td>Phone (home):</td>
    <td>{$person->home_phone} ({if $person->publish_home_phone == 'Y'}published{else}private{/if})</td>
  </tr>
  {/if}
  {if $person->work_phone && (($person->publish_work_phone == 'Y') || session_perm("person/view/`$person->user_id`/work_phone"))}
  <tr>
    <td>Phone (work):</td>
    <td>{$person->work_phone} ({if $person->publish_work_phone == 'Y'}published{else}private{/if})</td>
  </tr>
  {/if}
  {if $person->mobile_phone && (($person->publish_mobile_phone == 'Y') || session_perm("person/view/`$person->user_id`/mobile_phone"))}
  <tr>
    <td>Phone (mobile):</td>
    <td>{$person->mobile_phone} ({if $person->publish_mobile_phone == 'Y'}published{else}private{/if})</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/address")}
  <tr>
    <td>Address:</td>
    <td>
	{include file='components/street_address.tpl'
		street=`$person->addr_street`
		city=`$person->addr_city`
		province=`$person->addr_prov`
		country=`$person->addr_country`
		postalcode=`$person->addr_postalcode`
	}
    </td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/birthdate")}
  <tr>
    <td>Birthdate:</td>
    <td>{$person->birthdate}</td>
  </tr>
  {/if}

  {if $person->height && session_perm("person/view/`$person->user_id`/height")}
  <tr>
    <td>Height:</td>
    <td>{math equation=floor(h/12) h=$person->height}' {$person->height%12}"</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/gender")}
  <tr>
    <td>Gender:</td>
    <td>{$person->gender}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/shirtsize")}
  <tr>
    <td>Shirt Size:</td>
    <td>{$person->shirtsize|default:Unspecified}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/skill")}
  <tr>
    <td>Player Rating:</td>
    <td>{if $person->skill_level}{$person->skill_level} (out of 10){else}Unknown{/if}</td>
  </tr>
  <tr>
    <td>Year Started:</td>
    <td>{$person->year_started|default:Unspecified}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/class")}
  <tr>
    <td>Account Class:</td>
    <td>{$person->class|default:Unspecified}</td>
  </tr>
  {/if}

  {if $person->has_dog && session_perm("person/view/`$person->user_id`/dog")}
  <tr>
    <td>Has Dog:</td>
    <td>{if $person->has_dog == 'Y'}
    	yes (waiver signed {$person->dog_waiver_signed})
	{else}
	no
	{/if}
    </td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/willing_to_volunteer")}
  <tr>
    <td>Volunteer Contact?</td>
    <td>
    {if $person->willing_to_volunteer == 'Y'}
    Yes, player can be contacted regarding volunteering.
    {else}
    No, player cannot be contacted.
    {/if}
    </td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/contact_for_feedback")}
  <tr>
    <td>Contact for feedback?</td>
    <td>
    {if $person->contact_for_feedback == 'Y'}
    Yes, player can be contacted for feedback on OCUA programs.
    {else}
    No, player cannot be contacted.
    {/if}
    </td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/created")}
  <tr>
    <td>Account Created:</td>
    <td>{$person->created|default:'Unknown'}</td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/last_login")}
  <tr>
    <td>Last Login:</td>
    <td>{$person->last_login|default:'Never logged in'} from {$person->client_ip|default:'Nowhere'}</td>
  </tr>
  {/if}

  <tr>
    <td>Teams:</td>
    <td>
      <table>
	{foreach item=t from=$teams}
        <tr>
	   <td>{$t->rendered_position}</td>
	   <td>on</td>
	   <td><a href="{lr_url path="team/view/`$t->team_id`"}">{$t->name}</a></td>
	   <td>(<a href="{lr_url path="league/view/`$t->league_id`"}">{$t->league_name}</a>)</td>
	</tr>
	{foreachelse}
	<tr><td colspan='4'>No teams</td></tr>
	{/foreach}
      </table>
    </td>
  </tr>

  {if $person->is_a_coordinator}
  <tr>
    <td>Leagues:</td>
    <td>
      <table>
	{foreach item=l from=$person->leagues}
        <tr>
	   <td>Coordinator</td>
	   <td>of</td>
	   <td><a href="{lr_url path="league/view/`$l->league_id`"}">{$l->fullname}</a></td>
	</tr>
	{foreachelse}
	<tr><td colspan='4'>No leagues</td></tr>
	{/foreach}
      </table>
    </td>
  </tr>
  {/if}

  {if session_perm("registration/history/`$person->user_id`")}
  <tr>
    <td>Registration:</td>
    <td><a href="{lr_url path="registration/history/`$person->user_id`"}">view registration history</a></td>
  </tr>
  {/if}

  {if session_perm("person/view/`$person->user_id`/notes")}
  <tr>
    <td>Notes:</td>
    <td>
      <table class="baretable">
	{foreach item=n from=$person->get_notes()}
        <tr>
	   <td><a href="{lr_url path="note/view/`$n->id`"}">{$n->created}</a></td><td>{$n->note}</td>
        </tr>
        <tr>
           <td></td>
	   <td>(note added by {$n->creator->fullname} )</td>
	</tr>
	{foreachelse}
	<tr><td colspan='4'>No notes</td></tr>
	{/foreach}
      </table>
    </td>
  </tr>
  {/if}



</table></div>

  <tr>
    <td>Name:</td>
    <td><a href="{lr_url path="person/view/`$registrant->user_id`"}">{$registrant->fullname}</a></td>
  </tr>
  <tr>
    <td>Member ID:</td>
    <td>{$registrant->member_id}</td>
  </tr>
  <tr>
    <td>Event:</td>
    <td><a href="{lr_url path="event/view/`$event->registration_id`"}">{$event->name}</a></td>
  </tr>
  <tr>
    <td>Created On:</td>
    <td>{$reg->time}</td>
  </tr>
  <tr>
    <td>Last Modified On:</td>
    <td>{$reg->modified}</td>
  </tr>
  <tr>
    <td>Registered Price:</td>
    <td>${$reg->total_amount|string_format:"%.2f"}</td>
  </tr>
  <tr>
    <td>Registration Status:</td>
    <td>{$reg->payment}</td>
  </tr>
  <tr>
    <td>Balance Owed:</td>
    <td>${$reg->balance_owed()|string_format:"%.2f"}</td>
  </tr>
{if session_perm("registration/viewnotes/`$reg->order_id`")}
  <tr>
    <td>Notes:</td>
    <td>{$reg->notes}</td>
  </tr>
{/if}

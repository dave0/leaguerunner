  <tr>
    <td>Name:</td>
    <td>{$person->fullname}</td>
  </tr>
  <tr>
    <td>Email Address:</td>
    <td>{$person->email}</td>
  </tr>
  <tr>
    <td>{if variable_get('birth_year_only', 0) }Year{else}Date{/if} of Birth:</td>
    <td>{$person->birthdate}</td>
  </tr>

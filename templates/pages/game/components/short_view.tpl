  <tr>
    <td>Game ID:</td>
    <td>{$game->game_id}</td>
  </tr>
  <tr>
    <td>Date and Time:</td>
    <td>{$game->game_date}, {$game->game_start} until {$game->display_game_end()}</td>
  </tr>
  <tr>
    <td>League:</td>
    <td>{$game->league->fullname}</td>
  </tr>
  <tr>
    <td>Home Team:</td>
    <td>{$game->home_name}</td>
  </tr>
  <tr>
    <td>Away Team:</td>
    <td>{$game->away_name}</td>
  </tr>
  <tr>
    <td>Field:</td>
    <td>{$game->field_code}</td>
  </tr>

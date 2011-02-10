  <tr>
    <td>Season:</td>
    <td><a href="{lr_url path="season/view/`$event->season_id`"}">{$event->season_name}</a></td>
  </tr>
  <tr>
    <td>Description:</td>
    <td>{$event->description}</td>
  </tr>
  <tr>
    <td>Type:</td>
    <td>{$event->get_long_type()}</td>
  </tr>
  <tr>
    <td>Price:</td>
    <td>${$event->total_cost()}</td>
  </tr>
  <tr>
    <td>Opens on:</td>
    <td>{$event->open}</td>
  </tr>
  <tr>
    <td>Closes on:</td>
    <td>{$event->close}</td>
  </tr>

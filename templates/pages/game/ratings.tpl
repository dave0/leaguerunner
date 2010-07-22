{include file=header.tpl}
<h1>{$title}</h1>
<h2>{$game->home_name} (rating {$rating_home}) vs {$game->away_name} (rating {$rating_away})</h2>
<form name='whatif' method='GET'>
    <label>{$game->home_name}<input type='text' size=5 name='rating_home' value='{$rating_home}' /></label>
    <label>{$game->away_name}<input type='text' size=5 name='rating_away' value='{$rating_away}' /></label>
    <input type="submit" value="Test different rating values" />
</form>
{$ratings_table}
<p>
    The number of rating points transferred depends on several factors:
    <ul>
        <li> the total score
        <li> the difference in score
        <li> and the current rating of both teams
    </ul>
</p>
<p>
    How to read the table above:
    <ul>
        <li> Find the 'home' team's score along the left.
        <li> Find the 'away' team's score along the top.
        <li> The points shown in the table where these two scores intersect are the number of rating points that will be transfered from the losing team to the winning team
    </ul>
</p>
<p>
    A tie does not necessarily mean 0 rating points will be transfered. Unless
    the two team's rating scores are very close, one team is expected to win.
    If that team doesn't win, they will lose rating points. The opposite is
    also true: if a team is expected to lose, but they tie, they will gain some
    rating points.
</p>
<p>
    Ties are shown from the home team's perspective.  So, a negative value
    indicates that in the event of a tie, the home team will lose rating points
    (and the away team will gain them).
</p>
{include file=footer.tpl}

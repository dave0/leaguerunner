{include file="header.tpl"}
<script type="text/javascript" src="{$base_url}/js/table-of-contents.js"></script>
{literal}
<style type="text/css">
	#toc { background-color: #F0F0F0; padding: 1em; }
        #toc a { display:block;}
        #toc a[title=H2] { font-size:14px;}
        #toc a[title=H3] { font-size:12px;}
</style>
{/literal}

<h1>{$title}</h1>
  <p>
  This document is intended to answer frequently asked questions about OCUA's
  Leaguerunner implementation.
  </p>
  <p><i>Please note that this document is a work in progress.  As such, if there
are questions you'd like answered, please don't hesitate to contact the <a
href="mailto:{$app_admin_email}">{$app_admin_name}</a></i>
  </p>

  <!--  Table Of Contents -->
  <div id="toc">
  <b>Table Of Contents</b>
  </div>

  <h2>What's the SBF?</h2>
  <p>
    The SBF is the "Spence Balance Factor".  It was suggested during
    discussions about Thursday Indoor 2003 as a way to see how balanced a
    particular tier was.
  </p>
  <p>
    The Leaguerunner implementation of the SBF is a straight average of the
    point differentials (as a positive number) of all games played.  In the
    case of the <i>League SBF</i>, this is all games in the league.  For the
    <i>Team SBF</i>, this is all games played by that team.
  </p>
  <p>
    Assuming that this measurement has actual statistical validity (something
    that has not yet been proven), these numbers can be used to measure the
    'closeness' of game scores for a tier/division or a team.  Tiers with lower
    SBF values generally have closer games, and thus are more balanced.  Teams
    whose SBF is much higher than the SBF for the tier they're in may be good
    candidates for moving up or down (depending on their win record) to another
    tier.
  </p>

  <h2>What's the 'rating'?</h2>
  <p>
    The rating, as shown on the team view page and on the standings, is a
    measure of the team's past performance.  Higher ratings indicate that a
    team has done well against its previous opponents and should probably do
    well against opponents with lower ratings in the future.
  </p>
  <p>
    The rating system used by Leaguerunner is based on the Elo system which was
    originally designed for ranking chess players.   The system was adapted for
    team sports in 1997 by <a href="http://www.eloratings.net">The World
    Football Elo Ratings</a> site, and further modified to be more applicable
    for Ultimate by the author of Leaguerunner.
  </p>
  <p>
    Here's how it works.  Each team starts at 1500, and moves up or down from
    there based on game performance.  Each game is worth a base value, adjusted
    for strength of schedule (based on your rating as compared to your
    opponent's rating) with bonuses given for higher point-differentials
    (games won by a difference of more than 1/3 the winning score).  The
    winning team gets the value for that game added to their rating, and the
    losing team gets it subtracted.  In this manner, "upset" wins are worth
    more than "expected" wins for rating purposes,  so if a team with a 1500
    rating beats one with a 1600 rating, it's a higher-value game than if the
    1600-rated team beat the 1500-rated one.
  </p>
  <p>
    Further details, as well as the mathematical formulas used to calculate the
    rating, will be posted here later.
  </p>
{include file="footer.tpl"}

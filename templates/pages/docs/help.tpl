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
This document outlines the way the <b>OCUA Leaguerunner</b> system works.  
Guides are broken down in to categories based on what sort of role you have in the system.</p>

<p><i>Please note that this document is a work in progress.  As such, you might run into 
things you're trying to do that aren't covered here.  If you have any questions, don't 
hesitate to contact <a href="mailto:webmaster@ocua.ca">the webmasters</a></i></p>

<p>If there are specific questions you're looking for the answers to, check out
the <a href="{lr_url path="docs/faq"}">Leaguerunner FAQ</a>.</p>

<!--  Table Of Contents -->
<div id="toc">
<b>Table Of Contents</b>
</div>

<!-- Start General Section -->
<h2>General</h2>

<h3>Create a new account</h3>

<p>In order to do anything in the system, you have to have an account.  To start the process, 
go to the <a href="/leaguerunner/">login page</a> and click on <b>Create new account</b>.  You 
will be brought to a form where you are asked for various information, including address, phone 
number(s), email address, skill level, and date of birth.  You also have control over what aspects 
of your contact information is available for other people in the system to see.  See 
<a href="#A.5">section A.5</a> for details on when your preferences might be overridden.</p>

<p>After you're done filling in the form and you've hit submit (twice) your account will be up for review. 
This is simply a step where a system administrator will review your account to make sure it isn't a duplicate 
of an existing account in the system.  If you're creating a new account because you've forgotten your 
username of password, don't.  Please see <a href="#A.3">section A.3</a> for help with getting your username and 
password emailed to you.</p>

<p>Once your account has been approved, you can now login.  The first time you login to the system, you will 
be asked to:</p>

<ol type="a" style="margin-left: 20pt;">
	<li>Sign the <a href="{lr_url path="person/signwaiver"}">waiver form</a></li>
	<li>Optionally sign the <a href="{lr_url path="person/signdogwaiver"}">dog owner waiver form</a> if you intend to bring your dog(s) to games.</li>
</ol>

<p>If you do not sign either of the waver forms your account will not become active, and you can't register with 
your team(s).</p>

<p>Once you're done all that, you can start using the rest of the system.</p>

<h3>Login</h3>

<p>Simply go to the <a href="/leaguerunner/">login page</a> and enter your username and password.  If you've 
forgotten your username and / or password then get you can <a href="#A.3">get a reminder emailed to you</a>.  
If you don't have an account yet, you have to <a href="#A.1">create one first</a>.</p>

<h3>Forgot your login information</h3>

<p>If you've forgotten your username and / or your password, then you can get a reminder emailed to you.  Go to 
the <a href="/leaguerunner/">login page</a> and click on <b>Forgot your password?</b>.  You will be brought to 
a form where you can enter any of your <b>membership number</b>, <b>username</b>, or <b>email address</b>.  The 
system will email you a new password at the address that is in the system.  If you can't remember either your 
membership number or your username and you don't have access to your email address of record any more, then you 
will need to contact <a href="mailto:leaguerunner@ocua.ca">the leaguerunner administrator</a> and provide your full name, street address, 
and date of birth so that we can confirm that you are in fact you.</p>

<h3>Edit Your Information</h3>

<p>If any of your contact information has changed from when you first created your account, then you'll need to 
update it.  First of all, <a href="/leaguerunner/">login to the system</a>.
Then, from the sidebar menu, click on <b>My Account</b>, and then <b>edit</b>.
Once you've updated all the appropriate info, hit <b>submit</b>.  You will see a confirmation page, so check that all your information is correct, then submit again and you're done.</p>

<h3>Change your password</h3>

<p>Login, click on <b>My Account</b> from the left menu, then click "change password" and follow the directions.</p>

<h3>What can people see?</h3>

<p>When you are entering / editing your personal information, there are check
boxes next to your email address and phone numbers.  If you check one of those
boxes, that piece of information will be publicly available to any other
player logged in to the system.  Your gender, skill level, name, and the list of teams you play on will always be visible, but other information will not be.</p>

<p>There are some exceptions to the above rule:</p>
<ul style="margin-left: 20pt;">
	<li>If you are a coordinator, your email address is viewable by all captains in your tier.</li>
	<li>If you are a captain, your email address is viewable by all your players, your coordinator, 
	and other captains in your tier.</li>
	<li>If you are a captain, you can see the email address for every player on your team.</li>
	<li>The system administrator can see everything.</li>
</ul>

<p>If you have any concerns about your information being made available to outside parties, please 
<a href="http://www.ocua.ca/node/17">review our Privacy Policy</a> and / or contact <a href="mailto:webmaster@ocua.ca">the 
webmaster</a>.</p>

<p><font size="-2"><a href="#toc">Back to top</a></font></p>

<hr align="center" width="75%" />

<!-- Start Player's Section -->
<h2>Players</h2>

<h3>Join a team</h3>

<p>There are two ways to become part of a team:</p>
<ol type="a"  style="margin-left: 20pt;">
	<li><b>Ask to be a member:</b> This is accomplished by logging in and clicking on <b>list teams</b> in the side menu.  You are presented with an alphabetical list of teams.  To see teams whose name starts with a given letter, click on that letter.
	Once you have found the team you wish to join, click on <b>view</b> and then click <b>join team</b> in the left menu.  Note that this option will only be available if the captain has set the Team Status to "open" instead of "closed".  The captain will 
	need to confirm you on the team after this.</li>
	<li><b>Confirm membership request:</b> A captain can add you to his
	team.  Once he's done that you then have to accept membership on that
	team.  Any teams that you have been added to by a captain will show up
	in your main menu.  Click on the team's name, and then on the
	"position" in the second column beside your name on the team roster
	page.  You will be presented with the option of setting yourself to a
	regular, a substitute, or removing yourself from the team.</li>
</ol>

<h3>Leave a team</h3>

<p>To leave a team you are currently on, first view the team, and then click on
the position next to your name in the roster list.  Select <b>remove from
team</b> and you're done.</p>

<p><font size="-2"><a href="#toc">Back to top</a></font></p>

<hr align="center" width="75%" />

<!-- Start Captain's Section -->
<h2>Captains</h2>

<h3>Create a new team</h3>

<p>If you are captaining a brand new team then you need to create the team in
the system.  To do so, click on the <b>create team</b> item in the side menu.
Provide your team's name, website (if appropriate) and shirt color.  There,
you're done.  The team will be added to the "Inactive Teams" tier until the
coordinator moves you to the appropriate league.</p>

<p><b>Naming Convention:</b> If you are the captain of more then one team with
the same name that plays on more then one night, you will need create multiple
teams.  In order to make it easy to differentiate between the teams, please
append something that indicates which night the team is for (<i>eg:</i> Fuzzy
Pickles - Monday)</p>

<h3>Add a co-captain</h3>

<p>All teams in the system should have two captains on the team.  To mark
someone as a team captain or assistant captain, go to the info page for the
team, and click on the status next to the person you wish to make co-captain.
Select captain or assistant from the list and you're done.</p>

<p><b>Please note:</b> Each team must have at least one captain and one assistant (or two captains, if you so choose).</p>

<h3>Add a player to your team</h3>

<p>Rather then waiting for your players to add themselves to your team's
roster, you can go out and add them instead.  You do this by going to the team
info page, and clicking on <b>add player</b> in the left menu.  From there you
can browse through all the people in the system until you find the person
you're looking for.  Click on <b>add player</b> and you're done.</p>

<p><b>Please note:</b> The person you added to your team still has to log in
and confirm that they are on your team before the addition is official.</p>

<h3>Accept a player on your team</h3>

<p>Your players can go onto the system and add themselves to your team.
However, you still have to accept them onto your team before they are official
members.  To do this, go to the team info page and click on the position link
next to any player whose status is shown as <b>requested by player</b>.  You
will have the option of setting the person as a regular player, a substitute, a
captain, an assistant, or removing them from the team entirely</p>

<h3><a nane="C.5">C.5. Submit game score</a></h3>

<p>One of your most important tasks as a team captain is the timely submission of scores and spirit ratings after a game.  To 
do this click on the team name in the left menu, then click <b>schedule</b>
In the schedule view, click on the <b>submit score</b> link next to the game your are submitting.  Enter the final score of the 
game, and the SOTG score for your opponents, and hit submit.</p>

<p>Standings aren't updated until both captains have submitted matching scores.  If your score entry and your opponent's don't 
match, then the coordinator will need to resolve the discrepancy before the final results are reflected in the standings.</p>

<h3>Open / close your team to new members</h3>

<p>Team rosters can have one of two states: open or closed.  If a team is closed, then only the team captain(s) can add 
new players to their team (still requires player to accept).  If a team is open, individuals can add themselves to your 
team (still requires captain to accept).</p>

<p>To change your team from open to closed (and vice versa) simply go to the
info page for your team, click on <b>edit team</b> in the left menu, and select
the appropriate new state.</p>

<p><font size="-2"><a href="#toc">Back to top</a></font></p>

<hr align="center" width="75%" />

<!-- Start Coordinator's Section -->
<h2>Coordinator</h2>

<h3>Move teams between tiers</h3>

<p>Once your tiers are created, you'll need to move teams into them.  The first
step is to find the team(s) you are looking to move.  Unassigned teams can be
found in the Inactive Teams tier (in the left menu) and then the <b>none</b>
season, and then <b>view</b>.  Next to the team you want click on <b>move
team</b>.  You will be presented with a list of the tiers you control.  Select
the tier in which the team should be placed, and hit submit.</p>

<h3>Edit a tier's configuration</h3>

<h3>Add a week to a schedule</h3>

<p>Start by accessing the schedule page for the tier you want to work on.  Click on the <b>Add a week</b> 
link at the bottom of the schedule page.  You'll be presented with a calendar.  Click on the date you want to 
add games for.  Now, fill in the matchups and field assignments for the games and hit submit.</p>

<p>If you need to create a double header on a particular date, simply repeat the above steps for the date 
of the double header.</p>

<h3>Edit an existing week in a schedule</h3>

<p>So, you've already entered you schedule into the system but you've gotta fix something.  Here's what you do.
Go and view the schedule for the tier you want to edit, and click on the <b>edit week</b> link in the week's 
title box.  Once you've made your changes, hit submit.</p>

<h3>Fix mismatched scores</h3>

<p>

<h3>Start a new round</h3>

<p>When one Round Robin has come to an end and it's time to start the 2nd, the first thing you should do is to 
<a href="D.2">edit your tier's info</a> to indicate the new round.  This will set the default value for <b>round</b> 
to be the new round when adding weeks to the schedule, and will also start a new set of standings on the standings grid.</p>

<p><font size="-2"><a href="#toc">Back to top</a></font></p>
{include file="footer.tpl"}

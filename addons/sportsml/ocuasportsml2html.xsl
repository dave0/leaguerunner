<?xml version="1.0" encoding="ISO-8859-1"?>

<xsl:stylesheet
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
	>

<xsl:strip-space elements="*"/>

<xsl:output method="html" encoding="ISO-8859-1"/>

<!-- 
	SportsML formatting for OCUA league schedules/scores by Dave O'Neill
	Only deals with <schedule> and <standing> subelements.
	
	Based on sample XSLT code originally created by Johan Lindgren (TT,
	Sweden) to show various possible outputs from SportsML.
  -->


<!-- main template -->
<xsl:template match="sports-content">
	<html>
		<head>
			<title><xsl:value-of select="sports-metadata/sports-title"/></title>
			<link rel="stylesheet" type="text/css" href="sportsml.css"/>
		</head>
		<body>
		
		<h1 class="docTitle"><xsl:value-of select="sports-metadata/sports-title"/></h1>
		

			<xsl:choose>
			<xsl:when test="sports-content">
				<xsl:apply-templates/>    <!-- Call all subtemplates -->
			</xsl:when>
			<xsl:otherwise>
				<table width="100%" class="bodyTable" cellpadding="3"><tr valign="top"><td>
				<!--
				<xsl:apply-templates />
				-->

				<!-- comment out below when you do not want metadata block -->
				<!--
				<xsl:apply-templates select="sports-metadata"/>
				-->

				<xsl:apply-templates select="sports-event"/>
				<xsl:apply-templates select="standing"/>
				<xsl:apply-templates select="schedule"/>
				</td></tr></table>
			</xsl:otherwise>
			</xsl:choose>
		</body>
	</html>
</xsl:template>
<!-- end main template -->


<!-- template for standings -->
<xsl:template match="standing">
	<xsl:if test="@date-label or @content-label">
		<p class="standline">Standings for: <xsl:value-of select="@content-label"/><xsl:text> </xsl:text><xsl:value-of select="@date-label"/></p>
	</xsl:if>

	<!-- uncomment the part below when debugging -->
	<!--
	<table><tr><td bgcolor="#cccccc">
	<xsl:apply-templates select="standing-metadata/sports-content-codes"/>
	</td></tr></table>
	-->

	<table border="0" cellpadding="3" cellspacing="0"><tdata>
  	<tr>
	  <td class='standings_title' valign='middle' colspan='2' rowspan='2'>Team Name</td>
          <td class='standings_title' valign='bottom' colspan='7'>Season To Date</td>
	  <td class='standings_title' valign='middle' rowspan='2'>Avg. SOTG</td>
	</tr>

	<tr>
	  <td class='standings_subtitle' valign='bottom' >Win</td>
	  <td class='standings_subtitle' valign='bottom' >Loss</td>
	  <td class='standings_subtitle' valign='bottom' >Tie</td>
	  <td class='standings_subtitle' valign='bottom' ><span title="Defaulted games">Dfl</span></td>
	  <td class='standings_subtitle' valign='bottom' ><span title="Points For">PF</span></td>
	  <td class='standings_subtitle' valign='bottom' ><span title="Points Against">PA</span></td>
	  <td class='standings_subtitle' valign='bottom' ><span title="Plus/Minus ranking">+/-</span></td>
	</tr>

	<xsl:for-each select="team">             <!--process all teams-->
		<xsl:call-template name="standing-team">
			<xsl:with-param name="oneteam" select="."/>
		</xsl:call-template>
	</xsl:for-each>
	</tdata></table>
</xsl:template>
<!-- end template for standing -->


<!-- Named template to process a  team in a standing -->
<xsl:template name="standing-team">
	<xsl:param name="oneteam"/>
	<tr valign="top">
		<!-- put the rank in the first field-->
		<td class='standings_item'>
		  <xsl:value-of select="$oneteam/team-stats/rank/@value"/> 
		</td>

		<!--Build the name in the second field-->
		<td class='standings_item' nowrap="nowrap">
		  <xsl:for-each select="$oneteam/team-metadata/name">
		    <xsl:if test="@language">
		      <xsl:value-of select="@language"/>:
		    </xsl:if>
		      <xsl:call-template name="choose-name">
		        <xsl:with-param name="team-meta" select="$oneteam/team-metadata"/>
			<xsl:with-param name="shownickname" select="'yes'"/>
		      </xsl:call-template>
		    <br/>
		  </xsl:for-each>
		</td>

		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/outcome-totals/@wins"/>
		</td>
		<td class="standings_item">
	  	  <xsl:value-of select="$oneteam/team-stats/outcome-totals/@losses"/>
		</td>
		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/outcome-totals/@ties"/>
		</td>
		
		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/team-stats-ultimate/stats-ultimate-miscellaneous/@defaults"/>
		</td>

		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/outcome-totals/@points-scored-for"/>
		</td>

		<td class="standings_item">
			<xsl:value-of select="$oneteam/team-stats/outcome-totals/@points-scored-against"/>
		</td>

		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/team-stats-ultimate/stats-ultimate-miscellaneous/@plusminus"/>
		</td>

		<td class="standings_item">
		  <xsl:value-of select="$oneteam/team-stats/team-stats-ultimate/stats-ultimate-spirit/@value"/>
		</td>
	</tr>
</xsl:template>
<!-- end template for team lists in standings -->


<!-- Template to catch schedules -->
	<!--
<table border="0" cellpadding="3" cellspacing="0"><tdata>
  <tr>
    <td class="schedule_title" valign='top' colspan='7'> 
      <font face="verdana,arial,helvetica" color='#FFFF00'><b>Mon May 19 2003</b></font>
    </td>
    <td class="schedule_title" colspan='2'>
                  <a class="topbarlink" href="/leaguerunner/index.php?op=league_schedule_view&id=11&week_id=2003139">edit week</a>
              </td>

  </tr>

  <tr>
    <td class="schedule_subtitle" rowspan="2">Round</td>
    <td class="schedule_subtitle" rowspan="2">Game Time</td>
    <td class="schedule_subtitle" rowspan="2">Home</td>
    <td class="schedule_subtitle" rowspan="2">Away</td>

    <td class="schedule_subtitle" rowspan="2">Field</td>
    <th class="schedule_subtitle" colspan="2">Score</td>
          <td class="schedule_subtitle" colspan="2">SOTG</td>
      </tr>
  <tr>
    <td class="schedule_subtitle">Home</td>
    <td class="schedule_subtitle">Away</td>

          <td class="schedule_subtitle">Home</td>
	    <td class="schedule_subtitle">Away</td>
      </tr>

 
    <tr>
    <td>1</td>
    <td>18:30</td>

    <td>
              <a href="/leaguerunner/index.php?op=team_view&id=323">Bionic Monkeys</a>          </td>
    <td>
              <a href="/leaguerunner/index.php?op=team_view&id=253">Dingoes (Monday)</a>          </td>
    <td><a href="/leaguerunner/index.php?op=site_view&id=7">Potvin Green Space 2 (POT2)</a>&nbsp;</td>
    <td>6</td>

    <td>15</td>
               	 	<td>9</td>
       	<td>8</td>
            </tr>
	-->
<xsl:template match="schedule">
	<xsl:if test="@date-label or @content-label">
		<h2 class="schedline"> Schedule: <xsl:value-of select="@content-label"/><xsl:text> </xsl:text><xsl:value-of select="@date-label"/></h2>
	</xsl:if>
	<table class="mediumtable" cellpadding="4">
		<tr bgcolor="#cccccc"><td><b>date</b></td><td><b>home team</b></td><td><b>visiting team</b></td><td>Site</td></tr>
		<xsl:for-each select="sports-event">
			<xsl:call-template name="event-schedule">
				<xsl:with-param name="oneevent" select="."/>
			</xsl:call-template>
		</xsl:for-each>
	<!-- <xsl:apply-templates select="sports-event"/>-->
	</table>
</xsl:template>
<!-- end template for schedules -->



<!-- Template for the elements and attributes of sports-metadata -->
<xsl:template match="sports-metadata">
	<div class="sportsMetadata">
	<table cellpadding="6" width="100%">
		<tr><td bgcolor="#cccccc"><h1 class="titleheading"><xsl:value-of select="sports-title"/></h1></td></tr>
	</table>
	<br/>
	<table cellpadding="3" bgcolor="#ccff99">
	<tr>
	<td><xsl:if test="advisory"><p class="note"><i>Note:  </i><xsl:value-of select="advisory"/></p></xsl:if>

		<table width="100%" class="smalltable" border="1" cellpadding="3">
		<tr><th bgcolor="black" colspan="2"><font color="white">metadata</font></th></tr>
		<tr><td>docpath:</td><td><b><xsl:value-of select="../../@docpath"/></b></td></tr>
		<tr><td>doc-ID:</td><td><b><xsl:value-of select="@doc-id"/></b></td></tr>
		<xsl:if test="@publisher"><tr><td>publisher:</td><td><b><xsl:value-of select="@publisher"/></b></td></tr></xsl:if>
		<xsl:if test="@date-time"><tr><td>date-time:</td><td><b><xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="@date-time"/></xsl:call-template></b></td></tr></xsl:if>
		<xsl:if test="@slug"><tr><td>slug:</td><td><b><xsl:value-of select="@slug"/></b></td></tr></xsl:if>
		<xsl:if test="@language"><tr><td>language:</td><td><b><xsl:value-of select="@language"/></b></td></tr></xsl:if>
		<xsl:if test="@feature-name"><tr><td>feature-name:</td><td><b><xsl:value-of select="@feature-name"/></b></td></tr></xsl:if>
		<xsl:if test="@fixture-key"><tr><td>fixture-key:</td><td><b><xsl:value-of select="@fixture-key"/></b></td></tr></xsl:if>
		<xsl:if test="@fixture-key-source"><tr><td>source:</td><td><b><xsl:value-of select="@fixture-key-source"/></b></td></tr></xsl:if>
		<xsl:if test="@fixture-name"><tr><td>name:</td><td><b><xsl:value-of select="@fixture-name"/></b></td></tr></xsl:if>
		<xsl:if test="@stats-coverage"><tr><td>stats-coverage</td><td><xsl:value-of select="@stats-coverage"/></td></tr></xsl:if>
		<xsl:if test="@event-coverage-type"><tr><td>event-coverage-type</td><td><xsl:value-of select="@event-coverage-type"/></td></tr></xsl:if>
		<xsl:if test="@date-coverage-type"><tr><td>date-coverage-type</td><td><xsl:value-of select="@date-coverage-type"/><xsl:if test="@date-coverage-value"> (<xsl:value-of select="@date-coverage-value"/>)</xsl:if></td></tr></xsl:if>
		<xsl:if test="@competition-scoping"><tr><td>competition-scoping</td><td><xsl:value-of select="@competition-scoping"/></td></tr></xsl:if>
		<xsl:if test="@alignment-scoping"><tr><td>alignment-scoping</td><td><xsl:value-of select="@alignment-scoping"/></td></tr></xsl:if>
		<xsl:if test="@team-scoping"><tr><td>team-scoping</td><td><xsl:value-of select="@team-scoping"/></td></tr></xsl:if>
		</table>
	
		<xsl:apply-templates select="sports-content-codes"/>

	</td>
	</tr>
	</table>
	</div>
</xsl:template>
<!-- end Sports metadata section -->


<!-- Special template for sports-content-codes since they can appear at several places -->
<xsl:template match="sports-content-codes">
	<table width="100%" class="smalltable" border="1">
		<tr><th bgcolor="black" colspan="4"><font color="white">codes</font></th></tr>
		<xsl:for-each select="sports-content-code">
		<tr>
			<xsl:for-each select="@*">
			<td><xsl:value-of select="."/></td>
				<xsl:for-each select="sports-content-qualifier">
				<td>(<xsl:for-each select="@*"><xsl:value-of select="."/> / </xsl:for-each>)</td>
				</xsl:for-each>
			</xsl:for-each>
		</tr>
		</xsl:for-each>
	</table>
</xsl:template>
<!-- end sports-content-codes -->

<!-- Template to handle a tournament  -->
<xsl:template match="tournament">
	<table width="100%" class="tournament">
	<tr>
		<td width="10%">     </td>
		<td>
		<xsl:apply-templates />
		</td>
	</tr>
	</table>
</xsl:template>
<!-- end one tournament -->


<!-- Template to handle a tournament metadata and division metadata -->
<xsl:template match="tournament-metadata|tournament-division-metadata">

 <xsl:if test="@tournament-name"><h3 class="tourname"><xsl:value-of select="@tournament-name"/></h3></xsl:if>
 <xsl:if test="@division-name"><h4 class="tourdivname"><xsl:value-of select="@division-name"/></h4></xsl:if>

 <xsl:if test="@start-date-time">
  <b><xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="@start-date-time"/></xsl:call-template>
  <xsl:if test="@end-date-time">
   - <xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="@end-date-time"/></xsl:call-template>
  </xsl:if></b>
 </xsl:if>

 <small>
  <xsl:value-of select="@tournament-key"/><xsl:if test="@tournament-key-source"> (<xsl:value-of select="@tournament-key-source"/>)</xsl:if>
  <xsl:value-of select="@division-key"/><xsl:if test="@division-key-source"> (<xsl:value-of select="@division-key-source"/>)</xsl:if>
 </small>

 <table class="smalltable">
  <xsl:if test="@stats-coverage or @event-coverage-type or @date-coverage-type">
  <tr>
   <xsl:if test="@stats-coverage"><td><xsl:value-of select="@stats-coverage"/></td></xsl:if>
   <xsl:if test="@event-coverage-type"><td><xsl:value-of select="@event-coverage-type"/></td></xsl:if>
   <xsl:if test="@date-coverage-type"><td><xsl:value-of select="@date-coverage-type"/><xsl:if test="@date-coverage-value"> (<xsl:value-of select="@date-coverage-value"/>)</xsl:if></td></xsl:if>
  </tr>
  </xsl:if>
  <xsl:if test="@competition-scoping or @alignment-scoping or @team-scoping">
  <tr>
   <xsl:if test="@competition-scoping"><td><xsl:value-of select="@competition-scoping"/></td></xsl:if>
   <xsl:if test="@alignment-scoping"><td><xsl:value-of select="@alignment-scoping"/></td></xsl:if>
   <xsl:if test="@team-scoping"><td><xsl:value-of select="@team-scoping"/></td></xsl:if>
  </tr>
  </xsl:if>
  <xsl:for-each select="award">
  <tr>
   <td><xsl:value-of select="@place"/></td><td><xsl:value-of select="@currency"/></td><td><xsl:value-of select="@value"/></td>
  </tr>
  </xsl:for-each>
 </table>
 <table class="smalltable">
  <tr align="center"><th colspan="3"><xsl:value-of select="@site-name"/></th></tr>
  <xsl:if test="@site-key or @site-alignment">
  <tr>
   <xsl:if test="@site-key"><td><xsl:value-of select="@site-key"/><xsl:if test="@site-key-source"> (<xsl:value-of select="@site-key-source"/>)</xsl:if></td></xsl:if>
   <xsl:if test="@site-alignment"><td><xsl:value-of select="@site-alignment"/></td></xsl:if>
  </tr>
  </xsl:if>
  <xsl:if test="@site-city or @site-state or @site-country">
  <tr>
   <xsl:if test="@site-city"><td><xsl:value-of select="@site-city"/><xsl:if test="@site-county"> (<xsl:value-of select="@site-county"/>)</xsl:if></td></xsl:if>
   <xsl:if test="@site-state"><td><xsl:value-of select="@site-state"/></td></xsl:if>
   <xsl:if test="@site-country"><td><xsl:value-of select="@site-country"/></td></xsl:if>
  </tr>
  </xsl:if>
  <xsl:if test="@site-attendance or @site-style or @site-surface">
  <tr>
   <xsl:if test="@site-attendance"><td><xsl:value-of select="@site-attendance"/><xsl:if test="@site-capacity"> (<xsl:value-of select="@site-capacity"/>)</xsl:if></td></xsl:if>
   <xsl:if test="@site-style"><td><xsl:value-of select="@site-style"/></td></xsl:if>
   <xsl:if test="@site-surface"><td><xsl:value-of select="@site-surface"/></td></xsl:if>
  </tr>
  </xsl:if>
  <xsl:if test="@site-temperature or @site-weather-wind or @site-weather-label">
  <tr>
   <xsl:if test="@site-temperature"><td><xsl:value-of select="@site-temperature"/><xsl:if test="@site-temperature-units"> (<xsl:value-of select="@site-temperature-units"/>)</xsl:if></td></xsl:if>
   <xsl:if test="@site-weather-wind"><td><xsl:value-of select="@site-weather-wind"/></td></xsl:if>
   <xsl:if test="@site-weather-label"><td><xsl:value-of select="@site-weather-label"/></td></xsl:if>
  </tr>
  </xsl:if>

 </table>

 <table class="smalltable">
  <tr>
   <xsl:for-each select="sports-content-qualifier">
    <td>(<xsl:for-each select="@*"><xsl:value-of select="."/> / </xsl:for-each>)</td>
   </xsl:for-each>
  </tr>
 </table>

 <xsl:apply-templates select="tournament-division-metadata-golf"/>

<!-- LEGACY: Remove this call to apply-templates after Parser bug is fixed -->
   <xsl:apply-templates />

</xsl:template>
<!-- end tournament-metadata and tournament-division-metadata -->


<xsl:template match="tournament-division-metadata-golf">
 <table class="smalltable">
  <tr>
    <td><xsl:for-each select="@*"><xsl:value-of select="name()"/>: <xsl:value-of select="."/> / </xsl:for-each>)</td>
  </tr>
 </table>
</xsl:template>


<!-- Template to handle a tournament division -->
<xsl:template match="tournament-division">
	<table width="100%" class="tournamentDivision">
	<tr>
		<td width="5%">    </td>
		<td>
		<xsl:apply-templates />
		</td>
	</tr>
	</table>
</xsl:template>
<!-- end one tournament-divison -->


<!-- Template to handle a tournament round -->
<xsl:template match="tournament-round">
 <table width="100%" class="tournamentRound">
  <tr>
   <td width="5%">    </td>
   <td>
    <xsl:if test="@round-name or @start-date-time or @round-number">
     <h5 class="tourroundname">
     <xsl:if test="@round-name">
      <xsl:value-of select="@round-name"/><xsl:text>  </xsl:text>
     </xsl:if>
     <xsl:if test="@round-number"> (Round: <xsl:value-of select="@round-number"/>)    </xsl:if>
      <xsl:if test="@start-date-time">
       <xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="@start-date-time"/></xsl:call-template>
       <xsl:if test="@end-date-time">
        <xsl:text> - </xsl:text><xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="@end-date-time"/></xsl:call-template>
       </xsl:if>
      </xsl:if>
     </h5>
    </xsl:if>

    <small>
     <xsl:value-of select="@round-key"/><xsl:if test="@round-key-source"> (<xsl:value-of select="@round-key-source"/>)</xsl:if>
    </small>

    <table class="smalltable">
     <tr align="center"><th colspan="3"><xsl:value-of select="@site-name"/></th></tr>

     <tr><td>
      <xsl:if test="@site-key"><xsl:value-of select="@site-key"/><xsl:if test="@site-key-source"> (<xsl:value-of select="@site-key-source"/>)</xsl:if>/ </xsl:if>
      <xsl:if test="@site-alignment"><xsl:value-of select="@site-alignment"/>/ </xsl:if>
      <xsl:if test="@site-city"><xsl:value-of select="@site-city"/><xsl:if test="@site-county"> (<xsl:value-of select="@site-county"/>)</xsl:if>/ </xsl:if>
      <xsl:if test="@site-state"><xsl:value-of select="@site-state"/>/ </xsl:if>
      <xsl:if test="@site-country"><xsl:value-of select="@site-country"/>/ </xsl:if>
      <xsl:if test="@site-attendance"><xsl:value-of select="@site-attendance"/><xsl:if test="@site-capacity"> (<xsl:value-of select="@site-capacity"/>)</xsl:if>/ </xsl:if>
      <xsl:if test="@site-style"><xsl:value-of select="@site-style"/>/ </xsl:if>
      <xsl:if test="@site-surface"><xsl:value-of select="@site-surface"/>/ </xsl:if>
      <xsl:if test="@site-temperature"><xsl:value-of select="@site-temperature"/><xsl:if test="@site-temperature-units"> (<xsl:value-of select="@site-temperature-units"/>)</xsl:if>/ </xsl:if>
      <xsl:if test="@site-weather-wind"><xsl:value-of select="@site-weather-wind"/>/ </xsl:if>
      <xsl:if test="@site-weather-label"><xsl:value-of select="@site-weather-label"/>/ </xsl:if>
     </td></tr>
    </table>

    <xsl:apply-templates select="player"/>  <!--call this to process other children of tournament round-->
    <xsl:apply-templates select="sports-event"/>  <!--call this to process all children of tournament round-->
 <xsl:call-template name="teams"/>
 <xsl:call-template name="players"/>

   </td>
  </tr>
 </table>
</xsl:template>
<!-- end one tournament-round  -->


<!-- template for one sports-event within a schedule. We assume this is head-to-head stuff -->
<xsl:template name="event-schedule">
 <xsl:param name="oneevent"/>
 <tr>
  <td>
   <xsl:call-template name="formatted-date-time"><xsl:with-param name="date-value" select="$oneevent/event-metadata/@start-date-time"/></xsl:call-template>
  </td>
  <td>
   <b>
    <xsl:call-template name="choose-name">
     <xsl:with-param name="team-meta" select="$oneevent/team[1]/team-metadata"/>
     <xsl:with-param name="shownickname" select="'no'"/>
    </xsl:call-template>
   </b>
   <xsl:if test="$oneevent/event-metadata/@event-status = 'post-event'">
 	(<xsl:value-of select="$oneevent/team[1]/team-stats/@score"/>)
   </xsl:if>
   <!-- below is temporary, for bug in feed -->
   <xsl:if test="$oneevent/event-metadata/@event-status = 'final'">
 	(<xsl:value-of select="$oneevent/team[1]/team-stats/@score"/>)
   </xsl:if>

  </td>
  <td><b>
    <xsl:call-template name="choose-name">
     <xsl:with-param name="team-meta" select="$oneevent/team[2]/team-metadata"/>
     <xsl:with-param name="shownickname" select="'no'"/>
    </xsl:call-template></b>
   <xsl:if test="$oneevent/event-metadata/@event-status = 'post-event'">
 	(<xsl:value-of select="$oneevent/team[2]/team-stats/@score"/>)
   </xsl:if>
  </td>
  <td>
   <xsl:value-of select="$oneevent/event-metadata/@site-name"/>
  </td>
 </tr>
</xsl:template>
<!-- end named template for one sports-event within a schedule -->


<!-- The template for the actual sports-events -->
<xsl:template match="sports-event">
 <xsl:if test="team or player"> <!-- if there are no players or no teams it is an empty sports-event and we skip it. -->
 <!--
 <h1 class="sportseventline">Sports Event</h1>
 -->
 <table width="100%" class="sportsEvent">          <!-- create a table -->
  <tr>                       <!-- One row for the metadata -->
   <td width="5%">    </td>
    <!-- apply templates to event-metadata -->
   <!--
   <td>
    <xsl:apply-templates select="event-metadata"/>
   </td>
   -->
  </tr>
  <tr>  <!-- Another row for teams or players -->
   <td width="5%">    </td>
   <td>
    <xsl:choose>
     <xsl:when test="team">  <!-- We have team(s) in the event -->
      <xsl:choose>
       <xsl:when test="count(./team) = 2">  <!-- if there are two teams we treat it as a duel. IMPROVE!!  -->
        <xsl:call-template name="teamduel"/>
       </xsl:when>
       <xsl:otherwise> <!-- Otherwise we called the named template for listing teams -->
        <xsl:call-template name="teams"/>
       </xsl:otherwise>
      </xsl:choose>
     </xsl:when>
     <xsl:otherwise>  <!-- Otherwise there are player(s) in the event -->
      <xsl:choose>
       <xsl:when test="count(./player) = 2"> <!-- It there are two players we treat it as a duel. IMPROVE!! -->
        <xsl:call-template name="playerduel"/>
       </xsl:when>
       <xsl:otherwise>  <!-- Otherwise we call the named template to list the players -->
        <xsl:call-template name="players"/>
       </xsl:otherwise>
      </xsl:choose>
     </xsl:otherwise>
    </xsl:choose>
   </td>
  </tr>
  <xsl:apply-templates select="officials"/>
  <xsl:apply-templates select="highlight"/>
  <xsl:apply-templates select="event-actions"/>
 </table>
 </xsl:if>
</xsl:template>
<!-- end template for sports-event -->

<!-- template to handle event-actions -->
<xsl:template match="event-actions">
	<xsl:apply-templates select="event-actions-ice-hockey"/>
</xsl:template>
<!-- end template for various event actions -->

<!-- Template to output all officials-->
<xsl:template match="officials">
 <tr><th bgcolor="black" colspan="2"><font color="white">officials</font></th></tr>
 <xsl:for-each select="official">
  <tr>
  <td> </td><td class="officialline"><xsl:value-of select="official-metadata/@position"/><b><xsl:text> </xsl:text><xsl:choose>
     <xsl:when test="official-metadata/name/@full">
      <xsl:value-of select="official-metadata/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="official-metadata/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="official-metadata/name/@last"/>
     </xsl:otherwise>
    </xsl:choose></b><xsl:text> </xsl:text>
    <xsl:value-of select="official-metadata/home-location/@city"/><xsl:text> </xsl:text>
    <xsl:value-of select="official-metadata/home-location/@county"/><xsl:text> </xsl:text>
    <xsl:value-of select="official-metadata/home-location/@state"/><xsl:text> </xsl:text>
    <xsl:value-of select="official-metadata/home-location/@country"/><xsl:text> </xsl:text>
    </td>
  </tr>
 </xsl:for-each>
</xsl:template>
<!-- end template for officials -->


<!-- The template for each event-metadata -->

<xsl:template match="event-metadata">
 <xsl:for-each select="event-sponsor/@name">
  <marquee bgcolor="#ff80ff" width="256" height="22" align="middle" scrolldelay="95" border="0"><b>EVENT SPONSOR: <xsl:value-of select="."/></b></marquee><br/>
 </xsl:for-each>

 <table class="smalltable" cellpadding="3" border="1" bgcolor="#cccccc">

    <tr><th bgcolor="black" colspan="2"><font color="white">event metadata</font></th></tr>

	<xsl:if test="@event-name">
		<tr><td>name</td><td class="tourroundname"><xsl:value-of select="@event-name"/>
		<xsl:if test="@event-number"> (<xsl:value-of select="@event-number"/>)</xsl:if>
		</td></tr>
	</xsl:if>


 <xsl:if test="@heat-number">
  <tr><td class="heatno">heat</td><td class="heatno"><b><xsl:value-of select="@heat-number"/></b></td></tr>
 </xsl:if>

 <xsl:if test="@event-key">
 <tr><td>key</td><td><xsl:value-of select="@event-key"/>
 <xsl:if test="@event-key-source"> (<xsl:value-of select="@event-key-source"/>)</xsl:if>
 </td></tr>
 </xsl:if>

 <xsl:if test="@start-date-time">
  <tr><td>date</td><td class="dateline">
  <xsl:value-of select="@start-weekday"/>
  <xsl:text> </xsl:text>
  <b><xsl:call-template name="formatted-date-time">
	<xsl:with-param name="date-value" select="@start-date-time"/>
  </xsl:call-template>
  <xsl:if test="@end-date-time">
   - <xsl:value-of select="@end-weekday"/>
   <xsl:text> </xsl:text>
   <xsl:call-template name="formatted-date-time">
 	<xsl:with-param name="date-value" select="@end-date-time"/>
   </xsl:call-template>
  </xsl:if></b></td></tr>
  </xsl:if>

   <xsl:if test="@stats-coverage"><tr><td>coverage</td><td><xsl:value-of select="@stats-coverage"/></td></tr></xsl:if>
   <xsl:if test="@event-coverage-type"><tr><td>coverage type</td><td><xsl:value-of select="@event-coverage-type"/></td></tr></xsl:if>
   <xsl:if test="@date-coverage-type"><tr><td>date coverage</td><td><xsl:value-of select="@date-coverage-type"/><xsl:if test="@date-coverage-value"> (<xsl:value-of select="@date-coverage-value"/>)</xsl:if></td></tr></xsl:if>
   <xsl:if test="@competition-scoping"><tr><td>competition scoping</td><td><xsl:value-of select="@competition-scoping"/></td></tr></xsl:if>
   <xsl:if test="@alignment-scoping"><tr><td>alignment scoping</td><td><xsl:value-of select="@alignment-scoping"/></td></tr></xsl:if>
   <xsl:if test="@team-scoping"><tr><td>team scoping</td><td><xsl:value-of select="@team-scoping"/></td></tr></xsl:if>
   <xsl:if test="@event-status"><tr><td>event status</td><td><xsl:value-of select="@event-status"/></td></tr></xsl:if>
   <xsl:if test="@postponent-status"><tr><td>postponement status</td><td><xsl:value-of select="@postponent-status"/></td></tr></xsl:if>
   <xsl:if test="@postponent-note"><tr><td>postponement note</td><td><xsl:value-of select="@postponent-note"/></td></tr></xsl:if>

    <tr><th bgcolor="black" colspan="2"><font color="white">site metadata</font></th></tr>

   <xsl:if test="@site-name"><tr align="center"><th>site name</th><th><xsl:value-of select="@site-name"/></th></tr></xsl:if>

   <xsl:if test="@site-key"><tr><td>key</td><td><xsl:value-of select="@site-key"/><xsl:if test="@site-key-source"> (<xsl:value-of select="@site-key-source"/>)</xsl:if></td></tr></xsl:if>
   <xsl:if test="@site-alignment"><tr><td>alignment</td><td><xsl:value-of select="@site-alignment"/></td></tr></xsl:if>
   <xsl:if test="@site-city"><tr><td>city</td><td><xsl:value-of select="@site-city"/><xsl:if test="@site-county"> (<xsl:value-of select="@site-county"/>)</xsl:if></td></tr></xsl:if>
   <xsl:if test="@site-state"><tr><td>state</td><td><xsl:value-of select="@site-state"/></td></tr></xsl:if>
   <xsl:if test="@site-country"><tr><td>country</td><td><xsl:value-of select="@site-country"/></td></tr></xsl:if>
   <xsl:if test="@site-attendance"><tr><td>attendance</td><td><xsl:value-of select="@site-attendance"/><xsl:if test="@site-capacity"> (<xsl:value-of select="@site-capacity"/>)</xsl:if></td></tr></xsl:if>
   <xsl:if test="@site-style"><tr><td>style</td><td><xsl:value-of select="@site-style"/></td></tr></xsl:if>
   <xsl:if test="@site-surface"><tr><td>surface</td><td><xsl:value-of select="@site-surface"/></td></tr></xsl:if>
   <xsl:if test="@site-temperature"><tr><td>temperature</td><td><xsl:value-of select="@site-temperature"/><xsl:if test="@site-temperature-units"> (<xsl:value-of select="@site-temperature-units"/>)</xsl:if></td></tr></xsl:if>
   <xsl:if test="@site-weather-wind"><tr><td>weather wind</td><td><xsl:value-of select="@site-weather-wind"/></td></tr></xsl:if>
   <xsl:if test="@site-weather-label"><tr><td>weather label</td><td><xsl:value-of select="@site-weather-label"/></td></tr></xsl:if>

 </table>

 <xsl:apply-templates />  <!-- Apply templates to sub elements of event-metadata -->

</xsl:template>
<!-- end event metadata  -->

<!--  Template to output a formatted string -->
<xsl:template name="formatted-date-time">
 <xsl:param name="date-value"/>
 <xsl:value-of select="concat(substring-before($date-value,'T'),' ',substring-after($date-value,'T'))"/>
</xsl:template>

<!-- Template to output either fullname or name parts -->
<xsl:template name="choose-name">
 <xsl:param name="team-meta"/>
 <xsl:param name="shownickname"/>
 <xsl:param name="showuniform"/>
 <xsl:choose>
  <xsl:when test="$team-meta/@home-page-url">
   <xsl:element name="a">
    <xsl:attribute name="href">http://<xsl:value-of select="$team-meta/@home-page-url"/></xsl:attribute>
    <xsl:choose>
     <xsl:when test="$team-meta/name/@full">
      <xsl:value-of select="$team-meta/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="$team-meta/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="$team-meta/name/@last"/>
     </xsl:otherwise>
    </xsl:choose>
       </xsl:element>
  </xsl:when>
  <xsl:otherwise>
    <xsl:choose>
     <xsl:when test="$team-meta/name/@full">
      <xsl:value-of select="$team-meta/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="$team-meta/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="$team-meta/name/@last"/>
     </xsl:otherwise>
    </xsl:choose>
  </xsl:otherwise>
 </xsl:choose>
 <xsl:if test="$shownickname = 'yes'">
  <xsl:if test="$team-meta/name/@nickname">
   <xsl:text> &quot;</xsl:text>
   <xsl:value-of select="$team-meta/name/@nickname"/>
   <xsl:text> &quot;</xsl:text>
  </xsl:if>
 </xsl:if>
 <xsl:if test="$showuniform = 'yes'">
  <xsl:if test="$team-meta/@uniform-number">
   <xsl:text> (</xsl:text>
   <xsl:value-of select="$team-meta/@uniform-number"/>
   <xsl:text>) </xsl:text>
  </xsl:if>
 </xsl:if>
</xsl:template>
<!-- End template to choose name to output -->

<!-- Named template to process a two teams in a duel style event -->
<xsl:template name="teamduel">

 <xsl:variable name="tableclass"> <!-- Set up a variable to hold the classname for the stylesheet depending on this beeing a sub sports-event or not -->
  <xsl:choose>
   <xsl:when test="../../sports-event">
    <xsl:text>smalltable</xsl:text>
   </xsl:when>
   <xsl:otherwise>
    <xsl:text>mediumtable</xsl:text>
   </xsl:otherwise>
  </xsl:choose>
 </xsl:variable>

 <xsl:element name="table">  <!-- Start the table and give it the classname we discovered above -->
  <xsl:attribute name="width">100%</xsl:attribute>
  <xsl:attribute name="valign">top</xsl:attribute>
  <xsl:attribute name="class"><xsl:value-of select="$tableclass"/></xsl:attribute>

  <tr>
   <td>  <!-- If there is a rank start with that and possibly a result-effect. -->
    <xsl:if test="team[1]/team-stats/rank">
     <xsl:value-of select="team[1]/team-stats/rank/@value"/>
      <xsl:if test="team[1]/team-stats/@result-effect">
       (<xsl:value-of select="team[1]/team-stats/@result-effect"/>)
      </xsl:if>
     </xsl:if>
    <b>

    <xsl:call-template name="choose-name">
     <xsl:with-param name="team-meta" select="team[1]/team-metadata"/>
     <xsl:with-param name="shownickname" select="'yes'"/>
    </xsl:call-template>

 -
    </b>
    <xsl:if test="team[2]/team-stats/rank">
     <xsl:value-of select="team[2]/team-stats/rank/@value"/>
     <xsl:if test="team[2]/team-stats/@result-effect">
      (<xsl:value-of select="team[2]/team-stats/@result-effect"/>)
     </xsl:if>
    </xsl:if>
    <b>
    <xsl:call-template name="choose-name">
     <xsl:with-param name="team-meta" select="team[2]/team-metadata"/>
     <xsl:with-param name="shownickname" select="'yes'"/>
    </xsl:call-template>
    </b>
    <xsl:text>  </xsl:text>
    <xsl:if test="team[1]/team-stats/@score">
     <table class="smalltable" valign="top">  <!--start a table-->
      <tr class="blueline"><td>Goals</td><td>Total</td><td>1</td><td>2</td><td>3</td><td>OT</td><td>Shootout</td></tr>
      <xsl:for-each select="team">
       <tr>
        <td><b>
         <xsl:call-template name="choose-name">
          <xsl:with-param name="team-meta" select="team-metadata"/>
          <xsl:with-param name="shownickname" select="'no'"/>
         </xsl:call-template>
         </b>
        </td>
        <td><xsl:value-of select="team-stats/@score"/></td>
        <xsl:for-each select="team-stats/sub-score">
         <td><xsl:value-of select="@score"/></td>
        </xsl:for-each>
       </tr>
      </xsl:for-each>
     </table>

    </xsl:if>

    <xsl:if test="team[1]/team-stats/@score-attempts">
     <table class="smalltable" valign="top">  <!--start a table-->
      <tr class="blueline"><td>Shots on goal</td><td>Total</td><td>1</td><td>2</td><td>3</td><td>OT</td><td>Shootout</td></tr>
      <xsl:for-each select="team">
       <tr>
        <td><b>
         <xsl:call-template name="choose-name">
          <xsl:with-param name="team-meta" select="team-metadata"/>
          <xsl:with-param name="shownickname" select="'no'"/>
         </xsl:call-template>
         </b>
        </td>
        <td><xsl:value-of select="team-stats/@score-attempts"/></td>
        <xsl:for-each select="team-stats/sub-score-attempts">
         <td><xsl:value-of select="@score-attempts"/></td>
        </xsl:for-each>
       </tr>
      </xsl:for-each>
     </table>
    </xsl:if>
    </td></tr>

    <xsl:if test="team[1]/team-stats/penalty-stats or team[2]/team-stats/penalty-stats">
     <tr><td>
     <table class="smalltable" valign="top">  <!--start a table-->
     <tr class="blueline"><td>Penalties:</td></tr>
     <xsl:for-each select="team">
      <tr>
        <td><b>
         <xsl:call-template name="choose-name">
          <xsl:with-param name="team-meta" select="team-metadata"/>
          <xsl:with-param name="shownickname" select="'no'"/>
         </xsl:call-template>:
          </b>

       <xsl:for-each select="team-stats/penalty-stats">

         <xsl:value-of select="@count"/>x<xsl:value-of select="@type"/><xsl:text> </xsl:text>

       </xsl:for-each>

       </td></tr>
     </xsl:for-each>
      </table></td></tr>
    </xsl:if>

    <xsl:if test="team[1]/player">
    <tr><td class="playerlist">
    <xsl:for-each select="team">
      <b>
       <xsl:call-template name="choose-name">
        <xsl:with-param name="team-meta" select="team-metadata"/>
        <xsl:with-param name="shownickname" select="'no'"/>
       </xsl:call-template>:</b>
     <xsl:for-each select="player">

       <xsl:call-template name="choose-name">
        <xsl:with-param name="team-meta" select="player-metadata"/>
        <xsl:with-param name="shownickname" select="'no'"/>
        <xsl:with-param name="showuniform" select="'yes'"/>
       </xsl:call-template>
      <xsl:if test="not(position()=last())"> <br /> </xsl:if>
     </xsl:for-each>
     <xsl:text>. </xsl:text> <br/>
    </xsl:for-each>
   </td>
  </tr>
    </xsl:if>
  <tr><td>
    <xsl:apply-templates select="sports-event"/>
   </td>
  </tr>
  </xsl:element>
</xsl:template>
<!-- end template for teams in duel-type -->


<!-- Named template to process a two players in a duel style event -->
<xsl:template name="playerduel">

 <table xwidth="100%" class="smalltable" valign="top" cellpadding="4">  <!--start a table-->

<xsl:choose>
<xsl:when test=".//player-stats-tennis">
<tr class="blueline"><td>  </td><td>  </td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td></tr>
<xsl:for-each select="player">
<tr>
	<td>
	<xsl:value-of select="player-stats/rank"/>
	
	<xsl:if test="player-stats/@result-effect">
		(<xsl:value-of select="player-stats/@result-effect"/>)
	</xsl:if>  

	<xsl:if test="player-metadata/home-location/@country">
		<xsl:value-of select="player-metadata/home-location/@country"/>
	</xsl:if>  
	</td>



      <td><b><xsl:choose>     <xsl:when test="player-metadata/name/@full">
      <xsl:value-of select="player-metadata/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="player-metadata/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="player-metadata/name/@last"/>
     </xsl:otherwise>
    </xsl:choose></b>
    <xsl:if test="player-stats/rank/@value">
		(<xsl:value-of select="player-stats/rank/@value"/>)
	</xsl:if>  
</td>
<xsl:for-each select="player-stats/player-stats-tennis/stats-tennis-set">
<td><xsl:value-of select="@score"/>
	<xsl:if test="@score-tiebreaker">
		<sup><xsl:value-of select="@score-tiebreaker"/></sup>
	</xsl:if> 
</td>
</xsl:for-each>
</tr>
</xsl:for-each>
</xsl:when>
<xsl:otherwise>

  <tr>
   <td>
    <xsl:if test="player[1]/player-stats/rank">
     <xsl:value-of select="player[1]/player-stats/rank/@value"/>
      <xsl:if test="player[1]/player-stats/@result-effect">
       (<xsl:value-of select="player[1]/player-stats/@result-effect"/>)
      </xsl:if>
     </xsl:if>
    <b>
    <xsl:choose>
     <xsl:when test="player[1]/player-metadata/name/@full">
      <xsl:value-of select="player[1]/player-metadata/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="player[1]/player-metadata/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="player[1]/player-metadata/name/@last"/>
     </xsl:otherwise>
    </xsl:choose>
    <xsl:if test="player[1]/player-metadata/name/@nickname">
     <xsl:text> &quot;</xsl:text>
     <xsl:value-of select="player[1]/player-metadata/name/@nickname"/>
     <xsl:text> &quot;</xsl:text>
    </xsl:if>
 -
    </b>
        <xsl:if test="player[2]/player-stats/rank/@value">
     <xsl:value-of select="player[2]/player-stats/rank/@value"/>
      <xsl:if test="player[2]/player-stats/@result-effect">
       (<xsl:value-of select="player[2]/player-stats/@result-effect"/>)
      </xsl:if>
     </xsl:if>
     <b>
    <xsl:choose>
     <xsl:when test="player[2]/player-metadata/name/@full">
      <xsl:value-of select="player[2]/player-metadata/name/@full"/>
     </xsl:when>
     <xsl:otherwise>
      <xsl:value-of select="player[2]/player-metadata/name/@first"/><xsl:text> </xsl:text>
      <xsl:value-of select="player[2]/player-metadata/name/@last"/>
     </xsl:otherwise>
    </xsl:choose>
    <xsl:if test="player[2]/player-metadata/name/@nickname">
     <xsl:text> &quot;</xsl:text>
     <xsl:value-of select="player[2]/player-metadata/name/@nickname"/>
     <xsl:text> &quot;</xsl:text>
    </xsl:if></b>
    <xsl:text>  </xsl:text>
    <xsl:if test="player[1]/player-stats/@score">
    <b>
     <xsl:value-of select="player[1]/player-stats/@score"/>-
     <xsl:value-of select="player[2]/player-stats/@score"/>
    </b>
    </xsl:if>
    <xsl:if test="player[1]/player-stats/sub-score">
     <xsl:text>, </xsl:text>
     (<xsl:for-each select="player[1]/player-stats/sub-score">
      <xsl:value-of select="@score"/>-
      <xsl:variable name="periodvalue" select="./@period-value"/>
      <xsl:value-of select="../../../player[2]/player-stats/sub-score[./@period-value = $periodvalue]/@score"/>
      <xsl:if test="not(position() = last())"><xsl:text>, </xsl:text></xsl:if>
     </xsl:for-each>)
    </xsl:if>
    <xsl:if test="player[1]/player-stats/player-stats-tennis">

     <xsl:text>, </xsl:text>
     (<xsl:for-each select="player[1]/player-stats/player-stats-tennis/stats-tennis-set">
      <xsl:value-of select="@score"/>-
      <xsl:variable name="periodvalue" select="./@set-number"/>
      <xsl:value-of select="../../../../player[2]/player-stats/player-stats-tennis/stats-tennis-set[./@set-number = $periodvalue]/@score"/>
      <xsl:if test="not(position() = last())"><xsl:text>, </xsl:text></xsl:if>
     </xsl:for-each>)

    </xsl:if>
    <br/>
    <xsl:apply-templates select="./sports-event"/>

   </td>
  </tr>
</xsl:otherwise>
</xsl:choose>

 </table>

</xsl:template>


<!-- Named template to process a list of teams in a competition style event -->
<xsl:template name="teams">

 <table width="100%" class="smalltable" valign="top">  <!--start a table-->
  <xsl:for-each select="team">             <!--process all teams-->
   <tr valign="top">                                    <!--one row for each team-->
    <td>
     <xsl:value-of select="team-stats/rank/@value"/> <!-- put the rank in the first field-->
     <xsl:if test="team-stats/award/@name">
      <xsl:choose>
       <xsl:when test="team-stats/award/@name = 'Guld' or team-stats/award/@name = 'Gold'">
        <img align="absmiddle"  width="30" height="24" border="0" alt="Gold medal" src="images/medal-gold.gif" />
       </xsl:when>
       <xsl:when test="team-stats/award/@name = 'Silver'">
        <img align="absmiddle"  width="30" height="24" border="0" alt="Silver medal" src="images/medal-silver.gif" />
       </xsl:when>
       <xsl:when test="team-stats/award/@name = 'Bronze' or team-stats/award/@name = 'Brons'">
        <img align="absmiddle" width="30" height="24" border="0" alt="Bronze medal" src="images/medal-bronze.gif" />
       </xsl:when>
       <xsl:otherwise>
      (<xsl:value-of select="team-stats/@result-effect"/>)
      </xsl:otherwise>
      </xsl:choose>
     </xsl:if>
     <xsl:if test="team-stats/@result-effect">
      (<xsl:value-of select="team-stats/@result-effect"/>)
     </xsl:if>
    </td>
    <td>
     <xsl:for-each select="team-metadata/name"> <!--Build the name in the second field-->
      <xsl:if test="@language">
       <xsl:value-of select="@language"/>:
      </xsl:if>
      <xsl:choose>
       <xsl:when test="@full">
        <xsl:value-of select="@full"/>
         <xsl:if test="@first">
          <small>
           (<xsl:value-of select="@first"/><xsl:text> </xsl:text><xsl:value-of select="@last"/>)
          </small>
         </xsl:if>
       </xsl:when>
       <xsl:otherwise>
        <xsl:value-of select="@first"/><xsl:text> </xsl:text><xsl:value-of select="@last"/>
       </xsl:otherwise>
      </xsl:choose>
      <xsl:if test="@nickname">
       <xsl:text> &quot;</xsl:text><xsl:value-of select="@nickname"/><xsl:text> &quot;</xsl:text>
      </xsl:if><br/>
     </xsl:for-each>
     <xsl:if test="team-stats/@result-effect or team-stats/award/@name">
      (<xsl:for-each select="player">
       <xsl:choose>
        <xsl:when test="player-metadata/name/@full">
         <xsl:value-of select="player-metadata/name/@full"/>
        </xsl:when>
        <xsl:otherwise>
         <xsl:value-of select="player-metadata/name/@first"/><xsl:text> </xsl:text><xsl:value-of select="player-metadata/name/@last"/>
        </xsl:otherwise>
       </xsl:choose>
       <xsl:if test="not(position()=last())">, </xsl:if>
      </xsl:for-each>)
     </xsl:if>
    </td>
    <td>  <!--Put the home-information in the third field-->
     <xsl:if test="home-location/@city"><xsl:value-of select="home-location/@city"/>, </xsl:if>
     <xsl:if test="home-location/@county"><xsl:value-of select="home-location/@county"/>, </xsl:if>
     <xsl:if test="home-location/@state"><xsl:value-of select="home-location/@state"/>, </xsl:if>
     <xsl:if test="home-location/@country"><xsl:value-of select="home-location/@country"/>, </xsl:if>
    </td>
    <td>
     <xsl:value-of select="team-stats/@score"/>
     <xsl:if test="team-stats/event-record">
      <b><i color="#FF00FF">
      <xsl:for-each select="team-stats/event-record">
       <xsl:text> </xsl:text>
       <xsl:value-of select="./@type"/>-record
       <xsl:if test="not(position() = last())">, </xsl:if>
      </xsl:for-each>
      </i></b>
     </xsl:if>
    </td>
   </tr>
  </xsl:for-each>
 </table>
</xsl:template>
<!-- end template for team lists in competitions -->

</xsl:stylesheet>

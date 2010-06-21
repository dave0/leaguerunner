{$street}<br />
{$city}, {$province}<br />
{if $postalcode}{$postalcode}<br />{/if}
{if $country}{$country}<br />{/if}
{strip}
<br />[&nbsp;
	<a href="http://maps.google.com?q={$street|escape:'url'},+{$city|escape:'url'},+{$province|escape:'url'}&hl=en">maps.google.com</a>
&nbsp;|&nbsp;
	<a href="http://www.mapquest.com/maps/map.adp?country=ca&zoom=7&city={$city|escape:'url'}&state={$province|escape:'url'|truncate:2:""}&address={$street|escape:'url'}">MapQuest</a>
&nbsp;]
{/strip}

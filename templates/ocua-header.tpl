{* Smarty *}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>{ $title | escape | default:$app_name }</title>
    {include file="components/css.tpl"}
    <script type="text/javascript" src="{$base_url}/js/jquery-1.4.4.min.js"></script>
    <script type="text/javascript" src="{$base_url}/js/jquery.dataTables.1.6.min.js"></script>
    <script type="text/javascript" src="{$base_url}/js/jquery-ui-1.8.11.custom.min.js"></script>
    <script type="text/javascript" src="{$base_url}/js/jquery.bsmselect.js"></script>
    <script type="text/javascript" src="{$base_url}/js/jquery.bsmselect.sortable.js"></script>
    <script type="text/javascript" src="{$base_url}/js/jquery.bsmselect.compatibility.js"></script>
    <link rel="shortcut icon" href="/favicon.ico" />
  </head>
  <body>
<table id="primary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
<tr valign="bottom">
    <td rowspan="2" width="401" valign="bottom"><a href="/"><img src="/themes/ocua_2004/ocua-logo-top-half.png" width="399" height="37" border="0" alt="Ottawa-Carleton Ultimate Association" /></a></td>
	<td></td>
</tr>
<tr>
   <td class="primary links" align="right" valign="bottom">
   {if $session_valid}
	You are logged in as <b>{$session_fullname}</b> | <a href="{lr_url path="logout"}">Log Out</a>
   {else}
	<a href="{lr_url path="login"}">Log In</a>
   {/if}
   </td>
</tr>
</table>
<table id="secondary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr height="22">
		<td align="left" height="22" width="130" valign="top"><a href="/"><img src="/themes/ocua_2004/ocua-logo-bottom-half.png" width="130" height="22" border="0" alt="" /></a></td>
	</tr>
</table>
<!-- end header -->
<table width='100%'><tr>
{if $hide_sidebar}
<td></td>
{else}
<td id='sidebar-left' width='160'><div class='menu'>{ $menu }</div></td>
{/if}
<td valign='top'><div id='main'>

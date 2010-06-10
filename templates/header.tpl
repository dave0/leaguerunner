{* Smarty *}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>{ $title | escape | default:$app_name }</title>
    <base href="{$base_url}/" />
    <link rel="stylesheet" type="text/css" href="/themes/ocua_2004/style.css" />
    <link rel="stylesheet" type="text/css" href="{$base_url}/style.css" />
    <link rel="shortcut icon" href="/favicon.ico" />
  </head>
  <body>
<table id="primary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
<tr valign="bottom">
    <td rowspan="2" width="401" valign="bottom"><a href="/"><img src="/themes/ocua_2004/ocua-logo-top-half.png" width="399" height="37" border="0" alt="Ottawa-Carleton Ultimate Association"></a></td>
	<td></td>
</tr>
<tr>
   <td class="primary links" align="right" valign="bottom">
   {if $session_valid}
	You are logged in as <b>{$session_fullname}</b> | <a href="{$base_url}/logout">Log Out</a>
   {else}
   	{* TODO: create a [link 'login' "Log In"] plugin for smarty? *}
	<a href="{$base_url}/login">Log In</a>
   {/if}
   </td>
</tr>
</table>
<table id="secondary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr height="22">
		<td align="left" height="22" width="130" valign="top"><a href="/"><img src="/themes/ocua_2004/ocua-logo-bottom-half.png" width="130" height="22" border="0" alt=""></a></td>
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

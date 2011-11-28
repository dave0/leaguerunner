{* Smarty *}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>{ $title | escape | default:$app_name }</title>
    {include file="components/css.tpl"}
    {include file="components/javascript.tpl"}
    <link rel="shortcut icon" href="/suc_logo.ico" />
</head>
<body>
	<div id="page-wrapper">
		<div id="header-wrapper">
			<div id="header">
				<div id="branding-wrapper">
					<div class="branding">
						<div class="logo">
							<a href="/" title="Home"><img src="/suc_logo.jpg" alt="Home" /></a>
						</div> <!-- end logo -->
						<div class="name-slogan-wrapper">
							<h1 class="site-name"><a href="/" title="{$site_name}">{$site_name}</a></h1>
							<span class="site-slogan">{$site_slogan}</span>
						</div> <!-- end site-name + site-slogan wrapper -->
					</div>
				</div> <!-- end branding wrapper -->
				<div id="authorize">
					<ul>
					{if $session_valid}
						<li class="first">Logged in as <a href="{lr_url path=person/view/$session_userid}">{$session_fullname}</a></li>
						<li class="last"><a href="{lr_url path=logout}">Logout</a></li>
					{else}
						<li class="first"><a href="{lr_url path=login}">Log In</a></li>
						<li class="last"><a href="{lr_url path=person/create}">Register</a></li>
					{/if}
					</ul>
				</div> <!-- end authorize -->
			</div> <!-- end header -->
		</div> <!-- end header wrapper -->
		
		<div id="container-wrapper">
		<div id="container-outer">			
				<div id="container-inner">
					<div id="content-wrapper" class="clearfix">
						<div id="main-content">																
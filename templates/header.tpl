{* Smarty *}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>{$title|default:$app_name}</title>
    {include file="components/css.tpl"}
    {include file="components/javascript.tpl"}
    <link rel="shortcut icon" href="/suc_logo.ico" />
</head>
<body class="one-sidebar">
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
						<li class="first">Logged in as <a href="{lr_url path='person/view/`$session_userid`'}">{$session_fullname}</a></li>
						<li class="last"><a href="{lr_url path=logout}">Logout</a></li>
					{else}
						<li class="first"><a href="{lr_url path=login}">Login</a></li>
						<li class="last"><a href="{lr_url path='person/create'}">Register</a></li>
					{/if}
					</ul>
				</div> <!-- end authorize -->
			</div> <!-- end header -->
		</div> <!-- end header wrapper -->

		<div id="container-wrapper">
		<div id="container-outer">
			<div class="menu-wrapper">
			<div class="menu-outer">
				<div class="menu-inner">
				<div class="menu-left"></div>
				<div id="superfish">
					<div class="region region-superfish-menu">
						<div id="block-menu-menu-superfish" class="block block-menu">
							<div class="content">
							<ul class="menu">
								<li class="first leaf"><a href="/" title="Sudbury Ultimate Club">Home</a></li>
								<li class="leaf"><a href="/photos">Photos</a></li>
								<li class="expanded"><a href="/about" title="Information about Sudbury Ultimate Club">About</a>
								<ul class="menu">
									<li class="first leaf"><a href="/content/rules" title="">Rules</a></li>
									<li class="leaf"><a href="/content/bylaws" title="">Bylaws</a></li>
									<li class="last leaf"><a href="/content/board" title="People currently looking after Sudbury Ultimate Club">The Board</a></li>
								</ul></li>
								<li class="leaf"><a href="/forum" title="Forums for Sudbury Ultimate Club">Forums</a></li>
								<li class="leaf"><a href="/content/snowplate" title="Details about the Snowplate Tournament hosted by the Sudbury Ultimate Club">Snowplate</a></li>
								<li class="leaf"><a href="/contact" title="">Contact</a></li>
								<li class="last leaf"><a href="/leaguerunner" title="Leaguerunner" class="active">Leaguerunner</a></li>
							</ul>
						</div>
					</div>
				</div>
			</div> <!-- end menu / superfish -->
			<div class="menu-right"></div>
		</div>
	</div>
</div>
				<div id="container-inner">
					<div id="content-wrapper" class="clearfix">
						<div id="main-content">
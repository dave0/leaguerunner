<?php
// $Id$

/** 
 * Add a child to a parent menu. 
 * parentName, thisKey, and title are required
 * in $args, we can have:
 *   link (the URL we link to.  If absent, this is a pure container, 
 *         and this item will always be expanded if it has children)
 *   weight (lower = floats, higher = sinks)
 *   expanded (true = always open)
 * Menu should be started with something like:
 *   menu_add_child( null, '_root', 'Root of Menu', array( 'expanded' =>
 *   true));
 */
function menu_add_child( $parentName, $name, $title, $args = array() )
{
	global $_menu;

	if(!is_array($_menu) ) {
		$_menu = array();
	}

	if(array_key_exists($name, $_menu)) {
		return;
	}

	/* Concat parent/child names together to prevent collisions */
	$item = array(
		'title'    => $title,
		'parent'   => $parentName,
		'weight'   => 0,  // Default weight
	); 

	$_menu[$name] = array_merge($item, $args);

	/* and add to children of parent if parent exists */
	if($parentName) { 
		$_menu[$parentName]['children'][] = $name;
	}

}

/**
 * Returns an array with the menu keys for all parent menu items
 * of the given path.
 */
function _menu_get_parents( $path ) {
	global $_menu;
	static $parents;
	
	if( empty($parents) ) {
		$parents = array();

		$itemKey = null;
		while(list($key, $item) = each($_menu)) {
			if( array_key_exists( 'link', $item ) && $item['link'] == $path) {
				$itemKey = $key;
				break;
			}
		}
		reset($_menu);

		while( $itemKey ) {
			$itemKey = $_menu[$itemKey]['parent'];
			$parents[] = $itemKey;
		}
	}

	return $parents;
}

/**
 * Returns active menu item corresponding to current page
 */
function _menu_get_active( $path ) {
	global $_menu;
	static $itemKey;
	if( empty($itemKey) ) {
		$itemKey = null;
		while(list($key, $item) = each($_menu)) {
			if( array_key_exists( 'link', $item ) && $item['link'] == $path) {
				$itemKey = $key;
				break;
			}
		}
		reset($_menu);
	}
	return $itemKey;
}

/**
 * Returns a rendered menu tree.
 */
function menu_render($parentKey = '_root') {
	global $_menu;

	$parents = _menu_get_parents($_GET["q"]);
	foreach($parents as $key) {
		$_menu[$key]['expanded'] = true;
	}
	
	$active  = _menu_get_active($_GET["q"]);
	$_menu[$active]['expanded'] = true;

	$output = '';
	if ($_menu[$parentKey]['children']) {
		usort($_menu[$parentKey]['children'], "_menu_internal_sort");
		foreach ($_menu[$parentKey]['children'] as $itemKey) {
			/* Always expand in this case because otherwise the children
			 * become unreachable.
			 */
			$mustExpand = $_menu[$itemKey]['expanded'] || ($_menu[$itemKey]['children'] && !array_key_exists('link', $_menu[$itemKey]));
			
			if($mustExpand) {
				$style = ($_menu[$itemKey]['children'] ? 'expanded' : 'leaf');
			} else {
				$style = ($_menu[$itemKey]['children'] ? 'collapsed' : 'leaf');
			}

			$output .= "<li class=\"$style\">";
			$output .= _menu_render_item($_menu[$itemKey], ($itemKey == $active));
			if ($mustExpand) {
				$output .= menu_render($itemKey);
			}
			$output .= "</li>\n";
		}
	}
	if ($output != '') {
		$output  = "\n<ul>\n$output\n</ul>\n";
	}
	return $output;
}

/**
 * Sort function for ordering menu children
 */
function _menu_internal_sort($a, $b) {
	global $_menu;

	$a = &$_menu[$a];
	$b = &$_menu[$b];

	return $a["weight"] < $b["weight"] ? -1 : ($a["weight"] > $b["weight"] ? 1 : ($a["title"] < $b["title"] ? -1 : 1));
}
function _menu_render_item(&$item, $isActive) {

	if($isActive) {
		
		$attrs = array('class' => 'active');
	} else {
		$attrs = array();
	}

	if ( $item['link'] ) {
		return l($item['title'], $item['link'], $attrs);
	} else {
		return $item["title"];
	}
}

/**
 * Query to module to build the menu.
 */
function menu_build( ) {
	/*
  	 * Build a sequential list of all menus items.
  	 */
	menu_add_child('','_root','Root of Menus');
	module_invoke_all('menu');
}

/**
 * Get the parent menu items that lead to a given page
 */
function menu_get_trail( $path = 0)
{
	global $_menu;
	
	if(!$path) {
		$path = $_GET["q"];
	}

	$trail = array();
	$parents = _menu_get_parents($path);	
	while( list(,$key) = each($parents) ) {
		if(!is_null($_menu[$key])) {
			$trail[] = $_menu[$key];
		}
	}
	$trail = array_reverse($trail);
	$trail[] = $_menu[_menu_get_active($path)];
	return $trail;
}

?>

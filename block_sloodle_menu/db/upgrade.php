<?php  

function xmldb_block_sloodle_menu_upgrade($oldversion=0) 
{
	global $CFG, $THEME, $DB;

	$dbman = $DB->get_manager();

    return true;
}

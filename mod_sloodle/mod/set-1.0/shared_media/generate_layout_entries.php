﻿<?php
/** Grab the Sloodle/Moodle configuration. */
require_once('../../../init.php');
/** Include the Sloodle PHP API. */
/** Sloodle core library functionality */
require_once(SLOODLE_DIRROOT.'/lib.php');
/** General Sloodle functions. */
require_once(SLOODLE_LIBROOT.'/io.php');
/** Sloodle course data. */
require_once(SLOODLE_LIBROOT.'/course.php');
require_once(SLOODLE_LIBROOT.'/layout_profile.php');
require_once(SLOODLE_LIBROOT.'/user.php');

require_once(SLOODLE_LIBROOT.'/object_configs.php');
require_once '../../../lib/json/json_encoding.inc.php';

include('index.template.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(SLOODLE_LIBROOT.'/layout_recipe/layout_recipe_base.php');

$layoutid = optional_param('layoutid', 0, PARAM_INT);
$recipe   = optional_param('layoutrecipe', 'SloodleSimpleLayoutRecipe', PARAM_TEXT);

if (!$layoutid) {
	error_output( 'Layout ID missing');
}

if (!class_exists( $recipe ) ){
	error_output( 'Recipe definition not found');
}

$layout = new SloodleLayout();
if (!$layout->load($layoutid)) {
	error_output( 'Could not load layout');
}

$recipe = new $recipe();
if (!$recipe->generate()) {
	error_output( 'Could not generate recipe');
}

if (!$recipe->saveToLayoutWithID( $layoutid )) {
	error_output( 'Could not save generated recipe to layout');
}

$courseid = $layout->course;

//$controller_context = get_context_instance( CONTEXT_MODULE, $layout->controllerid);
$controller_context = context_module::instance($layout->controllerid);
if (!has_capability('mod/sloodle:uselayouts', $controller_context)) {
        error_output( 'Access denied');
}

$controllerid = $layout->controllerid;
$rezzeruuid = $_REQUEST['rezzeruuid'];

$addedentries = array();
foreach($layout->get_entries() as $layoutentry) {
	$config = SloodleObjectConfig::ForObjectName($layoutentry->name);

	$modtitle = $layoutentry->get_course_module_title();
	if (!$modtitle) {
		$modtitle = '';
	}

    ob_start();
    $element_id = print_rezzable_item_li( $layoutentry, $courseid, $controllerid, $layout, false);
    $html_list_item = ob_get_clean();

    ob_start();
    $element_id = print_config_form( $layoutentry, $config, $courseid, $controllerid, $layoutid, $config->group, $rezzeruuid);
    $edit_object_form = ob_get_clean();

	$addedentries[] = array(
		'objectgroup' => $config->group,
		'objectgrouptext' => get_string('objectgroup:'.$config->group, 'sloodle'), 
		'objecttypelinkable' => $config->type_for_link(), 
		'objectname' => preg_replace('/SLOODLE\s/', '', $layoutentry->name),
		'moduletitle' => $modtitle,
		'layoutid' => $layoutid,
		'layoutentryid' => $layoutentry->id,
        'html_list_item' => $html_list_item,
        'edit_object_form' => $edit_object_form
	);
};

$content = array(
	'result' => 'generated',
	'layoutid' => $layoutid,
	'addedentries' => $addedentries,
);

print json_encode($content);
exit;

function error_output($error) {
	$content = array(
		'result' => 'failed',
		'error' => $error,
	);
	print json_encode($content);
	exit;
}
?>

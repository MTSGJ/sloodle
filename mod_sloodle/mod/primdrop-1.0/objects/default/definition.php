<?php
$sloodleconfig = new SloodleObjectConfig();
$sloodleconfig->primname   = 'SLOODLE PrimDrop';
//$sloodleconfig->module     = 'assignment';
//$sloodleconfig->module_choice_message = 'selectassignment';
//$sloodleconfig->module_no_choices_message = 'nosloodleassignments'; 
//$sloodleconfig->module_filters = array( 'assignmenttype' => 'sloodleobject');
$sloodleconfig->module     = 'assign';
$sloodleconfig->module_choice_message = 'selectassign';
$sloodleconfig->module_no_choices_message = 'nosloodleassigns'; 
$sloodleconfig->module_filters = array('plugin'=>'sloodleobj', 'subtype'=>'assignsubmission', 'name'=>'enabled');
$sloodleconfig->group      = 'communication';
$sloodleconfig->collections= array('SLOODLE 2.0');
$sloodleconfig->aliases    = array('SLOODLE 1.1 PrimDrop');
$sloodleconfig->field_sets = array( 
	'accesslevel' => array(
		'sloodleobjectaccessleveluse'  => $sloodleconfig->access_level_object_use_option(), 
		'sloodleobjectaccesslevelctrl' => $sloodleconfig->access_level_object_control_option(),
		'sloodleserveraccesslevel'     => $sloodleconfig->access_level_server_option(),
	),
);

<?php

$settings->add(new admin_setting_configcheckbox('assignsubmission_sloodleobj/default',
                   new lang_string('default', 'assignsubmission_sloodleobj'),
                   new lang_string('default_help', 'assignsubmission_sloodleobj'), 0));

$settings->add(new admin_setting_configtext('assignsubmission_sloodleobj/maxobjects',
                   new lang_string('maxobjects', 'assignsubmission_sloodleobj'),
                   new lang_string('maxobjects_help', 'assignsubmission_sloodleobj'), 10, PARAM_INT));

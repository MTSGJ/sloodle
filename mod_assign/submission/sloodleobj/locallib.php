<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
    
/**
 * This file contains the definition for the library class for sloodle object submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_sloodleobj
 * @copyright 2012 NetSpot  {@link http://www.netspot.com.au}
 * @copyright 2016 Fumi.Hax {@link http://www.nsl.tuis.ac.jp}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/eventslib.php');

defined('MOODLE_INTERNAL') || die();


define('ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA', 'submission_sloodleobj');

 
class assign_submission_sloodleobj extends assign_submission_plugin
{
    public function get_name()
    {
        return get_string('object', 'assignsubmission_sloodleobj');
    }


    private function get_object_submission($submissionid)
    {
        global $DB;

        return $DB->get_record('assignsubmission_sloodleobj', array('submission'=>$submissionid));
    }


    public function get_settings(MoodleQuickForm $mform)
    {
        global $CFG, $COURSE;

        $defaultmaxobjectsubmissions = $this->get_config('maxobjectsubmissions');
        if ($defaultmaxobjectsubmissions==null) $defaultmaxobjectsubmissions = 1;

        $options = array();
        for ($i=0; $i<=get_config('assignsubmission_sloodleobj', 'maxobjects'); $i++) {
            $options[$i] = $i;
        }

        $name = get_string('maxobjectssubmission', 'assignsubmission_sloodleobj');
        $mform->addElement('select', 'assignsubmission_sloodleobj_maxobjects', $name, $options);
        $mform->addHelpButton('assignsubmission_sloodleobj_maxobjects', 'maxobjectssubmission', 'assignsubmission_sloodleobj');
        $mform->setDefault('assignsubmission_sloodleobj_maxobjects', $defaultmaxobjectsubmissions);
        $mform->disabledIf('assignsubmission_sloodleobj_maxobjects', 'assignsubmission_sloodleobj_enabled', 'notchecked');
    }


    public function save_settings(stdClass $data) 
    {
        $this->set_config('maxobjectsubmissions', $data->assignsubmission_sloodleobj_maxobjects);

        return true;
    }


    private function get_edit_options()
    {
         $editoroptions = array(
           'noclean'  => false,
           'maxfiles' => EDITOR_UNLIMITED_FILES,
           'maxbytes' => $this->assignment->get_course()->maxbytes,
           'context'  => $this->assignment->get_context(),
           'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        );
        return $editoroptions;
    }


    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data)
    {
        if ($this->get_config('maxobjectsubmissions')<0) {
            return false;
        }

        $editoroptions = $this->get_edit_options();
        $submissionid = $submission ? $submission->id : 0;

        if (!isset($data->objectname)) {
            $data->objectname = '';
        }
        if (!isset($data->objectnameformat)) {
            $data->objectnameformat = editors_get_preferred_format();
        }
        if ($submission) {
            $objectsubmission = $this->get_object_submission($submissionid);
            if ($objectsubmission) {
                $data->objectname = $objectsubmission->objectname;
                $data->objectnameformat = $objectsubmission->objectnameformat;
            }
        }

        $context = $this->assignment->get_context();
        $data = file_prepare_standard_editor($data, 'objectname', $editoroptions, $context, 'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $submissionid);
        $mform->addElement('editor', 'objectname_editor', $this->get_name(), null, $editoroptions);

        return true;
    }


    public function save(stdClass $submission, stdClass $data)
    {
        global $USER, $DB;

        $editoptions = $this->get_edit_options();
        $context = $this->assignment->get_context();
        $submissionid = $submission->id;
        $data = file_postupdate_standard_editor($data, 'objectname', $editoptions, $context, 'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $submissionid);

        $objectsubmission = $this->get_object_submission($submissionid);

        $fs = get_file_storage();
        $contextid = $this->assignment->get_context()->id;
        $files = $fs->get_area_files($contextid, 'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $submissionid, 'id', false);

        // log
        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submissionid,
            'other' => array(
                'pathnamehashes' => array_keys($files),
                'content' => trim($data->objectname),
                'format'  => $data->objectname_editor['format']
            )
        );

        if (!empty($submission->userid) && ($submission->userid!=$USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }

        $event = \assignsubmission_sloodleobj\event\assessable_uploaded::create($params);
        $event->trigger();

        //
        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), '*', MUST_EXIST);
            $groupid = $submission->groupid;
        }
        else {
            $params['relateduserid'] = $submission->userid;
        }

        unset($params['objectid']);
        unset($params['other']);
        $params['other'] = array(
            'submissionid' => $submissionid,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'objectsubmissioncount' => 0,
            'groupid' => $groupid,
            'groupname' => $groupname
        );

        if ($objectsubmission) {
            $objectsubmission->objectname = $data->objectname;
            $objectsubmission->objectnameformat = $data->objectname_editor['format'];
            $updatestatus = $DB->update_record('assignsubmission_sloodleobj', $objectsubmission);
            // log
            $params['objectid'] = $objectsubmission->id;
            $params['other']['objectsubmissioncount'] = $objectsubmission->numobjects;
            $event = \assignsubmission_sloodleobj\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $updatestatus;
        }
        else {
            $objectsubmission = new stdClass();
            $objectsubmission->assignment = $this->assignment->get_instance()->id;
            $objectsubmission->submission = $submissionid;
            $objectsubmission->numobjects = 0;
            $objectsubmission->data1 = '';
            $objectsubmission->data2 = '';
            $objectsubmission->objectname = $data->objectname;
            $objectsubmission->objectnameformat = $data->objectname_editor['format'];
            $objectsubmission->id = $DB->insert_record('assignsubmission_sloodleobj', $objectsubmission);
            // log
            $params['objectid'] = $objectsubmission->id;
            $event = \assignsubmission_sloodleobj\event\submission_created::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $objectsubmission->id > 0;
        }
    }


    public function get_files(stdClass $submission, stdClass $user)
    {
        global $DB;

        $files = array();
        $objectsubmission = $this->get_object_submission($submission->id);

        if ($objectsubmission) {
            $finaltext = $this->assignment->download_rewrite_pluginfile_urls($objectsubmission->objectname, $user, $this);
            $formattedtext = format_text($finaltext, $objectsubmission->objectnameformat, array('context'=>$this->assignment->get_context()));
            $head = '<head><meta charset="UTF-8"></head>';
            $submissioncontent = '<!DOCTYPE html><html>' . $head . '<body>'. $formattedtext . '</body></html>';

            $filename = get_string('sloodleobjfilename', 'assignsubmission_sloodleobj');
            $files[$filename] = array($submissioncontent);

            $fs = get_file_storage();
            $fsfiles = $fs->get_area_files($this->assignment->get_context()->id, 'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $submission->id, 'timemodified', false);

            foreach ($fsfiles as $file) {
                $files[$file->get_filename()] = $file;
            }
        }

        return $files;
    }


    public function get_editor_fields()
    {
        return array('sloodleobj' => get_string('pluginname', 'assignsubmission_comments'));
    }


    public function get_editor_text($name, $submissionid)
    {
        if ($name=='objectname') {
            $objectsubmission = $this->get_object_submission($submissionid);
            if ($objectsubmission) {
                return $objectsubmission->objectname;
            }
        }

        return '';
    }


    public function get_editor_format($name, $submissionid)
    {
        if ($name=='objectname') {
            $objectsubmission = $this->get_object_submission($submissionid);
            if ($objectsubmission) {
                return $objectsubmission->objectnameformat;
            }
        }

        return 0;
    }


    // display at grading
    public function view_summary(stdClass $submission, & $showviewlink)
    {
        global $CFG;

        $showviewlink = true;
        $objectsubmission = $this->get_object_submission($submission->id);

        if ($objectsubmission) {
            $text = $objectsubmission->objectname;
            $shorttext = shorten_text($text, 100);
            return $shorttext;
        }
        return '';
    }


    public function view(stdClass $submission)
    {
        $result = '';

        $objectsubmission = $this->get_object_submission($submission->id);
        if ($objectsubmission) {
            $result = $objectsubmission->objectname;
        }

        return $result;
    }


    public function can_upgrade($type, $version)
    {
        if ($type=='online') return true;
        return false;
    }


    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log)
    {
        return true;
    }


    public function upgrade(context $oldcontext, stdClass $oldassignment, stdClass $oldsubmission, stdClass $submission, & $log)
    {
        global $DB;
    
        $objectsubmission = new stdClass();
        $objectsubmission->data1 = $oldsubmission->data1;
        $objectsubmission->data2 = $oldsubmission->data2;
   
        $submissionid = $submission->id;
        $objectsubmission->submission = $submissionid;
        $objectsubmission->assignment = $this->assignment->get_instance()->id;
    
        $objectsubmission->objectname = '';
        $objectsubmission->objectnameformat = editors_get_preferred_format();
    
        if (!$DB->insert_record('assignsubmission_sloodleobj', $objectsubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }
    
        $contextid = $this->assignment->get_context()->id;
        $oldcontextid = $oldcontext->id;
        $this->assignment->copy_area_files_for_upgrade($oldcontext->id, 'mod_assignment', 'submission', $oldsubmissionid, $contextid, 
                                                                        'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $submissionid);
        return true;
    }


    public function delete_instance()
    {
        global $DB;

        $assignmentid = $this->assignment->get_instance()->id;
        $DB->delete_records('assignsubmission_sloodleobj', array('assignment'=>$assignmentid));

        return true;
    }


    public function format_for_log(stdClass $submission)
    {
        $objectsubmission = $this->get_object_submission($submission->id);
        $objectloginfo = get_string('numobjectforlog', 'assignsubmission_sloodleobj', $objectsubmission->numobjects);

        return $objectloginfo;
    }


    public function is_empty(stdClass $submission)
    {
        $objectsubmission = $this->get_object_submission($submission->id);

        return empty($objectsubmission->objectname);
    }


    public function get_file_areas()
    {
        return array(ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA=>$this->get_name());
    }


    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission)
    {
        global $DB;

        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'assignsubmission_sloodleobj', ASSIGNSUBMISSION_SLOODLEOBJ_FILEAREA, $sourcesubmission->id, 'id', false);

        foreach ($files as $file) {
            $fieldupdates = array('itemid' => $destsubmission->id);
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }

        $objectsubmission = $this->get_object_submission($sourcesubmission->id);
        if ($objectsubmission) {
            unset($obectjsubmission->id);
            $objectsubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_sloodleobj', $objectsubmission);
        }
        return true;
    }


    public function get_external_parameters()
    {
        $editorparams = array('text'   => new external_value(PARAM_RAW, 'The text for this submission.'),
                              'format' => new external_value(PARAM_INT, 'The format for this submission'),
                              'itemid' => new external_value(PARAM_INT, 'The draft area id for files attached to the submission'));
        $editorstructure = new external_single_structure($editorparams);

        return array('objectname_editor' => $editorstructure);
    }
}

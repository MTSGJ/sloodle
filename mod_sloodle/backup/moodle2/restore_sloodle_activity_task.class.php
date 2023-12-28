<?php
require_once($CFG->dirroot . '/mod/sloodle/backup/moodle2/restore_sloodle_stepslib.php');
/**
 * sloodle restore task that provides all the settings and steps to perform one
* complete restore of the activity
*/
class restore_sloodle_activity_task extends restore_activity_task
{
    /**
    * Define (add) particular settings this activity can have
    */
    protected function define_my_settings()
    {
    // No particular settings for this activity
    }


    /**
    * Define (add) particular steps this activity can have
    */
    protected function define_my_steps()
    {
        // Sloodle only has one structure step
        $this->add_step(new restore_sloodle_activity_structure_step('sloodle_structure', 'sloodle.xml'));
    }


    /*
    Hack alert, Edmund Edgar, 2012-06-23:
    Presenter URLs need to be mapped back to the new site and context.
    This is probably doable with these decode_rules, but I don't understand how they work
    ...so I'm using after_restore instead.
    If anyone understands this stuff, please go ahead and rewrite it to do it right.
    */

    /**
    * Define the contents in the activity that must be
    * processed by the link decoder
    */
    static public function define_decode_contents()
    {
        $contents = array();

        //$contents[] = new restore_decode_content('sloodle_presenter_entry', array('source'), 'sloodle_presenter_entry');

        return $contents;
    }


    /**
    * Define the decoding rules for links belonging
    * to the activity to be executed by the link decoder
    */
    static public function define_decode_rules()
    {
        $rules = array();

        /*
        $rules[] = new restore_decode_rule('CHOICEVIEWBYID', '/mod/choice/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CHOICEINDEX', '/mod/choice/index.php?id=$1', 'course');
        */

        return $rules;
    }


    /**
    * Define the restore log rules that will be applied
    * by the {@link restore_logs_processor} when restoring
    * choice logs. It must return one array
    * of {@link restore_log_rule} objects
    */
    static public function define_restore_log_rules()
    {
        $rules = array();

        /*
        $rules[] = new restore_log_rule('choice', 'add', 'view.php?id={course_module}', '{choice}');
        $rules[] = new restore_log_rule('choice', 'update', 'view.php?id={course_module}', '{choice}');
        $rules[] = new restore_log_rule('choice', 'view', 'view.php?id={course_module}', '{choice}');
        $rules[] = new restore_log_rule('choice', 'choose', 'view.php?id={course_module}', '{choice}');
        $rules[] = new restore_log_rule('choice', 'choose again', 'view.php?id={course_module}', '{choice}');
        $rules[] = new restore_log_rule('choice', 'report', 'report.php?id={course_module}', '{choice}');
        */

        return $rules;
    }


    /**
    * Define the restore log rules that will be applied
    * by the {@link restore_logs_processor} when restoring
    * course logs. It must return one array
    * of {@link restore_log_rule} objects
    *
    * Note this rules are applied when restoring course logs
    * by the restore final task, but are defined here at
    * activity level. All them are rules not linked to any module instance (cmid = 0)
    */
    static public function define_restore_log_rules_for_course()
    {
        $rules = array();

        /*
        // Fix old wrong uses (missing extension)
        $rules[] = new restore_log_rule('choice', 'view all', 'index?id={course}', null,
        null, null, 'index.php?id={course}');
        $rules[] = new restore_log_rule('choice', 'view all', 'index.php?id={course}', null);
        */

        return $rules;
    }
 

    public function after_restore()
    {
        // Get a list of inserted layout entries

        global $DB;
        global $CFG;

        // Layout entry config may refer to an external course module, eg chatroom.
        // If we've done a full course backup, we should be able to recover it.
        // If we can't recover it, we'll set it to 0/null, and hope the UI can deal with that sensibly.
        $layouts = $DB->get_records('sloodle_layout', array('controllerid' => $this->get_moduleid()));
        if (count($layouts)) {
            foreach($layouts as $layout) {
                $layout_entries = $DB->get_records('sloodle_layout_entry', array('layout' => $layout->id)); 
                if (count($layout_entries)) {
                    foreach($layout_entries as $le) {

                        $layout_entry_configs = $DB->get_records('sloodle_layout_entry_config', array('layout_entry' => $le->id, 'name'=>'sloodlemoduleid')); 
                        foreach($layout_entry_configs as $lec) {
                            if ($mapcm = restore_structure_step::get_mapping('course_module', $lec->value)) {
                                $lec->value = $mapcm->newitemid;
                                $DB->update_record('sloodle_layout_entry_config', $lec);
                            } 
                        }

                        $layout_entry_configs = $DB->get_records('sloodle_layout_entry_config', array('layout_entry' => $le->id, 'name'=>'sloodlecurrencyid')); 
                        foreach($layout_entry_configs as $lec) {
                            if ($mapcm = restore_structure_step::get_mapping('sloodle_currency_types', $lec->value)) {
                                $lec->value = $mapcm->newitemid;
                                $DB->update_record('sloodle_layout_entry_config', $lec);
                            }
                        } 

                    }
                }
            }
        }

        /*
        This should probably really be done with decode_rules - see hack alert above.
        */
        $moduleid = $this->get_moduleid();
        $sloodleid = $DB->get_field('course_modules', 'instance', array('id'=>$moduleid));
        $contextid = $this->get_contextid();

        $presenter_entries = $DB->get_records('sloodle_presenter_entry', array('sloodleid' => $sloodleid));
        if (count($presenter_entries) > 0) {
            foreach($presenter_entries as $pe) {
                $source = $pe->source;
                $base = preg_quote('$@SITEROOT@$', '#');
                $contextidplaceholder = preg_quote('$@CONTEXTID@$', '#');
                if (preg_match('#^'.$base.'(/pluginfile.php/)'.$contextidplaceholder.'(/mod_sloodle/presenter/.*)#', $source, $matches)) {
                    $pe->source = $CFG->wwwroot.$matches[1].$contextid.$matches[2];
                    $DB->update_record('sloodle_presenter_entry', $pe);
                }
            }
        }
        //$file_entries = $DB->get_records('mdl_files', array('contextid'=>$contextid, 'component'=>'mod_sloodle', 'filearea'=>'presenter'));
    }

}

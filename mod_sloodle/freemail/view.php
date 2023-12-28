<?php
 /**
* Freemail v1.1 with SL patch
*
* @package freemail
* @copyright Copyright (c) 2008 Serafim Panov
* @license http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3
* @author Serafim Panov
* @contributor: Paul G. Preibisch - aka Fire centaur in Second Life
*
*/


require_once "../init.php";
if (!defined('SLOODLE_FREEMAIL_ACTIVATE') || !SLOODLE_FREEMAIL_ACTIVATE) {
    die("Freemail is turned off. Enable SLOODLE_FREEMAIL_ACTIVATE in sloodle_config to turn it on.");
}

$PAGE->set_context(get_system_context());
//$PAGE->set_context(get_context_instance(CONTEXT_COURSE, SITEID));
$PAGE->set_context(context_course::instance(SITEID, IGNORE_MISSING));

$PAGE->set_url('/mod/sloodle/freemail/view.php');
$PAGE->set_title('Postcard Blogger');
$PAGE->set_heading('Postcard Blogger.');

require_login();

echo $OUTPUT->header();

//$PAGE->set_context(context_system::instance());
//$PAGE->set_pagelayout('standard');

$freemail_dir = dirname(__FILE__);

require_once $freemail_dir.'/lib/freemail_imap_message_handler.php'; 
require_once $freemail_dir.'/lib/freemail_email_processor.php'; 
require_once $freemail_dir.'/lib/freemail_moodle_importer.php'; 

$freemail_cfg = isset($CFG->sloodle_freemail_force_settings) ? $CFG->sloodle_freemail_force_settings : $CFG;

$noticeTable = new html_table();
$noticeTable->head = array('SLOODLE Freemail - Postcard Blogger');
$r = array();

if ( $freemail_cfg->sloodle_freemail_mail_box_settings == '' ) {
    $body = get_string('freemail:confignotset','sloodle', '');

    $noticeTable->width='100%';
    $noticeTable->cellpadding=10;
    $noticeTable->cellspacing=10; 
    $noticeTable->data[] = $r;  
    echo html_writer::table($noticeTable);
    echo $OUTPUT->footer();
    exit;
}

$body = get_string('freemail:explanation_wheretosend','sloodle', '');
$body .= '<br />';
$body .= '<br />';
$body .= '<strong>'.htmlentities($freemail_cfg->sloodle_freemail_mail_email_address).'</strong>';
$body .= '<br />';
$body .= '<br />';
$body .= get_string('freemail:explanation_howtoblog','sloodle');
$r[] = $body;

$courseTable = new stdClass();
$courseTable->class="course-view";

$noticeTable->width='100%';
$noticeTable->cellpadding=10;
$noticeTable->cellspacing=10; 
$noticeTable->data[] = $r;  
echo html_writer::table($noticeTable);

$nodelete = false;
if (isset($_POST['nodelete'])) {
    $nodelete = true;
}

?>
<div style="text-align:center; width:100%">
<form method="POST">
<input type="hidden" value="1" name="do_test" />
<input type="checkbox" name="nodelete" value="1" <?php echo $nodelete ? ' checked="checked" ' : ''?>/>
<?php
echo get_string('freemail:delete_message', 'sloodle');
?>
<br />
<input type="submit" value="<?php echo get_string('freemail:testbutton', 'sloodle')?>" />
</form>
</div>

<p><br /></p>
<?php
if (isset($_POST['do_test'])) {
    $verbose = true;
    $daemon = false;

    echo '<textarea rows="10" style="width:100%">';
    sloodle_freemail_email_processor::read_mail($CFG, $verbose, $daemon, null, $nodelete);
    echo '</textarea>';
}
if ($nodelete && $daemon) {
    echo "Refusing to run in daemon mode with nodelete specified, as this will spam you into oblivion.\n";
    exit;
}

echo $OUTPUT->footer();
exit;

//echo $OUTPUT->heading('SLOODLE Freemail - Postcard Blogger', 1);
//exit;

$PAGE->set_state(1);
$PAGE->set_state(2);

echo $OUTPUT->footer();
exit;

//$OUTPUT->footer();

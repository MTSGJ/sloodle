<?php
/**
* Quiz-engine-specific part of the SLOODLE quiz linker.
* For Moodle 2.1 or higher
* 
* Allows in-world objects to interact with Moodle quizzes.
* Part of the Sloodle project (www.sloodle.org).
*
* @package sloodlequiz
* @copyright Copyright (c) 2006-8 Sloodle (various contributors)
* @license http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3
*
* @contributor (various Moodle authors including Tim Hunt)
* @contributor Edmund Edgar
* @contributor Peter R. Bloomfield
*/

/**
* 
* This file is based on the following files from Moodle Core:
* startattempt.php handles starting an attempt.
* processattempt.php handles processing input.
* finishattempt.php handles closing the attempt.
*
* These are combined into one, because in SLOODLE we linked to have one object talk to a single linker.php.
* 
*/

defined('SLOODLE_LINKER_SCRIPT') || die();

/*
SLOODLE-specific attemptlib with a sub-class of one of the core Moodle classes:
See that file for an explanation of why we're doing this.
*/
require_once(SLOODLE_DIRROOT.'/mod/quiz-1.0/'.'attemptlib.php');

/**
* The following is mostly copied from startattempt.php
*/

$quizobj = quiz::create($cm->instance, $USER->id);

//require_login($quizobj->get_courseid(), false, $quizobj->get_cm());

// if no questions have been set up yet redirect to edit.php or display an error.
if (!$quizobj->has_questions()) {
    $sloodle->response->quick_output(-12312, 'QUIZ', 'The quiz does not have any questions', FALSE);
    exit();
}

// Create an object to manage all the other (non-roles) access rules.
$accessmanager = $quizobj->get_access_manager(time());
if ($quizobj->is_preview_user() && $forcenew) {
    $accessmanager->current_attempt_finished();
}

// Check capabilities.
// SLOODLE TODO: Replace this with a check to has_capability, and the relevant SLOODLE error code
if (!$quizobj->is_preview_user()) {
    $quizobj->require_capability('mod/quiz:attempt');
}

// Check to see if a new preview was requested.
if ($quizobj->is_preview_user() && $forcenew) {
    // To force the creation of a new preview, we set a finish time on the
    // current attempt (if any). It will then automatically be deleted below
    $DB->set_field('quiz_attempts', 'timefinish', time(), array('quiz' => $quizobj->get_quizid(), 'userid' => $USER->id));
}

// Look for an existing attempt.
$attempts = quiz_get_user_attempts($quizobj->get_quizid(), $USER->id, 'all', true);
$lastattempt = end($attempts);

// If an in-progress attempt exists, check password then redirect to it.
if ($lastattempt && !$lastattempt->timefinish) {
    $currentattemptid = $lastattempt->id;
    $messages = $accessmanager->prevent_access();
}
else {
    // Get number for the next or unfinished attempt
    if ($lastattempt && !$lastattempt->preview && !$quizobj->is_preview_user()) {
        $attemptnumber = $lastattempt->attempt + 1;
    }
    else {
        $lastattempt = false;
        $attemptnumber = 1;
    }
    $currentattemptid = null;

    $messages = $accessmanager->prevent_access() + $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);
}

// Check access.
if (!$quizobj->is_preview_user() && $messages) {
    $sloodle->response->quick_output(-12313, 'QUIZ', 'You do not have permission to use this quiz.', FALSE);
    exit();
}

$attemptid = null;

//
if ($currentattemptid) {

    $attemptid = $currentattemptid;

}
else {
    // Delete any previous preview attempts belonging to this user.
    quiz_delete_previews($quizobj->get_quiz(), $USER->id);

    $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
    $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

    // API changes in 2.4. https://github.com/moodle/moodle/commit/8e771aed93eea08cc3e9410283f5354e02311281
    // Create the new attempt and initialize the question sessions
    if ($CFG->version < 2012120303) { 
        $attempt = quiz_create_attempt($quizobj->get_quiz(), $attemptnumber, $lastattempt, time(), $quizobj->is_preview_user());
    }
    else {
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, $lastattempt, time(), $quizobj->is_preview_user());
    }

    if (!($quizobj->get_quiz()->attemptonlast && $lastattempt)) {
        // Starting a normal, new, quiz attempt.
        $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, time());
    }
    else {
        // Starting a subsequent attempt in each attempt builds on last mode.
        $attempt = quiz_start_attempt_built_on_last($quba, $attempt, $lastattempt); 
    }

    // Save the attempt in the database.
    $transaction = $DB->start_delegated_transaction();
    $attempt = quiz_attempt_save_started($quizobj, $quba, $attempt);
    $transaction->allow_commit();

    $attemptid = $attempt->id;
} 


/* 
SLOODLE: Edmund Edgar, 2012-05-15
The following comes from attempt.php
Some of this is probably duplicated with code originally from startattempt.php
...but we'll repeat it just in case
...as I'm not sure exactly what it all does.
*/

$attemptobj = sloodle_quiz_attempt::create($attemptid);
$quiz  = $attemptobj->get_quiz();
$slots = $attemptobj->get_slots('all');

if (empty($slots)) {
    $sloodle->response->quick_output(-12312, 'QUIZ', 'The quiz does not have any questions', FALSE);
    exit();
}

$title = get_string('attempt', 'quiz', $attemptobj->get_attempt_number());
//$PAGE->set_title(format_string($attemptobj->get_quiz_name()));
//$PAGE->set_heading($attemptobj->get_course()->fullname);
//$accessmanager->setup_attempt_page($PAGE);

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    $sloodle->response->quick_output(-12314, 'QUIZ', 'The attempt did not appear to belong to you.', FALSE);
    exit();
}

// Check capabilities and block settings
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}
else {
    //navigation_node::override_active_url($attemptobj->start_attempt_url());
    $sloodle->response->quick_output(-12315, 'QUIZ', 'You do not have permission to take the quiz. Moodle quizzes are often only open to students, not teachers.', FALSE);
    exit();
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    // TODO SLOODLE Error
    //redirect($attemptobj->review_url(null, $page));
    $sloodle->response->quick_output(-12316, 'QUIZ', 'This quiz attempt has already finished.', FALSE);
    exit();
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$messages = $accessmanager->prevent_access();
if (count($messages)) {
    $sloodle->response->quick_output(-12317, 'QUIZ', 'Access to this quiz is restricted.', FALSE);
    exit();
}

$output = $PAGE->get_renderer('mod_quiz');
if (!$attemptobj->is_preview_user() && $messages) {
    // TODO: SLOODLE ERROR
    //print_error('attempterror', 'quiz', $attemptobj->view_url(), $output->access_messages($messages));
}

//add_to_log($attemptobj->get_courseid(), 'quiz', 'continue attempt', 'review.php?attempt=' . $attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());
sloodle_add_to_log($attemptobj->get_courseid(), 'mod_log', 'mod/quiz-1.0/linker_moodle_2_1_up.php', array('attempt'=>$attemptobj->get_attemptid()), 'quiz_up: continue attempt');


/*
*
* The following mostly comes from processattempt.php
*
*/

if ($isnotify) {
    $transaction = $DB->start_delegated_transaction();

    if ($questionids != '') {
        $questionids = explode(',', $questionids);
    }

    if (!$questionid = $questionids[0]) {
        $sloodle->response->quick_output(-12318, 'QUIZ', 'Question ID not set.', FALSE);
        exit();
    }
    if (!$quizresponse) {
        $sloodle->response->quick_output(-12319, 'QUIZ', 'Question response not set.', FALSE);
        exit();
    }

    //echo $attemptobj->get_question_attempt(1)->get_field_prefix();
    //exit;

    $responseoptionindex = -1;
    //$attemptobj->process_all_actions(time(), array('answer'=>$quizresponse));
    // We get passed the quiz ID, so we'll have to work through them to find the slot.
    // TOOD: It would probably make more sense to have the LSL script know which slot and send us it itself.
    foreach($slots as $slot) {

        $qa = $attemptobj->get_question_attempt($slot);
        $q  = $qa->get_question();

        if ($q->id == $questionid) {
            if (method_exists($q, 'get_order')) {
                // The linker script returns the option ID, but we need its index for process_all_actions_for_slot.
                $order = $q->get_order($qa);
                $responseoptionindex = array_search( $quizresponse, $order );

                if ($responseoptionindex < 0) {
                    $sloodle->response->quick_output(-12318, 'QUIZ', 'Response index not found.', FALSE);
                    exit();
                }

                $submitteddata = array('answer'=>$responseoptionindex);
                //i print "processing for $slot, $questionid $responseoptionindex";
                $attemptobj->process_all_actions_for_slot($slot, $submitteddata, time());
            }

            $transaction->allow_commit();
            break;
        }
    }

    // We have now finished processing form data

    // With post sloodle-2.0 quiz chairs, we should be told what happened to the score.
    // In theory we should be able to get this from the data we already have, but it seems complex...
    // The process_events allows us to award points if there is an instruction to do so in the object config.
    $scorechange = floatval(optional_param( 'scorechange', 0, PARAM_TEXT));

    //SloodleDebugLogger::log('DEBUG', "active object check");
    if (!is_null($sloodle->active_object)) {
        //SloodleDebugLogger::log('DEBUG', "quiz has an active object");
        $sloodle->process_interaction('answerquestion', $scorechange);
        //$transaction->allow_commit();
    }

    $output = array();
}
//
else {
    //$pagequestions = explode(',', $pagelist);
    //$lastquestion  = count($pagequestions);
    $lastquestion  = 1;

    // TODO SLOODLE: Recheck this
    // Only output quiz data if a question has not been requetsed
    $output = array();
    
    // Can't see a way to access this - every bastard thing is protected...
    // Load all the questions and loop through them...
    $availablequestionids = array();

    $localqnum = 0;
    foreach($slots as $slot) {

        $qa = $attemptobj->get_question_attempt($slot);
        $q  = $qa->get_question();

        //var_dump($q);
        //var_dump($attemptobj->get_question_attempt($slot));
        //exit;
        //var_dump($q);
        //exit;
        //var_dump($attemptobj->get_behaviour());
        //exit;
        $availablequestionids[] = $q->id;
        $localqnum++;

        if ($limittoquestion == $q->id) {
            //echo "<hr>"; print_r($q); echo "<hr>";                

            // Make sure the variables exist (avoids warnings!)
            if (!isset($q->single)) $q->single = 0;
            $shuffleanswers = 0;
            if (isset($q->options->shuffleanswers) && $q->options->shuffleanswers) $shuffleanswers = 1;
        
            $qtypestr = is_object($q->qtype) ? get_class($q->qtype) : $q->qtype;
            // Feed the LSL script the question type it expects.
            // If we don't recognise it we'll just send the script whatever we got
            // ...which it will probably just tell the user it doesn't support.
            $supported_qtypes = array(
                'multichoice' => 'multichoice',
                'qtype_multichoice' => 'multichoice',
                'truefalse' => 'truefalse',
                'qtype_truefalse' => 'truefalse'
            );
            $qtype = isset($supported_qtypes[$qtypestr]) ? $supported_qtypes[$qtypestr] : $qtypestr;

            $output[] = array(
                'question',
                $localqnum, //$i, // The value in $i is equal to $q->id, rather than being sequential in the quiz
                $q->id,
                $q->parent,
                sloodle_clean_for_output($q->questiontext),
                1,//$q->defaultgrade ? $q->defaultgrade : 1,
                $q->penalty,
                $qtype,
                $q->hidden,
                0,//$q->maxgrade,
                $q->single,
                $shuffleanswers,
                0 //$deferred   // This variable doesn't seem to be mentioned anywhere else in the file
            );

            // Create an output array for our options (available answers) so that we can shuffle them later if necessary
            $outputoptions = array();
            // Go through each option

            if (method_exists($q, "get_order")) {
                $ordered_option_keys = $q->get_order($qa);
                $ops = $q->answers;             

                $i=1;
                foreach($ordered_option_keys as $option_key) {
                    $op = $ops[$option_key];
                    if (!is_object($op)) {
                        continue; // Ignore this if there are no options (Prevents nasty PHP notices!)
                    }
                    $feedback = sloodle_clean_for_output($op->feedback);

                    // If the feedback is too long, substitute a placeholder. 
                    // The script will see that and grab the feedback in a separate request. 
                    if (strlen($feedback) > 512) {
                        $feedback = "[[LONG]]";
                    }

                    $outputoptions[] = array(
                        'questionoption',
                        $i,
                        $op->id,
                        $q->id,
                        sloodle_clean_for_output($op->answer),
                        $op->fraction,
                        $feedback
                    );
                    $i++;
                }
            }
            
            // Shuffle the options if necessary
            if ($shuffleanswers) shuffle($outputoptions);
            // Append our options to the main output array
            $output = array_merge($output, $outputoptions);
        }

        /*
        var_dump($attemptobj->quba->render_question($slot,
                      $this->get_display_options_with_edit_link($reviewing, $slot, $thispageurl),
                                      $this->quba->get_question($slot)->_number));
        */
    }

    if ($limittoquestion == 0) {
        $output[] = array('quiz', $quiz->attempts, $quiz->name, $quiz->timelimit, $quiz->id, $lastquestion);
        $output[] = array('quizpages', 1, 1, join(',', $availablequestionids));
    }

    $secondsleft = ($quiz->timeclose ? $quiz->timeclose : 999999999999) - time();
    //if ($isteacher) {
    //    // For teachers ignore the quiz closing time
    //    $secondsleft = 999999999999;
    //}
    // If time limit is set include floating timer.
    if ($quiz->timelimit > 0) {
        $output[] = array('seconds left',$secondsleft);
    }
}


/*
*
* The following mostly comes from finishattempt.php
*
*/

if ($finishattempt) {

    $transaction = $DB->start_delegated_transaction();

    // Log the end of this attempt.
    //add_to_log($attemptobj->get_courseid(), 'quiz', 'close attempt', 'review.php?attempt=' . $attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());
    sloodle_add_to_log($attemptobj->get_courseid(), 'mod_log', 'mod/quiz-1.0/linker_moodle_2_1_up.php', array('attempt'=>$attemptobj->get_attemptid()), 'quiz_up: close attempt');

    // Update the quiz attempt record.
    try {
        // This seems to have changed somewhere between Moodle 2.1 and 2.3.
        // Try the old method first, then the new method if that fails.
        if (method_exists($attemptobj, 'finish_attempt')) {
            $attemptobj->finish_attempt(time());
        }
        else {
            $attemptobj->process_finish(time(), false);
        }
    /*
    } catch (question_out_of_sequence_exception $e) {
        print_error('submissionoutofsequencefriendlymessage', 'question', $attemptobj->attempt_url(null, $thispage));
    */
    } catch (Exception $e) {
        $sloodle->response->quick_output(-701, 'QUIZ', 'Closing quiz failed: '.$e->getMessage(), FALSE);
        exit();
    }

    // Send the user to the review page.
    $transaction->allow_commit();

    //redirect('review.php?attempt='.$attempt->id);
    //$sloodle->response->quick_output(-701, 'QUIZ', 'Got to finishattempt - but do not yet have Sloodle code to handle it.', FALSE);
    //exit();
}

$sloodle->response->set_status_code(1);
$sloodle->response->set_status_descriptor('QUIZ');
$sloodle->response->set_data($output);
$sloodle->response->render_to_output();

exit;

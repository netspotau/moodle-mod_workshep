<?php
    
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = required_param('id', PARAM_INT);            // course module

$workshep   = $DB->get_record('workshep', array('id' => $id), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);

$workshep   = new workshep($workshep, $cm, $course);

// the params to be re-passed to view.php
$page       = optional_param('page', 0, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);

$PAGE->set_url($workshep->calibrate_url(), array('page' => $page, 'sortby' => $sortby, 'sorthow' => $sorthow));

require_login($course, false, $cm);
require_capability('mod/workshep:overridegrades', $PAGE->context);

$calibration = $workshep->calibration_instance();
$settingsform = $calibration->get_settings_form($PAGE->url);

if ($settingsdata = $settingsform->get_data()) {
    // BASE-5468: Update the record with the changed Comparison / Consistency settings
    $workshepsettings = array('id' => $workshep->id,
                              'calibrationcomparison' => $settingsdata->comparison,
                              'calibrationconsistency' => $settingsdata->consistency);
    $DB->update_record('workshep', $workshepsettings);

    $calibration->calculate_calibration_scores($settingsdata);   // updates 'gradinggrade' in {workshep_assessments}
    $context = context_module::instance($cm->id); // BASE-5360.

    // BASE-5468: Replaced legacy logging process when updating calibration was updated.

    $params = array(
        'relateduserid' => null,
        'objectid' => $workshep->id,
        'context' => $workshep->context,
        'courseid' => $course->id,
        'other' => array(
            'workshepid' => $workshep->id,
            'submissionid' => null
        )
    );

    $event = \mod_workshep\event\update_calibration_scores::create($params); // BASE-5468
    $event->add_record_snapshot('workshep', $workshep);
    $event->trigger();
}

redirect(new moodle_url($workshep->view_url(), array('page' => $page, 'sortby' => $sortby, 'sorthow' => $sorthow)));

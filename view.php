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
 * Prints a particular instance of workshep
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = optional_param('id', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // workshep instance ID
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$generate   = optional_param('generate', 0, PARAM_INT); // BASE-5468.
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);

if ($id) {
    $cm             = get_coursemodule_from_id('workshep', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $worksheprecord = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $worksheprecord = $DB->get_record('workshep', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $worksheprecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('workshep', $worksheprecord->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('mod/workshep:view', $PAGE->context);

$workshep = new workshep($worksheprecord, $cm, $course);

$PAGE->set_url($workshep->view_url());

// Mark viewed.
$workshep->set_module_viewed();

// If the phase is to be switched, do it asap. This just has to happen after triggering
// the event so that the scheduled allocator had a chance to allocate submissions.
if ($workshep->phase == workshep::PHASE_SUBMISSION and $workshep->phaseswitchassessment
        and $workshep->submissionend > 0 and $workshep->submissionend < time()) {
    $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
    // Disable the automatic switching now so that it is not executed again by accident
    // if the teacher changes the phase back to the submission one.
    $DB->set_field('workshep', 'phaseswitchassessment', 0, array('id' => $workshep->id));
    $workshep->phaseswitchassessment = 0;
}

if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}
$workshep->init_initial_bar();
$userplan = new workshep_user_plan($workshep, $USER->id);

foreach ($userplan->phases as $phase) {
    if ($phase->active) {
        $currentphasetitle = $phase->title;
    }
}

$PAGE->set_title($workshep->name . " (" . $currentphasetitle . ")");
$PAGE->set_heading($course->fullname);
$PAGE->requires->js(new moodle_url('/mod/workshep/view.js'));

if ($perpage and $perpage > 0 and $perpage <= 1000) {
    require_sesskey();
    set_user_preference('workshep_perpage', $perpage);
    redirect($PAGE->url);
}

if ($eval) {
    require_sesskey();
    require_capability('mod/workshep:overridegrades', $workshep->context);
    $workshep->set_grading_evaluation_method($eval);
    redirect($PAGE->url);
}

$heading = $OUTPUT->heading_with_help(format_string($workshep->name), 'userplan', 'workshep');
$heading = preg_replace('/<h2[^>]*>([.\s\S]*)<\/h2>/', '$1', $heading);
$PAGE->activityheader->set_attrs([
    'title' => $PAGE->activityheader->is_title_allowed() ? $heading : "",
    'description' => ''
]);

$output = $PAGE->get_renderer('mod_workshep');

echo $output->header(); // BASE-5075.

echo $output->view_page($workshep, $userplan, $currentphasetitle, $page, $sortby, $sorthow, $generate); // BASE-5468.

$PAGE->requires->js_call_amd('mod_workshep/workshepview', 'init');

// Team Evaluation. We always need to see it, so it lives outside the massive switch().
$teameval_plugin = core_plugin_manager::instance()->get_plugin_info('local_teameval');
if ($teameval_plugin) {
    $teameval_renderer = $PAGE->get_renderer('local_teameval');
    $teameval = \local_teameval\output\team_evaluation_block::from_cmid($cm->id);
    echo $teameval_renderer->render($teameval);
}

echo $output->footer();

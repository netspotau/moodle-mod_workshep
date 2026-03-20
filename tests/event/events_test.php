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
 * Unit tests for workshep events.
 *
 * @package    mod_workshep
 * @category   phpunit
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_workshep\event;

use testable_workshep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/workshep/lib.php'); // Include the code to test.
require_once($CFG->dirroot . '/mod/workshep/locallib.php'); // Include the code to test.
require_once($CFG->dirroot . '/mod/workshep/tests/fixtures/testable.php'); // BASE-4539.


/**
 * Test cases for the internal workshep api
 */
final class events_test extends \advanced_testcase {

    /** @var \stdClass $workshep Basic workshep data stored in an object. */
    protected $workshep;
    /** @var \stdClass $course Generated Random Course. */
    protected $course;
    /** @var stdClass mod info */
    protected $cm;
    /** @var context $context Course module context. */
    protected $context;

    /**
     * Set up the testing environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->setAdminUser();

        // Create a workshep activity.
        $this->course = $this->getDataGenerator()->create_course();
        $this->workshep = $this->getDataGenerator()->create_module('workshep', array('course' => $this->course));
        $this->cm = get_coursemodule_from_instance('workshep', $this->workshep->id);
        $this->context = \context_module::instance($this->cm->id);
    }

    protected function tearDown(): void {
        $this->workshep = null;
        $this->course = null;
        $this->cm = null;
        $this->context = null;
        parent::tearDown();
    }

    /**
     * This event is triggered in view.php and workshep/lib.php through the function workshep_cron().
     */
    public function test_phase_switched_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Add additional workshep information.
        $this->workshep->phase = 20;
        $this->workshep->phaseswitchassessment = 1;
        $this->workshep->submissionend = time() - 1;

        $cm = get_coursemodule_from_instance('workshep', $this->workshep->id, $this->course->id, false, MUST_EXIST);
        $workshep = new testable_workshep($this->workshep, $cm, $this->course);

        // The phase that we are switching to.
        $newphase = 30;
        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $workshep->switch_phase($newphase);
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertEventContextNotUsed($event);

        $sink->close();
    }

    public function test_assessment_evaluated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cm = get_coursemodule_from_instance('workshep', $this->workshep->id, $this->course->id, false, MUST_EXIST);

        $workshep = new testable_workshep($this->workshep, $cm, $this->course);

        $assessments = array();
        $assessments[] = (object)array('reviewerid' => 2, 'gradinggrade' => null,
            'gradinggradeover' => null, 'aggregationid' => null, 'aggregatedgrade' => 12);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $workshep->aggregate_grading_grades_process($assessments);
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertInstanceOf('\mod_workshep\event\assessment_evaluated', $event);
        $this->assertEquals('workshep_aggregations', $event->objecttable);
        $this->assertEquals(\context_module::instance($cm->id), $event->get_context());
        $this->assertEventContextNotUsed($event);

        $sink->close();
    }

    public function test_assessment_reevaluated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cm = get_coursemodule_from_instance('workshep', $this->workshep->id, $this->course->id, false, MUST_EXIST);

        $workshep = new testable_workshep($this->workshep, $cm, $this->course);

        $assessments = array();
        $assessments[] = (object)array('reviewerid' => 2, 'gradinggrade' => null, 'gradinggradeover' => null,
            'aggregationid' => 2, 'aggregatedgrade' => 12);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $workshep->aggregate_grading_grades_process($assessments);
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertInstanceOf('\mod_workshep\event\assessment_reevaluated', $event);
        $this->assertEquals('workshep_aggregations', $event->objecttable);
        $this->assertEquals(\context_module::instance($cm->id), $event->get_context());
        $this->assertEventContextNotUsed($event);

        $sink->close();
    }

    /**
     * There is no api involved so the best we can do is test legacy data by triggering event manually.
     */
    public function test_aggregate_grades_reset_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $event = \mod_workshep\event\assessment_evaluations_reset::create(array(
            'context'  => $this->context,
            'courseid' => $this->course->id,
            'other' => array('workshepid' => $this->workshep->id)
        ));

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        $sink->close();
    }

    /**
     * There is no api involved so the best we can do is test legacy data by triggering event manually.
     */
    public function test_instances_list_viewed_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $context = \context_course::instance($this->course->id);

        $event = \mod_workshep\event\course_module_instance_list_viewed::create(array('context' => $context));

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertEventContextNotUsed($event);

        $sink->close();
    }

    /**
     * There is no api involved so the best we can do is test legacy data by triggering event manually.
     */
    public function test_submission_created_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $submissionid = 48;

        $event = \mod_workshep\event\submission_created::create(array(
                'objectid'      => $submissionid,
                'context'       => $this->context,
                'courseid'      => $this->course->id,
                'relateduserid' => $user->id,
                'other'         => array(
                    'submissiontitle' => 'The submission title'
                )
            )
        );

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertEventContextNotUsed($event);

        $sink->close();
    }
}

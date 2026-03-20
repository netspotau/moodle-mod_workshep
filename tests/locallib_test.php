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
 * Unit tests for workshep api class defined in mod/workshep/locallib.php
 *
 * @package    mod_workshep
 * @category   phpunit
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_workshep;

use testable_workshep;
use workshep;
use workshep_example_assessment;
use workshep_example_reference_assessment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/workshep/locallib.php'); // Include the code to test
require_once(__DIR__ . '/fixtures/testable.php');


/**
 * Test cases for the internal workshep api
 */
final class locallib_test extends \advanced_testcase {

    /** @var object */
    protected $course;

    /** @var workshep */
    protected $workshep;

    /** setup testing environment */
    protected function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $workshep = $this->getDataGenerator()->create_module('workshep', array('course' => $this->course));
        $cm = get_coursemodule_from_instance('workshep', $workshep->id, $this->course->id, false, MUST_EXIST);
        $this->workshep = new testable_workshep($workshep, $cm, $this->course);
    }

    protected function tearDown(): void {
        $this->workshep = null;
        parent::tearDown();
    }

    public function test_aggregate_submission_grades_process_notgraded(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => null);
        //$DB->expectNever('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_single(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.12345);
        $expected = 10.12345;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_null_doesnt_influence(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => 45.54321);
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => null);
        $expected = 45.54321;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_weighted_single(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 4, 'grade' => 14.00012);
        $expected = 14.00012;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 0.00000);
        $expected = 19.67750;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean_changed(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 0.00000);
        $expected = 19.67750;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean_nochange(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 0.00000);
        //$DB->expectNever('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_rounding(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 4.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 2.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 1.00000);
        $expected = 2.33333;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_weighted_mean(): void {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 3, 'grade' => 12.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 2, 'grade' => 30.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 0, 'grade' => 1000.00000);
        $expected = 17.66667;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->workshep->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_nograding(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>2, 'gradinggrade'=>null, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        //$DB->expectNever('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_new(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>82.87670, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        $now = time();
        $expected = new \stdClass();
        $expected->workshepid = $this->workshep->id;
        $expected->userid = 3;
        $expected->gradinggrade = 82.87670;
        $expected->timegraded = $now;
        //$DB->expectOnce('insert_record', array('workshep_aggregations', $expected));
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch, $now);
    }

    public function test_aggregate_grading_grades_process_single_grade_update(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>90.00000, 'gradinggradeover'=>null, 'aggregationid'=>1, 'aggregatedgrade'=>82.87670);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_uptodate(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>90.00000, 'gradinggradeover'=>null, 'aggregationid'=>1, 'aggregatedgrade'=>90.00000);
        // expectation
        //$DB->expectNever('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_overridden(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>4, 'gradinggrade'=>91.56700, 'gradinggradeover'=>82.32105, 'aggregationid'=>2, 'aggregatedgrade'=>91.56700);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_new(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>99.45670, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        $now = time();
        $expected = new \stdClass();
        $expected->workshepid = $this->workshep->id;
        $expected->userid = 5;
        $expected->gradinggrade = 79.3066;
        $expected->timegraded = $now;
        //$DB->expectOnce('insert_record', array('workshep_aggregations', $expected));
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch, $now);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_update(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>56.23400, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_overriden(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>56.23400, 'gradinggradeover'=>99.45670, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_one_missing(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>50.00000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>null, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>52.20000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_missing_overridden(): void {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>50.00000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>null, 'gradinggradeover'=>69.00000, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>52.20000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->workshep->aggregate_grading_grades_process($batch);
    }

    public function test_percent_to_value(): void {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = 56.6543;
        // exercise SUT
        $part = workshep::percent_to_value($percent, $total);
        // verify
        $this->assertEquals($part, $total * $percent / 100);
    }

    public function test_percent_to_value_negative(): void {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = -7.098;
        // exercise SUT
        $this->expectException(\coding_exception::class);
        $part = workshep::percent_to_value($percent, $total);
    }

    public function test_percent_to_value_over_hundred(): void {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = 121.08;
        // exercise SUT
        $this->expectException(\coding_exception::class);
        $part = workshep::percent_to_value($percent, $total);
    }

    public function test_lcm(): void {
        $this->resetAfterTest(true);
        // fixture setup + exercise SUT + verify in one step
        $this->assertEquals(workshep::lcm(1,4), 4);
        $this->assertEquals(workshep::lcm(2,4), 4);
        $this->assertEquals(workshep::lcm(4,2), 4);
        $this->assertEquals(workshep::lcm(2,3), 6);
        $this->assertEquals(workshep::lcm(6,4), 12);
    }

    public function test_lcm_array(): void {
        $this->resetAfterTest(true);
        // fixture setup
        $numbers = array(5,3,15);
        // excersise SUT
        $lcm = array_reduce($numbers, 'workshep::lcm', 1);
        // verify
        $this->assertEquals($lcm, 15);
    }

    public function test_prepare_example_assessment(): void {
        $this->resetAfterTest(true);
        // fixture setup
        $fakerawrecord = (object)array(
            'id'                => 42,
            'submissionid'      => 56,
            'weight'            => 0,
            'timecreated'       => time() - 10,
            'timemodified'      => time() - 5,
            'grade'             => null,
            'gradinggrade'      => null,
            'gradinggradeover'  => null,
            'feedbackauthor'    => null,
            'feedbackauthorformat' => 0,
            'feedbackauthorattachment' => 0,
        );
        // excersise SUT
        $a = $this->workshep->prepare_example_assessment($fakerawrecord);
        // verify
        $this->assertTrue($a instanceof workshep_example_assessment);
        $this->assertTrue($a->url instanceof \moodle_url);

        // modify setup
        $fakerawrecord->weight = 1;
        $this->expectException('coding_exception');
        // excersise SUT
        $a = $this->workshep->prepare_example_assessment($fakerawrecord);
    }

    public function test_prepare_example_reference_assessment(): void {
        global $USER;
        $this->resetAfterTest(true);
        // fixture setup
        $fakerawrecord = (object)array(
            'id'                => 38,
            'submissionid'      => 56,
            'weight'            => 1,
            'timecreated'       => time() - 100,
            'timemodified'      => time() - 50,
            'grade'             => 0.75000,
            'gradinggrade'      => 1.00000,
            'gradinggradeover'  => null,
            'feedbackauthor'    => null,
            'feedbackauthorformat' => 0,
            'feedbackauthorattachment' => 0,
        );
        // excersise SUT
        $a = $this->workshep->prepare_example_reference_assessment($fakerawrecord);
        // verify
        $this->assertTrue($a instanceof workshep_example_reference_assessment);

        // modify setup
        $fakerawrecord->weight = 0;
        $this->expectException('coding_exception');
        // excersise SUT
        $a = $this->workshep->prepare_example_reference_assessment($fakerawrecord);
    }

    /**
     * Test normalizing list of extensions.
     */
    public function test_normalize_file_extensions(): void {
        $this->resetAfterTest(true);

        workshep::normalize_file_extensions('');
        $this->assertDebuggingCalled();
    }

    /**
     * Test cleaning list of extensions.
     */
    public function test_clean_file_extensions(): void {
        $this->resetAfterTest(true);

        workshep::clean_file_extensions('');
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * Test validation of the list of file extensions.
     */
    public function test_invalid_file_extensions(): void {
        $this->resetAfterTest(true);

        workshep::invalid_file_extensions('', '');
        $this->assertDebuggingCalledCount(3);
    }

    /**
     * Test checking file name against the list of allowed extensions.
     */
    public function test_is_allowed_file_type(): void {
        $this->resetAfterTest(true);

        workshep::is_allowed_file_type('', '');
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * Test workshep::check_group_membership() functionality.
     */
    public function test_check_group_membership(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        $courseid = $this->course->id;
        $generator = $this->getDataGenerator();

        // Make test groups.
        $group1 = $generator->create_group(array('courseid' => $courseid));
        $group2 = $generator->create_group(array('courseid' => $courseid));
        $group3 = $generator->create_group(array('courseid' => $courseid));

        // Revoke the accessallgroups from non-editing teachers (tutors).
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        unassign_capability('moodle/site:accessallgroups', $roleids['teacher']);

        // Create test use accounts.
        $teacher1 = $generator->create_user();
        $tutor1 = $generator->create_user();
        $tutor2 = $generator->create_user();
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();

        // Enrol the teacher (has the access all groups permission).
        $generator->enrol_user($teacher1->id, $courseid, $roleids['editingteacher']);

        // Enrol tutors (can not access all groups).
        $generator->enrol_user($tutor1->id, $courseid, $roleids['teacher']);
        $generator->enrol_user($tutor2->id, $courseid, $roleids['teacher']);

        // Enrol students.
        $generator->enrol_user($student1->id, $courseid, $roleids['student']);
        $generator->enrol_user($student2->id, $courseid, $roleids['student']);
        $generator->enrol_user($student3->id, $courseid, $roleids['student']);

        // Add users in groups.
        groups_add_member($group1, $tutor1);
        groups_add_member($group2, $tutor2);
        groups_add_member($group1, $student1);
        groups_add_member($group2, $student2);
        groups_add_member($group3, $student3);

        // Workshep with no groups.
        $workshepitem1 = $this->getDataGenerator()->create_module('workshep', [
            'course' => $courseid,
            'groupmode' => NOGROUPS,
        ]);
        $cm = get_coursemodule_from_instance('workshep', $workshepitem1->id, $courseid, false, MUST_EXIST);
        $workshep1 = new testable_workshep($workshepitem1, $cm, $this->course);

        $this->setUser($teacher1);
        $this->assertTrue($workshep1->check_group_membership($student1->id));
        $this->assertTrue($workshep1->check_group_membership($student2->id));
        $this->assertTrue($workshep1->check_group_membership($student3->id));

        $this->setUser($tutor1);
        $this->assertTrue($workshep1->check_group_membership($student1->id));
        $this->assertTrue($workshep1->check_group_membership($student2->id));
        $this->assertTrue($workshep1->check_group_membership($student3->id));

        // Workshep in visible groups mode.
        $workshepitem2 = $this->getDataGenerator()->create_module('workshep', [
            'course' => $courseid,
            'groupmode' => VISIBLEGROUPS,
        ]);
        $cm = get_coursemodule_from_instance('workshep', $workshepitem2->id, $courseid, false, MUST_EXIST);
        $workshep2 = new testable_workshep($workshepitem2, $cm, $this->course);

        $this->setUser($teacher1);
        $this->assertTrue($workshep2->check_group_membership($student1->id));
        $this->assertTrue($workshep2->check_group_membership($student2->id));
        $this->assertTrue($workshep2->check_group_membership($student3->id));

        $this->setUser($tutor1);
        $this->assertTrue($workshep2->check_group_membership($student1->id));
        $this->assertTrue($workshep2->check_group_membership($student2->id));
        $this->assertTrue($workshep2->check_group_membership($student3->id));

        // Workshep in separate groups mode.
        $workshepitem3 = $this->getDataGenerator()->create_module('workshep', [
            'course' => $courseid,
            'groupmode' => SEPARATEGROUPS,
        ]);
        $cm = get_coursemodule_from_instance('workshep', $workshepitem3->id, $courseid, false, MUST_EXIST);
        $workshep3 = new testable_workshep($workshepitem3, $cm, $this->course);

        $this->setUser($teacher1);
        $this->assertTrue($workshep3->check_group_membership($student1->id));
        $this->assertTrue($workshep3->check_group_membership($student2->id));
        $this->assertTrue($workshep3->check_group_membership($student3->id));

        $this->setUser($tutor1);
        $this->assertTrue($workshep3->check_group_membership($student1->id));
        $this->assertFalse($workshep3->check_group_membership($student2->id));
        $this->assertFalse($workshep3->check_group_membership($student3->id));

        $this->setUser($tutor2);
        $this->assertFalse($workshep3->check_group_membership($student1->id));
        $this->assertTrue($workshep3->check_group_membership($student2->id));
        $this->assertFalse($workshep3->check_group_membership($student3->id));
    }

    /**
     * Test init_initial_bar function.
     *
     * @covers \workshep::init_initial_bar
     */
    public function test_init_initial_bar(): void {
        global $SESSION;
        $this->resetAfterTest();

        $_GET['ifirst'] = 'A';
        $_GET['ilast'] = 'B';
        $contextid = $this->workshep->context->id;

        $this->workshep->init_initial_bar();
        $initialbarprefs = $this->get_initial_bar_prefs_property();

        $this->assertEquals('A', $initialbarprefs['i_first']);
        $this->assertEquals('B', $initialbarprefs['i_last']);
        $this->assertEquals('A', $SESSION->mod_workshep->initialbarprefs['id-' . $contextid]['i_first']);
        $this->assertEquals('B', $SESSION->mod_workshep->initialbarprefs['id-' . $contextid]['i_last']);

        $_GET['ifirst'] = null;
        $_GET['ilast'] = null;
        $SESSION->mod_workshep->initialbarprefs['id-' . $contextid]['i_first'] = 'D';
        $SESSION->mod_workshep->initialbarprefs['id-' . $contextid]['i_last'] = 'E';

        $this->workshep->init_initial_bar();
        $initialbarprefs = $this->get_initial_bar_prefs_property();

        $this->assertEquals('D', $initialbarprefs['i_first']);
        $this->assertEquals('E', $initialbarprefs['i_last']);
    }

    /**
     * Test empty init_initial_bar
     *
     * @covers \workshep::init_initial_bar
     */
    public function test_init_initial_bar_empty(): void {
        $this->resetAfterTest();

        $this->workshep->init_initial_bar();
        $initialbarprefs = $this->get_initial_bar_prefs_property();

        $this->assertEmpty($initialbarprefs);
    }

    /**
     * Test get_initial_first function
     *
     * @covers \workshep::get_initial_first
     */
    public function test_get_initial_first(): void {
        $this->resetAfterTest();
        $this->workshep->init_initial_bar();
        $this->assertEquals(null, $this->workshep->get_initial_first());

        $_GET['ifirst'] = 'D';
        $this->workshep->init_initial_bar();
        $this->assertEquals('D', $this->workshep->get_initial_first());
    }

    /**
     * Test get_initial_last function
     *
     * @covers \workshep::get_initial_last
     */
    public function test_get_initial_last(): void {
        $this->resetAfterTest();
        $this->workshep->init_initial_bar();
        $this->assertEquals(null, $this->workshep->get_initial_last());

        $_GET['ilast'] = 'D';
        $this->workshep->init_initial_bar();
        $this->assertEquals('D', $this->workshep->get_initial_last());
    }

    /**
     * Get the protected propertyinitialbarprefs from workshep class.
     *
     * @coversNothing
     * @return array initialbarspref property. eg ['i_first' => 'A', 'i_last' => 'B']
     */
    private function get_initial_bar_prefs_property(): array {

        $reflector = new \ReflectionObject($this->workshep);
        $initialbarprefsprop = $reflector->getProperty('initialbarprefs');
        $initialbarprefs = $initialbarprefsprop->getValue($this->workshep);

        return $initialbarprefs;
    }
}

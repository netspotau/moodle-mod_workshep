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
 * Provides the {@see mod_workshep\privacy\provider_test} class.
 *
 * @package     mod_workshep
 * @category    test
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_workshep\privacy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Unit tests for the privacy API implementation.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {

    /** @var testing_data_generator */
    protected $generator;

    /** @var mod_workshep_generator */
    protected $workshepgenerator;

    /** @var stdClass */
    protected $course1;

    /** @var stdClass */
    protected $course2;

    /** @var stdClass */
    protected $student1;

    /** @var stdClass */
    protected $student2;

    /** @var stdClass */
    protected $student3;

    /** @var stdClass */
    protected $teacher4;

    /** @var stdClass first workshep in course1 */
    protected $workshep11;

    /** @var stdClass second workshep in course1 */
    protected $workshep12;

    /** @var stdClass first workshep in course2 */
    protected $workshep21;

    /** @var int ID of the submission in workshep11 by student1 */
    protected $submission111;

    /** @var int ID of the submission in workshep12 by student1 */
    protected $submission121;

    /** @var int ID of the submission in workshep12 by student2 */
    protected $submission122;

    /** @var int ID of the submission in workshep21 by student2 */
    protected $submission212;

    /** @var int ID of the assessment of submission111 by student1 */
    protected $assessment1111;

    /** @var int ID of the assessment of submission111 by student2 */
    protected $assessment1112;

    /** @var int ID of the assessment of submission111 by student3 */
    protected $assessment1113;

    /** @var int ID of the assessment of submission121 by student2 */
    protected $assessment1212;

    /** @var int ID of the assessment of submission212 by student1 */
    protected $assessment2121;

    /**
     * Set up the test environment.
     *
     * course1
     *  |
     *  +--workshep11 (first digit matches the course, second is incremental)
     *  |   |
     *  |   +--submission111 (first two digits match the workshep, last one matches the author)
     *  |       |
     *  |       +--assessment1111 (first three digits match the submission, last one matches the reviewer)
     *  |       +--assessment1112
     *  |       +--assessment1113
     *  |
     *  +--workshep12
     *      |
     *      +--submission121
     *      |   |
     *      |   +--assessment1212
     *      |
     *      +--submission122
     *
     *  etc.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->generator = $this->getDataGenerator();
        $this->workshepgenerator = $this->generator->get_plugin_generator('mod_workshep');

        $this->course1 = $this->generator->create_course();
        $this->course2 = $this->generator->create_course();

        $this->workshep11 = $this->generator->create_module('workshep', [
            'course' => $this->course1,
            'name' => 'Workshop11',
        ]);
        $DB->set_field('workshep', 'phase', 50, ['id' => $this->workshep11->id]);

        $this->workshep12 = $this->generator->create_module('workshep', ['course' => $this->course1]);
        $this->workshep21 = $this->generator->create_module('workshep', ['course' => $this->course2]);

        $this->student1 = $this->generator->create_user();
        $this->student2 = $this->generator->create_user();
        $this->student3 = $this->generator->create_user();
        $this->teacher4 = $this->generator->create_user();

        $this->submission111 = $this->workshepgenerator->create_submission($this->workshep11->id, $this->student1->id);
        $this->submission121 = $this->workshepgenerator->create_submission($this->workshep12->id, $this->student1->id,
            ['gradeoverby' => $this->teacher4->id]);
        $this->submission122 = $this->workshepgenerator->create_submission($this->workshep12->id, $this->student2->id);
        $this->submission212 = $this->workshepgenerator->create_submission($this->workshep21->id, $this->student2->id);

        $this->assessment1111 = $this->workshepgenerator->create_assessment($this->submission111, $this->student1->id, [
            'grade' => null,
        ]);
        $this->assessment1112 = $this->workshepgenerator->create_assessment($this->submission111, $this->student2->id, [
            'grade' => 92,
        ]);
        $this->assessment1113 = $this->workshepgenerator->create_assessment($this->submission111, $this->student3->id);

        $this->assessment1212 = $this->workshepgenerator->create_assessment($this->submission121, $this->student2->id, [
            'feedbackauthor' => 'This is what student 2 thinks about submission 121',
            'feedbackreviewer' => 'This is what the teacher thinks about this assessment',
        ]);

        $this->assessment2121 = $this->workshepgenerator->create_assessment($this->submission212, $this->student1->id, [
            'grade' => 68,
            'gradinggradeover' => 80,
            'gradinggradeoverby' => $this->teacher4->id,
            'feedbackauthor' => 'This is what student 1 thinks about submission 212',
            'feedbackreviewer' => 'This is what the teacher thinks about this assessment',
        ]);
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::get_contexts_for_userid()} implementation.
     */
    public function test_get_contexts_for_userid(): void {

        $cm11 = get_coursemodule_from_instance('workshep', $this->workshep11->id);
        $cm12 = get_coursemodule_from_instance('workshep', $this->workshep12->id);
        $cm21 = get_coursemodule_from_instance('workshep', $this->workshep21->id);

        $context11 = \context_module::instance($cm11->id);
        $context12 = \context_module::instance($cm12->id);
        $context21 = \context_module::instance($cm21->id);

        // Student1 has data in workshep11 (author + self reviewer), workshep12 (author) and workshep21 (reviewer).
        $contextlist = \mod_workshep\privacy\provider::get_contexts_for_userid($this->student1->id);
        $this->assertInstanceOf(\core_privacy\local\request\contextlist::class, $contextlist);
        $this->assertEqualsCanonicalizing([$context11->id, $context12->id, $context21->id], $contextlist->get_contextids());

        // Student2 has data in workshep11 (reviewer), workshep12 (reviewer) and workshep21 (author).
        $contextlist = \mod_workshep\privacy\provider::get_contexts_for_userid($this->student2->id);
        $this->assertEqualsCanonicalizing([$context11->id, $context12->id, $context21->id], $contextlist->get_contextids());

        // Student3 has data in workshep11 (reviewer).
        $contextlist = \mod_workshep\privacy\provider::get_contexts_for_userid($this->student3->id);
        $this->assertEqualsCanonicalizing([$context11->id], $contextlist->get_contextids());

        // Teacher4 has data in workshep12 (gradeoverby) and workshep21 (gradinggradeoverby).
        $contextlist = \mod_workshep\privacy\provider::get_contexts_for_userid($this->teacher4->id);
        $this->assertEqualsCanonicalizing([$context21->id, $context12->id], $contextlist->get_contextids());
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::get_users_in_context()} implementation.
     */
    public function test_get_users_in_context(): void {

        $cm11 = get_coursemodule_from_instance('workshep', $this->workshep11->id);
        $cm12 = get_coursemodule_from_instance('workshep', $this->workshep12->id);
        $cm21 = get_coursemodule_from_instance('workshep', $this->workshep21->id);

        $context11 = \context_module::instance($cm11->id);
        $context12 = \context_module::instance($cm12->id);
        $context21 = \context_module::instance($cm21->id);

        // Users in the workshep11.
        $userlist11 = new \core_privacy\local\request\userlist($context11, 'mod_workshep');
        \mod_workshep\privacy\provider::get_users_in_context($userlist11);
        $expected11 = [
            $this->student1->id, // Student1 has data in workshep11 (author + self reviewer).
            $this->student2->id, // Student2 has data in workshep11 (reviewer).
            $this->student3->id, // Student3 has data in workshep11 (reviewer).
        ];
        $actual11 = $userlist11->get_userids();
        $this->assertEqualsCanonicalizing($expected11, $actual11);

        // Users in the workshep12.
        $userlist12 = new \core_privacy\local\request\userlist($context12, 'mod_workshep');
        \mod_workshep\privacy\provider::get_users_in_context($userlist12);
        $expected12 = [
            $this->student1->id, // Student1 has data in workshep12 (author).
            $this->student2->id, // Student2 has data in workshep12 (reviewer).
            $this->teacher4->id, // Teacher4 has data in workshep12 (gradeoverby).
        ];
        $actual12 = $userlist12->get_userids();
        $this->assertEqualsCanonicalizing($expected12, $actual12);

        // Users in the workshep21.
        $userlist21 = new \core_privacy\local\request\userlist($context21, 'mod_workshep');
        \mod_workshep\privacy\provider::get_users_in_context($userlist21);
        $expected21 = [
            $this->student1->id, // Student1 has data in workshep21 (reviewer).
            $this->student2->id, // Student2 has data in workshep21 (author).
            $this->teacher4->id, // Teacher4 has data in workshep21 (gradinggradeoverby).
        ];
        $actual21 = $userlist21->get_userids();
        $this->assertEqualsCanonicalizing($expected21, $actual21);
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::export_user_data()} implementation.
     */
    public function test_export_user_data_1(): void {

        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student1, 'mod_workshep', [
            \context_module::instance($this->workshep11->cmid)->id,
            \context_module::instance($this->workshep12->cmid)->id,
        ]);

        \mod_workshep\privacy\provider::export_user_data($contextlist);

        $writer = writer::with_context(\context_module::instance($this->workshep11->cmid));

        $workshep = $writer->get_data([]);
        $this->assertEquals('Workshop11', $workshep->name);
        $this->assertObjectHasProperty('phase', $workshep);

        $mysubmission = $writer->get_data([
            get_string('mysubmission', 'mod_workshep'),
        ]);

        $mysubmissionselfassessmentwithoutgrade = $writer->get_data([
            get_string('mysubmission', 'mod_workshep'),
            get_string('assessments', 'mod_workshep'),
            $this->assessment1111,
        ]);
        $this->assertNull($mysubmissionselfassessmentwithoutgrade->grade);
        $this->assertEquals(get_string('yes'), $mysubmissionselfassessmentwithoutgrade->selfassessment);

        $mysubmissionassessmentwithgrade = $writer->get_data([
            get_string('mysubmission', 'mod_workshep'),
            get_string('assessments', 'mod_workshep'),
            $this->assessment1112,
        ]);
        $this->assertEquals(92, $mysubmissionassessmentwithgrade->grade);
        $this->assertEquals(get_string('no'), $mysubmissionassessmentwithgrade->selfassessment);

        $mysubmissionassessmentwithoutgrade = $writer->get_data([
            get_string('mysubmission', 'mod_workshep'),
            get_string('assessments', 'mod_workshep'),
            $this->assessment1113,
        ]);
        $this->assertEquals(null, $mysubmissionassessmentwithoutgrade->grade);
        $this->assertEquals(get_string('no'), $mysubmissionassessmentwithoutgrade->selfassessment);

        $myassessments = $writer->get_data([
            get_string('myassessments', 'mod_workshep'),
        ]);
        $this->assertEmpty($myassessments);
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::export_user_data()} implementation.
     */
    public function test_export_user_data_2(): void {

        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student2, 'mod_workshep', [
            \context_module::instance($this->workshep11->cmid)->id,
        ]);

        \mod_workshep\privacy\provider::export_user_data($contextlist);

        $writer = writer::with_context(\context_module::instance($this->workshep11->cmid));

        $assessedsubmission = $writer->get_related_data([
            get_string('myassessments', 'mod_workshep'),
            $this->assessment1112,
        ], 'submission');
        $this->assertEquals(get_string('no'), $assessedsubmission->myownsubmission);
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::delete_data_for_all_users_in_context()} implementation.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->assertTrue($DB->record_exists('workshep_submissions', ['workshepid' => $this->workshep11->id]));

        // Passing a non-module context does nothing.
        \mod_workshep\privacy\provider::delete_data_for_all_users_in_context(\context_course::instance($this->course1->id));
        $this->assertTrue($DB->record_exists('workshep_submissions', ['workshepid' => $this->workshep11->id]));

        // Passing a workshep context removes all data.
        \mod_workshep\privacy\provider::delete_data_for_all_users_in_context(\context_module::instance($this->workshep11->cmid));
        $this->assertFalse($DB->record_exists('workshep_submissions', ['workshepid' => $this->workshep11->id]));
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::delete_data_for_user()} implementation.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $student1submissions = $DB->get_records('workshep_submissions', [
            'workshepid' => $this->workshep12->id,
            'authorid' => $this->student1->id,
        ]);

        $student2submissions = $DB->get_records('workshep_submissions', [
            'workshepid' => $this->workshep12->id,
            'authorid' => $this->student2->id,
        ]);

        $this->assertNotEmpty($student1submissions);
        $this->assertNotEmpty($student2submissions);

        foreach ($student1submissions as $submission) {
            $this->assertNotEquals(get_string('privacy:request:delete:title', 'mod_workshep'), $submission->title);
        }

        foreach ($student2submissions as $submission) {
            $this->assertNotEquals(get_string('privacy:request:delete:title', 'mod_workshep'), $submission->title);
        }

        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student1, 'mod_workshep', [
            \context_module::instance($this->workshep12->cmid)->id,
            \context_module::instance($this->workshep21->cmid)->id,
        ]);

        \mod_workshep\privacy\provider::delete_data_for_user($contextlist);

        $student1submissions = $DB->get_records('workshep_submissions', [
            'workshepid' => $this->workshep12->id,
            'authorid' => $this->student1->id,
        ]);

        $student2submissions = $DB->get_records('workshep_submissions', [
            'workshepid' => $this->workshep12->id,
            'authorid' => $this->student2->id,
        ]);

        $this->assertNotEmpty($student1submissions);
        $this->assertNotEmpty($student2submissions);

        foreach ($student1submissions as $submission) {
            $this->assertEquals(get_string('privacy:request:delete:title', 'mod_workshep'), $submission->title);
        }

        foreach ($student2submissions as $submission) {
            $this->assertNotEquals(get_string('privacy:request:delete:title', 'mod_workshep'), $submission->title);
        }

        $student1assessments = $DB->get_records('workshep_assessments', [
            'submissionid' => $this->submission212,
            'reviewerid' => $this->student1->id,
        ]);
        $this->assertNotEmpty($student1assessments);

        foreach ($student1assessments as $assessment) {
            // In Moodle, feedback is seen to belong to the recipient user.
            $this->assertNotEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackauthor);
            $this->assertEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackreviewer);
            // We delete what we can without affecting others' grades.
            $this->assertEquals(68, $assessment->grade);
        }

        $assessments = $DB->get_records_list('workshep_assessments', 'submissionid', array_keys($student1submissions));
        $this->assertNotEmpty($assessments);

        foreach ($assessments as $assessment) {
            if ($assessment->reviewerid == $this->student1->id) {
                $this->assertNotEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackauthor);
                $this->assertNotEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackreviewer);

            } else {
                $this->assertEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackauthor);
                $this->assertNotEquals(get_string('privacy:request:delete:content', 'mod_workshep'), $assessment->feedbackreviewer);
            }
        }
    }

    /**
     * Test {@link \mod_workshep\privacy\provider::delete_data_for_users()} implementation.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        // Student1 has submissions in two worksheps.
        $this->assertFalse($this->is_submission_erased($this->submission111));
        $this->assertFalse($this->is_submission_erased($this->submission121));

        // Student1 has self-assessed one their submission.
        $this->assertFalse($this->is_given_assessment_erased($this->assessment1111));
        $this->assertFalse($this->is_received_assessment_erased($this->assessment1111));

        // Student2 and student3 peer-assessed student1's submission.
        $this->assertFalse($this->is_given_assessment_erased($this->assessment1112));
        $this->assertFalse($this->is_given_assessment_erased($this->assessment1113));

        // Delete data owned by student1 and student3 in the workshep11.

        $context11 = \context_module::instance($this->workshep11->cmid);

        $approveduserlist = new \core_privacy\local\request\approved_userlist($context11, 'mod_workshep', [
            $this->student1->id,
            $this->student3->id,
        ]);
        \mod_workshep\privacy\provider::delete_data_for_users($approveduserlist);

        // Student1's submission is erased in workshep11 but not in the other workshep12.
        $this->assertTrue($this->is_submission_erased($this->submission111));
        $this->assertFalse($this->is_submission_erased($this->submission121));

        // Student1's self-assessment is erased.
        $this->assertTrue($this->is_given_assessment_erased($this->assessment1111));
        $this->assertTrue($this->is_received_assessment_erased($this->assessment1111));

        // Student1's received peer-assessments are also erased because they are "owned" by the recipient of the assessment.
        $this->assertTrue($this->is_received_assessment_erased($this->assessment1112));
        $this->assertTrue($this->is_received_assessment_erased($this->assessment1113));

        // Student2's owned data in the given assessment are not erased.
        $this->assertFalse($this->is_given_assessment_erased($this->assessment1112));

        // Student3's owned data in the given assessment were erased because she/he was in the userlist.
        $this->assertTrue($this->is_given_assessment_erased($this->assessment1113));

        // Personal data in other contexts are not affected.
        $this->assertFalse($this->is_submission_erased($this->submission121));
        $this->assertFalse($this->is_given_assessment_erased($this->assessment2121));
        $this->assertFalse($this->is_received_assessment_erased($this->assessment2121));
    }

    /**
     * Check if the given submission has the author's personal data erased.
     *
     * @param int $submissionid Identifier of the submission.
     * @return boolean
     */
    protected function is_submission_erased(int $submissionid) {
        global $DB;

        $submission = $DB->get_record('workshep_submissions', ['id' => $submissionid], 'id, title, content', MUST_EXIST);

        $titledeleted = $submission->title === get_string('privacy:request:delete:title', 'mod_workshep');
        $contentdeleted = $submission->content === get_string('privacy:request:delete:content', 'mod_workshep');

        if ($titledeleted && $contentdeleted) {
            return true;

        } else {
            return false;
        }
    }

    /**
     * Check is the received assessment has recipient's (author's) personal data erased.
     *
     * @param int $assessmentid Identifier of the assessment.
     * @return boolean
     */
    protected function is_received_assessment_erased(int $assessmentid) {
        global $DB;

        $assessment = $DB->get_record('workshep_assessments', ['id' => $assessmentid], 'id, feedbackauthor', MUST_EXIST);

        if ($assessment->feedbackauthor === get_string('privacy:request:delete:content', 'mod_workshep')) {
            return true;

        } else {
            return false;
        }
    }

    /**
     * Check is the given assessment has reviewer's personal data erased.
     *
     * @param int $assessmentid Identifier of the assessment.
     * @return boolean
     */
    protected function is_given_assessment_erased(int $assessmentid) {
        global $DB;

        $assessment = $DB->get_record('workshep_assessments', ['id' => $assessmentid], 'id, feedbackreviewer', MUST_EXIST);

        if ($assessment->feedbackreviewer === get_string('privacy:request:delete:content', 'mod_workshep')) {
            return true;

        } else {
            return false;
        }
    }
}

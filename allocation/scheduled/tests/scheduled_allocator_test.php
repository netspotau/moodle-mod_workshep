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

namespace workshepallocation_scheduled;

/**
 * Test for the scheduled allocator.
 *
 * @package workshepallocation_scheduled
 * @copyright 2020 Jaume I University <https://www.uji.es/>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scheduled_allocator_test extends \advanced_testcase {

    /** @var \stdClass $course The course where the tests will be run */
    private $course;

    /** @var \workshep $workshep The workshep where the tests will be run */
    private $workshep;

    /** @var \stdClass $workshepcm The workshep course module instance */
    private $workshepcm;

    /** @var \stdClass[] $students An array of student enrolled in $course */
    private $students;

    /**
     * Tests that student submissions get automatically alocated after the submission deadline and when the workshep
     * "Switch to the next phase after the submissions deadline" checkbox is active.
     */
    public function test_that_allocator_in_executed_on_submission_end_when_phaseswitchassessment_is_active(): void {
        global $DB;

        $this->resetAfterTest();

        $this->setup_test_course_and_workshep();

        $this->activate_switch_to_the_next_phase_after_submission_deadline();
        $this->set_the_submission_deadline_in_the_past();
        $this->activate_the_scheduled_allocator();

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');

        \core\cron::setup_user();

        // Let the students add submissions.
        $this->workshep->switch_phase(\workshep::PHASE_SUBMISSION);

        // Create some submissions.
        foreach ($this->students as $student) {
            $workshepgenerator->create_submission($this->workshep->id, $student->id);
        }

        // No allocations yet.
        $this->assertEmpty($this->workshep->get_allocations());

        /* Execute the tasks that will do the transition and allocation thing.
         * We expect the workshep cron to do the whole work: change the phase and
         * allocate the submissions.
         */
        $this->execute_workshep_cron_task();

        $workshepdb = $DB->get_record('workshep', ['id' => $this->workshep->id]);
        $workshep = new \workshep($workshepdb, $this->workshepcm, $this->course);

        $this->assertEquals(\workshep::PHASE_ASSESSMENT, $workshep->phase);
        $this->assertNotEmpty($workshep->get_allocations());
    }

    /**
     * No allocations are performed if the allocator is not enabled.
     */
    public function test_that_allocator_is_not_executed_when_its_not_active(): void {
        global $DB;

        $this->resetAfterTest();

        $this->setup_test_course_and_workshep();
        $this->activate_switch_to_the_next_phase_after_submission_deadline();
        $this->set_the_submission_deadline_in_the_past();

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');

        \core\cron::setup_user();

        // Let the students add submissions.
        $this->workshep->switch_phase(\workshep::PHASE_SUBMISSION);

        // Create some submissions.
        foreach ($this->students as $student) {
            $workshepgenerator->create_submission($this->workshep->id, $student->id);
        }

        // No allocations yet.
        $this->assertEmpty($this->workshep->get_allocations());

        // Transition to the assessment phase.
        $this->execute_workshep_cron_task();

        $workshepdb = $DB->get_record('workshep', ['id' => $this->workshep->id]);
        $workshep = new \workshep($workshepdb, $this->workshepcm, $this->course);

        // No allocations too.
        $this->assertEquals(\workshep::PHASE_ASSESSMENT, $workshep->phase);
        $this->assertEmpty($workshep->get_allocations());
    }

    /**
     * Activates and configures the scheduled allocator for the workshep.
     */
    private function activate_the_scheduled_allocator(): void {

        $settings = \workshep_random_allocator_setting::instance_from_object((object)[
            'numofreviews' => count($this->students),
            'numper' => 1,
            'removecurrentuser' => true,
            'excludesamegroup' => false,
            'assesswosubmission' => true,
            'addselfassessment' => false
        ]);

        $allocator = new \workshep_scheduled_allocator($this->workshep);

        $storesettingsmethod = new \ReflectionMethod('workshep_scheduled_allocator', 'store_settings');
        $storesettingsmethod->invoke($allocator, true, true, $settings, new \workshep_allocation_result($allocator));
    }

    /**
     * Creates a minimum common setup to execute tests:
     */
    protected function setup_test_course_and_workshep(): void {
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();

        $this->course = $datagenerator->create_course();

        $this->students = [];
        for ($i = 0; $i < 10; $i++) {
            $this->students[] = $datagenerator->create_and_enrol($this->course);
        }

        $workshepdb = $datagenerator->create_module('workshep', [
            'course' => $this->course,
            'name' => 'Test Workshop',
        ]);
        $this->workshepcm = get_coursemodule_from_instance('workshep', $workshepdb->id, $this->course->id, false, MUST_EXIST);
        $this->workshep = new \workshep($workshepdb, $this->workshepcm, $this->course);
    }

    /**
     * Executes the workshep cron task.
     */
    protected function execute_workshep_cron_task(): void {
        ob_start();
        $cron = new \mod_workshep\task\cron_task();
        $cron->execute();
        ob_end_clean();
    }

    /**
     * Executes the scheduled allocator cron task.
     */
    protected function execute_allocator_cron_task(): void {
        ob_start();
        $cron = new \workshepallocation_scheduled\task\cron_task();
        $cron->execute();
        ob_end_clean();
    }

    /**
     * Activates the "Switch to the next phase after the submissions deadline" flag in the workshep.
     */
    protected function activate_switch_to_the_next_phase_after_submission_deadline(): void {
        global $DB;
        $DB->set_field('workshep', 'phaseswitchassessment', 1, ['id' => $this->workshep->id]);
    }

    /**
     * Sets the submission deadline in a past time.
     */
    protected function set_the_submission_deadline_in_the_past(): void {
        global $DB;
        $DB->set_field('workshep', 'submissionend', time() - 1, ['id' => $this->workshep->id]);
    }
}

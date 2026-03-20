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
 * A scheduled task for workshep cron.
 *
 * @package    mod_workshep
 * @copyright  2019 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_workshep\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The main scheduled task for the workshep.
 *
 * @package   mod_workshep
 * @copyright 2019 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_workshep');
    }

    /**
     * Run workshep cron.
     */
    public function execute() {
        global $CFG, $DB;

        $now = time();

        mtrace(' processing workshep subplugins ...');

        // Check if there are some worksheps to switch into the assessment phase.
        $worksheps = $DB->get_records_select("workshep",
            "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", [$now]);

        if (!empty($worksheps)) {
            mtrace('Processing automatic assessment phase switch in ' . count($worksheps) . ' workshep(s) ... ', '');
            require_once($CFG->dirroot . '/mod/workshep/locallib.php');
            foreach ($worksheps as $workshep) {
                $cm = get_coursemodule_from_instance('workshep', $workshep->id, $workshep->course, false, MUST_EXIST);
                $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
                $workshep = new \workshep($workshep, $cm, $course);
                $workshep->switch_phase(\workshep::PHASE_ASSESSMENT);

                $params = [
                    'objectid' => $workshep->id,
                    'context' => $workshep->context,
                    'courseid' => $workshep->course->id,
                    'other' => [
                        'targetworkshepphase' => $workshep->phase,
                        'previousworkshepphase' => \workshep::PHASE_SUBMISSION,
                    ]
                ];
                $event = \mod_workshep\event\phase_automatically_switched::create($params);
                $event->trigger();

                // Disable the automatic switching now so that it is not executed again by accident.
                // That can happen if the teacher changes the phase back to the submission one.
                $DB->set_field('workshep', 'phaseswitchassessment', 0, ['id' => $workshep->id]);
            }
            mtrace('done');
        }
    }
}

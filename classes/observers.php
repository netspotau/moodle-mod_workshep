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
 * @package   mod_workshep
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace mod_workshep;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/workshep/locallib.php');

class observers {

    /**
     * Observes when a group member is deleted. Move first group member the Workshep submission. BASE-5468.
     *
     * @param \core\event\group_member_removed $event The event.
     * @return void
     */
    public static function team_group_member_removed(\core\event\group_member_removed $event) {
        global $DB;

        if (empty($event->objectid) && empty($event->objecttable) && $event->objecttable != 'groups' &&
                $event->target != 'group_member' && $event->action != 'removed') {
            return;
        }
        // Get users in this group. Is similar to 'groups_get_members' function but
        // returns less information which is what we need.
        $usersingroup = search_users($event->courseid, $event->objectid, '');
        if ($usersingroup) {
            $course = $DB->get_record('course', array('id' => $event->courseid));
            // Get all workshep submissions from this user for this course.
            $sql = "SELECT ws.*
                      FROM {workshep_submissions} ws
                      JOIN {workshep} w ON w.id = ws.workshepid AND w.course = ?
                     WHERE ws.authorid = ?";// BASE-5468.
            $wssubmissionsbyuser = $DB->get_records_sql($sql, [$event->courseid, $event->relateduserid]);// BASE-5468.
            $allwsbycourse = $DB->get_records('workshep', ['course' => $event->courseid]);
            // Validate if there are submissions uploaded by this user.
            if ($wssubmissionsbyuser) {
                // Update all Worksheps which this user has uploaded a sumbmission.
                foreach ($wssubmissionsbyuser as $wssumbmission) {
                    $cm = get_coursemodule_from_instance('workshep', $wssumbmission->workshepid, $event->courseid);
                    // BASE-5468: Reset value.
                    $ws = '';
                    // Get each workshep.
                    foreach ($allwsbycourse as $wskey => $wsbycourse) {
                        if ($wsbycourse->id == $wssumbmission->workshepid) {
                            $ws = $wsbycourse;
                            unset($allwsbycourse[$wskey]);
                            break;
                        }
                    }
                    // BASE-5468: If found a sumbmission that belongs to this user.
                    if ($ws) {
                        if (!$ws->teammode) {
                            continue;
                        }
                        $workshep = '';
                        $workshep = new \workshep($ws, $cm, $course);
                        // Check if we can assign the sumbmission to another group member.
                        $potentialauthors = $workshep->get_potential_authors(false, $event->objectid);
                        // Check if other users from this group can upload a submission to this activity.
                        if ($potentialauthors) {
                            $isvaliduser = '';
                            foreach ($potentialauthors[$event->objectid] as $potentialauthor) {
                                $isvaliduser = $workshep->user_group($potentialauthor->id);
                                if ($isvaliduser) {
                                    // Store userid.
                                    $isvaliduser = $potentialauthor->id;
                                    // Stop since we already have an user.
                                    break;
                                }
                            }
                            if ($isvaliduser) {
                                // Change sumbmission authorid.
                                $wssumbmission->authorid = $isvaliduser;
                                // Store the updated value.
                                $DB->update_record('workshep_submissions', $wssumbmission);
                                // Event information.
                                $params = array(
                                    'context' => $workshep->context,
                                    'courseid' => $event->courseid,
                                    'other' => array(
                                        'submissiontitle' => $wssumbmission->title
                                    )
                                );
                                $params['objectid'] = $wssumbmission->id;
                                // Trigger the event to record that it was a user event removed from the group,
                                // since the userid in the log table shows that it was not the same user who edited the submission.
                                $updateevent = \mod_workshep\event\submission_updated::create($params);
                                $updateevent->add_record_snapshot('workshep', $workshep->dbrecord);
                                $updateevent->trigger();
                            }
                        }
                    }
                }
            }
        }
    }
}

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

namespace mod_workshep\courseformat;

use cm_info;
use core_calendar\output\humandate;
use core_courseformat\local\overview\overviewitem;
use core\output\action_link;
use core\output\local\properties\text_align;
use core\output\local\properties\button;
use core\url;
use stdClass;
use workshep;

/**
 * Workshep overview integration.
 *
 * @package    mod_workshep
 * @copyright  2025 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    /** @var workshep $workshep the workshep instance. */
    private workshep $workshep;

    /** @var stdClass $activephase the active phase. */
    private stdClass $activephase;

    /**
     * Constructor.
     *
     * @param cm_info $cm the course module instance.
     */
    public function __construct(
        cm_info $cm,
    ) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/workshep/locallib.php');

        parent::__construct($cm);
        $this->workshep = new workshep(
            $cm->get_instance_record(),
            $cm,
            $this->course,
            $this->context,
        );

        $userplan = new \workshep_user_plan($this->workshep, $USER->id);
        foreach ($userplan->phases as $phase) {
            if ($phase->active) {
                $this->activephase = $phase;
            }
        }
    }

    #[\Override]
    protected function get_grade_item_names(array $items): array {
        if (count($items) != 2) {
            return parent::get_grade_item_names($items);
        }
        $names = [];
        foreach ($items as $item) {
            $stridentifier = ($item->itemnumber == 0) ? 'overview_submission_grade' : 'overview_assessment_grade';
            $names[$item->id] = get_string($stridentifier, 'mod_workshep');
        }
        return $names;
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'phase' => $this->get_extra_phase_overview(),
            'deadline' => $this->get_extra_deadline_overview(),
            'submissions' => $this->get_extra_submissions_overview(),
            'assessments' => $this->get_extra_assessments_overview(),
        ];
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {

        if (
            !has_capability('mod/workshep:viewallsubmissions', $this->cm->context)
            && !has_capability('mod/workshep:viewallassessments', $this->cm->context)
        ) {
            // Students do not have any actions.
            return null;
        }

        $anchor = null;
        if ($this->workshep->phase == workshep::PHASE_SUBMISSION) {
            $anchor = 'workshep-viewlet-allsubmissions';
        } else if ($this->workshep->phase == workshep::PHASE_ASSESSMENT) {
            $anchor = 'workshep-viewlet-gradereport';
        }

        $content = new action_link(
            url: new url(
                '/mod/workshep/view.php',
                ['id' => $this->cm->id],
                $anchor,
            ),
            text: get_string('view', 'core'),
            attributes: ['class' => button::BODY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('actions', 'core'),
            value: get_string('view', 'core'),
            content: $content,
            textalign: text_align::CENTER,
        );
    }

    /**
     * Get the current phase overview item.
     *
     * @return overviewitem|null An overview item, or null if the user lacks the required capability.
     */
    private function get_extra_phase_overview(): ?overviewitem {
        $currentphasetitle = '-';
        if ($this->activephase) {
            $currentphasetitle = $this->activephase->title;
        }
        return new overviewitem(
            name: get_string('phase', 'workshep'),
            value: $this->workshep->phase,
            content: $currentphasetitle,
        );
    }

    /**
     * Retrieves an overview of the deadline for the workshep.
     *
     * @return overviewitem|null An overview item, or null if the current phase does not have a deadline.
     */
    private function get_extra_deadline_overview(): ?overviewitem {
        $deadline = match ((int)$this->workshep->phase) {
            workshep::PHASE_SUBMISSION => $this->workshep->submissionend ?? 0,
            workshep::PHASE_ASSESSMENT => $this->workshep->assessmentend ?? 0,
            default => 0,
        };

        if (empty($deadline)) {
            return new overviewitem(
                name: get_string('deadline', 'workshep'),
                value: null,
                content: '-',
            );
        }

        return new overviewitem(
            name: get_string('deadline', 'workshep'),
            value: (int) $deadline,
            content: humandate::create_from_timestamp($deadline),
        );
    }

    /**
     * Retrieves an overview of submissions for the workshep.
     *
     * @return overviewitem|null An overview item, or null if the user lacks the required capability.
     */
    private function get_extra_submissions_overview(): ?overviewitem {
        if (!has_capability('mod/workshep:viewallsubmissions', $this->cm->context)) {
            return null;
        }

        $groups = array_map(fn($group) => $group->id, $this->get_groups_for_filtering());

        $submissions = $this->workshep->count_all_submissions(groupids: $groups);
        $total = $this->workshep->count_all_participants(groupids: $groups);

        if (!$total) {
            return new overviewitem(
                name: get_string('submissions', 'workshep'),
                value: 0,
                content: '-',
                textalign: text_align::END,
            );
        }

        $content = get_string(
            'count_of_total',
            'core',
            ['count' => $submissions, 'total' => $total]
        );

        return new overviewitem(
            name: get_string('submissions', 'workshep'),
            value: $submissions,
            content: $content,
            textalign: text_align::END,
        );
    }

    /**
     * Retrieves an overview of assessments for the workshep.
     *
     * @return overviewitem|null An overview item, or null if the user lacks the required capability.
     */
    private function get_extra_assessments_overview(): ?overviewitem {
        if (!has_capability('mod/workshep:viewallassessments', $this->cm->context)) {
            return null;
        }

        $groups = array_map(fn($group) => $group->id, $this->get_groups_for_filtering());

        $assessments = $this->workshep->count_all_assessments(true, $groups);
        $total = $this->workshep->count_all_assessments(false, $groups);

        if (!$total) {
            return new overviewitem(
                name: get_string('assessments', 'workshep'),
                value: 0,
                content: '-',
                textalign: text_align::END,
            );
        }

        $content = get_string(
            'count_of_total',
            'core',
            ['count' => $assessments, 'total' => $total]
        );

        return new overviewitem(
            name: get_string('assessments', 'workshep'),
            value: $assessments,
            content: $content,
            textalign: text_align::END,
        );
    }
}

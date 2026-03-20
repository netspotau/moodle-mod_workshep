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
 * Provides the {@see workshepallocation_manual\privacy\provider_test} class.
 *
 * @package     workshepallocation_manual
 * @category    test
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace workshepallocation_manual\privacy;

use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the privacy API implementation.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * When no preference exists, there should be no export.
     */
    public function test_no_preference(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        \workshepallocation_manual\privacy\provider::export_user_preferences($USER->id);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Test that the recently selected perpage is exported.
     */
    public function test_export_preferences(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        set_user_preference('workshepallocation_manual_perpage', 81);

        \workshepallocation_manual\privacy\provider::export_user_preferences($USER->id);
        $this->assertTrue(writer::with_context(\context_system::instance())->has_any_data());

        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('workshepallocation_manual');
        $this->assertNotEmpty($prefs->workshepallocation_manual_perpage);
        $this->assertEquals(81, $prefs->workshepallocation_manual_perpage->value);
        $this->assertStringContainsString(get_string('privacy:metadata:preference:perpage', 'workshepallocation_manual'),
            $prefs->workshepallocation_manual_perpage->description);
    }
}

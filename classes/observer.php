<?php
// This file is part of "Moodle SGA Integration"
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Local stuff for category enrolment plugin.
 *
 * @package    tool_sga
 * @copyright  2025 kelson Medeiros {@link https://github.com/kelsoncm}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


class tool_sga_observer
{
    public static function user_enrolment_created(\core\event\user_enrolment_created $event)
    {
        global $DB;
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event)
    {
        global $DB;
    }

    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event)
    {
        global $DB;
    }
}

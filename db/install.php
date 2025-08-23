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

/**\
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     tool_sga
 * @category    upgrade
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/admin/tool/sga/db/upgrade.php');
require_once(__DIR__ . '/upgradelib.php');

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_tool_sga_install()
{
    sga_bulk_course_custom_field();
    sga_bulk_user_custom_field();
    return tool_sga_migrate(0);
}

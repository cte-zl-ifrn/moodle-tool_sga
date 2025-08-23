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
 * Events
 *
 * @package   tool_sga
 * @copyright 2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga\event;

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'tool_sga_observer::user_enrolment_created',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'tool_sga_observer::user_enrolment_deleted',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'tool_sga_observer::user_enrolment_updated',
    ]
];

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
 * SGA Integration
 *
 * @package     tool_sga
 * @category    tool
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga\admin;

require_once(\dirname(\dirname(\dirname(__DIR__))) . '/config.php');

$PAGE->set_url(new \moodle_url('/admin/tool/sga/admin/view.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('SGA Sync Admin :: View');

if (!is_siteadmin()) {
    echo $OUTPUT->header();
    echo "Fazes o quê aqui?";
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();
$linha = $DB->get_record("sga_enrolment_to_sync", ['id' => $_GET['id']]);
$statuses = [0 => "Não processado", 1 => "Sucesso", 2 => 'Falha'];
$linha->status = $statuses[$linha->processed];
$templatecontext = ['linha' => $linha];
echo $OUTPUT->render_from_template('tool_sga/view', $templatecontext);
echo $OUTPUT->footer();

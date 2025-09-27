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
 * This module provides extensive analytics on a platform of choice
 * Currently support Google Analytics and Piwik
 *
 * @package     tool_sga
 * @category    upgrade
 * @copyright   2020 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../../../config.php');
require_once("../locallib.php");
require_once("servicelib.php");

// Link de acesso (exemplo): http://ava/admin/tool/sga/api/sync_down_grades.php?codigo_diario=20231.1.15806.1E.TEC.1386

class sync_down_grades_service extends service
{

    function do_call()
    {
        global $CFG, $DB;
        $notes_to_sync = config('notes_to_sync') ?: "'N1', 'N2', 'N3', 'N4', 'NAF'";
        try {
            $notas = $DB->get_records_sql("
                WITH a AS (
                    SELECT  ra.userid                        AS id_usuario,
                            u.username                       AS matricula,
                            u.firstname || ' ' || u.lastname AS nome_completo,
                            u.email                          AS email,
                            c.id                             AS id_curso
                    FROM     {course} AS c
                                INNER JOIN {context} AS ctx ON (c.id=ctx.instanceid AND ctx.contextlevel=50)
                                    INNER JOIN {role_assignments} AS ra ON (ctx.id=ra.contextid)
                                        INNER JOIN {role} AS r ON (ra.roleid=r.id AND r.archetype='student')
                                        INNER JOIN {user} AS u ON (ra.userid=u.id)
                    WHERE    C.idnumber LIKE '%#' || ?
                )
                SELECT   a.matricula, a.nome_completo,
                        (
                                SELECT   jsonb_object_agg(gi.idnumber::text, gg.finalgrade)
                                FROM     {grade_items} gi
                                            inner join {grade_grades} gg on (gg.itemid=gi.id AND gg.userid = a.id_usuario)
                                WHERE    gi.idnumber IN ($notes_to_sync)
                                AND    gi.courseid = a.id_curso
                        ) notas
                FROM     a
                ORDER BY a.nome_completo           
            ", [$_GET['diario_id']]);
            $result = array_values($notas);
            foreach ($result as $key => $aluno) {
                if ($aluno->notas != null) {
                    $aluno->notas = json_decode($aluno->notas);
                }
            }
            return $result;
        } catch (Exception $ex) {
            die("error");
            http_response_code(500);
            if ($ex->getMessage() == "Data submitted is invalid (value: Data submitted is invalid)") {
                echo json_encode(["error" => ["message" => "Ocorreu uma inconsistência no servidor do AVA. Este erro é conhecido e a solução dele já está sendo estudado pela equipe de desenvolvimento. Favor tentar novamente em 5 minutos."]]);
            } else {
                echo json_encode(["error" => ["message" => $ex->getMessage()]]);
            }
        }
    }
}

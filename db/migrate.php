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
 * Plugin upgrade helper functions are defined here.
 *
 * @package     tool_sga
 * @category    upgrade
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see         https://docs.moodle.org/dev/Data_definition_API
 * @see         https://docs.moodle.org/dev/XMLDB_creating_new_DDL_functions
 * @see         https://docs.moodle.org/dev/Upgrade_API
 */
namespace tool_sga;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/sga/locallib.php');

function get_languages() {
    $languages = \get_string_manager()->get_list_of_translations();
    $options = array_keys($languages);
    return implode("\n", $options);
}


function sga_save_course_custom_category($name) {
    global $DB;

    return \tool_sga\get_or_create(
        'customfield_category',
        ['name' => $name, 'component' => 'core_course', 'area' => 'course'],
        ['sortorder' => \tool_sga\get_last_sort_order('customfield_category'), 'itemid' => 0, 'contextid' => 1, 'descriptionformat' => 0, 'timecreated' => time(), 'timemodified' => time()]
    )->id;
}


function sga_save_course_custom_field($categoryid, $shortname, $name, $type = 'text', $configdata = '{"required":"0","uniquevalues":"0","displaysize":50,"maxlength":250,"ispassword":"0","link":"","locked":"0","visibility":"0"}')
{
    return \tool_sga\get_or_create(
        'customfield_field',
        ['shortname' => $shortname],
        ['categoryid' => $categoryid, 'name' => $name, 'type' => $type, 'configdata' => $configdata, 'timecreated' => time(), 'timemodified' => time(), 'sortorder' => \tool_sga\get_last_sort_order('customfield_field')]
    );
}


function sga_save_user_custom_field($categoryid, $shortname, $name, $datatype = 'text', $visible = 1, $p1 = NULL, $p2 = NULL)
{
    return \tool_sga\get_or_create(
        'user_info_field',
        ['shortname' => $shortname],
        ['categoryid' => $categoryid, 'name' => $name, 'description' => $name, 'descriptionformat' => 2, 'datatype' => $datatype, 'visible' => $visible, 'param1' => $p1, 'param2' => $p2]
    );
}


function sga_bulk_course_custom_field()
{
    global $DB;
    $campus = sga_save_course_custom_category('Campus');
    sga_save_course_custom_field($campus, 'campus_id', 'ID do campus');
    sga_save_course_custom_field($campus, 'campus_sigla', 'Sigla do campus');
    sga_save_course_custom_field($campus, 'campus_descricao', 'Descrição do campus');

    $curso = sga_save_course_custom_category('Curso');
    sga_save_course_custom_field($curso, 'curso_id', 'ID do curso');
    sga_save_course_custom_field($curso, 'curso_codigo', 'Código do curso');
    sga_save_course_custom_field($curso, 'curso_nome', 'Nome do curso');
    sga_save_course_custom_field($curso, 'curso_descricao', 'Descrição do curso');
    sga_save_course_custom_field($curso, 'curso_descricao_historico', 'Descrição do curso que constará no histórico');
    sga_save_course_custom_field($curso, 'curso_titulo_certificado_masculino', 'Título do certificado masculino');
    sga_save_course_custom_field($curso, 'curso_titulo_certificado_feminino', 'Título do certificado feminino');
    sga_save_course_custom_field($curso, 'curso_ch_total', 'Carga horária total do curso');
    sga_save_course_custom_field($curso, 'curso_ch_aula', 'Carga horária da aula');
    sga_save_course_custom_field($curso, 'curso_autoinstrucional', 'Curso é autoinstrucional', 'checkbox');
    sga_save_course_custom_field($curso, 'curso_programa', 'Programa do curso');
    sga_save_course_custom_field($curso, 'curso_modalidade_id', 'ID da modalidade do curso');
    sga_save_course_custom_field($curso, 'curso_modalidade_descricao', 'Descrição da modalidade do curso');
    sga_save_course_custom_field($curso, 'curso_nivel_ensino_id', 'ID do nível de ensino do curso');
    sga_save_course_custom_field($curso, 'curso_nivel_ensino_descricao', 'Descrição do nível de ensino do curso');
    sga_save_course_custom_field($curso, 'curso_conteudo', 'Conteúdo do curso');
    sga_save_course_custom_field($curso, 'curso_restricoes', 'Restrições do curso');
    sga_save_course_custom_field($curso, 'curso_sala_coordenacao', 'É sala de coordenação do curso');

    $componente = sga_save_course_custom_category('Disciplina/Componente curricular');
    sga_save_course_custom_field($componente, 'disciplina_id', 'ID da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_tipo', 'Tipo da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_sigla', 'Sigla da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_descricao', 'Descrição da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_descricao_historico', 'Descrição da disciplina que constará no histórico');
    sga_save_course_custom_field($componente, 'disciplina_periodo', 'Período da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_optativo', 'Optativo da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_qtd_avaliacoes', 'Quantidade de avaliações da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_is_seminario_estagio_docente', 'É disciplina de seminário ou estágio docente', 'checkbox');
    sga_save_course_custom_field($componente, 'disciplina_ch_presencial', 'Carga horária presencial da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_pratica', 'Carga horária prática da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_extensao', 'Carga horária de extensão da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_pcc', 'Carga horária de PCC da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_visita_tecnica', 'Carga horária de visita técnica da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_semanal_1s', 'Carga horária semanal do 1º semestre da disciplina');
    sga_save_course_custom_field($componente, 'disciplina_ch_semanal_2s', 'Carga horária semanal do 2º semestre da disciplina');

    $turma = sga_save_course_custom_category('Turma');
    sga_save_course_custom_field($turma, 'turma_id', 'ID da turma');
    sga_save_course_custom_field($turma, 'turma_codigo', 'Código da turma');
    sga_save_course_custom_field($turma, 'turma_ano_periodo', 'Ano/Semestre da turma');
    sga_save_course_custom_field($turma, 'turma_data_inicio', 'Data de início da turma');
    sga_save_course_custom_field($turma, 'turma_data_fim', 'Data de fim da turma');
    sga_save_course_custom_field($turma, 'turma_gerar_matricula', 'Gerar matrícula na turma', 'checkbox');
    sga_save_course_custom_field($turma, 'turma_nota_minima', 'Nota mínima da turma');
    sga_save_course_custom_field($turma, 'turma_completude_minima', 'Completude mínima da turma');
    sga_save_course_custom_field($turma, 'turma_modelo_padrao', 'Modelo padrão da turma');

    $diario = sga_save_course_custom_category('Diário');
    sga_save_course_custom_field($diario, 'diario_id', 'ID do diário');
    sga_save_course_custom_field($diario, 'diario_tipo', 'Tipo de diário');
    sga_save_course_custom_field($diario, 'diario_situacao', 'Situação do diário');
    sga_save_course_custom_field($diario, 'diario_descricao', 'Descrição do diário');
    sga_save_course_custom_field($diario, 'diario_descricao_historico', 'Descrição do diário que constará no histórico');

    $aberto = sga_save_course_custom_category('Aberto');
    $linguagens = json_encode([
        "required" => "0",
        "uniquevalues" => "0",
        "options" => \local_suap\get_languages(),
        "defaultvalue" => "pt_br",
        "locked" => "0",
        "visibility" => "2"
    ]);
    sga_save_course_custom_field($aberto, 'carga_horaria', 'Carga horária');
    sga_save_course_custom_field($aberto, 'tem_certificado', 'Tem certificado', 'checkbox');
    sga_save_course_custom_field($aberto, 'linguagem_conteudo', 'Linguagem do conteúdo', 'select', $linguagens);

    $integrador_ava = sga_save_course_custom_category('Integrador AVA');
    sga_save_course_custom_field($integrador_ava, 'grupos_sincronizados', 'Grupos sincronizados pelo Integrador AVA');

    $painel_ava = sga_save_course_custom_category('Painel AVA');
    sga_save_course_custom_field($painel_ava, 'curso_autoinscricao', 'Curso aceita autoinscrição', 'checkbox');
    sga_save_course_custom_field($painel_ava, 'sala_tipo', 'Tipo de sala');
    // $sql = "       SELECT 'diarios'        AS id, 'Diários'        AS data"
    //      . " UNION SELECT 'autoinscricoes' AS id, 'Autoinscrições' AS data"
    //      . " UNION SELECT 'coordenacoes'   AS id, 'Coordenações'   AS data"
    //      . " UNION SELECT 'praticas'       AS id, 'Práticas'       AS data"
    //      . " UNION SELECT 'modelos'        AS id, 'Modelos'        AS data";    
    // $configdata = json_encode([
    //     "required" => "0",
    //     "uniquevalues" => "0",
    //     "dynamicsql" => $sql,
    //     "autocomplete" => "0",
    //     "defaultvalue" => "",
    //     "multiselect" => "0",
    //     "locked" => "1",
    //     "visibility" => "0"
    // ]);    
    // sga_save_course_custom_field($painel_ava, 'sala_tipo', 'Tipo de sala', 'dynamic', $configdata);
}


function sga_bulk_user_custom_field()
{
    global $DB;

    $cid = \tool_sga\get_or_create('user_info_category', ['name' => 'SGA'], ['sortorder' => \tool_sga\get_last_sort_order('user_info_category')])->id;

    sga_save_user_custom_field($cid, 'email_google_classroom', 'E-mail @escolar (Google Classroom)');
    sga_save_user_custom_field($cid, 'email_academico', 'E-mail @academico (Microsoft)');
    sga_save_user_custom_field($cid, 'email_secundario', 'Secundário (servidores)');

    sga_save_user_custom_field($cid, 'campus_id', 'ID do campus');
    sga_save_user_custom_field($cid, 'campus_descricao', 'Descrição do campus');
    sga_save_user_custom_field($cid, 'campus_sigla', 'Sigla do campus');

    sga_save_user_custom_field($cid, 'curso_id', 'ID do curso');
    sga_save_user_custom_field($cid, 'curso_codigo', 'Código do curso');
    sga_save_user_custom_field($cid, 'curso_descricao', 'Descrição do curso');

    sga_save_user_custom_field($cid, 'turma_id', 'ID da turma');
    sga_save_user_custom_field($cid, 'turma_codigo', 'Código da turma');

    sga_save_user_custom_field($cid, 'polo_id', 'ID do pólo');
    sga_save_user_custom_field($cid, 'polo_nome', 'Nome do pólo');

    sga_save_user_custom_field($cid, 'ingresso_periodo', 'Período de ingresso');

    sga_save_user_custom_field($cid, 'nome_apresentacao', 'Nome de apresentação');
    sga_save_user_custom_field($cid, 'nome_completo', 'Nome completo');
    sga_save_user_custom_field($cid, 'nome_social', 'Nome social');
    sga_save_user_custom_field($cid, 'tipo_usuario', 'Tipo de usuário');

    sga_save_user_custom_field($cid, 'programa_nome', 'Nome do programa');

    sga_save_user_custom_field($cid, 'last_login', 'JSON do último login', 'textarea', 0);
}

function tool_sga_migrate($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    $sga_enrolment_to_sync = new \xmldb_table("sga_enrolment_to_sync");
    if (!$dbman->table_exists($sga_enrolment_to_sync)) {
        $sga_enrolment_to_sync->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
        $sga_enrolment_to_sync->add_field("json",           XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
        $sga_enrolment_to_sync->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_enrolment_to_sync->add_field("processed",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

        $sga_enrolment_to_sync->add_key("primary",          XMLDB_KEY_PRIMARY,  ["id"],         null,       null);

        $dbman->create_table($sga_enrolment_to_sync);
    }

    $sga_learning_path = new \xmldb_table("sga_learning_path");
    if (!$dbman->table_exists($sga_learning_path)) {
        $sga_learning_path->add_field("id",                 XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
        $sga_learning_path->add_field("name",               XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("description",        XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
        $sga_learning_path->add_field("descriptionformat",  XMLDB_TYPE_INTEGER, '2',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("slug",               XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("timecreated",        XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("timemodified",       XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("visible",            XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path->add_field("sortorder",          XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

        $sga_learning_path->add_key("primary",              XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
        $dbman->create_table($sga_learning_path);
    }

    $sga_learning_path_course = new \xmldb_table("sga_learning_path_course");
    if (!$dbman->table_exists($sga_learning_path_course)) {
        $sga_learning_path_course->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
        $sga_learning_path_course->add_field("learningpathid", XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path_course->add_field("courseid",       XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path_course->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path_course->add_field("timemodified",   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path_course->add_field("visible",        XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
        $sga_learning_path_course->add_field("sortorder",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

        $sga_learning_path_course->add_key("primary",        XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
        $sga_learning_path_course->add_key("learningpathid", XMLDB_KEY_FOREIGN, ["learningpathid"], "sga_learning_path", ["id"]);
        $sga_learning_path_course->add_key("courseid",       XMLDB_KEY_FOREIGN, ["courseid"],       "course",            ["id"]);

        $dbman->create_table($sga_learning_path_course);
    }

    $table = new \xmldb_table('sga_relatorio_cursos_autoinstrucionais');
    if (!$dbman->table_exists($table)) {

        $table->add_field('id',                     XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('curso_nome',             XMLDB_TYPE_CHAR,    '255',  null, XMLDB_NOTNULL);
        $table->add_field('campus',                 XMLDB_TYPE_CHAR,    '255',  null, XMLDB_NOTNULL);
        $table->add_field('diario_tipo',            XMLDB_TYPE_CHAR,    '50',   null, XMLDB_NOTNULL);
        $table->add_field('quantidade_cursos',      XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '1');
        $table->add_field('total_enrolled',         XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('accessed',               XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('no_access',              XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('final_exam_takers',      XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('passed',                 XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('failed',                 XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('avg_grade',              XMLDB_TYPE_NUMBER,  '10,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('with_certificate',       XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('without_certificate',    XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('completed',              XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timegenerated',          XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL);

        $table->add_key('primary',                  XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('idx_curso_nome',         XMLDB_INDEX_NOTUNIQUE, ['curso_nome']);
        $table->add_index('idx_campus',             XMLDB_INDEX_NOTUNIQUE, ['campus']);
        $table->add_index('idx_diario_tipo',        XMLDB_INDEX_NOTUNIQUE, ['diario_tipo']);
        $table->add_index('idx_timegenerated',      XMLDB_INDEX_NOTUNIQUE, ['timegenerated']);

        $dbman->create_table($table);
    }

    $table = new \xmldb_table('sga_restricoes_autoinscricao');
    if (!$dbman->table_exists($table)) {

        $table->add_field('id',             XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid',       XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL);
        $table->add_field('chave',          XMLDB_TYPE_CHAR,    '255',  null, XMLDB_NOTNULL);
        $table->add_field('restricao',      XMLDB_TYPE_CHAR,    '255',  null, XMLDB_NOTNULL);
        $table->add_field('descricao',      XMLDB_TYPE_TEXT,    null,   null);
        $table->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL);

        $table->add_key('primary',          XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('idx_courseid',   XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('idx_chave',      XMLDB_INDEX_NOTUNIQUE, ['chave']);

        $dbman->create_table($table);
    }

    sga_bulk_course_custom_field();
    sga_bulk_user_custom_field();

    return true;
}

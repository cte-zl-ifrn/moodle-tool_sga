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

require_once('../../../../config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/admin/tool/sga/locallib.php');
require_once($CFG->dirroot . '/admin/tool/sga/classes/Jsv4/Validator.php');
require_once($CFG->dirroot . '/admin/tool/sga/api/servicelib.php');


class sync_up_enrolments_service extends service
{

    private $json;
    private $sgaIssuer;
    private $categories = [];
    private $urls = [];
    private $context;
    private $course;
    private $diarioIsNew = false;
    private $diario;
    private $coordenacao;
    private $isRoom;
    private $aluno_enrol;
    private $professor_enrol;
    private $tutor_enrol;
    private $docente_enrol;
    private $studentAuth;
    private $teacherAuth;
    private $assistantAuth;
    private $default_user_preferences;


    function do_call() {
        global $CFG;
        $prefix = "{$CFG->wwwroot}/course/view.php";
        $jsonstring = file_get_contents('php://input');

        $this->validate_json($jsonstring);
        $result = $this->process($jsonstring, false);
        $this->insertSyncDB($jsonstring);

        return $result;
    }


    function validate_json($jsonstring) {

        try {
            $this->json = json_decode($jsonstring);
            if (!$this->json) {
                throw new \Exception("Erro ao validar o JSON.");
            }
        } catch (\Exception $e) {
            throw new \Exception("Erro ao validar o JSON, favor corrigir.");
        }

        if (!is_object($this->json)) {
            throw new \Exception("JSON inválido, favor corrigir.");
        }

        /*
        $schema = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/sga/schemas/sync_up_enrolments.schema.json'));
        $validation = \Jsv4\Validator::validate($this->json, $schema);
        if (!\Jsv4\Validator::isValid($this->json, $schema)) {
            $errors = "";

            foreach ($validation->errors as $error) {
                $errors .= "{$error->message}";
            }
            throw new \Exception("Erro ao validar o JSON, favor corrigir." . $errors);
        }
        */
    }


    function process($jsonstring, $assync) {
        global $CFG;
        $prefix = "{$CFG->wwwroot}/course/view.php";

        $this->sync_categories();
        $this->sync_courses();
        if ($assync) {
            // $this->sync_users();
            // $this->sync_enrols();
            // $this->sync_groups();
            // $this->sync_cohorts();
        }

        return $this->urls;
    }

    function get_category_by_idnumber($idnumber) {
        global $DB;

        if (isset($this->categories[$idnumber])) {
            return $this->categories[$idnumber];
        }

        $category = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
        if ($category) {
            $this->categories[$idnumber] = $category;
            return $category;
        }
        return null;
    }

    function sync_categories() {
        global $DB;

        if (!isset($this->json->categories->list)) {
            return;
        }

        $i = 0;
        foreach ($this->json->categories->list as $category) {
            $i++;
            if (!isset($category->idnumber)) {
                throw new \Exception("A categoria #{$i} veio sem o atributo 'idnumber', favor corrigir.");
            }
            if (!isset($category->name)) {
                throw new \Exception("A categoria #{$i} veio sem o atributo 'name', favor corrigir.");
            }

            $category_on_db = $DB->get_record('course_categories', ['idnumber' => $category->idnumber]);
            if (empty($category_on_db)) {
                $data = [
                    'idnumber' => $category->idnumber,
                    'name' => $category->name,

                    'description' => isset($category->description) ? $category->description : null,
                    'descriptionformat' => isset($category->descriptionformat) ? $category->descriptionformat : 0,
                    'visible' => isset($category->visible) ? $category->visible : 1,
                    'theme' => isset($category->theme) ? $category->theme : '',
                    'timecreated' => time(),
                    'timemodified' => time()
                ];

                if (isset($category->parent_idnumber) && isset($this->categories[$category->parent_idnumber])) {
                    $parent = $DB->get_record('course_categories', ['idnumber' => $category->parent_idnumber]);
                    if (!empty($parent) && isset($parent->id)) {
                        $data['parent'] = $parent->id;
                    }
                }
                $category_on_db = \core_course_category::create($data);
                $this->categories[$category->idnumber] = $category_on_db;
            } elseif (isset($this->json->categories->update_fields)) {
                $update_fields = $this->json->categories->update_fields;
                $data = [];
                if (in_array('idnumber', $update_fields)) {
                    throw new Exception("Não é possível atualizar o 'idnumber'.", 1);
                }

                if (in_array('name', $update_fields)) {
                    $data['name'] = $category->name;
                }

                if (in_array('description', $update_fields) && $category->description) {
                    $data['description'] = $category->description;
                }

                if (in_array('descriptionformat', $update_fields) && isset($category->descriptionformat)) {
                    $data['descriptionformat'] = $category->descriptionformat;
                }

                if (in_array('visible', $update_fields) && isset($category->visible)) {
                    $data['visible'] = $category->visible;
                }

                if (in_array('theme', $update_fields) && isset($category->theme)) {
                    $data['theme'] = $category->theme;
                }

                if (in_array('parent_idnumber', $update_fields) && isset($this->categories[$category->parent_idnumber])) {
                    $parent = $DB->get_record('course_categories', ['idnumber' => $category->parent_idnumber]);
                    if (!empty($parent) && isset($parent->id)) {
                        $data['parent'] = $parent->id;
                    }
                }

                if (count($data) > 0) {
                    $category = \core_course_category::get($category_on_db->id);
                    $category->update($data);
                    $this->categories[$category->idnumber] = $category;
                }
            }
        }
    }


    function sync_courses() {
        global $DB, $CFG;
        $prefix = "{$CFG->wwwroot}/course/view.php";

        if (!isset($this->json->courses->list)) {
            return;
        }

        $i = 0;
        foreach ($this->json->courses->list as $course) {
            $course_as_array = (array)$course;
            if (isset($course->category)) {
                throw new \Exception("O curso #{$i} NÃO PODE ter o atributo 'category', deveria ser 'category_idnumber', favor corrigir.");
            }
            foreach (['category_idnumber', 'fullname', 'shortname', 'idnumber'] as $fieldname) {
                if (!isset($course->fullname)) {
                    throw new \Exception("O curso #{$i} TEM QUE TER o atributo 'fullname', favor corrigir.");
                }
            }
            foreach (['id', 'sortorder', 'originalcourseid', 'timecreated', 'timemodified'] as $fieldname) {
                if (isset($course->$fieldname)) {
                    throw new \Exception("O curso #{$i} NÃO PODE ter o atributo '{$fieldname}', favor corrigir.");
                }
            }
            $course_on_db = $DB->get_record('course', ['idnumber' => $course->idnumber]) ?: $DB->get_record('course', ['shortname' => $course->shortname]);
            $category_on_db = $this->get_category_by_idnumber($course->category_idnumber);

            if (!$course) {
                foreach (['category_idnumber', 'fullname', 'shortname', 'idnumber'] as $fieldname) {
                    unset($course_as_array[$fieldname]);
                }
                $course_on_db = create_course((object)$course_as_array);
            } elseif (isset($this->json->courses->update_fields)) {
                $update_fields = $this->json->courses->update_fields;
                if (in_array('idnumber', $update_fields)) {
                    throw new Exception("Não é possível atualizar o 'idnumber'.", 1);
                }

                $course_on_db_as_array = (array)$course_on_db;
                foreach ($course_as_array as $key => $value) {
                    if (in_array('', $update_fields)) {
                        $course_on_db_as_array[$key] = $value;
                    }
                }
                $course_on_db = (object)$course_on_db_as_array;
                update_course($course_on_db);
            }

            $course_on_db->context = \context_course::instance($course_on_db->id);
            $this->courses[$course_on_db->idnumber] = $course_on_db;
            $this->urls[$course_on_db->idnumber] = "$prefix?id={$course_on_db->id}";
        }
    }

    /*
    function sync_users() {
        global $CFG, $DB;
        
        if (!isset($this->json->users->list)) {
            return;
        }

        $i = 0;
        foreach ($this->json->users->list as $user) {
            if (!isset($course->idnumber)) {
                throw new \Exception("O user #{$i} veio sem o atributo 'idnumber', favor corrigir.");
            }
            if (!isset($course->firstname)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'firstname', favor corrigir.");
            }
            if (!isset($course->lastname)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'lastname', favor corrigir.");
            }
            if (!isset($course->username)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'username', favor corrigir.");
            }
            if (!isset($course->email)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'email', favor corrigir.");
            }
            if (!isset($course->auth)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'auth', favor corrigir.");
            }
            if (!isset($course->password)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'password', favor corrigir.");
            }
            if (!isset($course->lang)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'lang', favor corrigir.");
            }
            if (!isset($course->lang)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'lang', favor corrigir.");
            }
            if (!isset($course->category_idnumber)) {
                throw new \Exception("O curso #{$i} veio sem o atributo 'category_idnumber', favor corrigir.");
            }
            $user_on_db = $DB->get_record('course', ['idnumber' => $course->idnumber]) ?: $DB->get_record('course', ['shortname' => $course->shortname]);

            // "confirmed": 0,
            // "suspended": 0,
            // "profile_customfield1": "profile_customfield1",
            // "active": false

            $status = strtolower($usuario->isAluno ? $usuario->situacao : $usuario->status);
            $suspended = $status == 'ativo' ? 0 : 1;

            $nome_parts = explode(' ', $usuario->nome);
            $firstname = $nome_parts[0];
            $lastname = implode(' ', array_slice($nome_parts, 1));

            if ($usuario->isAluno) {
                $auth = $this->studentAuth;
            } else {
                $auth = $usuario->tipo == 'Principal' ? $this->teacherAuth : $this->assistantAuth;
            }

            $insert_only = ['username' => $username, 'password' => '!aA1' . uniqid(), 'timezone' => '99', 'confirmed' => 1, 'mnethostid' => 1];
            $insert_or_update = ['firstname' => $firstname, 'lastname' => $lastname, 'auth' => $auth, 'email' => $email, 'suspended' => $suspended];

            $usuario->user = $DB->get_record("user", ["username" => $username]);
            if ($usuario->user) {
                \user_update_user(array_merge(['id' => $usuario->user->id], $insert_or_update));
            } else {
                \user_create_user(array_merge($insert_or_update, $insert_only));
                $usuario->user = $DB->get_record("user", ["username" => $username]);
                foreach (preg_split('/\r\n|\r|\n/', $this->default_user_preferences) as $preference) {
                    $parts = explode("=", $preference);
                    \set_user_preference($parts[0], $parts[1], $usuario->user);
                }

                get_or_create(
                    'auth_oauth2_linked_login',
                    ['userid' => $usuario->user->id, 'issuerid' => $this->sgaIssuer->id, 'username' => $username],
                    ['email' => $email, 'timecreated' => time(), 'usermodified' => 0, 'confirmtoken' => '', 'confirmtokenexpires' => 0, 'timemodified' => time()],
                );
            }

            if ($usuario->isAluno) {
                $custom_fields = [
                    'programa_nome' => isset($usuario->programa) ? $usuario->programa : "Institucional",
                    'curso_descricao' => $this->json->curso->nome,
                    'curso_codigo' => $this->json->curso->codigo
                ];
                if (property_exists($usuario, 'polo')) {
                    $custom_fields['polo_id'] = property_exists($usuario->polo, 'id') ? $usuario->polo->id : null;
                    $custom_fields['polo_nome'] = property_exists($usuario->polo, 'descricao') ? $usuario->polo->descricao : null;
                }
                \profile_save_custom_fields($usuario->user->id, $custom_fields);
            }








            if (!$user) {
                $data = [
                    "idnumber" => $course_on_db->idnumber,
                    "shortname" => $course_on_db->shortname,
                    "fullname" => $course_on_db->fullname,
                    "category" => $category_on_db->id,

                    "visible" => isset($course_on_db->visible) ? $course_on_db->visible : 0,
                    "enablecompletion" => isset($course_on_db->enablecompletion) ? $course_on_db->enablecompletion : 0,
                    "showreports" => isset($course_on_db->showreports) ? $course_on_db->showreports : 1,
                    "completionnotify" => isset($course_on_db->completionnotify) ? $course_on_db->completionnotify : 1,

                    // "customfield_campus_id" => $this->json->campus->id,
                ];
                $course_on_db = create_course((object)$data);
                $course_on_db->context = \context_course::instance($course_on_db->id);
            } elseif (isset($this->json->courses->update_fields)) {
                $update_fields = $this->json->courses->update_fields;
                if (in_array('idnumber', $update_fields)) {
                    throw new Exception("Não é possível atualizar o 'idnumber'.", 1);
                }
                update_course($course_on_db);
                $course_on_db->context = \context_course::instance($course_on_db->id);
            }

            $this->user[$course_on_db->idnumber] = $course_on_db;
        }
    }


    function sync_enrols() {
        $this->professor_enrol = $this->get_enrolment_config('teacher');
        $this->tutor_enrol = $this->get_enrolment_config('assistant');
        $this->docente_enrol = $this->get_enrolment_config('instructor');
        $this->aluno_enrol = $this->get_enrolment_config('student');
    }


    function get_enrolment_config($type) {
        $roleid = config("default_{$type}_role_id");
        $enrol_type = config("default_{$type}_enrol_type");
        $enrol = enrol_get_plugin($enrol_type);
        $instance = $this->get_instance($enrol_type);
        if ($instance == null) {
            $enrol->add_instance($this->course);
            $instance = $this->get_instance($enrol_type);
        }
        return (object)['roleid' => $roleid, 'enrol_type' => $enrol_type, 'enrol' => $enrol, 'instance' => $instance];
    }


    function get_instance($enrol_type) {
        foreach (\enrol_get_instances($this->course->id, FALSE) as $i) {
            if ($i->enrol == $enrol_type) {
                return $i;
            }
        }
        return null;
    }


    function sync_docentes_enrol() {
        global $CFG, $DB;

        if (isset($this->json->professores)) {
            foreach ($this->json->professores as $usuario) {
                if ($this->isRoom) {
                    $enrol = $this->docente_enrol;
                } elseif (in_array(strtolower($usuario->tipo), ['principal', 'formador'])) {
                    $enrol = $this->professor_enrol;
                } else {
                    $enrol = $this->tutor_enrol;
                }

                $this->sync_enrol($enrol, $usuario, \ENROL_USER_ACTIVE);
            }
        }
    }


    function sync_discentes_enrol() {
        global $CFG, $DB;
        $alunos_suspensos = [];
        $alunos_sincronizados = [];
        if (isset($this->json->alunos)) {
            foreach ($this->json->alunos as $usuario) {
                $status = isset($usuario->situacao_diario) && strtolower($usuario->situacao_diario) != 'ativo' ? \ENROL_USER_SUSPENDED : \ENROL_USER_ACTIVE;
                $this->sync_enrol($this->aluno_enrol, $usuario, $status);
                array_push($alunos_sincronizados, $usuario->user->id);
            }

            // Inativa no diário os ALUNOS que não vieram na sicronização
            if (!$this->isRoom) {
                foreach ($DB->get_records_sql("SELECT ra.userid FROM {role_assignments} ra WHERE ra.roleid = {$this->aluno_enrol->roleid} AND ra.contextid={$this->context->id}") as $userid => $ra) {
                    if (!in_array($userid, $alunos_sincronizados)) {
                        $this->aluno_enrol->enrol->update_user_enrol($this->aluno_enrol->instance, $userid, \ENROL_USER_SUSPENDED);
                    }
                }
            }
        }
    }


    function sync_enrol($enrol, $usuario, $status) {
        if (is_enrolled($this->context, $usuario->user)) {
            $enrol->enrol->update_user_enrol($enrol->instance, $usuario->user->id, $status);
        } else {
            $enrol->enrol->enrol_user($enrol->instance, $usuario->user->id, $enrol->roleid, time(), 0, $status);
        }
    }


    function sync_groups() {
        global $CFG, $DB;
        if (isset($this->json->alunos)) {
            $grupos = [];
            foreach ($this->json->alunos as $usuario) {
                $entrada = substr($usuario->user->username, 0, 5);
                $turma = $this->json->turma->codigo;
                $polo = isset($usuario->polo) && isset($usuario->polo->descricao) ? $usuario->polo->descricao : '--Sem pólo--';
                $programa = isset($usuario->programa) && $usuario->programa != null ? $usuario->programa : "Institucional";

                if (!isset($grupos[$entrada])) {
                    $grupos[$entrada] = [];
                }
                if (!isset($grupos[$turma])) {
                    $grupos[$turma] = [];
                }
                if (!isset($grupos[$polo])) {
                    $grupos[$polo] = [];
                }
                if (!isset($grupos[$programa])) {
                    $grupos[$programa] = [];
                }

                $grupos[$entrada][] = $usuario;
                $grupos[$turma][] = $usuario;
                $grupos[$polo][] = $usuario;
                $grupos[$programa][] = $usuario;
            }

            foreach ($grupos as $group_name => $alunos) {
                $group = $this->sync_group($group_name);
                $idDosAlunosFaltandoAgrupar = $this->getIdDosAlunosFaltandoAgrupar($group, $alunos);
                // $new_group_members = [];
                foreach ($alunos as $group_name => $usuario) {
                    if (!in_array($usuario->user->id, $idDosAlunosFaltandoAgrupar)) {
                        \groups_add_member($group->id, $usuario->user->id);
                        // array_push($new_group_members, (object)['groupid' => $group->id, 'userid' => $usuario->user->id, "timeadded"=>time()]);
                    }
                }
                // $DB->insert_records("groups_members", $new_group_members);
            }
            //
        }
    }


    function sync_group($group_name) {
        global $DB;
        $data = ['courseid' => $this->course->id, 'name' => $group_name];
        $group = $DB->get_record('groups', $data);
        if (!$group && $this->diarioIsNew) {
            \groups_create_group((object)$data);
            $group = $DB->get_record('groups', $data);
        }
        return $group;
    }


    function getIdDosAlunosFaltandoAgrupar($group, $alunos) {
        global $DB;
        $alunoIds = array_map(function ($x) {
            return $x->user->id;
        }, $alunos);
        list($insql, $inparams) = $DB->get_in_or_equal($alunoIds);
        $sql = "SELECT userid FROM {groups_members} WHERE groupid = ? and userid $insql";
        $ja_existem = $DB->get_records_sql($sql, array_merge([$group->id], $inparams));
        return array_map(function ($x) {
            return $x->userid;
        }, $ja_existem);
    }


    function sync_cohorts() {
        global $DB;

        $roles = [];
        $instances = [];
        $coortesid = [];
        $enrol = enrol_get_plugin("cohort");
        if (isset(($this->json->coortes))) {
            foreach ($this->json->coortes as $coorte) {
                if (!isset($instances[$coorte->role])) {
                    $instance = $DB->get_record('cohort', ['idnumber' => $coorte->idnumber]);
                    if (!$instance) {
                        $coortesid[$coorte->role] = \cohort_add_cohort(
                            (object)[
                                "name" => $coorte->nome,
                                "idnumber" => $coorte->idnumber,
                                "description" => $coorte->descricao,
                                "visible" => $coorte->ativo,
                                "contextid" => 1
                            ]
                        );
                    } else {
                        $instance->name = $coorte->nome;
                        $instance->idnumber = $coorte->idnumber;
                        $instance->description = $coorte->descricao;
                        $instance->visible = $coorte->ativo;
                        \cohort_update_cohort($instance);
                        $coortesid[$coorte->role] = $instance->id;
                    }
                }
                $cohortid = $coortesid[$coorte->role];

                foreach ($coorte->colaboradores as $usuario) {
                    $usuario->isAluno = False;
                    $usuario->isProfessor = False;
                    $usuario->isColaborador = True;
                    $usuario->tipo = "Staff";
                    $this->sync_user($usuario);
                    \cohort_add_member($cohortid, $usuario->user->id);
                }

                if (!isset($roles[$coorte->role])) {
                    $roles[$coorte->role] = $DB->get_record('role', ['shortname' => $coorte->role]);
                }
                $role = $roles[$coorte->role];

                if (!isset($instances[$cohortid])) {
                    $instances[$cohortid] = $DB->get_record('enrol', ["enrol" => "cohort", "customint1" => $cohortid, "courseid" => $this->course->id]);
                    if (!$instance) {
                        $enrol->add_instance($this->course, ["customint1" => $cohortid, "roleid" => $role->id, "customint2" => 0]);
                    }
                }
                $instance = $instances[$cohortid];
            }
        }
    }

    function sync_auths() {
        global $DB;

        $this->studentAuth = config('default_student_auth');
        $this->teacherAuth = config('default_teacher_auth');
        $this->assistantAuth = config('default_assistant_auth');
        $this->default_user_preferences = config('default_user_preferences');
    }

*/

    function insertSyncDB($jsonstring) {
        global $DB;

        $DB->insert_record(
            "sga_enrolment_to_sync",
            (object)[
                'json' => $jsonstring,
                'timecreated' => time(),
                'processed' => 0
            ]
        );
    }
}

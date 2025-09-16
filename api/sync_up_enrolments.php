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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');


define(
    'TOOL_SGA_USERS_BANNED_FIELDS',
    [
        'id',
        'timecreated', 'timemodified', 'lastlogin', 'firstaccess', 'lastaccess', 'currentlogin', 'lastip',
        'secret',
        'picture', 'imagealt',
        'description', 'descriptionformat', 'htmleditor',
        'mailformat','maildigest', 'maildisplay',
        'autosubscribe', 'trackforums',
        'trustbitmask',
        'calendartype',
        'lastnamephonetic', 'firstnamephonetic','middlename', 'alternatename',
        'password',
        'mnethostid', 'moodlenetprofile',
        'theme'
    ]
);

define(
    'TOOL_SGA_USERS_REQUIRED_FIELDS',
    ['username', 'auth', 'firstname', 'lastname', 'email', "active"]
);

define(
    'TOOL_SGA_USERS_OPTIONAL_FIELDS',
    [
        "confirmed", "policyagreed", "deleted", "suspended", "emailstop", 
        "phone1", "phone2", "institution", "department", "address", "city", "country", "lang",
        'timezone', 'idnumber'
    ]
);


trait sync_up_enrolments_helper {

    protected $categories = [];
    protected $courses = [];
    protected $users = [];
    protected $cohorts = [];
    protected $enrolments = [];
    protected $groups = [];

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

    function get_category_by_idnumber($idnumber) {
        global $DB;
        if (in_array($idnumber, $this->categories)) {
            return $this->categories[$idnumber];
        }

        $this->categories[$idnumber] = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
        return $this->categories[$idnumber];
    }

    function get_course_by_idnumber($idnumber) {
        global $DB;

        if (in_array($idnumber, $this->courses)) {
            return $this->courses[$idnumber];
        }
        
        $course = $DB->get_record('course', ['idnumber' => $idnumber]);

        if (!$course) {
            return null;
        }

        $this->courses[$idnumber] = $course;
        $this->courses[$idnumber]->context = \context_course::instance($course->id);
        return $this->courses[$idnumber];
    }

    function get_user_by_username($username) {
        global $DB;

        if (in_arra($username, $this->users)) {
            return $this->users[$username];
        }

        $this->users[$username] = $DB->get_record('course', ['username' => $username]);
        $this->users[$username]->context = \context_course::instance($this->users[$username]->id);
        return $this->users[$username];
    }

    function check_required_fields($object, $required_fields, $object_name, $object_index_at_list) {
        $error = "";
        foreach ($required_fields as $required_field) {
            if (!property_exists($object, $required_field)) {
                $error .= "O $object_name '#{$object_index_at_list}' TEM QUE TER o atributo '$required_field', favor corrigir.\n";
            }
        }
        if ($error != "") {
            throw new \Exception($error);
        }
    }

    function set_updatable_fields($object, $data, $updatable_fields) {
        foreach ($data as $fieldname => $value) {
            if (in_array('idnumber', $updatable_fields)) {
                throw new Exception("Não é possível atualizar o 'idnumber'.");
            }

            if (in_array($fieldname, $updatable_fields) && property_exists($object, $fieldname)) {
                $data[$fieldname] = $object->$fieldname;
            }
        }
        return $data;
    }

    function check_banned_fields($object, $banned_fields, $object_name, $object_index_at_list) {
        $error = "";

        foreach ($banned_fields as $fieldname) {
            if (isset($object->$fieldname)) {
                throw new \Exception("Não é permitido atualizar o atributo '{$fieldname}' do '$object_name' #{$object_index_at_listi}, favor corrigir.");
            }
        }
        if ($error != "") {
            throw new \Exception($error);
        }
    }

    function _get_parent_id($category) {
        if (!isset($category->parent_idnumber)) {
            return null;
        }
        $parent = $this->get_category_by_idnumber($category->parent_idnumber);
        if (!$parent) {
            return null;
        }
        return $parent->id;
    }
}


class sync_up_enrolments_service extends service {

    use sync_up_enrolments_helper;


    private $urls = [
        "categories" => [],
        "courses" => []
    ];
    private $json;


    function do_call() {
        global $CFG;
        $jsonstring = file_get_contents('php://input');

        $result = $this->process($jsonstring, false);
        $this->insertSyncDB($jsonstring);

        return $result;
    }


    function process($jsonstring, $assync) {
        global $CFG;

        $this->validate_json($jsonstring);
        $this->sync_categories();
        $this->sync_courses();
        if ($assync) {
            // $this->restore_courses_backup();
            // $this->sync_users();
            // $this->sync_cohorts();
            // $this->sync_enrols();
            // $this->sync_groups();
        }

        return $this->urls;
    }


    function sync_categories() {
        global $DB, $CFG;

        $i = 0;
        foreach ($this->json->categories->list ?? [] as $category) {
            $i++;
            $this->check_banned_fields($category, ['id', 'parent', 'sortorder', 'coursecount', 'visibleold', 'timemodified', 'depth', 'path'], 'categoria', $i);
            $this->check_required_fields($category, ['idnumber', 'name'], 'categoria', $i);

            $category_on_db = $this->get_category_by_idnumber($category->idnumber);
            if (empty($category_on_db)) {
                $data = [
                    'idnumber' => $category->idnumber,
                    'name' => $category->name,

                    'description' => isset($category->description) ? $category->description : null,
                    'descriptionformat' => isset($category->descriptionformat) ? $category->descriptionformat : 0,
                    'visible' => isset($category->visible) ? $category->visible : 1,
                    'theme' => isset($category->theme) ? $category->theme : '',
                    'parent' => $this->_get_parent_id($category)
                ];

                $category_on_db = \core_course_category::create($data);
            } elseif (isset($this->json->categories->update_fields)) {
                $data = $this->set_updatable_fields($category, [], $this->json->categories->update_fields);
                if (in_array('parent_idnumber', $this->json->categories->update_fields)) {
                    $this->_set_parent($data, $category);
                }

                if (count($data) > 0) {
                    \core_course_category::update($data);
                    unset($this->categories[$category->idnumber]);
                    
                }
            }
            $category_on_db = $this->get_category_by_idnumber($category->idnumber);
            $this->urls['categories'][$category_on_db->idnumber] = "{$CFG->wwwroot}/course/index.php?categoryid={$category_on_db->id}";
        }
    }


    function sync_courses() {
        global $DB, $CFG;

        $i = 0;
        foreach ($this->json->courses->list ?? [] as $course) {
            $i++;
            $this->check_banned_fields($course, ['id', 'category', 'sortorder', 'originalcourseid', 'timecreated', 'timemodified'], 'curso', $i);
            $this->check_required_fields($course, ['category_idnumber', 'fullname', 'shortname', 'idnumber'], 'curso', $i);

            $course_on_db = $this->get_course_by_idnumber($course->idnumber);
            $category_on_db = $this->get_category_by_idnumber($course->category_idnumber);

            if ($course_on_db == null) {
                $course->category = isset($category_on_db->id) ? $category_on_db->id : 1;
                create_course($course);
                $course_on_db = $this->get_course_by_idnumber($course->idnumber);
            } elseif (isset($this->json->courses->update_fields)) {
                $data = $this->set_updatable_fields($course, [], $this->json->courses->update_fields);
                if (count($data) > 0) {
                    update_course((object)$data);
                    unset($this->courses[$course->idnumber]);
                    $course_on_db = $this->get_course_by_idnumber($course->idnumber);
                }
            }
            $this->urls["courses"][$course_on_db->idnumber] = "{$CFG->wwwroot}/course/view.php?id={$course_on_db->id}";
        }
    }


    function restore_courses_backup() {
        global $CFG, $DB;
        $i = 0;
        foreach ($this->json->courses->list ?? [] as $course) {
            $i++;
            if (!isset($course->modelo_path)) {
                continue;
            }

            $course_on_db = $this->get_course_by_idnumber($course->idnumber);

            foreach ($course->modelo_path as $modelo_idnumber) {
                $modelo_course = $this->get_course_by_idnumber($modelo_idnumber);
                if (!$modelo_course) {
                    continue;
                }

                # Backup do modelo
                $bc = new \backup_controller(
                    \backup::TYPE_1COURSE,
                    $modelo_course->id,
                    \backup::FORMAT_MOODLE,
                    \backup::INTERACTIVE_NO,
                    \backup::MODE_GENERAL,
                    get_admin()->id
                );

                $bc->execute_plan();
                $results = $bc->get_results();
                $backupfile = $results['backup_destination'];
                $bc->destroy();

                # Restauração no curso destino
                $rc = new \restore_controller(
                    $bc->get_backupid(),
                    $course_on_db->id,
                    \backup::INTERACTIVE_NO,
                    \backup::MODE_GENERAL,
                    get_admin()->id,
                    \backup::TARGET_CURRENT_ADDING
                );

                if ($rc->execute_precheck()) {
                //     $rc->execute_plan();
                     echo "Backup do curso $courseid restaurado com sucesso no curso $targetcourseid.";
                } else {
                     echo "Erro na verificação prévia da restauração.";
                }

                $rc->destroy();
                die();
            }

            $this->check_required_fields($course, ['modelo_path'], 'curso', $i);

        }
        /*
            $course_on_db = $this->get_course_by_idnumber($course->idnumber);
            if (empty($course_on_db)) {
                throw new \Exception("O curso com idnumber '{$course->idnumber}' não existe no Moodle, favor corrigir.");
            }

            if (!file_exists($course->backup_file_path)) {
                throw new \Exception("O arquivo de backup do curso com idnumber '{$course->idnumber}' não existe no caminho '{$course->backup_file_path}', favor corrigir.");
            }

            // Start the restore process.
            $bc = new \backup_controller(
                \backup::TYPE_1COURSE,
                $course_on_db->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                1
            );
            $bc->get_plan()->get_setting('filename')->set_value($course->backup_file_path);
            $bc->execute_plan();
        }

        /*
        require_once('../../config.php');

        // Backup do curso
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $courseid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );

        $bc->execute_plan();
        $results = $bc->get_results();
        $backupfile = $results['backup_destination'];
        $bc->destroy();


        */
    }

    function sync_users() {
        global $CFG, $DB;
        
        $i = 0;
        foreach ($this->json->users->list ?? [] as $user) {
            $this->check_banned_fields($user, TOOL_SGA_USERS_BANNED_FIELDS, 'user', $i);
            $this->check_required_fields($user, TOOL_SGA_USERS_REQUIRED_FIELDS, 'user', $i);
            $user_on_db = $this->get_user_by_username($user->username);
            $user_as_array = (array)$user;
            
            if (empty($user_on_db)) {
                $data = [
                    # Required fields
                    'username' => $user->username,
                    'auth' => $user->auth,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'active' => $user->active,
                ];

                foreach (TOOL_SGA_USERS_OPTIONAL_FIELDS as $optional_field) {
                    if (isset($user_as_array[$optional_field])) {
                        if ($optional_field == 'password') {
                            $user_as_array['password'] = hash_internal_user_password($user->password);
                        } else {
                            $data[$optional_field] = $user_as_array[$optional_field];
                        }
                    }
                }

                \user_create_user($data);

                $user_on_db = $this->get_user_by_username($user->username);
                $user_on_db->is_new = True;
            } elseif (isset($this->json->users->update_fields)) {
                $data = $this->set_updatable_fields($user, [], $this->json->users->update_fields);
                if (count($data) > 0) {
                    if (in_array('password', $data)) {
                        $data['password'] = hash_internal_user_password($user->password);
                    }
                    $data['id'] = $user_on_db->id;
                    \user_update_user($data);
                    unset($this->users[$user->username]);
                    $user_on_db = $this->get_user_by_username($user->username);
                }
            }

            if (isset($user->custom_fields)) {
                \profile_save_custom_fields($user_on_db->id, $custom_fields);
            }

            if (isset($user->is_new) && isset($this->json->users->default_user_preferences)) {
                foreach ($this->json->users->default_user_preferences as $key => $value) {
                    \set_user_preference($key, $value, $user_on_db);
                }
            }
        }
    }

    /*
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

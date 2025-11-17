<?php
/** This file is part of "Moodle SGA Integration"
 * 
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
 */

/** SGA Integration
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

require_once('../../../../../../config.php');
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/user/lib.php');
    require_once($CFG->dirroot . '/cohort/lib.php');
    require_once($CFG->dirroot . '/user/profile/lib.php');
    require_once($CFG->dirroot . '/group/lib.php');
    require_once($CFG->dirroot . '/lib/enrollib.php');
    require_once($CFG->dirroot . '/enrol/locallib.php');
    require_once($CFG->dirroot . '/enrol/externallib.php');
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    require_once($CFG->dirroot . '/admin/tool/sga/locallib.php');
    require_once($CFG->dirroot . '/admin/tool/sga/api/servicelib.php');
    require_once($CFG->dirroot . '/admin/tool/sga/classes/Jsv4/Validator.php');


trait sync_up_enrolments_helper {

    protected $categories = [];
    protected $courses = [];
    protected $users = [];
    protected $cohorts = [];
    protected $cache = [];
    protected $enrolments = [];
    protected $groups = [];
    protected $user_fields = [
        'optional' => [
            "confirmed", "policyagreed", "deleted", "suspended", "emailstop", 
            "phone1", "phone2", "institution", "department", "address", "city", "country", "lang",
            'timezone', 'idnumber', "password"
        ]
    ];

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

        $cat = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
        if (!$cat) {
            return null;
        }

        $this->categories[$idnumber] = \core_course_category::get($cat->id);
        return $this->categories[$idnumber];
    }

    function get_from_cache($bucket, $naturalkey, $default_value = null) {
        if (!array_key_exists($bucket, $this->cache)) {
            $this->cache[$bucket] = [];
        }

        return $this->cache[$bucket][$naturalkey] ?? $default_value;
    }
    
    function put_into_cache($bucket, $key, $value) {
        if (!array_key_exists($bucket, $this->cache)) {
            $this->cache[$bucket] = [];
        }

        $this->cache[$bucket][$key] = $value;
        return $value;
    }

    function get_cached($origin, $naturalkey, $key, $thows_exception_if_not_found = false) {
        global $DB;

        $cached = $this->get_from_cache($origin, $key);

        if ($cached) {
            return $cached;
        }
        
        $on_db = $DB->get_record($origin, [$naturalkey => $key]);
        if (!$on_db) {
            if ($thows_exception_if_not_found) {
                throw new \Exception("O registro do tipo '$origin' com `$naturalkey='$key'` não existe, favor corrigir.");
            }
            return null;
        }

        if ($origin === 'course') {
            $on_db->context = \context_course::instance($on_db->id);
        }

        return $this->put_into_cache($origin, $naturalkey, $on_db);
    }

    function get_cached_group_course($group_idnumber, $course_idnumber) {
        global $DB;
        $naturalkey = "$group_idnumber::$course_idnumber";
        if ($group = $this->get_from_cache('groups', $naturalkey)) {
            return $group;
        }

        $course = $this->get_cached('course', 'idnumber', $course_idnumber, true);
        $group = $DB->get_record('groups', ['idnumber' => $group_idnumber, 'courseid' => $course->id]);
        $this->put_into_cache('groups', $naturalkey, $group);

        return $group;
    }

    function get_cached_enrol_plugin($enrol_type) {

        $cached = $this->get_from_cache('enrol_plugins', $enrol_type);
        if ($cached) {
            return $cached;
        }

        $enrolplugin = enrol_get_plugin($enrol_type);
        if (!$enrolplugin) {
            throw new \Exception("O método de inscrição '{$enrol_type}' não existe, favor corrigir.");
        }

        return $this->put_into_cache('enrol_plugins', $enrol_type, $enrolplugin);
    }

    function get_course_enrol($course_idnumber, $enrol_type) {
        $naturalkey = "$enrol_type::$course_idnumber";
        $cached = $this->get_from_cache('course_enrol_instances', $naturalkey);

        if ($cached) {
            return $cached;
        }

        $course = $this->get_cached('course', 'idnumber', $course_idnumber);
        $enrol_plugin = $this->get_cached_enrol_plugin($enrol_type);
        foreach (\enrol_get_instances($course->id, FALSE) as $instance) {
            if ($instance->enrol == $enrol_type) {
                $this->put_into_cache(
                    'course_enrol_instances', 
                    $naturalkey, 
                    (object)['plugin' => $enrol_plugin, 'instance' => $instance, 'roleid' => $instance->roleid]
                );
                return $this->get_from_cache('course_enrol_instances', $naturalkey);
            }
        }
        throw new Exception("Não foi encontrado o enrol `$enroltype` para o curso `$course_idnumber`.", 1);
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
        foreach ($updatable_fields as $fieldname) {
            if ($fieldname === 'idnumber') {
                throw new \Exception("Não é possível atualizar o 'idnumber'.");
            }

            if (property_exists($object, $fieldname)) {
                $data[$fieldname] = $object->$fieldname;
            }
        }
        return $data;
    }

    function check_banned_fields($object, $banned_fields, $object_name, $object_index_at_list) {
        $error = "";

        foreach ($banned_fields as $fieldname) {
            if (isset($object->$fieldname)) {
                throw new \Exception("Não é permitido atualizar o atributo '{$fieldname}' do '$object_name' #{$object_index_at_list}, favor corrigir.");
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
        return $parent->i ?? null;
    }

    function get_template_course($course) {
        $candidate_templates = array_filter($course->template_path, fn($idn) => $this->get_cached('course', 'idnumber', $idn));
        $candidate_template = array_values($candidate_templates)[0] ?? null;
        return $this->get_cached('course', 'idnumber', $candidate_template);
    }

    function backup_template($template_course) {
        # Backup do template
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE, 
            $template_course->id, 
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            // \backup::MODE_IMPORT,
            get_admin()->id
        );
        $filename = \backup_plan_dbops::get_default_backup_filename($bc->get_format(), $bc->get_type(), $bc->get_id(), false, true);
        $bc->get_plan()->get_setting('filename')->set_value($filename);
        $bc->execute_plan();
        $result = $bc->get_results();
        $bc->destroy();
        return ($bc->get_status() == \backup::STATUS_FINISHED_OK) ? $result : false;
    }

    function restore_into_course($backupfile, $course) {
        $backupdir = \restore_controller::get_tempdir_name(SITEID, get_admin()->id);
        $path = make_backup_temp_directory($backupdir);
        $backupfile->copy_content_to("$path/kkk");
        $fp = get_file_packer('application/vnd.moodle.backup');
        $fp->extract_to_pathname("$path/kkk", $path);

        $rc = new \restore_controller(
            $backupdir,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_EXISTING_ADDING
        );

        if ($rc->execute_precheck()) {
            try {
                $rc->execute_plan();
                return true;
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            return false;
        }
        $rc->destroy();
    }

    function request_iterator($type, $banned_fields, $required_fields, $callback) {
        global $CFG;

        if (!isset($this->json->$type) || !is_object($this->json->$type) || !isset($this->json->$type->list) || !is_array($this->json->$type->list)) {
            return;
        }

        $i = 0;
        $this->$type = [];
        foreach ($this->json->$type->list ?? [] as $object) {
            $naturalkey = $object->username ?? $object->idnumber ?? 'unknown';
            try {
                $this->check_banned_fields($object, $banned_fields, $type, $i);
                $this->check_required_fields($object, $required_fields, $type, $i);

                $on_db = $callback($object, $i);
                $this->$type[$naturalkey] = $on_db;
            } catch (\Throwable $th) {
                $this->urls[$type][$naturalkey] = null;
                $op = isset($object->new) && $object->new ? 'ADD' : 'UPD';
                $this->errors[] = "Erro ao processar($op) o $type#$i '{$naturalkey}': `" . $th->getMessage() . "`.";
            }
            $i++;
        }
    }

    function parse_date($date, $att_name, $dafeult_value = 0) {
        if (isset($date)) {
            if (is_string($date)) {
                if (strtotime($date) === false) {
                    throw new \Exception("O atributo '$att_name' deve ser uma string com data válida em formato ISO, favor corrigir.");
                }
                return strtotime($date);
            } else if (is_int($to_sync->timestart)) {
                if ($date < 0) {
                    throw new \Exception("O atributo '$att_name' deve ser um unix timestamp positivo, favor corrigir.");
                }
                return $date;
            } else {
                throw new \Exception("O atributo '$att_name' deve ser um inteiro representando um unix timestamp ou uma string com data válida em formato ISO, favor corrigir.");
            }
        }
        return $dafeult_value;
    }
}


class sync_up_enrolments_service extends service {

    use sync_up_enrolments_helper;


    private $urls = [];
    private $errors = [];
    private $successes = [];
    private $json;


    function do_call() {
        global $CFG;
        $jsonstring = file_get_contents('php://input');

        $result = $this->process($jsonstring, true);
        $this->insertSyncDB($jsonstring);

        return $result;
    }


    function process($jsonstring, $assync) {
        global $CFG;

        $this->validate_json($jsonstring);
        $this->sync_categories();
        $this->sync_courses();
        if ($assync) {
            $this->import_template_courses_backup();
            $this->sync_users();
            $this->sync_cohorts();
            $this->sync_cohorts_members();
            $this->sync_enrols();
            $this->sync_enrolments();
            $this->sync_groups();
            $this->sync_groups_members();
        }

        return [
            "urls"=>$this->urls,
            'erros'=>$this->errors,
            'successes'=>$this->successes,
        ];
    }


    function sync_categories() {
        $this->request_iterator(
            'categories',
            ['id', 'parent', 'sortorder', 'coursecount', 'visibleold', 'timemodified', 'depth', 'path'],
            ['idnumber', 'name', 'visible'],
            function($to_sync, $i) {
                $on_db = $this->get_category_by_idnumber($to_sync->idnumber);
                $to_sync->naturalkey = $to_sync->idnumber;
                $to_sync->op = $on_db ? 'UPD' : 'ADD';
                if ($to_sync->op == 'ADD') {
                    $data = [
                        'idnumber' => $to_sync->idnumber,
                        'name' => $to_sync->name,

                        'description' => isset($to_sync->description) ? $to_sync->description : null,
                        'descriptionformat' => isset($to_sync->descriptionformat) ? $to_sync->descriptionformat : 0,
                        'visible' => isset($to_sync->visible) ? $to_sync->visible : 1,
                        'theme' => isset($to_sync->theme) ? $to_sync->theme : '',
                        'parent' => $this->_get_parent_id($to_sync)
                    ];

                    $on_db = \core_course_category::create($data);
                } elseif (isset($this->json->categories->update_fields)) {
                    $data = $this->set_updatable_fields($to_sync, [], $this->json->categories->update_fields);
                    if (in_array('parent_idnumber', $this->json->categories->update_fields)) {
                        $this->_set_parent($data, $to_sync);
                    }

                    if (count($data) > 0) {
                        $on_db->update($data);
                        unset($this->categories[$to_sync->idnumber]);
                        $on_db = $this->get_category_by_idnumber($to_sync->idnumber);
                    }
                }
                $this->urls['categories'][$to_sync->idnumber] = "{$CFG->wwwroot}/course/index.php?categoryid={$on_db->id}";
                return $on_db;
            }
        );
    }


    function sync_courses() {
        $this->request_iterator(
            'courses',
            ['id', 'category', 'sortorder', 'originalcourseid', 'timecreated', 'timemodified'],
            ['category_idnumber', 'fullname', 'shortname', 'idnumber'],
            function($course, $i) {
                $course->naturalkey = $course->idnumber;
                $catetory_on_db = $this->get_cached('course_categories', 'idnumber', $course->category_idnumber, true);

                $on_db = $this->get_cached('course', 'idnumber', $course->idnumber);
                $course->op = $on_db ? 'UPD' : 'ADD';
                if ($course->op === 'ADD') {
                    $course->category = $catetory_on_db->id;
                    \create_course($course);
                    $on_db = $this->get_cached('course', 'idnumber', $course->idnumber);
                } elseif (isset($this->json->courses->update_fields)) {
                    $data = $this->set_updatable_fields($course, [], $this->json->courses->update_fields);
                    if (count($data) > 0) {
                        \update_course((object)$data);
                        unset($this->courses[$course->idnumber]);
                        $on_db = $this->get_cached('course', 'idnumber', $course->idnumber);
                    }
                }
                $this->urls["courses"][$on_db->idnumber] = "{$CFG->wwwroot}/course/view.php?id={$on_db->id}";
                return $on_db;
            }
        );
    }


    function import_template_courses_backup() {
        // TODO: Fazer de forma assíncrona, ou seja, não haverá o atributo `$course->op` no JSON e tenho que decidir como proceder.
        global $CFG, $DB;

        $courses_with_templates = array_filter($this->json->courses->list ?? [], function($course) {return isset($course->template_path) && $course->op == 'ADD';});
        foreach ($courses_with_templates as $course) {
            if (!$on_db = $this->get_cached('course', 'idnumber', $course->idnumber)) {
                continue;
            };

            if (!$template_course = $this->get_template_course($course)) {
                continue;
            }

            if (!$backup_result = $this->backup_template($template_course)) {
                $this->errors[] = "Não foi possível restaurar o modelo {$template_course} para o curso {$course->idnumber}.";
                continue;
            }

            if ($this->restore_into_course($backup_result['backup_destination'], $on_db)) {
                $this->successes[] = "Backup do curso $template_course->idnumber ($template_course->id) restaurado com sucesso no curso $on_db->idnumber ($on_db->id).";
            } else {
                $this->errors[] = "Falha na verificação prévia da restauração Backup do curso $template_course->idnumber ($template_course->id) restaurado com sucesso no curso $on_db->idnumber ($on_db->id).";
            }
        }
    }


    function sync_users() {
        global $CFG;
        $this->request_iterator(
            'users',
            ['id', 'timecreated', 'timemodified', 'lastlogin', 'firstaccess', 'lastaccess', 'currentlogin', 'lastip', 'secret', 'description', 'descriptionformat', 'htmleditor', 'mailformat','maildigest', 'maildisplay', 'autosubscribe', 'trackforums', 'trustbitmask', 'calendartype', 'mnethostid', 'moodlenetprofile'],
            ['username', 'auth', 'firstname', 'lastname', 'email', 'password', "active"],
            function($user, $i) {
                $password = !empty(trim($user->password ?? "")) ? hash_internal_user_password($user->password) : AUTH_PASSWORD_NOT_CACHED;

                $user->naturalkey = $user->username;
                $on_db = $this->get_cached('user', 'username', $user->naturalkey);
                $user->op = $on_db ? 'UPD' : 'ADD';
                if ($user->op == 'ADD') {
                    $data = (array)$user;
                    $data['password'] = $password;
                    unset($data['user_preferences']);
                    unset($data['custom_fields']);

                    \user_create_user($data, ($password != AUTH_PASSWORD_NOT_CACHED));

                    $on_db = $this->get_cached('user', 'username', $user->naturalkey);

                    $on_db->is_new = True;

                    foreach ($user->user_preferences ?? [] as $key => $value) {
                        \set_user_preference($key, $value, $on_db);
                    }
                } elseif (isset($this->json->users->update_fields)) {
                    $data = $this->set_updatable_fields($user, [], $this->json->users->update_fields);
                    if (count($data) > 0) {
                        if ($password != AUTH_PASSWORD_NOT_CACHED) {
                            $data['password'] = $password;
                        } else {
                            unset($data['password']);
                        }

                        $data['id'] = $on_db->id;
                        \user_update_user($data);
                        unset($this->users[$user->username]);
                        $on_db = $this->get_cached('user', 'username', $user->naturalkey);
                    }
                }

                if (isset($user->custom_fields)) {
                    \profile_save_custom_fields($on_db->id, (array)($user->custom_fields));
                }

                if (isset($user->picture) && !empty($user->picture) && $user->picture != $on_db->picture) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    $usercontext = \context_user::instance($on_db->id);
                    $fs = \get_file_storage();
                    $fs->delete_area_files($usercontext->id, 'user', 'icon');
                    $user->picture = \profile_save_data($usercontext, 'icon', $user->picture);
                    $on_db->picture = $user->picture;
                    $DB->update_record('user', (object)['id' => $on_db->id, 'picture' => $on_db->picture]);
                }

                $this->urls['users'][$user->naturalkey] = "{$CFG->wwwroot}/user/view.php?id={$on_db->id}";
                return $on_db;
            }
        );
    }


    function sync_cohorts() {
        $this->request_iterator(
            'cohorts',
            ['id', 'timecreated', 'timemodified'],
            ['idnumber', 'contextid', 'visible'],
            function($to_sync, $i) {
                global $CFG, $DB;
                $to_sync->naturalkey = $to_sync->idnumber;
                $on_db = $DB->get_record('cohort', ['idnumber' => $to_sync->idnumber]);
                $to_sync->op = $on_db ? 'UPD' : 'ADD';
                if ($to_sync->op == 'ADD') {
                    $db_id = \cohort_add_cohort($to_sync);
                    $on_db = $DB->get_record('cohort', ['id' => $db_id]);
                    $this->cohorts[$to_sync->naturalkey] = $on_db;
                } elseif (isset($this->json->cohorts->update_fields)) {
                    $data = $this->set_updatable_fields($to_sync, [], $this->json->cohorts->update_fields);
                    if (count($data) > 0) {
                        $data['id'] = $on_db->id;
                        \cohort_update_cohort($instance);
                        $on_db = $DB->get_record('cohort', ['id' => $on_db->id]);
                    }
                }
                $this->urls['cohorts'][$to_sync->naturalkey] = "$CFG->wwwroot/cohort/edit.php?id=$on_db->id";
                return (object)['on_db'=>$on_db, 'to_sync'=>$to_sync, 'naturalkey'=>$to_sync->naturalkey];
            }
        );
    }


    function sync_cohorts_members() {
        $this->request_iterator(
            "cohorts_members",
            ["id", "cohortid", "userid", "timeadded"],
            ["cohort_idnumber", "user_username"],
            function($to_sync, $i) {
                \cohort_add_member(
                    $this->get_cached('cohort', 'idnumber', $to_sync->cohort_idnumber, true)->id,
                    $this->get_cached('user', 'username', $to_sync->user_username, true)->id
                );

                $this->urls['cohorts_members']["$to_sync->cohort_idnumber::$to_sync->user_username"] = true;
                return true;
            }
        );
    }

    function sync_enrols() {
        $this->request_iterator(
            "enrols",
            ['id', 'roleid', 'courseid', 'timecreated', 'timemodified', 'sortorder'],
            ['enrol', 'role_shortname', 'course_idnumber', 'name'],
            function($to_sync, $i) {
                global $CFG, $DB;
                $type = 'enrol';
                $to_sync->naturalkey = $to_sync->enrol . '::' . $to_sync->role_shortname . '::' . $to_sync->course_idnumber;

                $role =  $this->get_cached('role', 'shortname', $to_sync->role_shortname, true);
                $course = $this->get_cached('course', 'idnumber', $to_sync->course_idnumber, true);
                $enrolplugin = $this->get_cached_enrol_plugin($to_sync->enrol);


                $on_db = $DB->get_record('enrol', ['enrol' => $to_sync->enrol, 'courseid' => $course->id, 'roleid' => $role->id]);
                $to_sync->op = $on_db ? 'UPD' : 'ADD';

                if ($to_sync->op == 'ADD') {

                    $data = (array)$to_sync;
                    unset($data['role_shortname']);
                    unset($data['course_idnumber']);
                    unset($data['naturalkey']);
                    unset($data['op']);
                    $data['roleid'] = $role->id;

                    $instanceid = $enrolplugin->add_instance($course, $data);
                    $on_db = $DB->get_record($type, ['id' => $instanceid]);
                    $this->cache[$type][$to_sync->naturalkey] = $on_db;
                } elseif (isset($this->json->enrols->update_fields)) {

                    $data = $this->set_updatable_fields($to_sync, [], $this->json->enrols->update_fields);
                    unset($data['enrol']);
                    unset($data['role_shortname']);
                    unset($data['course_idnumber']);

                    if (count($data) > 0) {
                        $data['id'] = $on_db->id;
                        $enrolplugin->update_instance($on_db, $data);
                        $on_db = $DB->get_record('enrol', ['id' => $on_db->id]);
                        $this->cache[$type][$to_sync->naturalkey] = $on_db;
                    }
                }

                $this->urls['enrols'][$to_sync->naturalkey] = "$CFG->wwwroot/enrol/editinstance.php?courseid=$course->id&id=$on_db->id&type=$to_sync->enrol";
                return $on_db;
            }
        );
    }


    function sync_enrolments() {
        $this->request_iterator(
            "enrolments",
            ['id', 'timecreated', 'timemodified', 'modifierid', 'enrolid', 'userid', 'sortorder', 'itemid', 'contextid', 'roleid'],
            // Opcionais: ['timestart', 'timeend']
            ['course_idnumber', 'enrol', 'username', 'role_shortname', 'status'],
            function($to_sync, $i) {
                global $CFG;
                $to_sync->naturalkey = "$to_sync->username::$to_sync->course_idnumber::$to_sync->enrol::$to_sync->role_shortname";
                $course = $this->get_cached('course', 'idnumber', $to_sync->course_idnumber, true);
                $course_enrol = $this->get_course_enrol($to_sync->course_idnumber, $to_sync->enrol);
                $user = $this->get_cached('user', 'username', $to_sync->username, true);
                $role =  $this->get_cached('role', 'shortname', $to_sync->role_shortname, true);

                if (!\is_enrolled($course->context, $user)) {
                    $course_enrol->plugin->enrol_user(
                        $course_enrol->instance,
                        $user->id,
                        $role->id,
                        $this->parse_date($to_sync->timestart, 'timestart', time()),
                        $this->parse_date($to_sync->timeend, 'timeend', time()),
                        $to_sync->status
                    );
                } else {
                    $course_enrol->plugin->update_user_enrol(
                        $course_enrol->instance,
                        $user->id,
                        $to_sync->status
                    );
                }
                $this->urls['enrolments'][$to_sync->naturalkey] = "$CFG->wwwroot/user/view.php?course=$course->id&id=$user->id";
                return null;
            }
        );
    }


    function sync_groups() {
        $this->request_iterator(
            "groups",
            ['id', 'courseid', 'timecreated', 'timemodified'],
            ['course_idnumber', 'idnumber', 'name'],
            function($to_sync, $i) {
                global $CFG, $DB;

                $type = 'groups';
                $to_sync->naturalkey = $to_sync->idnumber . '::' . $to_sync->course_idnumber;
                $course = $this->get_cached('course', 'idnumber', $to_sync->course_idnumber, true);

                $on_db = $DB->get_record('groups', ['idnumber' => $to_sync->idnumber, 'courseid' => $course->id]);
                $to_sync->op = $on_db ? 'UPD' : 'ADD';

                if ($to_sync->op == 'ADD') {
                    $data = (array)$to_sync;
                    unset($data['course_idnumber']);
                    unset($data['naturalkey']);
                    unset($data['op']);
                    $data['courseid'] = $course->id;

                    \groups_create_group((object)$data);

                    $on_db = $DB->get_record($type, ['idnumber' => $to_sync->idnumber, 'courseid' => $course->id]);
                    $this->cache[$type][$to_sync->naturalkey] = $on_db;
                } elseif (isset($this->json->enrols->update_fields)) {
                    $data = $this->set_updatable_fields($to_sync, [], $this->json->enrols->update_fields);
                    unset($data['course_idnumber']);

                    if (count($data) > 0) {
                        $data['id'] = $on_db->id;
                        $enrolplugin->update_instance($on_db, $data);
                        $on_db = $DB->get_record('groups', ['id' => $on_db->id]);
                        $this->cache[$type][$to_sync->naturalkey] = $on_db;
                    }
                }

                $this->urls[$type][$to_sync->naturalkey] = "$CFG->wwwroot/group/group.php?courseid=$course->id&id=$on_db->id";
                return $on_db;
            }
        );
    }


    function sync_enrolments() {
        $this->request_iterator(
            "enrolments",
            ['id', 'timecreated', 'timemodified', 'modifierid', 'enrolid', 'userid', 'timestart', 'timeend', 'sortorder', 'itemid', 'contextid', 'roleid'],
            // Opcionais: ['timestart', 'timeend']
            ['course_idnumber', 'enrol', 'username', 'role_shortname', 'status'],
            function($to_sync, $i) {
                // TODO: Tratar o caso de timestart e timeend
                global $CFG, $DB;
                $to_sync->naturalkey = "$to_sync->username::$to_sync->course_idnumber::$to_sync->enrol::$to_sync->role_shortname";
                $course = $this->get_cached('course', 'idnumber', $to_sync->course_idnumber, true);
                $course_enrol = $this->get_course_enrol($to_sync->course_idnumber, $to_sync->enrol);
                $user = $this->get_cached('user', 'username', $to_sync->username, true);
                $role =  $this->get_cached('role', 'shortname', $to_sync->role_shortname, true);

                if (!is_enrolled($course->context, $user)) {
                    $course_enrol->plugin->enrol_user(
                        $course_enrol->instance,
                        $user->id,
                        $course_enrol->roleid,
                        $to_sync->timestart ?? time(),
                        $to_sync->timeend ?? 0,
                        $to_sync->status
                    );
                } else {
                    $course_enrol->plugin->update_user_enrol(
                        $course_enrol->instance,
                        $user->id,
                        $to_sync->status,
                        $to_sync->timestart ?? time(),
                        $to_sync->timeend ?? 0
                    );
                }                
                $this->urls['enrolments'][$to_sync->naturalkey] = "$CFG->wwwroot/user/view.php?course=$course->id&id=$user->id";
                return $on_db;
            }
        );
    }

    
    function sync_groups_members() {
        $this->request_iterator(
            "groups_members",
            ["id", "groupid", "userid", "timeadded", "componente", "itemid"],
            ['course_idnumber', 'group_idnumber', 'username'],
            function($to_sync, $i) {
                global $CFG, $DB;

                // \groups_add_member(
                    $this->get_cached_group_course($to_sync->group_idnumber, $to_sync->course_idnumber)->id;
                //     $this->get_cached('user', 'username', $to_sync->username, true)->id
                // );

                $this->urls['groups_members']["$to_sync->group_idnumber::$to_sync->course_idnumber::$to_sync->username"] = true;
                return $on_db;
            }
        );

    }


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

(new sync_up_enrolments_service())->call();

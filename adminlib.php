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
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class sga_admin_settingspage extends admin_settingpage
{

    public function __construct($admin_mode)
    {
        $plugin_name = 'tool_sga';
        parent::__construct($plugin_name, get_string('pluginname', $plugin_name), 'moodle/site:config', false, NULL);
        $this->setup($admin_mode);
    }

    function _($str, $args = null, $lazyload = false)
    {
        return get_string($str, $this->name);
    }

    function add_heading($name)
    {
        $this->add(new admin_setting_heading("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc")));
    }

    function add_configtext($name, $default = '')
    {
        $this->add(new admin_setting_configtext("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configtextarea($name, $default = '')
    {
        $this->add(new admin_setting_configtextarea("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function add_configcheckbox($name, $default = 0)
    {
        $this->add(new admin_setting_configcheckbox("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    function setup($admin_mode)
    {
        global $CFG;
        if ($admin_mode) {
            $default_enrol = is_dir(dirname(__FILE__) . '/../../enrol/suap/') ? 'suap' : 'manual';
            $this->add_heading('integration_token_header');
            $this->add_configtext("integration_token", 'changeme');
            $this->add_configtext("integration_callback", '');

            $this->add_heading('user_and_enrolment_header');
            $this->add_configtextarea("default_user_preferences", "auth_forcepasswordchange=0\nhtmleditor=0\nemail_bounce_count=1\nemail_send_count=1\nvisual_preference=1");

            $this->add_heading('notes_to_sync_header');
            $this->add_configtext("notes_to_sync", "'N1', 'N2', 'N3' , 'N4', 'NAF'");
        }
    }
}

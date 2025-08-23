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
 * Plugin strings are defined here.
 *
 * @package     tool_sga
 * @category    string
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Integrador SGA';
$string['sga:adminview'] = 'Ver o admin do Integrador SGA';


# Integrador SGA
$string['integration_token_header'] = 'Integrador SGA';
$string['integration_token_header_desc'] = 'Qual será o token utilizado pelo Integrador SGA para se autenticar nesta instalação do Moodle';
$string["integration_token"] = 'Integrador SGA auth token';
$string["integration_token_desc"] = 'Qual será o token utilizado pelo Integrador SGA para se autenticar nesta instalação do Moodle';

# Enviar sincronização
$string["integration_callback"] = 'URL de callback do Integrador SGA';
$string["integration_callback_desc"] = 'Qual a URL de callback do Integrador SGA para esta instalação do Moodle';

# Baixar notas
$string["notes_to_sync_header"] = 'Notas de sincronizar';
$string["notes_to_sync_header_desc"] = 'Configurações para sincronização de notas';
$string["notes_to_sync"] = 'Notas a sincronizar';
$string["notes_to_sync_desc"] = "Notas a sincronizar, para o SUAP, por exemplo, costuma ser: 'N1', 'N2', 'N3' , 'N4', 'NAF'.";

# New user and new enrolment defaults
$string['user_and_enrolment_header'] = 'Novo usuário e novos padrões de inscrição';
$string['user_and_enrolment_header_desc'] = 'Configurações padrão da categoria principal';

$string["default_user_preferences"] = 'Preferências padrão do usuário';
$string["default_user_preferences_desc"] = 'Todo novo usuário (aluno ou professor) terá essas preferências. Use uma linha por preferência. Como um arquivo .ini.';

$string["sync_up_enrolments_task"] = 'Integrador SGA: Sincronizar as inscrições em background';

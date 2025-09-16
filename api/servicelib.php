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


class service
{

    function authenticate()
    {
        $sync_up_auth_token = config('integration_token');

        $headers = getallheaders();
        $authentication_key = array_key_exists('Authentication', $headers) ? "Authentication" : "authentication";
        if (!array_key_exists($authentication_key, $headers)) {
            throw new \Exception("Bad Request - Authentication not informed", 400);
        }

        if ("Token $sync_up_auth_token" != $headers[$authentication_key]) {
            throw new \Exception("Unauthorized", 401);
        }
    }

    function call()
    {
        try {
            $this->authenticate();
            $data = $this->do_call();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error"=> $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    function do_call()
    {
        throw new \Exception("Não implementado", 501);
    }
}

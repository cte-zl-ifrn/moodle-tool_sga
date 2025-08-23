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
 * @category    tool
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function exception_handler($exception)
{
    /*
        200 – 208, 226, 
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    $error_code = $exception->getCode() ?: 500;
    http_response_code($error_code);
    die(json_encode(["error" => ["message" => $exception->getMessage(), "code" => $error_code]]));
}

try {
    require_once('../../../../config.php');
    header('Content-Type: application/json; charset=utf-8');
    set_exception_handler('\tool_sga\exception_handler');

    $whitelist = [
        'sync_up_enrolments',
        'sync_down_grades'
    ];
    $params = explode('&', $_SERVER["QUERY_STRING"]);
    $service_name = $params[0];

    if ((!in_array($service_name, $whitelist))) {
        throw new \Exception("Serviço não existe", 404);
    }
    require_once "$service_name.php";

    $service_class = "\\tool_sga\\$service_name" . "_service";
    $service = new $service_class();
    $service->call();
} catch (\Exception $e) {
    /*
        200 – 208, 226, 
        300 – 305, 307, 308
        400 – 417, 422 – 424, 426, 428 – 429, 431
        500 – 508, 510 – 511
    */
    exception_handler($e);
}

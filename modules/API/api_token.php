<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Module\API\Auth\TokenAuth;
use Gibbon\Module\API\Domain\APIKeyGateway;

// Set content type to JSON
header('Content-Type: application/json');

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Initialize TokenAuth
$tokenAuth = new TokenAuth($container->get(APIKeyGateway::class));

switch ($method) {
    case 'POST':
        // Generate new token
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        
        if (empty($apiKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $result = $tokenAuth->generateToken($apiKey);
        if ($result) {
            echo json_encode($result);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
        }
        break;

    case 'DELETE':
        // Revoke token
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token) || !preg_match('/^Bearer\s+(.*)$/', $token, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Bearer token required']);
            exit;
        }

        $token = $matches[1];
        if ($tokenAuth->revokeToken($token)) {
            http_response_code(204);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ideas.php';
require_once __DIR__ . '/registrations.php';

header('Content-Type: application/json');

$method   = $_SERVER['REQUEST_METHOD'];
$path     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path     = preg_replace('#^/api/?#', '', $path);
$segments = ($path === '' || $path === null) ? [] : explode('/', trim($path, '/'));

if (empty($segments)) {
    json_response(['status' => 'TeamEats API']);
} elseif ($segments[0] === 'ideas') {
    $rest = array_slice($segments, 1);
    if (count($rest) >= 2 && ctype_digit($rest[0]) && $rest[1] === 'registrations') {
        handle_idea_registrations($method, (int)$rest[0]);
    } else {
        handle_ideas($method, $rest);
    }
} elseif ($segments[0] === 'registrations' && isset($segments[1]) && ctype_digit($segments[1])) {
    handle_registration_delete($method, (int)$segments[1]);
} elseif ($segments[0] === 'predefined' && $method === 'GET') {
    $file = __DIR__ . '/../public/predefined_ideas.json';
    json_response(json_decode(file_get_contents($file), true));
} else {
    json_response(['error' => 'Not found'], 404);
}

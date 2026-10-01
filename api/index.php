<?php

$project_root = realpath(__DIR__ . '/..');
$request_path = $_GET['__route'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request_path = rawurldecode($request_path ?: '/');
$relative_path = ltrim($request_path, '/');

if ($relative_path === '') {
    $relative_path = 'index.php';
}

$php_file = realpath($project_root . DIRECTORY_SEPARATOR . $relative_path);
$project_prefix = $project_root . DIRECTORY_SEPARATOR;

if (
    !$php_file
    || strpos($php_file, $project_prefix) !== 0
    || !is_file($php_file)
    || strtolower(pathinfo($php_file, PATHINFO_EXTENSION)) !== 'php'
) {
    http_response_code(404);
    exit('Not Found');
}

require $php_file;
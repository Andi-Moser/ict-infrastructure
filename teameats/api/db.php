<?php

function get_db(): PDO
{
    static $db = null;
    if ($db !== null) return $db;

    $path = getenv('DB_PATH') ?: __DIR__ . '/../data/teameats.db';

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');

    migrate($db);
    return $db;
}

function migrate(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS ideas (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            date        TEXT NOT NULL,
            idea        TEXT NOT NULL,
            description TEXT,
            image_url   TEXT,
            proposed_by TEXT NOT NULL,
            email       TEXT NOT NULL,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS registrations (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            idea_id       INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
            name          TEXT NOT NULL,
            comment       TEXT,
            email         TEXT NOT NULL,
            registered_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_body(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            json_response(['error' => "Missing required field: $field"], 422);
        }
    }
}

<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

function handle_idea_registrations(string $method, int $idea_id): void
{
    if ($method === 'GET') {
        list_registrations($idea_id);
    } elseif ($method === 'POST') {
        create_registration($idea_id);
    } else {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

function handle_registration_delete(string $method, int $id): void
{
    if ($method !== 'DELETE') {
        json_response(['error' => 'Method not allowed'], 405);
    }
    delete_registration($id);
}

function list_registrations(int $idea_id): void
{
    $db   = get_db();
    $stmt = $db->prepare("SELECT id FROM ideas WHERE id = ?");
    $stmt->execute([$idea_id]);

    if (!$stmt->fetch()) {
        json_response(['error' => 'Idea not found'], 404);
    }

    $stmt = $db->prepare("SELECT * FROM registrations WHERE idea_id = ? ORDER BY registered_at ASC");
    $stmt->execute([$idea_id]);
    json_response($stmt->fetchAll());
}

function create_registration(int $idea_id): void
{
    $data = request_body();
    require_fields($data, ['name', 'email']);

    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM ideas WHERE id = ?");
    $stmt->execute([$idea_id]);
    $idea = $stmt->fetch();

    if (!$idea) {
        json_response(['error' => 'Idea not found'], 404);
    }

    // Prevent duplicate: same email for the same idea
    $dup = $db->prepare("SELECT id FROM registrations WHERE idea_id = ? AND email = ?");
    $dup->execute([$idea_id, $data['email']]);
    if ($dup->fetch()) {
        json_response(['error' => 'This email is already registered for this idea'], 409);
    }

    $stmt = $db->prepare("
        INSERT INTO registrations (idea_id, name, comment, email)
        VALUES (:idea_id, :name, :comment, :email)
    ");
    $stmt->execute([
        ':idea_id' => $idea_id,
        ':name'    => $data['name'],
        ':comment' => $data['comment'] ?? null,
        ':email'   => $data['email'],
    ]);

    $id  = (int)$db->lastInsertId();
    $row = $db->prepare("SELECT * FROM registrations WHERE id = ?");
    $row->execute([$id]);
    $registration = $row->fetch();

    send_registration_email($idea, $registration);

    json_response($registration, 201);
}

function delete_registration(int $id): void
{
    $data = request_body();
    $db   = get_db();

    $stmt = $db->prepare("SELECT * FROM registrations WHERE id = ?");
    $stmt->execute([$id]);
    $registration = $stmt->fetch();

    if (!$registration) {
        json_response(['error' => 'Registration not found'], 404);
    }

    if (empty($data['email']) || $data['email'] !== $registration['email']) {
        json_response(['error' => 'Provided email does not match the registration'], 403);
    }

    $db->prepare("DELETE FROM registrations WHERE id = ?")->execute([$id]);
    json_response(['success' => true]);
}

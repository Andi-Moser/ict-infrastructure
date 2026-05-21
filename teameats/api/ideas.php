<?php

require_once __DIR__ . '/db.php';

function handle_ideas(string $method, array $segments): void
{
    if ($method === 'GET' && empty($segments)) {
        list_ideas();
    } elseif ($method === 'POST' && empty($segments)) {
        create_idea();
    } elseif ($method === 'DELETE' && count($segments) === 1 && ctype_digit($segments[0])) {
        delete_idea((int)$segments[0]);
    } else {
        json_response(['error' => 'Not found'], 404);
    }
}

function list_ideas(): void
{
    $db   = get_db();
    $date = $_GET['date'] ?? null;

    $sql = "
        SELECT i.*, COUNT(r.id) AS registration_count
        FROM ideas i
        LEFT JOIN registrations r ON r.idea_id = i.id
    ";
    if ($date) {
        $sql .= " WHERE i.date = :date";
    }
    $sql .= " GROUP BY i.id ORDER BY i.date ASC, i.id ASC";

    $stmt = $db->prepare($sql);
    if ($date) {
        $stmt->bindValue(':date', $date);
    }
    $stmt->execute();

    json_response($stmt->fetchAll());
}

function create_idea(): void
{
    $data = request_body();
    require_fields($data, ['date', 'idea', 'proposed_by', 'email']);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
        json_response(['error' => 'Invalid date format, expected YYYY-MM-DD'], 422);
    }

    $db   = get_db();
    $stmt = $db->prepare("
        INSERT INTO ideas (date, idea, description, image_url, proposed_by, email)
        VALUES (:date, :idea, :description, :image_url, :proposed_by, :email)
    ");
    $stmt->execute([
        ':date'        => $data['date'],
        ':idea'        => $data['idea'],
        ':description' => $data['description'] ?? null,
        ':image_url'   => $data['image_url']   ?? null,
        ':proposed_by' => $data['proposed_by'],
        ':email'       => $data['email'],
    ]);

    $id = (int)$db->lastInsertId();
    $row = $db->prepare("SELECT *, 0 AS registration_count FROM ideas WHERE id = ?");
    $row->execute([$id]);

    json_response($row->fetch(), 201);
}

function delete_idea(int $id): void
{
    $db   = get_db();
    $data = request_body();

    $stmt = $db->prepare("SELECT * FROM ideas WHERE id = ?");
    $stmt->execute([$id]);
    $idea = $stmt->fetch();

    if (!$idea) {
        json_response(['error' => 'Idea not found'], 404);
    }

    $count = $db->prepare("SELECT COUNT(*) FROM registrations WHERE idea_id = ?");
    $count->execute([$id]);
    $has_registrations = (int)$count->fetchColumn() > 0;

    if ($has_registrations && ($data['email'] ?? '') !== $idea['email']) {
        json_response(['error' => 'Idea has registrations; provide the proposer email to delete anyway'], 409);
    }

    $db->prepare("DELETE FROM ideas WHERE id = ?")->execute([$id]);
    json_response(['success' => true]);
}

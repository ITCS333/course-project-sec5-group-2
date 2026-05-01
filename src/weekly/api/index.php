<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];

$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function sendResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// WEEKS FUNCTIONS
// ============================================================

function getAllWeeks(PDO $db): void {
    $sql    = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowed_sort  = ['title', 'start_date'];
    $allowed_order = ['asc', 'desc'];
    $sort  = in_array($_GET['sort']  ?? '', $allowed_sort)  ? $_GET['sort']  : 'start_date';
    $order = in_array($_GET['order'] ?? '', $allowed_order) ? $_GET['order'] : 'asc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll();

    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch();

    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $week['links'] = json_decode($week['links'], true) ?? [];
    sendResponse(['success' => true, 'data' => $week]);
}

function createWeek(PDO $db, array $data): void {
    if (empty($data['title']) || empty($data['start_date'])) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required'], 400);
    }

    $title       = sanitizeInput($data['title']);
    $start_date  = sanitizeInput($data['start_date']);
    $description = sanitizeInput($data['description'] ?? '');
    $links       = json_encode(is_array($data['links'] ?? null) ? $data['links'] : []);

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $start_date, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week created', 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week'], 500);
    }
}

function updateWeek(PDO $db, array $data): void {
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'ID is required'], 400);
    }

    $id   = $data['id'];
    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $params[] = sanitizeInput($data['title']);
    }
    if (isset($data['start_date'])) {
        if (!validateDate($data['start_date'])) {
            sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
        }
        $fields[] = 'start_date = ?';
        $params[] = $data['start_date'];
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = sanitizeInput($data['description']);
    }
    if (isset($data['links'])) {
        $fields[] = 'links = ?';
        $params[] = json_encode(is_array($data['links']) ? $data['links'] : []);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $id;
    $sql      = "UPDATE weeks SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt     = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse(['success' => true, 'message' => 'Week updated']);
}

function deleteWeek(PDO $db, $id): void {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete'], 500);
    }
}

// ============================================================
// COMMENTS FUNCTIONS
// ============================================================

function getCommentsByWeek(PDO $db, $weekId): void {
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void {
    if (empty($data['week_id']) || empty(trim($data['author'] ?? '')) || empty(trim($data['text'] ?? ''))) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required'], 400);
    }

    if (!is_numeric($data['week_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid week ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$data['week_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data['week_id'], $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId   = $db->lastInsertId();
        $comment = ['id' => $newId, 'week_id' => $data['week_id'], 'author' => $author, 'text' => $text, 'created_at' => date('Y-m-d H:i:s')];
        sendResponse(['success' => true, 'message' => 'Comment added', 'id' => $newId, 'data' => $comment], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to add comment'], 500);
    }
}

function deleteComment(PDO $db, $commentId): void {
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}

// ============================================================
// ROUTER
// ============================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}
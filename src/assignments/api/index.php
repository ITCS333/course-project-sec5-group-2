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
$db = getDBConnection();

$method       = $_SERVER['REQUEST_METHOD'];
$rawData      = file_get_contents('php://input');
$data         = json_decode($rawData, true) ?? [];
$action       = $_GET['action']        ?? null;
$id           = $_GET['id']            ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

function getAllAssignments(PDO $db): void
{
    $sql    = 'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments';
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort  = ['title', 'due_date', 'created_at'];
    $allowedOrder = ['asc', 'desc'];
    $sort  = in_array($_GET['sort']  ?? '', $allowedSort)  ? $_GET['sort']  : 'due_date';
    $order = in_array($_GET['order'] ?? '', $allowedOrder) ? $_GET['order'] : 'asc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $rows]);
}

function getAssignmentById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }

    $assignment['files'] = json_decode($assignment['files'], true) ?? [];
    sendResponse(['success' => true, 'data' => $assignment]);
}

function createAssignment(PDO $db, array $data): void
{
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'title, description, and due_date are required'], 400);
    }

    $title       = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $due_date    = trim($data['due_date']);

    if (!validateDate($due_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid due_date format. Use YYYY-MM-DD'], 400);
    }

    $files = (isset($data['files']) && is_array($data['files']))
        ? json_encode($data['files'])
        : json_encode([]);

    $stmt = $db->prepare('INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)');
    $stmt->execute([$title, $description, $due_date, $files]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Assignment created', 'id' => (int) $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create assignment'], 500);
    }
}

function updateAssignment(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'id is required'], 400);
    }

    $id   = $data['id'];
    $stmt = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $params[] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = sanitizeInput($data['description']);
    }
    if (isset($data['due_date'])) {
        if (!validateDate($data['due_date'])) {
            sendResponse(['success' => false, 'message' => 'Invalid due_date format. Use YYYY-MM-DD'], 400);
        }
        $fields[] = 'due_date = ?';
        $params[] = $data['due_date'];
    }
    if (isset($data['files'])) {
        $fields[] = 'files = ?';
        $params[] = is_array($data['files']) ? json_encode($data['files']) : json_encode([]);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $id;
    $stmt     = $db->prepare('UPDATE assignments SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);

    sendResponse(['success' => true, 'message' => 'Assignment updated']);
}

function deleteAssignment(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }

    $stmt = $db->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Assignment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete assignment'], 500);
    }
}

// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if (!$assignmentId || !is_numeric($assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment_id'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([$assignmentId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    if (
        empty($data['assignment_id']) ||
        !isset($data['author'])       || trim($data['author']) === '' ||
        !isset($data['text'])         || trim($data['text'])   === ''
    ) {
        sendResponse(['success' => false, 'message' => 'assignment_id, author, and text are required'], 400);
    }

    if (!is_numeric($data['assignment_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid assignment_id'], 400);
    }

    $assignmentId = $data['assignment_id'];
    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Assignment not found'], 404);
    }

    $author = sanitizeInput($data['author']);
    $text   = trim($data['text']);

    $stmt = $db->prepare('INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$assignmentId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId   = (int) $db->lastInsertId();
        $comment = [
            'id'            => $newId,
            'assignment_id' => (int) $assignmentId,
            'author'        => $author,
            'text'          => $text,
            'created_at'    => date('Y-m-d H:i:s')
        ];
        sendResponse(['success' => true, 'message' => 'Comment created', 'id' => $newId, 'data' => $comment], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment_id'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM comments_assignment WHERE id = ?');
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_assignment WHERE id = ?');
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createAssignment($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
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
?>
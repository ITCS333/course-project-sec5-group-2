<?php
/**
 * Course Resources API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/db.php';
}
$db = getDBConnection();

$method     = $_SERVER['REQUEST_METHOD'];
$rawData    = file_get_contents('php://input');
$data       = json_decode($rawData, true) ?? [];

$action     = $_GET['action']      ?? null;
$id         = $_GET['id']          ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId  = $_GET['comment_id']  ?? null;

// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources($db) {
    $sql    = 'SELECT id, title, description, link, created_at FROM resources';
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= ' WHERE (title LIKE :search OR description LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort))
             ? $_GET['sort'] : 'created_at';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([(int)$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    sendResponse(['success' => true, 'data' => $resource]);
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(['success' => false, 'message' => 'Title and link are required.'], 400);
    }

    $title       = trim($data['title']);
    $link        = trim($data['link']);
    $description = isset($data['description']) ? trim($data['description']) : '';

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL provided.'], 400);
    }

    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id'      => $db->lastInsertId()
        ], 201);
    }
    sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
}

function updateResource($db, $data) {
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Resource ID is required.'], 400);
    }

    $id = (int)$data['id'];

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $values[] = trim($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = trim($data['description']);
    }
    if (isset($data['link'])) {
        $link = trim($data['link']);
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL provided.'], 400);
        }
        $fields[] = 'link = ?';
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $values[] = $id;
    $sql      = 'UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}

function deleteResource($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }

    $id = (int)$resourceId;

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    }
    sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int)$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment($db, $data) {
    if (!isset($data['resource_id']) || $data['resource_id'] === '' ||
        empty($data['author']) || empty($data['text'])) {
        sendResponse(['success' => false, 'message' => 'resource_id, author, and text are required.'], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }

    $resourceId = (int)$data['resource_id'];

    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $author = trim($data['author']);
    $text   = trim($data['text']);

    $stmt = $db->prepare('INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$resourceId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        $fetch = $db->prepare(
            'SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE id = ?'
        );
        $fetch->execute([$newId]);
        $comment = $fetch->fetch(PDO::FETCH_ASSOC);
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => $newId,
            'data'    => $comment
        ], 201);
    }
    sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
}

function deleteComment($db, $commentId) {
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID.'], 400);
    }

    $id = (int)$commentId;

    $check = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    }
    sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An error occurred.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) {
        $data = ['success' => false, 'message' => (string)$data];
    }
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    return ['valid' => (count($missing) === 0), 'missing' => $missing];
}

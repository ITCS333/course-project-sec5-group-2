<?php
/**
 * Discussion Board API
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

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

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action  = $_GET['action']   ?? null;
$id      = $_GET['id']       ?? null;
$topicId = $_GET['topic_id'] ?? null;


// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

function getAllTopics(PDO $db): void
{
    $sql    = 'SELECT id, subject, message, author, created_at FROM topics';
    $params = [];

    $search = $_GET['search'] ?? '';
    if ($search !== '') {
        $sql .= ' WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSort  = ['subject', 'author', 'created_at'];
    $allowedOrder = ['asc', 'desc'];

    $sort  = (isset($_GET['sort'])  && in_array($_GET['sort'],  $allowedSort,  true)) ? $_GET['sort']  : 'created_at';
    $order = (isset($_GET['order']) && in_array($_GET['order'], $allowedOrder, true)) ? $_GET['order'] : 'desc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $topics = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $topics]);
}


function getTopicById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE id = ?');
    $stmt->execute([(int)$id]);
    $topic = $stmt->fetch();

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }
}


function createTopic(PDO $db, array $data): void
{
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');
    $author  = trim($data['author']  ?? '');

    if ($subject === '' || $message === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'subject, message, and author are required.'], 400);
    }

    $subject = sanitizeInput($subject);
    $message = sanitizeInput($message);
    $author  = sanitizeInput($author);

    $stmt = $db->prepare('INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)');
    $stmt->execute([$subject, $message, $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Topic created.', 'id' => $newId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create topic.'], 500);
    }
}


function updateTopic(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing id.'], 400);
    }

    $id = (int)$data['id'];

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $setClauses = [];
    $values     = [];

    if (isset($data['subject']) && trim($data['subject']) !== '') {
        $setClauses[] = 'subject = ?';
        $values[]     = sanitizeInput(trim($data['subject']));
    }
    if (isset($data['message']) && trim($data['message']) !== '') {
        $setClauses[] = 'message = ?';
        $values[]     = sanitizeInput(trim($data['message']));
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided.'], 400);
    }

    $values[] = $id;
    $sql      = 'UPDATE topics SET ' . implode(', ', $setClauses) . ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Topic updated.']);
}


function deleteTopic(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing id.'], 400);
    }

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([(int)$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM topics WHERE id = ?');
    $stmt->execute([(int)$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Topic deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete topic.'], 500);
    }
}


// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

function getRepliesByTopicId(PDO $db, $topicId): void
{
    if ($topicId === null || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing topic_id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, topic_id, text, author, created_at
         FROM replies
         WHERE topic_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int)$topicId]);
    $replies = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $replies]);
}


function createReply(PDO $db, array $data): void
{
    $topicId = trim((string)($data['topic_id'] ?? ''));
    $text    = trim($data['text']     ?? '');
    $author  = trim($data['author']   ?? '');

    if ($topicId === '' || $text === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'topic_id, text, and author are required.'], 400);
    }

    if (!is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'topic_id must be numeric.'], 400);
    }

    $topicId = (int)$topicId;

    $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $check->execute([$topicId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $text   = sanitizeInput($text);
    $author = sanitizeInput($author);

    $stmt = $db->prepare('INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)');
    $stmt->execute([$topicId, $text, $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();

        $fetch = $db->prepare('SELECT id, topic_id, text, author, created_at FROM replies WHERE id = ?');
        $fetch->execute([$newId]);
        $reply = $fetch->fetch();

        sendResponse(['success' => true, 'message' => 'Reply created.', 'id' => $newId, 'data' => $reply], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create reply.'], 500);
    }
}


function deleteReply(PDO $db, $replyId): void
{
    if ($replyId === null || !is_numeric($replyId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing reply id.'], 400);
    }

    $check = $db->prepare('SELECT id FROM replies WHERE id = ?');
    $check->execute([(int)$replyId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Reply not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM replies WHERE id = ?');
    $stmt->execute([(int)$replyId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Reply deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete reply.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id !== null) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateTopic($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);

} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
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
$id     = $_GET['id']     ?? null;
$action = $_GET['action'] ?? null;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }
    exit;
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// FUNCTIONS
// ============================================================

function getUsers($db) {
    $sql    = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowed_sort  = ['name', 'email', 'is_admin'];
    $allowed_order = ['asc', 'desc'];
    $sort  = in_array($_GET['sort']  ?? '', $allowed_sort)  ? $_GET['sort']  : 'name';
    $order = in_array($_GET['order'] ?? '', $allowed_order) ? $_GET['order'] : 'asc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUserById($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse('Invalid ID', 400);
    }
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendResponse('User not found', 404);
    }
    sendResponse($user);
}

function createUser($db, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse('Name, email, and password are required', 400);
    }

    $name     = sanitizeInput($data['name']);
    $email    = sanitizeInput($data['email']);
    $password = $data['password'];
    $is_admin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;

    if (!validateEmail($email)) {
        sendResponse('Invalid email format', 400);
    }

    if (strlen($password) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse('Email already exists', 409);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hashed, $is_admin]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['id' => $db->lastInsertId()], 201);
    } else {
        sendResponse('Failed to create user', 500);
    }
}

function updateUser($db, $data) {
    if (empty($data['id'])) {
        sendResponse('ID is required', 400);
    }

    $id   = $data['id'];
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse('User not found', 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = sanitizeInput($data['name']);
    }
    if (isset($data['email'])) {
        if (!validateEmail($data['email'])) {
            sendResponse('Invalid email format', 400);
        }
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$data['email'], $id]);
        if ($check->fetch()) {
            sendResponse('Email already exists', 409);
        }
        $fields[] = 'email = ?';
        $params[] = sanitizeInput($data['email']);
    }
    if (isset($data['is_admin'])) {
        $fields[] = 'is_admin = ?';
        $params[] = $data['is_admin'] == 1 ? 1 : 0;
    }

    if (empty($fields)) {
        sendResponse('No fields to update', 400);
    }

    $params[] = $id;
    $stmt     = $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);
    sendResponse('User updated');
}

function deleteUser($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse('Invalid ID', 400);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse('User not found', 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse('User deleted');
    } else {
        sendResponse('Failed to delete user', 500);
    }
}

function changePassword($db, $data) {
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse('ID, current_password, and new_password are required', 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse('User not found', 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse('Current password is incorrect', 401);
    }

    $hashed = password_hash($data['new_password'], PASSWORD_DEFAULT);
    $stmt   = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $data['id']]);

    if ($stmt->rowCount() > 0) {
        sendResponse('Password changed successfully');
    } else {
        sendResponse('Failed to change password', 500);
    }
}

// ============================================================
// ROUTER
// ============================================================

try {
    if ($method === 'GET') {
        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateUser($db, $data);
    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);
    } else {
        sendResponse('Method not allowed', 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse('Database error', 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>
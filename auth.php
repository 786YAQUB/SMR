<?php
// auth.php - Handles login, register, logout, profile
require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'register':   handleRegister($data); break;
    case 'login':      handleLogin($data);    break;
    case 'logout':     handleLogout();        break;
    case 'profile':    handleProfile($data);  break;
    case 'check':      checkSession();        break;
    default: jsonResponse(['error' => 'Invalid action'], 400);
}

// ── Register ──────────────────────────────────────────────
function handleRegister($data) {
    $db = getDB();
    $name  = trim($data['full_name'] ?? '');
    $email = trim(strtolower($data['email'] ?? ''));
    $pass  = $data['password'] ?? '';
    $phone = trim($data['phone'] ?? '');
    $dob   = $data['date_of_birth'] ?? null;
    $gender= $data['gender'] ?? null;

    if (!$name || !$email || !$pass)
        jsonResponse(['error' => 'Name, email and password are required'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonResponse(['error' => 'Invalid email address'], 400);
    if (strlen($pass) < 6)
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonResponse(['error' => 'Email already registered'], 409);

    $colors = ['#4ecdc4','#ff6b6b','#45b7d1','#96ceb4','#feca57','#ff9ff3','#54a0ff'];
    $color  = $colors[array_rand($colors)];
    $hash   = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (full_name, email, password, phone, date_of_birth, gender, avatar_color) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$name, $email, $hash, $phone ?: null, $dob ?: null, $gender ?: null, $color]);

    $uid = $db->lastInsertId();
    $_SESSION['user_id']   = $uid;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email']= $email;
    $_SESSION['user_color']= $color;

    jsonResponse(['success' => true, 'user' => ['id'=>$uid,'name'=>$name,'email'=>$email,'color'=>$color]]);
}

// ── Login ──────────────────────────────────────────────────
function handleLogin($data) {
    $db    = getDB();
    $email = trim(strtolower($data['email'] ?? ''));
    $pass  = $data['password'] ?? '';

    if (!$email || !$pass) jsonResponse(['error' => 'Email and password required'], 400);

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password']))
        jsonResponse(['error' => 'Invalid email or password'], 401);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email']= $user['email'];
    $_SESSION['user_color']= $user['avatar_color'];

    unset($user['password']);
    jsonResponse(['success' => true, 'user' => $user]);
}

// ── Logout ─────────────────────────────────────────────────
function handleLogout() {
    session_destroy();
    jsonResponse(['success' => true]);
}

// ── Check session ──────────────────────────────────────────
function checkSession() {
    if (!empty($_SESSION['user_id'])) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id,full_name,email,phone,date_of_birth,gender,avatar_color,created_at FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        jsonResponse(['loggedIn' => true, 'user' => $user]);
    }
    jsonResponse(['loggedIn' => false]);
}

// ── Update profile ──────────────────────────────────────────
function handleProfile($data) {
    $uid = requireLogin();
    $db  = getDB();

    $name   = trim($data['full_name'] ?? '');
    $phone  = trim($data['phone'] ?? '');
    $dob    = $data['date_of_birth'] ?? null;
    $gender = $data['gender'] ?? null;

    if (!$name) jsonResponse(['error' => 'Name is required'], 400);

    // Password change (optional)
    if (!empty($data['new_password'])) {
        if (strlen($data['new_password']) < 6)
            jsonResponse(['error' => 'New password too short'], 400);
        // verify current password
        $s = $db->prepare("SELECT password FROM users WHERE id=?");
        $s->execute([$uid]);
        $row = $s->fetch();
        if (!password_verify($data['current_password'] ?? '', $row['password']))
            jsonResponse(['error' => 'Current password incorrect'], 401);
        $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
    }

    $db->prepare("UPDATE users SET full_name=?,phone=?,date_of_birth=?,gender=? WHERE id=?")
       ->execute([$name, $phone ?: null, $dob ?: null, $gender ?: null, $uid]);

    $_SESSION['user_name'] = $name;
    jsonResponse(['success' => true, 'message' => 'Profile updated']);
}
?>
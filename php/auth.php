<?php
require_once 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'status') {
    header('Content-Type: application/json');
    $payload = [
        'logged_in' => isset($_SESSION['user_id']),
        'user_id'   => $_SESSION['user_id'] ?? null,
        'name'      => $_SESSION['user_name'] ?? null,
        'role'      => $_SESSION['user_role'] ?? null
    ];

    if (isset($_SESSION['user_id'])) {
        $sessionUserId = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT email, phone, cnic, city FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $sessionUserId);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($details) {
            $payload = array_merge($payload, $details);
        }
    }

    echo json_encode($payload);
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ../index.html?success=' . urlencode('You have logged out successfully.'));
    exit;
}

if ($action === 'register') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role            = trim($_POST['role'] ?? 'customer');
    $cnic            = trim($_POST['cnic'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $companyName     = trim($_POST['company_name'] ?? '');

    if (!in_array($role, ['customer', 'owner'], true)) {
        redirectWithFlash('../register.html', 'Invalid account type.', 'error');
    }

    if (!$firstName || !$lastName || !$email || !$phone || !$password) {
        redirectWithFlash('../register.html?role=' . $role, 'All required fields must be completed.', 'error');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithFlash('../register.html?role=' . $role, 'Please enter a valid email address.', 'error');
    }

    if (strlen($password) < 6) {
        redirectWithFlash('../register.html?role=' . $role, 'Password must be at least 6 characters.', 'error');
    }

    if ($password !== $confirmPassword) {
        redirectWithFlash('../register.html?role=' . $role, 'Passwords do not match.', 'error');
    }

    if ($role === 'owner' && (!$cnic || !$city || !$companyName)) {
        redirectWithFlash('../register.html?role=owner', 'Car owner accounts require CNIC, city and company name.', 'error');
    }

    $check = $conn->prepare('SELECT id FROM users WHERE email = ? OR login_id = ? LIMIT 1');
    $check->bind_param('ss', $email, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        redirectWithFlash('../register.html?role=' . $role, 'This email is already registered.', 'error');
    }
    $check->close();

    $fullName      = trim($firstName . ' ' . $lastName);
    $plainPassword = $password;
    $status        = $role === 'owner' ? 'pending' : 'active';
    $loginId       = $email;

    $stmt = $conn->prepare(
        'INSERT INTO users
            (login_id, full_name, email, phone, password, role, cnic, city, company_name, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ssssssssss',
        $loginId,
        $fullName,
        $email,
        $phone,
        $plainPassword,
        $role,
        $cnic,
        $city,
        $companyName,
        $status
    );

    if (!$stmt->execute()) {
        $stmt->close();
        redirectWithFlash('../register.html?role=' . $role, 'Registration failed. Please try again.', 'error');
    }

    $userId = $conn->insert_id;
    $stmt->close();

    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $fullName;
    $_SESSION['user_role'] = $role;

    if ($role === 'owner') {
        redirectWithFlash('../dashboard.html', 'Owner account created. Admin approval may be required before listings go live.', 'success');
    }

    redirectWithFlash('../dashboard.html', 'Customer account created successfully.', 'success');
}

if ($action === 'login') {
    $loginRole = trim($_POST['login_role'] ?? 'customer');
    $loginId   = trim($_POST['login_id'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!in_array($loginRole, ['customer', 'owner', 'admin'], true)) {
        redirectWithFlash('../login.html', 'Please choose a valid login type.', 'error');
    }

    if (!$loginId || !$password) {
        redirectWithFlash('../login.html?role=' . $loginRole, 'Enter your ID/email and password.', 'error');
    }

    if ($loginRole !== 'admin') {
        $loginId = strtolower($loginId);
    }

    $stmt = $conn->prepare(
        'SELECT id, full_name, password, role, status
           FROM users
          WHERE role = ?
            AND (login_id = ? OR email = ?)
          LIMIT 1'
    );
    $stmt->bind_param('sss', $loginRole, $loginId, $loginId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        redirectWithFlash('../login.html?role=' . $loginRole, 'Invalid login details.', 'error');
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (($user['status'] ?? '') === 'blocked') {
        redirectWithFlash('../login.html?role=' . $loginRole, 'This account is blocked. Please contact support.', 'error');
    }

    if (!passwordMatches($password, $user['password'])) {
        redirectWithFlash('../login.html?role=' . $loginRole, 'Invalid login details.', 'error');
    }

    $userId = (int) $user['id'];
    if (isStoredPasswordHash($user['password'])) {
        $plainUpdate = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $plainUpdate->bind_param('si', $password, $userId);
        $plainUpdate->execute();
        $plainUpdate->close();
    }

    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    if ($user['role'] === 'admin') {
        header('Location: ../admin.html');
        exit;
    }

    header('Location: ../dashboard.html');
    exit;
}

header('Location: ../index.html');
exit;

function redirectWithFlash($page, $message, $type = 'error') {
    $separator = strpos($page, '?') === false ? '?' : '&';
    header('Location: ' . $page . $separator . $type . '=' . urlencode($message));
    exit;
}

function passwordMatches($inputPassword, $storedPassword) {
    if (hash_equals((string) $storedPassword, (string) $inputPassword)) {
        return true;
    }

    return isStoredPasswordHash($storedPassword) && password_verify($inputPassword, $storedPassword);
}

function isStoredPasswordHash($storedPassword) {
    $hashInfo = password_get_info((string) $storedPassword);
    return !empty($hashInfo['algo']);
}

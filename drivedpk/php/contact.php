<?php
/* ============================================================
   DrivePK — contact.php
   Receives POST from contact.html form.
   Saves the message to the messages table.
   Redirects back to contact.html with success or error.
   ============================================================ */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../contact.html');
    exit;
}

/* ---- collect fields ---- */
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$subject = trim($_POST['subject'] ?? 'other');
$message = trim($_POST['message'] ?? '');

/* ---- validate ---- */
if (!$name || !$email || !$message) {
    header('Location: ../contact.html?error=' . urlencode('Name, email and message are required.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../contact.html?error=' . urlencode('Please enter a valid email address.'));
    exit;
}

/* ---- save to database ---- */
$stmt = $conn->prepare(
    "INSERT INTO messages (name, email, phone, subject, message)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: ../contact.html?success=' . urlencode("Thanks {$name}! We've received your message and will reply within 2 hours."));
    exit;
} else {
    $stmt->close();
    header('Location: ../contact.html?error=' . urlencode('Could not send your message. Please try again.'));
    exit;
}
?>

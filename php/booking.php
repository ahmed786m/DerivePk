<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

ensureBookingWorkflowSchema($conn);

if ($action === 'list_my_bookings') {
    requireCustomerSession();

    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT b.id, b.booking_ref, b.car_id, b.full_name, b.email, b.phone, b.cnic,
                b.pickup_date, b.dropoff_date, b.pickup_city, b.total_days, b.total_price,
                b.notes, b.owner_note, b.status, b.created_at,
                c.brand, c.model, c.year, c.image, c.available,
                owner.full_name AS owner_name, owner.phone AS owner_phone
           FROM bookings b
           JOIN cars c ON c.id = b.car_id
      LEFT JOIN users owner ON owner.id = c.owner_id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC, b.id DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();

    respond(true, 'Bookings loaded.', ['bookings' => $bookings]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.');
}

requireCustomerSession();

$userId      = (int) $_SESSION['user_id'];
$firstName   = trim($_POST['first_name'] ?? '');
$lastName    = trim($_POST['last_name'] ?? '');
$fullName    = trim($firstName . ' ' . $lastName);
$email       = strtolower(trim($_POST['email'] ?? ''));
$phone       = trim($_POST['phone'] ?? '');
$cnic        = trim($_POST['cnic'] ?? '');
$carId       = (int) ($_POST['car_id'] ?? 0);
$pickupDate  = trim($_POST['pickup_date'] ?? '');
$dropoffDate = trim($_POST['dropoff_date'] ?? '');
$pickupCity  = trim($_POST['city'] ?? $_POST['pickup_city'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if (!$fullName || !$email || !$phone || !$cnic || !$carId || !$pickupDate || !$dropoffDate || !$pickupCity) {
    respond(false, 'Please complete all required booking fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.');
}

try {
    $pickup  = new DateTime($pickupDate);
    $dropoff = new DateTime($dropoffDate);
} catch (Exception $e) {
    respond(false, 'Please choose valid booking dates.');
}

$days = (int) $pickup->diff($dropoff)->days;
if ($days <= 0 || $dropoff <= $pickup) {
    respond(false, 'Drop-off date must be after pick-up date.');
}

$carStmt = $conn->prepare(
    'SELECT c.price_per_day, c.available, c.owner_id, c.brand, c.model, u.full_name AS owner_name
       FROM cars c
  LEFT JOIN users u ON u.id = c.owner_id
      WHERE c.id = ?
      LIMIT 1'
);
$carStmt->bind_param('i', $carId);
$carStmt->execute();
$carResult = $carStmt->get_result();

if ($carResult->num_rows === 0) {
    $carStmt->close();
    respond(false, 'Selected car was not found.');
}

$car = $carResult->fetch_assoc();
$carStmt->close();

if ((int) $car['available'] !== 1) {
    respond(false, 'Selected car is currently booked. Please choose a free car.');
}

if (empty($car['owner_id'])) {
    respond(false, 'This car is missing an owner account, so it cannot receive booking requests yet. Please contact admin.');
}

$serviceFee = 500;
$totalPrice = ((int) $car['price_per_day'] * $days) + $serviceFee;
$bookingRef = generateBookingRef($conn);

$stmt = $conn->prepare(
    'INSERT INTO bookings
        (booking_ref, user_id, car_id, full_name, email, phone, cnic,
         pickup_date, dropoff_date, pickup_city, total_days, total_price, notes, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
);

$stmt->bind_param(
    'siisssssssiis',
    $bookingRef,
    $userId,
    $carId,
    $fullName,
    $email,
    $phone,
    $cnic,
    $pickupDate,
    $dropoffDate,
    $pickupCity,
    $days,
    $totalPrice,
    $notes
);

if (!$stmt->execute()) {
    $stmt->close();
    respond(false, 'Booking request failed. Please try again.');
}

$stmt->close();

respond(true, 'Booking request sent to the car owner for approval.', [
    'booking_ref' => $bookingRef,
    'total_days'  => $days,
    'total_price' => $totalPrice,
    'status'      => 'pending',
    'owner_name'  => $car['owner_name'] ?? 'car owner'
]);

function requireCustomerSession() {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
        respond(false, 'Please login as a customer before booking a car.', [
            'login_required' => true
        ]);
    }
}

function ensureBookingWorkflowSchema($conn) {
    $ownerColumn = $conn->query("SHOW COLUMNS FROM cars LIKE 'owner_id'");
    if ($ownerColumn && $ownerColumn->num_rows === 0) {
        $conn->query("ALTER TABLE cars ADD COLUMN owner_id INT DEFAULT NULL AFTER id");
    }

    $conn->query(
        "UPDATE cars
            SET owner_id = (SELECT id FROM users WHERE role = 'owner' AND status <> 'blocked' ORDER BY id ASC LIMIT 1)
          WHERE owner_id IS NULL
            AND EXISTS (SELECT 1 FROM users WHERE role = 'owner' AND status <> 'blocked')"
    );

    $status = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
    if ($status && ($row = $status->fetch_assoc()) && strpos($row['Type'], 'rejected') === false) {
        $conn->query(
            "ALTER TABLE bookings
             MODIFY status ENUM('pending','confirmed','active','completed','cancelled','rejected')
             NOT NULL DEFAULT 'pending'"
        );
    }

    $ownerNote = $conn->query("SHOW COLUMNS FROM bookings LIKE 'owner_note'");
    if ($ownerNote && $ownerNote->num_rows === 0) {
        $conn->query("ALTER TABLE bookings ADD COLUMN owner_note VARCHAR(255) DEFAULT NULL AFTER notes");
    }
}

function generateBookingRef($conn) {
    do {
        $ref = 'DPK-' . date('ymd') . '-' . random_int(100, 999);
        $stmt = $conn->prepare('SELECT id FROM bookings WHERE booking_ref = ? LIMIT 1');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $ref;
}

function respond($success, $message, array $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

<?php


require_once 'db.php'; // starts session

/* ---- admin guard: if not logged in as admin, kick out ---- */
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.html?role=admin&error=' . urlencode('Please login as admin.'));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

ensureCarRequestsTable($conn);
ensureCarsOwnerColumn($conn);
ensureCarGalleryColumns($conn);
ensureBookingWorkflowSchema($conn);

if ($action === 'list_car_requests') {
    header('Content-Type: application/json');

    $sql = "SELECT cr.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
              FROM car_requests cr
              JOIN users u ON u.id = cr.owner_id
             ORDER BY FIELD(cr.status, 'pending', 'approved', 'rejected'), cr.created_at DESC";
    $result = $conn->query($sql);
    $requests = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }

    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

if ($action === 'summary') {
    header('Content-Type: application/json');

    $summary = [
        'cars' => 0,
        'bookings' => 0,
        'users' => 0,
        'revenue' => 0
    ];

    $summary['cars'] = getScalar($conn, 'SELECT COUNT(*) AS total FROM cars');
    $summary['bookings'] = getScalar($conn, 'SELECT COUNT(*) AS total FROM bookings');
    $summary['users'] = getScalar($conn, 'SELECT COUNT(*) AS total FROM users');
    $summary['revenue'] = getScalar($conn, "SELECT COALESCE(SUM(total_price), 0) AS total FROM bookings WHERE status IN ('confirmed','active','completed')");

    echo json_encode(['success' => true, 'summary' => $summary]);
    exit;
}

if ($action === 'list_bookings') {
    header('Content-Type: application/json');

    $sql = 'SELECT b.id, b.booking_ref, b.user_id, b.car_id, b.full_name, b.email, b.phone, b.cnic,
                   b.pickup_date, b.dropoff_date, b.pickup_city, b.total_days, b.total_price,
                   b.notes, b.owner_note, b.status, b.created_at,
                   c.brand, c.model, c.year, c.type, c.fuel_type, c.transmission, c.seats,
                   c.price_per_day, c.image, c.available,
                   owner.full_name AS owner_name, owner.email AS owner_email, owner.phone AS owner_phone,
                   account.full_name AS account_name, account.email AS account_email
              FROM bookings b
              JOIN cars c ON c.id = b.car_id
         LEFT JOIN users owner ON owner.id = c.owner_id
         LEFT JOIN users account ON account.id = b.user_id
             ORDER BY b.created_at DESC, b.id DESC';

    $result = $conn->query($sql);
    $bookings = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }

    echo json_encode(['success' => true, 'bookings' => $bookings]);
    exit;
}

if ($action === 'approve_car_request') {
    $requestId = (int) ($_POST['request_id'] ?? $_GET['id'] ?? 0);

    if (!$requestId) {
        redirectAdmin('Invalid owner car request.', 'error');
    }

    $stmt = $conn->prepare("SELECT * FROM car_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        redirectAdmin('Request was not found or is already reviewed.', 'error');
    }

    $image = trim($request['image'] ?? '');
    if (!$image) {
        $image = 'corolla.jpg';
    }
    $galleryImages = normalizeGalleryImages($request['gallery_images'] ?? '', $image);

    $conn->begin_transaction();

    try {
        $ownerId      = (int) $request['owner_id'];
        $brand        = $request['brand'];
        $model        = $request['model'];
        $year         = (int) $request['year'];
        $type         = $request['type'];
        $fuelType     = $request['fuel_type'];
        $transmission = $request['transmission'];
        $seats        = (int) $request['seats'];
        $pricePerDay  = (int) $request['price_per_day'];

        $insert = $conn->prepare(
            "INSERT INTO cars
                (owner_id, brand, model, year, type, fuel_type, transmission, seats, price_per_day, image, gallery_images, available)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $insert->bind_param(
            'ississsiiss',
            $ownerId,
            $brand,
            $model,
            $year,
            $type,
            $fuelType,
            $transmission,
            $seats,
            $pricePerDay,
            $image,
            $galleryImages
        );
        $insert->execute();
        $carId = $conn->insert_id;
        $insert->close();

        $reviewedBy = (int) $_SESSION['user_id'];
        $note = trim($_POST['admin_note'] ?? 'Approved by admin.');
        $update = $conn->prepare(
            "UPDATE car_requests
                SET status = 'approved',
                    admin_note = ?,
                    approved_car_id = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW()
              WHERE id = ?"
        );
        $update->bind_param('siii', $note, $carId, $reviewedBy, $requestId);
        $update->execute();
        $update->close();

        $conn->commit();
        redirectAdmin('Owner car request approved. The car is now live on the website.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        redirectAdmin('Could not approve the request. Please try again.', 'error');
    }
}

if ($action === 'reject_car_request') {
    $requestId = (int) ($_POST['request_id'] ?? $_GET['id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? 'Rejected by admin.');
    $reviewedBy = (int) $_SESSION['user_id'];

    if (!$requestId) {
        redirectAdmin('Invalid owner car request.', 'error');
    }

    $stmt = $conn->prepare(
        "UPDATE car_requests
            SET status = 'rejected',
                admin_note = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
          WHERE id = ? AND status = 'pending'"
    );
    $stmt->bind_param('sii', $note, $reviewedBy, $requestId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        redirectAdmin('Owner car request rejected.', 'success');
    }

    redirectAdmin('Request was not found or is already reviewed.', 'error');
}

if ($action === 'add_car') {

    $brand        = trim($_POST['brand']        ?? '');
    $model        = trim($_POST['model']        ?? '');
    $year         = intval($_POST['year']       ?? 0);
    $price_per_day = intval($_POST['price_per_day'] ?? 0);
    $type         = trim($_POST['type']         ?? 'sedan');
    $fuel_type    = trim($_POST['fuel_type']    ?? 'petrol');
    $transmission = trim($_POST['transmission'] ?? 'auto');
    $seats        = intval($_POST['seats']      ?? 5);
    $image        = trim($_POST['image']        ?? '');

    if (!$brand || !$model || !$year || !$price_per_day) {
        $_SESSION['admin_error'] = 'Brand, model, year and price are required.';
        header('Location: ../admin.html');
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO cars
            (brand, model, year, price_per_day, type, fuel_type, transmission, seats, image, available)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->bind_param(
        'ssiisssis',
        $brand, $model, $year, $price_per_day,
        $type, $fuel_type, $transmission, $seats, $image
    );

    if ($stmt->execute()) {
        $_SESSION['admin_success'] = "Car '{$brand} {$model}' added successfully.";
    } else {
        $_SESSION['admin_error'] = 'Failed to add car. Please try again.';
    }
    $stmt->close();
    header('Location: ../admin.html');
    exit;
}


if ($action === 'delete_car') {

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        header('Location: ../admin.html');
        exit;
    }

    /* check if car has active bookings before deleting */
    $check = $conn->prepare(
        "SELECT id FROM bookings WHERE car_id = ? AND status IN ('pending','confirmed','active')"
    );
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $_SESSION['admin_error'] = 'Cannot delete this car — it has active bookings.';
        header('Location: ../admin.html');
        exit;
    }
    $check->close();

    $stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $_SESSION['admin_success'] = 'Car deleted successfully.';
    } else {
        $_SESSION['admin_error'] = 'Failed to delete car.';
    }
    $stmt->close();
    header('Location: ../admin.html');
    exit;
}

/* ============================================================
   UPDATE CAR
   ============================================================ */
if ($action === 'update_car') {

    $id           = intval($_POST['id']          ?? 0);
    $brand        = trim($_POST['brand']         ?? '');
    $model        = trim($_POST['model']         ?? '');
    $year         = intval($_POST['year']        ?? 0);
    $price_per_day = intval($_POST['price_per_day'] ?? 0);
    $type         = trim($_POST['type']          ?? 'sedan');
    $fuel_type    = trim($_POST['fuel_type']     ?? 'petrol');
    $transmission = trim($_POST['transmission']  ?? 'auto');
    $seats        = intval($_POST['seats']       ?? 5);
    $available    = intval($_POST['available']   ?? 1);

    if (!$id) {
        header('Location: ../admin.html');
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE cars
         SET brand=?, model=?, year=?, price_per_day=?,
             type=?, fuel_type=?, transmission=?, seats=?, available=?
         WHERE id=?"
    );
    $stmt->bind_param(
        'ssiisssiii',
        $brand, $model, $year, $price_per_day,
        $type, $fuel_type, $transmission, $seats, $available, $id
    );

    if ($stmt->execute()) {
        $_SESSION['admin_success'] = 'Car updated successfully.';
    } else {
        $_SESSION['admin_error'] = 'Failed to update car.';
    }
    $stmt->close();
    header('Location: ../admin.html');
    exit;
}

/* ============================================================
   UPDATE BOOKING STATUS
   ============================================================ */
if ($action === 'update_booking') {

    $booking_id = intval($_POST['booking_id'] ?? 0);
    $status     = trim($_POST['status']       ?? '');

    $allowed = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected'];
    if (!$booking_id || !in_array($status, $allowed)) {
        header('Location: ../admin.html');
        exit;
    }

    $carQ = $conn->prepare("SELECT car_id FROM bookings WHERE id=? LIMIT 1");
    $carQ->bind_param('i', $booking_id);
    $carQ->execute();
    $carQ->bind_result($car_id);
    $carQ->fetch();
    $carQ->close();

    if (!$car_id) {
        $_SESSION['admin_error'] = 'Booking was not found.';
        header('Location: ../admin.html');
        exit;
    }

    $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
    $stmt->bind_param('si', $status, $booking_id);

    if ($stmt->execute()) {
        if (in_array($status, ['confirmed', 'active'], true)) {
            $book = $conn->prepare("UPDATE cars SET available=0 WHERE id=?");
            $book->bind_param('i', $car_id);
            $book->execute();
            $book->close();
        }

        if (in_array($status, ['completed', 'cancelled', 'rejected'], true)) {
            $free = $conn->prepare("UPDATE cars SET available=1 WHERE id=?");
            $free->bind_param('i', $car_id);
            $free->execute();
            $free->close();
        }

        $_SESSION['admin_success'] = "Booking #$booking_id status updated to '$status'.";
    } else {
        $_SESSION['admin_error'] = 'Failed to update booking status.';
    }
    $stmt->close();
    header('Location: ../admin.html');
    exit;
}

/* fallback */
header('Location: ../admin.html');
exit;

function redirectAdmin($message, $type) {
    header('Location: ../admin.html?' . $type . '=' . urlencode($message));
    exit;
}

function getScalar($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

function ensureCarRequestsTable($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS car_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            brand VARCHAR(50) NOT NULL,
            model VARCHAR(50) NOT NULL,
            year YEAR NOT NULL,
            type ENUM('sedan','suv','hatchback','luxury') DEFAULT 'sedan',
            fuel_type ENUM('petrol','diesel','hybrid') DEFAULT 'petrol',
            transmission ENUM('auto','manual','cvt') DEFAULT 'auto',
            seats TINYINT NOT NULL DEFAULT 5,
            price_per_day INT NOT NULL,
            image VARCHAR(200) DEFAULT 'corolla.jpg',
            gallery_images TEXT DEFAULT NULL,
            city VARCHAR(50) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) DEFAULT NULL,
            approved_car_id INT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX owner_idx (owner_id),
            INDEX status_idx (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($sql);
}

function ensureCarsOwnerColumn($conn) {
    $result = $conn->query("SHOW COLUMNS FROM cars LIKE 'owner_id'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE cars ADD COLUMN owner_id INT DEFAULT NULL AFTER id");
    }
}

function ensureCarGalleryColumns($conn) {
    ensureGalleryColumn($conn, 'cars');
    ensureGalleryColumn($conn, 'car_requests');
}

function ensureGalleryColumn($conn, $table) {
    $table = preg_replace('/[^a-z_]/', '', $table);
    $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE 'gallery_images'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE {$table} ADD COLUMN gallery_images TEXT DEFAULT NULL AFTER image");
    }
}

function normalizeGalleryImages($galleryImages, $fallbackImage) {
    $images = [];
    $decoded = json_decode($galleryImages ?: '[]', true);

    if (is_array($decoded)) {
        $images = $decoded;
    } elseif ($galleryImages) {
        $images = preg_split('/[\r\n,]+/', $galleryImages);
    }

    $images = array_values(array_filter(array_map('trim', $images)));
    if ($fallbackImage && !in_array($fallbackImage, $images, true)) {
        array_unshift($images, $fallbackImage);
    }

    return json_encode(array_values(array_unique($images)), JSON_UNESCAPED_SLASHES);
}

function ensureBookingWorkflowSchema($conn) {
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
?>

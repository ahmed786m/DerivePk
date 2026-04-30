<?php
require_once 'db.php';

if (($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../login.html?role=owner&error=' . urlencode('Please login as a car owner.'));
    exit;
}

ensureCarRequestsTable($conn);
ensureCarsOwnerColumn($conn);
ensureCarGalleryColumns($conn);
ensureBookingWorkflowSchema($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list_my_cars') {
    header('Content-Type: application/json');

    $ownerId = (int) $_SESSION['user_id'];

    $cars = [];
    $carStmt = $conn->prepare(
        'SELECT id, brand, model, year, type, fuel_type, transmission, seats, price_per_day, image, gallery_images, available, created_at
           FROM cars
          WHERE owner_id = ?
          ORDER BY created_at DESC, id DESC'
    );
    $carStmt->bind_param('i', $ownerId);
    $carStmt->execute();
    $carResult = $carStmt->get_result();
    while ($row = $carResult->fetch_assoc()) {
        $cars[] = $row;
    }
    $carStmt->close();

    $requests = [];
    $requestStmt = $conn->prepare(
        'SELECT id, brand, model, year, type, fuel_type, transmission, seats, price_per_day,
                image, gallery_images, city, description, status, admin_note, approved_car_id, reviewed_at, created_at
           FROM car_requests
          WHERE owner_id = ?
          ORDER BY FIELD(status, "pending", "approved", "rejected"), created_at DESC, id DESC'
    );
    $requestStmt->bind_param('i', $ownerId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    while ($row = $requestResult->fetch_assoc()) {
        $requests[] = $row;
    }
    $requestStmt->close();

    echo json_encode([
        'success' => true,
        'approved_cars' => $cars,
        'requests' => $requests
    ]);
    exit;
}

if ($action === 'list_booking_requests') {
    header('Content-Type: application/json');

    $ownerId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT b.id, b.booking_ref, b.car_id, b.full_name, b.email, b.phone, b.cnic,
                b.pickup_date, b.dropoff_date, b.pickup_city, b.total_days, b.total_price,
                b.notes, b.owner_note, b.status, b.created_at,
                c.brand, c.model, c.year, c.type, c.fuel_type, c.transmission, c.seats,
                c.price_per_day, c.image, c.available,
                u.full_name AS account_name, u.email AS account_email, u.phone AS account_phone
           FROM bookings b
           JOIN cars c ON c.id = b.car_id
      LEFT JOIN users u ON u.id = b.user_id
          WHERE c.owner_id = ?
          ORDER BY FIELD(b.status, "pending", "confirmed", "active", "completed", "cancelled", "rejected"),
                   b.created_at DESC, b.id DESC'
    );
    $stmt->bind_param('i', $ownerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'bookings' => $bookings]);
    exit;
}

if ($action === 'approve_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $ownerNote = trim($_POST['owner_note'] ?? 'Approved by car owner.');

    if (!$bookingId) {
        redirectDashboard('Invalid booking request.', 'error');
    }

    $ownerId = (int) $_SESSION['user_id'];
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'SELECT b.id, b.car_id, c.available
               FROM bookings b
               JOIN cars c ON c.id = b.car_id
              WHERE b.id = ?
                AND c.owner_id = ?
                AND b.status = "pending"
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->bind_param('ii', $bookingId, $ownerId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            throw new RuntimeException('Booking request was not found or was already reviewed.');
        }

        $carId = (int) $booking['car_id'];
        if ((int) $booking['available'] !== 1) {
            throw new RuntimeException('This car is already booked, so this request cannot be approved.');
        }

        $updateBooking = $conn->prepare(
            'UPDATE bookings
                SET status = "confirmed", owner_note = ?
              WHERE id = ?'
        );
        $updateBooking->bind_param('si', $ownerNote, $bookingId);
        $updateBooking->execute();
        $updateBooking->close();

        $bookCar = $conn->prepare('UPDATE cars SET available = 0 WHERE id = ?');
        $bookCar->bind_param('i', $carId);
        $bookCar->execute();
        $bookCar->close();

        $rejectNote = 'Another customer booking was approved for this car.';
        $rejectOthers = $conn->prepare(
            'UPDATE bookings
                SET status = "rejected", owner_note = ?
              WHERE car_id = ?
                AND id <> ?
                AND status = "pending"'
        );
        $rejectOthers->bind_param('sii', $rejectNote, $carId, $bookingId);
        $rejectOthers->execute();
        $rejectOthers->close();

        $conn->commit();
        redirectDashboard('Booking approved. The car is now shown as booked.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        redirectDashboard($e->getMessage(), 'error');
    }
}

if ($action === 'reject_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $ownerNote = trim($_POST['owner_note'] ?? 'Rejected by car owner.');
    $ownerId = (int) $_SESSION['user_id'];

    if (!$bookingId) {
        redirectDashboard('Invalid booking request.', 'error');
    }

    $stmt = $conn->prepare(
        'UPDATE bookings b
          JOIN cars c ON c.id = b.car_id
           SET b.status = "rejected",
               b.owner_note = ?
         WHERE b.id = ?
           AND c.owner_id = ?
           AND b.status = "pending"'
    );
    $stmt->bind_param('sii', $ownerNote, $bookingId, $ownerId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        redirectDashboard('Booking rejected. The car is still free.', 'success');
    }

    redirectDashboard('Booking request was not found or was already reviewed.', 'error');
}

if ($action === 'mark_booking_free') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $ownerNote = trim($_POST['owner_note'] ?? 'Rental completed. Car is free again.');
    $ownerId = (int) $_SESSION['user_id'];

    if (!$bookingId) {
        redirectDashboard('Invalid booking.', 'error');
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'SELECT b.car_id
               FROM bookings b
               JOIN cars c ON c.id = b.car_id
              WHERE b.id = ?
                AND c.owner_id = ?
                AND b.status IN ("confirmed", "active")
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->bind_param('ii', $bookingId, $ownerId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            throw new RuntimeException('Only approved or active bookings can be marked free.');
        }

        $carId = (int) $booking['car_id'];
        $complete = $conn->prepare(
            'UPDATE bookings
                SET status = "completed", owner_note = ?
              WHERE id = ?'
        );
        $complete->bind_param('si', $ownerNote, $bookingId);
        $complete->execute();
        $complete->close();

        $free = $conn->prepare('UPDATE cars SET available = 1 WHERE id = ?');
        $free->bind_param('i', $carId);
        $free->execute();
        $free->close();

        $conn->commit();
        redirectDashboard('Car marked free again.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        redirectDashboard($e->getMessage(), 'error');
    }
}

if ($action === 'request_car') {
    $ownerId      = (int) $_SESSION['user_id'];
    $brand        = trim($_POST['brand'] ?? '');
    $model        = trim($_POST['model'] ?? '');
    $year         = (int) ($_POST['year'] ?? 0);
    $type         = trim($_POST['type'] ?? 'sedan');
    $fuelType     = trim($_POST['fuel_type'] ?? 'petrol');
    $transmission = trim($_POST['transmission'] ?? 'auto');
    $seats        = (int) ($_POST['seats'] ?? 5);
    $pricePerDay  = (int) ($_POST['price_per_day'] ?? 0);
    $city         = trim($_POST['city'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    $allowedTypes = ['sedan', 'suv', 'hatchback', 'luxury'];
    $allowedFuel = ['petrol', 'diesel', 'hybrid'];
    $allowedTrans = ['auto', 'manual', 'cvt'];

    if (!in_array($type, $allowedTypes, true)) $type = 'sedan';
    if (!in_array($fuelType, $allowedFuel, true)) $fuelType = 'petrol';
    if (!in_array($transmission, $allowedTrans, true)) $transmission = 'auto';
    if (!$brand || !$model || !$year || !$pricePerDay || !$city) {
        redirectDashboard('Brand, model, year, price and city are required.', 'error');
    }

    if ($year < 1990 || $year > ((int) date('Y') + 1)) {
        redirectDashboard('Please enter a valid car year.', 'error');
    }

    if ($seats < 2 || $seats > 12) {
        redirectDashboard('Seats must be between 2 and 12.', 'error');
    }

    try {
        $uploadedImages = saveUploadedCarImages('car_images', $ownerId);
    } catch (Throwable $e) {
        redirectDashboard($e->getMessage(), 'error');
    }

    $image = $uploadedImages[0];
    $galleryImages = encodeGalleryImages($uploadedImages);

    $stmt = $conn->prepare(
        'INSERT INTO car_requests
            (owner_id, brand, model, year, type, fuel_type, transmission, seats, price_per_day, image, gallery_images, city, description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ississsiissss',
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
        $galleryImages,
        $city,
        $description
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirectDashboard('Your car request has been sent to admin for approval.', 'success');
    }

    $stmt->close();
    deleteUploadedImages($uploadedImages);
    redirectDashboard('Could not submit the car request. Please try again.', 'error');
}

header('Location: ../dashboard.html');
exit;

function redirectDashboard($message, $type) {
    header('Location: ../dashboard.html?' . $type . '=' . urlencode($message));
    exit;
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

function saveUploadedCarImages($fieldName, $ownerId) {
    $files = normalizeUploadedFiles($fieldName);

    if (count($files) < 3) {
        throw new RuntimeException('Please upload at least 3 real photos of your car.');
    }

    if (count($files) > 10) {
        throw new RuntimeException('You can upload up to 10 photos for one car.');
    }

    $uploadDir = realpath(__DIR__ . '/../images');
    if (!$uploadDir) {
        throw new RuntimeException('Images folder was not found.');
    }

    $uploadDir .= DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        throw new RuntimeException('Could not create the uploads folder.');
    }

    $saved = [];
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    foreach ($files as $index => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            deleteUploadedImages($saved);
            throw new RuntimeException('One of the photos did not upload correctly. Please try again.');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            deleteUploadedImages($saved);
            throw new RuntimeException('Each car photo must be 5 MB or smaller.');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = $imageInfo['mime'] ?? '';

        if (!isset($allowedMime[$mime])) {
            deleteUploadedImages($saved);
            throw new RuntimeException('Only JPG, PNG or WEBP car photos are allowed.');
        }

        $extension = $allowedMime[$mime];
        $safeName = sprintf(
            'owner-%d-car-%s-%02d-%s.%s',
            $ownerId,
            date('YmdHis'),
            $index + 1,
            bin2hex(random_bytes(4)),
            $extension
        );

        $destination = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            deleteUploadedImages($saved);
            throw new RuntimeException('Could not save uploaded photos. Please check folder permissions.');
        }

        $saved[] = 'uploads/' . $safeName;
    }

    return $saved;
}

function normalizeUploadedFiles($fieldName) {
    if (empty($_FILES[$fieldName])) {
        return [];
    }

    $source = $_FILES[$fieldName];
    if (!is_array($source['name'])) {
        return [$source];
    }

    $files = [];
    foreach ($source['name'] as $index => $name) {
        if (($source['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $name,
            'type' => $source['type'][$index] ?? '',
            'tmp_name' => $source['tmp_name'][$index] ?? '',
            'error' => $source['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $source['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function encodeGalleryImages(array $images) {
    $images = array_values(array_filter(array_map('trim', $images)));
    return json_encode($images, JSON_UNESCAPED_SLASHES);
}

function deleteUploadedImages(array $images) {
    foreach ($images as $image) {
        $path = realpath(__DIR__ . '/../images/' . $image);
        $imagesRoot = realpath(__DIR__ . '/../images');
        if ($path && $imagesRoot && strpos($path, $imagesRoot) === 0 && is_file($path)) {
            @unlink($path);
        }
    }
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

<?php
header('Content-Type: application/json');
require_once 'db.php';

$availableOnly = ($_GET['available'] ?? '') === '1';
$ownerColumn = $conn->query("SHOW COLUMNS FROM cars LIKE 'owner_id'");
$hasOwnerColumn = $ownerColumn && $ownerColumn->num_rows > 0;
$galleryColumn = $conn->query("SHOW COLUMNS FROM cars LIKE 'gallery_images'");
$hasGalleryColumn = $galleryColumn && $galleryColumn->num_rows > 0;

if ($hasOwnerColumn) {
    $sql = "SELECT c.*, u.full_name AS owner_name
              FROM cars c
         LEFT JOIN users u ON u.id = c.owner_id";
} else {
    $sql = "SELECT c.*, NULL AS owner_id, NULL AS owner_name
              FROM cars c";
}

if ($availableOnly) {
    $sql .= " WHERE c.available = 1";
}

$sql .= " ORDER BY c.id ASC";

$result = $conn->query($sql);
$cars = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cars[] = [
            'id' => (int) $row['id'],
            'owner_id' => $row['owner_id'] ? (int) $row['owner_id'] : null,
            'owner_name' => $row['owner_name'],
            'brand' => $row['brand'],
            'model' => $row['model'],
            'year' => (int) $row['year'],
            'type' => $row['type'],
            'fuel_type' => $row['fuel_type'],
            'transmission' => $row['transmission'],
            'seats' => (int) $row['seats'],
            'price_per_day' => (int) $row['price_per_day'],
            'image' => $row['image'] ?: 'corolla.jpg',
            'gallery_images' => buildGalleryImages($row, $hasGalleryColumn),
            'available' => (int) $row['available'] === 1
        ];
    }
}

echo json_encode(['success' => true, 'cars' => $cars]);

function buildGalleryImages($row, $hasGalleryColumn) {
    $primary = $row['image'] ?: 'corolla.jpg';
    $images = [];

    if ($hasGalleryColumn && !empty($row['gallery_images'])) {
        $decoded = json_decode($row['gallery_images'], true);
        if (is_array($decoded)) {
            $images = $decoded;
        } else {
            $images = preg_split('/[\r\n,]+/', $row['gallery_images']);
        }
    }

    if (!$images) {
        $images = defaultGalleryForCar($row, $primary);
    }

    if (!in_array($primary, $images, true)) {
        array_unshift($images, $primary);
    }

    $images = array_values(array_unique(array_filter(array_map('trim', $images))));
    return $images ?: [$primary];
}

function defaultGalleryForCar($row, $primary) {
    $brand = strtolower(trim($row['brand'] ?? ''));
    $model = strtolower(trim($row['model'] ?? ''));
    $key = $brand . ' ' . $model;

    $galleries = [
        'toyota corolla' => ['corolla.jpg', 'corolla-rear.jpg', 'corolla-interior.jpg'],
        'honda civic' => ['civic.jpg', 'civic-exterior.jpg', 'civic-interior.jpg'],
        'toyota fortuner' => ['fortuner.jpg', 'fortuner-front.jpg', 'fortuner-side.jpg'],
        'suzuki cultus' => ['cultus.jpg', 'cultus-front.jpg', 'cultus-side.jpg'],
        'honda br-v' => ['brv.jpg', 'brv-front.jpg', 'brv-interior.jpg'],
        'hyundai tucson' => ['tucson.jpg', 'tucson-front.png', 'tucson-interior.jpg'],
    ];

    return $galleries[$key] ?? [$primary];
}

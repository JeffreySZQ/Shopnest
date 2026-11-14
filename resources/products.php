
<?php
/*
 * ShopNest - Database Products API
 * Compatible with PHP 5.4.16
 *
 * Reads existing products from the ShopNest MariaDB database.
 * Does not create or modify database tables.
 */

// Allow requests from the local Vite development server.
$allowedOrigins = array(
    'http://localhost:5173',
    'http://127.0.0.1:5173'
);

if (isset($_SERVER['HTTP_ORIGIN']) &&
    in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {

    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle browser preflight requests.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only GET requests are supported.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed.'
    ));

    exit;
}

// --------------------------------------------------
// DATABASE CONNECTION
// --------------------------------------------------

// Keep the hostname exactly as specified.
$dbHost = 'feenix-mariadb.swin.edu.au';

// Replace these with your actual database credentials.
// Do not commit real credentials to GitHub.
$dbUser = 's105534669';
$dbPass = '230706';
$dbName = 's105534669_db';

$conn = new mysqli(
    $dbHost,
    $dbUser,
    $dbPass,
    $dbName
);

if ($conn->connect_error) {
    error_log('ShopNest database error: ' . $conn->connect_error);

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Database connection failed.'
    ));

    exit;
}

if (!$conn->set_charset('utf8')) {
    error_log('Could not set database character set.');
}

// --------------------------------------------------
// RETRIEVE PRODUCTS
// --------------------------------------------------

$sql = "
    SELECT
        p.id,
        p.sku,
        p.name,
        p.image_path,
        p.image_alt,
        p.category,
        p.product_type,
        p.price,
        p.stock,
        p.short_description,
        p.description,
        p.status,
        (
            SELECT COUNT(*)
            FROM product_likes pl
            WHERE pl.product_id = p.id
        ) AS likes
    FROM products p
    WHERE p.status = 'published'
    ORDER BY p.id ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Product query preparation failed: ' . $conn->error);

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Could not retrieve products.'
    ));

    $conn->close();
    exit;
}

if (!$stmt->execute()) {
    error_log('Product query execution failed: ' . $stmt->error);

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Could not retrieve products.'
    ));

    $stmt->close();
    $conn->close();
    exit;
}

// PHP 5.4-compatible result retrieval.
// This avoids mysqli_stmt::get_result(), which may not
// be available on servers without mysqlnd.

$stmt->bind_result(
    $id,
    $sku,
    $name,
    $imagePath,
    $imageAlt,
    $category,
    $productType,
    $price,
    $stock,
    $shortDescription,
    $description,
    $status,
    $likes
);

$products = array();

while ($stmt->fetch()) {
    $products[] = array(
        'id' => (int) $id,
        'sku' => $sku,
        'name' => $name,
        'imagePath' => $imagePath,
        'imageAlt' => $imageAlt,
        'category' => $category,
        'type' => $productType,
        'price' => (float) $price,
        'stock' => (int) $stock,
        'short' => $shortDescription,
        'description' => $description,
        'status' => $status,
        'likes' => (int) $likes
    );
}

$stmt->close();
$conn->close();

// --------------------------------------------------
// JSON RESPONSE
// --------------------------------------------------

echo json_encode(array(
    'success' => true,
    'products' => $products,
    'count' => count($products)
));

?>

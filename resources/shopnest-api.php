<?php
/*
 * ShopNest REST API
 * PHP 5.4.16 compatible.
 * Uses only the tables and columns in resources/ShopnestTable.sql.
 */

// ------------------------------------------------------------
// CORS and session
// ------------------------------------------------------------
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigins = array(
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://mercury.swin.edu.au'
);

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(0, '/', '', $isHttps, true);
session_start();

// ------------------------------------------------------------
// Response and request helpers
// ------------------------------------------------------------
class ShopNestApiException extends Exception {
    public $httpStatus;

    public function __construct($message, $httpStatus) {
        parent::__construct($message);
        $this->httpStatus = (int)$httpStatus;
    }
}

function apiRespond($data, $status) {
    http_response_code((int)$status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiFail($message, $status) {
    throw new ShopNestApiException($message, $status);
}

function apiInput() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        apiFail('Request body must be a valid JSON object.', 400);
    }
    return $data;
}

function apiRequired($data, $key, $label) {
    $value = isset($data[$key]) ? trim((string)$data[$key]) : '';
    if ($value === '') {
        apiFail($label . ' is required.', 400);
    }
    return $value;
}

function apiCurrentUser() {
    return isset($_SESSION['shopnest_user']) ? $_SESSION['shopnest_user'] : null;
}

function apiRequireLogin() {
    $user = apiCurrentUser();
    if (!$user) {
        apiFail('Please sign in first.', 401);
    }
    return $user;
}

function apiRequireAdmin() {
    $user = apiRequireLogin();
    if (!isset($user['role']) || $user['role'] !== 'admin') {
        apiFail('Administrator access required.', 403);
    }
    return $user;
}

function apiUserOutput($row) {
    return array(
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role']
    );
}

// ------------------------------------------------------------
// Database connection
// db-config.php is intentionally not included in the ZIP.
// Copy db-config.example.php to db-config.php and add credentials.
// ------------------------------------------------------------
$configPath = __DIR__ . '/db-config.php';
if (!file_exists($configPath)) {
    apiRespond(array(
        'success' => false,
        'message' => 'Database configuration is missing. Create resources/db-config.php from db-config.example.php.'
    ), 500);
}

$dbConfig = require $configPath;
if (!is_array($dbConfig) || !isset($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database'])) {
    apiRespond(array(
        'success' => false,
        'message' => 'Database configuration is incomplete.'
    ), 500);
}

$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($conn->connect_error) {
    error_log('ShopNest database connection failed: ' . $conn->connect_error);
    apiRespond(array(
        'success' => false,
        'message' => 'Database connection failed. Check the Mercury database settings.'
    ), 500);
}

if (!$conn->set_charset('utf8mb4')) {
    $conn->set_charset('utf8');
}

// ------------------------------------------------------------
// MySQLi helpers. These use bind_result(), not get_result(),
// so they do not require mysqlnd.
// ------------------------------------------------------------
function apiRows($sql, $types, $params) {
    global $conn;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }

    if ($types !== '') {
        $bind = array($types);
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new Exception('Database query failed: ' . $message);
    }

    $stmt->store_result();
    $metadata = $stmt->result_metadata();
    $rows = array();

    if ($metadata) {
        $fields = $metadata->fetch_fields();
        $row = array();
        $bind = array();

        foreach ($fields as $field) {
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }

        call_user_func_array(array($stmt, 'bind_result'), $bind);

        while ($stmt->fetch()) {
            $copy = array();
            foreach ($row as $key => $value) {
                $copy[$key] = $value;
            }
            $rows[] = $copy;
        }

        $metadata->free();
    }

    $stmt->free_result();
    $stmt->close();
    return $rows;
}

function apiExec($sql, $types, $params) {
    global $conn;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database statement preparation failed: ' . $conn->error);
    }

    if ($types !== '') {
        $bind = array($types);
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        $message = $stmt->error;
        $stmt->close();
        if ($errno === 1062) {
            apiFail('A record with that unique value already exists.', 409);
        }
        throw new Exception('Database operation failed: ' . $message);
    }

    $result = array(
        'affected' => $stmt->affected_rows,
        'insertId' => $conn->insert_id
    );

    $stmt->close();
    return $result;
}

// ------------------------------------------------------------
// Password hashing for PHP 5.4
// PHP 5.4 does not provide password_hash/password_verify.
// Blowfish crypt hashes are compatible with modern bcrypt hashes.
// ------------------------------------------------------------
function apiPasswordHash($password) {
    if (!defined('CRYPT_BLOWFISH') || !CRYPT_BLOWFISH) {
        apiFail('This Mercury PHP installation does not support bcrypt password hashing. Contact the server administrator.', 500);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes = openssl_random_pseudo_bytes(16, $strong);
        if ($bytes === false || !$strong) {
            apiFail('Could not generate a secure password salt.', 500);
        }
    } else {
        apiFail('OpenSSL is required to register accounts securely on this server.', 500);
    }

    $salt = substr(str_replace('+', '.', base64_encode($bytes)), 0, 22);
    $hash = crypt($password, '$2y$10$' . $salt);

    if (!is_string($hash) || strlen($hash) < 20 || substr($hash, 0, 4) !== '$2y$') {
        apiFail('Bcrypt password hashing is unavailable on this server.', 500);
    }

    return $hash;
}

function apiPasswordVerify($password, $hash) {
    if (!is_string($hash) || substr($hash, 0, 4) !== '$2y$') {
        return false;
    }

    $candidate = crypt($password, $hash);
    if (!is_string($candidate) || strlen($candidate) !== strlen($hash)) {
        return false;
    }

    $difference = 0;
    $length = strlen($hash);
    for ($i = 0; $i < $length; $i++) {
        $difference |= ord($candidate[$i]) ^ ord($hash[$i]);
    }
    return $difference === 0;
}

// ------------------------------------------------------------
// Activity logging
// ------------------------------------------------------------
function apiLog($action, $resource, $resourceId, $detail) {
    $user = apiCurrentUser();
    if (!$user) {
        return;
    }

    apiExec(
        'INSERT INTO activity_logs (actor_id, actor_name, action, resource_type, resource_id, detail) VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        array(
            (int)$user['id'],
            $user['name'],
            $action,
            $resource,
            (string)$resourceId,
            $detail
        )
    );
}

// ------------------------------------------------------------
// Product helpers
// ------------------------------------------------------------
function apiGetProduct($id) {
    $rows = apiRows(
        'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p WHERE p.id = ?',
        'i',
        array((int)$id)
    );
    return count($rows) ? $rows[0] : null;
}

function apiProductOutput($p) {
    return array(
        'id' => (int)$p['id'],
        'sku' => $p['sku'],
        'name' => $p['name'],
        'imagePath' => $p['image_path'],
        'imageAlt' => $p['image_alt'],
        'category' => $p['category'],
        'type' => $p['product_type'],
        'price' => (float)$p['price'],
        'stock' => (int)$p['stock'],
        'short' => $p['short_description'] === null ? '' : $p['short_description'],
        'description' => $p['description'] === null ? '' : $p['description'],
        'status' => $p['status'],
        'likes' => isset($p['likes']) ? (int)$p['likes'] : 0
    );
}

function apiProductSnapshot($p) {
    $out = apiProductOutput($p);
    unset($out['likes']);
    return $out;
}

function apiProductPayload($input, $old) {
    $sku = apiRequired($input, 'sku', 'SKU');
    $name = apiRequired($input, 'name', 'Product name');
    $category = apiRequired($input, 'category', 'Category');

    $type = isset($input['type']) ? $input['type'] : ($old ? $old['product_type'] : 'physical');
    $status = isset($input['status']) ? $input['status'] : ($old ? $old['status'] : 'draft');

    if (!in_array($type, array('physical', 'digital'), true)) {
        apiFail('Invalid product type.', 400);
    }
    if (!in_array($status, array('draft', 'published', 'archived'), true)) {
        apiFail('Invalid product status.', 400);
    }

    $price = isset($input['price']) ? $input['price'] : null;
    $stock = isset($input['stock']) ? $input['stock'] : ($old ? $old['stock'] : 0);

    if (!is_numeric($price) || (float)$price < 0) {
        apiFail('Price must be a non-negative number.', 400);
    }
    if (!is_numeric($stock) || (int)$stock < 0 || (float)$stock != (int)$stock) {
        apiFail('Stock must be a non-negative whole number.', 400);
    }

    $short = isset($input['short']) ? $input['short'] : ($old ? $old['short_description'] : '');
    $description = isset($input['description']) ? $input['description'] : ($old ? $old['description'] : '');
    $imagePath = isset($input['imagePath']) ? trim($input['imagePath']) : ($old ? $old['image_path'] : '');
    $imageAlt = isset($input['imageAlt']) ? trim($input['imageAlt']) : ($old ? $old['image_alt'] : $name);

    if (strlen($sku) > 40 || strlen($name) > 150 || strlen($category) > 80 || strlen($short) > 255 || strlen($imagePath) > 255 || strlen($imageAlt) > 255) {
        apiFail('A product field exceeds its database column length.', 400);
    }

    if ($imageAlt === '') {
        $imageAlt = $name;
    }

    return array(
        'sku' => $sku,
        'name' => $name,
        'category' => $category,
        'type' => $type,
        'status' => $status,
        'price' => (float)$price,
        'stock' => (int)$stock,
        'short' => $short,
        'description' => $description,
        'imagePath' => $imagePath,
        'imageAlt' => $imageAlt
    );
}

function apiCreateProductVersion($productId, $admin, $note) {
    apiExec(
        "UPDATE product_versions SET status = 'historical' WHERE product_id = ? AND status = 'live'",
        'i',
        array((int)$productId)
    );

    $rows = apiRows(
        'SELECT COALESCE(MAX(version_number), 0) AS version FROM product_versions WHERE product_id = ?',
        'i',
        array((int)$productId)
    );
    $version = (int)$rows[0]['version'] + 1;

    $product = apiGetProduct($productId);
    if (!$product) {
        throw new Exception('Product disappeared while creating its version.');
    }

    if ($product['status'] === 'published') {
        $versionStatus = 'live';
    } elseif ($product['status'] === 'draft') {
        $versionStatus = 'draft';
    } else {
        $versionStatus = 'historical';
    }

    $snapshot = json_encode(apiProductSnapshot($product), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    apiExec(
        'INSERT INTO product_versions (product_id, version_number, created_by, actor_name, status, note, snapshot) VALUES (?, ?, ?, ?, ?, ?, ?)',
        'iiissss',
        array(
            (int)$productId,
            $version,
            (int)$admin['id'],
            $admin['name'],
            $versionStatus,
            $note,
            $snapshot
        )
    );

    return $version;
}

// ------------------------------------------------------------
// Cart and order helpers
// ------------------------------------------------------------
function apiCartId($userId) {
    $rows = apiRows('SELECT id FROM carts WHERE user_id = ?', 'i', array((int)$userId));
    if (count($rows)) {
        return (int)$rows[0]['id'];
    }

    $result = apiExec('INSERT INTO carts (user_id) VALUES (?)', 'i', array((int)$userId));
    return (int)$result['insertId'];
}

function apiOrderItems($orderId) {
    $rows = apiRows(
        'SELECT product_id, product_name, product_type, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id ASC',
        'i',
        array((int)$orderId)
    );

    $items = array();
    foreach ($rows as $item) {
        $items[] = array(
            'productId' => $item['product_id'] === null ? null : (int)$item['product_id'],
            'name' => $item['product_name'],
            'type' => $item['product_type'],
            'price' => (float)$item['unit_price'],
            'quantity' => (int)$item['quantity']
        );
    }
    return $items;
}

function apiOrderOutput($order) {
    $items = apiOrderItems((int)$order['id']);
    $cancelRequested = null;
    if ($order['cancellation_status'] === 'pending') {
        $cancelRequested = $order['updated_at'] ? $order['updated_at'] : $order['created_at'];
    }

    return array(
        'id' => $order['order_number'],
        'databaseId' => (int)$order['id'],
        'userId' => (int)$order['user_id'],
        'customer' => $order['customer_name'],
        'email' => $order['customer_email'],
        'items' => $items,
        'subtotal' => (float)$order['subtotal'],
        'shipping' => (float)$order['shipping_fee'],
        'total' => (float)$order['total'],
        'paymentStatus' => $order['payment_status'],
        'fulfillmentStatus' => $order['fulfillment_status'],
        'cancellationStatus' => $order['cancellation_status'],
        'cancellationRequestedAt' => $cancelRequested,
        'address' => $order['shipping_address'] === null ? '' : $order['shipping_address'],
        'city' => $order['city'] === null ? '' : $order['city'],
        'postcode' => $order['postcode'] === null ? '' : $order['postcode'],
        'createdAt' => $order['created_at'],
        'simulation' => (bool)$order['is_demo_payment']
    );
}

function apiGetOrder($orderNumber) {
    $rows = apiRows('SELECT * FROM orders WHERE order_number = ?', 's', array($orderNumber));
    return count($rows) ? $rows[0] : null;
}

// ------------------------------------------------------------
// Parse URL path after shopnest-api.php
// ------------------------------------------------------------
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

if ($path === '') {
    $uriPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName !== '' && strpos($uriPath, $scriptName) === 0) {
        $path = substr($uriPath, strlen($scriptName));
    }
}

$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$resource = isset($segments[0]) ? $segments[0] : 'health';
$id = isset($segments[1]) ? $segments[1] : null;
$action = isset($segments[2]) ? $segments[2] : null;
$extra = isset($segments[3]) ? $segments[3] : null;
$last = isset($segments[4]) ? $segments[4] : null;

// ------------------------------------------------------------
// Routes
// ------------------------------------------------------------
try {
    $input = apiInput();

    // Health check
    if ($resource === 'health' && $method === 'GET') {
        apiRespond(array(
            'success' => true,
            'status' => 'ok',
            'database' => 'connected',
            'php' => PHP_VERSION,
            'bcryptAvailable' => (defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH && function_exists('openssl_random_pseudo_bytes'))
        ), 200);
    }

    // Authentication
    if ($resource === 'auth') {
        if ($method === 'GET' && $id === 'me') {
            apiRespond(array('success' => true, 'user' => apiCurrentUser()), 200);
        }

        if ($method === 'POST' && $id === 'register') {
            $name = apiRequired($input, 'name', 'Name');
            $email = strtolower(apiRequired($input, 'email', 'Email'));
            $password = isset($input['password']) ? (string)$input['password'] : '';

            if (strlen($name) > 100) {
                apiFail('Name must be 100 characters or fewer.', 400);
            }
            if (strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                apiFail('Enter a valid email address.', 400);
            }
            if (strlen($password) < 8) {
                apiFail('Password must contain at least 8 characters.', 400);
            }

            $existing = apiRows('SELECT id FROM users WHERE email = ?', 's', array($email));
            if (count($existing)) {
                apiFail('An account with this email already exists.', 409);
            }

            $hash = apiPasswordHash($password);
            $result = apiExec(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')",
                'sss',
                array($name, $email, $hash)
            );

            $user = array(
                'id' => (int)$result['insertId'],
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            );

            session_regenerate_id(true);
            $_SESSION['shopnest_user'] = $user;
            apiLog('Account registered', 'Account', $user['id'], $email);
            apiRespond(array('success' => true, 'user' => $user), 201);
        }

        if ($method === 'POST' && $id === 'login') {
            $email = strtolower(trim(isset($input['email']) ? $input['email'] : ''));
            $password = isset($input['password']) ? (string)$input['password'] : '';

            $users = apiRows(
                'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1',
                's',
                array($email)
            );

            if (!count($users) || !apiPasswordVerify($password, $users[0]['password_hash'])) {
                apiFail('Incorrect email or password.', 401);
            }

            $user = apiUserOutput($users[0]);
            session_regenerate_id(true);
            $_SESSION['shopnest_user'] = $user;
            apiLog('Signed in', 'Account', $user['id'], $email);
            apiRespond(array('success' => true, 'user' => $user), 200);
        }

        if ($method === 'POST' && $id === 'logout') {
            $user = apiCurrentUser();
            if ($user) {
                apiLog('Signed out', 'Account', $user['id'], $user['email']);
            }

            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            apiRespond(array('success' => true, 'message' => 'Signed out.'), 200);
        }
    }

    // Products: public published products; admins can retrieve drafts by ID.
    if ($resource === 'products') {
        if ($method === 'GET' && $id === null) {
            $rows = apiRows(
                "SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p WHERE p.status = 'published' ORDER BY p.id ASC",
                '',
                array()
            );
            $products = array();
            foreach ($rows as $product) {
                $products[] = apiProductOutput($product);
            }
            apiRespond(array('success' => true, 'products' => $products, 'count' => count($products)), 200);
        }

        if ($method === 'GET' && $id !== null) {
            $product = apiGetProduct((int)$id);
            $user = apiCurrentUser();
            if (!$product || ($product['status'] !== 'published' && (!$user || $user['role'] !== 'admin'))) {
                apiFail('Product not found.', 404);
            }
            apiRespond(array('success' => true, 'product' => apiProductOutput($product)), 200);
        }

        if ($method === 'POST' && $id === null) {
            $admin = apiRequireAdmin();
            $p = apiProductPayload($input, null);
            $result = apiExec(
                'INSERT INTO products (sku, name, image_path, image_alt, category, product_type, price, stock, short_description, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ssssssdisss',
                array($p['sku'], $p['name'], $p['imagePath'], $p['imageAlt'], $p['category'], $p['type'], $p['price'], $p['stock'], $p['short'], $p['description'], $p['status'])
            );
            $productId = (int)$result['insertId'];
            $version = apiCreateProductVersion($productId, $admin, 'Product created');
            apiLog('Product created', 'Products', $productId, $p['name'] . ' (version ' . $version . ')');
            apiRespond(array('success' => true, 'product' => apiProductOutput(apiGetProduct($productId))), 201);
        }

        if ($method === 'PUT' && $id !== null) {
            $admin = apiRequireAdmin();
            $productId = (int)$id;
            $old = apiGetProduct($productId);
            if (!$old) {
                apiFail('Product not found.', 404);
            }
            $p = apiProductPayload($input, $old);
            // Check whether this product has an existing version history.
            $history = apiRows(
                'SELECT version_number
                FROM product_versions
                WHERE product_id = ?
                LIMIT 1',
                'i',
                array($productId)
            );

            // Save the original product as version 1
            // if no previous version exists.
            if (!count($history)) {

                $initialStatus = $old['status'] === 'published'
                    ? 'live'
                    : (
                        $old['status'] === 'draft'
                            ? 'draft'
                            : 'historical'
                    );

                $initialSnapshot = json_encode(
                    apiProductSnapshot($old),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                apiExec(
                    'INSERT INTO product_versions (
                        product_id,
                        version_number,
                        created_by,
                        actor_name,
                        status,
                        note,
                        snapshot
                    )
                    VALUES (?, 1, ?, ?, ?, ?, ?)',

                    'iissss',

                    array(
                        $productId,
                        (int)$admin['id'],
                        $admin['name'],
                        $initialStatus,
                        'Initial product version',
                        $initialSnapshot
                    )
                );
            }
            apiExec(
                'UPDATE products SET sku = ?, name = ?, image_path = ?, image_alt = ?, category = ?, product_type = ?, price = ?, stock = ?, short_description = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?',
                'ssssssdisssi',
                array($p['sku'], $p['name'], $p['imagePath'], $p['imageAlt'], $p['category'], $p['type'], $p['price'], $p['stock'], $p['short'], $p['description'], $p['status'], $productId)
            );
            $version = apiCreateProductVersion($productId, $admin, 'Product updated');
            apiLog('Product updated', 'Products', $productId, $p['name'] . ' (version ' . $version . ')');
            apiRespond(array('success' => true, 'product' => apiProductOutput(apiGetProduct($productId)), 'version' => $version), 200);
        }

        if ($method === 'DELETE' && $id !== null) {
            $admin = apiRequireAdmin();
            $product = apiGetProduct((int)$id);
            if (!$product) {
                apiFail('Product not found.', 404);
            }
            apiExec("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = ?", 'i', array((int)$id));
            apiCreateProductVersion((int)$id, $admin, 'Product archived');
            apiLog('Product archived', 'Products', $id, $product['name']);
            apiRespond(array('success' => true, 'message' => 'Product archived.'), 200);
        }
    }

    // Customer cart
    if ($resource === 'cart') {
        $user = apiRequireLogin();
        $cartId = apiCartId((int)$user['id']);

        if ($method === 'GET') {
            $rows = apiRows("SELECT ci.product_id, ci.quantity FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? AND p.status = 'published' ORDER BY ci.id ASC", 'i', array($cartId));
            $items = array();
            foreach ($rows as $item) {
                $items[] = array('productId' => (int)$item['product_id'], 'quantity' => (int)$item['quantity']);
            }
            apiRespond(array('success' => true, 'items' => $items), 200);
        }

        if ($method === 'POST' && $id === 'items') {
            $productId = isset($input['productId']) ? (int)$input['productId'] : 0;
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            if ($productId < 1 || $quantity < 1 || $quantity > 99) {
                apiFail('Choose a valid product and quantity.', 400);
            }
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            $existing = apiRows('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, $productId));
            $newQuantity = $quantity + (count($existing) ? (int)$existing[0]['quantity'] : 0);
            if ($product['product_type'] === 'physical' && $newQuantity > (int)$product['stock']) {
                apiFail('Not enough stock available.', 400);
            }
            if (count($existing)) {
                apiExec('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?', 'iii', array($newQuantity, $cartId, $productId));
            } else {
                apiExec('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)', 'iii', array($cartId, $productId, $quantity));
            }
            apiRespond(array('success' => true, 'message' => 'Cart updated.'), 200);
        }

        if ($method === 'PATCH' && $id === 'items' && $action !== null) {
            $productId = (int)$action;
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
            if ($quantity <= 0) {
                apiExec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, $productId));
                apiRespond(array('success' => true, 'message' => 'Cart item removed.'), 200);
            }
            if ($quantity > 99) {
                apiFail('Maximum quantity is 99.', 400);
            }
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            if ($product['product_type'] === 'physical' && $quantity > (int)$product['stock']) {
                apiFail('Not enough stock available.', 400);
            }
            apiExec('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?', 'iii', array($quantity, $cartId, $productId));
            apiRespond(array('success' => true, 'message' => 'Cart updated.'), 200);
        }

        if ($method === 'DELETE' && $id === 'items' && $action !== null) {
            apiExec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, (int)$action));
            apiRespond(array('success' => true, 'message' => 'Cart item removed.'), 200);
        }

        if ($method === 'DELETE' && $id === 'items' && $action === null) {
            apiExec('DELETE FROM cart_items WHERE cart_id = ?', 'i', array($cartId));
            apiRespond(array('success' => true, 'message' => 'Cart cleared.'), 200);
        }
    }

    // Product likes (heart button). wishlist_items remains untouched because
    // the current UI uses the product_likes table.
    if ($resource === 'likes') {
        $user = apiRequireLogin();

        if ($method === 'GET') {
            $rows = apiRows('SELECT product_id FROM product_likes WHERE user_id = ?', 'i', array((int)$user['id']));
            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int)$row['product_id'];
            }
            apiRespond(array('success' => true, 'productIds' => $ids), 200);
        }

        if ($method === 'POST' && $id !== null && $action === 'toggle') {
            $productId = (int)$id;
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            $existing = apiRows('SELECT product_id FROM product_likes WHERE user_id = ? AND product_id = ?', 'ii', array((int)$user['id'], $productId));
            if (count($existing)) {
                apiExec('DELETE FROM product_likes WHERE user_id = ? AND product_id = ?', 'ii', array((int)$user['id'], $productId));
                $liked = false;
            } else {
                apiExec('INSERT INTO product_likes (user_id, product_id) VALUES (?, ?)', 'ii', array((int)$user['id'], $productId));
                $liked = true;
            }
            $count = apiRows('SELECT COUNT(*) AS total FROM product_likes WHERE product_id = ?', 'i', array($productId));
            apiRespond(array('success' => true, 'liked' => $liked, 'likes' => (int)$count[0]['total']), 200);
        }
    }

    // Checkout: demonstration payment only.
    if ($resource === 'checkout' && $method === 'POST') {
        $user = apiRequireLogin();
        $cartId = apiCartId((int)$user['id']);
        $conn->autocommit(false);

        try {
            $rows = apiRows(
                'SELECT ci.product_id, ci.quantity, p.name, p.product_type, p.price, p.stock, p.status FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? FOR UPDATE',
                'i',
                array($cartId)
            );

            if (!count($rows)) {
                apiFail('Your cart is empty.', 400);
            }

            $email = isset($input['email']) ? trim($input['email']) : $user['email'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                apiFail('Enter a valid email address.', 400);
            }

            $subtotal = 0;
            $hasPhysical = false;
            foreach ($rows as $item) {
                if ($item['status'] !== 'published') {
                    apiFail('Your cart contains an unavailable product.', 400);
                }
                if ($item['product_type'] === 'physical') {
                    $hasPhysical = true;
                    if ((int)$item['quantity'] > (int)$item['stock']) {
                        apiFail('Not enough stock for ' . $item['name'] . '.', 400);
                    }
                }
                $subtotal += (float)$item['price'] * (int)$item['quantity'];
            }

            $address = isset($input['address']) ? trim($input['address']) : '';
            $city = isset($input['city']) ? trim($input['city']) : '';
            $postcode = isset($input['postcode']) ? trim($input['postcode']) : '';

            if ($hasPhysical && ($address === '' || $city === '' || $postcode === '')) {
                apiFail('Complete your delivery address.', 400);
            }

            $shipping = $hasPhysical ? 8.00 : 0.00;
            $total = $subtotal + $shipping;
            $orderNumber = 'SN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $fulfillment = $hasPhysical ? 'processing' : 'digital delivered';

            $orderResult = apiExec(
                "INSERT INTO orders (order_number, user_id, customer_name, customer_email, shipping_address, city, postcode, subtotal, shipping_fee, total, payment_status, fulfillment_status, is_demo_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 1)",
                'sisssssddds',
                array($orderNumber, (int)$user['id'], $user['name'], $email, $address, $city, $postcode, $subtotal, $shipping, $total, $fulfillment)
            );
            $orderId = (int)$orderResult['insertId'];

            foreach ($rows as $item) {
                apiExec(
                    'INSERT INTO order_items (order_id, product_id, product_name, product_type, unit_price, quantity) VALUES (?, ?, ?, ?, ?, ?)',
                    'iissdi',
                    array($orderId, (int)$item['product_id'], $item['name'], $item['product_type'], (float)$item['price'], (int)$item['quantity'])
                );

                if ($item['product_type'] === 'physical') {
                    $updated = apiExec(
                        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                        'iii',
                        array((int)$item['quantity'], (int)$item['product_id'], (int)$item['quantity'])
                    );
                    if ($updated['affected'] !== 1) {
                        apiFail('Stock changed during checkout. Please try again.', 409);
                    }
                }
            }

            apiExec('DELETE FROM cart_items WHERE cart_id = ?', 'i', array($cartId));
            $conn->commit();
            $conn->autocommit(true);
        } catch (Exception $checkoutError) {
            $conn->rollback();
            $conn->autocommit(true);
            throw $checkoutError;
        }

        apiLog('Demo order created', 'Orders', $orderNumber, 'Simulated payment only; no real funds were collected.');
        $order = apiGetOrder($orderNumber);
        apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 201);
    }

    // Customer orders
    if ($resource === 'orders') {
        $user = apiRequireLogin();

        if ($method === 'GET' && $id === null) {
            $rows = apiRows('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', 'i', array((int)$user['id']));
            $orders = array();
            foreach ($rows as $order) {
                $orders[] = apiOrderOutput($order);
            }
            apiRespond(array('success' => true, 'orders' => $orders), 200);
        }

        if ($method === 'GET' && $id !== null) {
            $order = apiGetOrder($id);
            if (!$order || (int)$order['user_id'] !== (int)$user['id']) {
                apiFail('Order not found.', 404);
            }
            apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 200);
        }

        if ($method === 'POST' && $id !== null && $action === 'cancellation') {
            $order = apiGetOrder($id);
            if (!$order || (int)$order['user_id'] !== (int)$user['id']) {
                apiFail('Order not found.', 404);
            }
            if (!in_array($order['fulfillment_status'], array('processing', 'digital delivered'), true)) {
                apiFail('This order can no longer be cancelled online.', 400);
            }
            if ($order['cancellation_status'] === 'pending') {
                apiFail('A cancellation request is already pending.', 409);
            }
            if ($order['cancellation_status'] === 'approved') {
                apiFail('This order has already been cancelled.', 409);
            }
            apiExec("UPDATE orders SET cancellation_status = 'pending', updated_at = NOW() WHERE id = ?", 'i', array((int)$order['id']));
            apiLog('Cancellation requested', 'Orders', $id, 'Customer requested cancellation.');
            apiRespond(array('success' => true, 'message' => 'Cancellation request submitted.'), 200);
        }
    }

    // Administrator endpoints
    if ($resource === 'admin') {
        $admin = apiRequireAdmin();

        if ($id === 'overview' && $method === 'GET') {
            $productStats = apiRows("SELECT COUNT(*) AS total, SUM(status = 'published') AS published FROM products", '', array());
            $orderStats = apiRows("SELECT COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS total_orders FROM orders WHERE payment_status = 'paid'", '', array());
            $pendingStats = apiRows("SELECT COUNT(*) AS total FROM orders WHERE cancellation_status = 'pending'", '', array());
            apiRespond(array(
                'success' => true,
                'overview' => array(
                    'products' => (int)$productStats[0]['total'],
                    'published' => (int)$productStats[0]['published'],
                    'revenue' => (float)$orderStats[0]['revenue'],
                    'orders' => (int)$orderStats[0]['total_orders'],
                    'pendingCancellations' => (int)$pendingStats[0]['total']
                )
            ), 200);
        }

        if ($id === 'products' && $action === null && $method === 'GET') {
            $rows = apiRows(
                'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p ORDER BY p.id DESC',
                '',
                array()
            );
            $products = array();
            foreach ($rows as $product) {
                $products[] = apiProductOutput($product);
            }
            apiRespond(array('success' => true, 'products' => $products), 200);
        }

        if ($id === 'products' && $action !== null && $extra === 'versions' && $method === 'GET') {
            $productId = (int)$action;
            if (!apiGetProduct($productId)) {
                apiFail('Product not found.', 404);
            }
            $versions = apiRows(
                'SELECT version_number AS version, actor_name AS actor, status, note, snapshot, created_at AS createdAt FROM product_versions WHERE product_id = ? ORDER BY version_number DESC',
                'i',
                array($productId)
            );
            foreach ($versions as $index => $version) {
                $decoded = json_decode($version['snapshot'], true);
                $versions[$index]['version'] = (int)$version['version'];
                $versions[$index]['snapshot'] = is_array($decoded) ? $decoded : array();
            }
            apiRespond(array('success' => true, 'versions' => $versions), 200);
        }

        if ($id === 'products' && $action !== null && $extra === 'restore' && $method === 'POST') {
            $productId = (int)$action;
            $sourceVersion = (int)$last;
            if (!apiGetProduct($productId)) {
                apiFail('Product not found.', 404);
            }
            $source = apiRows('SELECT snapshot FROM product_versions WHERE product_id = ? AND version_number = ?', 'ii', array($productId, $sourceVersion));
            if (!count($source)) {
                apiFail('Product version not found.', 404);
            }
            $snapshot = json_decode($source[0]['snapshot'], true);
            if (!is_array($snapshot)) {
                apiFail('Product version snapshot is invalid.', 500);
            }
            $latest = apiRows('SELECT COALESCE(MAX(version_number), 0) AS version FROM product_versions WHERE product_id = ?', 'i', array($productId));
            $nextVersion = (int)$latest[0]['version'] + 1;
            $snapshot['status'] = 'draft';
            apiExec(
                "INSERT INTO product_versions (product_id, version_number, created_by, actor_name, status, note, snapshot) VALUES (?, ?, ?, ?, 'draft', ?, ?)",
                'iiisss',
                array($productId, $nextVersion, (int)$admin['id'], $admin['name'], 'Restored from version ' . $sourceVersion, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            );
            apiLog('Product version restored to draft', 'Products', $productId, 'Version ' . $sourceVersion . ' restored as draft v' . $nextVersion . '.');
            apiRespond(array('success' => true, 'draftVersion' => $nextVersion), 201);
        }

        if ($id === 'orders' && $action === null && $method === 'GET') {
            $rows = apiRows('SELECT * FROM orders ORDER BY created_at DESC', '', array());
            $orders = array();
            foreach ($rows as $order) {
                $orders[] = apiOrderOutput($order);
            }
            apiRespond(array('success' => true, 'orders' => $orders), 200);
        }

        if ($id === 'orders' && $action !== null && $method === 'GET') {
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 200);
        }

        if ($id === 'orders' && $action !== null && $method === 'PATCH') {
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            $status = isset($input['fulfillmentStatus']) ? $input['fulfillmentStatus'] : '';
            $allowed = array('processing', 'digital delivered', 'shipped', 'delivered', 'cancelled');
            if (!in_array($status, $allowed, true)) {
                apiFail('Invalid fulfillment status.', 400);
            }
            if ($order['fulfillment_status'] === 'cancelled') {
                apiFail('A cancelled order cannot be updated.', 409);
            }
            apiExec('UPDATE orders SET fulfillment_status = ?, updated_at = NOW() WHERE id = ?', 'si', array($status, (int)$order['id']));
            apiLog('Order status updated', 'Orders', $action, $status);
            apiRespond(array('success' => true, 'message' => 'Order status updated.'), 200);
        }

        if ($id === 'cancellations' && $action !== null && $method === 'POST') {
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            if ($order['cancellation_status'] !== 'pending') {
                apiFail('No pending cancellation request.', 409);
            }
            $approve = !empty($input['approve']);
            $conn->autocommit(false);
            try {
                if ($approve) {
                    apiExec("UPDATE orders SET cancellation_status = 'approved', fulfillment_status = 'cancelled', payment_status = 'refunded', updated_at = NOW() WHERE id = ?", 'i', array((int)$order['id']));
                    $items = apiRows('SELECT product_id, product_type, quantity FROM order_items WHERE order_id = ?', 'i', array((int)$order['id']));
                    foreach ($items as $item) {
                        if ($item['product_type'] === 'physical' && $item['product_id'] !== null) {
                            apiExec('UPDATE products SET stock = stock + ? WHERE id = ?', 'ii', array((int)$item['quantity'], (int)$item['product_id']));
                        }
                    }
                } else {
                    apiExec("UPDATE orders SET cancellation_status = 'rejected', updated_at = NOW() WHERE id = ?", 'i', array((int)$order['id']));
                }
                $conn->commit();
                $conn->autocommit(true);
            } catch (Exception $cancelError) {
                $conn->rollback();
                $conn->autocommit(true);
                throw $cancelError;
            }
            apiLog($approve ? 'Demo cancellation approved' : 'Cancellation rejected', 'Orders', $action, 'Demo status only; no real payment refund is processed.');
            apiRespond(array('success' => true, 'message' => $approve ? 'Cancellation approved (demo).' : 'Cancellation rejected.'), 200);
        }

        if ($id === 'activity' && $method === 'GET') {
            $logs = apiRows(
                'SELECT id, actor_name AS actor, action, resource_type AS resource, resource_id, detail, created_at AS time FROM activity_logs ORDER BY created_at DESC LIMIT 300',
                '',
                array()
            );
            foreach ($logs as $index => $log) {
                $logs[$index]['id'] = (int)$log['id'];
            }
            apiRespond(array('success' => true, 'logs' => $logs), 200);
        }
    }

    apiFail('API endpoint not found.', 404);

} catch (ShopNestApiException $e) {
    apiRespond(array('success' => false, 'message' => $e->getMessage()), $e->httpStatus);
} catch (Exception $e) {
    error_log('ShopNest API error: ' . $e->getMessage());
    apiRespond(array('success' => false, 'message' => 'An unexpected server error occurred.'), 500);
}

?>

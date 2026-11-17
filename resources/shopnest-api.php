<?php
/*
 * ShopNest REST API
 */

// ============================================================
// CORS AND SESSION SETUP
// ============================================================

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


// ============================================================
// RESPONSE AND REQUEST HELPER FUNCTIONS
// ============================================================
class ShopNestApiException extends Exception {
    // Stores the HTTP status code that should be returned to the frontend.
    public $httpStatus;

    // Constructor: creates the exception with an error message and status.
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

    // Read the Content-Type header to determine the request format.
    $contentType = isset($_SERVER['CONTENT_TYPE'])
        ? $_SERVER['CONTENT_TYPE']
        : '';

    // Image uploads use multipart/form-data.
    // PHP automatically places the normal form fields into $_POST.
    if (stripos($contentType, 'multipart/form-data') === 0) {
        return isset($_POST) && is_array($_POST)
            ? $_POST
            : array();
    }

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

//Checks whether a required field was provided and is not empty.
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
    // Retrieve the current user from the session.
    $user = apiCurrentUser();
    // Reject the request if the session does not contain a signed-in user.
    if (!$user) {
        apiFail('Please sign in first.', 401);
    }
    return $user;
}

function apiRequireAdmin() {
    // First make sure the user is signed in.
    $user = apiRequireLogin();
    // Check the user's role.
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


// ============================================================
// DATABASE CONNECTION
// ============================================================

// Build the path to the database configuration file.
$configPath = __DIR__ . '/db-config.php';
// Stop the API if the database configuration file does not exist.
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

// Establish a connection to the MySQL database using MySQLi.
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


// ============================================================
// MYSQLI DATABASE HELPER FUNCTIONS
// ============================================================

function apiRows($sql, $types, $params) {
    global $conn;
    // Prepare the SQL statement before executing it.
    $stmt = $conn->prepare($sql);
    // If preparation fails, raise a server-side database error.
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }
    // Bind parameter values if the query contains placeholders.
    if ($types !== '') {
        $bind = array($types);
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    // Execute the prepared SELECT statement.
    if (!$stmt->execute()) {

        // Save the error before closing the statement.
        $message = $stmt->error;
        $stmt->close();

        throw new Exception('Database query failed: ' . $message);
    }

    // Store the result so that the returned rows can be read.
    $stmt->store_result();
    $metadata = $stmt->result_metadata();
    $rows = array();

    if ($metadata) {
        $fields = $metadata->fetch_fields();

        $row = array();
        $bind = array();

        // Create an empty value for every returned column.
        // Bind each column to the corresponding array element.
        foreach ($fields as $field) {
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }

        // Connect the database result columns to the PHP variables.
        call_user_func_array(array($stmt, 'bind_result'), $bind);

        // Fetch each row returned by the database.
        while ($stmt->fetch()) {

            // Copy the current row into a separate array.
            // This is important because the bound $row values are
            // reused and overwritten when the next row is fetched.
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
    // Prepare the SQL statement.
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database statement preparation failed: ' . $conn->error);
    }
    // Bind the parameters when placeholders are present.
    if ($types !== '') {
        $bind = array($types);
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    // Execute the INSERT, UPDATE or DELETE statement.
    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        $message = $stmt->error;
        $stmt->close();
        if ($errno === 1062) {
            apiFail('A record with that unique value already exists.', 409);
        }
        throw new Exception('Database operation failed: ' . $message);
    }

    // Collect useful information about the completed operation.
    $result = array(
        'affected' => $stmt->affected_rows,
        'insertId' => $conn->insert_id
    );

    $stmt->close();
    return $result;
}


// ============================================================
// PASSWORD HASHING FUNCTIONS
// ============================================================
function apiPasswordHash($password) {

    // Check whether this PHP installation supports bcrypt.
    if (!defined('CRYPT_BLOWFISH') || !CRYPT_BLOWFISH) {
        apiFail('This Mercury PHP installation does not support bcrypt password hashing. Contact the server administrator.', 500);
    }

    // Generate secure random bytes using OpenSSL.
    if (function_exists('openssl_random_pseudo_bytes')) {

        $strong = false;
        $bytes = openssl_random_pseudo_bytes(16, $strong);

        // Reject the salt if secure random data could not be generated.
        if ($bytes === false || !$strong) {
            apiFail('Could not generate a secure password salt.', 500);
        }

    } else {
        apiFail('OpenSSL is required to register accounts securely on this server.', 500);
    }

    // Convert the random bytes into a bcrypt-compatible salt.
    // '+' is replaced with '.' to match bcrypt's accepted alphabet.
    $salt = substr(str_replace('+', '.', base64_encode($bytes)), 0, 22);

    // Generate the bcrypt hash.
    $hash = crypt($password, '$2y$10$' . $salt);

    // Check that crypt() returned a valid-looking bcrypt hash.
    if (!is_string($hash) || strlen($hash) < 20 || substr($hash, 0, 4) !== '$2y$') {
        apiFail('Bcrypt password hashing is unavailable on this server.', 500);
    }

    return $hash;
}

function apiPasswordVerify($password, $hash) {

    // Only accept hashes that use the expected bcrypt format.
    if (!is_string($hash) || substr($hash, 0, 4) !== '$2y$') {
        return false;
    }
    $candidate = crypt($password, $hash);
    // Check that the generated candidate has the expected format and length.
    if (!is_string($candidate) || strlen($candidate) !== strlen($hash)) {
        return false;
    }

    // Compare the hashes character by character.
    $difference = 0;
    $length = strlen($hash);
    for ($i = 0; $i < $length; $i++) {
        $difference |= ord($candidate[$i]) ^ ord($hash[$i]);
    }
    // If no character differed, the result remains zero.
    return $difference === 0;
}


// ============================================================
// ACTIVITY LOGGING
// ============================================================
function apiLog($action, $resource, $resourceId, $detail) {
    // Get the currently signed-in user.
    $user = apiCurrentUser();
    // Do not create a log entry without an authenticated user.
    if (!$user) {
        return;
    }
    // Insert the action into the activity_logs database table.
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


// ============================================================
// PRODUCT HELPER FUNCTIONS
// ============================================================
function apiGetProduct($id) {
    $rows = apiRows(
        'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p WHERE p.id = ?',
        'i',
        array((int)$id)
    );
    // Return the first matching product, or null if no product was found.
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
    // Likes are dynamic user activity, not part of the product version.
    unset($out['likes']);

    return $out;
}
function apiProductPayload($input, $old) {
    $sku = apiRequired($input, 'sku', 'SKU');
    $name = apiRequired($input, 'name', 'Product name');
    $category = apiRequired($input, 'category', 'Category');

    // Use the submitted product type or preserve the existing type.
    // New products default to physical.
    $type = isset($input['type']) ? $input['type'] : ($old ? $old['product_type'] : 'physical');

    // Use the submitted status or preserve the existing status.
    // New products default to draft so they are not automatically published.
    $status = isset($input['status']) ? $input['status'] : ($old ? $old['status'] : 'draft');

    // Only physical and digital products are supported.
    if (!in_array($type, array('physical', 'digital'), true)) {
        apiFail('Invalid product type.', 400);
    }

    // Only draft, published and archived statuses are supported.
    if (!in_array($status, array('draft', 'published', 'archived'), true)) {
        apiFail('Invalid product status.', 400);
    }

    // Read the price and stock values.
    $price = isset($input['price']) ? $input['price'] : null;
    $stock = isset($input['stock']) ? $input['stock'] : ($old ? $old['stock'] : 0);

    // Price must be numeric and cannot be negative.
    if (!is_numeric($price) || (float)$price < 0) {
        apiFail('Price must be a non-negative number.', 400);
    }

    // Stock must be a non-negative whole number.
    if (!is_numeric($stock) || (int)$stock < 0 || (float)$stock != (int)$stock) {
        apiFail('Stock must be a non-negative whole number.', 400);
    }

    // For optional fields, use the submitted value if available.
    // Otherwise, preserve the old value when editing.
    $short = isset($input['short']) ? $input['short'] : ($old ? $old['short_description'] : '');
    $description = isset($input['description']) ? $input['description'] : ($old ? $old['description'] : '');
    $imagePath = isset($input['imagePath']) ? trim($input['imagePath']) : ($old ? $old['image_path'] : '');
    $imageAlt = isset($input['imageAlt']) ? trim($input['imageAlt']) : ($old ? $old['image_alt'] : $name);

    if (strlen($sku) > 40 || strlen($name) > 150 || strlen($category) > 80 || strlen($short) > 255 || strlen($imagePath) > 255 || strlen($imageAlt) > 255) {
        apiFail('A product field exceeds its database column length.', 400);
    }

    // If no alternative image text was provided, use the product name.
    if ($imageAlt === '') {
        $imageAlt = $name;
    }

    // Return the validated product information
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


//Save a snapshot of a product as a new version in product_versions.
function apiCreateProductVersion($productId, $admin, $note) {

    // Change the previous live version to historical before creating the next version.
    apiExec(
        "UPDATE product_versions SET status = 'historical' WHERE product_id = ? AND status = 'live'",
        'i',
        array((int)$productId)
    );

    // Find the highest version number currently stored for this product.
    // COALESCE returns 0 if no versions exist yet.
    $rows = apiRows(
        'SELECT COALESCE(MAX(version_number), 0) AS version FROM product_versions WHERE product_id = ?',
        'i',
        array((int)$productId)
    );

    // The new version number is always one higher than the current maximum.
    $version = (int)$rows[0]['version'] + 1;

    // Retrieve the latest product information from the products table.
    $product = apiGetProduct($productId);

    if (!$product) {
        throw new Exception('Product disappeared while creating its version.');
    }

    // Determine the status of the new version based on the product's status.
    if ($product['status'] === 'published') {
        $versionStatus = 'live';
    } elseif ($product['status'] === 'draft') {
        $versionStatus = 'draft';
    } else {
        $versionStatus = 'historical';
    }

    // Convert the product snapshot into JSON for storage in the database.
    $snapshot = json_encode(apiProductSnapshot($product), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Insert the new version and record which administrator created it.
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


// ============================================================
// CART AND ORDER HELPER FUNCTIONS
// ============================================================

//Retrieves the cart ID belonging to a particular user.
function apiCartId($userId) {
    // Search for an existing cart
    $rows = apiRows('SELECT id FROM carts WHERE user_id = ?', 'i', array((int)$userId));
    // Return the existing cart ID if a record was found.
    if (count($rows)) {
        return (int)$rows[0]['id'];
    }
    // Otherwise, create a new cart for the user.
    $result = apiExec('INSERT INTO carts (user_id) VALUES (?)', 'i', array((int)$userId));
    // Return the database-generated ID of the new cart.
    return (int)$result['insertId'];
}

function apiOrderItems($orderId) {
    // Retrieve all items for the selected order.
    $rows = apiRows(
        'SELECT product_id, product_name, product_type, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id ASC',
        'i',
        array((int)$orderId)
    );

    $items = array();
    // Convert every database row into the frontend's expected structure.
    foreach ($rows as $item) {
        $items[] = array(
            // Some order items may not have a linked product ID.
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
    // Retrieve the individual items belonging to this order.
    $items = apiOrderItems((int)$order['id']);
    // The cancellation request date is only relevant when a request is currently pending.
    $cancelRequested = null;
    if ($order['cancellation_status'] === 'pending') {
        $cancelRequested = $order['updated_at'] ? $order['updated_at'] : $order['created_at'];
    }
    // Return the order
    return array(
        'id' => $order['order_number'],
        'databaseId' => (int)$order['id'],
        'userId' => (int)$order['user_id'],
        'customer' => $order['customer_name'],
        'email' => $order['customer_email'],
        'phone' => isset($order['customer_phone']) ? $order['customer_phone'] : '',
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

// Retrieves one order using its public order number.
function apiGetOrder($orderNumber) {
    $rows = apiRows('SELECT * FROM orders WHERE order_number = ?', 's', array($orderNumber));
    return count($rows) ? $rows[0] : null;
}


// ============================================================
// URL AND ROUTE PARSING
// ============================================================+
// Read the HTTP method used by the request.
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
// PATH_INFO may contain the URL path after the PHP filename.
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

// If PATH_INFO is unavailable, extract the path from REQUEST_URI.
if ($path === '') {
    $uriPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    // Remove the PHP script's own path from the beginning of the URL.
    if ($scriptName !== '' && strpos($uriPath, $scriptName) === 0) {
        $path = substr($uriPath, strlen($scriptName));
    }
}

// Split the URL path into separate segments.
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
// Assign each segment to a variable so the route logic can inspect it.
$resource = isset($segments[0]) ? $segments[0] : 'health';
$id = isset($segments[1]) ? $segments[1] : null;
$action = isset($segments[2]) ? $segments[2] : null;
$extra = isset($segments[3]) ? $segments[3] : null;
$last = isset($segments[4]) ? $segments[4] : null;


// ============================================================
// API ROUTES
// ============================================================
try {
    // Read the request body once so individual routes can use the data.
    $input = apiInput();
    // --------------------------------------------------------
    // HEALTH CHECK
    // --------------------------------------------------------
    // Allows the frontend or developer to check whether the API is running.
    if ($resource === 'health' && $method === 'GET') {
        apiRespond(array(
            'success' => true,
            'status' => 'ok',
            'database' => 'connected',
            'php' => PHP_VERSION,
            // Reports whether the server has the required bcrypt support.
            'bcryptAvailable' => (defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH && function_exists('openssl_random_pseudo_bytes'))
        ), 200);
    }
    // --------------------------------------------------------
    // AUTHENTICATION ROUTES
    // --------------------------------------------------------
    if ($resource === 'auth') {
        // Returns the currently signed-in user, if there is one.
        if ($method === 'GET' && $id === 'me') {
            apiRespond(array('success' => true, 'user' => apiCurrentUser()), 200);
        }

        // Creates a new customer account.
        if ($method === 'POST' && $id === 'register') {
            // Retrieve the required name and email fields.
            $name = apiRequired($input, 'name', 'Name');
            $email = strtolower(apiRequired($input, 'email', 'Email'));
            // Retrieve the submitted password.
            $password = isset($input['password']) ? (string)$input['password'] : '';
            // Validate the user's name length.
            if (strlen($name) > 100) {
                apiFail('Name must be 100 characters or fewer.', 400);
            }
            // Validate the email length and format.
            if (strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                apiFail('Enter a valid email address.', 400);
            }
            // Require a minimum password length of eight characters.
            if (strlen($password) < 8) {
                apiFail('Password must contain at least 8 characters.', 400);
            }
            // Require at least one uppercase letter.
            if (!preg_match('/[A-Z]/', $password)) {
                apiFail('Password must contain at least one uppercase letter.', 400);
            }
            // Require at least one lowercase letter.
            if (!preg_match('/[a-z]/', $password)) {
                apiFail('Password must contain at least one lowercase letter.', 400);
            }
            // Require at least one number.
            if (!preg_match('/[0-9]/', $password)) {
                apiFail('Password must contain at least one number.', 400);
            }
            // Check whether the email address is already registered.
            $existing = apiRows('SELECT id FROM users WHERE email = ?', 's', array($email));
            if (count($existing)) {
                apiFail('An account with this email already exists.', 409);
            }
            // Hash the password before storing it.
            $hash = apiPasswordHash($password);
            // Insert the new user as a customer.
            $result = apiExec(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')",
                'sss',
                array($name, $email, $hash)
            );

            // Build the user information returned to the frontend.
            $user = array(
                'id' => (int)$result['insertId'],
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            );

            // Generate a new session ID after registration.
            session_regenerate_id(true);
            // Store the newly registered user in the session.
            $_SESSION['shopnest_user'] = $user;
            // Record the registration in the activity log.
            apiLog('Account registered', 'Account', $user['id'], $email);
            // Return the new account with HTTP 201 Created.
            apiRespond(array('success' => true, 'user' => $user), 201);
        }

        // Authenticates an existing user.
        if ($method === 'POST' && $id === 'login') {
            // Read and normalise the submitted email address.
            $email = strtolower(trim(isset($input['email']) ? $input['email'] : ''));
            // Read the submitted password.
            $password = isset($input['password']) ? (string)$input['password'] : '';
            // Retrieve the user record matching the submitted email.

            $users = apiRows(
                'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1',
                's',
                array($email)
            );

            // Reject the login if the user does not exist or the password does not match.
            if (!count($users) || !apiPasswordVerify($password, $users[0]['password_hash'])) {
                apiFail('Incorrect email or password.', 401);
            }

            // Convert the database row into the safe frontend user format.
            $user = apiUserOutput($users[0]);
            // Generate a new session ID after successful authentication.
            session_regenerate_id(true);
            // Store the authenticated user in the session.
            $_SESSION['shopnest_user'] = $user;
            // Record the successful login.
            apiLog('Signed in', 'Account', $user['id'], $email);
            apiRespond(array('success' => true, 'user' => $user), 200);
        }

        // Ends the current user's session.
        if ($method === 'POST' && $id === 'logout') {
            // Retrieve the current user so the logout can be recorded.
            $user = apiCurrentUser();
            if ($user) {
                apiLog('Signed out', 'Account', $user['id'], $user['email']);
            }
            // Clear all values stored in the session.
            $_SESSION = array();
            // If PHP uses cookies for sessions, expire the current session cookie.
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
            // Destroy the server-side session.
            session_destroy();
            apiRespond(array('success' => true, 'message' => 'Signed out.'), 200);
        }
    }


    // --------------------------------------------------------
    // PRODUCT ROUTES
    // --------------------------------------------------------
    if ($resource === 'products') {
        // Returns all published products.
        if ($method === 'GET' && $id === null) {
            // Retrieve published products and their like counts.
            $rows = apiRows(
                "SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p WHERE p.status = 'published' ORDER BY p.id ASC",
                '',
                array()
            );
            $products = array();
            // Convert each database row into the frontend product format.
            foreach ($rows as $product) {
                $products[] = apiProductOutput($product);
            }
            apiRespond(array('success' => true, 'products' => $products, 'count' => count($products)), 200);
        }

        // Returns one product.
        if ($method === 'GET' && $id !== null) {
            $product = apiGetProduct((int)$id);
            $user = apiCurrentUser();
            // Return 404 if the product does not exist.
            // Also prevent ordinary users from viewing drafts or archived products.
            if (!$product || ($product['status'] !== 'published' && (!$user || $user['role'] !== 'admin'))) {
                apiFail('Product not found.', 404);
            }
            apiRespond(array('success' => true, 'product' => apiProductOutput($product)), 200);
        }

        // Creates a new product. Administrator access is required.
        if ($method === 'POST' && $id === null) {
            // Verify that the current user is an administrator.
            $admin = apiRequireAdmin();
            // Validate and prepare the submitted product details.
            $p = apiProductPayload($input, null);
            // Insert the new product into the products table.
            $result = apiExec(
                'INSERT INTO products (sku, name, image_path, image_alt, category, product_type, price, stock, short_description, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ssssssdisss',
                array($p['sku'], $p['name'], $p['imagePath'], $p['imageAlt'], $p['category'], $p['type'], $p['price'], $p['stock'], $p['short'], $p['description'], $p['status'])
            );
            // Get the ID generated for the newly inserted product.
            $productId = (int)$result['insertId'];
            // Save the initial product details as version history.
            $version = apiCreateProductVersion($productId, $admin, 'Product created');
            // Record the creation in the activity log.
            apiLog('Product created', 'Products', $productId, $p['name'] . ' (version ' . $version . ')');
            // Retrieve the newly created product and return it to the frontend.
            apiRespond(array('success' => true, 'product' => apiProductOutput(apiGetProduct($productId))), 201);
        }

        // Updates an existing product. Administrator access is required.
        if ($method === 'PUT' && $id !== null) {
            $admin = apiRequireAdmin();
            $productId = (int)$id;
            // Retrieve the existing product before applying changes.
            $old = apiGetProduct($productId);
            if (!$old) {
                apiFail('Product not found.', 404);
            }
            // Validate the submitted values while preserving existing values for optional fields that were not submitted.
            $p = apiProductPayload($input, $old);
            // Check whether this product already has version history.
            $history = apiRows(
                'SELECT version_number
                FROM product_versions
                WHERE product_id = ?
                LIMIT 1',
                'i',
                array($productId)
            );
            // If no version history exists, preserve the original product as version 1 before updating the actual product record.
            if (!count($history)) {
                // Determine the initial version status from the old product.
                $initialStatus = $old['status'] === 'published'
                    ? 'live'
                    : (
                        $old['status'] === 'draft'
                            ? 'draft'
                            : 'historical'
                    );
                // Create a JSON snapshot of the original product.
                $initialSnapshot = json_encode(
                    apiProductSnapshot($old),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                // Insert the original product as version 1.
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
            // Update the current product record with the validated values.
            apiExec(
                'UPDATE products SET sku = ?, name = ?, image_path = ?, image_alt = ?, category = ?, product_type = ?, price = ?, stock = ?, short_description = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?',
                'ssssssdisssi',
                array($p['sku'], $p['name'], $p['imagePath'], $p['imageAlt'], $p['category'], $p['type'], $p['price'], $p['stock'], $p['short'], $p['description'], $p['status'], $productId)
            );
            // Create a new version containing the updated product details.
            $version = apiCreateProductVersion($productId, $admin, 'Product updated');
            // Record the update in the activity log.
            apiLog('Product updated', 'Products', $productId, $p['name'] . ' (version ' . $version . ')');
            // Return the updated product and its new version number.
            apiRespond(array('success' => true, 'product' => apiProductOutput(apiGetProduct($productId)), 'version' => $version), 200);
        }

        // Archives a product instead of permanently deleting it.
        if ($method === 'DELETE' && $id !== null) {
            $admin = apiRequireAdmin();
            // Retrieve the product so its existence and name can be checked.
            $product = apiGetProduct((int)$id);
            if (!$product) {
                apiFail('Product not found.', 404);
            }
            // Change the product status to archived.
            apiExec("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = ?", 'i', array((int)$id));
            // Save the archived state as a new product version.
            apiCreateProductVersion((int)$id, $admin, 'Product archived');
            // Record the archive operation.
            apiLog('Product archived', 'Products', $id, $product['name']);
            apiRespond(array('success' => true, 'message' => 'Product archived.'), 200);
        }
    }


    // --------------------------------------------------------
    // CUSTOMER CART ROUTES
    // --------------------------------------------------------

    if ($resource === 'cart') {
        // Every cart operation requires a signed-in user.
        $user = apiRequireLogin();
        // Merges items from a guest cart into the signed-in user's cart.
        // A merge token is used so that the same guest cart cannot be accidentally merged multiple times if the frontend retries.
        if ($method === 'POST' && $id === 'merge') {

            $mergeToken = isset($input['mergeToken']) ? trim($input['mergeToken']) : '';
            $items = isset($input['items']) ? $input['items'] : array();

            // The merge token must be exactly 64 hexadecimal characters.
            if (!preg_match('/^[a-f0-9]{64}$/i', $mergeToken)) {
                apiFail('Invalid guest cart merge token.', 400);
            }

            // The submitted cart items must be an array.
            if (!is_array($items)) {
                apiFail('Guest cart items must be an array.', 400);
            }

            // Limit the number of different products in a guest cart.
            if (count($items) > 100) {
                apiFail('Guest cart contains too many different products.', 400);
            }

            // There is nothing to merge if the guest cart is empty.
            if (!count($items)) {
                apiRespond(array('success' => true, 'merged' => false, 'message' => 'Guest cart is empty.'), 200);
            }

            // Start a database transaction.
            // All changes will either be committed together or rolled back.
            $conn->autocommit(false);

            try {

                // Record this user/token pair.
                // The database's unique key on (user_id, merge_token) prevents the same merge from being applied twice.
                apiExec(
                    'INSERT INTO cart_merge_tokens (user_id, merge_token) VALUES (?, ?)',
                    'is',
                    array((int)$user['id'], $mergeToken)
                );

                // Retrieve the user's existing cart or create one.
                $cartId = apiCartId((int)$user['id']);

                // Process each item submitted from the guest cart.
                foreach ($items as $guestItem) {
                    if (!is_array($guestItem)) {
                        apiFail('Invalid guest cart item.', 400);
                    }
                    // Extract the product ID and requested quantity.
                    $productId = isset($guestItem['productId']) ? (int)$guestItem['productId'] : 0;
                    $quantity = isset($guestItem['quantity']) ? (int)$guestItem['quantity'] : 0;
                    // Validate the product ID and quantity.
                    if ($productId < 1 || $quantity < 1 || $quantity > 99) {
                        apiFail('Guest cart contains an invalid product quantity.', 400);
                    }
                    // Confirm that the product still exists and is published.
                    $product = apiGetProduct($productId);
                    if (!$product || $product['status'] !== 'published') {
                        apiFail('A product in your guest cart is no longer available.', 409);
                    }
                    // Check whether the same product is already in the user's cart.
                    $existing = apiRows(
                        'SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?',
                        'ii',
                        array($cartId, $productId)
                    );
                    // Add the guest quantity to the existing cart quantity.
                    $newQuantity = $quantity + (count($existing) ? (int)$existing[0]['quantity'] : 0);
                    // Enforce the maximum quantity of 99.
                    if ($newQuantity > 99) {
                        apiFail('The merged quantity exceeds the maximum of 99 for ' . $product['name'] . '.', 400);
                    }
                    // Physical products must have enough stock available.
                    if ($product['product_type'] === 'physical' && $newQuantity > (int)$product['stock']) {
                        apiFail('Not enough stock available for ' . $product['name'] . '.', 409);
                    }
                    // If the product already exists in the cart, update its quantity.
                    if (count($existing)) {
                        apiExec(
                            'UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?',
                            'iii',
                            array($newQuantity, $cartId, $productId)
                        );

                    // Otherwise, insert it as a new cart item.
                    } else {
                        apiExec(
                            'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)',
                            'iii',
                            array($cartId, $productId, $quantity)
                        );
                    }
                }
                // If every item was processed successfully, commit the changes.
                $conn->commit();
                $conn->autocommit(true);
                apiRespond(array(
                    'success' => true,
                    'merged' => true,
                    'message' => 'Guest cart merged successfully.'
                ), 200);

            } catch (Exception $mergeError) {
                // If anything fails, undo all changes made during the merge.
                $conn->rollback();
                $conn->autocommit(true);

                if ($mergeError instanceof ShopNestApiException && $mergeError->httpStatus === 409) {
                    // Check whether this user/token pair already exists.
                    $alreadyMerged = apiRows(
                        'SELECT id FROM cart_merge_tokens WHERE user_id = ? AND merge_token = ?',
                        'is',
                        array((int)$user['id'], $mergeToken)
                    );
                    // If it exists, treat the retry as a successful no-op.
                    if (count($alreadyMerged)) {
                        apiRespond(array(
                            'success' => true,
                            'merged' => false,
                            'alreadyMerged' => true,
                            'message' => 'This guest cart was already merged.'
                        ), 200);
                    }
                }
                // Pass any other error to the main error handler.
                throw $mergeError;
            }
        }

        // Retrieve or create the signed-in user's cart.
        $cartId = apiCartId((int)$user['id']);

        // Returns the products and quantities currently in the user's cart.
        if ($method === 'GET') {
            // Only return items whose products are still published.
            $rows = apiRows("SELECT ci.product_id, ci.quantity FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? AND p.status = 'published' ORDER BY ci.id ASC", 'i', array($cartId));
            $items = array();
            // Convert the database values into the frontend format.
            foreach ($rows as $item) {
                $items[] = array('productId' => (int)$item['product_id'], 'quantity' => (int)$item['quantity']);
            }
            apiRespond(array('success' => true, 'items' => $items), 200);
        }

        // Adds a product to the user's cart.
        if ($method === 'POST' && $id === 'items') {
            $productId = isset($input['productId']) ? (int)$input['productId'] : 0;
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            // Validate the product ID and quantity.
            if ($productId < 1 || $quantity < 1 || $quantity > 99) {
                apiFail('Choose a valid product and quantity.', 400);
            }
            // Confirm that the product exists and is available for purchase.
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            // Check whether this product is already in the cart.
            $existing = apiRows('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, $productId));
            // Calculate the resulting quantity if this product is added.
            $newQuantity = $quantity + (count($existing) ? (int)$existing[0]['quantity'] : 0);
            // Prevent adding more physical items than the available stock.
            if ($product['product_type'] === 'physical' && $newQuantity > (int)$product['stock']) {
                apiFail('Not enough stock available.', 400);
            }
            // Update the existing cart item if it is already present.
            if (count($existing)) {
                apiExec('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?', 'iii', array($newQuantity, $cartId, $productId));
            // Otherwise, insert a new cart item.
            } else {
                apiExec('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)', 'iii', array($cartId, $productId, $quantity));
            }
            apiRespond(array('success' => true, 'message' => 'Cart updated.'), 200);
        }

        // Updates the quantity of a product already in the cart.
        if ($method === 'PATCH' && $id === 'items' && $action !== null) {
            // The product ID is taken from the URL.
            $productId = (int)$action;
            // Read the new quantity from the request body.
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
            // A quantity of zero or less means the item should be removed.
            if ($quantity <= 0) {
                apiExec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, $productId));
                apiRespond(array('success' => true, 'message' => 'Cart item removed.'), 200);
            }
            // Enforce the maximum quantity allowed per product.
            if ($quantity > 99) {
                apiFail('Maximum quantity is 99.', 400);
            }
            // Retrieve the product to check that it is still available.
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            // Physical products cannot exceed their available stock.
            if ($product['product_type'] === 'physical' && $quantity > (int)$product['stock']) {
                apiFail('Not enough stock available.', 400);
            }
            // Update the quantity for this product in the user's cart.
            apiExec('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?', 'iii', array($quantity, $cartId, $productId));

            apiRespond(array('success' => true, 'message' => 'Cart updated.'), 200);
        }

        // Removes one specific product from the cart.
        if ($method === 'DELETE' && $id === 'items' && $action !== null) {
            // Delete the cart item matching the user's cart ID and product ID.
            apiExec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', 'ii', array($cartId, (int)$action));
            apiRespond(array('success' => true, 'message' => 'Cart item removed.'), 200);
        }

        // Removes every item from the user's cart.
        if ($method === 'DELETE' && $id === 'items' && $action === null) {
            // Delete all cart_items records belonging to this cart.
            apiExec('DELETE FROM cart_items WHERE cart_id = ?', 'i', array($cartId));
            apiRespond(array('success' => true, 'message' => 'Cart cleared.'), 200);
        }
    }


    // --------------------------------------------------------
    // PRODUCT LIKES
    // --------------------------------------------------------
    if ($resource === 'likes') {
        // A user must be signed in to view or change their likes.
        $user = apiRequireLogin();
        // Returns the IDs of all products liked by the current user.
        if ($method === 'GET') {
            // Retrieve all product IDs liked by this user.
            $rows = apiRows('SELECT product_id FROM product_likes WHERE user_id = ?', 'i', array((int)$user['id']));
            $ids = array();
            // Convert each product ID to an integer and add it to the result.
            foreach ($rows as $row) {
                $ids[] = (int)$row['product_id'];
            }
            apiRespond(array('success' => true, 'productIds' => $ids), 200);
        }
        // Adds a like if the user has not liked the product.
        // Removes the like if the user has already liked it.
        if ($method === 'POST' && $id !== null && $action === 'toggle') {
            // Read the product ID from the URL.
            $productId = (int)$id;
            // Make sure the product exists and is published.
            $product = apiGetProduct($productId);
            if (!$product || $product['status'] !== 'published') {
                apiFail('Product is unavailable.', 404);
            }
            // Check whether this user has already liked the product.
            $existing = apiRows('SELECT product_id FROM product_likes WHERE user_id = ? AND product_id = ?', 'ii', array((int)$user['id'], $productId));
            if (count($existing)) {

                // If a like already exists, remove it.
                apiExec('DELETE FROM product_likes WHERE user_id = ? AND product_id = ?', 'ii', array((int)$user['id'], $productId));
                $liked = false;
            } else {
                // If no like exists, insert a new like record.
                apiExec('INSERT INTO product_likes (user_id, product_id) VALUES (?, ?)', 'ii', array((int)$user['id'], $productId));
                $liked = true;
            }
            // Recalculate the total number of likes for this product.
            $count = apiRows('SELECT COUNT(*) AS total FROM product_likes WHERE product_id = ?', 'i', array($productId));
            // Return whether the current user liked it and the updated total.
            apiRespond(array('success' => true, 'liked' => $liked, 'likes' => (int)$count[0]['total']), 200);
        }
    }


    // --------------------------------------------------------
    // CHECKOUT
    // --------------------------------------------------------
    if ($resource === 'checkout' && $method === 'POST') {
        // Checkout is only available to signed-in users.
        $user = apiRequireLogin();
        // Retrieve the user's cart ID.
        $cartId = apiCartId((int)$user['id']);
        // Begin a database transaction.
        // This keeps the order creation, order items, stock changes and clearing together as one operation.
        $conn->autocommit(false);
        try {
            // Retrieve all cart items together with their current product details.
            $rows = apiRows(
                'SELECT ci.product_id, ci.quantity, p.name, p.product_type, p.price, p.stock, p.status FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? FOR UPDATE',
                'i',
                array($cartId)
            );
            // A user cannot check out an empty cart.
            if (!count($rows)) {
                apiFail('Your cart is empty.', 400);
            }
            // Use the email supplied at checkout, or the user's account email.
            $email = isset($input['email']) ? trim($input['email']) : $user['email'];
            // Validate the email address.
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                apiFail('Enter a valid email address.', 400);
            }
            // Retrieve and validate the contact phone number.
            $phone = isset($input['phone']) ? trim($input['phone']) : '';
            if ($phone === '' || strlen($phone) > 40 || !preg_match('/^[0-9+()\\-\\s]{7,40}$/', $phone)) {
                apiFail('Enter a valid contact phone number.', 400);
            }
            // Initialise the order subtotal and physical-product indicator.
            $subtotal = 0;
            $hasPhysical = false;
            // Calculate the subtotal using the current database prices.
            // Also verify that every product is still published and in stock.
            foreach ($rows as $item) {
                // Do not allow checkout if a product is no longer published.
                if ($item['status'] !== 'published') {
                    apiFail('Your cart contains an unavailable product.', 400);
                }
                // Physical products require stock and delivery.
                if ($item['product_type'] === 'physical') {
                    $hasPhysical = true;
                    // Make sure the requested quantity is still available.
                    if ((int)$item['quantity'] > (int)$item['stock']) {
                        apiFail('Not enough stock for ' . $item['name'] . '.', 400);
                    }
                }
                // Add price multiplied by quantity to the subtotal.
                $subtotal += (float)$item['price'] * (int)$item['quantity'];
            }

            // Read the delivery address details.
            $address = isset($input['address']) ? trim($input['address']) : '';
            $city = isset($input['city']) ? trim($input['city']) : '';
            $postcode = isset($input['postcode']) ? trim($input['postcode']) : '';

            // Require delivery information if the cart contains physical items.
            // Digital-only orders do not require a delivery address.
            if ($hasPhysical && ($address === '' || $city === '' || $postcode === '')) {
                apiFail('Complete your delivery address.', 400);
            }

            // Charge RM8.00 shipping when physical products are present.
            // Digital-only orders have no shipping fee.
            $shipping = $hasPhysical ? 8.00 : 0.00;

            // Calculate the final order total.
            $total = $subtotal + $shipping;

            // Generate a customer-facing order number.
            $orderNumber = 'SN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            // Physical orders start as processing.
            // Digital-only orders are marked as digital delivered.
            $fulfillment = $hasPhysical ? 'processing' : 'digital delivered';

            // Create the order record.
            // payment_status is set to 'paid'
            $orderResult = apiExec(
                "INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, shipping_address, city, postcode, subtotal, shipping_fee, total, payment_status, fulfillment_status, is_demo_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 1)",
                'sissssssddds',
                array($orderNumber, (int)$user['id'], $user['name'], $email, $phone, $address, $city, $postcode, $subtotal, $shipping, $total, $fulfillment)
            );

            // Store the generated internal database order ID.
            $orderId = (int)$orderResult['insertId'];

            // Create one order_items record for every cart item.
            foreach ($rows as $item) {

                apiExec(
                    'INSERT INTO order_items (order_id, product_id, product_name, product_type, unit_price, quantity) VALUES (?, ?, ?, ?, ?, ?)',
                    'iissdi',
                    array($orderId, (int)$item['product_id'], $item['name'], $item['product_type'], (float)$item['price'], (int)$item['quantity'])
                );

                // Reduce stock only for physical products.
                if ($item['product_type'] === 'physical') {

                    // Prevent stock from becoming negative.
                    $updated = apiExec(
                        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                        'iii',
                        array((int)$item['quantity'], (int)$item['product_id'], (int)$item['quantity'])
                    );
                    // If exactly one product row was not updated, stock may
                    // have changed during checkout. Cancel the transaction.
                    if ($updated['affected'] !== 1) {
                        apiFail('Stock changed during checkout. Please try again.', 409);
                    }
                }
            }
            // Clear the user's cart after all order items are saved.
            apiExec('DELETE FROM cart_items WHERE cart_id = ?', 'i', array($cartId));
            // Commit the transaction because all checkout operations succeeded.
            $conn->commit();
            $conn->autocommit(true);
        } catch (Exception $checkoutError) {
            // If any checkout operation fails, undo the transaction.
            $conn->rollback();
            $conn->autocommit(true);
            // Pass the error to the main API error handler.
            throw $checkoutError;
        }

        // Record the successful demo order in the activity log.
        apiLog('Demo order created', 'Orders', $orderNumber, 'Simulated payment only; no real funds were collected.');
        // Retrieve the completed order from the database.
        $order = apiGetOrder($orderNumber);
        // Return the order details with HTTP 201 Created.
        apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 201);
    }

    // --------------------------------------------------------
    // CUSTOMER ORDER ROUTES
    // --------------------------------------------------------

    if ($resource === 'orders') {
        // All customer order operations require a signed-in user.
        $user = apiRequireLogin();

        // Returns the signed-in user's order history.
        if ($method === 'GET' && $id === null) {
            // Retrieve only orders belonging to the current user.
            $rows = apiRows('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', 'i', array((int)$user['id']));
            $orders = array();
            // Convert each database order into the frontend output format.
            foreach ($rows as $order) {
                $orders[] = apiOrderOutput($order);
            }
            apiRespond(array('success' => true, 'orders' => $orders), 200);
        }
        // Returns the details of one order belonging to the current user.
        if ($method === 'GET' && $id !== null) {
            // Retrieve the order using its public order number.
            $order = apiGetOrder($id);
            // Check both that the order exists and that it belongs to this user.
            // This prevents one customer from viewing another customer's order.
            if (!$order || (int)$order['user_id'] !== (int)$user['id']) {
                apiFail('Order not found.', 404);
            }
            apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 200);
        }

        // Allows a customer to request cancellation of their own order.
        if ($method === 'POST' && $id !== null && $action === 'cancellation') {
            $order = apiGetOrder($id);
            // Verify that the order exists and belongs to the signed-in user.
            if (!$order || (int)$order['user_id'] !== (int)$user['id']) {
                apiFail('Order not found.', 404);
            }
            // Only orders in these fulfillment states can be cancelled online.
            if (!in_array($order['fulfillment_status'], array('processing', 'digital delivered'), true)) {
                apiFail('This order can no longer be cancelled online.', 400);
            }
            // Prevent duplicate cancellation requests.
            if ($order['cancellation_status'] === 'pending') {
                apiFail('A cancellation request is already pending.', 409);
            }
            // Prevent a request for an order that has already been cancelled.
            if ($order['cancellation_status'] === 'approved') {
                apiFail('This order has already been cancelled.', 409);
            }
            // Mark the cancellation request as pending.
            apiExec("UPDATE orders SET cancellation_status = 'pending', updated_at = NOW() WHERE id = ?", 'i', array((int)$order['id']));
            // Record the customer's request in the activity log.
            apiLog('Cancellation requested', 'Orders', $id, 'Customer requested cancellation.');
            apiRespond(array('success' => true, 'message' => 'Cancellation request submitted.'), 200);
        }
    }


    // --------------------------------------------------------
    // ADMINISTRATOR ROUTES
    // --------------------------------------------------------

    if ($resource === 'admin') {
        // Every endpoint under /admin requires administrator privileges.
        $admin = apiRequireAdmin();
        // ----------------------------------------------------
        // Uploads a product image to the server. %
        // ----------------------------------------------------
        if ($id === 'upload-image' && $method === 'POST') {
            // Make sure the frontend submitted an image file.
            if (!isset($_FILES['image'])) {
                apiFail('Please select an image to upload.', 400);
            }
            // Retrieve the uploaded file information.
            $file = $_FILES['image'];
            // Check whether PHP reported an upload error.
            if ($file['error'] !== UPLOAD_ERR_OK) {
                // Handle the case where the server's upload limit was exceeded.
                if ($file['error'] === UPLOAD_ERR_INI_SIZE ||
                    $file['error'] === UPLOAD_ERR_FORM_SIZE) {

                    apiFail('Image exceeds the server upload limit.', 400);
                }
                // Return a general upload error for other upload failures.
                apiFail('Image upload failed. Please try again.', 400);
            }
            // Set the maximum permitted image size to 5 MB.
            $maxSize = 5 * 1024 * 1024;

            if ((int)$file['size'] > $maxSize) {
                apiFail('Image must not exceed 5 MB.', 400);
            }
            // Inspect the actual uploaded file to check whether it is an image.
            $imageInfo = @getimagesize($file['tmp_name']);

            if ($imageInfo === false) {
                apiFail('The uploaded file is not a valid image.', 400);
            }
            // Limit image width and height to 6000 pixels each.
            if ($imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
                apiFail('Image dimensions must not exceed 6000 x 6000 pixels.', 400);
            }
            // Determine the file extension from the actual image type,
            $extension = '';
            if ($imageInfo[2] === IMAGETYPE_JPEG) {
                $extension = 'jpg';
            } elseif ($imageInfo[2] === IMAGETYPE_PNG) {
                $extension = 'png';
            } elseif (
                defined('IMAGETYPE_WEBP') &&
                $imageInfo[2] === constant('IMAGETYPE_WEBP')
            ) {
                $extension = 'webp';
            } else {
                // Reject unsupported image formats.
                apiFail('Only JPG, PNG and WEBP images are supported.', 400);
            }

            // Build the destination directory for product images.
            $uploadDir = dirname(__DIR__)
                . DIRECTORY_SEPARATOR . 'images'
                . DIRECTORY_SEPARATOR . 'products';
            // Create the product image directory if it does not exist.
            if (!is_dir($uploadDir)) {
                // Use recursive directory creation so parent folders can also be created if necessary.
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    apiFail('Unable to create the product image directory.', 500);
                }
            }

            // Confirm that the server has permission to save files there.
            if (!is_writable($uploadDir)) {
                apiFail('Product image directory is not writable.', 500);
            }

            // Generate a unique filename on the server.
            $fileName = 'product-'
                . date('YmdHis')
                . '-'
                . substr(md5(uniqid(mt_rand(), true)), 0, 12)
                . '.'
                . $extension;

            // Combine the directory and filename into the final destination path.
            $destination = $uploadDir
                . DIRECTORY_SEPARATOR
                . $fileName;

            // Move the uploaded temporary file into the product image directory.
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                apiFail('Unable to save the uploaded image.', 500);
            }

            // Save a relative path in the database.
            $imagePath = 'images/products/' . $fileName;

            // Record the successful image upload in the activity log.
            apiLog(
                'Product image uploaded',
                'Products',
                $fileName,
                'Product image uploaded successfully.'
            );

            // Return the relative image path to the frontend.
            apiRespond(array(
                'success' => true,
                'message' => 'Image uploaded successfully.',
                'imagePath' => $imagePath
            ), 201);
        }

        // ----------------------------------------------------
        // Returns summary statistics for the administrator dashboard. %
        // ----------------------------------------------------

        if ($id === 'overview' && $method === 'GET') {
            // Count all products and the number currently published.
            $productStats = apiRows(
                "SELECT COUNT(*) AS total, SUM(status = 'published') AS published FROM products",
                '',
                array()
            );
            // Calculate total revenue and number of paid orders.
            $orderStats = apiRows(
                "SELECT COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS total_orders FROM orders WHERE payment_status = 'paid'",
                '',
                array()
            );

            // Count orders that currently have pending cancellation requests.
            $pendingStats = apiRows(
                "SELECT COUNT(*) AS total FROM orders WHERE cancellation_status = 'pending'",
                '',
                array()
            );

            // Return the statistics in one response for the dashboard.
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

        // ----------------------------------------------------
        // Returns all products for the administrator.
        // ----------------------------------------------------
        if ($id === 'products' && $action === null && $method === 'GET') {
            $rows = apiRows(
                'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id = p.id) AS likes FROM products p ORDER BY p.id DESC',
                '',
                array()
            );
            $products = array();
            // Convert each database record into the frontend product format.
            foreach ($rows as $product) {
                $products[] = apiProductOutput($product);
            }
            apiRespond(array('success' => true, 'products' => $products), 200);
        }


        // ----------------------------------------------------
        // Retrieves the version history of a product.
        // ----------------------------------------------------
        if ($id === 'products' && $action !== null && $extra === 'versions' && $method === 'GET') {
            // The product ID is taken from the URL.
            $productId = (int)$action;
            // Make sure the requested product exists.
            if (!apiGetProduct($productId)) {
                apiFail('Product not found.', 404);
            }
            // Retrieve all versions belonging to this product.
            $versions = apiRows(
                'SELECT version_number AS version, actor_name AS actor, status, note, snapshot, created_at AS createdAt FROM product_versions WHERE product_id = ? ORDER BY version_number DESC',
                'i',
                array($productId)
            );
            // If no version history exists, create an initial baseline version.
            if (!count($versions)) {
                // Retrieve the current product details.
                $product = apiGetProduct($productId);
                // Set the initial version status based on the product status.
                $initialStatus = $product['status'] === 'published'
                    ? 'live'
                    : ($product['status'] === 'draft' ? 'draft' : 'historical');
                // Convert the current product details into a JSON snapshot.
                $initialSnapshot = json_encode(
                    apiProductSnapshot($product),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                try {
                    // Save the baseline as version 1.
                    apiExec(
                        'INSERT INTO product_versions (product_id, version_number, created_by, actor_name, status, note, snapshot) VALUES (?, 1, ?, ?, ?, ?, ?)',
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

                } catch (ShopNestApiException $versionError) {
                    // If the version already exists, ignore the error.
                    if ($versionError->httpStatus !== 409) {
                        throw $versionError;
                    }
                }

                // Retrieve the version history again after attempting to create the baseline version.
                $versions = apiRows(
                    'SELECT version_number AS version, actor_name AS actor, status, note, snapshot, created_at AS createdAt FROM product_versions WHERE product_id = ? ORDER BY version_number DESC',
                    'i',
                    array($productId)
                );
            }

            // Convert each snapshot from its stored JSON string into
            // a PHP associative array before returning it to the frontend.
            foreach ($versions as $index => $version) {
                $decoded = json_decode($version['snapshot'], true);
                // Ensure the version number is returned as an integer.
                $versions[$index]['version'] = (int)$version['version'];
                // If the stored JSON cannot be decoded, return an empty array.
                $versions[$index]['snapshot'] = is_array($decoded) ? $decoded : array();
            }
            // Return the complete version history.
            apiRespond(array('success' => true, 'versions' => $versions), 200);
        }


        // ----------------------------------------------------
        // Restores an earlier product version as a new draft. %
        // ----------------------------------------------------
        if ($id === 'products' && $action !== null && $extra === 'restore' && $method === 'POST') {
            // Read the product ID and selected source version from the URL.
            $productId = (int)$action;
            $sourceVersion = (int)$last;
            // Check that the product still exists.
            if (!apiGetProduct($productId)) {
                apiFail('Product not found.', 404);
            }
            // Retrieve the snapshot for the selected version.
            // Both product ID and version number must match.
            $source = apiRows(
                'SELECT snapshot FROM product_versions WHERE product_id = ? AND version_number = ?',
                'ii',
                array($productId, $sourceVersion)
            );
            // Reject the request if the selected version does not exist.
            if (!count($source)) {
                apiFail('Product version not found.', 404);
            }
            // Decode the stored JSON snapshot into a PHP array.
            $snapshot = json_decode($source[0]['snapshot'], true);
            // A snapshot must be a valid array before it can be restored.
            if (!is_array($snapshot)) {
                apiFail('Product version snapshot is invalid.', 500);
            }
            // Find the highest existing version number.
            $latest = apiRows(
                'SELECT COALESCE(MAX(version_number), 0) AS version FROM product_versions WHERE product_id = ?',
                'i',
                array($productId)
            );
            // The restored version is always saved as a new version.
            $nextVersion = (int)$latest[0]['version'] + 1;
            // Force the restored snapshot to be a draft.
            // This prevents an older published version from being
            // automatically made live without administrator review.
            $snapshot['status'] = 'draft';
            // Insert the restored snapshot as a new draft version.
            apiExec(
                "INSERT INTO product_versions (product_id, version_number, created_by, actor_name, status, note, snapshot) VALUES (?, ?, ?, ?, 'draft', ?, ?)",
                'iiisss',
                array(
                    $productId,
                    $nextVersion,
                    (int)$admin['id'],
                    $admin['name'],
                    'Restored from version ' . $sourceVersion,
                    json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                )
            );
            // Record which version was restored and the new version number.
            apiLog(
                'Product version restored to draft',
                'Products',
                $productId,
                'Version ' . $sourceVersion . ' restored as draft v' . $nextVersion . '.'
            );
            // Tell the frontend which new draft version was created.
            apiRespond(array('success' => true, 'draftVersion' => $nextVersion), 201);
        }

        // ----------------------------------------------------
        // Returns all customer orders for the administrator.
        // ----------------------------------------------------
        if ($id === 'orders' && $action === null && $method === 'GET') {
            // Retrieve all orders, newest first.
            $rows = apiRows(
                'SELECT * FROM orders ORDER BY created_at DESC',
                '',
                array()
            );
            $orders = array();
            // Convert every order into the frontend output format.
            foreach ($rows as $order) {
                $orders[] = apiOrderOutput($order);
            }
            apiRespond(array('success' => true, 'orders' => $orders), 200);
        }

        // ----------------------------------------------------
        // Retrieves the details of one order for the administrator.
        // ----------------------------------------------------
        if ($id === 'orders' && $action !== null && $method === 'GET') {
            // Retrieve the selected order using its public order number.
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            // Return the order and its individual items.
            apiRespond(array('success' => true, 'order' => apiOrderOutput($order)), 200);
        }

        // ----------------------------------------------------
        // Updates an order's fulfillment status.
        // ----------------------------------------------------
        if ($id === 'orders' && $action !== null && $method === 'PATCH') {
            // Retrieve the order being updated.
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            // Read the new fulfillment status from the request body.
            $status = isset($input['fulfillmentStatus']) ? $input['fulfillmentStatus'] : '';
            // Only these fulfillment statuses are accepted.
            $allowed = array('processing', 'digital delivered', 'shipped', 'delivered', 'cancelled');
            // Reject any status that is not in the approved list.
            if (!in_array($status, $allowed, true)) {
                apiFail('Invalid fulfillment status.', 400);
            }
            // Do not allow an already cancelled order to be changed.
            if ($order['fulfillment_status'] === 'cancelled') {
                apiFail('A cancelled order cannot be updated.', 409);
            }
            // Update the order's fulfillment status and timestamp.
            apiExec(
                'UPDATE orders SET fulfillment_status = ?, updated_at = NOW() WHERE id = ?',
                'si',
                array($status, (int)$order['id'])
            );
            // Record the status change in the activity log.
            apiLog('Order status updated', 'Orders', $action, $status);

            apiRespond(array('success' => true, 'message' => 'Order status updated.'), 200);
        }

        // ----------------------------------------------------
        // Approves or rejects a customer's cancellation request.
        // ----------------------------------------------------
        if ($id === 'cancellations' && $action !== null && $method === 'POST') {
            // Retrieve the order associated with the cancellation request.
            $order = apiGetOrder($action);
            if (!$order) {
                apiFail('Order not found.', 404);
            }
            // Only pending cancellation requests can be processed.
            if ($order['cancellation_status'] !== 'pending') {
                apiFail('No pending cancellation request.', 409);
            }
            // Convert the submitted approve value into a boolean decision.
            $approve = !empty($input['approve']);
            // Start a transaction because cancellation may involve
            // several related database changes.
            $conn->autocommit(false);
            try {
                if ($approve) {
                    // Mark the cancellation as approved.
                    // Mark fulfillment as cancelled.
                    // Mark payment status as refunded.
                    apiExec(
                        "UPDATE orders SET cancellation_status = 'approved', fulfillment_status = 'cancelled', payment_status = 'refunded', updated_at = NOW() WHERE id = ?",
                        'i',
                        array((int)$order['id'])
                    );
                    // Retrieve the items belonging to the cancelled order.
                    $items = apiRows(
                        'SELECT product_id, product_type, quantity FROM order_items WHERE order_id = ?',
                        'i',
                        array((int)$order['id'])
                    );
                    // Return stock for physical products.
                    foreach ($items as $item) {

                        if ($item['product_type'] === 'physical' && $item['product_id'] !== null) {

                            apiExec(
                                'UPDATE products SET stock = stock + ? WHERE id = ?',
                                'ii',
                                array((int)$item['quantity'], (int)$item['product_id'])
                            );
                        }
                    }

                } else {
                    // Keep the order active and mark the cancellation request as rejected.
                    apiExec(
                        "UPDATE orders SET cancellation_status = 'rejected', updated_at = NOW() WHERE id = ?",
                        'i',
                        array((int)$order['id'])
                    );
                }
                // Commit the cancellation decision and any stock changes.
                $conn->commit();
                $conn->autocommit(true);
            } catch (Exception $cancelError) {
                // Undo all changes if any cancellation operation fails.
                $conn->rollback();
                $conn->autocommit(true);
                throw $cancelError;
            }
            // Record whether the administrator approved or rejected the request.
            apiLog(
                $approve ? 'Demo cancellation approved' : 'Cancellation rejected',
                'Orders',
                $action,
                'Demo status only; no real payment refund is processed.'
            );
            // Return a message based on the administrator's decision.
            apiRespond(array(
                'success' => true,
                'message' => $approve ? 'Cancellation approved (demo).' : 'Cancellation rejected.'
            ), 200);
        }

        // ----------------------------------------------------
        // Retrieves recent activity log entries.
        // ----------------------------------------------------

        if ($id === 'activity' && $method === 'GET') {
            // Retrieve the latest 300 activity log records.
            $logs = apiRows(
                'SELECT id, actor_name AS actor, action, resource_type AS resource, resource_id, detail, created_at AS time FROM activity_logs ORDER BY created_at DESC LIMIT 300',
                '',
                array()
            );
            // Convert each activity log ID to an integer.
            foreach ($logs as $index => $log) {
                $logs[$index]['id'] = (int)$log['id'];
            }
            // Return the activity logs to the administrator dashboard.
            apiRespond(array('success' => true, 'logs' => $logs), 200);
        }
    }

    // --------------------------------------------------------
    // UNKNOWN API ENDPOINT
    // --------------------------------------------------------
    apiFail('API endpoint not found.', 404);


// ============================================================
// GLOBAL ERROR HANDLING
// ============================================================
} catch (ShopNestApiException $e) {
    // Handle expected API errors, such as:
    // - 400 Bad Request
    // - 401 Unauthorized
    // - 403 Forbidden
    // - 404 Not Found
    // - 409 Conflict
    //
    // Return the original message and the HTTP status code
    // stored in the custom exception.
    apiRespond(array(
        'success' => false,
        'message' => $e->getMessage()
    ), $e->httpStatus);

} catch (Exception $e) {
    // Handle unexpected errors, including database or server errors.
    // Save the detailed error in the server log for debugging.
    error_log('ShopNest API error: ' . $e->getMessage());
    // Return a general HTTP 500 response.
    apiRespond(array(
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ), 500);
}
?>
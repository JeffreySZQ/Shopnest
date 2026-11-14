<?php
declare(strict_types=1);

/**
 * ShopNest REST API
 * Uses ONLY tables and columns defined in resources/ShopnestTable.sql.
 * PHP 8.1+ and MySQL 8+.
 */

// -----------------------------------------------------------------------------
// CORS, session and JSON helpers
// -----------------------------------------------------------------------------
$allowedOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400): never
{
    respond([
        'success' => false,
        'message' => $message
    ], $status);
}

function requestBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        fail('Request body must be a valid JSON object.');
    }

    return $data;
}

function requiredString(
    array $data,
    string $key,
    int $maxLength,
    string $label
): string {
    $value = trim((string)($data[$key] ?? ''));

    if ($value === '') {
        fail($label . ' is required.');
    }

    if (mb_strlen($value) > $maxLength) {
        fail($label . ' is too long.');
    }

    return $value;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): array
{
    $user = currentUser();

    if (!$user) {
        fail('Please sign in first.', 401);
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();

    if (($user['role'] ?? '') !== 'admin') {
        fail('Administrator access required.', 403);
    }

    return $user;
}

function userOutput(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role']
    ];
}

// -----------------------------------------------------------------------------
// DATABASE CONNECTION
// Uses the existing ShopNest database.
// -----------------------------------------------------------------------------

$dbHost = 'feenix-mariadb.swin.edu.au';
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
    error_log('ShopNest database connection error: ' . $conn->connect_error);
    fail('Database connection failed. Check your database settings.', 500);
}

$conn->set_charset('utf8mb4');

// -----------------------------------------------------------------------------
// DATABASE QUERY HELPERS
// -----------------------------------------------------------------------------

function queryRows(
    string $sql,
    string $types = '',
    array $params = []
): array {
    global $conn;

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log($conn->error);
        fail('Could not prepare database query.', 500);
    }

    if ($types !== '') {
        $bind = [$types];

        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if (!$stmt->execute()) {
        error_log($stmt->error);
        $stmt->close();
        fail('Database query failed.', 500);
    }

    $result = $stmt->get_result();

    $rows = $result
        ? $result->fetch_all(MYSQLI_ASSOC)
        : [];

    $stmt->close();

    return $rows;
}

function executeSql(
    string $sql,
    string $types = '',
    array $params = []
): int {
    global $conn;

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log($conn->error);
        fail('Could not prepare database statement.', 500);
    }

    if ($types !== '') {
        $bind = [$types];

        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if (!$stmt->execute()) {
        $errno = $stmt->errno;

        error_log($stmt->error);
        $stmt->close();

        if ($errno === 1062) {
            fail('A record with that unique value already exists.', 409);
        }

        fail('Database operation failed.', 500);
    }

    $affected = $stmt->affected_rows;

    $stmt->close();

    return $affected;
}

// -----------------------------------------------------------------------------
// ACTIVITY LOGGING
// Uses the existing activity_logs table.
// -----------------------------------------------------------------------------

function logActivity(
    string $action,
    string $resourceType = '',
    string $resourceId = '',
    string $detail = ''
): void {
    $user = currentUser();

    executeSql(
        'INSERT INTO activity_logs
        (actor_id, actor_name, action, resource_type, resource_id, detail)
        VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [
            $user['id'] ?? null,
            $user['name'] ?? 'Guest',
            $action,
            $resourceType,
            $resourceId,
            $detail
        ]
    );
}

// -----------------------------------------------------------------------------
// PRODUCT HELPERS
// -----------------------------------------------------------------------------

function getProduct(int $id): ?array
{
    $rows = queryRows(
        'SELECT p.*,
            (SELECT COUNT(*)
             FROM product_likes pl
             WHERE pl.product_id = p.id) AS likes
         FROM products p
         WHERE p.id = ?',
        'i',
        [$id]
    );

    return $rows[0] ?? null;
}

// Only database-backed product fields are returned.
// No non-existent database fields are fabricated.

function productOutput(array $p): array
{
    return [
        'id' => (int)$p['id'],
        'sku' => $p['sku'],
        'name' => $p['name'],
        'category' => $p['category'],
        'type' => $p['product_type'],
        'price' => (float)$p['price'],
        'stock' => (int)$p['stock'],
        'status' => $p['status'],
        'short' => $p['short_description'] ?? '',
        'description' => $p['description'] ?? '',
        'imagePath' => $p['image_path'],
        'imageAlt' => $p['image_alt'],
        'likes' => (int)($p['likes'] ?? 0)
    ];
}

function snapshotProduct(array $p): array
{
    return [
        'id' => (int)$p['id'],
        'sku' => $p['sku'],
        'name' => $p['name'],
        'category' => $p['category'],
        'type' => $p['product_type'],
        'price' => (float)$p['price'],
        'stock' => (int)$p['stock'],
        'short' => $p['short_description'] ?? '',
        'description' => $p['description'] ?? '',
        'status' => $p['status'],
        'imagePath' => $p['image_path'],
        'imageAlt' => $p['image_alt']
    ];
}

// -----------------------------------------------------------------------------
// CART HELPERS
// -----------------------------------------------------------------------------

function cartIdFor(int $userId): int
{
    $rows = queryRows(
        'SELECT id FROM carts WHERE user_id = ?',
        'i',
        [$userId]
    );

    if ($rows) {
        return (int)$rows[0]['id'];
    }

    executeSql(
        'INSERT INTO carts (user_id) VALUES (?)',
        'i',
        [$userId]
    );

    global $conn;

    return (int)$conn->insert_id;
}

// -----------------------------------------------------------------------------
// ORDER HELPERS
// -----------------------------------------------------------------------------

function orderItems(int $orderId): array
{
    $rows = queryRows(
        'SELECT
            oi.product_id,
            oi.product_name,
            oi.product_type,
            oi.unit_price,
            oi.quantity
         FROM order_items oi
         WHERE oi.order_id = ?',
        'i',
        [$orderId]
    );

    return array_map(
        static fn(array $item): array => [
            'productId' => $item['product_id'] === null
                ? null
                : (int)$item['product_id'],
            'name' => $item['product_name'],
            'type' => $item['product_type'],
            'price' => (float)$item['unit_price'],
            'quantity' => (int)$item['quantity']
        ],
        $rows
    );
}

function orderOutput(array $order, array $items): array
{
    return [
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
        'address' => $order['shipping_address'] ?? '',
        'city' => $order['city'] ?? '',
        'postcode' => $order['postcode'] ?? '',
        'createdAt' => $order['created_at'],
        'simulation' => (bool)$order['is_demo_payment']
    ];
}

function findOrderByNumber(string $number): ?array
{
    $rows = queryRows(
        'SELECT * FROM orders WHERE order_number = ?',
        's',
        [$number]
    );

    return $rows[0] ?? null;
}

// -----------------------------------------------------------------------------
// REST PATH PARSING
// Example: /Shopnest/resources/apis.php/products/12
// -----------------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['PATH_INFO'] ?? '';

if ($path === '') {
    $uriPath = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    ) ?: '/';

    $position = strpos($uriPath, 'apis.php');

    if ($position !== false) {
        $path = substr(
            $uriPath,
            $position + strlen('apis.php')
        );
    }
}

$segments = array_values(
    array_filter(
        explode('/', trim($path, '/')),
        'strlen'
    )
);

$resource = $segments[0] ?? 'health';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

$input = requestBody();

try {

    // -------------------------------------------------------------------------
    // HEALTH CHECK
    // GET /health
    // -------------------------------------------------------------------------

    if ($resource === 'health') {
        respond([
            'success' => true,
            'status' => 'ok',
            'database' => 'connected'
        ]);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION
    // -------------------------------------------------------------------------

    // POST /auth/register

    if ($resource === 'auth' && $method === 'POST' && $id === 'register') {

        $name = requiredString($input, 'name', 100, 'Name');

        $email = strtolower(
            requiredString($input, 'email', 191, 'Email')
        );

        $password = (string)($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Enter a valid email address.');
        }

        if (strlen($password) < 8) {
            fail('Password must contain at least 8 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        executeSql(
            "INSERT INTO users
            (name, email, password_hash, role)
            VALUES (?, ?, ?, 'customer')",
            'sss',
            [$name, $email, $hash]
        );

        $userId = (int)$conn->insert_id;

        $user = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'customer'
        ];

        session_regenerate_id(true);

        $_SESSION['user'] = $user;

        logActivity(
            'Account registered',
            'Account',
            (string)$userId,
            $email
        );

        respond([
            'success' => true,
            'user' => $user
        ], 201);
    }

    // POST /auth/login

    if ($resource === 'auth' && $method === 'POST' && $id === 'login') {

        $email = strtolower(
            trim((string)($input['email'] ?? ''))
        );

        $password = (string)($input['password'] ?? '');

        $rows = queryRows(
            'SELECT id, name, email, password_hash, role
             FROM users
             WHERE email = ?
             LIMIT 1',
            's',
            [$email]
        );

        if (!$rows || !password_verify($password, $rows[0]['password_hash'])) {
            fail('Incorrect email or password.', 401);
        }

        session_regenerate_id(true);

        $_SESSION['user'] = userOutput($rows[0]);

        logActivity(
            'Signed in',
            'Account',
            (string)$rows[0]['id'],
            $email
        );

        respond([
            'success' => true,
            'user' => $_SESSION['user']
        ]);
    }

    // POST /auth/logout

    if ($resource === 'auth' && $method === 'POST' && $id === 'logout') {

        $user = currentUser();

        if ($user) {
            logActivity(
                'Signed out',
                'Account',
                (string)$user['id'],
                $user['email']
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                '',
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        respond([
            'success' => true,
            'message' => 'Signed out.'
        ]);
    }

    // GET /auth/me

    if ($resource === 'auth' && $method === 'GET' && $id === 'me') {

        $user = currentUser();

        if (!$user) {
            fail('Not signed in.', 401);
        }

        respond([
            'success' => true,
            'user' => $user
        ]);
    }

    // -------------------------------------------------------------------------
    // PRODUCTS
    // -------------------------------------------------------------------------

    // GET /products
    // GET /products/{id}

    if ($resource === 'products' && $method === 'GET') {

        if ($id !== null) {

            $product = getProduct((int)$id);

            if (
                !$product ||
                (
                    $product['status'] !== 'published' &&
                    (currentUser()['role'] ?? '') !== 'admin'
                )
            ) {
                fail('Product not found.', 404);
            }

            respond([
                'success' => true,
                'product' => productOutput($product)
            ]);
        }

        $status = (string)($_GET['status'] ?? 'published');
        $search = trim((string)($_GET['search'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));

        $isAdmin = (currentUser()['role'] ?? '') === 'admin';

        if (!$isAdmin) {
            $status = 'published';
        }

        $sql = '
            SELECT p.*,
                (SELECT COUNT(*)
                 FROM product_likes pl
                 WHERE pl.product_id = p.id) AS likes
            FROM products p
            WHERE 1 = 1
        ';

        $types = '';
        $params = [];

        if ($status !== 'all') {

            if (!in_array($status, ['published', 'draft', 'archived'], true)) {
                fail('Invalid product status filter.');
            }

            $sql .= ' AND p.status = ?';

            $types .= 's';
            $params[] = $status;
        }

        if ($search !== '') {

            $sql .= '
                AND (
                    p.name LIKE ?
                    OR p.sku LIKE ?
                    OR p.category LIKE ?
                )
            ';

            $term = '%' . $search . '%';

            $types .= 'sss';

            array_push(
                $params,
                $term,
                $term,
                $term
            );
        }

        if ($category !== '') {

            $sql .= ' AND p.category = ?';

            $types .= 's';
            $params[] = $category;
        }

        $sql .= ' ORDER BY p.id DESC';

        $products = queryRows($sql, $types, $params);

        respond([
            'success' => true,
            'products' => array_map('productOutput', $products)
        ]);
    }

    // POST /products
    // Administrator creates a product.

    if ($resource === 'products' && $method === 'POST') {

        $admin = requireAdmin();

        $sku = requiredString($input, 'sku', 40, 'SKU');
        $name = requiredString($input, 'name', 150, 'Product name');
        $category = requiredString($input, 'category', 80, 'Category');

        $type = (string)($input['type'] ?? 'physical');
        $status = (string)($input['status'] ?? 'draft');

        if (!in_array($type, ['physical', 'digital'], true)) {
            fail('Invalid product type.');
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            fail('Invalid product status.');
        }

        $price = filter_var(
            $input['price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $input['stock'] ?? 0,
            FILTER_VALIDATE_INT
        );

        if (
            $price === false ||
            $price < 0 ||
            $stock === false ||
            $stock < 0
        ) {
            fail('Price and stock must be non-negative numbers.');
        }

        $short = (string)(
            $input['short'] ??
            $input['short_description'] ??
            ''
        );

        $description = (string)($input['description'] ?? '');

        $imagePath = trim(
            (string)($input['image_path'] ?? '')
        );

        $imageAlt = trim(
            (string)($input['image_alt'] ?? $name)
        );

        if (
            mb_strlen($short) > 255 ||
            mb_strlen($imagePath) > 255 ||
            mb_strlen($imageAlt) > 255
        ) {
            fail('A product text field is too long.');
        }

        executeSql(
            'INSERT INTO products
            (
                sku,
                name,
                image_path,
                image_alt,
                category,
                product_type,
                price,
                stock,
                short_description,
                description,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ssssssdisss',
            [
                $sku,
                $name,
                $imagePath,
                $imageAlt,
                $category,
                $type,
                (float)$price,
                (int)$stock,
                $short,
                $description,
                $status
            ]
        );

        $productId = (int)$conn->insert_id;

        $product = getProduct($productId);

        $snapshot = json_encode(
            snapshotProduct($product),
            JSON_UNESCAPED_UNICODE
        );

        $versionStatus = $status === 'published'
            ? 'live'
            : 'draft';

        executeSql(
            'INSERT INTO product_versions
            (
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
            [
                $productId,
                $admin['id'],
                $admin['name'],
                $versionStatus,
                'Product created',
                $snapshot
            ]
        );

        logActivity(
            'Product created',
            'Products',
            (string)$productId,
            $name
        );

        respond([
            'success' => true,
            'id' => $productId,
            'product' => productOutput($product)
        ], 201);
    }

    // PUT /products/{id}
    // Administrator updates a product.

    if ($resource === 'products' && $method === 'PUT' && $id !== null) {

        $admin = requireAdmin();

        $productId = (int)$id;

        $old = getProduct($productId);

        if (!$old) {
            fail('Product not found.', 404);
        }

        $sku = requiredString($input, 'sku', 40, 'SKU');
        $name = requiredString($input, 'name', 150, 'Product name');
        $category = requiredString($input, 'category', 80, 'Category');

        $type = (string)($input['type'] ?? $old['product_type']);
        $status = (string)($input['status'] ?? $old['status']);

        if (!in_array($type, ['physical', 'digital'], true)) {
            fail('Invalid product type.');
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            fail('Invalid product status.');
        }

        $price = filter_var(
            $input['price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $input['stock'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $price === false ||
            $price < 0 ||
            $stock === false ||
            $stock < 0
        ) {
            fail('Price and stock must be valid non-negative numbers.');
        }

        $short = (string)(
            $input['short'] ??
            $input['short_description'] ??
            $old['short_description'] ??
            ''
        );

        $description = (string)(
            $input['description'] ??
            $old['description'] ??
            ''
        );

        $imagePath = trim(
            (string)($input['image_path'] ?? $old['image_path'])
        );

        $imageAlt = trim(
            (string)($input['image_alt'] ?? $old['image_alt'])
        );

        if (
            mb_strlen($short) > 255 ||
            mb_strlen($imagePath) > 255 ||
            mb_strlen($imageAlt) > 255
        ) {
            fail('A product text field is too long.');
        }

        executeSql(
            'UPDATE products
             SET
                sku = ?,
                name = ?,
                image_path = ?,
                image_alt = ?,
                category = ?,
                product_type = ?,
                price = ?,
                stock = ?,
                short_description = ?,
                description = ?,
                status = ?,
                updated_at = NOW()
             WHERE id = ?',
            'ssssssdisssi',
            [
                $sku,
                $name,
                $imagePath,
                $imageAlt,
                $category,
                $type,
                (float)$price,
                (int)$stock,
                $short,
                $description,
                $status,
                $productId
            ]
        );

        executeSql(
            "UPDATE product_versions
             SET status = 'historical'
             WHERE product_id = ?
             AND status = 'live'",
            'i',
            [$productId]
        );

        $versionRows = queryRows(
            'SELECT COALESCE(MAX(version_number), 0) AS version
             FROM product_versions
             WHERE product_id = ?',
            'i',
            [$productId]
        );

        $version = (int)$versionRows[0]['version'] + 1;

        $product = getProduct($productId);

        $snapshot = json_encode(
            snapshotProduct($product),
            JSON_UNESCAPED_UNICODE
        );

        $versionStatus = $status === 'published'
            ? 'live'
            : 'draft';

        executeSql(
            'INSERT INTO product_versions
            (
                product_id,
                version_number,
                created_by,
                actor_name,
                status,
                note,
                snapshot
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iiissss',
            [
                $productId,
                $version,
                $admin['id'],
                $admin['name'],
                $versionStatus,
                'Product updated',
                $snapshot
            ]
        );

        logActivity(
            'Product updated',
            'Products',
            (string)$productId,
            $name . ' version ' . $version
        );

        respond([
            'success' => true,
            'product' => productOutput($product),
            'version' => $version
        ]);
    }

    // DELETE /products/{id}
    // Archives products instead of permanently deleting them.

    if ($resource === 'products' && $method === 'DELETE' && $id !== null) {

        requireAdmin();

        $productId = (int)$id;

        $product = getProduct($productId);

        if (!$product) {
            fail('Product not found.', 404);
        }

        executeSql(
            "UPDATE products
             SET status = 'archived', updated_at = NOW()
             WHERE id = ?",
            'i',
            [$productId]
        );

        logActivity(
            'Product archived',
            'Products',
            (string)$productId,
            $product['name']
        );

        respond([
            'success' => true,
            'message' => 'Product archived.'
        ]);
    }

    // -------------------------------------------------------------------------
    // CART
    // -------------------------------------------------------------------------

    if ($resource === 'cart') {

        $user = requireLogin();

        $cartId = cartIdFor((int)$user['id']);

        // GET /cart

        if ($method === 'GET') {

            $rows = queryRows(
                'SELECT
                    ci.product_id,
                    ci.quantity,
                    p.*,
                    (SELECT COUNT(*)
                     FROM product_likes pl
                     WHERE pl.product_id = p.id) AS likes
                 FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = ?
                 ORDER BY ci.id',
                'i',
                [$cartId]
            );

            $items = array_map(
                static fn(array $row): array => [
                    'productId' => (int)$row['product_id'],
                    'quantity' => (int)$row['quantity'],
                    'product' => productOutput($row)
                ],
                $rows
            );

            respond([
                'success' => true,
                'items' => $items
            ]);
        }

        // POST /cart/items

        if ($method === 'POST' && $id === 'items') {

            $productId = (int)($input['productId'] ?? 0);
            $quantity = (int)($input['quantity'] ?? 1);

            if (
                $productId < 1 ||
                $quantity < 1 ||
                $quantity > 99
            ) {
                fail('Invalid product or quantity.');
            }

            $product = getProduct($productId);

            if (!$product || $product['status'] !== 'published') {
                fail('Product unavailable.', 404);
            }

            $existing = queryRows(
                'SELECT quantity
                 FROM cart_items
                 WHERE cart_id = ?
                 AND product_id = ?',
                'ii',
                [$cartId, $productId]
            );

            $newQuantity = $quantity +
                (int)($existing[0]['quantity'] ?? 0);

            if (
                $product['product_type'] === 'physical' &&
                $newQuantity > (int)$product['stock']
            ) {
                fail('Not enough stock available.');
            }

            if ($existing) {

                executeSql(
                    'UPDATE cart_items
                     SET quantity = ?
                     WHERE cart_id = ?
                     AND product_id = ?',
                    'iii',
                    [$newQuantity, $cartId, $productId]
                );

            } else {

                executeSql(
                    'INSERT INTO cart_items
                    (cart_id, product_id, quantity)
                    VALUES (?, ?, ?)',
                    'iii',
                    [$cartId, $productId, $quantity]
                );
            }

            respond([
                'success' => true,
                'message' => 'Cart updated.'
            ]);
        }

        // PATCH /cart/items/{productId}

        if (
            $method === 'PATCH' &&
            $id === 'items' &&
            $action !== null
        ) {

            $productId = (int)$action;
            $quantity = (int)($input['quantity'] ?? 0);

            if ($quantity <= 0) {

                executeSql(
                    'DELETE FROM cart_items
                     WHERE cart_id = ?
                     AND product_id = ?',
                    'ii',
                    [$cartId, $productId]
                );

            } else {

                $product = getProduct($productId);

                if (!$product || $product['status'] !== 'published') {
                    fail('Product unavailable.', 404);
                }

                if ($quantity > 99) {
                    fail('Maximum quantity is 99.');
                }

                if (
                    $product['product_type'] === 'physical' &&
                    $quantity > (int)$product['stock']
                ) {
                    fail('Not enough stock available.');
                }

                executeSql(
                    'UPDATE cart_items
                     SET quantity = ?
                     WHERE cart_id = ?
                     AND product_id = ?',
                    'iii',
                    [$quantity, $cartId, $productId]
                );
            }

            respond([
                'success' => true,
                'message' => 'Cart updated.'
            ]);
        }

        // DELETE /cart/items/{productId}

        if (
            $method === 'DELETE' &&
            $id === 'items' &&
            $action !== null
        ) {

            executeSql(
                'DELETE FROM cart_items
                 WHERE cart_id = ?
                 AND product_id = ?',
                'ii',
                [$cartId, (int)$action]
            );

            respond([
                'success' => true,
                'message' => 'Cart item removed.'
            ]);
        }

        // DELETE /cart/items

        if (
            $method === 'DELETE' &&
            $id === 'items' &&
            $action === null
        ) {

            executeSql(
                'DELETE FROM cart_items WHERE cart_id = ?',
                'i',
                [$cartId]
            );

            respond([
                'success' => true,
                'message' => 'Cart cleared.'
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // WISHLIST
    // -------------------------------------------------------------------------

    if ($resource === 'wishlist') {

        $user = requireLogin();

        // GET /wishlist

        if ($method === 'GET') {

            $rows = queryRows(
                'SELECT product_id
                 FROM wishlist_items
                 WHERE user_id = ?
                 ORDER BY created_at DESC',
                'i',
                [$user['id']]
            );

            respond([
                'success' => true,
                'productIds' => array_map(
                    static fn(array $row): int => (int)$row['product_id'],
                    $rows
                )
            ]);
        }

        // POST /wishlist/{productId}
        // Toggles the product in the wishlist.

        if ($method === 'POST' && $id !== null) {

            $productId = (int)$id;

            $product = getProduct($productId);

            if (!$product || $product['status'] !== 'published') {
                fail('Product not found.', 404);
            }

            $found = queryRows(
                'SELECT product_id
                 FROM wishlist_items
                 WHERE user_id = ?
                 AND product_id = ?',
                'ii',
                [$user['id'], $productId]
            );

            if ($found) {

                executeSql(
                    'DELETE FROM wishlist_items
                     WHERE user_id = ?
                     AND product_id = ?',
                    'ii',
                    [$user['id'], $productId]
                );

                $liked = false;

            } else {

                executeSql(
                    'INSERT INTO wishlist_items (user_id, product_id)
                     VALUES (?, ?)',
                    'ii',
                    [$user['id'], $productId]
                );

                $liked = true;
            }

            respond([
                'success' => true,
                'liked' => $liked
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // CHECKOUT
    // Simulated payment only. No real payment gateway is contacted.
    // -------------------------------------------------------------------------

    if ($resource === 'checkout' && $method === 'POST') {

        $user = requireLogin();

        $cartId = cartIdFor((int)$user['id']);

        $conn->begin_transaction();

        try {

            $rows = queryRows(
                'SELECT
                    ci.product_id,
                    ci.quantity,
                    p.name,
                    p.product_type,
                    p.price,
                    p.stock,
                    p.status
                 FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = ?
                 FOR UPDATE',
                'i',
                [$cartId]
            );

            if (!$rows) {
                throw new RuntimeException('Your cart is empty.');
            }

            $email = trim(
                (string)($input['email'] ?? $user['email'])
            );

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }

            $subtotal = 0.0;
            $hasPhysical = false;

            foreach ($rows as $row) {

                if ($row['status'] !== 'published') {
                    throw new RuntimeException(
                        'Cart contains an unavailable product.'
                    );
                }

                if ($row['product_type'] === 'physical') {

                    $hasPhysical = true;

                    if ((int)$row['quantity'] > (int)$row['stock']) {
                        throw new RuntimeException(
                            'Not enough stock for ' . $row['name'] . '.'
                        );
                    }
                }

                $subtotal +=
                    (float)$row['price'] * (int)$row['quantity'];
            }

            $address = trim(
                (string)($input['address'] ?? '')
            );

            $city = trim(
                (string)($input['city'] ?? '')
            );

            $postcode = trim(
                (string)($input['postcode'] ?? '')
            );

            if (
                $hasPhysical &&
                (
                    $address === '' ||
                    $city === '' ||
                    $postcode === ''
                )
            ) {
                throw new RuntimeException(
                    'Shipping address, city and postcode are required for physical products.'
                );
            }

            $shipping = $hasPhysical ? 8.00 : 0.00;
            $total = $subtotal + $shipping;

            $orderNumber = 'SN-' .
                date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(3)));

            $fulfillment = $hasPhysical
                ? 'processing'
                : 'digital delivered';

            $userId = (int)$user['id'];
            $customerName = (string)$user['name'];

            $stmt = $conn->prepare(
                "INSERT INTO orders
                (
                    order_number,
                    user_id,
                    customer_name,
                    customer_email,
                    shipping_address,
                    city,
                    postcode,
                    subtotal,
                    shipping_fee,
                    total,
                    payment_status,
                    fulfillment_status,
                    is_demo_payment
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 1)"
            );

            if (!$stmt) {
                throw new RuntimeException($conn->error);
            }

            $stmt->bind_param(
                'sisssssddds',
                $orderNumber,
                $userId,
                $customerName,
                $email,
                $address,
                $city,
                $postcode,
                $subtotal,
                $shipping,
                $total,
                $fulfillment
            );

            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }

            $orderDbId = (int)$conn->insert_id;

            $stmt->close();

            foreach ($rows as $row) {

                executeSql(
                    'INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        product_name,
                        product_type,
                        unit_price,
                        quantity
                    )
                    VALUES (?, ?, ?, ?, ?, ?)',
                    'iissdi',
                    [
                        $orderDbId,
                        (int)$row['product_id'],
                        $row['name'],
                        $row['product_type'],
                        (float)$row['price'],
                        (int)$row['quantity']
                    ]
                );

                if ($row['product_type'] === 'physical') {

                    $changed = executeSql(
                        'UPDATE products
                         SET stock = stock - ?
                         WHERE id = ?
                         AND stock >= ?',
                        'iii',
                        [
                            (int)$row['quantity'],
                            (int)$row['product_id'],
                            (int)$row['quantity']
                        ]
                    );

                    if ($changed !== 1) {
                        throw new RuntimeException(
                            'Stock changed during checkout.'
                        );
                    }
                }
            }

            executeSql(
                'DELETE FROM cart_items WHERE cart_id = ?',
                'i',
                [$cartId]
            );

            $conn->commit();

        } catch (Throwable $error) {

            $conn->rollback();

            $message = $error->getMessage();

            if (
                in_array(
                    $message,
                    [
                        'Your cart is empty.',
                        'Enter a valid email address.',
                        'Cart contains an unavailable product.'
                    ],
                    true
                ) ||
                str_starts_with($message, 'Not enough stock') ||
                str_starts_with($message, 'Shipping address')
            ) {
                fail($message);
            }

            error_log('ShopNest checkout error: ' . $message);

            fail(
                'Checkout could not be completed. Please try again.',
                500
            );
        }

        logActivity(
            'Demo order created',
            'Orders',
            $orderNumber,
            'Demo payment; no real funds processed.'
        );

        $order = findOrderByNumber($orderNumber);

        respond([
            'success' => true,
            'order' => orderOutput(
                $order,
                orderItems($orderDbId)
            )
        ], 201);
    }

    // -------------------------------------------------------------------------
    // CUSTOMER ORDERS
    // -------------------------------------------------------------------------

    if ($resource === 'orders') {

        $user = requireLogin();

        // GET /orders

        if ($method === 'GET' && $id === null) {

            if ($user['role'] === 'admin') {

                $orders = queryRows(
                    'SELECT * FROM orders ORDER BY created_at DESC'
                );

            } else {

                $orders = queryRows(
                    'SELECT * FROM orders
                     WHERE user_id = ?
                     ORDER BY created_at DESC',
                    'i',
                    [$user['id']]
                );
            }

            $output = [];

            foreach ($orders as $order) {
                $output[] = orderOutput(
                    $order,
                    orderItems((int)$order['id'])
                );
            }

            respond([
                'success' => true,
                'orders' => $output
            ]);
        }

        // GET /orders/{orderNumber}

        if ($method === 'GET' && $id !== null) {

            $order = findOrderByNumber((string)$id);

            if (!$order) {
                fail('Order not found.', 404);
            }

            if (
                $user['role'] !== 'admin' &&
                (int)$order['user_id'] !== (int)$user['id']
            ) {
                fail('Order not found.', 404);
            }

            respond([
                'success' => true,
                'order' => orderOutput(
                    $order,
                    orderItems((int)$order['id'])
                )
            ]);
        }

        // POST /orders/{orderNumber}/cancellation

        if (
            $method === 'POST' &&
            $id !== null &&
            $action === 'cancellation'
        ) {

            $order = findOrderByNumber((string)$id);

            if (
                !$order ||
                (int)$order['user_id'] !== (int)$user['id']
            ) {
                fail('Order not found.', 404);
            }

            if (
                !in_array(
                    $order['fulfillment_status'],
                    ['processing', 'digital delivered'],
                    true
                )
            ) {
                fail('This order can no longer be cancelled online.');
            }

            if ($order['cancellation_status'] === 'pending') {
                fail('A cancellation request is already pending.');
            }

            executeSql(
                "UPDATE orders
                 SET cancellation_status = 'pending',
                     updated_at = NOW()
                 WHERE id = ?",
                'i',
                [$order['id']]
            );

            logActivity(
                'Cancellation requested',
                'Orders',
                (string)$id,
                'Customer requested cancellation.'
            );

            respond([
                'success' => true,
                'message' => 'Cancellation request submitted.'
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // ADMIN ROUTES
    // -------------------------------------------------------------------------

    if ($resource === 'admin') {

        $admin = requireAdmin();

        // GET /admin/overview

        if ($id === 'overview' && $method === 'GET') {

            $counts = queryRows(
                "SELECT
                    COUNT(*) AS product_count,
                    SUM(status = 'published') AS published_count
                 FROM products"
            );

            $sales = queryRows(
                "SELECT
                    COALESCE(SUM(total), 0) AS sales,
                    COUNT(*) AS order_count
                 FROM orders
                 WHERE payment_status = 'paid'"
            );

            $pending = queryRows(
                "SELECT COUNT(*) AS pending
                 FROM orders
                 WHERE cancellation_status = 'pending'"
            );

            respond([
                'success' => true,
                'overview' => [
                    'products' => (int)$counts[0]['product_count'],
                    'published' => (int)$counts[0]['published_count'],
                    'sales' => (float)$sales[0]['sales'],
                    'orders' => (int)$sales[0]['order_count'],
                    'pendingCancellations' => (int)$pending[0]['pending']
                ]
            ]);
        }

        // GET /admin/products/{productId}
        // Returns product version history.

        if (
            $id === 'products' &&
            $action !== null &&
            !isset($segments[3]) &&
            $method === 'GET'
        ) {

            $productId = (int)$action;

            if ($productId < 1 || !getProduct($productId)) {
                fail('Product not found.', 404);
            }

            $versions = queryRows(
                'SELECT
                    version_number AS version,
                    created_by,
                    actor_name AS actor,
                    status,
                    note,
                    snapshot,
                    created_at AS createdAt
                 FROM product_versions
                 WHERE product_id = ?
                 ORDER BY version_number DESC',
                'i',
                [$productId]
            );

            foreach ($versions as &$version) {

                $version['version'] = (int)$version['version'];

                $version['snapshot'] = json_decode(
                    $version['snapshot'],
                    true
                ) ?: [];
            }

            unset($version);

            respond([
                'success' => true,
                'versions' => $versions
            ]);
        }

        // GET /admin/products

        if (
            $id === 'products' &&
            $action === null &&
            $method === 'GET'
        ) {

            $products = queryRows(
                'SELECT p.*,
                    (SELECT COUNT(*)
                     FROM product_likes pl
                     WHERE pl.product_id = p.id) AS likes
                 FROM products p
                 ORDER BY p.id DESC'
            );

            respond([
                'success' => true,
                'products' => array_map('productOutput', $products)
            ]);
        }

        // GET /admin/orders

        if (
            $id === 'orders' &&
            $action === null &&
            $method === 'GET'
        ) {

            $orders = queryRows(
                'SELECT * FROM orders ORDER BY created_at DESC'
            );

            $output = [];

            foreach ($orders as $order) {
                $output[] = orderOutput(
                    $order,
                    orderItems((int)$order['id'])
                );
            }

            respond([
                'success' => true,
                'orders' => $output
            ]);
        }

        // GET /admin/orders/{orderNumber}

        if (
            $id === 'orders' &&
            $action !== null &&
            $method === 'GET'
        ) {

            $order = findOrderByNumber($action);

            if (!$order) {
                fail('Order not found.', 404);
            }

            respond([
                'success' => true,
                'order' => orderOutput(
                    $order,
                    orderItems((int)$order['id'])
                )
            ]);
        }

        // PATCH /admin/orders/{orderNumber}

        if (
            $id === 'orders' &&
            $action !== null &&
            $method === 'PATCH'
        ) {

            $order = findOrderByNumber($action);

            if (!$order) {
                fail('Order not found.', 404);
            }

            $status = (string)(
                $input['fulfillmentStatus'] ??
                $input['status'] ??
                ''
            );

            $allowedStatuses = [
                'processing',
                'digital delivered',
                'shipped',
                'delivered',
                'cancelled'
            ];

            if (!in_array($status, $allowedStatuses, true)) {
                fail('Invalid fulfillment status.');
            }

            executeSql(
                'UPDATE orders
                 SET fulfillment_status = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                'si',
                [$status, $order['id']]
            );

            logActivity(
                'Order status updated',
                'Orders',
                $action,
                $status
            );

            respond([
                'success' => true,
                'message' => 'Order status updated.'
            ]);
        }

        // POST /admin/cancellations/{orderNumber}

        if (
            $id === 'cancellations' &&
            $action !== null &&
            $method === 'POST'
        ) {

            $order = findOrderByNumber($action);

            if (!$order) {
                fail('Order not found.', 404);
            }

            if ($order['cancellation_status'] !== 'pending') {
                fail('No pending cancellation request.');
            }

            $approve = (bool)($input['approve'] ?? false);

            $conn->begin_transaction();

            try {

                if ($approve) {

                    // Demo status only. No actual payment refund is processed.

                    executeSql(
                        "UPDATE orders
                         SET cancellation_status = 'approved',
                             fulfillment_status = 'cancelled',
                             payment_status = 'refunded',
                             updated_at = NOW()
                         WHERE id = ?",
                        'i',
                        [$order['id']]
                    );

                    $items = queryRows(
                        'SELECT product_id, product_type, quantity
                         FROM order_items
                         WHERE order_id = ?',
                        'i',
                        [$order['id']]
                    );

                    foreach ($items as $item) {

                        if (
                            $item['product_type'] === 'physical' &&
                            $item['product_id'] !== null
                        ) {

                            executeSql(
                                'UPDATE products
                                 SET stock = stock + ?
                                 WHERE id = ?',
                                'ii',
                                [
                                    (int)$item['quantity'],
                                    (int)$item['product_id']
                                ]
                            );
                        }
                    }

                } else {

                    executeSql(
                        "UPDATE orders
                         SET cancellation_status = 'rejected',
                             updated_at = NOW()
                         WHERE id = ?",
                        'i',
                        [$order['id']]
                    );
                }

                $conn->commit();

            } catch (Throwable $error) {

                $conn->rollback();

                throw $error;
            }

            logActivity(
                $approve
                    ? 'Demo cancellation approved'
                    : 'Cancellation declined',
                'Orders',
                $action,
                'Demo status only; no payment provider refund.'
            );

            respond([
                'success' => true,
                'message' => $approve
                    ? 'Cancellation approved (demo).'
                    : 'Cancellation rejected.'
            ]);
        }

        // GET /admin/activity

        if ($id === 'activity' && $method === 'GET') {

            $logs = queryRows(
                'SELECT
                    id,
                    actor_name AS actor,
                    action,
                    resource_type AS resource,
                    resource_id,
                    detail,
                    created_at AS time
                 FROM activity_logs
                 ORDER BY created_at DESC
                 LIMIT 300'
            );

            respond([
                'success' => true,
                'logs' => $logs
            ]);
        }

        // POST /admin/products/{productId}/restore/{versionNumber}

        if (
            $id === 'products' &&
            $action !== null &&
            ($segments[3] ?? null) === 'restore' &&
            $method === 'POST'
        ) {

            $productId = (int)$action;
            $versionNumber = (int)($segments[4] ?? 0);

            $product = getProduct($productId);

            if (!$product) {
                fail('Product not found.', 404);
            }

            $found = queryRows(
                'SELECT snapshot
                 FROM product_versions
                 WHERE product_id = ?
                 AND version_number = ?',
                'ii',
                [$productId, $versionNumber]
            );

            if (!$found) {
                fail('Product version not found.', 404);
            }

            $snapshot = json_decode(
                $found[0]['snapshot'],
                true
            );

            if (!is_array($snapshot)) {
                fail('Product version snapshot is invalid.', 500);
            }

            $versionRows = queryRows(
                'SELECT COALESCE(MAX(version_number), 0) AS version
                 FROM product_versions
                 WHERE product_id = ?',
                'i',
                [$productId]
            );

            $nextVersion = (int)$versionRows[0]['version'] + 1;

            $snapshot['status'] = 'draft';

            $json = json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE
            );

            executeSql(
                "INSERT INTO product_versions
                (
                    product_id,
                    version_number,
                    created_by,
                    actor_name,
                    status,
                    note,
                    snapshot
                )
                VALUES (?, ?, ?, ?, 'draft', ?, ?)",
                'iiisss',
                [
                    $productId,
                    $nextVersion,
                    $admin['id'],
                    $admin['name'],
                    'Restored draft from version ' . $versionNumber,
                    $json
                ]
            );

            logActivity(
                'Product version restored to draft',
                'Products',
                (string)$productId,
                'Version ' . $versionNumber . ' -> ' . $nextVersion
            );

            respond([
                'success' => true,
                'draftVersion' => $nextVersion
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // UNKNOWN ENDPOINT
    // -------------------------------------------------------------------------

    fail('API endpoint not found.', 404);

} catch (Throwable $error) {

    error_log('ShopNest API exception: ' . $error->getMessage());

    fail('An unexpected server error occurred.', 500);
}
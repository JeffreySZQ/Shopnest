<?php
/**
 * ShopNest REST API
 * Adapted from the single-file apis.php pattern in vite-project-for-students.
 * PHP 8.0+ / MySQL 8+ recommended.
 * Does not create or alter database tables.
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1. CORS, sessions and JSON response helpers
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

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

function respond($data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $message, int $status = 400): never {
    respond(['success' => false, 'message' => $message], $status);
}
function body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) fail('Request body must be a valid JSON object.', 400);
    return $data;
}
function requiredString(array $data, string $key, int $max, string $label): string {
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '') fail($label . ' is required.');
    if (strlen($value) > $max) fail($label . ' is too long.');
    return $value;
}
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}
function requireLogin(): array {
    $user = currentUser();
    if (!$user) fail('Please sign in first.', 401);
    return $user;
}
function requireAdmin(): array {
    $user = requireLogin();
    if (($user['role'] ?? '') !== 'admin') fail('Administrator access required.', 403);
    return $user;
}
function userOutput(array $row): array {
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role']
    ];
}

// -----------------------------------------------------------------------------
// 2. MySQL connection - use your existing ShopNest database
// -----------------------------------------------------------------------------
$dbHost = 'feenix-mariadb.swin.edu.au';
$dbUser = 's105534669';
$dbPass = '230706';
$dbName = 's105534669_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log('ShopNest DB connection error: ' . $conn->connect_error);
    fail('Database connection failed. Check the database credentials in apis.php.', 500);
}
$conn->set_charset('utf8mb4');

function queryRows(string $sql, string $types = '', array $params = []): array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) fail('Database query could not be prepared.', 500);
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $key => $value) $bind[] = &$params[$key];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    if (!$stmt->execute()) {
        error_log($stmt->error);
        fail('Database query failed.', 500);
    }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function executeSql(string $sql, string $types = '', array $params = []): int {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) fail('Database statement could not be prepared.', 500);
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $key => $value) $bind[] = &$params[$key];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        $error = $stmt->error;
        $stmt->close();
        if ($errno === 1062) fail('A record with that unique value already exists.', 409);
        error_log($error);
        fail('Database operation failed.', 500);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}
function logActivity(string $action, string $resourceType = '', string $resourceId = '', string $detail = ''): void {
    $user = currentUser();
    executeSql(
        'INSERT INTO activity_logs (actor_id, actor_name, action, resource_type, resource_id, detail) VALUES (?, ?, ?, ?, ?, ?)',
        'isssss',
        [$user['id'] ?? null, $user['name'] ?? 'Guest', $action, $resourceType, $resourceId, $detail]
    );
}
function productOutput(array $p): array {
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
        // These are frontend presentation properties; the existing SQL schema has no columns for them.
        'features' => [],
        'emoji' => '📦',
        'tone' => 'blue',
        'featured' => false,
        'likes' => (int)($p['likes'] ?? 0)
    ];
}
function getProduct(int $id): ?array {
    $rows = queryRows(
        'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id=p.id) AS likes FROM products p WHERE p.id=?',
        'i', [$id]
    );
    return $rows[0] ?? null;
}
function cartIdFor(int $userId): int {
    $rows = queryRows('SELECT id FROM carts WHERE user_id=?', 'i', [$userId]);
    if ($rows) return (int)$rows[0]['id'];
    executeSql('INSERT INTO carts (user_id) VALUES (?)', 'i', [$userId]);
    global $conn;
    return (int)$conn->insert_id;
}
function orderOutput(array $o, array $items): array {
    return [
        'id' => $o['order_number'],
        'databaseId' => (int)$o['id'],
        'userId' => (int)$o['user_id'],
        'customer' => $o['customer_name'],
        'email' => $o['customer_email'],
        'items' => $items,
        'subtotal' => (float)$o['subtotal'],
        'shipping' => (float)$o['shipping_fee'],
        'total' => (float)$o['total'],
        'paymentStatus' => $o['payment_status'],
        'fulfillmentStatus' => $o['fulfillment_status'],
        'cancellationStatus' => $o['cancellation_status'],
        'address' => $o['shipping_address'] ?? '',
        'city' => $o['city'] ?? '',
        'postcode' => $o['postcode'] ?? '',
        'createdAt' => $o['created_at'],
        'simulation' => (bool)$o['is_demo_payment']
    ];
}
function orderItems(int $orderId): array {
    $rows = queryRows('SELECT oi.*, p.product_type AS current_type FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?', 'i', [$orderId]);
    return array_map(fn($i) => [
        'productId' => $i['product_id'] === null ? null : (int)$i['product_id'],
        'name' => $i['product_name'],
        'type' => $i['current_type'] ?? $i['product_type'],
        'price' => (float)$i['unit_price'],
        'quantity' => (int)$i['quantity']
    ], $rows);
}
function findOrderByNumber(string $number): ?array {
    $rows = queryRows('SELECT * FROM orders WHERE order_number=?', 's', [$number]);
    return $rows[0] ?? null;
}
function snapshotProduct(array $p): array {
    return [
        'id' => (int)$p['id'], 'sku' => $p['sku'], 'name' => $p['name'],
        'category' => $p['category'], 'type' => $p['product_type'],
        'price' => (float)$p['price'], 'stock' => (int)$p['stock'],
        'short' => $p['short_description'] ?? '', 'description' => $p['description'] ?? '',
        'status' => $p['status'], 'imagePath' => $p['image_path'], 'imageAlt' => $p['image_alt']
    ];
}

// -----------------------------------------------------------------------------
// 3. REST path parsing, following the lecturer's PATH_INFO approach
// -----------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $position = strpos($uriPath, 'apis.php');
    if ($position !== false) $path = substr($uriPath, $position + strlen('apis.php'));
}
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$resource = $segments[0] ?? 'health';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;
$input = body();

try {
    // -------------------------------------------------------------------------
    // HEALTH
    // -------------------------------------------------------------------------
    if ($resource === 'health' || ($resource === '' && $method === 'GET')) {
        respond(['success' => true, 'status' => 'ok', 'database' => 'connected']);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION
    // -------------------------------------------------------------------------
    if ($resource === 'auth' && $method === 'POST' && $id === 'register') {
        $name = requiredString($input, 'name', 100, 'Name');
        $email = strtolower(requiredString($input, 'email', 191, 'Email'));
        $password = (string)($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
        if (strlen($password) < 8) fail('Password must contain at least 8 characters.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        executeSql("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'customer')", 'sss', [$name,$email,$hash]);
        global $conn;
        $newId = (int)$conn->insert_id;
        $user = ['id'=>$newId,'name'=>$name,'email'=>$email,'role'=>'customer'];
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        logActivity('Account registered', 'Account', (string)$newId, $email);
        respond(['success'=>true,'user'=>$user], 201);
    }
    if ($resource === 'auth' && $method === 'POST' && $id === 'login') {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $rows = queryRows('SELECT id,name,email,password_hash,role FROM users WHERE email=? LIMIT 1', 's', [$email]);
        if (!$rows || !password_verify($password, $rows[0]['password_hash'])) fail('Incorrect email or password.', 401);
        session_regenerate_id(true);
        $_SESSION['user'] = userOutput($rows[0]);
        logActivity('Signed in', 'Account', (string)$rows[0]['id'], $email);
        respond(['success'=>true,'user'=>$_SESSION['user']]);
    }
    if ($resource === 'auth' && $method === 'POST' && $id === 'logout') {
        $user = currentUser();
        if ($user) logActivity('Signed out', 'Account', (string)$user['id'], $user['email']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['success'=>true,'message'=>'Signed out.']);
    }
    if ($resource === 'auth' && $method === 'GET' && $id === 'me') {
        $user = currentUser();
        if (!$user) fail('Not signed in.', 401);
        respond(['success'=>true,'user'=>$user]);
    }

    // -------------------------------------------------------------------------
    // PRODUCTS - public GET; admin POST/PUT/DELETE (DELETE archives)
    // -------------------------------------------------------------------------
    if ($resource === 'products' && $method === 'GET') {
        if ($id !== null) {
            $p = getProduct((int)$id);
            if (!$p || ($p['status'] !== 'published' && (currentUser()['role'] ?? '') !== 'admin')) fail('Product not found.', 404);
            respond(['success'=>true,'product'=>productOutput($p)]);
        }
        $status = $_GET['status'] ?? 'published';
        $search = trim((string)($_GET['search'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $isAdmin = (currentUser()['role'] ?? '') === 'admin';
        if (!$isAdmin) $status = 'published'; // Public visitors must never retrieve drafts/archived products.
        $sql = 'SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id=p.id) AS likes FROM products p WHERE 1=1';
        $types = ''; $params = [];
        if ($status !== 'all') { $sql .= ' AND p.status=?'; $types .= 's'; $params[] = $status; }
        if ($search !== '') { $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)'; $term='%'.$search.'%'; $types.='sss'; array_push($params,$term,$term,$term); }
        if ($category !== '') { $sql .= ' AND p.category=?'; $types.='s'; $params[]=$category; }
        $sql .= ' ORDER BY p.id DESC';
        $rows = queryRows($sql,$types,$params);
        respond(['success'=>true,'products'=>array_map('productOutput',$rows)]);
    }
    if ($resource === 'products' && $method === 'POST') {
        $admin = requireAdmin();
        $sku = requiredString($input,'sku',40,'SKU');
        $name = requiredString($input,'name',150,'Product name');
        $category = requiredString($input,'category',80,'Category');
        $type = (string)($input['type'] ?? 'physical');
        $status = (string)($input['status'] ?? 'draft');
        if (!in_array($type,['physical','digital'],true)) fail('Invalid product type.');
        if (!in_array($status,['draft','published','archived'],true)) fail('Invalid product status.');
        $price = filter_var($input['price'] ?? null,FILTER_VALIDATE_FLOAT);
        $stock = filter_var($input['stock'] ?? 0,FILTER_VALIDATE_INT);
        if ($price === false || $price < 0 || $stock === false || $stock < 0) fail('Price and stock must be non-negative numbers.');
        $short = (string)($input['short'] ?? $input['short_description'] ?? '');
        $description = (string)($input['description'] ?? '');
        $imagePath = (string)($input['image_path'] ?? '');
        $imageAlt = (string)($input['image_alt'] ?? $name);
        executeSql('INSERT INTO products (sku,name,image_path,image_alt,category,product_type,price,stock,short_description,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)', 'ssssssdisss', [$sku,$name,$imagePath,$imageAlt,$category,$type,(float)$price,(int)$stock,$short,$description,$status]);
        global $conn;
        $productId=(int)$conn->insert_id;
        $p=getProduct($productId);
        $snap=json_encode(snapshotProduct($p),JSON_UNESCAPED_UNICODE);
        executeSql("INSERT INTO product_versions (product_id,version_number,created_by,actor_name,status,note,snapshot) VALUES (?,1,?,?,'live','Product created',?)", 'iiss', [$productId,$admin['id'],$admin['name'],$snap]);
        logActivity('Product created','Products',(string)$productId,$name);
        respond(['success'=>true,'id'=>$productId,'product'=>productOutput($p)],201);
    }
    if ($resource === 'products' && $method === 'PUT' && $id !== null) {
        $admin=requireAdmin(); $productId=(int)$id; $old=getProduct($productId);
        if (!$old) fail('Product not found.',404);
        $sku=requiredString($input,'sku',40,'SKU'); $name=requiredString($input,'name',150,'Product name');
        $category=requiredString($input,'category',80,'Category'); $type=(string)($input['type']??'physical'); $status=(string)($input['status']??'draft');
        if (!in_array($type,['physical','digital'],true)||!in_array($status,['draft','published','archived'],true)) fail('Invalid product type or status.');
        $price=(float)($input['price']??-1); $stock=(int)($input['stock']??-1);
        if ($price<0||$stock<0) fail('Price and stock must be non-negative.');
        $short=(string)($input['short']??$input['short_description']??''); $description=(string)($input['description']??'');
        $imagePath=(string)($input['image_path']??$old['image_path']); $imageAlt=(string)($input['image_alt']??$old['image_alt']);
        executeSql('UPDATE products SET sku=?,name=?,image_path=?,image_alt=?,category=?,product_type=?,price=?,stock=?,short_description=?,description=?,status=?,updated_at=NOW() WHERE id=?','ssssssdisssi',[$sku,$name,$imagePath,$imageAlt,$category,$type,$price,$stock,$short,$description,$status,$productId]);
        executeSql("UPDATE product_versions SET status='historical' WHERE product_id=? AND status='live'",'i',[$productId]);
        $versions=queryRows('SELECT COALESCE(MAX(version_number),0) AS v FROM product_versions WHERE product_id=?','i',[$productId]);
        $version=(int)$versions[0]['v']+1; $p=getProduct($productId); $snap=json_encode(snapshotProduct($p),JSON_UNESCAPED_UNICODE);
        executeSql('INSERT INTO product_versions (product_id,version_number,created_by,actor_name,status,note,snapshot) VALUES (?,?,?, ?,\'live\',\'Product updated\',?)','iisss',[$productId,$version,$admin['id'],$admin['name'],$snap]);
        logActivity('Product updated','Products',(string)$productId,$name.' v'.$version);
        respond(['success'=>true,'product'=>productOutput($p),'version'=>$version]);
    }
    if ($resource === 'products' && $method === 'DELETE' && $id !== null) {
        requireAdmin(); $productId=(int)$id; $p=getProduct($productId); if(!$p) fail('Product not found.',404);
        // Archive instead of physical deletion: existing order/version foreign keys must remain valid.
        executeSql("UPDATE products SET status='archived',updated_at=NOW() WHERE id=?",'i',[$productId]);
        logActivity('Product archived','Products',(string)$productId,$p['name']);
        respond(['success'=>true,'message'=>'Product archived.']);
    }

    // -------------------------------------------------------------------------
    // CART
    // -------------------------------------------------------------------------
    if ($resource === 'cart') {
        $user=requireLogin(); $cartId=cartIdFor((int)$user['id']);
        if ($method==='GET') {
            $rows=queryRows('SELECT ci.product_id,ci.quantity,p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id=p.id) AS likes FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? ORDER BY ci.id','i',[$cartId]);
            $items=array_map(fn($r)=>['productId'=>(int)$r['product_id'],'quantity'=>(int)$r['quantity'],'product'=>productOutput($r)],$rows);
            respond(['success'=>true,'items'=>$items]);
        }
        if ($method==='POST' && $id==='items') {
            $productId=(int)($input['productId']??0); $qty=(int)($input['quantity']??1);
            if($productId<1||$qty<1||$qty>99) fail('Invalid product or quantity.');
            $p=getProduct($productId); if(!$p||$p['status']!=='published') fail('Product unavailable.',404);
            $existing=queryRows('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=?','ii',[$cartId,$productId]);
            $newQty=$qty+(int)($existing[0]['quantity']??0);
            if($p['product_type']==='physical'&&$newQty>(int)$p['stock']) fail('Not enough stock available.');
            if($existing) executeSql('UPDATE cart_items SET quantity=? WHERE cart_id=? AND product_id=?','iii',[$newQty,$cartId,$productId]);
            else executeSql('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?)','iii',[$cartId,$productId,$qty]);
            respond(['success'=>true,'message'=>'Cart updated.']);
        }
        if ($method==='PATCH' && $id==='items' && isset($action)) {
            $productId=(int)$action; $qty=(int)($input['quantity']??0);
            if($qty<=0){executeSql('DELETE FROM cart_items WHERE cart_id=? AND product_id=?','ii',[$cartId,$productId]);}
            else { $p=getProduct($productId); if(!$p) fail('Product not found.',404); if($p['product_type']==='physical'&&$qty>(int)$p['stock']) fail('Not enough stock available.'); executeSql('UPDATE cart_items SET quantity=? WHERE cart_id=? AND product_id=?','iii',[$qty,$cartId,$productId]); }
            respond(['success'=>true,'message'=>'Cart updated.']);
        }
        if ($method==='DELETE' && $id==='items' && isset($action)) {
            executeSql('DELETE FROM cart_items WHERE cart_id=? AND product_id=?','ii',[$cartId,(int)$action]);
            respond(['success'=>true,'message'=>'Cart item removed.']);
        }
        if ($method==='DELETE' && $id==='items') {
            executeSql('DELETE FROM cart_items WHERE cart_id=?','i',[$cartId]);
            respond(['success'=>true,'message'=>'Cart cleared.']);
        }
    }

    // -------------------------------------------------------------------------
    // WISHLIST / PRODUCT LIKES
    // -------------------------------------------------------------------------
    if ($resource==='wishlist') {
        $user=requireLogin();
        if($method==='GET'){
            $rows=queryRows('SELECT product_id FROM wishlist_items WHERE user_id=? ORDER BY created_at DESC','i',[$user['id']]);
            respond(['success'=>true,'productIds'=>array_map(fn($r)=>(int)$r['product_id'],$rows)]);
        }
        if($method==='POST'&&$id!==null){
            $productId=(int)$id; $p=getProduct($productId); if(!$p) fail('Product not found.',404);
            $found=queryRows('SELECT product_id FROM wishlist_items WHERE user_id=? AND product_id=?','ii',[$user['id'],$productId]);
            if($found){executeSql('DELETE FROM wishlist_items WHERE user_id=? AND product_id=?','ii',[$user['id'],$productId]);$liked=false;}
            else{executeSql('INSERT INTO wishlist_items (user_id,product_id) VALUES (?,?)','ii',[$user['id'],$productId]);$liked=true;}
            respond(['success'=>true,'liked'=>$liked]);
        }
    }

    // -------------------------------------------------------------------------
    // CHECKOUT: simulated payment only; no real payment provider
    // -------------------------------------------------------------------------
    if($resource==='checkout'&&$method==='POST'){
        $user=requireLogin(); $cartId=cartIdFor((int)$user['id']);
        $conn->begin_transaction();
        $rows=queryRows('SELECT ci.product_id,ci.quantity,p.* FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? FOR UPDATE','i',[$cartId]);
        if(!$rows) fail('Your cart is empty.');
        $email=trim((string)($input['email']??$user['email'])); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
        $subtotal=0.0; $hasPhysical=false;
        foreach($rows as $r){ if($r['status']!=='published') fail('Cart contains an unavailable product.'); if($r['product_type']==='physical'){ $hasPhysical=true; if((int)$r['quantity']>(int)$r['stock']) fail('Not enough stock for '.$r['name'].'.'); } $subtotal+=(float)$r['price']*(int)$r['quantity']; }
        $shipping=$hasPhysical?8.0:0.0; $total=$subtotal+$shipping;
        $address=trim((string)($input['address']??'')); $city=trim((string)($input['city']??'')); $postcode=trim((string)($input['postcode']??''));
        if($hasPhysical&&($address===''||$city===''||$postcode==='')) fail('Shipping address, city and postcode are required for physical products.');
        try{
            $number='SN-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $fulfillment=$hasPhysical?'processing':'digital delivered';
            $stmt=$conn->prepare("INSERT INTO orders (order_number,user_id,customer_name,customer_email,shipping_address,city,postcode,subtotal,shipping_fee,total,payment_status,fulfillment_status,is_demo_payment) VALUES (?,?,?,?,?,?,?,?,?,?,'paid',?,1)");
            $uid=(int)$user['id']; $customer=(string)$user['name'];
            $stmt->bind_param('sisssssddds',$number,$uid,$customer,$email,$address,$city,$postcode,$subtotal,$shipping,$total,$fulfillment);
            if(!$stmt->execute()) throw new RuntimeException($stmt->error);
            $orderDbId=(int)$conn->insert_id; $stmt->close();
            foreach($rows as $r){
                executeSql('INSERT INTO order_items (order_id,product_id,product_name,product_type,unit_price,quantity) VALUES (?,?,?,?,?,?)','iissdi',[$orderDbId,$r['product_id'],$r['name'],$r['product_type'],$r['price'],$r['quantity']]);
                if($r['product_type']==='physical') executeSql('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?','iii',[(int)$r['quantity'],(int)$r['product_id'],(int)$r['quantity']]);
            }
            executeSql('DELETE FROM cart_items WHERE cart_id=?','i',[$cartId]);
            $conn->commit();
            logActivity('Demo order created','Orders',$number,'Demo payment; no real funds processed.');
            $o=findOrderByNumber($number);
            respond(['success'=>true,'order'=>orderOutput($o,orderItems($orderDbId))],201);
        }catch(Throwable $e){$conn->rollback();error_log($e->getMessage());fail('Checkout could not be completed. Please try again.',500);}
    }

    // -------------------------------------------------------------------------
    // CUSTOMER ORDERS
    // -------------------------------------------------------------------------
    if($resource==='orders'){
        $user=requireLogin();
        if($method==='GET'&&$id===null){
            if($user['role']==='admin') $rows=queryRows('SELECT * FROM orders ORDER BY created_at DESC');
            else $rows=queryRows('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC','i',[$user['id']]);
            $out=[]; foreach($rows as $o)$out[]=orderOutput($o,orderItems((int)$o['id']));
            respond(['success'=>true,'orders'=>$out]);
        }
        if($method==='GET'&&$id!==null){
            $o=findOrderByNumber((string)$id); if(!$o)fail('Order not found.',404);
            if($user['role']!=='admin'&&(int)$o['user_id']!==(int)$user['id'])fail('Order not found.',404);
            respond(['success'=>true,'order'=>orderOutput($o,orderItems((int)$o['id']))]);
        }
        if($method==='POST'&&$id!==null&&$action==='cancellation'){
            $o=findOrderByNumber((string)$id); if(!$o||(int)$o['user_id']!==(int)$user['id'])fail('Order not found.',404);
            if(!in_array($o['fulfillment_status'],['processing','digital delivered'],true))fail('This order can no longer be cancelled online.');
            if($o['cancellation_status']==='pending')fail('A cancellation request is already pending.');
            executeSql("UPDATE orders SET cancellation_status='pending',updated_at=NOW() WHERE id=?",'i',[$o['id']]);
            logActivity('Cancellation requested','Orders',(string)$id,'Customer requested cancellation.');
            respond(['success'=>true,'message'=>'Cancellation request submitted.']);
        }
    }

    // -------------------------------------------------------------------------
    // ADMIN ROUTES
    // -------------------------------------------------------------------------
    if($resource==='admin'){
        $admin=requireAdmin();
        if($id==='overview'&&$method==='GET'){
            $counts=queryRows("SELECT COUNT(*) AS product_count, SUM(status='published') AS published_count FROM products");
            $sales=queryRows("SELECT COALESCE(SUM(total),0) AS sales, COUNT(*) AS order_count FROM orders WHERE payment_status='paid'");
            $pending=queryRows("SELECT COUNT(*) AS pending FROM orders WHERE cancellation_status='pending'");
            respond(['success'=>true,'overview'=>['products'=>(int)$counts[0]['product_count'],'published'=>(int)$counts[0]['published_count'],'sales'=>(float)$sales[0]['sales'],'orders'=>(int)$sales[0]['order_count'],'pendingCancellations'=>(int)$pending[0]['pending']]]);
        }
        if($id==='products'&&$method==='GET'){
            $rows=queryRows('SELECT p.*, (SELECT COUNT(*) FROM product_likes pl WHERE pl.product_id=p.id) AS likes FROM products p ORDER BY p.id DESC');
            respond(['success'=>true,'products'=>array_map('productOutput',$rows)]);
        }
        if($id==='orders'&&$method==='GET'){
            $rows=queryRows('SELECT * FROM orders ORDER BY created_at DESC'); $out=[];
            foreach($rows as $o)$out[]=orderOutput($o,orderItems((int)$o['id']));
            respond(['success'=>true,'orders'=>$out]);
        }
        if($id==='orders'&&$action!==null&&$method==='GET'){
            $o=findOrderByNumber($action); if(!$o)fail('Order not found.',404);
            respond(['success'=>true,'order'=>orderOutput($o,orderItems((int)$o['id']))]);
        }
        if($id==='orders'&&$action!==null&&$method==='PATCH'){
            $o=findOrderByNumber($action); if(!$o)fail('Order not found.',404);
            $status=(string)($input['fulfillmentStatus']??$input['status']??'');
            $allowed=['processing','digital delivered','shipped','delivered','cancelled'];
            if(!in_array($status,$allowed,true))fail('Invalid fulfillment status.');
            executeSql('UPDATE orders SET fulfillment_status=?,updated_at=NOW() WHERE id=?','si',[$status,$o['id']]);
            logActivity('Order status updated','Orders',$action,$status);
            respond(['success'=>true,'message'=>'Order status updated.']);
        }
        if($id==='cancellations'&&$action!==null&&$method==='POST'){
            $o=findOrderByNumber($action); if(!$o)fail('Order not found.',404);
            if($o['cancellation_status']!=='pending')fail('No pending cancellation request.');
            $approve=(bool)($input['approve']??false);
            $conn->begin_transaction();
            try{
                if($approve){
                    executeSql("UPDATE orders SET cancellation_status='approved',fulfillment_status='cancelled',payment_status='refunded',updated_at=NOW() WHERE id=?",'i',[$o['id']]);
                    $items=queryRows('SELECT product_id,product_type,quantity FROM order_items WHERE order_id=?','i',[$o['id']]);
                    foreach($items as $item)if($item['product_type']==='physical'&&$item['product_id']!==null)executeSql('UPDATE products SET stock=stock+? WHERE id=?','ii',[(int)$item['quantity'],(int)$item['product_id']]);
                }else executeSql("UPDATE orders SET cancellation_status='rejected',updated_at=NOW() WHERE id=?",'i',[$o['id']]);
                $conn->commit();
            }catch(Throwable $e){$conn->rollback();throw $e;}
            logActivity($approve?'Demo cancellation approved':'Cancellation declined','Orders',$action,'Demo status only; no payment provider refund.');
            respond(['success'=>true,'message'=>$approve?'Cancellation approved (demo).':'Cancellation rejected.']);
        }
        if($id==='activity'&&$method==='GET'){
            $rows=queryRows('SELECT id,actor_name AS actor,action,resource_type AS resource,resource_id,detail,created_at AS time FROM activity_logs ORDER BY created_at DESC LIMIT 300');
            respond(['success'=>true,'logs'=>$rows]);
        }
        if($id==='products'&&$action!==null&&$method==='GET'){
            $pid=(int)$action; $p=getProduct($pid); if(!$p)fail('Product not found.',404);
            $versions=queryRows('SELECT version_number AS version,created_by,actor_name AS actor,status,note,snapshot,created_at AS createdAt FROM product_versions WHERE product_id=? ORDER BY version_number DESC','i',[$pid]);
            foreach($versions as &$v){$v['version']=(int)$v['version'];$v['snapshot']=json_decode($v['snapshot'],true)?:[];} unset($v);
            respond(['success'=>true,'versions'=>$versions]);
        }
        if($id==='products'&&$action!==null&&isset($segments[3])&&$segments[3]==='restore'&&$method==='POST'){
            $pid=(int)$action; $version=(int)($segments[4]??0); $found=queryRows('SELECT snapshot FROM product_versions WHERE product_id=? AND version_number=?','ii',[$pid,$version]);
            if(!$found)fail('Product version not found.',404);
            $snap=json_decode($found[0]['snapshot'],true); if(!$snap)fail('Invalid product snapshot.',500);
            $p=getProduct($pid); if(!$p)fail('Product not found.',404);
            $versions=queryRows('SELECT COALESCE(MAX(version_number),0) AS v FROM product_versions WHERE product_id=?','i',[$pid]); $next=(int)$versions[0]['v']+1;
            $snap['status']='draft'; $json=json_encode($snap,JSON_UNESCAPED_UNICODE);
            executeSql("INSERT INTO product_versions (product_id,version_number,created_by,actor_name,status,note,snapshot) VALUES (?,?,?,?,'draft',?,?)",'iiisss',[$pid,$next,$admin['id'],$admin['name'],'Restored draft from v'.$version,$json]);
            logActivity('Product version restored to draft','Products',(string)$pid,'v'.$version.' -> v'.$next);
            respond(['success'=>true,'draftVersion'=>$next]);
        }
    }

    fail('API endpoint not found.',404);
} catch (Throwable $e) {
    error_log('ShopNest API exception: ' . $e->getMessage());
    fail('An unexpected server error occurred.',500);
}

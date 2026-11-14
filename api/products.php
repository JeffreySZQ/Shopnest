
<?php

header("Content-Type: application/json; charset=utf-8");

$allowedOrigins = array(
    "http://localhost:5173",
    "http://127.0.0.1:5173"
);

if (isset($_SERVER["HTTP_ORIGIN"]) &&
    in_array($_SERVER["HTTP_ORIGIN"], $allowedOrigins)) {

    header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed."
    ));

    exit;
}

require_once "db.php";

$sql = "
    SELECT
        p.product_id AS id,
        p.sku,
        p.name,
        c.category_name AS category,
        p.product_type AS type,
        p.price,
        p.stock,
        p.likes_count AS likes,
        p.status,
        p.short_description AS short,
        p.description,
        p.emoji,
        p.tone,
        p.featured

    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    WHERE p.status = 'published'

    ORDER BY p.product_id ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    error_log("Product query error: " . mysqli_error($conn));

    http_response_code(500);

    echo json_encode(array(
        "success" => false,
        "message" => "Unable to retrieve products."
    ));

    mysqli_close($conn);
    exit;
}

$products = array();

while ($row = mysqli_fetch_assoc($result)) {

    $products[] = array(
        "id" => (int)$row["id"],
        "sku" => $row["sku"],
        "name" => $row["name"],
        "category" => $row["category"],
        "type" => $row["type"],
        "price" => (float)$row["price"],
        "stock" => (int)$row["stock"],
        "likes" => (int)$row["likes"],
        "status" => $row["status"],
        "short" => $row["short"],
        "description" => $row["description"],
        "emoji" => $row["emoji"],
        "tone" => $row["tone"],
        "featured" => ((int)$row["featured"] === 1)
    );
}

mysqli_free_result($result);
mysqli_close($conn);

echo json_encode(array(
    "success" => true,
    "total" => count($products),
    "products" => $products
));

?>

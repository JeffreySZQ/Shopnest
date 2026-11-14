
<?php

$host = "feenix-mariadb.swin.edu.au";
$username = "s105534669";
$password = "230706";
$database = "s105534669_db";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database,
    3306
);

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());

    http_response_code(500);
    header("Content-Type: application/json");

    echo json_encode(array(
        "success" => false,
        "message" => "Database connection failed."
    ));

    exit;
}

mysqli_set_charset($conn, "utf8mb4");

?>

<?php
/* Compatibility entry point for the existing products.php URL. */
if (!isset($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] === '') {
    $_SERVER['PATH_INFO'] = '/products';
}
require __DIR__ . '/shopnest-api.php';
?>

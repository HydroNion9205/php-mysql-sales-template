<?php
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products/');
    exit;
}

$productID = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($productID <= 0) {
    header('Location: /admin/products/');
    exit;
}

$sql = "
    DELETE FROM products
    WHERE ProductID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $productID);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: /admin/products/');
    exit;
}

$stmt->close();
$conn->close();
die('Không thể xóa sản phẩm.');

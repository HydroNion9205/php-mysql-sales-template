<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng';
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: /cart.php');
    exit;
}

function getCartItems($conn, $cart)
{
    $items = [];
    $total = 0;
    $sql = "
        SELECT
            p.ProductID,
            p.ProductCode,
            p.ProductName,
            p.Price,
            p.StockQuantity,
            (
                SELECT pi.ImageFile
                FROM product_images pi
                WHERE pi.ProductID = p.ProductID
                  AND pi.IsPrimary = 1
                LIMIT 1
            ) AS ImageFile
        FROM products p
        WHERE p.ProductID = ?
          AND p.IsActive = 1
    ";
    $stmt = $conn->prepare($sql);

    foreach ($cart as $productID => $quantity) {
        $productID = (int) $productID;
        $quantity = (int) $quantity;
        if ($productID <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $result->free();

        if (!$product) {
            continue;
        }

        $product['Quantity'] = $quantity;
        $product['Subtotal'] = (float) $product['Price'] * $quantity;
        $total += $product['Subtotal'];
        $items[] = $product;
    }
    $stmt->close();

    return [
        'items' => $items,
        'total' => $total
    ];
}

$cartData = getCartItems($conn, $cart);
$cartItems = $cartData['items'];
$total = $cartData['total'];

if (empty($cartItems)) {
    header('Location: /cart.php');
    exit;
}

$errorMessage = '';
$customerName = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customerName === '' || $phone === '' || $address === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ thông tin khách hàng.';
    } else {
        try {
            $conn->begin_transaction();

            $orderItems = [];
            $orderTotal = 0;

            $sqlProduct = "
                SELECT
                    ProductID,
                    ProductName,
                    Price,
                    StockQuantity
                FROM products
                WHERE ProductID = ?
                  AND IsActive = 1
                FOR UPDATE
            ";
            $stmtProduct = $conn->prepare($sqlProduct);

            foreach ($cart as $productID => $quantity) {
                $productID = (int) $productID;
                $quantity = (int) $quantity;

                if ($productID <= 0 || $quantity <= 0) {
                    throw new Exception('Dữ liệu giỏ hàng không hợp lệ.');
                }

                $stmtProduct->bind_param('i', $productID);
                $stmtProduct->execute();
                $productResult = $stmtProduct->get_result();
                $product = $productResult->fetch_assoc();
                $productResult->free();

                if (!$product) {
                    throw new Exception('Có sản phẩm không còn khả dụng.');
                }

                if ($quantity > (int) $product['StockQuantity']) {
                    throw new Exception(
                        'Sản phẩm "' . $product['ProductName'] . '" không đủ số lượng tồn kho.'
                    );
                }

                $unitPrice = (float) $product['Price'];
                $subtotal = $unitPrice * $quantity;
                $orderTotal += $subtotal;

                $orderItems[] = [
                    'ProductID' => $productID,
                    'Quantity' => $quantity,
                    'UnitPrice' => $unitPrice
                ];
            }
            $stmtProduct->close();

            $sqlCustomer = "
                INSERT INTO customers
                    (CustomerName, Address, Phone)
                VALUES (?, ?, ?)
            ";
            $stmtCustomer = $conn->prepare($sqlCustomer);
            $stmtCustomer->bind_param(
                'sss',
                $customerName,
                $address,
                $phone
            );
            $stmtCustomer->execute();
            $customerID = $conn->insert_id;
            $stmtCustomer->close();

            $status = 'Pending';
            $sqlOrder = "
                INSERT INTO orders
                    (TotalAmount, Status, CustomerID)
                VALUES (?, ?, ?)
            ";
            $stmtOrder = $conn->prepare($sqlOrder);
            $stmtOrder->bind_param(
                'dsi',
                $orderTotal,
                $status,
                $customerID
            );
            $stmtOrder->execute();
            $orderID = $conn->insert_id;
            $stmtOrder->close();

            $sqlDetail = "
                INSERT INTO orderdetail
                    (Quantity, UnitPrice, OrderID, ProductID)
                VALUES (?, ?, ?, ?)
            ";
            $stmtDetail = $conn->prepare($sqlDetail);

            $sqlStock = "
                UPDATE products
                SET StockQuantity = StockQuantity - ?
                WHERE ProductID = ?
            ";
            $stmtStock = $conn->prepare($sqlStock);

            foreach ($orderItems as $item) {
                $quantity = $item['Quantity'];
                $unitPrice = $item['UnitPrice'];
                $productID = $item['ProductID'];

                $stmtDetail->bind_param(
                    'idii',
                    $quantity,
                    $unitPrice,
                    $orderID,
                    $productID
                );
                $stmtDetail->execute();

                $stmtStock->bind_param(
                    'ii',
                    $quantity,
                    $productID
                );
                $stmtStock->execute();
            }
            $stmtDetail->close();
            $stmtStock->close();

            $conn->commit();

            $_SESSION['cart'] = [];
            header('Location: /order-success.php?id=' . $orderID);
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">
    <h1 class="h3 mb-4">Đặt hàng</h1>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">
                        Thông tin khách hàng
                    </h2>
                    <form method="post">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">
                                Họ tên
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="customer_name"
                                name="customer_name"
                                value="<?= htmlspecialchars($customerName) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                Số điện thoại
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($phone) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">
                                Địa chỉ
                            </label>
                            <textarea
                                class="form-control"
                                id="address"
                                name="address"
                                rows="3"
                                required
                            ><?= htmlspecialchars($address) ?></textarea>
                        </div>

                        <button
                            type="submit"
                            name="place_order"
                            class="btn btn-success"
                        >
                            Xác nhận đặt hàng
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">
                        Đơn hàng của bạn
                    </h2>

                    <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <strong>
                                    <?= htmlspecialchars($item['ProductName']) ?>
                                </strong>
                                <div class="small text-muted">
                                    <?= (int) $item['Quantity'] ?>
                                    ×
                                    <?= number_format((float) $item['Price'], 0, ',', '.') ?> đ
                                </div>
                            </div>
                            <div>
                                <?= number_format((float) $item['Subtotal'], 0, ',', '.') ?> đ
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-between fw-bold fs-5 pt-3">
                        <span>Tổng cộng</span>
                        <span>
                            <?= number_format((float) $total, 0, ',', '.') ?> đ
                        </span>
                    </div>

                    <a href="/cart.php" class="btn btn-outline-secondary mt-3">
                        Quay lại giỏ hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();

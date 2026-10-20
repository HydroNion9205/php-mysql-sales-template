<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng thành công';

$orderID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderID <= 0) {
    header('Location: /');
    exit;
}

$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone,
        c.Address
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
      AND o.OrderID = ?
";

$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();
$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();
$orderResult->free();
$stmtOrder->close();

if (!$order) {
    header('Location: /');
    exit;
}

$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        p.ProductCode,
        p.ProductName
    FROM orderdetail od, products p
    WHERE od.ProductID = p.ProductID
      AND od.OrderID = ?
    ORDER BY od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param('i', $orderID);
$stmtDetail->execute();
$detailResult = $stmtDetail->get_result();

$orderItems = [];
while ($row = $detailResult->fetch_assoc()) {
    $row['Subtotal'] = (float) $row['UnitPrice'] * (int) $row['Quantity'];
    $orderItems[] = $row;
}

$detailResult->free();
$stmtDetail->close();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">
    <div class="alert alert-success">
        <h1 class="h4 mb-2">
            Đặt hàng thành công
        </h1>
        <p class="mb-0">
            Mã đơn hàng của bạn là
            <strong>#<?= (int) $order['OrderID'] ?></strong>.
        </p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Thông tin đơn hàng</h2>
            <p>
                <strong>Khách hàng:</strong>
                <?= htmlspecialchars($order['CustomerName']) ?>
            </p>
            <p>
                <strong>Số điện thoại:</strong>
                <?= htmlspecialchars($order['Phone']) ?>
            </p>
            <p>
                <strong>Địa chỉ:</strong>
                <?= htmlspecialchars($order['Address']) ?>
            </p>
            <p>
                <strong>Ngày đặt:</strong>
                <?= htmlspecialchars($order['OrderDate']) ?>
            </p>
            <p class="mb-0">
                <strong>Trạng thái:</strong>
                <?= htmlspecialchars($order['Status']) ?>
            </p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Chi tiết sản phẩm</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Mã SP</th>
                            <th>Sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-center">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item['ProductCode']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($item['ProductName']) ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format((float) $item['UnitPrice'], 0, ',', '.') ?> đ
                                </td>
                                <td class="text-center">
                                    <?= (int) $item['Quantity'] ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format((float) $item['Subtotal'], 0, ',', '.') ?> đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">
                                Tổng cộng
                            </th>
                            <th class="text-end">
                                <?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <a href="/products.php" class="btn btn-primary">
                Tiếp tục mua hàng
            </a>
        </div>
    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();

<?php
$pageTitle = 'Chi tiết đơn hàng';
require_once '/var/www/src/config/database.php';

$orderID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderID <= 0) {
    header('Location: /admin/orders/');
    exit;
}

$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.ContactName,
        c.Phone,
        c.Address,
        c.City,
        c.PostalCode,
        c.Country
    FROM
        orders AS o,
        customers AS c
    WHERE
        o.CustomerID = c.CustomerID
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
    header('Location: /admin/orders/');
    exit;
}

/*
 * Các trạng thái được phép chuyển tiếp.
 */
$allowedTransitions = [
    'Pending' => [
        'Confirmed',
        'Cancelled'
    ],
    'Confirmed' => [
        'Shipping',
        'Cancelled'
    ],
    'Shipping' => [
        'Completed',
        'Cancelled'
    ],
    'Completed' => [],
    'Cancelled' => []
];

/*
 * Xử lý cập nhật trạng thái.
 */
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';

    try {
        $conn->begin_transaction();

        /*
         * Đọc và khóa đơn hàng.
         * Trạng thái phải được kiểm tra lại trong transaction.
         */
        $sqlLockOrder = "
            SELECT Status
            FROM orders
            WHERE OrderID = ?
            FOR UPDATE
        ";
        $stmtLockOrder = $conn->prepare($sqlLockOrder);
        $stmtLockOrder->bind_param('i', $orderID);
        $stmtLockOrder->execute();
        $lockResult = $stmtLockOrder->get_result();
        $lockedOrder = $lockResult->fetch_assoc();
        $lockResult->free();
        $stmtLockOrder->close();

        if (!$lockedOrder) {
            throw new Exception('Không tìm thấy đơn hàng.');
        }

        $currentStatus = $lockedOrder['Status'];
        $allowedStatuses = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new Exception('Không thể chuyển sang trạng thái đã chọn.');
        }

        /*
         * Nếu hủy đơn hàng, hoàn lại tồn kho
         * theo số lượng đã lưu trong orderdetail.
         */
        if ($newStatus === 'Cancelled') {
            $sqlItems = "
                SELECT
                    ProductID,
                    Quantity
                FROM orderdetail
                WHERE OrderID = ?
            ";
            $stmtItems = $conn->prepare($sqlItems);
            $stmtItems->bind_param('i', $orderID);
            $stmtItems->execute();
            $itemsResult = $stmtItems->get_result();
            $orderItems = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $orderItems[] = $item;
            }
            $itemsResult->free();
            $stmtItems->close();

            $sqlRestoreStock = "
                UPDATE products
                SET StockQuantity = StockQuantity + ?
                WHERE ProductID = ?
            ";
            $stmtRestoreStock = $conn->prepare($sqlRestoreStock);

            foreach ($orderItems as $item) {
                $quantity = (int) $item['Quantity'];
                $productID = (int) $item['ProductID'];

                $stmtRestoreStock->bind_param(
                    'ii',
                    $quantity,
                    $productID
                );
                $stmtRestoreStock->execute();

                if ($stmtRestoreStock->affected_rows !== 1) {
                    throw new Exception('Không thể hoàn lại tồn kho.');
                }
            }
            $stmtRestoreStock->close();
        }

        /*
         * Cập nhật trạng thái đơn hàng.
         */
        $sqlUpdate = "
            UPDATE orders
            SET Status = ?
            WHERE OrderID = ?
        ";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param(
            'si',
            $newStatus,
            $orderID
        );
        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows !== 1) {
            throw new Exception('Không thể cập nhật trạng thái đơn hàng.');
        }
        $stmtUpdate->close();

        $conn->commit();

        header('Location: /admin/orders/detail.php?id=' . $orderID);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $errorMessage = $e->getMessage();
    }
}

/*
 * Sau POST, đọc lại thông tin đơn hàng
 * để giao diện luôn phản ánh dữ liệu hiện tại.
 */
$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();
$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();
$orderResult->free();
$stmtOrder->close();

/*
 * Đọc chi tiết sản phẩm trong đơn hàng.
 */
$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        p.ProductCode,
        p.ProductName
    FROM
        orderdetail AS od,
        products AS p
    WHERE
        od.ProductID = p.ProductID
        AND od.OrderID = ?
    ORDER BY
        od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param('i', $orderID);
$stmtDetail->execute();
$detailResult = $stmtDetail->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            Chi tiết đơn hàng #<?= (int) $order['OrderID'] ?>
        </h2>
        <a href="/admin/orders/" class="btn btn-outline-secondary">
            Quay lại
        </a>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Thông tin đơn hàng</strong>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Mã đơn:</strong>
                        #<?= (int) $order['OrderID'] ?>
                    </p>
                    <p>
                        <strong>Ngày đặt:</strong>
                        <?= htmlspecialchars($order['OrderDate']) ?>
                    </p>
                    <p>
                        <strong>Trạng thái:</strong>
                        <?= htmlspecialchars($order['Status']) ?>
                    </p>
                    <p>
                        <strong>Tổng tiền:</strong>
                        <?= number_format(
                            (float) $order['TotalAmount'],
                            0,
                            ',',
                            '.'
                        ) ?> đ
                    </p>

                    <?php
                    $nextStatuses = $allowedTransitions[$order['Status']] ?? [];
                    ?>
                    <?php if (!empty($nextStatuses)): ?>
                        <hr>
                        <form method="post">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Cập nhật trạng thái
                                </label>
                                <select
                                    name="status"
                                    id="status"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        -- Chọn trạng thái --
                                    </option>
                                    <?php foreach ($nextStatuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button
                                type="submit"
                                name="update_status"
                                class="btn btn-primary"
                            >
                                Cập nhật
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0">
                            Đơn hàng đã ở trạng thái kết thúc.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Thông tin khách hàng</strong>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Khách hàng:</strong>
                        <?= htmlspecialchars($order['CustomerName']) ?>
                    </p>
                    <p>
                        <strong>Người liên hệ:</strong>
                        <?= htmlspecialchars($order['ContactName'] ?? '') ?>
                    </p>
                    <p>
                        <strong>Điện thoại:</strong>
                        <?= htmlspecialchars($order['Phone'] ?? '') ?>
                    </p>
                    <p>
                        <strong>Địa chỉ:</strong>
                        <?= htmlspecialchars($order['Address'] ?? '') ?>
                    </p>
                    <p>
                        <strong>Thành phố:</strong>
                        <?= htmlspecialchars($order['City'] ?? '') ?>
                    </p>
                    <p>
                        <strong>Mã bưu chính:</strong>
                        <?= htmlspecialchars($order['PostalCode'] ?? '') ?>
                    </p>
                    <p class="mb-0">
                        <strong>Quốc gia:</strong>
                        <?= htmlspecialchars($order['Country'] ?? '') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Sản phẩm trong đơn hàng</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($item = $detailResult->fetch_assoc()): ?>
                        <?php
                        $subtotal = (float) $item['UnitPrice'] * (int) $item['Quantity'];
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['ProductCode']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['ProductName']) ?>
                            </td>
                            <td class="text-end">
                                <?= number_format(
                                    (float) $item['UnitPrice'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </td>
                            <td class="text-end">
                                <?= (int) $item['Quantity'] ?>
                            </td>
                            <td class="text-end">
                                <?= number_format(
                                    $subtotal,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">
                                Tổng cộng
                            </th>
                            <th class="text-end">
                                <?= number_format(
                                    (float) $order['TotalAmount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$detailResult->free();
$stmtDetail->close();
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();

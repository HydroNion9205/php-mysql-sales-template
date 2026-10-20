<?php
$pageTitle = 'Quản lý người giao hàng';
require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone
    FROM shippers
    ORDER BY ShipperID
";
$result = $conn->query($sql);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý người giao hàng</h2>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Tên đơn vị giao hàng</th>
                    <th>Số điện thoại</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($shipper = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $shipper['ShipperID'] ?></td>
                    <td><?= htmlspecialchars($shipper['ShipperName']) ?></td>
                    <td><?= htmlspecialchars($shipper['Phone'] ?? '') ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();

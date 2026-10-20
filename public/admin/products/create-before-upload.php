<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';

    } elseif ($categoryID <= 0) {
        $error = 'Vui lòng chọn danh mục.';

    } elseif ($supplierID <= 0) {
        $error = 'Vui lòng chọn nhà cung cấp.';

    } else {

        $sql = "
            INSERT INTO products
            (
                ProductCode,
                ProductName,
                Description,
                Unit,
                Price,
                StockQuantity,
                IsActive,
                SupplierID,
                CategoryID
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ssssdiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID
        );

        if ($stmt->execute()) {
            header('Location: /admin/products/');
            exit;
        }

        $error = 'Không thể thêm sản phẩm.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Thêm sản phẩm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="productCode" class="form-label">Mã sản phẩm</label>
            <input
                type="text"
                class="form-control"
                id="productCode"
                name="product_code"
                value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="productName" class="form-label">Tên sản phẩm</label>
            <input
                type="text"
                class="form-control"
                id="productName"
                name="product_name"
                value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label for="unit" class="form-label">Đơn vị</label>
            <input
                type="text"
                class="form-control"
                id="unit"
                name="unit"
                value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"
            >
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Giá</label>
            <input
                type="number"
                step="0.01"
                min="0"
                class="form-control"
                id="price"
                name="price"
                value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="stockQuantity" class="form-label">Tồn kho</label>
            <input
                type="number"
                min="0"
                class="form-control"
                id="stockQuantity"
                name="stock_quantity"
                value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="categoryId" class="form-label">Danh mục</label>
            <select id="categoryId" name="category_id" class="form-select" required>
                <option value="">-- Chọn danh mục --</option>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <option
                        value="<?= $category['CategoryID'] ?>"
                        <?= ((int)($_POST['category_id'] ?? 0) === (int)$category['CategoryID']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($category['CategoryName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="supplierId" class="form-label">Nhà cung cấp</label>
            <select id="supplierId" name="supplier_id" class="form-select" required>
                <option value="">-- Chọn nhà cung cấp --</option>
                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                    <option
                        value="<?= $supplier['SupplierID'] ?>"
                        <?= ((int)($_POST['supplier_id'] ?? 0) === (int)$supplier['SupplierID']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($supplier['SupplierName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-check mb-3">
            <input
                class="form-check-input"
                type="checkbox"
                id="isActive"
                name="is_active"
                value="1"
                <?= (!isset($_POST['product_code']) || isset($_POST['is_active'])) ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="isActive">
                Đang kinh doanh
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Lưu</button>
        <a href="/admin/products/" class="btn btn-secondary">Hủy</a>
    </form>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
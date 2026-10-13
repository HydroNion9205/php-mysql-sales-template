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

    $productCode   = trim($_POST['product_code']   ?? '');
    $productName   = trim($_POST['product_name']   ?? '');
    $description   = trim($_POST['description']    ?? '');
    $unit          = trim($_POST['unit']           ?? '');

    $price         = (float) ($_POST['price']          ?? 0);
    $stockQuantity = (int)   ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $files = $_FILES['product_images'] ?? null;

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

    } elseif (
        !$files
        || !isset($files['name'])
        || !is_array($files['name'])
    ) {
        $error = 'Vui lòng chọn ảnh sản phẩm.';

    } else {

        $fileCount = count($files['name']);

        if ($fileCount < 1 || $fileCount > 4) {
            $error = 'Chỉ được chọn từ 1 đến 4 ảnh.';

        } else {

            $maxSize = 2 * 1024 * 1024; // 2 MB

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $preparedImages = [];

            // Kiểm tra TẤT CẢ ảnh trước khi bắt đầu transaction
            for ($i = 0; $i < $fileCount; $i++) {

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = 'Có file ảnh upload không thành công.';
                    break;
                }

                if ($files['size'][$i] > $maxSize) {
                    $error = 'Mỗi file ảnh không được vượt quá 2 MB.';
                    break;
                }

                $mimeType = $finfo->file($files['tmp_name'][$i]);

                if (!isset($extensionMap[$mimeType])) {
                    $error = 'Chỉ cho phép file JPG, PNG hoặc WebP.';
                    break;
                }

                $extension = $extensionMap[$mimeType];

                $newFileName =
                    'product-'
                    . bin2hex(random_bytes(8))
                    . '.'
                    . $extension;

                $preparedImages[] = [
                    'tmp_name'   => $files['tmp_name'][$i],
                    'file_name'  => $newFileName,
                    'is_primary' => ($i === 0) ? 1 : 0,
                    'sort_order' => $i + 1,
                ];
            }

            // Chỉ thực hiện INSERT khi tất cả ảnh đều hợp lệ
            if ($error === '') {

                $movedFiles = [];

                try {
                    $conn->begin_transaction();

                    // 1. INSERT products
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

                    if (!$stmt->execute()) {
                        throw new Exception('Không thể thêm sản phẩm.');
                    }

                    // 2. Lấy ProductID vừa tạo
                    $productID = $conn->insert_id;
                    $stmt->close();

                    // 3. Chuẩn bị câu INSERT product_images
                    $sqlImage = "
                        INSERT INTO product_images
                        (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
                        VALUES
                        (?, ?, ?, ?, ?)
                    ";

                    $stmtImage = $conn->prepare($sqlImage);

                    // 4. Duyệt từng ảnh: move file → INSERT product_images
                    foreach ($preparedImages as $index => $image) {

                        $destination =
                            '/var/www/html/uploads/products/'
                            . $image['file_name'];

                        if (!move_uploaded_file(
                            $image['tmp_name'],
                            $destination
                        )) {
                            throw new Exception(
                                'Không thể lưu một trong các file ảnh.'
                            );
                        }

                        // Ghi nhận file đã move để rollback vật lý nếu cần
                        $movedFiles[] = $destination;

                        $altText = ($image['is_primary'] === 1)
                            ? $productName . ' - ảnh chính'
                            : $productName . ' - ảnh ' . ($index + 1);

                        $imageFile = $image['file_name'];
                        $isPrimary = $image['is_primary'];
                        $sortOrder = $image['sort_order'];

                        $stmtImage->bind_param(
                            'issii',
                            $productID,
                            $imageFile,
                            $altText,
                            $isPrimary,
                            $sortOrder
                        );

                        if (!$stmtImage->execute()) {
                            throw new Exception(
                                'Không thể lưu thông tin ảnh.'
                            );
                        }
                    }

                    $stmtImage->close();

                    $conn->commit();

                    header('Location: /products/');
                    exit;

                } catch (Throwable $e) {
                    $conn->rollback();

                    // Xóa các file đã lưu vật lý để đồng bộ với database
                    foreach ($movedFiles as $movedFile) {
                        if (file_exists($movedFile)) {
                            unlink($movedFile);
                        }
                    }

                    if (
                        $e instanceof mysqli_sql_exception
                        && $conn->errno === 1062
                    ) {
                        $error = "Mã sản phẩm '"
                            . htmlspecialchars($productCode)
                            . "' đã tồn tại. Vui lòng chọn mã khác.";
                    } else {
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Thêm sản phẩm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="productCode" class="form-label">
                    Mã sản phẩm
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="productCode"
                    name="product_code"
                    value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>"
                    required
                >
            </div>

            <div class="col-md-8 mb-3">
                <label for="productName" class="form-label">
                    Tên sản phẩm
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="productName"
                    name="product_name"
                    value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                    required
                >
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                Mô tả
            </label>
            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="unit" class="form-label">
                    Đơn vị tính
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="unit"
                    name="unit"
                    value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"
                >
            </div>

            <div class="col-md-4 mb-3">
                <label for="price" class="form-label">
                    Giá
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="price"
                    name="price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>"
                    required
                >
            </div>

            <div class="col-md-4 mb-3">
                <label for="stockQuantity" class="form-label">
                    Tồn kho
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="stockQuantity"
                    name="stock_quantity"
                    min="0"
                    value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>"
                    required
                >
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="categoryID" class="form-label">
                    Danh mục
                </label>
                <select
                    class="form-select"
                    id="categoryID"
                    name="category_id"
                    required
                >
                    <option value="">-- Chọn danh mục --</option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option
                            value="<?= $category['CategoryID'] ?>"
                            <?= (($_POST['category_id'] ?? '') == $category['CategoryID']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($category['CategoryName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="supplierID" class="form-label">
                    Nhà cung cấp
                </label>
                <select
                    class="form-select"
                    id="supplierID"
                    name="supplier_id"
                    required
                >
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <option
                            value="<?= $supplier['SupplierID'] ?>"
                            <?= (($_POST['supplier_id'] ?? '') == $supplier['SupplierID']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($supplier['SupplierName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="productImages" class="form-label">
                Hình ảnh sản phẩm
            </label>
            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
            >
            <div class="form-text">
                Chọn từ 1 đến 4 ảnh. Chấp nhận JPG, PNG hoặc WebP.
                Mỗi ảnh tối đa 2 MB. Ảnh đầu tiên là ảnh chính.
            </div>
        </div>

        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= (isset($_POST['is_active']) || $_SERVER['REQUEST_METHOD'] !== 'POST') ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="isActive">
                Đang kinh doanh
            </label>
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>
        <a href="/products/" class="btn btn-secondary">
            Hủy
        </a>

    </form>
</div>

<?php
require_once '/var/www/src/includes/footer.php';
$conn->close();

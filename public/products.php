<?php
require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM
        products p,
        categories c
    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
    ORDER BY
        p.ProductID DESC
";

$result = $conn->query($sql);

$pageTitle = 'Sản phẩm';
require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-5">
    <div class="mb-4">
        <h1>Sản phẩm</h1>
        <p class="text-muted">
            Khám phá các sản phẩm hiện có tại cửa hàng.
        </p>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <?php if (!empty($product['ImageFile'])): ?>
                            <div
                                class="bg-light d-flex align-items-center justify-content-center p-3"
                                style="height: 260px;"
                            >
                                <img
                                    src="/uploads/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                                    alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                    style="width: 100%; height: 100%; object-fit: contain;"
                                >
                            </div>
                        <?php else: ?>
                            <div
                                class="bg-light d-flex align-items-center justify-content-center text-muted"
                                style="height: 260px;"
                            >
                                Chưa có hình ảnh
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">
                            <p class="text-muted small mb-1">
                                <?= htmlspecialchars($product['CategoryName']) ?>
                            </p>

                            <h5 class="card-title">
                                <?= htmlspecialchars($product['ProductName']) ?>
                            </h5>

                            <p class="text-muted small">
                                Mã sản phẩm: <?= htmlspecialchars($product['ProductCode']) ?>
                            </p>

                            <p class="fw-bold fs-5 mb-3">
                                <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                            </p>

                            <a
                                href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                class="btn btn-outline-primary mt-auto"
                            >
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Hiện chưa có sản phẩm nào để hiển thị.
        </div>
    <?php endif; ?>
</main>

<?php
$result->free();
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();

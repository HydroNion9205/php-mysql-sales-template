<?php

$pageTitle = 'Thêm danh mục';

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm danh mục</h2>

    <form method="post">

        <div class="mb-3">
            <label for="categoryName" class="form-label">
                Tên danh mục
            </label>

            <input
                type="text"
                class="form-control"
                id="categoryName"
                name="category_name"
                required
            >
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
            ></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/admin/categories/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

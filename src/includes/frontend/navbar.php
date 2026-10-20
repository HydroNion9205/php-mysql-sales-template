<?php
$cartCount = array_sum(
    $_SESSION['cart'] ?? []
);
?>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Sales Management</a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#frontendNavbar"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="frontendNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/products.php">
                        Sản phẩm
                    </a>
                </li>
            </ul>

            <div class="d-flex gap-2">
                <a
                    class="btn btn-outline-light btn-sm"
                    href="/cart.php"
                >
                    Giỏ hàng (<?= (int) $cartCount ?>)
                </a>
                <a
                    class="btn btn-outline-light btn-sm"
                    href="/admin/"
                >
                    Quản trị
                </a>
            </div>
        </div>
    </div>
</nav>

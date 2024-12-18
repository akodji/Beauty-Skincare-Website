<?php
// Include database configuration
include 'config.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch current user's details
$stmt = $conn->prepare("SELECT user_id, firstname, lastname, userrole FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$userId = $userData['user_id'];
$userRole = $userData['userrole'];
$stmt->close();

// Fetch orders based on user role
if ($userRole == 1) {
    // Admin: Fetch all orders
    $query = "
        SELECT o.order_id, o.created_at AS order_date, o.total_amount, 
               GROUP_CONCAT(p.name_ SEPARATOR ', ') as products, 
               SUM(oi.quantity) as total_quantity, u.firstname, u.lastname
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN shop_products p ON oi.shop_product_id = p.shop_product_id
        JOIN users u ON o.user_id = u.user_id
        GROUP BY o.order_id, o.created_at, o.total_amount, u.firstname, u.lastname
        ORDER BY o.created_at DESC
    ";
} else {
    // Regular user: Fetch only their orders
    $query = "
        SELECT o.order_id, o.created_at AS order_date, o.total_amount, 
               GROUP_CONCAT(p.name_ SEPARATOR ', ') as products, 
               SUM(oi.quantity) as total_quantity
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN shop_products p ON oi.shop_product_id = p.shop_product_id
        WHERE o.user_id = ?
        GROUP BY o.order_id, o.created_at, o.total_amount
        ORDER BY o.created_at DESC
    ";
}

$stmt = $conn->prepare($query);
if ($userRole != 1) {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Beauty & Skincare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .order-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 20px;
            border-radius: 15px 15px 0 0;
        }
        .order-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-spa me-2"></i>Beauty & Skincare Manager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="routine.php"><i class="fas fa-calendar me-1"></i>Routines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php"><i class="fas fa-shopping-bag me-1"></i>Your Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php"><i class="fas fa-shopping-bag me-1"></i>Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php"><i class="fas fa-box me-1"></i>Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php"><i class="fas fa-info-circle me-1"></i>About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact Us</a>
                    </li>
                    <?php if (isset($_SESSION['userrole']) && $_SESSION['userrole'] === '1'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="contact_submissions.php"><i class="fas fa-comments me-1"></i>Customer Feedback</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($userData['firstname'] . ' ' . $userData['lastname']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?php echo $userRole == 1 ? 'All Orders' : 'My Orders'; ?></h2>
        </div>

        <?php if ($orders->num_rows > 0): ?>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="card order-card">
                    <div class="order-header">
                        <h5 class="mb-0">Order #<?php echo $order['order_id']; ?></h5>
                        <?php if ($userRole == 1): ?>
                            <p class="mb-0">Customer: <?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="order-body">
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                        <p><strong>Products:</strong> <?php echo htmlspecialchars($order['products']); ?></p>
                        <p><strong>Total Quantity:</strong> <?php echo $order['total_quantity']; ?></p>
                        <p><strong>Total Amount:</strong> GH₵<?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <?php echo $userRole == 1 ? 'No orders found.' : 'You have no orders yet.'; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


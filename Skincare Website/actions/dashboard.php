<?php
include 'config.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Function to get feedback count
function getFeedbackCount($conn)
{
    $result = $conn->query("SELECT COUNT(*) as count FROM contact_submissions");
    $row = $result->fetch_assoc();
    return $row['count'];
}


// Function to get user role from database
function getUserRole($conn, $username)
{
    $stmt = $conn->prepare("SELECT userrole FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['userrole'] : 2; // Default to regular user if not found
}

// Function to get user count
function getUserCount($conn)
{
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get total product count
function getTotalProductCount($conn)
{
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get user's role
$userRole = getUserRole($conn, $_SESSION['username']);

// Fetch current user's details
$stmt = $conn->prepare("SELECT user_id, firstname, lastname FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$userId = $userData['user_id'];


// Function to get total products for a specific user
function getUserProductCount($conn, $userId)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get total routines for a specific user
function getUserRoutineCount($conn, $userId)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM routines WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Add this function to the existing functions in the file
function getSuperadminOrderCount($conn, $userId)
{
    // Count orders for products added by this superadmin
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) as order_count
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN shop_products sp ON oi.shop_product_id = sp.shop_product_id
        WHERE sp.seller_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['order_count'];
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Beauty & Skincare Manager - Dashboard</title>
    <style>
        /* [Previous CSS remains the same] */
        :root {
            --primary-pink: #FFB6C1;
            --light-pink: #FFF0F5;
        }

        body {
            background-color: #f8f9fa;
        }

        .dashboard-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .routine-item {
            border-left: 3px solid var(--primary-pink);
            background-color: white;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 0 10px 10px 0;
            transition: all 0.2s;
        }

        .routine-item:hover {
            background-color: var(--light-pink);
        }

        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-pink);
        }

        .nav-pills .nav-link {
            color: #495057;
        }

        .streak-badge {
            background: linear-gradient(45deg, #FFB6C1, #FFC0CB);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .task-checkbox {
            width: 20px;
            height: 20px;
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
                        <a class="nav-link active" href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
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
                        <a class="nav-link" href="orders.php"><i class="fas fa-box me-1"></i>Orders</a>
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

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="sidebar dashboard-card">
                    <div class="text-center mb-4">
                        <h5 class="mb-1"><?php echo htmlspecialchars($userData['firstname'] . ' ' . $userData['lastname']); ?></h5>
                        <span class="badge <?php echo $userRole == 1 ? 'bg-danger' : 'bg-primary'; ?>">
                            <?php echo $userRole == 1 ? 'Super Admin' : 'Regular User'; ?>
                        </span>
                    </div>
                    <hr>
                    <div class="nav flex-column nav-pills">
                        <a class="nav-link active mb-2" href="#"><i class="fas fa-chart-line me-2"></i>Overview</a>
                        <?php if ($userRole == 1): ?>
                            <a class="nav-link mb-2" href="usermanagement.php"><i class="fas fa-users me-2"></i>User Management</a>
                        <?php endif; ?>
                        <a class="nav-link mb-2" href="routine.php"><i class="fas fa-calendar-check me-2"></i>My Routines</a>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard -->
            <div class="col-lg-9">
                <!-- Welcome Section -->
                <div class="dashboard-card card mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Hello, <?php echo htmlspecialchars($userData['firstname']); ?>!</h4>
                            <p class="text-muted mb-0">Here's your skincare overview for today</p>
                        </div>
                    </div>
                </div>

                <!-- Admin Statistics Section (Visible only to Super Admin) -->
                <div class="row">
                    <?php if ($userRole == 1): ?>
                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card admin-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getUserCount($conn); ?></h3>
                                            <p class="mb-0">Total Users</p>
                                        </div>
                                        <i class="fas fa-users ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card admin-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getTotalProductCount($conn); ?></h3>
                                            <p class="mb-0">Total Products</p>
                                        </div>
                                        <i class="fas fa-shopping-bag ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card admin-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getSuperadminOrderCount($conn, $userId); ?></h3>
                                            <p class="mb-0">My Product Orders</p>
                                        </div>
                                        <i class="fas fa-shopping-cart ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Customer Feedback Count Card -->
                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card admin-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getFeedbackCount($conn); ?></h3>
                                            <p class="mb-0">Customer Feedback</p>
                                        </div>
                                        <i class="fas fa-comment-dots ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>


                    <?php else: ?>
                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card user-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getUserProductCount($conn, $userId); ?></h3>
                                            <p class="mb-0">My Products</p>
                                        </div>
                                        <i class="fas fa-shopping-bag ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="dashboard-card card user-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h3 class="mb-1"><?php echo getUserRoutineCount($conn, $userId); ?></h3>
                                            <p class="mb-0">My Routines</p>
                                        </div>
                                        <i class="fas fa-calendar-check ms-auto fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>


                </div>



            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
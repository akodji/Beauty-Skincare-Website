<?php
include 'config.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT user_id, firstname, lastname FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$userId = $userData['user_id'];
$stmt->close();
 ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Beauty & Skincare - About Us</title>
    <style>
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
        
        .about-section {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .team-member {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
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
                        <a class="nav-link" href="orders.php"><i class="fas fa-box me-1"></i>Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php"><i class="fas fa-info-circle me-1"></i>About Us</a>
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
        <!-- Page Header -->
        <div class="dashboard-card card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">About Beauty & Skincare</h4>
                    <p class="text-muted mb-0">Our mission, our story, and our passion</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="about-section mb-4">
                    <h5 class="mb-3">Our Story</h5>
                    <p>Founded in 2024, Beauty & Skincare started with a simple mission: to help everyone discover their best skin. What began as a passion project has grown into a community-driven platform that connects skincare enthusiasts with high-quality, carefully curated products.</p>
                    <p>We believe that skincare is more than just products—it's about understanding your unique skin, building confidence, and creating personalized routines that make you feel amazing.</p>
                </div>

                <div class="about-section">
                    <h5 class="mb-3">Our Mission</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <i class="fas fa-leaf fa-3x text-primary mb-3"></i>
                                <h6>Clean Beauty</h6>
                                <p class="small">We prioritize products with natural, ethical ingredients.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                <h6>Community</h6>
                                <p class="small">Building a supportive network of skincare lovers.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <i class="fas fa-handshake fa-3x text-primary mb-3"></i>
                                <h6>Transparency</h6>
                                <p class="small">Honest information about ingredients and results.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Additional content can go here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
</rewritten_file>


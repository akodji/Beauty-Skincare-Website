<?php
session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Beauty & Skincare Routine Manager</title>

    <!-- Custom CSS -->
    <style>
        /*Animation when hovering cards*/
        .routine-card {
            transition: transform 0.2s;
        }

        .routine-card:hover {
            transform: translateY(-5px);
        }

        .hero-section {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFF0F5 100%);
            padding: 4rem 0;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="landing.php">
                <i class="fas fa-spa me-2"></i>
                Beauty & Skincare Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="landing.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="routine.php"><i class="fas fa-calendar-alt me-1"></i> Routines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php"><i class="fas fa-flask me-1"></i> Products</a>
                    </li>

                    <?php if (!isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                        </li>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>


    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Track Your Beauty Journey</h1>
            <p class="lead mb-4">Organize your skincare routines, track products, and achieve your beauty goals.</p>
            <a href="login.php" class="btn btn-dark btn-lg">Get Started</a>
        </div>
    </section>


    <!-- Main Content -->
    <div class="container my-5">
        <!-- Quick Actions -->
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="card routine-card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-sun fa-3x text-warning mb-3"></i>
                        <h5 class="card-title">Setup Your Morning Routine</h5>
                        <p class="card-text">Start your day with a perfect skincare routine</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card routine-card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-moon fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Setup Your Night Routine</h5>
                        <p class="card-text">End your day with proper skin care</p>

                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card routine-card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-plus fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Add Skincare Products of Your Choice</h5>
                        <p class="card-text">Track a new beauty product</p>

                    </div>
                </div>
            </div>
        </div>



        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
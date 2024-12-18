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
$stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE username = ?");
if ($stmt === false) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $messageContent = trim($_POST['message']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($messageContent)) {
        $message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
    } else {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO contact_submissions (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("sssss", $name, $email, $phone, $subject, $messageContent);

        if ($stmt->execute()) {
            $message = 'Your message has been sent successfully!';
        } else {
            $message = 'Failed to send your message. Please try again later.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Beauty & Skincare - Contact</title>
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
        
        .contact-form {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
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
                        <a class="nav-link" href="about.php"><i class="fas fa-info-circle me-1"></i>About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php"><i class="fas fa-envelope me-1"></i>Contact Us</a>
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
                    <h4 class="mb-1">Contact Us</h4>
                    <p class="text-muted mb-0">We'd love to hear from you! Send us a message.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="contact-form">
                    <h5 class="mb-4">Send us a Message</h5>
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number (Optional)</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="">Select a Subject</option>
                                <option value="support">Customer Support</option>
                                <option value="product">Product Inquiry</option>
                                <option value="feedback">Feedback</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="contact-form h-100">
                    <h5 class="mb-4">Contact Information</h5>
                    <div class="mb-3">
                        <h6><i class="fas fa-map-marker-alt me-2 text-primary"></i>Address</h6>
                        <p>123 Skincare Lane, Beauty City, BC 12345</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-phone me-2 text-primary"></i>Phone</h6>
                        <p>(555) 123-4567</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-envelope me-2 text-primary"></i>Email</h6>
                        <p>support@beautyskincare.com</p>
                    </div>
                    <div>
                        <h6>Follow Us</h6>
                        <div class="social-links">
                            <a href="#" class="text-primary me-3"><i class="fab fa-facebook fa-2x"></i></a>
                            <a href="#" class="text-primary me-3"><i class="fab fa-instagram fa-2x"></i></a>
                            <a href="#" class="text-primary me-3"><i class="fab fa-twitter fa-2x"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
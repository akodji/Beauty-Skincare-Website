<?php
include 'config.php';

// Check if signup form is submitted
if (isset($_POST['signup'])) {
    // Sanitize and validate input
    $firstname = filter_var(trim($_POST['firstname']), FILTER_SANITIZE_STRING);
    $lastname = filter_var(trim($_POST['lastname']), FILTER_SANITIZE_STRING);
    $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation checks
    $error = '';

    // Validate email format using regex
    if (!preg_match('/^[\w\-\.]+@([\w\-]+\.)+[\w\-]{2,4}$/', $email)) {
        header("Location: signup.php?error=Invalid email format");
        exit();
    }

    // Password validation
    if (!preg_match('/^((?=\S*?[A-Z])(?=\S*?[a-z])(?=\S*?[0-9]).{6,})\S$/', $password)) {
        header("Location: signup.php?error=Password must be at least 6 characters long and include at least one uppercase letter, one lowercase letter, and one number.");
        exit();
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        header("Location: signup.php?error=Passwords do not match");
        exit();
    }

    // Check if email or username already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: signup.php?error=Email or username already exists");
        exit();
    }

    // Hash password 
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user 
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, password_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $firstname, $lastname, $username, $email, $password_hash);

    if ($stmt->execute()) {
        // Create default settings for the user
        // $user_id = $stmt->insert_id;
        // $settings_stmt = $conn->prepare("INSERT INTO settings (user_id) VALUES (?)");
        // $settings_stmt->bind_param("i", $user_id);
        // $settings_stmt->execute();

        // Redirect to login page with success message
        header("Location: login.php?success=Account created successfully. Please login.");
        exit();
    } else {
        header("Location: login.php?error=Error creating account: " . $stmt->error);
        exit();
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
    <title>Sign Up - Beauty & Skincare Manager</title>
    <style>
        body {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFF0F5 100%);
            min-height: 100vh;
        }

        .auth-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .auth-sidebar {
            background: linear-gradient(45deg, #FFB6C1, #FFC0CB);
            border-radius: 20px 0 0 20px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
        }

        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: #FFB6C1;
            box-shadow: 0 0 0 0.25rem rgba(255, 182, 193, 0.25);
        }

        .btn-auth {
            background: linear-gradient(45deg, #FFB6C1, #FFC0CB);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            color: white;
        }

        .auth-tabs {
            border-bottom: none;
        }

        .auth-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 1rem 2rem;
        }

        .auth-tabs .nav-link.active {
            color: #FFB6C1;
            background: none;
            border-bottom: 2px solid #FFB6C1;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="auth-container">
                    <div class="row g-0">
                        <!-- Sidebar -->
                        <div class="col-lg-4 auth-sidebar">
                            <h2 class="mb-4">Welcome to Your Beauty Journey</h2>
                            <p class="mb-0">Create your account to start tracking your skincare routine.</p>
                        </div>

                        <!-- Signup Form -->
                        <div class="col-lg-8 p-4">
                            <h2 class="text-center mb-4">Sign Up</h2>

                            <?php
                            if (isset($_GET['error'])) {
                                echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
                            }
                            ?>

                            <form method="post" action="signup.php">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="firstname" class="form-control" placeholder="Enter your first name" required>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="lastname" class="form-control" placeholder="Enter your last name" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Email address</label>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Create a password" required pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$" title="Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, and one number.">

                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                                </div>
                                <div class="mb-4">
                                </div>
                                <button type="submit" name="signup" class="btn btn-auth w-100 mb-4">Sign Up</button>

                                <div class="text-center">
                                    <p>Already have an account? <a href="login.php" style="color: #FFB6C1;">Login</a></p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
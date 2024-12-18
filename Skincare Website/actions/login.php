<?php
include 'config.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

// Check if login form is submitted
if(isset($_POST['login'])) {
    // Sanitize input
    $login_input = filter_var(trim($_POST['login_input']), FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    
    // Validate email format using regex if login input is an email
    if (filter_var($login_input, FILTER_VALIDATE_EMAIL) && !preg_match('/^[\w\-\.]+@([\w\-]+\.)+[\w\-]{2,4}$/', $login_input)) {
        header("Location: login.php?error=Invalid email format");
        exit();
    }
    
    // Prepare statement to check by either email or username
    $stmt = $conn->prepare("SELECT user_id, username, email, password_hash FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if(password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid password
            header("Location: login.php?error=Invalid credentials");
            exit();
        }
    } else {
        // User not found
        header("Location: login.php?error=User not found");
        exit();
    }
}

//Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Login - Beauty & Skincare Manager</title>
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
        .social-login {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
            color: #6c757d;
            transition: all 0.2s;
        }
        .social-login:hover {
            background: #f8f9fa;
            color: #FFB6C1;
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
                            <h2 class="mb-4">Welcome Back</h2>
                            <p class="mb-0">Log in to continue tracking your skincare journey.</p>
                        </div>
                        
                        <!-- Login Form -->
                        <div class="col-lg-8 p-4">
                            <h2 class="text-center mb-4">Login</h2>
                            
                            <?php 
                            if(isset($_GET['success'])) {
                                echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
                            }
                            if(isset($_GET['error'])) {
                                echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
                            }
                            ?>
                            
                            <form method="post" action="login.php">
                                <div class="mb-4">
                                    <label class="form-label">Email or Username</label>
                                    <input type="text" name="login_input" class="form-control" placeholder="Enter your email or username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                <div class="mb-4 d-flex justify-content-between">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="remember">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    <a href="#" class="text-decoration-none" style="color: #FFB6C1;">Forgot password?</a>
                                </div>
                                <button type="submit" name="login" class="btn btn-auth w-100 mb-4">Login</button>
                                
                                <div class="text-center">
                                    <p>Don't have an account? <a href="signup.php" style="color: #FFB6C1;">Sign Up</a></p>
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
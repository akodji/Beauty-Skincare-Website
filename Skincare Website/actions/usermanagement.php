<?php
include 'config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle Create/Update User
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create' || $_POST['action'] == 'update') {
            $firstname = filter_var(trim($_POST['firstname']), FILTER_SANITIZE_STRING);
            $lastname = filter_var(trim($_POST['lastname']), FILTER_SANITIZE_STRING);
            $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $userrole = filter_var($_POST['userrole'], FILTER_VALIDATE_INT);
            
            // Validate userrole is either 1 or 2
            if (!in_array($userrole, [1, 2])) {
                $error = "Invalid user role selected";
            } else {
                if ($_POST['action'] == 'create') {
                    // Validate password is not empty for new users
                    if (empty($_POST['password'])) {
                        $error = "Password is required for new users";
                    } else {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, password_hash, userrole) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssi", $firstname, $lastname, $username, $email, $password, $userrole);
                    }
                } else {
                    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                    if (!$user_id) {
                        $error = "Invalid user ID";
                    } else {
                        if (!empty($_POST['password'])) {
                            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, password_hash=?, userrole=? WHERE user_id=?");
                            $stmt->bind_param("sssssii", $firstname, $lastname, $username, $email, $password, $userrole, $user_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, userrole=? WHERE user_id=?");
                            $stmt->bind_param("ssssii", $firstname, $lastname, $username, $email, $userrole, $user_id);
                        }
                    }
                }
                
                if (!isset($error) && $stmt->execute()) {
                    $success = $_POST['action'] == 'create' ? 'User created successfully!' : 'User updated successfully!';
                } elseif (!isset($error)) {
                    $error = $stmt->error;
                }
            }
        }
        
        // Handle Delete User
        elseif ($_POST['action'] == 'delete' && isset($_POST['user_id'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            if ($user_id) {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $success = 'User deleted successfully!';
                } else {
                    $error = $stmt->error;
                }
            }
        }
    }
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);

// Function to get role name
function getRoleName($roleId) {
    return $roleId == 1 ? 'Super Admin' : 'Regular Admin';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Beauty & Skincare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFF0F5 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .content-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        .btn-custom {
            background: linear-gradient(45deg, #FFB6C1, #FFC0CB);
            border: none;
            color: white;
        }
        .btn-custom:hover {
            background: linear-gradient(45deg, #FFC0CB, #FFB6C1);
            color: white;
        }
        .table {
            background: white;
            border-radius: 10px;
        }
        .modal-content {
            border-radius: 20px;
        }
        .navbar{
            margin-top: -5px;
            
        }
        body,html{
            margin: 0;
            padding: 0;
        }
    </style>

    
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand" href="landing.php"><i class="fas fa-spa me-2"></i>Beauty & Skincare Manager</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php"><i class="fas fa-shopping-bag me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="routine.php"><i class="fas fa-calendar-check me-1"></i>Routines</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Management</h2>
            <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['firstname']); ?></td>
                            <td><?php echo htmlspecialchars($user['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars(getRoleName($user['userrole'])); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-custom" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="action" value="create">
                        <input type="hidden" name="user_id" id="user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="firstname" id="firstname" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lastname" id="lastname" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" id="password">
                            <small class="text-muted" id="passwordHelp">Leave empty to keep existing password when updating</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">User Role</label>
                            <select class="form-select" name="userrole" id="userrole" required>
                                <option value="1">Super Admin</option>
                                <option value="2">Regular Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-custom">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this user?
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('action').value = 'update';
            document.getElementById('user_id').value = user.user_id;
            document.getElementById('firstname').value = user.firstname;
            document.getElementById('lastname').value = user.lastname;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('userrole').value = user.userrole;
            document.getElementById('password').value = '';
            document.getElementById('passwordHelp').style.display = 'block';
            
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function deleteUser(userId) {
            document.getElementById('delete_user_id').value = userId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Reset form when adding new user
        document.querySelector('[data-bs-target="#userModal"]').addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('userForm').reset();
            document.getElementById('action').value = 'create';
            document.getElementById('user_id').value = '';
            document.getElementById('passwordHelp').style.display = 'none';
        });
    </script>
</body>
</html>
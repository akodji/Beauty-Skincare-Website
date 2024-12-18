<?php
// routine.php - Routine management functionality
session_start();
require_once 'config.php'; // This will include your existing database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user details including role
$stmt = $conn->prepare("SELECT firstname, lastname, userrole FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = $user['firstname'] . ' ' . $user['lastname'];
$_SESSION['userrole'] = $user['userrole'];

$userId = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'create_routine':
            try {
                $stmt = $conn->prepare("
                    INSERT INTO routines (user_id, name, frequency) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $userId, $_POST['routine_name'], $_POST['frequency']);
                $stmt->execute();

                $response = [
                    'success' => true,
                    'message' => 'Routine created successfully!',
                    'routine_id' => $conn->insert_id
                ];
            } catch (Exception $e) {
                $response['message'] = 'Failed to create routine: ' . $e->getMessage();
            }
            break;

        case 'update_routine':
            try {
                $stmt = $conn->prepare("
                    UPDATE routines 
                    SET name = ?, frequency = ? 
                    WHERE routine_id = ? AND user_id = ?
                ");
                $stmt->bind_param("ssii", $_POST['routine_name'], $_POST['frequency'], $_POST['routine_id'], $userId);
                $stmt->execute();

                $response = [
                    'success' => true,
                    'message' => 'Routine updated successfully!'
                ];
            } catch (Exception $e) {
                $response['message'] = 'Failed to update routine: ' . $e->getMessage();
            }
            break;

        case 'delete_routine':
            try {
                $stmt = $conn->prepare("
                    DELETE FROM routines 
                    WHERE routine_id = ? AND user_id = ?
                ");
                $stmt->bind_param("ii", $_POST['routine_id'], $userId);
                $stmt->execute();

                $response = [
                    'success' => true,
                    'message' => 'Routine deleted successfully!'
                ];
            } catch (Exception $e) {
                $response['message'] = 'Failed to delete routine: ' . $e->getMessage();
            }
            break;

        case 'add_product':
            try {
                // Extensive logging
                error_log("Add Product Request Received");
                error_log("User ID: " . $userId);
                error_log("POST Data: " . print_r($_POST, true));

                // Validate input data
                if (!isset($_POST['routine_id']) || !isset($_POST['product_id'])) {
                    throw new Exception("Missing routine_id or product_id");
                }

                $routineId = intval($_POST['routine_id']);
                $productId = intval($_POST['product_id']);
                $usageTime = isset($_POST['usage_time']) ? $_POST['usage_time'] : 'anytime';

                // Verify the product exists and belongs to the user
                $productStmt = $conn->prepare("
                        SELECT product_id FROM products 
                        WHERE product_id = ? AND user_id = ?
                    ");
                $productStmt->bind_param("ii", $productId, $userId);
                $productStmt->execute();
                $productResult = $productStmt->get_result();

                if ($productResult->num_rows === 0) {
                    throw new Exception("Product not found or does not belong to user. Product ID: $productId, User ID: $userId");
                }

                // Verify the routine belongs to the user
                $routineStmt = $conn->prepare("
                        SELECT routine_id FROM routines 
                        WHERE routine_id = ? AND user_id = ?
                    ");
                $routineStmt->bind_param("ii", $routineId, $userId);
                $routineStmt->execute();
                $routineResult = $routineStmt->get_result();

                if ($routineResult->num_rows === 0) {
                    throw new Exception("Unauthorized access: Routine does not belong to user");
                }

                // Check if product is already in the routine
                $checkStmt = $conn->prepare("
                        SELECT * FROM routine_products 
                        WHERE routine_id = ? AND product_id = ?
                    ");
                $checkStmt->bind_param("ii", $routineId, $productId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    throw new Exception("Product is already in this routine");
                }

                // Insert product into routine
                $insertStmt = $conn->prepare("
                        INSERT INTO routine_products (routine_id, product_id, usage_time) 
                        VALUES (?, ?, ?)
                    ");
                $insertStmt->bind_param("iis", $routineId, $productId, $usageTime);
                $insertResult = $insertStmt->execute();

                if (!$insertResult) {
                    throw new Exception("Failed to insert product into routine: " . $insertStmt->error);
                }

                $response = [
                    'success' => true,
                    'message' => 'Product added to routine successfully!',
                    'routine_id' => $routineId,
                    'product_id' => $productId
                ];
            } catch (Exception $e) {
                error_log("Add Product Error: " . $e->getMessage());
                $response = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
            break;

        case 'remove_product':
            try {
                $stmt = $conn->prepare("
                    DELETE rp FROM routine_products rp 
                    INNER JOIN routines r ON rp.routine_id = r.routine_id 
                    WHERE rp.routine_id = ? 
                    AND rp.product_id = ? 
                    AND r.user_id = ?
                ");
                $stmt->bind_param("iii", $_POST['routine_id'], $_POST['product_id'], $userId);
                $stmt->execute();

                $response = [
                    'success' => true,
                    'message' => 'Product removed from routine successfully!'
                ];
            } catch (Exception $e) {
                $response['message'] = 'Failed to remove product: ' . $e->getMessage();
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user's routines
function getUserRoutines($userId)
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT r.*, 
            COUNT(rp.product_id) as product_count 
        FROM routines r 
        LEFT JOIN routine_products rp ON r.routine_id = rp.routine_id 
        WHERE r.user_id = ? 
        GROUP BY r.routine_id
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $userId); // Bind the parameter
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get routine details including products
function getRoutineDetails($routineId, $userId)
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT r.*, 
            p.product_id, p.name as product_name, p.brand, 
            rp.usage_time 
        FROM routines r 
        LEFT JOIN routine_products rp ON r.routine_id = rp.routine_id 
        LEFT JOIN products p ON rp.product_id = p.product_id 
        WHERE r.routine_id = ? AND r.user_id = ?
    ");
    $stmt->bind_param("ii", $routineId, $userId); // Bind the parameters
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getAvailableProducts($userId)
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE user_id = ? 
        ORDER BY name
    ");
    $stmt->bind_param("i", $userId); // Bind the parameter
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$routines = getUserRoutines($userId);
$availableProducts = getAvailableProducts($userId);

$stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = $user['firstname'] . ' ' . $user['lastname'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Beauty & Skincare Manager - Routine Management</title>
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

        .product-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .reminder-badge {
            background: linear-gradient(45deg, #FFB6C1, #FFC0CB);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        /* Toast positioning */
        .toast-container {
            position: fixed;
            bottom: 0;
            right: 0;
            z-index: 1070;
            padding: 1rem;
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
                        <a class="nav-link active" href="routine.php"><i class="fas fa-calendar me-1"></i>Routines</a>
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
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Toast Container -->
    <div class="toast-container">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successToastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="errorToastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Page Header -->
        <div class="dashboard-card card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Routine Management</h4>
                    <p class="text-muted mb-0">Create and manage your skincare routines</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRoutineModal">
                    <i class="fas fa-plus me-2"></i>New Routine
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Routine List -->
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">My Routines</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($routines as $routine): ?>
                                <a href="#" class="list-group-item list-group-item-action" data-routine-id="<?php echo $routine['routine_id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($routine['name']); ?></h6>
                                            <small><?php echo $routine['product_count']; ?> products</small>
                                        </div>
                                        <span class="reminder-badge"><?php echo htmlspecialchars($routine['frequency']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Routine Details -->
            <div class="col-lg-8">
                <?php foreach ($routines as $routine): ?>
                    <div class="dashboard-card card mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($routine['name']); ?></h5>
                            <div>
                                <button class="btn btn-outline-primary btn-sm me-2 edit-routine-btn"
                                    data-routine-id="<?php echo $routine['routine_id']; ?>"
                                    data-routine-name="<?php echo htmlspecialchars($routine['name']); ?>"
                                    data-routine-frequency="<?php echo htmlspecialchars($routine['frequency']); ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm delete-routine-btn" data-routine-id="<?php echo $routine['routine_id']; ?>">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Product List -->
                            <?php
                            $routineDetails = getRoutineDetails($routine['routine_id'], $userId);
                            foreach ($routineDetails as $product): ?>
                                <div class="routine-item">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-grip-vertical text-muted"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></small>
                                        </div>
                                        <div class="ms-3">
                                            <button class="btn btn-link btn-sm text-muted remove-product-btn" data-routine-id="<?php echo $routine['routine_id']; ?>" data-product-id="<?php echo $product['product_id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Add Product Button -->
                            <button class="btn btn-outline-primary btn-sm w-100 add-product-btn"
                                data-routine-id="<?php echo $routine['routine_id']; ?>">
                                <i class="fas fa-plus me-1"></i>Add Product
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- New Routine Modal -->
    <div class="modal fade" id="newRoutineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Routine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newRoutineForm">
                        <div class="mb-3">
                            <label class="form-label">Routine Name</label>
                            <input type="text" class="form-control" name="routine_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select class="form-control" name="frequency" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="occasionally">Occasionally</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Routine</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Product to Routine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="current-routine-id" value="">
                    <div class="mb-3">
                        <input type="search" class="form-control" id="productSearch" placeholder="Search products...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usage Time</label>
                        <select class="form-control" id="usageTime">
                            <option value="morning">Morning</option>
                            <option value="evening">Evening</option>
                            <option value="anytime">Anytime</option>
                        </select>
                    </div>
                    <div class="row" id="productsContainer">
                        <?php foreach ($availableProducts as $product): ?>
                            <div class="col-md-4 mb-3 product-item">
                                <div class="product-card p-3">
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'path/to/default-image.jpg'); ?>"
                                        class="rounded mb-2 w-100" alt="Product">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($product['brand']); ?></small>
                                    <button class="btn btn-outline-primary btn-sm w-100 select-product-btn"
                                        data-product-id="<?php echo $product['product_id']; ?>">
                                        Select
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="confirmAddProduct" disabled>Add to Routine</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmationMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Routine Modal -->
    <div class="modal fade" id="editRoutineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Routine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Routine Name</label>
                        <input type="text" class="form-control" id="editRoutineName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Frequency</label>
                        <select class="form-control" id="editRoutineFrequency">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="occasionally">Occasionally</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveRoutineBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize toasts
        const successToast = new bootstrap.Toast(document.getElementById('successToast'));
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        const editRoutineModal = new bootstrap.Modal(document.getElementById('editRoutineModal'));

        function showSuccess(message) {
            document.getElementById('successToastMessage').textContent = message;
            successToast.show();
        }

        function showError(message) {
            document.getElementById('errorToastMessage').textContent = message;
            errorToast.show();
        }

        // Product selection and modal handling (keep existing code)
        let selectedProductId = null;
        const addProductModal = new bootstrap.Modal(document.getElementById('addProductModal'));

        // ... (keep existing openAddProductModal and product search functionality)

        // Confirm product addition
        document.getElementById('confirmAddProduct').addEventListener('click', function() {
            const routineId = document.getElementById('current-routine-id').value;
            const usageTime = document.getElementById('usageTime').value;

            const formData = new FormData();
            formData.append('action', 'add_product');
            formData.append('routine_id', routineId);
            formData.append('product_id', selectedProductId);
            formData.append('usage_time', usageTime);

            fetch('routine.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addProductModal.hide();
                        showSuccess('Product added to routine successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message || 'Failed to add product');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while adding the product.');
                });
        });

        // Delete routine
        document.querySelectorAll('.delete-routine-btn').forEach(button => {
            button.addEventListener('click', function() {
                const routineId = this.dataset.routineId;
                document.getElementById('confirmationMessage').textContent = 'Are you sure you want to delete this routine?';
                document.getElementById('confirmActionBtn').onclick = function() {
                    const formData = new FormData();
                    formData.append('action', 'delete_routine');
                    formData.append('routine_id', routineId);

                    fetch('routine.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            confirmationModal.hide();
                            if (data.success) {
                                showSuccess('Routine deleted successfully!');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showError(data.message || 'Failed to delete routine');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showError('An error occurred while deleting the routine.');
                        });
                };
                confirmationModal.show();
            });
        });

        // Edit routine
        document.querySelectorAll('.edit-routine-btn').forEach(button => {
            button.addEventListener('click', function() {
                const routineId = this.dataset.routineId;
                const routineName = this.dataset.routineName;
                const routineFrequency = this.dataset.routineFrequency;

                // Set values in the modal
                document.getElementById('editRoutineName').value = routineName;
                document.getElementById('editRoutineFrequency').value = routineFrequency;

                // Update save button click handler
                document.getElementById('saveRoutineBtn').onclick = function() {
                    const formData = new FormData();
                    formData.append('action', 'update_routine');
                    formData.append('routine_id', routineId);
                    formData.append('routine_name', document.getElementById('editRoutineName').value);
                    formData.append('frequency', document.getElementById('editRoutineFrequency').value);

                    fetch('routine.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            editRoutineModal.hide();
                            if (data.success) {
                                showSuccess('Routine updated successfully!');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showError(data.message || 'Failed to update routine');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showError('An error occurred while updating the routine.');
                        });
                };
                editRoutineModal.show();
            });
        });

        // Remove product from routine
        document.querySelectorAll('.remove-product-btn').forEach(button => {
            button.addEventListener('click', function() {
                const routineId = this.dataset.routineId;
                const productId = this.dataset.productId;
                const productName = this.closest('.routine-item').querySelector('h6').textContent;

                document.getElementById('confirmationMessage').textContent = `Are you sure you want to remove "${productName}" from this routine?`;
                document.getElementById('confirmActionBtn').onclick = function() {
                    const formData = new FormData();
                    formData.append('action', 'remove_product');
                    formData.append('routine_id', routineId);
                    formData.append('product_id', productId);

                    fetch('routine.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            confirmationModal.hide();
                            if (data.success) {
                                showSuccess('Product removed from routine successfully!');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showError(data.message || 'Failed to remove product');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showError('An error occurred while removing the product.');
                        });
                };
                confirmationModal.show();
            });
        });

        // Add product functionality
        document.querySelectorAll('.add-product-btn').forEach(button => {
            button.addEventListener('click', function() {
                const routineId = this.dataset.routineId;
                document.getElementById('current-routine-id').value = routineId;
                selectedProductId = null;
                document.getElementById('confirmAddProduct').disabled = true;

                // Reset any previously selected products
                document.querySelectorAll('.select-product-btn').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                });

                // Reset search and usage time
                document.getElementById('productSearch').value = '';
                document.getElementById('usageTime').value = 'morning';

                // Show all products (reset search)
                document.querySelectorAll('.product-item').forEach(item => {
                    item.style.display = 'block';
                });

                addProductModal.show();
            });
        });

        // Product search functionality
        const productSearch = document.getElementById('productSearch');
        if (productSearch) {
            productSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('.product-item').forEach(item => {
                    const productName = item.querySelector('h6').textContent.toLowerCase();
                    const productBrand = item.querySelector('small').textContent.toLowerCase();
                    const matches = productName.includes(searchTerm) || productBrand.includes(searchTerm);
                    item.style.display = matches ? 'block' : 'none';
                });
            });
        }

        // Product selection
        document.querySelectorAll('.select-product-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.select-product-btn').forEach(b => {
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-outline-primary');
                });
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                selectedProductId = this.dataset.productId;
                document.getElementById('confirmAddProduct').disabled = false;
            });
        });

        // Add this to your existing JavaScript
        document.getElementById('newRoutineForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'create_routine');

            fetch('routine.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newRoutineModal = bootstrap.Modal.getInstance(document.getElementById('newRoutineModal'));
                        newRoutineModal.hide();
                        showSuccess('Routine created successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message || 'Failed to create routine');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while creating the routine.');
                });
        });
    </script>
</body>

</html>
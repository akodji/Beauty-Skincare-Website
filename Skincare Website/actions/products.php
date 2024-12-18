<?php
include 'config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch current user's details
$stmt = $conn->prepare("SELECT user_id, firstname, lastname, userrole FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) {
    header("Location: login.php");
    exit();
}

$userId = $userData['user_id'];

// Fetch user's products
$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$productsResult = $stmt->get_result();
$stmt->close();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];

    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete_product':
                    handleDeleteProduct($conn, $userId);
                    break;
                case 'edit_product':
                    handleEditProduct($conn, $userId);
                    break;
                default:
                    handleAddProduct($conn, $userId);
                    break;
            }
        } else {
            handleAddProduct($conn, $userId);
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to handle file upload
function handleFileUpload($file) {
    $upload_dir = 'uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.');
    }

    // Check file size (5MB limit)
    if ($file['size'] > 5000000) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to upload file.');
    }

    return $upload_path;
}

// Function to handle product deletion
function handleDeleteProduct($conn, $userId) {
    global $response;
    
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_POST['product_id'], $userId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_POST['product_id'], $userId);

    if ($stmt->execute()) {
        if ($product && $product['image_url'] && file_exists($product['image_url'])) {
            unlink($product['image_url']);
        }
        $response = ['success' => true, 'message' => 'Product deleted successfully!'];
    } else {
        throw new Exception('Failed to delete product.');
    }
    $stmt->close();
}

// Function to handle product editing
function handleEditProduct($conn, $userId) {
    global $response;
    
    $image_url = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $image_url = handleFileUpload($_FILES['product_image']);
    }

    if ($image_url) {
        $stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, brand = ?, product_type = ?, ingredients = ?, notes = ?, image_url = ? 
            WHERE product_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssssssii", 
            $_POST['name'], 
            $_POST['brand'], 
            $_POST['product_type'], 
            $_POST['ingredients'], 
            $_POST['notes'], 
            $image_url, 
            $_POST['product_id'], 
            $userId
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, brand = ?, product_type = ?, ingredients = ?, notes = ? 
            WHERE product_id = ? AND user_id = ?
        ");
        $stmt->bind_param("sssssii", 
            $_POST['name'], 
            $_POST['brand'], 
            $_POST['product_type'], 
            $_POST['ingredients'], 
            $_POST['notes'], 
            $_POST['product_id'], 
            $userId
        );
    }

    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Product updated successfully!'];
    } else {
        throw new Exception('Failed to update product.');
    }
    $stmt->close();
}

// Function to handle adding new product
function handleAddProduct($conn, $userId) {
    global $response;
    
    $image_url = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $image_url = handleFileUpload($_FILES['product_image']);
    }

    $stmt = $conn->prepare("
        INSERT INTO products (user_id, name, brand, product_type, ingredients, notes, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("issssss",
        $userId,
        $_POST['name'],
        $_POST['brand'],
        $_POST['product_type'],
        $_POST['ingredients'],
        $_POST['notes'],
        $image_url
    );

    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Product added successfully!'];
    } else {
        throw new Exception('Failed to add product to database.');
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - Beauty & Skincare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-pink: #FFB6C1;
            --light-pink: #FFF0F5;
        }

        .toast-container {
            z-index: 1090 !important;
        }

        .product-card {
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: scale(1.05);
        }

        .product-image {
            height: 200px;
            object-fit: cover;
        }

        .product-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-spa me-2"></i>Beauty & Skincare Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="routine.php">
                            <i class="fas fa-calendar me-1"></i>Routines
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">
                            <i class="fas fa-shopping-bag me-1"></i>Your Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">
                            <i class="fas fa-shopping-bag me-1"></i>Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-box me-1"></i>Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">
                            <i class="fas fa-info-circle me-1"></i>About Us
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">
                            <i class="fas fa-envelope me-1"></i>Contact Us
                        </a>
                    </li>
                    <?php if (isset($userData['userrole']) && $userData['userrole'] === '1'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="contact_submissions.php">
                                <i class="fas fa-comments me-1"></i>Customer Feedback
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($userData['firstname'] . ' ' . $userData['lastname']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Skincare Products</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus me-2"></i>Add New Product
            </button>
        </div>

        <div class="row">
            <?php while ($product = $productsResult->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card product-card position-relative">
                        <div class="product-actions">
                            <a href="#" class="btn btn-sm btn-warning edit-product"
                               data-product-id="<?php echo $product['product_id']; ?>"
                               data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                               data-product-brand="<?php echo htmlspecialchars($product['brand']); ?>"
                               data-product-type="<?php echo htmlspecialchars($product['product_type']); ?>"
                               data-product-ingredients="<?php echo htmlspecialchars($product['ingredients']); ?>"
                               data-product-notes="<?php echo htmlspecialchars($product['notes']); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-danger delete-product"
                               data-product-id="<?php echo $product['product_id']; ?>"
                               data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <img src="<?php echo !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'uploads/default.jpg'; ?>"
                             class="card-img-top product-image"
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text">
                                <strong>Brand:</strong> <?php echo htmlspecialchars($product['brand']); ?><br>
                                <strong>Type:</strong> <?php echo htmlspecialchars($product['product_type']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" class="form-control" name="brand">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Type</label>
                                <select class="form-select" name="product_type">
                                    <option value="Cleanser">Cleanser</option>
                                    <option value="Toner">Toner</option>
                                    <option value="Serum">Serum</option>
                                    <option value="Moisturizer">Moisturizer</option>
                                    <option value="Sunscreen">Sunscreen</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Image</label>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ingredients</label>
                            <textarea class="form-control" name="ingredients" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" id="editProductId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" name="name" id="editProductName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" id="editProductBrand">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Type</label>
                            <select class="form-select" name="product_type" id="editProductType">
                                <option value="Cleanser">Cleanser</option>
                                <option value="Toner">Toner</option>
                                <option value="Serum">Serum</option>
                                <option value="Moisturizer">Moisturizer</option>
                                <option value="Sunscreen">Sunscreen</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ingredients</label>
                            <textarea class="form-control" name="ingredients" id="editProductIngredients" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" id="editProductNotes" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-control" name="product_image" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
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

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <!-- Success Toast -->
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successToastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Error Toast -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize toasts
            const errorToastElement = document.getElementById('errorToast');
            const errorToast = errorToastElement ? new bootstrap.Toast(errorToastElement) : null;
            const successToastElement = document.getElementById('successToast');
            const successToast = successToastElement ? new bootstrap.Toast(successToastElement) : null;

            function showError(message) {
                if (errorToast) {
                    document.getElementById('errorToastMessage').textContent = message;
                    errorToast.show();
                } else {
                    console.error('Error toast element not found');
                }
            }

            function showSuccess(message) {
                if (successToast) {
                    document.getElementById('successToastMessage').textContent = message;
                    successToast.show();
                } else {
                    console.error('Success toast element not found');
                }
            }

            // Initialize modals
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            const editProductModal = new bootstrap.Modal(document.getElementById('editProductModal'));

            // Handle product editing
            document.querySelectorAll('.edit-product').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    const productBrand = this.dataset.productBrand;
                    const productType = this.dataset.productType;
                    const productIngredients = this.dataset.productIngredients;
                    const productNotes = this.dataset.productNotes;

                    document.getElementById('editProductId').value = productId;
                    document.getElementById('editProductName').value = productName;
                    document.getElementById('editProductBrand').value = productBrand;
                    document.getElementById('editProductType').value = productType;
                    document.getElementById('editProductIngredients').value = productIngredients;
                    document.getElementById('editProductNotes').value = productNotes;

                    editProductModal.show();
                });
            });

            // Handle form submission for editing
            document.getElementById('editProductForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'edit_product');

                fetch('products.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editProductModal.hide();
                        location.reload();
                    } else {
                        showError(data.message || 'Failed to update product');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while updating the product.');
                });
            });

            // Handle form submission for adding new product
            document.getElementById('addProductForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('products.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showError(data.message || 'Failed to add product');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while adding the product.');
                });
            });

            // Handle product deletion
            document.querySelectorAll('.delete-product').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;

                    document.getElementById('confirmationMessage').textContent =
                        `Are you sure you want to delete "${productName}"?`;

                    document.getElementById('confirmActionBtn').onclick = function() {
                        const formData = new FormData();
                        formData.append('action', 'delete_product');
                        formData.append('product_id', productId);

                        fetch('products.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            confirmationModal.hide();
                            if (data.success) {
                                location.reload();
                            } else {
                                showError(data.message || 'Failed to delete product');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showError('An error occurred while deleting the product.');
                        });
                    };

                    confirmationModal.show();
                });
            });

            // Reset form when modal is closed
            document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('addProductForm').reset();
            });
        });
    </script>
</body>
</html>
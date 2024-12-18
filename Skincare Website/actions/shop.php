<?php
// shop.php
include 'config.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['product_edit_success'])) {
    $product_edit_success = $_SESSION['product_edit_success'];
    unset($_SESSION['product_edit_success']); // Clear the session variable
}

// Function to get user role
function getUserRole($conn, $username)
{
    $stmt = $conn->prepare("SELECT userrole FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ? $user['userrole'] : '2';
}

// Fetch current user's details
$stmt = $conn->prepare("SELECT user_id, firstname, lastname FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$userId = $userData['user_id'];

// Get current user's role
$username = $_SESSION['username'];
$userRole = getUserRole($conn, $username);

// Handle product addition (for superadmins only)
if ($userRole === '1' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $brand = $_POST['brand'];
    $condition = $_POST['condition'];

    // Handle file upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $target_dir = "uploads/";
        // Create uploads directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $unique_filename = uniqid() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $unique_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO shop_products (seller_id, name_, description_, price, brand, condition_, image_url) VALUES ((SELECT user_id FROM users WHERE username = ?), ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdsss", $username, $name, $description, $price, $brand, $condition, $image_url);

    if ($stmt->execute()) {
        $product_add_success = true;
    } else {
        $product_add_error = true;
    }
    $stmt->close();
}



// Handle search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$products = []; // Initialize empty products array

if (!empty($search_query)) {
    // Use prepared statement for search to prevent SQL injection
    $search_stmt = $conn->prepare("SELECT * FROM shop_products WHERE 
        name_ LIKE ? OR 
        brand LIKE ? OR 
        description_ LIKE ? 
        ORDER BY created_at DESC");

    $search_param = "%{$search_query}%";
    $search_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $search_stmt->execute();
    $result = $search_stmt->get_result();

    // Fetch results
    $products = $result->fetch_all(MYSQLI_ASSOC);

    $search_stmt->close();
} else {
    // Fetch all products if no search is performed
    $result = $conn->query("SELECT * FROM shop_products ORDER BY created_at DESC");
    $products = $result->fetch_all(MYSQLI_ASSOC);
}

// Handle product editing (for superadmins only)
if ($userRole === '1' && isset($_POST['edit_product'])) {
    $product_id = $_POST['product_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $brand = $_POST['brand'];
    $condition = $_POST['condition'];

    // Handle file upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $target_dir = "uploads/";
        $unique_filename = uniqid() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $unique_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = $target_file;
        }
    }

    // Prepare the update statement
    if (!empty($image_url)) {
        $stmt = $conn->prepare("UPDATE shop_products SET name_ = ?, description_ = ?, price = ?, brand = ?, condition_ = ?, image_url = ? WHERE shop_product_id = ?");
        $stmt->bind_param("ssdsssi", $name, $description, $price, $brand, $condition, $image_url, $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE shop_products SET name_ = ?, description_ = ?, price = ?, brand = ?, condition_ = ? WHERE shop_product_id = ?");
        $stmt->bind_param("ssdssi", $name, $description, $price, $brand, $condition, $product_id);
    }

    if ($stmt->execute()) {
        $_SESSION['product_edit_success'] = true;
        header("Location: shop.php"); // Redirect to prevent form resubmission
        exit();
    }
}

// Handle product deletion (for superadmins only)
if ($userRole === '1' && isset($_GET['delete_product'])) {
    $product_id = $_GET['delete_product'];
    $stmt = $conn->prepare("DELETE FROM shop_products WHERE shop_product_id = ?");
    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        $product_delete_success = true;
    } else {
        $product_delete_error = true;
    }
    $stmt->close();
}

// Handle purchase (for regular users)
if ($userRole === '2' && isset($_POST['purchase'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get product price
        $stmt = $conn->prepare("SELECT price FROM shop_products WHERE shop_product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        // Calculate total
        $total = $product['price'] * $quantity;

        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount) VALUES ((SELECT user_id FROM users WHERE username = ?), ?)");
        $stmt->bind_param("sd", $username, $total);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();

        // Create order item
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, shop_product_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $product['price']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $purchase_success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $purchase_error = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Beauty & Skincare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-card {
            transition: transform 0.2s;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            height: 200px;
            object-fit: cover;
        }

        .search-container {
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
                        <a class="nav-link active" href="shop.php"><i class="fas fa-shopping-bag me-1"></i>Shop</a>
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


    <div class="container py-4">
        <!-- Page Heading -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Shop Our Products</h2>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>

        <!-- Search Results Message -->
        <?php if (!empty($search_query)): ?>
            <div class="alert alert-info">
                Search results for "<?php echo htmlspecialchars($search_query); ?>":
                <?php echo count($products); ?> product(s) found
            </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if (isset($purchase_success)): ?>
            <div class="alert alert-success" role="alert">
                Purchase successful! Thank you for your order.
            </div>
        <?php endif; ?>

        <?php if (isset($purchase_error)): ?>
            <div class="alert alert-danger" role="alert">
                Error processing your purchase. Please try again.
            </div>
        <?php endif; ?>

        <?php if (isset($product_add_success)): ?>
            <div class="alert alert-success" role="alert">
                Product added successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($product_add_error)): ?>
            <div class="alert alert-danger" role="alert">
                Error adding product. Please try again.
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php
                echo htmlspecialchars($_SESSION['message']);
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($product_edit_error)): ?>
            <div class="alert alert-danger" role="alert">
                Error updating product. Please try again.
            </div>
        <?php endif; ?>

        <?php if (isset($product_delete_success)): ?>
            <div class="alert alert-success" role="alert">
                Product deleted successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($product_delete_error)): ?>
            <div class="alert alert-danger" role="alert">
                Error deleting product. Please try again.
            </div>
        <?php endif; ?>

        <?php if ($userRole === '1'): ?>
            <!-- Product Addition Form (Superadmin only) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add New Product</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Product Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Brand</label>
                                <input type="text" name="brand" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Price</label>
                                <input type="number" step="0.01" name="price" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Condition</label>
                                <select name="condition" class="form-control" required>
                                    <option value="new">New</option>
                                    <option value="used">Used</option>
                                    <option value="open box">Open Box</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label>Product Image</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        No products found.
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card product-card">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name_']); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name_']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($product['description_']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Brand: <?php echo htmlspecialchars($product['brand']); ?><br>
                                    Condition: <?php echo htmlspecialchars($product['condition_']); ?>
                                </small>
                            </p>
                            <h6 class="card-subtitle mb-3">GH₵<?php echo number_format($product['price'], 2); ?></h6>

                            <?php if ($userRole === '2'): ?>
                                <!-- Purchase Form (Regular users only) -->
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="product_id" value="<?php echo $product['shop_product_id']; ?>">
                                    <input type="number" name="quantity" value="1" min="1" class="form-control" style="width: 80px;">
                                    <button type="submit" name="purchase" class="btn btn-primary">Buy Now</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($userRole === '1'): ?>
                                <div class="d-flex gap-2">
                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                        data-bs-target="#editProductModal<?php echo $product['shop_product_id']; ?>">
                                        Edit Product
                                    </button>

                                    <!-- Delete Button -->
                                    <a href="?delete_product=<?php echo $product['shop_product_id']; ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this product?')">
                                        Delete Product
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Product Modal -->
                <?php if ($userRole === '1'): ?>
                    <div class="modal fade" id="editProductModal<?php echo $product['shop_product_id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Product</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <input type="hidden" name="product_id" value="<?php echo $product['shop_product_id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Product Name</label>
                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name_']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Brand</label>
                                            <input type="text" name="brand" class="form-control" value="<?php echo htmlspecialchars($product['brand']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Price</label>
                                            <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $product['price']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Condition</label>
                                            <select name="condition" class="form-control" required>
                                                <option value="new" <?php echo $product['condition_'] == 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="used" <?php echo $product['condition_'] == 'used' ? 'selected' : ''; ?>>Used</option>
                                                <option value="open box" <?php echo $product['condition_'] == 'open box' ? 'selected' : ''; ?>>Open Box</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($product['description_']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Product Image</label>
                                            <input type="file" name="image" class="form-control" accept="image/*">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <small class="text-muted">Current image will be replaced if a new image is uploaded.</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="edit_product" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js">
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 3000);
    </script>
</body>

</html>
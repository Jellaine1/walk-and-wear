
<?php
require_once '../includes/config.php';
require_admin();

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $brand = trim($_POST['brand']);
    $category_id = (int)$_POST['category_id'];
    $sizes = trim($_POST['sizes']);
    $image = trim($_POST['image']) ?: 'images/product-placeholder.svg';

    $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, name, description, price, stock, image, brand, size_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issdisss", $category_id, $name, $description, $price, $stock, $image, $brand, $sizes);
    mysqli_stmt_execute($stmt);
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Product | Walk & Wear Admin</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body style="background:var(--cream);">
<div class="container" style="max-width:600px;">
    <h1 style="margin:40px 0 20px;">Add New Product</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">Product added successfully!</div>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" required>
                    <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" value="Walk & Wear">
            </div>
            <div class="form-group">
                <label>Price (₱)</label>
                <input type="number" step="0.01" name="price" required>
            </div>
            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" name="stock" required>
            </div>
            <div class="form-group">
                <label>Available Sizes (comma separated)</label>
                <input type="text" name="sizes" value="38,39,40,41,42,43">
            </div>
            <div class="form-group">
                <label>Image Path (e.g. images/catalog/shoe7.jpg)</label>
                <input type="text" name="image" placeholder="images/product-placeholder.svg">
            </div>
            <button type="submit" class="btn btn-full">Add Product</button>
        </form>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/index.php">&larr; Back to Dashboard</a>
</div>
</body>
</html>

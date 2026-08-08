<?php
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='index.php?page=login';</script>"; exit(); }

$rest_id = isset($_GET['rest_id']) ? intval($_GET['rest_id']) : 0;
$rest = $conn->query("SELECT * FROM restaurants WHERE id = $rest_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_item'])) {
    $item_name   = trim($_POST['item_name']);
    $category    = trim($_POST['category']);
    $price       = floatval($_POST['price']);
    $image_url   = trim($_POST['image_url']);
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("INSERT INTO food_items (restaurant_id, item_name, category, price, image_url, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdss", $rest_id, $item_name, $category, $price, $image_url, $description);
    if ($stmt->execute()) {
        echo "<script>alert('Food Item Added!'); window.location.href='index.php?page=admin_items&rest_id=$rest_id';</script>";
        exit();
    }
}

$items = $conn->query("SELECT * FROM food_items WHERE restaurant_id = $rest_id ORDER BY id DESC");
?>

<div class="admin-card">
    <a href="index.php?page=admin_restaurants" style="color:#0D47A1; text-decoration:none; font-weight:800; font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Restaurants</a>
    <h3 style="color:#0D47A1; margin:15px 0; font-weight:800;">Add Menu Item for <?= htmlspecialchars($rest['name'] ?? 'Restaurant') ?></h3>

    <form method="POST">
        <input type="hidden" name="action_add_item" value="1">
        <div class="form-group"><label>Item Name</label><input type="text" name="item_name" required placeholder="e.g. Chicken Dum Biryani"></div>
        <div class="form-group">
            <label>Category</label>
            <select name="category">
                <option value="Biryani">Biryani</option>
                <option value="Tiffins">Tiffins</option>
                <option value="Pizza">Pizza</option>
                <option value="Burger">Burger</option>
                <option value="Dessert">Dessert</option>
            </select>
        </div>
        <div class="form-group"><label>Price (₹)</label><input type="number" step="0.01" name="price" required placeholder="250"></div>
        <div class="form-group"><label>Image URL</label><input type="text" name="image_url" required placeholder="https://images.unsplash.com/..."></div>
        <div class="form-group"><label>Description</label><textarea name="description" rows="2" placeholder="Brief description..."></textarea></div>
        <button type="submit" class="btn" style="background:#FF9800; width:100%;">Add Item to Menu</button>
    </form>
</div>

<div class="admin-card">
    <h3 style="color:#0D47A1; margin-bottom:15px; font-weight:800;">Current Menu Items</h3>
    <?php while($item = $items->fetch_assoc()): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding:10px 0;">
            <div>
                <strong><?= htmlspecialchars($item['item_name']) ?></strong> (<?= $item['category'] ?>)
                <div style="color:#0D47A1; font-weight:800; font-size:14px;">₹<?= $item['price'] ?></div>
            </div>
        </div>
    <?php endwhile; ?>
</div>
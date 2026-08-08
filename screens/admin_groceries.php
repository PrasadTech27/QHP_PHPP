<?php
$my_admin_id = 2; 
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $my_admin_id) {
    echo "<script>alert('Unauthorized Access!'); window.location.href='index.php';</script>";
    exit();
}

$toast_message = "";

// Handle Add Grocery POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_admin_grocery'])) {
    $g_name = trim($_POST['g_name']);
    $g_price = floatval($_POST['g_price']);
    $g_img = trim($_POST['g_img']);
    $g_desc = trim($_POST['g_desc']);
    
    if (!empty($g_name) && $g_price > 0) {
        $stmt = $conn->prepare("INSERT INTO groceries (name, price, image_url, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdss", $g_name, $g_price, $g_img, $g_desc);
        if ($stmt->execute()) {
            $toast_message = "Grocery item added successfully!";
        }
    } else {
        $toast_message = "Please enter valid item name and price.";
    }
}

// Handle Delete Grocery Item
if (isset($_GET['delete_grocery'])) {
    $del_id = intval($_GET['delete_grocery']);
    $conn->query("DELETE FROM groceries WHERE id = $del_id");
    $toast_message = "Grocery item deleted successfully!";
}

$groceries_list = $conn->query("SELECT * FROM groceries ORDER BY id DESC");
?>

<style>
    .admin-med-wrapper { font-family: 'Segoe UI', sans-serif; padding-bottom: 90px; max-width: 900px; margin: 0 auto; }
    .admin-card { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 6px 20px rgba(0,0,0,0.04); margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .admin-title { color: #0D47A1; font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
    @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }

    .input-box { margin-bottom: 15px; }
    .input-box label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .input-box input, .input-box textarea { width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 14px; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; }
    
    .btn-submit { background: #0D47A1; color: #fff; border: none; padding: 14px; border-radius: 14px; font-weight: 800; font-size: 15px; width: 100%; cursor: pointer; box-shadow: 0 4px 15px rgba(13,71,161,0.3); }
    
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
    th { background: #f8fafc; color: #0D47A1; font-weight: 800; }
</style>

<?php if (!empty($toast_message)): ?>
    <div style="background:#0D47A1; color:#fff; padding:12px 20px; border-radius:12px; margin-bottom:20px; font-weight:700;">
        <i class="fas fa-circle-check" style="color:#FF9800; margin-right:6px;"></i> <?= htmlspecialchars($toast_message) ?>
    </div>
<?php endif; ?>

<div class="admin-med-wrapper">
    <div class="admin-card">
        <div class="admin-title"><i class="fas fa-shopping-basket"></i> Admin Add Grocery Inventory</div>
        <form method="POST">
            <input type="hidden" name="action_add_admin_grocery" value="1">
            
            <div class="form-grid">
                <div class="input-box">
                    <label>Item Name</label>
                    <input type="text" name="g_name" placeholder="e.g. Aashirvaad Atta 5kg" required>
                </div>
                <div class="input-box">
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="g_price" placeholder="e.g. 240.00" required>
                </div>
            </div>

            <div class="input-box">
                <label>Image URL</label>
                <input type="url" name="g_img" placeholder="https://example.com/grocery.jpg">
            </div>

            <div class="input-box">
                <label>Description / Details</label>
                <textarea name="g_desc" rows="2" placeholder="100% whole wheat flour"></textarea>
            </div>

            <button type="submit" class="btn-submit">Add Grocery Item</button>
        </form>
    </div>

    <!-- Groceries List Table Down -->
    <div class="admin-card">
        <div class="admin-title" style="font-size:17px;"><i class="fas fa-list"></i> Managed Groceries Catalog</div>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Item Name</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groceries_list && $groceries_list->num_rows > 0): ?>
                    <?php while($g = $groceries_list->fetch_assoc()): 
                        $img = !empty($g['image_url']) ? $g['image_url'] : 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=100';
                    ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($img) ?>" style="width:40px; height:40px; border-radius:8px; object-fit:cover;"></td>
                            <td><strong><?= htmlspecialchars($g['name']) ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($g['description']) ?></small></td>
                            <td>₹<?= $g['price'] ?></td>
                            <td>
                                <a href="index.php?page=admin_groceries&delete_grocery=<?= $g['id'] ?>" onclick="return confirm('Delete this grocery item?')" style="color:#dc2626; font-weight:800; text-decoration:none;"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:#64748b;">No grocery items added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>s
<?php
// Admin ID 3 set chesanu (nee project prakaram change చేసుకోవచ్చు)
$my_admin_id = 2; 
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $my_admin_id) {
    echo "<script>alert('Unauthorized Access!'); window.location.href='index.php';</script>";
    exit();
}

$toast_message = "";

// Handle Add Medicine POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_admin_medicine'])) {
    $med_name = trim($_POST['med_name']);
    $med_price = floatval($_POST['med_price']);
    $med_img = trim($_POST['med_img']);
    $med_desc = trim($_POST['med_desc']);

    if (!empty($med_name) && $med_price > 0) {
        $stmt = $conn->prepare("INSERT INTO medicines (name, price, image_url, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdss", $med_name, $med_price, $med_img, $med_desc);
        if ($stmt->execute()) {
            $toast_message = "Medicine added successfully by admin!";
        }
    } else {
        $toast_message = "Please enter valid medicine name and price.";
    }
}

// Handle Delete Medicine
if (isset($_GET['delete_med'])) {
    $del_id = intval($_GET['delete_med']);
    $conn->query("DELETE FROM medicines WHERE id = $del_id");
    $toast_message = "Medicine deleted successfully!";
}

$medicines_list = $conn->query("SELECT * FROM medicines ORDER BY id DESC");
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
        <div class="admin-title"><i class="fas fa-pills"></i> Admin Add Medicine Inventory</div>
        <form method="POST">
            <input type="hidden" name="action_add_admin_medicine" value="1">
            
            <div class="form-grid">
                <div class="input-box">
                    <label>Medicine Name</label>
                    <input type="text" name="med_name" placeholder="e.g. Paracetamol 650mg" required>
                </div>
                <div class="input-box">
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="med_price" placeholder="e.g. 45.00" required>
                </div>
            </div>

            <div class="input-box">
                <label>Image URL</label>
                <input type="url" name="med_img" placeholder="https://example.com/medicine.jpg">
            </div>

            <div class="input-box">
                <label>Description / Usage</label>
                <textarea name="med_desc" rows="2" placeholder="Fever and pain relief tablet"></textarea>
            </div>

            <button type="submit" class="btn-submit">Add Medicine to Store</button>
        </form>
    </div>

    <!-- Medicines List Table -->
    <div class="admin-card">
        <div class="admin-title" style="font-size:17px;"><i class="fas fa-list"></i> Managed Medicines Catalog</div>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($medicines_list && $medicines_list->num_rows > 0): ?>
                    <?php while($m = $medicines_list->fetch_assoc()): ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($m['image_url'] ?: 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=100') ?>" style="width:40px; height:40px; border-radius:8px; object-fit:cover;"></td>
                            <td><strong><?= htmlspecialchars($m['name']) ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($m['description']) ?></small></td>
                            <td>₹<?= $m['price'] ?></td>
                            <td>
                                <a href="index.php?page=admin_medicines&delete_med=<?= $m['id'] ?>" onclick="return confirm('Delete this medicine?')" style="color:#dc2626; font-weight:800; text-decoration:none;"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:#64748b;">No medicines added by admin yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
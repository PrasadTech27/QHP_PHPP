<?php
if (!isset($_SESSION['user_id'])) { 
    echo "<script>window.location.href='index.php?page=login';</script>"; 
    exit(); 
}

$user_id = $_SESSION['user_id'];

// Fetch Primary Delivery Address Safely
$primary = null;
$check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id AND is_primary = 1 LIMIT 1");
if (!$check_addr || $check_addr->num_rows === 0) {
    $check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
}
if ($check_addr && $check_addr->num_rows > 0) {
    $primary = $check_addr->fetch_assoc();
}

$raw_address = '';
if ($primary) {
    if (!empty($primary['address'])) $raw_address = $primary['address'];
    elseif (!empty($primary['full_address'])) $raw_address = $primary['full_address'];
    elseif (!empty($primary['address_line'])) $raw_address = $primary['address_line'];
    elseif (!empty($primary['location'])) $raw_address = $primary['location'];
}

$full_address_text = !empty($raw_address) ? htmlspecialchars($raw_address) : 'No address details found.';
$address_title = ($primary && !empty($primary['title'])) ? htmlspecialchars($primary['title']) : 'Current Location';

// Fetch Groceries from Database
$groceries_result = $conn->query("SELECT * FROM groceries ORDER BY id DESC");
?>

<style>
    .med-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; }
    
    .location-card-box {
        background: #ffffff; border-radius: 18px; padding: 14px 18px;
        border: 1.5px solid #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        display: flex; align-items: center; justify-content: space-between;
        gap: 15px; margin-bottom: 20px;
    }
    .loc-left-details { display: flex; align-items: center; gap: 12px; overflow: hidden; }
    .loc-pin-badge {
        width: 40px; height: 40px; border-radius: 12px;
        background: #e0f2fe; color: #0D47A1;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0;
    }
    .loc-title-text { font-size: 14px; font-weight: 800; color: #1e293b; }
    .loc-sub-address {
        font-size: 12px; color: #64748b; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;
    }
    .btn-change-address {
        background: #f1f5f9; color: #0D47A1; padding: 6px 14px;
        border-radius: 20px; font-size: 12px; font-weight: 800; text-decoration: none; border: 1px solid #cbd5e1;
    }

    .med-search-bar {
        background: #ffffff; border-radius: 16px; padding: 6px 18px;
        display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
        border: 1.5px solid #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .med-search-bar i { color: #0D47A1; font-size: 16px; }
    .med-search-bar input { border: none; outline: none; width: 100%; padding: 10px 0; font-size: 14px; font-weight: 600; color: #1e293b; }

    .med-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
    .med-item-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .med-img-box { height: 130px; width: 100%; background: #f1f5f9; }
    .med-img-box img { width: 100%; height: 100%; object-fit: cover; }
    
    .btn-add-cart { background: #FF9800; color: #ffffff; border: none; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; cursor: pointer; transition: 0.2s; }
    .btn-add-cart:hover { background: #f57c00; }

    .qty-stepper { display: flex; align-items: center; border: 1.5px solid #0D47A1; border-radius: 20px; overflow: hidden; background: #ffffff; }
    .qty-stepper button { background: none; border: none; color: #0D47A1; font-weight: 900; padding: 6px 12px; cursor: pointer; font-size: 14px; }
    .qty-stepper button:hover { background: #e0f2fe; }
    .qty-stepper span { font-size: 13px; font-weight: 800; padding: 0 6px; color: #0D47A1; }
</style>

<div class="med-wrapper">
    <!-- Delivery Location Card Top -->
    <div class="location-card-box">
        <div class="loc-left-details">
            <div class="loc-pin-badge"><i class="fas fa-location-dot"></i></div>
            <div>
                <div class="loc-title-text"><span>Delivering to: <strong><?= $address_title ?></strong></span></div>
                <div class="loc-sub-address"><?= $full_address_text ?></div>
            </div>
        </div>
        <a href="index.php?page=profile" class="btn-change-address">Change</a>
    </div>

    <!-- Search Bar -->
    <div class="med-search-bar">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="gSearchInput" oninput="filterGroceries()" placeholder="Search groceries by name or description...">
    </div>

    <div style="font-size:18px; font-weight:800; color:#0D47A1; margin-bottom:16px;">
        <i class="fas fa-shopping-basket" style="margin-right:6px;"></i> Available Groceries
    </div>

    <div class="med-grid" id="groceryContainerGrid">
        <?php if ($groceries_result && $groceries_result->num_rows > 0): ?>
            <?php while($g = $groceries_result->fetch_assoc()): 
                $img = !empty($g['image_url']) ? $g['image_url'] : 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=300';
                $safeName = htmlspecialchars($g['name'], ENT_QUOTES);
            ?>
                <div class="med-item-card" data-gname="<?= htmlspecialchars(strtolower($g['name'])) ?>" data-gdesc="<?= htmlspecialchars(strtolower($g['description'])) ?>">
                    <div class="med-img-box">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Grocery">
                    </div>
                    <div style="padding:15px; display:flex; flex-direction:column; justify-content:space-between; flex:1;">
                        <div>
                            <strong style="font-size:16px; color:#1e293b; display:block; margin-bottom:4px;"><?= htmlspecialchars($g['name']) ?></strong>
                            <p style="color:#64748b; font-size:12px; line-height:1.3; margin-bottom:10px;"><?= htmlspecialchars($g['description']) ?></p>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:17px; font-weight:900; color:#0D47A1;">₹<?= $g['price'] ?></span>
                            <div class="dynamic-action-box">
                                <button class="btn-add-cart" onclick="updateGroceryQty('<?= $safeName ?>', <?= $g['price'] ?>, 1)">ADD +</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; grid-column:1/-1; padding:45px 20px; color:#64748b; background:#fff; border-radius:20px; border:1px solid #e2e8f0;">
                <i class="fas fa-shopping-basket" style="font-size:42px; color:#cbd5e1; margin-bottom:12px;"></i>
                <h3 style="color:#1e293b; margin-bottom:6px;">No Groceries Available!</h3>
                <p style="font-size:13px;">No grocery items found in the inventory right now.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function getCartData() {
    return JSON.parse(localStorage.getItem('qhp_cart')) || [];
}

function saveCartData(cart) {
    localStorage.setItem('qhp_cart', JSON.stringify(cart));
    if (typeof updateGlobalNavCartBadge === 'function') {
        updateGlobalNavCartBadge();
    }
    renderGroceryButtons();
}

function updateGroceryQty(gName, gPrice, change) {
    let cart = getCartData();
    let existing = cart.find(i => i.name === gName);

    if (existing) {
        existing.qty += change;
        if (existing.qty <= 0) {
            cart = cart.filter(i => i.name !== gName);
        }
    } else if (change > 0) {
        cart.push({
            name: gName,
            price: Number(gPrice),
            qty: 1,
            type: 'grocery',
            distance_km: 1.0
        });
    }

    saveCartData(cart);
}

function renderGroceryButtons() {
    let cart = getCartData();
    const cards = document.querySelectorAll('.med-item-card');

    cards.forEach(card => {
        const nameEl = card.querySelector('strong');
        if (!nameEl) return;
        const gName = nameEl.innerText.trim();
        const priceText = card.querySelector('span[style*="color:#0D47A1"]').innerText.replace('₹', '');
        const gPrice = Number(priceText);
        
        let container = card.querySelector('.dynamic-action-box');
        if (!container) return;

        let cartItem = cart.find(i => i.name === gName);
        if (cartItem && cartItem.qty > 0) {
            container.innerHTML = `
                <div class="qty-stepper">
                    <button type="button" onclick="updateGroceryQty('${gName.replace(/'/g, "\\'")}', ${gPrice}, -1)">-</button>
                    <span>${cartItem.qty}</span>
                    <button type="button" onclick="updateGroceryQty('${gName.replace(/'/g, "\\'")}', ${gPrice}, 1)">+</button>
                </div>
            `;
        } else {
            container.innerHTML = `
                <button class="btn-add-cart" onclick="updateGroceryQty('${gName.replace(/'/g, "\\'")}', ${gPrice}, 1)">ADD +</button>
            `;
        }
    });
}

function filterGroceries() {
    const query = document.getElementById('gSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.med-item-card');

    cards.forEach(card => {
        const name = card.dataset.gname || '';
        const desc = card.dataset.gdesc || '';
        if (name.includes(query) || desc.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    renderGroceryButtons();
});
</script>
<?php
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='index.php?page=login';</script>"; exit(); }

$user_id = $_SESSION['user_id'];
$selected_rest_id = isset($_GET['rest_id']) ? intval($_GET['rest_id']) : 0;

// Fetch Customer Primary Location Safely
$primary = null;
$check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id AND is_primary = 1 LIMIT 1");
if (!$check_addr || $check_addr->num_rows === 0) {
    $check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
}
if ($check_addr && $check_addr->num_rows > 0) {
    $primary = $check_addr->fetch_assoc();
}

$cust_lat = 16.8282;
if ($primary && isset($primary['lat'])) {
    $cust_lat = floatval($primary['lat']);
}

$cust_lng = 81.8961;
if ($primary && isset($primary['lng'])) {
    $cust_lng = floatval($primary['lng']);
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

// 1. Fetch Nearby Restaurants with Distance (KM)
$rest_sql = "
    SELECT r.id, r.name, r.image_url, r.address, r.lat, r.lng, r.range_km, r.rating,
    ( 6371 * acos( cos( radians($cust_lat) ) * cos( radians( r.lat ) ) 
    * cos( radians( r.lng ) - radians($cust_lng) ) + sin( radians($cust_lat) ) 
    * sin( radians( r.lat ) ) ) ) AS distance_km
    FROM restaurants r
    WHERE r.is_active = 1
    HAVING distance_km <= r.range_km
    ORDER BY distance_km ASC
";
$restaurants = $conn->query($rest_sql);

// 2. Fetch All Available Dishes with Distance (KM)
$all_dishes_sql = "
    SELECT f.*, r.name as restaurant_name, r.id as rest_id, r.range_km,
    ( 6371 * acos( cos( radians($cust_lat) ) * cos( radians( r.lat ) ) 
    * cos( radians( r.lng ) - radians($cust_lng) ) + sin( radians($cust_lat) ) 
    * sin( radians( r.lat ) ) ) ) AS distance_km
    FROM food_items f
    JOIN restaurants r ON f.restaurant_id = r.id
    WHERE f.is_available = 1 AND r.is_active = 1
    HAVING distance_km <= r.range_km
    ORDER BY distance_km ASC
";
$all_dishes = $conn->query($all_dishes_sql);

// 3. Selected Restaurant Details
$selected_restaurant = null;
$menu_items = null;
if ($selected_rest_id > 0) {
    $selected_restaurant = $conn->query("
        SELECT r.*, 
        ( 6371 * acos( cos( radians($cust_lat) ) * cos( radians( r.lat ) ) 
        * cos( radians( r.lng ) - radians($cust_lng) ) + sin( radians($cust_lat) ) 
        * sin( radians( r.lat ) ) ) ) AS distance_km
        FROM restaurants r WHERE r.id = $selected_rest_id
    ")->fetch_assoc();
    $menu_items = $conn->query("SELECT * FROM food_items WHERE restaurant_id = $selected_rest_id AND is_available = 1");
}
?>

<style>
    .food-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; }
    .location-card-box {
        background: #ffffff; border-radius: 18px; padding: 14px 18px;
        border: 1.5px solid #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        display: flex; align-items: center; justify-content: space-between;
        gap: 15px; margin-bottom: 16px;
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
    .food-search-bar {
        background: #ffffff; border-radius: 16px; padding: 6px 18px;
        display: flex; align-items: center; gap: 12px;
        border: 1.5px solid #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 22px;
    }
    .food-search-bar i { color: #0D47A1; font-size: 16px; }
    .food-search-bar input { border: none; outline: none; width: 100%; padding: 10px 0; font-size: 14px; font-weight: 600; color: #1e293b; }
    .circle-category-slider { display: flex; gap: 18px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 25px; scrollbar-width: none; }
    .circle-category-slider::-webkit-scrollbar { display: none; }
    .category-circle-item { display: flex; flex-direction: column; align-items: center; cursor: pointer; flex-shrink: 0; }
    .circle-img-box { width: 75px; height: 75px; border-radius: 50%; overflow: hidden; border: 2.5px solid #e2e8f0; background: #f8fafc; margin-bottom: 8px; }
    .circle-img-box img { width: 100%; height: 100%; object-fit: cover; }
    .circle-title { font-size: 12px; font-weight: 800; color: #334155; text-align: center; }
    .dishes-search-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; margin-bottom: 30px; }
    .dish-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
    .dish-card-img-box { height: 140px; width: 100%; position: relative; background: #cbd5e1; }
    .dish-card-img-box img { width: 100%; height: 100%; object-fit: cover; }
    .dish-rest-badge { position: absolute; bottom: 10px; left: 10px; background: rgba(13, 71, 161, 0.9); color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; }
    .restaurants-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px; }
    .restaurant-card { background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; display: block; transition: transform 0.2s ease; }
    .restaurant-card:hover { transform: translateY(-4px); border-color: #0D47A1; }
    .rest-cover-img { height: 165px; width: 100%; position: relative; background: #cbd5e1; }
    .rest-cover-img img { width: 100%; height: 100%; object-fit: cover; }
    .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
    .menu-item-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 16px; display: flex; justify-content: space-between; gap: 15px; }
    .btn-add-cart { background: #FF9800; color: #ffffff; border: none; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; cursor: pointer; }
</style>

<div class="food-wrapper">
    <!-- Location Card -->
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

    <?php if ($selected_rest_id === 0): ?>
        <!-- Search Bar -->
        <div class="food-search-bar">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="globalFoodSearch" oninput="handleGlobalFoodSearch()" placeholder="Search biryani, dosa, pizza, or restaurant...">
        </div>

        <!-- Categories Slider -->
        <div style="font-size:16px; font-weight:800; color:#0D47A1; margin-bottom:14px;">What's on your mind?</div>
        <div class="circle-category-slider">
            <div class="category-circle-item" onclick="triggerCategorySearch('all', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300" alt="All"></div>
                <span class="circle-title">All Foods</span>
            </div>
            <div class="category-circle-item" onclick="triggerCategorySearch('biryani', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=300" alt="Biryani"></div>
                <span class="circle-title">Biryani</span>
            </div>
            <div class="category-circle-item" onclick="triggerCategorySearch('tiffins', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1668236543090-82eba5ee5976?w=300" alt="Tiffins"></div>
                <span class="circle-title">Tiffins</span>
            </div>
            <div class="category-circle-item" onclick="triggerCategorySearch('haleem', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=300" alt="Haleem"></div>
                <span class="circle-title">Haleem</span>
            </div>
            <div class="category-circle-item" onclick="triggerCategorySearch('burger', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=300" alt="Fast Foods"></div>
                <span class="circle-title">Fast Foods</span>
            </div>
            <div class="category-circle-item" onclick="triggerCategorySearch('pizza', this)">
                <div class="circle-img-box"><img src="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=300" alt="Pizza"></div>
                <span class="circle-title">Pizza</span>
            </div>
        </div>

        <!-- SEARCH RESULTS SECTION FOR MATCHING DISHES -->
        <div id="dishesSearchSection" style="display: none; margin-bottom: 25px;">
            <div style="font-size:18px; font-weight:800; color:#0D47A1; margin-bottom:16px;">
                <i class="fas fa-utensils" style="margin-right:8px;"></i> Search Matching Dishes
            </div>
            <div class="dishes-search-grid" id="dishesContainer">
                <?php if ($all_dishes && $all_dishes->num_rows > 0): ?>
                    <?php while($d = $all_dishes->fetch_assoc()): ?>
                        <div class="dish-card" 
                             data-dishname="<?= htmlspecialchars(strtolower($d['item_name'])) ?>" 
                             data-category="<?= htmlspecialchars(strtolower($d['category'])) ?>"
                             data-restname="<?= htmlspecialchars(strtolower($d['restaurant_name'])) ?>">
                            <div class="dish-card-img-box">
                                <img src="<?= htmlspecialchars($d['image_url']) ?>" alt="Dish">
                                <span class="dish-rest-badge"><i class="fas fa-store"></i> <?= htmlspecialchars($d['restaurant_name']) ?></span>
                            </div>
                            <div style="padding:15px; display:flex; flex-direction:column; justify-content:space-between; flex:1;">
                                <div>
                                    <strong style="font-size:16px; color:#1e293b; display:block; margin-bottom:4px;"><?= htmlspecialchars($d['item_name']) ?></strong>
                                    <div style="font-size:12px; color:#64748b;"><i class="fas fa-motorcycle"></i> <?= number_format($d['distance_km'], 1) ?> KM Away</div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                                    <span style="font-size:18px; font-weight:900; color:#0D47A1;">₹<?= $d['price'] ?></span>
                                    <button class="btn-add-cart" onclick="addToLocalStorageCart('<?= htmlspecialchars($d['item_name']) ?>', <?= $d['price'] ?>, <?= $d['rest_id'] ?>, <?= number_format($d['distance_km'], 1) ?>)">ADD +</button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RESTAURANTS SECTION -->
        <div id="restSectionTitle" style="font-size:18px; font-weight:800; color:#0D47A1; margin-bottom:16px;">
            <i class="fas fa-store" style="margin-right:8px;"></i> Restaurants Near You
        </div>

        <div class="restaurants-grid" id="restaurantsContainer">
            <?php if ($restaurants && $restaurants->num_rows > 0): ?>
                <?php while($r = $restaurants->fetch_assoc()): ?>
                    <a href="index.php?page=food&rest_id=<?= $r['id'] ?>" class="restaurant-card" 
                       data-name="<?= htmlspecialchars(strtolower($r['name'])) ?>" 
                       data-address="<?= htmlspecialchars(strtolower($r['address'])) ?>">
                        <div class="rest-cover-img">
                            <img src="<?= htmlspecialchars($r['image_url']) ?>" alt="Restaurant">
                            <span style="position:absolute; bottom:12px; left:12px; background:rgba(13,71,161,0.9); color:#fff; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800;"><i class="fas fa-motorcycle"></i> <?= number_format($r['distance_km'], 1) ?> KM Away</span>
                        </div>
                        <div style="padding:18px;">
                            <div style="font-size:18px; font-weight:800; color:#1e293b; margin-bottom:4px;"><?= htmlspecialchars($r['name']) ?></div>
                            <div style="font-size:13px; color:#64748b;"><i class="fas fa-map-marker-alt" style="color:#94a3b8;"></i> <?= htmlspecialchars($r['address']) ?></div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; grid-column:1/-1; padding:45px 20px; color:#64748b; background:#fff; border-radius:20px; border:1px solid #e2e8f0;">
                    <i class="fas fa-store-slash" style="font-size:42px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <h3 style="color:#1e293b; margin-bottom:6px;">No Restaurants Delivering Here!</h3>
                    <p style="font-size:13px;">There are no active restaurants within your delivery address range radius circle.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- VIEW 2: SELECTED RESTAURANT MENU ITEMS -->
        <a href="index.php?page=food" style="display:inline-flex; align-items:center; gap:6px; color:#0D47A1; font-weight:800; text-decoration:none; margin-bottom:16px; font-size:14px;">
            <i class="fas fa-arrow-left"></i> Back to Restaurants
        </a>

        <?php if ($selected_restaurant): ?>
            <div style="background:#fff; border-radius:20px; padding:22px; border:1px solid #e2e8f0; margin-bottom:25px; display:flex; gap:20px; align-items:center;">
                <img src="<?= htmlspecialchars($selected_restaurant['image_url']) ?>" style="width:100px; height:100px; border-radius:16px; object-fit:cover;" alt="Cover">
                <div>
                    <h2 style="color:#1e293b; font-weight:800; font-size:22px; margin-bottom:4px;"><?= htmlspecialchars($selected_restaurant['name']) ?></h2>
                    <div style="color:#64748b; font-size:13px; margin-bottom:8px;"><i class="fas fa-location-dot" style="color:#FF9800;"></i> <?= htmlspecialchars($selected_restaurant['address']) ?></div>
                    <span style="background:#f0fdf4; color:#166534; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:800;">
                        <i class="fas fa-star" style="color:#eab308;"></i> <?= $selected_restaurant['rating'] ?> Rating | <?= number_format($selected_restaurant['distance_km'], 1) ?> KM Away
                    </span>
                </div>
            </div>

            <div class="menu-grid">
                <?php if ($menu_items && $menu_items->num_rows > 0): ?>
                    <?php while($item = $menu_items->fetch_assoc()): ?>
                        <div class="menu-item-card">
                            <div style="display:flex; flex-direction:column; justify-content:space-between; flex:1;">
                                <div>
                                    <strong style="color:#1e293b; font-size:16px; display:block; margin-bottom:4px;"><?= htmlspecialchars($item['item_name']) ?></strong>
                                    <p style="color:#64748b; font-size:12px; line-height:1.3;"><?= htmlspecialchars($item['description']) ?></p>
                                </div>
                                <div>
                                    <div style="color:#0D47A1; font-size:18px; font-weight:900; margin-top:8px;">₹<?= $item['price'] ?></div>
                                    <button class="btn-add-cart" onclick="addToLocalStorageCart('<?= htmlspecialchars($item['item_name']) ?>', <?= $item['price'] ?>, <?= $selected_restaurant['id'] ?>, <?= number_format($selected_restaurant['distance_km'], 1) ?>)">ADD +</button>
                                </div>
                            </div>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" style="width:90px; height:90px; border-radius:14px; object-fit:cover;" alt="Food">
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const customerLat = <?= $cust_lat ?>;
const customerLng = <?= $cust_lng ?>;
const customerAddress = "<?= addslashes($raw_address ?: 'Primary Location') ?>";

function addToLocalStorageCart(itemName, itemPrice, restId, distanceKm) {
    let cart = JSON.parse(localStorage.getItem('qhp_cart')) || [];
    
    let existingItem = cart.find(i => i.name === itemName);
    if (existingItem) {
        existingItem.qty += 1;
    } else {
        cart.push({
            name: itemName,
            price: itemPrice,
            rest_id: restId,
            distance_km: distanceKm,
            qty: 1,
            lat: customerLat,
            lng: customerLng,
            address: customerAddress
        });
    }

    localStorage.setItem('qhp_cart', JSON.stringify(cart));
    if (typeof updateGlobalNavCartBadge === 'function') {
        updateGlobalNavCartBadge();
    }
}

function handleGlobalFoodSearch() {
    const searchInput = document.getElementById('globalFoodSearch');
    if (!searchInput) return;

    const query = searchInput.value.toLowerCase().trim();
    const dishesSection = document.getElementById('dishesSearchSection');
    const dishCards = document.querySelectorAll('.dish-card');
    const restCards = document.querySelectorAll('.restaurant-card');

    if (query === '') {
        dishesSection.style.display = 'none';
        restCards.forEach(card => card.style.display = 'block');
        return;
    }

    let matchingDishesCount = 0;
    dishCards.forEach(card => {
        const name = (card.dataset.dishname || '').toLowerCase();
        const category = (card.dataset.category || '').toLowerCase();
        const restaurant = (card.dataset.restname || '').toLowerCase();

        if (name.includes(query) || category.includes(query) || restaurant.includes(query)) {
            card.style.display = 'flex';
            matchingDishesCount++;
        } else {
            card.style.display = 'none';
        }
    });

    if (matchingDishesCount > 0) {
        dishesSection.style.display = 'block';
    } else {
        dishesSection.style.display = 'none';
    }

    restCards.forEach(card => {
        const name = (card.dataset.name || '').toLowerCase();
        const address = (card.dataset.address || '').toLowerCase();

        if (name.includes(query) || address.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function triggerCategorySearch(catName, element) {
    document.querySelectorAll('.category-circle-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');

    const searchInput = document.getElementById('globalFoodSearch');
    if (catName === 'all') {
        searchInput.value = '';
    } else {
        searchInput.value = catName;
    }
    handleGlobalFoodSearch();
}

document.addEventListener("DOMContentLoaded", function() {
    if (typeof updateGlobalNavCartBadge === 'function') {
        updateGlobalNavCartBadge();
    }
});
</script>
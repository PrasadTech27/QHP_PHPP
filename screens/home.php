<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit(); }

// Query All Subcategories Grouped by Category
$categories = [];
$query = "SELECT * FROM subcategories ORDER BY id ASC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Skip printing shops subcategory
        if (stripos($row['sub_category_name'], 'print') !== false) { continue; }
        $categories[$row['category_name']][] = $row;
    }
}
?>

<style>
    /* Overall Page Background Contrast & Smooth Font Styling */
    body {
        background: #f0f4f8 !important; /* Premium Soft Slate Contrast */
    }

    /* Premium Hero Cards Grid Layout */
    .hero-cards-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 35px;
    }
    
    .hero-card {
        color: #ffffff;
        padding: 28px 20px;
        border-radius: 26px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        text-decoration: none;
        box-shadow: 0 12px 30px rgba(13, 71, 161, 0.12);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        min-height: 175px;
        text-align: center;
        position: relative;
        overflow: hidden;
        background-size: 110%;
        background-position: center;
        border: 2px solid rgba(255, 255, 255, 0.4);
    }

    /* Dynamic Zoom & Glow Effect on Hover */
    .hero-card:hover { 
        transform: translateY(-8px) scale(1.03); 
        box-shadow: 0 20px 40px rgba(13, 71, 161, 0.25);
        background-size: 122%;
        border-color: rgba(255, 255, 255, 0.8);
    }

    /* Glassmorphic Indian Dark Vignette Overlay */
    .hero-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.2) 0%, rgba(15, 23, 42, 0.88) 100%);
        z-index: 1;
        transition: background 0.3s ease;
    }
    .hero-card:hover::before {
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.1) 0%, rgba(15, 23, 42, 0.95) 100%);
    }

    /* Floating Frosted Glass Icon Badge */
    .icon-badge {
        position: relative;
        z-index: 2;
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        border: 1.5px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        transition: all 0.35s ease;
    }
    .hero-card:hover .icon-badge {
        transform: scale(1.15) rotate(6deg);
        background: rgba(255, 255, 255, 0.35);
    }

    .icon-badge i { 
        font-size: 26px; 
        color: #ffffff;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));
    }
    
    .hero-card h3 { 
        position: relative;
        z-index: 2;
        font-size: 19px; 
        font-weight: 800; 
        letter-spacing: -0.3px; 
        text-shadow: 0 3px 10px rgba(0, 0, 0, 0.8);
    }

    /* High-Quality Authentic Indian Background Images */
    .card-food { 
        background-image: url('https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?q=80&w=800&auto=format&fit=crop'); 
    }
    .card-medicine { 
        background-image: url('https://images.unsplash.com/photo-1471864190281-a93a3070b6de?q=80&w=800&auto=format&fit=crop'); 
    }
    .card-grocery { 
        background-image: url('https://images.unsplash.com/photo-1610348725531-843dff563e2c?q=80&w=800&auto=format&fit=crop'); 
    }
    .card-parcel { 
        background-image: url('https://images.unsplash.com/photo-1580674285054-bed31e145f59?q=80&w=800&auto=format&fit=crop'); 
    }

    /* Categories Directory Section */
    .directory-container { width: 100%; margin-top: 10px; }
    .directory-header {
        font-size: 22px;
        font-weight: 800;
        color: #0D47A1;
        margin-bottom: 22px;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .directory-header::before {
        content: '';
        width: 6px;
        height: 26px;
        background: #0D47A1;
        border-radius: 4px;
        display: inline-block;
    }

    /* Premium White Accordion Cards popping over Soft Background */
    .tab-accordion {
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        margin-bottom: 18px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 8px 25px rgba(15, 23, 42, 0.05);
        transition: all 0.3s ease;
    }
    .tab-accordion-header {
        padding: 22px 26px;
        font-weight: 800;
        font-size: 18px;
        color: #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
        background: #ffffff;
        transition: background 0.2s ease;
    }
    .tab-accordion-header:hover { background: #f8fafc; color: #0D47A1; }
    .tab-accordion-header i.chevron { transition: transform 0.3s ease; color: #94a3b8; font-size: 18px; }
    
    /* Glowing Active Border Line */
    .tab-accordion.active { 
        border-color: #0D47A1; 
        border-left: 8px solid #0D47A1; 
        box-shadow: 0 14px 35px rgba(13, 71, 161, 0.15); 
    }
    .tab-accordion.active .tab-accordion-header { background: #f0f6ff; color: #0D47A1; }
    .tab-accordion.active i.chevron { transform: rotate(180deg); color: #0D47A1; }

    .tab-accordion-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0, 1, 0, 1), padding 0.3s ease;
        padding: 0 26px;
        background: #ffffff;
    }
    .tab-accordion.active .tab-accordion-body {
        padding: 24px 26px;
        max-height: 1200px;
        border-top: 1px dashed #cbd5e1;
    }

    /* Subcategory Chips Grid */
    .subcat-chips-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }
    .subcat-chip {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        padding: 16px;
        border-radius: 16px;
        text-decoration: none;
        color: #334155;
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.25s ease;
    }
    .subcat-chip:hover {
        background: #ffffff;
        border-color: #0D47A1;
        color: #0D47A1;
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(13, 71, 161, 0.12);
    }
    .subcat-chip i { 
        font-size: 20px; 
        color: #0D47A1; 
        background: #eaf2ff; 
        width: 42px; 
        height: 42px; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center;
    }
</style>

<!-- Hero Cards Grid with Indian Specific Images -->
<div class="hero-cards-grid">
    <a href="index.php?page=food" class="hero-card card-food">
        <div class="icon-badge"><i class="fas fa-utensils"></i></div>
        <h3>Food Services</h3>
    </a>
    <a href="index.php?page=medicines" class="hero-card card-medicine">
        <div class="icon-badge"><i class="fas fa-pills"></i></div>
        <h3>Medicines</h3>
    </a>
    <a href="index.php?page=grocery" class="hero-card card-grocery">
        <div class="icon-badge"><i class="fas fa-shopping-cart"></i></div>
        <h3>Grocery Hub</h3>
    </a>
    <a href="index.php?page=parcel" class="hero-card card-parcel">
        <div class="icon-badge"><i class="fas fa-box"></i></div>
        <h3>Express Parcel</h3>
    </a>
</div>

<!-- Dynamic Expanded Directory -->
<div class="directory-container">
    <div class="directory-header">Categories & Services</div>

    <?php foreach ($categories as $cat_name =>$subs): ?>
        <div class="tab-accordion" id="tab-<?= md5($cat_name) ?>">
            <div class="tab-accordion-header" onclick="toggleTabAccordion('tab-<?= md5($cat_name) ?>')">
                <span><i class="fas fa-layer-group" style="margin-right: 12px; color:#0D47A1;"></i> <?= htmlspecialchars($cat_name) ?></span>
                <i class="fas fa-chevron-down chevron"></i>
            </div>
            <div class="tab-accordion-body">
                <div class="subcat-chips-grid">
                    <?php foreach ($subs as $sub): ?>
                        <?php 
                            $target_page = 'services';
                            $sub_name = $sub['sub_category_name'];
                            if (in_array($cat_name, ['Food', 'Restaurants']) || $sub_name === 'Food Services') $target_page = 'food';
                            else if ($cat_name === 'Grocery' && $sub_name !== 'Fancy Stores') $target_page = 'grocery';
                            else if ($cat_name === 'Medicines' || $sub_name === 'Medical Stores') $target_page = 'medicines';
                            else if ($cat_name === 'Parcel' || $sub_name === 'Pickup and Delivery Services') $target_page = 'parcel';
                            else if ($sub_name === 'Lodges') $target_page = 'lodges';
                            else if ($sub_name === 'Car Rentals') $target_page = 'car_rentals';
                            else if ($sub_name === 'Trip Planners') $target_page = 'trip_planners';
                            else if ($sub_name === 'Tourist Guiders' || $sub_name === 'Tourist Guides') $target_page = 'tourist_guides';
                            else if ($sub_name === 'Fancy Stores') $target_page = 'fancy_stores';
                        ?>
                        <a href="index.php?page=<?= $target_page ?>&sub=<?= urlencode($sub_name) ?>" class="subcat-chip">
                            <i class="fas <?= $sub['icon'] ?>"></i>
                            <span><?= htmlspecialchars($sub_name) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function toggleTabAccordion(tabId) {
    const targetTab = document.getElementById(tabId);
    const isActive = targetTab.classList.contains('active');
    
    document.querySelectorAll('.tab-accordion').forEach(tab => {
        tab.classList.remove('active');
    });

    if (!isActive) {
        targetTab.classList.add('active');
    }
}
</script>
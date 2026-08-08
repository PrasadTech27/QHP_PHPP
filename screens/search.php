<?php
require_once __DIR__ . '/../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - QHP Super App</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Adjust path as needed -->
</head>
<body>
    <div class="container">
        <header>
            <h2>Search Services & Products</h2>
        </header>
        <form action="search_results.php" method="GET" class="search-form">
            <div class="input-group">
                <input type="text" name="query" placeholder="Search for food, grocery, hotels, services..." required autocomplete="off">
                <button type="submit" class="btn-primary">Search</button>
            </div>
        </form>
        <div class="recent-searches">
            <h3>Popular Categories</h3>
            <ul>
                <li><a href="subcategory_list.php?cat_id=1">Food Delivery</a></li>
                <li><a href="subcategory_list.php?cat_id=2">Groceries</a></li>
                <li><a href="subcategory_list.php?cat_id=3">Hotels & Lodging</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
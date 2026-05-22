<?php
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();
$products = [];
try {
    $products = getProducts($db, 8);
} catch (Throwable $e) {
    $products = [];
}

$sampleProducts = [
    ['name' => 'Dream Pro Jersey', 'description' => 'Official community jersey with a clean esports fit.', 'price' => '$29', 'image' => 'assets/images/apps/app1.jpg', 'tag' => 'Popular'],
    ['name' => 'Creator Stream Deck', 'description' => 'Quick controls for streams, posts, and tournament moments.', 'price' => '$79', 'image' => 'assets/images/apps/app2.jpg', 'tag' => 'Creator'],
    ['name' => 'Tournament Pass', 'description' => 'Priority access to featured competitions and rewards.', 'price' => '$12', 'image' => 'assets/images/apps/app3.jpg', 'tag' => 'Events'],
];
?>

<div class="dream-market-page">
    <section class="dream-market-hero dream-market-hero--products">
        <div>
            <span class="home-rail-kicker">Products</span>
            <h1>DreamBD Store</h1>
            <p>Browse community products, merch, tools, and future marketplace items from one polished page.</p>
        </div>
        <a href="index.php?page=cart" class="btn btn-primary" data-page="cart"><i class="fas fa-cart-shopping"></i> Open cart</a>
    </section>

    <section class="dream-market-grid">
        <?php if ($products): ?>
            <?php foreach ($products as $product): ?>
                <?php
                $name = $product['name'] ?? $product['title'] ?? 'DreamBD Product';
                $description = $product['description'] ?? $product['short_description'] ?? 'A featured DreamBD marketplace item.';
                $price = isset($product['price']) ? '$' . number_format((float) $product['price'], 2) : 'View price';
                $image = $product['image'] ?? $product['image_path'] ?? '';
                $imageSrc = $image ? (str_starts_with($image, 'assets/') ? $image : 'assets/images/products/' . $image) : 'assets/images/apps/app1.jpg';
                ?>
                <article class="dream-market-card">
                    <div class="dream-market-card-media">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy" onerror="this.src='assets/images/apps/app1.jpg'">
                    </div>
                    <div class="dream-market-card-copy">
                        <span>Available now</span>
                        <h2><?php echo htmlspecialchars($name); ?></h2>
                        <p><?php echo htmlspecialchars($description); ?></p>
                        <div class="dream-market-card-bottom">
                            <strong><?php echo htmlspecialchars($price); ?></strong>
                            <button type="button" class="btn btn-outline btn-sm">Details</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($sampleProducts as $product): ?>
                <article class="dream-market-card">
                    <div class="dream-market-card-media">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy" onerror="this.src='assets/images/apps/app1.jpg'">
                    </div>
                    <div class="dream-market-card-copy">
                        <span><?php echo htmlspecialchars($product['tag']); ?></span>
                        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                        <p><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="dream-market-card-bottom">
                            <strong><?php echo htmlspecialchars($product['price']); ?></strong>
                            <button type="button" class="btn btn-outline btn-sm">Preview</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

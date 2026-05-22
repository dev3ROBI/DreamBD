<?php
require_once __DIR__ . '/../includes/functions.php';

$viewerId = $_SESSION['user_id'] ?? null;
$db = Database::getInstance()->getConnection();
ensureSocialTables($db);

$query = trim((string) ($_GET['q'] ?? ''));
$activeTab = $_GET['tab'] ?? 'all';
if (!in_array($activeTab, ['all', 'people', 'posts'], true)) {
    $activeTab = 'all';
}

$results = getSearchResults($db, $viewerId ? (int) $viewerId : null, $query, $activeTab, 12, 12);
?>

<div class="search-page">
    <section class="search-shell social-card">
        <div class="search-header">
            <div class="search-header-copy">
                <span class="social-kicker">Search</span>
                <h1>Search results</h1>
                <p>Find people and visible posts across DreamBD in a cleaner Facebook-style results page.</p>
            </div>
            <form class="search-header-form" action="index.php" method="get">
                <input type="hidden" name="page" value="search">
                <div class="search-header-input">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search people, posts, topics..." autocomplete="off">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>

        <div class="search-tabs">
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=all" class="search-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">
                <span>All</span>
                <strong><?php echo (int) ($results['counts']['all'] ?? 0); ?></strong>
            </a>
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=people" class="search-tab <?php echo $activeTab === 'people' ? 'active' : ''; ?>">
                <span>People</span>
                <strong><?php echo (int) ($results['counts']['people'] ?? 0); ?></strong>
            </a>
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=posts" class="search-tab <?php echo $activeTab === 'posts' ? 'active' : ''; ?>">
                <span>Posts</span>
                <strong><?php echo (int) ($results['counts']['posts'] ?? 0); ?></strong>
            </a>
        </div>

        <?php if ($query === ''): ?>
        <div class="social-empty search-empty-state">
            <i class="fas fa-magnifying-glass"></i>
            <h3>Start searching</h3>
            <p>Look for people, post topics, and conversations across DreamBD.</p>
        </div>
        <?php else: ?>
        <div class="search-layout">
            <aside class="search-sidebar">
                <div class="search-side-card">
                    <span class="search-side-kicker">Query</span>
                    <h3><?php echo htmlspecialchars($query); ?></h3>
                    <p><?php echo (int) ($results['counts']['all'] ?? 0); ?> total matches across people and posts.</p>
                </div>
                <div class="search-side-card">
                    <span class="search-side-kicker">Breakdown</span>
                    <div class="search-side-stats">
                        <div><strong><?php echo (int) ($results['counts']['people'] ?? 0); ?></strong><span>People</span></div>
                        <div><strong><?php echo (int) ($results['counts']['posts'] ?? 0); ?></strong><span>Posts</span></div>
                    </div>
                </div>
            </aside>

            <div class="search-results-stack">
                <?php if (!empty($results['users'])): ?>
                <section class="search-results-card">
                    <div class="search-results-card-header">
                        <h2>People</h2>
                    </div>
                    <div class="search-people-list">
                        <?php foreach ($results['users'] as $user): ?>
                        <a href="index.php?page=profile&user=<?php echo (int) $user['id']; ?>" class="search-person-row" data-no-ajax>
                            <img src="assets/avatars/<?php echo htmlspecialchars($user['avatar'] ?: 'default.png'); ?>" alt="<?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>" onerror="this.src='assets/avatars/default.png'">
                            <span>
                                <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                <em><?php echo htmlspecialchars($user['location'] ?: ($user['bio'] ?: 'Member of DreamBD')); ?></em>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($results['posts'])): ?>
                <section class="search-results-card">
                    <div class="search-results-card-header">
                        <h2>Posts</h2>
                    </div>
                    <div class="search-post-list">
                        <?php foreach ($results['posts'] as $post): ?>
                        <a href="index.php?page=community&post=<?php echo (int) $post['id']; ?>#post-<?php echo (int) $post['id']; ?>" class="search-post-row" data-no-ajax>
                            <div class="search-post-author">
                                <img src="assets/avatars/<?php echo htmlspecialchars($post['avatar'] ?: 'default.png'); ?>" alt="<?php echo htmlspecialchars($post['full_name'] ?: $post['username']); ?>" onerror="this.src='assets/avatars/default.png'">
                                <span>
                                    <strong><?php echo htmlspecialchars($post['full_name'] ?: $post['username']); ?></strong>
                                    <em><?php echo htmlspecialchars($post['created_at_formatted']); ?></em>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars($post['content_excerpt']); ?></p>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (empty($results['users']) && empty($results['posts'])): ?>
                <div class="social-empty search-empty-state">
                    <i class="fas fa-face-frown"></i>
                    <h3>No results found</h3>
                    <p>Try a different name, keyword, or post topic.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

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
<style>
.sr-page { max-width: 900px; margin: 0 auto; padding: 0 16px 2rem; }
.sr-card { background: rgba(255,255,255,0.78); backdrop-filter: blur(12px); border: 1px solid rgba(226,232,240,0.8); border-radius: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.04); padding: 1.5rem; }
.dark .sr-card { background: rgba(15,23,42,0.7); border-color: rgba(51,65,85,0.6); }
.sr-header { text-align: center; padding: 1rem 0 0.5rem; }
.sr-header h1 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem; }
.dark .sr-header h1 { color: #f1f5f9; }
.sr-header p { font-size: 0.85rem; color: #64748b; margin: 0 0 1.2rem; }
.sr-header .sr-kicker { display: inline-block; padding: 0.2rem 0.7rem; border-radius: 999px; background: linear-gradient(135deg,rgba(124,58,237,0.1),rgba(37,99,235,0.1)); color: #7c3aed; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.sr-search-form { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.7rem; border-radius: 999px; border: 1.5px solid rgba(148,163,184,0.2); background: rgba(255,255,255,0.6); backdrop-filter: blur(6px); transition: border-color .2s, box-shadow .2s; }
.sr-search-form:focus-within { border-color: #7c3aed; box-shadow: 0 0 0 4px rgba(124,58,237,0.1); }
.dark .sr-search-form { background: rgba(15,23,42,0.5); border-color: rgba(71,85,105,0.3); }
.sr-search-form i { color: #94a3b8; font-size: 0.95rem; flex-shrink: 0; }
.sr-search-form input { flex: 1; border: 0; outline: none; background: transparent; color: #0f172a; font-size: 0.9rem; min-width: 0; }
.dark .sr-search-form input { color: #f1f5f9; }
.sr-search-form button { border: 0; border-radius: 999px; padding: 0.5rem 1.2rem; background: linear-gradient(135deg,#7c3aed,#2563eb); color: #fff; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: transform .15s, box-shadow .15s; flex-shrink: 0; }
.sr-search-form button:hover { transform: scale(1.03); box-shadow: 0 4px 16px rgba(124,58,237,0.2); }

.sr-tabs { display: flex; gap: 6px; padding: 0 4px; margin: 1rem 0; }
.sr-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 0.6rem 0.5rem; border-radius: 14px; border: 1px solid rgba(148,163,184,0.15); background: rgba(255,255,255,0.4); color: #64748b; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: all .2s; }
.dark .sr-tab { background: rgba(15,23,42,0.4); border-color: rgba(71,85,105,0.2); color: #94a3b8; }
.sr-tab.active { background: linear-gradient(135deg,#7c3aed,#2563eb); color: #fff; border-color: transparent; box-shadow: 0 2px 12px rgba(124,58,237,0.25); }
.sr-tab.active strong { color: rgba(255,255,255,0.85); }
.sr-tab strong { font-size: 0.75rem; background: rgba(148,163,184,0.12); padding: 0.1rem 0.5rem; border-radius: 999px; color: #64748b; }

.sr-empty { text-align: center; padding: 3rem 1rem; }
.sr-empty i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.8rem; display: block; }
.dark .sr-empty i { color: #334155; }
.sr-empty h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.3rem; }
.dark .sr-empty h3 { color: #f1f5f9; }
.sr-empty p { font-size: 0.85rem; color: #64748b; margin: 0; }

.sr-layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; margin-top: 0.5rem; }
.sr-sidebar { display: flex; flex-direction: column; gap: 10px; }
.sr-side-card { background: rgba(255,255,255,0.5); border-radius: 16px; padding: 1rem; border: 1px solid rgba(226,232,240,0.6); }
.dark .sr-side-card { background: rgba(15,23,42,0.4); border-color: rgba(51,65,85,0.5); }
.sr-side-kicker { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; background: rgba(124,58,237,0.08); color: #7c3aed; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.4rem; }
.sr-side-card h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem; word-break: break-word; }
.dark .sr-side-card h3 { color: #f1f5f9; }
.sr-side-card p { font-size: 0.78rem; color: #64748b; margin: 0; }
.sr-side-stats { display: flex; gap: 1rem; margin-top: 0.3rem; }
.sr-side-stats > div { flex: 1; }
.sr-side-stats strong { display: block; font-size: 1.2rem; font-weight: 800; color: #0f172a; }
.dark .sr-side-stats strong { color: #f1f5f9; }
.sr-side-stats span { font-size: 0.72rem; color: #64748b; }

.sr-results { display: flex; flex-direction: column; gap: 12px; }
.sr-section { background: rgba(255,255,255,0.5); border-radius: 16px; border: 1px solid rgba(226,232,240,0.6); overflow: hidden; }
.dark .sr-section { background: rgba(15,23,42,0.4); border-color: rgba(51,65,85,0.5); }
.sr-section-header { padding: 0.8rem 1rem; border-bottom: 1px solid rgba(226,232,240,0.5); }
.dark .sr-section-header { border-color: rgba(51,65,85,0.4); }
.sr-section-header h2 { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 6px; }
.dark .sr-section-header h2 { color: #f1f5f9; }
.sr-section-header h2 i { color: #7c3aed; font-size: 0.85rem; }

.sr-person { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1rem; text-decoration: none; transition: background .15s; }
.sr-person:hover { background: rgba(124,58,237,0.04); }
.dark .sr-person:hover { background: rgba(124,58,237,0.08); }
.sr-person img { width: 44px; height: 44px; border-radius: 14px; object-fit: cover; flex-shrink: 0; background: #e2e8f0; }
.sr-person span { min-width: 0; }
.sr-person strong { display: block; font-size: 0.85rem; font-weight: 700; color: #0f172a; }
.dark .sr-person strong { color: #f1f5f9; }
.sr-person em { display: block; font-size: 0.75rem; color: #64748b; font-style: normal; }

.sr-post { display: block; padding: 0.8rem 1rem; text-decoration: none; transition: background .15s; }
.sr-post:hover { background: rgba(124,58,237,0.04); }
.dark .sr-post:hover { background: rgba(124,58,237,0.08); }
.sr-post-author { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem; }
.sr-post-author img { width: 32px; height: 32px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: #e2e8f0; }
.sr-post-author strong { font-size: 0.82rem; font-weight: 700; color: #0f172a; }
.dark .sr-post-author strong { color: #f1f5f9; }
.sr-post-author em { font-size: 0.7rem; color: #94a3b8; font-style: normal; }
.sr-post p { font-size: 0.85rem; color: #475569; margin: 0; line-height: 1.5; }
.dark .sr-post p { color: #cbd5e1; }

@media (max-width: 768px) {
  .sr-page { padding: 0 12px 1.5rem; }
  .sr-card { padding: 1rem; border-radius: 20px; }
  .sr-layout { grid-template-columns: 1fr; }
  .sr-sidebar { flex-direction: row; gap: 8px; }
  .sr-side-card { flex: 1; padding: 0.8rem; }
  .sr-side-stats { gap: 0.5rem; }
  .sr-header h1 { font-size: 1.3rem; }
  .sr-tab { font-size: 0.75rem; padding: 0.5rem 0.3rem; }
  .sr-tab strong { font-size: 0.65rem; padding: 0.05rem 0.4rem; }
}
@media (max-width: 480px) {
  .sr-sidebar { flex-direction: column; }
  .sr-search-form { padding: 0.4rem 0.6rem; }
  .sr-search-form input { font-size: 0.85rem; }
  .sr-search-form button { padding: 0.4rem 0.8rem; font-size: 0.75rem; }
}
</style>

<div class="sr-page">
    <div class="sr-card">
        <div class="sr-header">
            <span class="sr-kicker">Search</span>
            <h1><?php echo $query ? 'Results for &ldquo;' . htmlspecialchars($query) . '&rdquo;' : 'Search DreamBD'; ?></h1>
            <p>Find people and visible posts across the community.</p>

            <form class="sr-search-form" action="index.php" method="get">
                <input type="hidden" name="page" value="search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search people, posts, topics..." autocomplete="off">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($query): ?>
        <div class="sr-tabs">
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=all" class="sr-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">
                <span>All</span>
                <strong><?php echo (int) ($results['counts']['all'] ?? 0); ?></strong>
            </a>
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=people" class="sr-tab <?php echo $activeTab === 'people' ? 'active' : ''; ?>">
                <span>People</span>
                <strong><?php echo (int) ($results['counts']['people'] ?? 0); ?></strong>
            </a>
            <a href="index.php?page=search&q=<?php echo urlencode($query); ?>&tab=posts" class="sr-tab <?php echo $activeTab === 'posts' ? 'active' : ''; ?>">
                <span>Posts</span>
                <strong><?php echo (int) ($results['counts']['posts'] ?? 0); ?></strong>
            </a>
        </div>

        <div class="sr-layout">
            <aside class="sr-sidebar">
                <div class="sr-side-card">
                    <span class="sr-side-kicker">Query</span>
                    <h3><?php echo htmlspecialchars($query); ?></h3>
                    <p><?php echo (int) ($results['counts']['all'] ?? 0); ?> total matches.</p>
                </div>
                <div class="sr-side-card">
                    <span class="sr-side-kicker">Breakdown</span>
                    <div class="sr-side-stats">
                        <div><strong><?php echo (int) ($results['counts']['people'] ?? 0); ?></strong><span>People</span></div>
                        <div><strong><?php echo (int) ($results['counts']['posts'] ?? 0); ?></strong><span>Posts</span></div>
                    </div>
                </div>
            </aside>

            <div class="sr-results">
                <?php if (!empty($results['users'])): ?>
                <div class="sr-section">
                    <div class="sr-section-header">
                        <h2><i class="fas fa-users"></i> People</h2>
                    </div>
                    <div>
                        <?php foreach ($results['users'] as $user): ?>
                        <a href="index.php?page=profile&user=<?php echo (int) $user['id']; ?>" class="sr-person" data-no-ajax>
                            <img src="assets/avatars/<?php echo htmlspecialchars($user['avatar'] ?: 'default.png'); ?>" alt="<?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>" onerror="this.src='assets/avatars/default.png'">
                            <span>
                                <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                <em><?php echo htmlspecialchars($user['location'] ?: ($user['bio'] ?: 'Member of DreamBD')); ?></em>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($results['posts'])): ?>
                <div class="sr-section">
                    <div class="sr-section-header">
                        <h2><i class="fas fa-file-lines"></i> Posts</h2>
                    </div>
                    <div>
                        <?php foreach ($results['posts'] as $post): ?>
                        <a href="index.php?page=community&post=<?php echo (int) $post['id']; ?>#post-<?php echo (int) $post['id']; ?>" class="sr-post" data-no-ajax>
                            <div class="sr-post-author">
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
                </div>
                <?php endif; ?>

                <?php if (empty($results['users']) && empty($results['posts'])): ?>
                <div class="sr-empty">
                    <i class="fas fa-face-frown"></i>
                    <h3>No results found</h3>
                    <p>Try a different name, keyword, or post topic.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="sr-empty">
            <i class="fas fa-magnifying-glass"></i>
            <h3>Start searching</h3>
            <p>Look for people, post topics, and conversations across DreamBD.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
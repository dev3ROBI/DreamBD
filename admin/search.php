<?php
$pageTitle = 'Search Admin';
$pageHeading = 'Global Search';
$currentPage = 'search';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Search']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$searchQuery = $_GET['q'] ?? '';
$searchResults = [];
$resultCount = 0;

if (!empty($searchQuery) && strlen($searchQuery) >= 2) {
    try {
        // Search users
        $stmt = $db->prepare("
            SELECT 'user' as type, id, username, full_name, email, registered_at
            FROM users 
            WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?
            LIMIT 10
        ");
        $searchTerm = "%$searchQuery%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $users = $stmt->fetchAll();
        $searchResults['users'] = $users;
        $resultCount += count($users);

        // Search posts
        $stmt = $db->prepare("
            SELECT 'post' as type, p.id, p.content, p.created_at, u.username
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.content LIKE ?
            LIMIT 10
        ");
        $stmt->execute([$searchTerm]);
        $posts = $stmt->fetchAll();
        $searchResults['posts'] = $posts;
        $resultCount += count($posts);

    } catch (PDOException $e) {
        $errors[] = 'Search error: ' . $e->getMessage();
    }
}
?>

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8 slide-in">
    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
        <i class="fas fa-search text-blue-500 mr-2"></i>Global Search
    </h2>

    <form method="GET" action="" class="mb-6">
        <div class="flex gap-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>"
                       class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white"
                       placeholder="Search users, posts, pages..." autofocus>
            </div>
            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Minimum 2 characters. Searches across users, posts, and content.</p>
    </form>

    <?php if (!empty($searchQuery)): ?>
    <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"
            </h3>
            <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo $resultCount; ?> results found</span>
        </div>

        <?php if ($resultCount === 0): ?>
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <i class="fas fa-search text-4xl mb-4 opacity-50"></i>
            <p>No results found. Try different keywords.</p>
        </div>
        <?php else: ?>

        <!-- User Results -->
        <?php if (!empty($searchResults['users'])): ?>
        <div class="mb-6">
            <h4 class="text-md font-semibold text-gray-800 dark:text-white mb-3">
                <i class="fas fa-users text-blue-500 mr-2"></i>Users (<?php echo count($searchResults['users']); ?>)
            </h4>
            <div class="space-y-2">
                <?php foreach ($searchResults['users'] as $user): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">@<?php echo htmlspecialchars($user['username']); ?> · <?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                    <a href="../profile.php?id=<?php echo $user['id']; ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        View Profile <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Post Results -->
        <?php if (!empty($searchResults['posts'])): ?>
        <div class="mb-6">
            <h4 class="text-md font-semibold text-gray-800 dark:text-white mb-3">
                <i class="fas fa-file-alt text-green-500 mr-2"></i>Posts (<?php echo count($searchResults['posts']); ?>)
            </h4>
            <div class="space-y-2">
                <?php foreach ($searchResults['posts'] as $post): ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <div class="flex items-center space-x-2 mb-2">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-xs text-gray-600 dark:text-gray-400">@<?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?></span>
                        <span class="text-xs text-gray-500 dark:text-gray-500">· <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?>...</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

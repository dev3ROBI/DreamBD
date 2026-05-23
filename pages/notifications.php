<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$viewerId = $_SESSION['user_id'] ?? null;

$db = Database::getInstance()->getConnection();
ensureSocialTables($db);
$activeFilter = $_GET['filter'] ?? 'all';
if (!in_array($activeFilter, ['all', 'read', 'unread'], true)) {
    $activeFilter = 'all';
}
$notifications = getNotificationsList($db, (int) $viewerId, 10, $activeFilter);
$notificationCounts = getNotificationCounts($db, (int) $viewerId);
$security = new Security();
$notificationsCsrfToken = $security->generateCSRFToken();
?>
<div class="max-w-7xl mx-auto px-4 py-6 notifications-page" data-notifications-page data-csrf-token="<?php echo htmlspecialchars($notificationsCsrfToken); ?>" data-filter="<?php echo htmlspecialchars($activeFilter); ?>">
    <div class="notifications-shell bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="notifications-header px-6 py-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="notifications-header-copy">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                            <i class="fas fa-bell text-sm"></i>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-500">Activity Center</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5">
                        <i class="fas fa-sync-alt text-[10px] text-blue-400"></i>
                        Keep up with reactions, comments, and friend activity.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gradient-to-br from-rose-50 to-orange-50 dark:from-rose-900/15 dark:to-orange-900/10 border border-rose-200/60 dark:border-rose-800/30 shadow-sm">
                        <div class="relative">
                            <i class="fas fa-bell text-lg text-rose-500 dark:text-rose-400"></i>
                            <span class="absolute -top-1.5 -right-2 min-w-[18px] h-[18px] rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center px-1 leading-none shadow-sm border-2 border-white dark:border-gray-800"><?php echo (int) $notificationCounts['unread']; ?></span>
                        </div>
                    </div>
                    <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-700 border border-gray-200 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:from-gray-100 hover:to-gray-200 dark:hover:from-gray-600 dark:hover:to-gray-600 hover:shadow-sm transition-all active:scale-[0.98]" type="button" id="markAllNotificationsBtn">
                        <i class="fas fa-check-double text-emerald-500 text-xs"></i>
                        <span>Mark all read</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="notifications-tabs flex gap-1.5 px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/40">
            <button class="notifications-tab <?php echo $activeFilter === 'all' ? 'active' : ''; ?>" data-filter="all">
                <i class="fas fa-list-ul text-xs"></i> All <strong data-tab-count="all"><?php echo (int) $notificationCounts['all']; ?></strong>
            </button>
            <button class="notifications-tab <?php echo $activeFilter === 'unread' ? 'active' : ''; ?>" data-filter="unread">
                <i class="fas fa-circle text-[6px]"></i> Unread <strong data-tab-count="unread"><?php echo (int) $notificationCounts['unread']; ?></strong>
            </button>
            <button class="notifications-tab <?php echo $activeFilter === 'read' ? 'active' : ''; ?>" data-filter="read">
                <i class="fas fa-check-circle text-xs"></i> Read <strong data-tab-count="read"><?php echo (int) $notificationCounts['read']; ?></strong>
            </button>
        </div>

        <!-- Notifications List -->
        <div class="notifications-feed-wrap" id="notificationsFeedWrap">
            <div class="notifications-list divide-y divide-gray-100 dark:divide-gray-700/50" id="notificationsList" data-has-more="<?php echo count($notifications) >= 10 ? '1' : '0'; ?>">
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $i => $notification): ?>
                    <a href="<?php echo htmlspecialchars($notification['target_url'] ?? '#'); ?>" class="notification-card-modern notification-open-btn block <?php echo empty($notification['is_read']) ? 'is-unread' : ''; ?> <?php echo htmlspecialchars($notification['meta']['accent'] ?? 'is-system'); ?> no-underline" data-notification-id="<?php echo (int) $notification['id']; ?>" data-notification-url="<?php echo htmlspecialchars($notification['target_url'] ?? '#'); ?>" data-is-read="<?php echo empty($notification['is_read']) ? '0' : '1'; ?>" style="animation:notifFadeIn 0.35s ease both;animation-delay:<?php echo min($i, 15) * 0.05; ?>s">
                        <div class="flex items-start gap-3.5 px-5 py-3.5">
                            <!-- Avatar with type badge -->
                            <div class="relative flex-shrink-0" style="width:44px;height:44px">
                                <img src="assets/avatars/<?php echo htmlspecialchars($notification['avatar'] ?? 'default.png'); ?>" alt="" class="w-11 h-11 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600" onerror="this.src='assets/avatars/default.png'">
                                <span class="absolute -bottom-0.5 -right-0.5 w-[18px] h-[18px] rounded-full flex items-center justify-center text-white text-[7px] border-2 border-white dark:border-gray-800 shadow-sm" style="background:<?php echo $notification['meta']['color'] ?? '#64748b'; ?>">
                                    <i class="fas fa-<?php echo $notification['meta']['icon'] ?? 'bell'; ?>"></i>
                                </span>
                            </div>
                            <!-- Content -->
                            <div class="flex-1 min-w-0 pt-0.5">
                                <div class="text-sm text-gray-900 dark:text-white leading-snug">
                                    <?php if ($notification['type'] === 'message'): ?>
                                    <strong class="font-semibold"><?php echo htmlspecialchars($notification['actor_name']); ?></strong>
                                    <span class="text-gray-500 dark:text-gray-400"> sent you a message</span>
                                    <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-1.5 italic truncate max-w-xs border-l-2 border-purple-400"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <?php elseif ($notification['type'] === 'friend_request'): ?>
                                    <strong class="font-semibold"><?php echo htmlspecialchars($notification['actor_name']); ?></strong>
                                    <span class="text-gray-600 dark:text-gray-300"> sent you a friend request</span>
                                    <?php elseif ($notification['type'] === 'friend_accept'): ?>
                                    <strong class="font-semibold"><?php echo htmlspecialchars($notification['actor_name']); ?></strong>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-medium"> accepted your friend request</span>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded-full"><i class="fas fa-user-check text-[8px]"></i> Now friends</span>
                                    </div>
                                    <?php elseif ($notification['type'] === 'payment_cancelled'): ?>
                                    <strong class="font-semibold"><?php echo htmlspecialchars($notification['actor_name'] ?? 'Admin'); ?></strong>
                                    <span class="text-red-600 dark:text-red-400 font-medium"> <?php echo htmlspecialchars($notification['message']); ?></span>
                                    <?php else: ?>
                                    <strong class="font-semibold"><?php echo htmlspecialchars($notification['actor_name']); ?></strong>
                                    <span class="text-gray-600 dark:text-gray-300"> <?php echo htmlspecialchars($notification['message']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo htmlspecialchars($notification['time_ago']); ?></span>
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded" style="background:<?php echo $notification['meta']['color'] ?? '#64748b'; ?>12;color:<?php echo $notification['meta']['color'] ?? '#64748b'; ?>"><?php echo htmlspecialchars($notification['meta']['label'] ?? ''); ?></span>
                                    <?php if (empty($notification['is_read'])): ?>
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Mark read button (only visible on hover) -->
                            <?php if (empty($notification['is_read'])): ?>
                            <button class="mark-notification-btn flex-shrink-0 w-7 h-7 rounded-full border-0 flex items-center justify-center text-gray-300 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all opacity-0 self-center" title="Mark as read" data-notification-id="<?php echo (int) $notification['id']; ?>">
                                <i class="fas fa-check text-[10px]"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-16 px-6">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center shadow-inner">
                            <i class="fas fa-bell-slash text-2xl text-gray-400 dark:text-gray-500"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">All caught up!</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">No notifications to show. When people react, comment, or send you a friend request, it will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="notifications-load-indicator text-center py-5 border-t border-gray-100 dark:border-gray-700/50" id="notificationsLoadIndicator" <?php echo count($notifications) < 10 ? 'hidden' : ''; ?>>
                <span class="text-sm text-gray-400 dark:text-gray-500"><i class="fas fa-spinner fa-spin mr-1.5"></i> Loading older notifications...</span>
            </div>
        </div>
    </div>
</div>

<style>
.notification-card-modern {
    transition: all 0.2s ease;
    position: relative;
}
.notification-card-modern.is-cancelled {
    border-left: 3px solid #dc2626;
    background: #fef2f2;
}
.dark .notification-card-modern.is-cancelled {
    border-left: 3px solid #ef4444;
    background: rgba(220,38,38,0.08);
}
.notification-card-modern.is-cancelled:hover {
    background: #fee2e2;
}
.dark .notification-card-modern.is-cancelled:hover {
    background: rgba(220,38,38,0.14);
}
.notification-card-modern:hover {
    background: #f7f8fa;
}
.dark .notification-card-modern:hover {
    background: rgba(51,65,85,0.35);
}
.notification-card-modern.is-unread {
    background: #eaf3ff;
}
.dark .notification-card-modern.is-unread {
    background: rgba(24,119,242,0.06);
}
.notification-card-modern.is-unread:hover {
    background: #dce9fa;
}
.dark .notification-card-modern.is-unread:hover {
    background: rgba(24,119,242,0.1);
}
.notification-card-modern:hover .mark-notification-btn {
    opacity: 1 !important;
}

/* Tab styles */
.notifications-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.45rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.15s ease;
    border: none;
    cursor: pointer;
    color: #6b7280;
    background: transparent;
}
.dark .notifications-tab {
    color: #9ca3af;
}
.notifications-tab:hover {
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.dark .notifications-tab:hover {
    background: #374151;
    box-shadow: none;
}
.notifications-tab.active[data-filter="all"] {
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
    color: #fff;
    box-shadow: 0 4px 12px rgba(59,130,246,0.25);
}
.notifications-tab.active[data-filter="unread"] {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: #fff;
    box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}
.notifications-tab.active[data-filter="read"] {
    background: linear-gradient(135deg, #10b981, #0d9488);
    color: #fff;
    box-shadow: 0 4px 12px rgba(16,185,129,0.25);
}
.notifications-tab.active strong { color: inherit; }
.notifications-tab strong { font-size: 0.75rem; margin-left: auto; opacity: 0.7; }

/* Scrollable feed */
.notifications-feed-wrap {
    max-height: 68vh;
    overflow-y: auto;
    overflow-x: hidden;
}
.notifications-feed-wrap::-webkit-scrollbar {
    width: 5px;
}
.notifications-feed-wrap::-webkit-scrollbar-track {
    background: transparent;
}
.notifications-feed-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.dark .notifications-feed-wrap::-webkit-scrollbar-thumb {
    background: #475569;
}

@keyframes notifFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid #e4e6eb;
    transition: background 0.15s ease;
}
.dark .notif-item { border-color: rgba(71,85,105,0.3); }
.notif-item:hover { background: #f7f8fa; }
.dark .notif-item:hover { background: rgba(51,65,85,0.3); }
.notif-item:last-child { border-bottom: none; }
.notif-unread { background: #eaf3ff; }
.dark .notif-unread { background: rgba(24,119,242,0.06); }
.notif-unread:hover { background: #dce9fa; }
.dark .notif-unread:hover { background: rgba(24,119,242,0.1); }

.notif-avatar-wrap {
    position: relative;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
}
.notif-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e4e6eb;
}
.dark .notif-avatar { border-color: rgba(71,85,105,0.5); }
.notif-type-icon {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.6rem;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.dark .notif-type-icon { border-color: #1e293b; }

.notif-body { flex: 1; min-width: 0; }
.notif-text { font-size: 0.9rem; line-height: 1.4; color: #1c1e21; }
.dark .notif-text { color: #e2e8f0; }
.notif-text strong { font-weight: 600; }
.notif-message { color: #65676b; }
.dark .notif-message { color: #94a3b8; }
.notif-badge {
    display: inline-block;
    padding: 0.05rem 0.45rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    margin: 0 0.3rem;
    vertical-align: middle;
}
.notif-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.2rem;
    font-size: 0.78rem;
    color: #65676b;
}
.dark .notif-meta { color: #94a3b8; }
.notif-unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1877f2;
    flex-shrink: 0;
}
.notif-actions {
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
    align-items: center;
    padding-top: 0.2rem;
}
.notif-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    color: #65676b;
    text-decoration: none;
}
.dark .notif-btn { color: #94a3b8; }
.notif-btn-ghost { background: transparent; }
.notif-btn-ghost:hover { background: #e4e6eb; color: #1c1e21; }
.dark .notif-btn-ghost:hover { background: rgba(51,65,85,0.6); color: #e2e8f0; }
</style>
<script src="<?php echo dream_asset('assets/js/social-pages.js'); ?>" defer></script>

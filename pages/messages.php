<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$viewerId = $_SESSION['user_id'] ?? null;
$db = Database::getInstance()->getConnection();
ensureSocialTables($db);

$threads = getMessageThreads($db, (int) $viewerId);
$contacts = getMessageContacts($db, (int) $viewerId, 15);
$allFriends = getAllFriends($db, (int) $viewerId);
$activeUserId = isset($_GET['user']) ? (int) $_GET['user'] : (int) ($threads[0]['other_user_id'] ?? $contacts[0]['id'] ?? 0);
$activeMessages = $activeUserId ? getConversationMessages($db, (int) $viewerId, $activeUserId, 50) : [];
$activeThread = null;
foreach (array_merge($threads, $contacts) as $c) {
    if ((int) ($c['id'] ?? $c['other_user_id'] ?? 0) === $activeUserId) {
        $activeThread = $c;
        break;
    }
}
$security = new Security();
$messagesCsrfToken = $security->generateCSRFToken();

function fmtTime($dt) {
    if (!$dt) return '';
    $ts = strtotime((string) $dt);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if (date('Y-m-d', $ts) === date('Y-m-d')) return date('g:i A', $ts);
    if ($diff < 86400 * 2) return 'Yesterday';
    return date('M j', $ts);
}
function fmtMsgTime($dt) {
    return $dt ? date('g:i A', strtotime((string) $dt)) : '';
}
function fmtDateGroup($dt) {
    if (!$dt) return '';
    $ts = strtotime((string) $dt);
    if (date('Y-m-d', $ts) === date('Y-m-d')) return 'Today';
    $diff = time() - $ts;
    if ($diff < 86400 * 2 && $diff > 0) return 'Yesterday';
    return date('F j, Y', $ts);
}
function unreadCount($threads) {
    $c = 0; foreach ($threads as $t) { $c += (int) ($t['unread_count'] ?? 0); } return $c;
}
function safeTrimWidth($str, $max, $suffix = '...') {
    if (function_exists('mb_strimwidth')) return mb_strimwidth((string) $str, 0, $max, $suffix);
    $str = (string) $str;
    if (strlen($str) <= $max) return $str;
    return substr($str, 0, $max) . $suffix;
}
?>
<div class="max-w-7xl mx-auto px-4 py-6">
<div class="messenger-app" data-messages-page data-csrf-token="<?php echo htmlspecialchars($messagesCsrfToken); ?>" data-active-user-id="<?php echo (int) $activeUserId; ?>" data-viewer-id="<?php echo (int) $viewerId; ?>" data-friends="<?php echo htmlspecialchars(json_encode($allFriends, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>">
    <div class="messenger-inner">

        <!-- ===== SIDEBAR ===== -->
        <aside class="messenger-sidebar">
            <div class="sidebar-header">
                <h1><i class="fab fa-facebook-messenger"></i>Dream Chat</h1>
                <div class="sidebar-header-actions">
                    <?php $uc = unreadCount($threads); if ($uc > 0): ?>
                    <span class="tab-badge" style="padding:2px 8px;font-size:0.65rem;align-self:center"><?php echo $uc; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-search-wrap">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="sidebar-search-input" placeholder="Search people, messages..." id="messengerSearch" autocomplete="off">
                    <button class="search-clear" id="searchClear" aria-label="Clear"><i class="fas fa-times"></i></button>
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>

            <div class="active-now-section" id="activeNowSection">
                <div class="active-now-label"><i class="fas fa-circle" style="color:var(--msg-online);font-size:0.5rem;margin-right:4px;vertical-align:middle"></i>Active Now</div>
                <div class="active-now-list" id="activeNowList"></div>
            </div>

            <div class="sidebar-tabs">
                <button class="sidebar-tab active" data-tab="inbox"><i class="fas fa-comment-dots"></i> Inbox</button>
                <button class="sidebar-tab" data-tab="friends"><i class="fas fa-user-friends"></i> Friends</button>
                <button class="sidebar-tab" data-tab="active"><i class="fas fa-circle" style="color:var(--msg-online);font-size:0.55rem"></i> Active</button>
            </div>

            <div class="sidebar-list" id="sidebarList">
                <?php if ($threads): ?>
                    <?php foreach ($threads as $thread): ?>
                    <?php $tid = (int) ($thread['other_user_id'] ?? $thread['id'] ?? 0); ?>
                    <?php $isActive = $tid === $activeUserId; ?>
                    <?php $pinned = !empty($thread['is_pinned']); ?>
                    <a href="index.php?page=messages&user=<?php echo $tid; ?>"
                       class="list-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $pinned ? 'pinned' : ''; ?>"
                       data-no-ajax data-thread-user-id="<?php echo $tid; ?>" <?php echo $pinned ? 'data-is-pinned="1"' : ''; ?>>
                        <div class="list-item-avatar">
                            <img src="assets/avatars/<?php echo htmlspecialchars($thread['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                            <?php if (!empty($thread['is_online'])): ?>
                            <span class="online-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="list-item-info">
                            <div class="list-item-top">
                                <strong><?php echo htmlspecialchars($thread['full_name'] ?: $thread['username']); ?></strong>
                                <?php if ($pinned): ?><span class="pin-badge"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
                                <span class="list-item-status <?php echo !empty($thread['is_online']) ? 'online' : ''; ?>"><?php echo !empty($thread['is_online']) ? 'Active' : 'Offline'; ?></span>
                                <span class="list-item-time"><?php echo htmlspecialchars(fmtTime($thread['last_message_at'] ?? '')); ?></span>
                            </div>
                            <div class="list-item-bottom">
                                <span class="list-item-preview"><?php echo htmlspecialchars(safeTrimWidth($thread['last_message'] ?? 'Start a conversation', 50)); ?></span>
                                <?php if (!empty($thread['unread_count'])): ?>
                                <span class="unread-badge"><?php echo (int) $thread['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="list-item-menu-wrap">
                            <button class="list-item-menu-btn" data-thread-id="<?php echo $tid; ?>" title="More"><i class="fas fa-ellipsis-h"></i></button>
                            <div class="list-item-dropdown">
                                <button class="dropdown-item" data-action="pin" data-thread-id="<?php echo $tid; ?>" data-pinned="<?php echo $pinned ? '1' : '0'; ?>"><i class="fas fa-thumbtack"></i> <span class="pin-text"><?php echo $pinned ? 'Unpin' : 'Pin'; ?></span></button>
                                <button class="dropdown-item danger" data-action="delete" data-thread-id="<?php echo $tid; ?>"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="list-empty">
                    <i class="fas fa-comment-slash"></i>
                    <p>No conversations yet</p>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- ===== MAIN CHAT ===== -->
        <main class="messenger-chat">
            <?php if ($activeThread): ?>
            <?php $atid = (int) ($activeThread['other_user_id'] ?? $activeThread['id'] ?? 0); ?>

            <!-- Select Bar -->
            <div class="select-bar" id="selectBar">
                <span id="selectCount">0 selected</span>
                <div class="select-bar-actions">
                    <button class="select-bar-btn" id="selectForwardBtn"><i class="fas fa-forward"></i> Forward</button>
                    <button class="select-bar-btn" id="selectDeleteBtn"><i class="fas fa-trash"></i> Delete</button>
                    <button class="select-bar-btn" id="selectCancelBtn"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>

            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-header-left">
                    <a href="index.php?page=messages" data-page="messages" class="chat-back-btn" title="Back"><i class="fas fa-arrow-left"></i></a>
                    <div class="chat-header-avatar">
                        <img src="assets/avatars/<?php echo htmlspecialchars($activeThread['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <?php if (!empty($activeThread['is_online'])): ?>
                        <span class="online-dot"></span>
                        <?php endif; ?>
                    </div>
                    <div class="chat-header-info">
                        <strong><?php echo htmlspecialchars($activeThread['full_name'] ?: $activeThread['username']); ?></strong>
                        <span><?php echo htmlspecialchars(formatPresenceStatus($activeThread['last_active_at'] ?? null, !empty($activeThread['is_online']))); ?></span>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="chat-action-btn" id="convSearchToggle" title="Search conversation"><i class="fas fa-search"></i></button>
                    <button class="chat-action-btn" id="themePickerToggle" title="Theme"><i class="fas fa-palette"></i></button>
                    <button class="chat-action-btn" id="openPinnedMessagesBtn" title="Pinned"><i class="fas fa-thumbtack"></i></button>
                    <a href="index.php?page=profile&user=<?php echo $atid; ?>" class="chat-action-btn" title="Profile" data-no-ajax><i class="fas fa-info-circle"></i></a>
                    <!-- Theme picker dropdown -->
                    <div class="theme-picker-dropdown" id="themePickerDropdown">
                        <div class="picker-label">Mode</div>
                        <div class="theme-mode-options">
                            <button class="theme-mode-btn active" data-mode="light"><i class="fas fa-sun"></i> Light</button>
                            <button class="theme-mode-btn" data-mode="dark"><i class="fas fa-moon"></i> Dark</button>
                        </div>
                        <div class="picker-label" style="margin-top:6px">Accent</div>
                        <div class="theme-color-options">
                            <button class="theme-color-btn theme-color-blue active" data-color="blue" title="Blue"></button>
                            <button class="theme-color-btn theme-color-purple" data-color="purple" title="Purple"></button>
                            <button class="theme-color-btn theme-color-green" data-color="green" title="Green"></button>
                            <button class="theme-color-btn theme-color-orange" data-color="orange" title="Orange"></button>
                            <button class="theme-color-btn theme-color-pink" data-color="pink" title="Pink"></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conversation search bar -->
            <div class="conv-search-bar" id="convSearchBar">
                <input type="text" class="conv-search-input" id="convSearchInput" placeholder="Search messages..." autocomplete="off">
                <span class="conv-search-results" id="convSearchResults"></span>
                <button class="conv-search-clear" id="convSearchClear"><i class="fas fa-times"></i></button>
            </div>

            <!-- Messages Stream -->
            <div class="chat-stream" id="messagesStream" data-other-user-id="<?php echo $atid; ?>" data-has-more="<?php echo count($activeMessages) >= 50 ? '1' : '0'; ?>">
                <div class="text-center py-3" id="messagesLoadIndicator" hidden>
                    <span style="font-size:0.75rem;color:var(--msg-text-secondary)"><i class="fas fa-spinner fa-spin"></i> Loading older messages...</span>
                </div>

                <?php if ($activeMessages): ?>
                    <?php $lastDate = ''; $lastSender = 0; ?>
                    <?php foreach ($activeMessages as $msg):
                        $isMine = (int) $msg['sender_id'] === (int) $viewerId;
                        $msgDate = date('Y-m-d', strtotime((string) $msg['created_at']));
                        $senderName = $msg['full_name'] ?: $msg['username'];
                        $isSameSender = !$isMine && (int) $msg['sender_id'] === $lastSender && $msgDate === $lastDate;
                    ?>
                    <?php if ($msgDate !== $lastDate): $lastDate = $msgDate; $lastSender = 0; ?>
                    <div class="date-line"><span><?php echo htmlspecialchars(fmtDateGroup($msg['created_at'])); ?></span></div>
                    <?php endif; ?>

                    <div class="msg-row <?php echo $isMine ? 'mine' : 'theirs'; ?> <?php echo $isSameSender ? 'same-sender' : ''; ?>" data-message-id="<?php echo (int) $msg['id']; ?>">
                        <?php if (!$isMine): ?>
                        <div class="msg-avatar <?php echo $isSameSender ? 'invisible' : ''; ?>">
                            <img src="assets/avatars/<?php echo htmlspecialchars($msg['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        </div>
                        <?php endif; ?>
                        <span class="select-checkbox"></span>
                        <div class="msg-content">
                            <?php if (!$isMine && !$isSameSender): ?>
                            <div class="msg-sender-label"><?php echo htmlspecialchars($senderName); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($msg['reply_to_message_id'])): ?>
                            <div class="msg-reply <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                                <i class="fas fa-reply"></i>
                                <span><?php echo htmlspecialchars(safeTrimWidth($msg['reply_body'] ?? 'Photo', 40)); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="msg-bubble <?php echo $isMine ? 'mine' : ($isSameSender ? 'same' : ''); ?>">
                                <?php if (!empty($msg['image_path'])): ?>
                                <img src="assets/messages/<?php echo htmlspecialchars($msg['image_path']); ?>" alt="" class="msg-image" loading="lazy">
                                <?php endif; ?>
                                <?php if (!empty(trim((string) $msg['body']))): ?>
                                <p><?php echo htmlspecialchars($msg['body']); ?></p>
                                <?php endif; ?>
                                <div class="msg-meta">
                                    <span class="msg-time"><?php echo htmlspecialchars(fmtMsgTime($msg['created_at'])); ?></span>
                                    <?php if (!empty($msg['edited_at'])): ?>
                                    <span class="msg-edited">edited</span>
                                    <?php endif; ?>
                                    <?php if ($isMine): ?>
                                        <?php if (!empty($msg['is_read'])): ?>
                                        <i class="fas fa-check-double msg-read"></i>
                                        <?php else: ?>
                                        <i class="fas fa-check msg-sent"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($msg['is_pinned'])): ?>
                            <div class="msg-pinned"><i class="fas fa-thumbtack"></i> Pinned</div>
                            <?php endif; ?>
                            <?php if (!empty($msg['reaction_summary'])): ?>
                            <div class="msg-reaction-pills <?php echo $isMine ? 'mine' : ''; ?>">
                                <?php foreach ($msg['reaction_summary'] as $rxn):
                                    $activeRxn = ($msg['viewer_reaction'] ?? null) === $rxn['reaction_type'];
                                ?>
                                <span class="msg-reaction-pill <?php echo $activeRxn ? 'active' : ''; ?>">
                                    <span class="reaction-emoji"><?php echo htmlspecialchars($rxn['reaction_type']); ?></span>
                                    <span class="reaction-count"><?php echo (int) $rxn['count']; ?></span>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="msg-reaction-bar <?php echo $isMine ? 'mine' : ''; ?>">
                                <?php $reactionsList = ['👍','❤️','😂','😮','😢']; ?>
                                <?php foreach ($reactionsList as $emoji): ?>
                                <button class="msg-reaction-btn" data-message-id="<?php echo (int) $msg['id']; ?>" data-reaction="<?php echo htmlspecialchars($emoji); ?>" title="<?php echo htmlspecialchars($emoji); ?>"><?php echo $emoji; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="msg-actions">
                            <button class="msg-action msg-reply-action message-reply-btn" title="Reply" data-message-id="<?php echo (int) $msg['id']; ?>" data-author-name="<?php echo htmlspecialchars($senderName); ?>" data-preview="<?php echo htmlspecialchars(safeTrimWidth($msg['body'] ?? 'Photo', 60)); ?>"><i class="fas fa-reply"></i></button>
                            <?php if ($isMine): ?>
                            <button class="msg-action msg-edit-action message-edit-btn" title="Edit" data-message-id="<?php echo (int) $msg['id']; ?>" data-body="<?php echo htmlspecialchars($msg['body'] ?? ''); ?>"><i class="fas fa-pen"></i></button>
                            <?php endif; ?>
                            <button class="msg-action msg-pin-action message-pin-btn" title="<?php echo !empty($msg['is_pinned']) ? 'Unpin' : 'Pin'; ?>" data-message-id="<?php echo (int) $msg['id']; ?>" data-pinned="<?php echo !empty($msg['is_pinned']) ? '1' : '0'; ?>"><i class="fas fa-thumbtack"></i></button>
                            <button class="msg-action msg-forward-action message-forward-btn" title="Forward" data-message-id="<?php echo (int) $msg['id']; ?>"><i class="fas fa-forward"></i></button>
                            <?php if ($isMine): ?>
                            <button class="msg-action msg-delete-action message-delete-btn" title="Delete" data-message-id="<?php echo (int) $msg['id']; ?>"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php $lastSender = (int) $msg['sender_id']; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="chat-empty">
                    <i class="fas fa-comment-dots"></i>
                    <p>No messages yet. Say something!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Compose -->
            <form class="chat-compose" id="messageForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messagesCsrfToken); ?>">
                <input type="hidden" name="receiver_id" value="<?php echo (int) $activeUserId; ?>">
                <input type="hidden" name="reply_to_message_id" id="replyToMessageId" value="">
                <div class="reply-preview hidden" id="messageReplyingBox"></div>
                <div class="compose-bar">
                    <label class="compose-attach">
                        <i class="fas fa-plus"></i>
                        <input type="file" id="messageImageInput" name="message_image" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                    </label>
                    <div class="compose-input-wrap">
                        <textarea name="body" class="compose-input" rows="1" maxlength="1000" placeholder="Type a message..."></textarea>
                    </div>
                    <button class="compose-send" type="submit" disabled>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="compose-preview" id="messageImagePreview"></div>
            </form>

            <?php else: ?>
            <div class="chat-welcome">
                <div class="welcome-icon"><i class="fab fa-facebook-messenger"></i></div>
                <h3>Welcome to Dream Chat</h3>
                <p>Select a conversation or start chatting with friends</p>
                <?php if ($contacts): ?>
                <div class="welcome-contacts">
                    <?php foreach (array_slice($contacts, 0, 5) as $sug): ?>
                    <a href="index.php?page=messages&user=<?php echo (int) $sug['id']; ?>" data-no-ajax class="welcome-chip">
                        <img src="assets/avatars/<?php echo htmlspecialchars($sug['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <span><?php echo htmlspecialchars(explode(' ', trim($sug['full_name'] ?: $sug['username']))[0]); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Pinned Modal -->
    <div class="pinned-modal-overlay hidden" id="pinnedMessagesModal">
        <div class="pinned-modal-backdrop" data-close-pinned-modal></div>
        <div class="pinned-modal-dialog">
            <div class="pinned-modal-header">
                <h3><i class="fas fa-thumbtack"></i>Pinned Messages</h3>
                <button class="pinned-modal-close" type="button" data-close-pinned-modal><i class="fas fa-times"></i></button>
            </div>
            <div class="pinned-modal-body" id="pinnedMessagesBody">
                <div class="pinned-empty">No pinned messages.</div>
            </div>
        </div>
    </div>

    <!-- Forward Modal -->
    <div class="forward-modal-overlay hidden" id="forwardModal">
        <div class="forward-modal-backdrop" data-close-forward-modal></div>
        <div class="forward-modal-dialog">
            <div class="forward-modal-header">
                <h3><i class="fas fa-forward" style="color:var(--msg-primary);margin-right:6px"></i>Forward Message</h3>
                <button class="forward-modal-close" type="button" data-close-forward-modal><i class="fas fa-times"></i></button>
            </div>
            <div class="forward-modal-search">
                <input type="text" id="forwardSearchInput" placeholder="Search friends..." autocomplete="off">
            </div>
            <div class="forward-modal-body" id="forwardList"></div>
        </div>
    </div>
</div>
</div>
<script src="<?php echo dream_asset('assets/js/social-pages.js'); ?>" defer></script>

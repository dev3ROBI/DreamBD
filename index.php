<?php
// index.php
require_once __DIR__ . '/includes/session.php';
dream_start_session();

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/includes/auth_functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$security = new Security();

$isLoggedIn = $auth->isLoggedIn();
$user_name = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;

$page = $_GET['page'] ?? 'home';
$allowed_pages = ['home', 'community', 'products', 'tournaments', 'tournament-room', 'how-it-works', 'cart', 'login', 'register', 'rules', 'faq', 'profile', 'messages', 'notifications', 'search', 'agent-dashboard', 'balance', 'p2p', 'admin', 'agent_submit_results'];
$page = in_array($page, $allowed_pages) ? $page : 'home';

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if user is logged in and trying to access login/register
if ($isLoggedIn && ($page === 'login' || $page === 'register')) {
    if ($is_ajax) {
        echo json_encode(['redirect' => 'index.php?page=profile', 'status' => 'redirect']);
        exit;
    } else {
        header('Location: index.php?page=profile');
        exit();
    }
}

// Check if user is not logged in and trying to access protected page
if (!$isLoggedIn && in_array($page, ['profile', 'messages', 'notifications', 'tournament-room'], true)) {
    if ($is_ajax) {
        echo json_encode(['redirect' => 'index.php?page=login', 'status' => 'redirect']);
        exit;
    } else {
        header('Location: index.php?page=login');
        exit();
    }
}

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'nav_search') {
    header('Content-Type: application/json');
    $query = trim((string) ($_GET['q'] ?? ''));
    if ($query === '') {
        echo json_encode(['users' => [], 'posts' => [], 'counts' => ['all' => 0, 'people' => 0, 'posts' => 0]]);
        exit;
    }

    try {
        $results = getNavbarSearchResults(Database::getInstance()->getConnection(), $isLoggedIn ? (int) ($_SESSION['user_id'] ?? 0) : null, $query, 4, 3);
        echo json_encode($results);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['users' => [], 'posts' => [], 'counts' => ['all' => 0, 'people' => 0, 'posts' => 0]]);
    }
    exit;
}

if ($is_ajax && isset($_GET['ajax'])) {
    ob_start();
    try {
        include "pages/{$page}.php";
        $content = ob_get_clean();
    } catch (Throwable $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unable to load page.']);
        exit;
    }
    echo json_encode(['title' => "DreamBD - {$page}", 'content' => $content, 'page' => $page]);
    exit;
}

// Normal page load - NO ob_start() here
$csrf_token = $security->generateCSRFToken();

// Theme logic
$theme = $_COOKIE['dreambd-theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'])) {
    $theme = 'light';
}
$themeAttr = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $themeAttr; ?>" data-theme="<?php echo $themeAttr; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DreamBD - <?php echo ucfirst($page); ?></title>
    
    <!-- Tailwind CSS (Play CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {} }
        }
    </script>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/profile.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/social-pages.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/animations.css'); ?>">
    <link rel="stylesheet" href="<?php echo dream_asset('assets/css/community.css'); ?>">

    <style>
        body { opacity: 0; }
        body.css-ready { opacity: 1; transition: opacity 0.15s ease; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('css-ready');
        });
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white transition-colors duration-300" id="body" data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>">
    
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Main Content -->
    <main id="mainContent" class="main-content min-h-screen">
        <div id="pageSkeleton" class="page-skeleton" aria-hidden="true">
            <div class="skeleton-container">
                <div class="sk-hero">
                    <div class="sk-banner"></div>
                    <div class="sk-hero-content">
                        <div class="sk-avatar sk-avatar-lg"></div>
                        <div class="sk-hero-text">
                            <div class="sk-line sk-line-lg"></div>
                            <div class="sk-line sk-line-sm"></div>
                        </div>
                    </div>
                </div>
                <div class="sk-tabs">
                    <div class="sk-tab"></div>
                    <div class="sk-tab"></div>
                    <div class="sk-tab"></div>
                    <div class="sk-tab"></div>
                </div>
                <div class="sk-feed">
                    <div class="sk-post">
                        <div class="sk-post-head">
                            <div class="sk-avatar"></div>
                            <div class="sk-post-head-text">
                                <div class="sk-line sk-line-md"></div>
                                <div class="sk-line sk-line-xs"></div>
                            </div>
                        </div>
                        <div class="sk-post-body">
                            <div class="sk-line sk-line-xl"></div>
                            <div class="sk-line sk-line-lg"></div>
                            <div class="sk-line sk-line-md"></div>
                        </div>
                        <div class="sk-post-thumb"></div>
                        <div class="sk-post-actions">
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                        </div>
                    </div>
                    <div class="sk-post">
                        <div class="sk-post-head">
                            <div class="sk-avatar"></div>
                            <div class="sk-post-head-text">
                                <div class="sk-line sk-line-md"></div>
                                <div class="sk-line sk-line-xs"></div>
                            </div>
                        </div>
                        <div class="sk-post-body">
                            <div class="sk-line sk-line-xl"></div>
                            <div class="sk-line sk-line-lg"></div>
                            <div class="sk-line sk-line-sm"></div>
                        </div>
                        <div class="sk-post-actions">
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                        </div>
                    </div>
                    <div class="sk-post">
                        <div class="sk-post-head">
                            <div class="sk-avatar"></div>
                            <div class="sk-post-head-text">
                                <div class="sk-line sk-line-md"></div>
                                <div class="sk-line sk-line-xs"></div>
                            </div>
                        </div>
                        <div class="sk-post-body">
                            <div class="sk-line sk-line-xl"></div>
                            <div class="sk-line sk-line-md"></div>
                            <div class="sk-line sk-line-sm"></div>
                        </div>
                        <div class="sk-post-thumb"></div>
                        <div class="sk-post-actions">
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                            <div class="sk-action"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="pageContent">
            <?php include "pages/{$page}.php"; ?>
        </div>
    </main>

    <!-- Modal Root (fixed-position, outside mainContent to avoid stacking issues) -->
    <div id="modalRoot"></div>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress" role="progressbar" aria-label="Page scroll progress"></div>

    <footer class="footer dream-footer" id="mainFooter">
        <!-- Decorative top wave -->
        <div class="footer-wave" aria-hidden="true">
            <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
                <path d="M0,40 C360,120 1080,-40 1440,40 L1440,0 L0,0 Z" fill="currentColor"/>
            </svg>
        </div>

        <!-- Floating decoration circles -->
        <div class="footer-deco footer-deco-1" aria-hidden="true"></div>
        <div class="footer-deco footer-deco-2" aria-hidden="true"></div>

        <div class="container">
            <div class="footer-content">
                <section class="footer-section footer-brand-section">
                    <a href="index.php?page=home" class="footer-logo" data-page="home">
                        <img src="assets/logo/logo.png" alt="DreamBD" onerror="this.style.display='none'">
                        <span>DreamBD</span>
                    </a>
                    <p class="footer-description">A colorful social home for community posts, products, tournaments, friends, and real-time conversations.</p>
                    <div class="social-links">
                        <a href="#" class="social-link social-facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link social-discord" aria-label="Discord"><i class="fab fa-discord"></i></a>
                        <a href="#" class="social-link social-youtube" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="social-link social-instagram" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link social-github" aria-label="GitHub"><i class="fab fa-github"></i></a>
                    </div>
                </section>

                <section class="footer-section">
                    <h3 class="footer-title">Explore</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?page=home" data-page="home"><i class="fas fa-house"></i> Home</a></li>
                        <li><a href="index.php?page=community" data-page="community"><i class="fas fa-users"></i> Community</a></li>
                        <li><a href="index.php?page=products" data-page="products"><i class="fas fa-store"></i> Products</a></li>
                        <li><a href="index.php?page=tournaments" data-page="tournaments"><i class="fas fa-trophy"></i> Tournaments</a></li>
                        <li><a href="index.php?page=how-it-works" data-page="how-it-works"><i class="fas fa-circle-info"></i> How it works</a></li>
                    </ul>
                </section>

                <section class="footer-section">
                    <h3 class="footer-title">Support</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?page=rules" data-page="rules"><i class="fas fa-book"></i> Rules</a></li>
                        <li><a href="index.php?page=faq" data-page="faq"><i class="fas fa-question-circle"></i> FAQ</a></li>
                        <li><a href="index.php?page=cart" data-page="cart"><i class="fas fa-cart-shopping"></i> Cart</a></li>
                        <li><a href="index.php?page=contact" data-page="contact"><i class="fas fa-envelope"></i> Contact</a></li>
                    </ul>
                </section>

                <section class="footer-section">
                    <h3 class="footer-title">Stay Updated</h3>
                    <p class="footer-description">Get ready for marketplace launches, tournament updates, and community features.</p>
                    <form class="newsletter-form" id="footerNewsletterForm" action="#" method="post" novalidate>
                        <div class="newsletter-input-wrap">
                            <input type="email" placeholder="Enter your email" aria-label="Email address" required>
                            <button type="submit" aria-label="Subscribe">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <div class="newsletter-feedback" id="newsletterFeedback" aria-live="polite"></div>
                    </form>
                    <p class="newsletter-note">No spam. Unsubscribe anytime.</p>
                </section>
            </div>

            <div class="footer-bottom">
                <p class="footer-copy">&copy; <span id="footerYear"><?php echo date('Y'); ?></span> DreamBD. All rights reserved.</p>
                <div class="footer-trust">
                    <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure</span>
                    <span class="trust-badge"><i class="fas fa-lock"></i> Privacy</span>
                    <span class="trust-badge"><i class="fas fa-clock"></i> 24/7 Support</span>
                </div>
                <div class="footer-bottom-links">
                    <a href="index.php?page=rules" data-page="rules">Terms</a>
                    <a href="index.php?page=faq" data-page="faq">Privacy</a>
                    <a href="index.php?page=faq" data-page="faq">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top" data-scroll-progress>
        <svg class="progress-ring" viewBox="0 0 48 48" aria-hidden="true">
            <circle class="progress-ring-bg" cx="24" cy="24" r="20" fill="none"/>
            <circle class="progress-ring-fill" cx="24" cy="24" r="20" fill="none" stroke-linecap="round"/>
        </svg>
        <i class="fas fa-chevron-up" aria-hidden="true"></i>
    </button>
    
    <!-- Modal Overlay -->
    <div class="modal-overlay hidden fixed inset-0 bg-black/50 z-40" id="modalOverlay"></div>

    <!-- Post View Modal -->
    <div class="home-post-modal-backdrop hidden" id="homePostModalBackdrop" aria-hidden="true">
        <section class="home-post-modal" role="dialog" aria-modal="true" aria-labelledby="homePostModalTitle">
            <header class="home-post-modal-header">
                <h2 id="homePostModalTitle">Post details</h2>
                <button class="home-post-modal-close" type="button" aria-label="Close post details">
                    <i class="fas fa-times"></i>
                </button>
            </header>
            <div class="home-post-modal-body">
                <article class="home-post-modal-card">
                    <div class="home-post-modal-author">
                        <img src="assets/avatars/default.png" alt="" data-modal-avatar onerror="this.src='assets/avatars/default.png'">
                        <div>
                            <strong data-modal-author>DreamBD</strong>
                            <span><span data-modal-time>Just now</span> · <i class="fas fa-earth-asia"></i> <span data-modal-privacy>Public</span></span>
                        </div>
                    </div>
                    <p data-modal-content></p>
                    <div class="home-post-modal-image hidden" data-modal-image-wrap>
                        <img src="" alt="Post image" data-modal-image>
                    </div>
                    <div class="home-post-modal-stats">
                        <span><span class="home-modal-reaction-bubbles">👍 ❤️ 😮</span> <strong data-modal-likes>0</strong></span>
                        <span><strong data-modal-comments>0</strong> comments · <strong data-modal-shares>0</strong> shares</span>
                    </div>
                </article>
                <div class="home-post-modal-comments" data-modal-comments-list></div>
            </div>
            <form class="home-post-modal-composer" data-modal-comment-form>
                <input type="hidden" name="post_id" value="">
                <input type="hidden" name="parent_comment_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                <div class="home-post-modal-input-wrap">
                    <input type="text" name="comment" maxlength="300" placeholder="Write a comment...">
                    <div class="home-post-modal-tools">
                        <button type="button" aria-label="Add emoji"><i class="far fa-smile"></i></button>
                        <button type="button" aria-label="Add photo"><i class="fas fa-camera"></i></button>
                        <button type="button" aria-label="Add GIF"><i class="fas fa-file-image"></i></button>
                    </div>
                </div>
                <button class="home-post-modal-send" type="submit" aria-label="Post comment">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </section>
    </div>

    <!-- Settings & Logout Dialogs -->
    <div class="site-dialog-backdrop" id="siteDialogBackdrop" hidden></div>
    
    <div class="site-dialog" id="globalSettingsDialog" role="dialog" aria-modal="true" aria-labelledby="globalSettingsTitle" hidden>
        <div class="site-dialog-panel settings-dialog-panel">
            <div class="site-dialog-header">
                <div>
                    <span class="site-dialog-kicker">DreamBD</span>
                    <h2 id="globalSettingsTitle"><i class="fas fa-sliders"></i> Settings & Shortcuts</h2>
                    <p class="site-dialog-subtitle">Personalize navigation, appearance, and quick access from one colorful panel.</p>
                </div>
                <button type="button" class="site-dialog-close" data-close-site-dialog aria-label="Close settings">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="site-settings-grid">
                <section class="site-settings-card">
                    <span class="site-settings-card-icon"><i class="fas fa-user-gear"></i></span>
                    <span class="site-settings-card-tag">Daily essentials</span>
                    <h3>Account</h3>
                    <p>Manage your profile, messages, notifications, and orders.</p>
                    <div class="site-settings-links">
                        <a href="index.php?page=profile" data-no-ajax><i class="fas fa-user"></i><span>Profile</span></a>
                        <a href="index.php?page=messages" data-no-ajax><i class="fas fa-comments"></i><span>Messages</span></a>
                        <a href="index.php?page=notifications" data-no-ajax><i class="fas fa-bell"></i><span>Notifications</span></a>
                        <a href="index.php?page=cart" data-page="cart"><i class="fas fa-cart-shopping"></i><span>Cart & Orders</span></a>
                    </div>
                </section>
                <section class="site-settings-card">
                    <span class="site-settings-card-icon"><i class="fas fa-compass"></i></span>
                    <span class="site-settings-card-tag">Main sections</span>
                    <h3>Explore</h3>
                    <p>Jump to the main DreamBD sections quickly.</p>
                    <div class="site-settings-links">
                        <a href="index.php?page=home" data-page="home"><i class="fas fa-house"></i><span>Home</span></a>
                        <a href="index.php?page=community" data-page="community"><i class="fas fa-users"></i><span>Community</span></a>
                        <a href="index.php?page=products" data-page="products"><i class="fas fa-shopping-bag"></i><span>Products</span></a>
                        <a href="index.php?page=tournaments" data-page="tournaments"><i class="fas fa-trophy"></i><span>Tournaments</span></a>
                    </div>
                </section>
                <section class="site-settings-card">
                    <span class="site-settings-card-icon"><i class="fas fa-bolt"></i></span>
                    <span class="site-settings-card-tag">Fast shortcuts</span>
                    <h3>Quick actions</h3>
                    <p>Start common social actions without extra clicks.</p>
                    <div class="site-settings-links">
                        <a href="index.php?page=community" data-page="community"><i class="fas fa-pen-to-square"></i><span>Share a post</span></a>
                        <a href="index.php?page=messages" data-no-ajax><i class="fas fa-paper-plane"></i><span>Open inbox</span></a>
                        <a href="index.php?page=profile" data-no-ajax><i class="fas fa-user-group"></i><span>Manage friends</span></a>
                        <a href="index.php?page=notifications" data-no-ajax><i class="fas fa-heart"></i><span>Review activity</span></a>
                    </div>
                </section>
                <section class="site-settings-card">
                    <span class="site-settings-card-icon"><i class="fas fa-life-ring"></i></span>
                    <span class="site-settings-card-tag">Helpful reads</span>
                    <h3>Help & Guide</h3>
                    <p>Read platform rules, guide, and common answers.</p>
                    <div class="site-settings-links">
                        <a href="index.php?page=how-it-works" data-page="how-it-works"><i class="fas fa-cogs"></i><span>How It Works</span></a>
                        <a href="index.php?page=rules" data-page="rules"><i class="fas fa-book"></i><span>Rules</span></a>
                        <a href="index.php?page=faq" data-page="faq"><i class="fas fa-question-circle"></i><span>FAQ</span></a>
                    </div>
                </section>
                <section class="site-settings-card">
                    <span class="site-settings-card-icon"><i class="fas fa-palette"></i></span>
                    <span class="site-settings-card-tag">Look and feel</span>
                    <h3>Appearance</h3>
                    <p>Choose the look that feels best for you.</p>
                    <div class="site-theme-options">
                        <button type="button" class="site-theme-btn" data-theme-choice="light"><i class="fas fa-sun"></i> Light</button>
                        <button type="button" class="site-theme-btn" data-theme-choice="dark"><i class="fas fa-moon"></i> Dark</button>
                        <button type="button" class="site-theme-btn" data-theme-choice="auto"><i class="fas fa-circle-half-stroke"></i> Auto</button>
                    </div>
                    <div class="site-settings-note">You can still change detailed preferences from your profile settings page.</div>
                </section>
                <section class="site-settings-card site-settings-card-wide">
                    <span class="site-settings-card-icon"><i class="fas fa-shield-heart"></i></span>
                    <span class="site-settings-card-tag">Safety</span>
                    <h3>Session & privacy</h3>
                    <p>Need a clean exit on a shared device? Open the logout confirmation from here or from the mobile menu any time.</p>
                    <div class="site-settings-links">
                        <button type="button" class="site-settings-action" data-open-logout-dialog><i class="fas fa-right-from-bracket"></i><span>Open logout confirmation</span></button>
                        <a href="index.php?page=profile&tab=sessions" data-no-ajax><i class="fas fa-shield-halved"></i><span>Review active sessions</span></a>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    <div class="site-dialog" id="logoutConfirmDialog" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle" hidden>
        <div class="site-dialog-panel logout-dialog-panel">
            <div class="site-dialog-header">
                <div>
                    <h2 id="logoutConfirmTitle"><i class="fas fa-right-from-bracket"></i> Log out?</h2>
                </div>
                <button type="button" class="site-dialog-close" data-close-site-dialog aria-label="Close logout dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="site-dialog-copy logout-dialog-copy">Are you sure you want to log out of this device?</div>
            <div class="site-dialog-actions">
                <button type="button" class="btn btn-outline" data-close-site-dialog>Cancel</button>
                <a href="logout.php" class="btn btn-primary logout-confirm-btn" data-no-ajax>
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div class="auth-modal hidden fixed inset-0 z-50 items-center justify-center" id="forgotPasswordModal">
        <div class="modal-content bg-white dark:bg-gray-900 rounded-3xl p-8 max-w-md w-full mx-4 relative">
            <button class="modal-close absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" data-modal-close="forgotPassword" aria-label="Close modal">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <div class="modal-header text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mb-4">
                    <i class="fas fa-key text-2xl text-white"></i>
                </div>
                <h2 class="modal-title text-2xl font-bold text-gray-900 dark:text-white">Reset Password</h2>
                <p class="modal-subtitle text-gray-600 dark:text-gray-400 mt-2">Enter your email and we'll send you a link to reset your password</p>
            </div>
            
            <form method="POST" action="handlers/forgot_password_handler.php" class="auth-form space-y-5" id="forgotPasswordForm" data-ajax-form="true" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <div class="relative group">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                        <input type="email" id="reset_email" name="email" 
                               class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-all" 
                               placeholder="your@email.com" autocomplete="email" required>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter the email associated with your account</div>
                </div>
                
                <button type="submit" class="w-full relative overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-1 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 transition-all duration-300 text-base group">
                    <span class="btn-text group-hover:tracking-wide transition-all">Send Reset Link</span>
                    <div class="btn-loader hidden absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div class="w-6 h-6 border-2 border-white/30 rounded-full border-t-white animate-spin"></div>
                    </div>
                </button>
                
                <div class="auth-footer text-center">
                    <p class="text-gray-600 dark:text-gray-400">Remember your password?
                        <a href="index.php?page=login" class="text-blue-500 hover:underline font-medium">Sign in here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="<?php echo dream_asset('assets/js/main.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/navbar.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/ajax.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/auth-handler.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/home.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/profile.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/social-pages.js'); ?>" defer></script>
    <script src="<?php echo dream_asset('assets/js/social-feed.js'); ?>" defer></script>
    
    <script>
        // Initialize app when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            const setNavbarActiveState = (pageName) => {
                document.querySelectorAll('#mainNav [data-page]').forEach((link) => {
                    const isActive = link.getAttribute('data-page') === pageName;
                    link.classList.toggle('active', isActive);
                    link.classList.toggle('is-active', isActive);
                    if (isActive) {
                        link.setAttribute('aria-current', 'page');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };

            if (window.DreamBDApp) {
                window.DreamBDApp.init();
            }
            
            // Navbar dropdown toggle with animation
            const userDropdownToggle = document.getElementById('userDropdownToggle');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            const hideUserDropdown = () => {
                if (!userDropdownMenu) return;
                userDropdownMenu.classList.add('opacity-0', 'scale-95');
                setTimeout(() => userDropdownMenu.classList.add('hidden'), 200);
            };
            if (userDropdownToggle && userDropdownMenu) {
                userDropdownToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = userDropdownMenu.classList.contains('hidden');
                    if (isHidden) {
                        userDropdownMenu.classList.remove('hidden', 'opacity-0', 'scale-95');
                        userDropdownMenu.classList.add('opacity-100', 'scale-100');
                    } else {
                        hideUserDropdown();
                    }
                });
                userDropdownMenu.addEventListener('click', (e) => {
                    if (e.target.closest('a, button')) {
                        hideUserDropdown();
                    }
                });
                document.addEventListener('click', (e) => {
                    if (!userDropdownMenu.classList.contains('hidden') &&
                        !userDropdownMenu.contains(e.target) &&
                        e.target !== userDropdownToggle &&
                        !userDropdownToggle.contains(e.target)) {
                        hideUserDropdown();
                    }
                });
            }
            
            // Dialog handlers
            const siteDialogBackdrop = document.getElementById('siteDialogBackdrop');
            const settingsDialog = document.getElementById('globalSettingsDialog');
            const logoutDialog = document.getElementById('logoutConfirmDialog');
            let siteDialogScrollY = 0;
            
            const closeAllDialogs = () => {
                if (settingsDialog) {
                    settingsDialog.hidden = true;
                    settingsDialog.classList.add('hidden');
                    settingsDialog.classList.remove('grid', 'flex');
                }
                if (logoutDialog) {
                    logoutDialog.hidden = true;
                    logoutDialog.classList.add('hidden');
                    logoutDialog.classList.remove('grid', 'flex');
                }
                if (siteDialogBackdrop) {
                    siteDialogBackdrop.hidden = true;
                    siteDialogBackdrop.classList.add('hidden');
                }
                document.documentElement.classList.remove('dialog-open');
                document.body.classList.remove('dialog-open');
                document.body.style.position = '';
                document.body.style.width = '';
                document.body.style.top = '';
                window.scrollTo(0, siteDialogScrollY);
            };

            const openSiteDialog = (dialog) => {
                siteDialogScrollY = window.scrollY || document.documentElement.scrollTop || 0;
                closeAllDialogs();
                if (dialog) {
                    dialog.hidden = false;
                    dialog.classList.remove('hidden', 'flex');
                    dialog.classList.add('grid');
                    dialog.scrollTop = 0;
                }
                if (siteDialogBackdrop) {
                    siteDialogBackdrop.hidden = false;
                    siteDialogBackdrop.classList.remove('hidden');
                }
                document.documentElement.classList.add('dialog-open');
                document.body.classList.add('dialog-open');
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
                document.body.style.top = `-${siteDialogScrollY}px`;
            };
            
            // Open settings dialog
            document.querySelectorAll('[data-open-global-settings]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideUserDropdown();
                    openSiteDialog(settingsDialog);
                });
            });
            
            // Open logout dialog
            document.querySelectorAll('[data-open-logout-dialog]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideUserDropdown();
                    openSiteDialog(logoutDialog);
                });
            });
            
            // Close dialog buttons
            document.querySelectorAll('[data-close-site-dialog]').forEach(btn => {
                btn.addEventListener('click', closeAllDialogs);
            });
            
            // Close on backdrop click
            if (siteDialogBackdrop) {
                siteDialogBackdrop.addEventListener('click', closeAllDialogs);
            }
            
            // Theme choice buttons in settings
            document.querySelectorAll('[data-theme-choice]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const theme = btn.getAttribute('data-theme-choice');
                    const html = document.documentElement;
                    if (theme === 'dark') {
                        html.classList.add('dark');
                        html.setAttribute('data-theme', 'dark');
                    } else if (theme === 'light') {
                        html.classList.remove('dark');
                        html.setAttribute('data-theme', 'light');
                    } else {
                        // Auto - check system preference
                        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                            html.classList.add('dark');
                            html.setAttribute('data-theme', 'dark');
                        } else {
                            html.classList.remove('dark');
                            html.setAttribute('data-theme', 'light');
                        }
                    }
                    document.cookie = `dreambd-theme=${theme}; path=/; max-age=31536000`;
                });
            });

            setNavbarActiveState('<?php echo htmlspecialchars($page); ?>');
            document.addEventListener('pageChanged', (event) => {
                setNavbarActiveState(event.detail?.page || 'home');
            });

            // ============ Footer: Back to Top with Scroll Progress ============
            const backToTopBtn = document.getElementById('backToTop');
            if (backToTopBtn) {
                const ringFill = backToTopBtn.querySelector('.progress-ring-fill');
                const circumference = 2 * Math.PI * 20;
                if (ringFill) ringFill.style.strokeDasharray = circumference;

                const handleScroll = () => {
                    const scrollTop = window.scrollY;
                    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                    const progress = docHeight > 0 ? scrollTop / docHeight : 0;

                    if (scrollTop > 400) {
                        backToTopBtn.classList.add('visible');
                    } else {
                        backToTopBtn.classList.remove('visible');
                    }

                    if (ringFill) {
                        const offset = circumference - progress * circumference;
                        ringFill.style.strokeDashoffset = offset;
                    }
                };

                window.addEventListener('scroll', handleScroll, { passive: true });

                backToTopBtn.addEventListener('click', () => {
                    backToTopBtn.classList.add('clicked');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(() => backToTopBtn.classList.remove('clicked'), 500);
                });
            }

            // ============ Footer: Newsletter Form ============
            const newsletterForm = document.getElementById('footerNewsletterForm');
            const newsletterFeedback = document.getElementById('newsletterFeedback');
            if (newsletterForm) {
                newsletterForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const input = newsletterForm.querySelector('input[type="email"]');
                    const email = input ? input.value.trim() : '';

                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        if (newsletterFeedback) {
                            newsletterFeedback.textContent = 'Please enter a valid email address.';
                            newsletterFeedback.className = 'newsletter-feedback error';
                        }
                        return;
                    }

                    if (newsletterFeedback) {
                        newsletterFeedback.textContent = 'Thanks for subscribing! Check your inbox.';
                        newsletterFeedback.className = 'newsletter-feedback success';
                    }
                    if (input) input.value = '';
                });
            }

            // ============ Footer: Animate sections on scroll ============
            const footerObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('footer-section-visible');
                        footerObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.dream-footer .footer-section').forEach((section, index) => {
                section.style.transitionDelay = `${index * 0.1}s`;
                footerObserver.observe(section);
            });
        });
    </script>
</body>
</html>

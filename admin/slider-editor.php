<?php
$pageTitle = 'Slider Editor';
$pageHeading = 'Slider Editor';
$currentPage = 'slider';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Slider Editor']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$csrfToken = $security->generateCSRFToken();
$messages = [];
$errors = [];
$editSlide = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_slide') {
                $stmt = $db->prepare("INSERT INTO slider_content
                    (title, badge, slider_type, description, image_path, bg_gradient, bg_image,
                     button1_text, button1_icon, button1_class, button1_href,
                     button2_text, button2_icon, button2_class, button2_href,
                     link_url, link_text, sort_order,
                     badge_icon, accent_color, text_color, overlay_opacity, prize_money)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    trim($_POST['title'] ?? ''),
                    trim($_POST['badge'] ?? ''),
                    trim($_POST['slider_type'] ?? 'features'),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['image_path'] ?? ''),
                    trim($_POST['bg_gradient'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'),
                    trim($_POST['bg_image'] ?? ''),
                    trim($_POST['button1_text'] ?? ''),
                    trim($_POST['button1_icon'] ?? ''),
                    trim($_POST['button1_class'] ?? ''),
                    trim($_POST['button1_href'] ?? ''),
                    trim($_POST['button2_text'] ?? ''),
                    trim($_POST['button2_icon'] ?? ''),
                    trim($_POST['button2_class'] ?? ''),
                    trim($_POST['button2_href'] ?? ''),
                    trim($_POST['link_url'] ?? ''),
                    trim($_POST['link_text'] ?? ''),
                    (int)($_POST['sort_order'] ?? 0),
                    trim($_POST['badge_icon'] ?? 'fa-star'),
                    trim($_POST['accent_color'] ?? '#3b82f6'),
                    trim($_POST['text_color'] ?? '#ffffff'),
                    (float)($_POST['overlay_opacity'] ?? 0.6),
                    trim($_POST['prize_money'] ?? '')
                ]);
                $messages[] = '[SUCCESS] Slide added successfully';
            }

            if ($action === 'update_slide') {
                $id = (int)($_POST['slide_id'] ?? 0);
                $stmt = $db->prepare("UPDATE slider_content SET title=?, badge=?, slider_type=?, description=?, image_path=?, bg_gradient=?, bg_image=?, button1_text=?, button1_icon=?, button1_class=?, button1_href=?, button2_text=?, button2_icon=?, button2_class=?, button2_href=?, link_url=?, link_text=?, badge_icon=?, accent_color=?, text_color=?, overlay_opacity=?, prize_money=?, status=?, sort_order=? WHERE id=?");
                $stmt->execute([
                    trim($_POST['title'] ?? ''),
                    trim($_POST['badge'] ?? ''),
                    trim($_POST['slider_type'] ?? 'features'),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['image_path'] ?? ''),
                    trim($_POST['bg_gradient'] ?? ''),
                    trim($_POST['bg_image'] ?? ''),
                    trim($_POST['button1_text'] ?? ''),
                    trim($_POST['button1_icon'] ?? ''),
                    trim($_POST['button1_class'] ?? ''),
                    trim($_POST['button1_href'] ?? ''),
                    trim($_POST['button2_text'] ?? ''),
                    trim($_POST['button2_icon'] ?? ''),
                    trim($_POST['button2_class'] ?? ''),
                    trim($_POST['button2_href'] ?? ''),
                    trim($_POST['link_url'] ?? ''),
                    trim($_POST['link_text'] ?? ''),
                    trim($_POST['badge_icon'] ?? 'fa-star'),
                    trim($_POST['accent_color'] ?? '#3b82f6'),
                    trim($_POST['text_color'] ?? '#ffffff'),
                    (float)($_POST['overlay_opacity'] ?? 0.6),
                    trim($_POST['prize_money'] ?? ''),
                    $_POST['status'] ?? 'active',
                    (int)($_POST['sort_order'] ?? 0),
                    $id
                ]);
                $messages[] = '[SUCCESS] Slide updated successfully';
            }

            if ($action === 'delete_slide') {
                $id = (int)($_POST['slide_id'] ?? 0);
                $stmt = $db->prepare("DELETE FROM slider_content WHERE id=?");
                $stmt->execute([$id]);
                $messages[] = '[SUCCESS] Slide deleted successfully';
            }

            if ($action === 'edit_slide') {
                $id = (int)($_POST['slide_id'] ?? 0);
                $stmt = $db->prepare("SELECT * FROM slider_content WHERE id=?");
                $stmt->execute([$id]);
                $editSlide = $stmt->fetch();
            }

            if ($action === 'reorder_slides') {
                $order = $_POST['slide_order'] ?? [];
                foreach ($order as $index => $slideId) {
                    $stmt = $db->prepare("UPDATE slider_content SET sort_order=? WHERE id=?");
                    $stmt->execute([$index, $slideId]);
                }
                $messages[] = '[SUCCESS] Slides reordered successfully';
            }
        } catch (PDOException $e) {
            $errors[] = '[ERROR] ' . $e->getMessage();
        }
    }
}

// Get all slides
$slides = [];
try {
    $stmt = $db->query("SELECT * FROM slider_content ORDER BY sort_order ASC");
    $slides = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = '[ERROR] Slider table not found. Run "Create Tables" from manage-db.php first.';
}
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

    <!-- Add/Edit Slide Form -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-<?php echo $editSlide ? 'edit' : 'plus'; ?> text-blue-500 mr-2"></i>
            <?php echo $editSlide ? 'Edit Slide' : 'Add New Slide'; ?>
        </h2>

        <form method="POST" action="" id="slideForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editSlide ? 'update_slide' : 'add_slide'; ?>">
            <?php if ($editSlide): ?>
            <input type="hidden" name="slide_id" value="<?php echo $editSlide['id']; ?>">
            <?php endif; ?>

            <!-- Live Preview -->
            <div class="mb-6 rounded-xl overflow-hidden shadow-lg">
                <div id="slidePreview" class="h-48 flex items-center justify-center p-8 transition-all duration-300 relative"
                     style="background: <?php echo htmlspecialchars($editSlide['bg_gradient'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'); ?>;">
                    <div class="absolute top-2 right-2 text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/15 text-white/80" id="previewTypeLabel">
                        <?php echo htmlspecialchars($editSlide['slider_type'] ?? 'features'); ?>
                    </div>
                    <div class="text-center text-white">
                        <div class="inline-flex items-center gap-1.5 bg-white/20 px-3 py-1 rounded-full text-sm mb-3" id="previewBadge">
                            <i class="fas <?php echo htmlspecialchars($editSlide['badge_icon'] ?? 'fa-star'); ?>"></i>
                            <span><?php echo htmlspecialchars($editSlide['badge'] ?? 'Badge'); ?></span>
                        </div>
                        <h3 class="text-2xl font-bold mb-2" id="previewTitle"><?php echo htmlspecialchars(substr($editSlide['title'] ?? 'Slide Title', 0, 50)); ?></h3>
                        <p class="text-sm opacity-90" id="previewDesc"><?php echo htmlspecialchars(substr($editSlide['description'] ?? 'Description here...', 0, 100)); ?></p>
                        <div class="mt-4 space-x-3" id="previewButtons">
                            <button type="button" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm transition-colors" id="previewBtn1">
                                <?php echo htmlspecialchars($editSlide['button1_text'] ?? 'Button 1'); ?>
                            </button>
                            <button type="button" class="px-4 py-2 border border-white/50 hover:bg-white/10 rounded-lg text-sm transition-colors" id="previewBtn2">
                                <?php echo htmlspecialchars($editSlide['button2_text'] ?? 'Button 2'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Fields -->
            <div class="space-y-4 mb-6">

                <!-- Slider Type Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Slide Type</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="sliderTypeSelector">
                        <?php
                        $types = [
                            'features' => ['icon' => 'fa-star', 'label' => 'Web Features', 'desc' => 'Showcase platform features', 'color' => 'blue'],
                            'tournament' => ['icon' => 'fa-trophy', 'label' => 'Tournament', 'desc' => 'Promote tournaments', 'color' => 'purple'],
                            'leaderboard' => ['icon' => 'fa-ranking-star', 'label' => 'Leaderboard', 'desc' => 'Top players ranking', 'color' => 'amber'],
                            'ads' => ['icon' => 'fa-ad', 'label' => 'Ad / Promo', 'desc' => 'Advertisement banner', 'color' => 'green'],
                        ];
                        $currentType = $editSlide['slider_type'] ?? 'features';
                        foreach ($types as $key => $t):
                            $selected = $key === $currentType;
                            $colorMap = ['blue' => 'ring-blue-500 bg-blue-50 dark:bg-blue-900/20 border-blue-200', 'purple' => 'ring-purple-500 bg-purple-50 dark:bg-purple-900/20 border-purple-200', 'amber' => 'ring-amber-500 bg-amber-50 dark:bg-amber-900/20 border-amber-200', 'green' => 'ring-green-500 bg-green-50 dark:bg-green-900/20 border-green-200'];
                        ?>
                        <label class="type-option relative cursor-pointer rounded-xl border-2 p-3 text-center transition-all <?php echo $selected ? $colorMap[$t['color']] . ' ring-2' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            <input type="radio" name="slider_type" value="<?php echo $key; ?>" class="sr-only" <?php echo $selected ? 'checked' : ''; ?> onchange="updateSlideType(this.value)">
                            <i class="fas <?php echo $t['icon']; ?> text-xl <?php echo $selected ? '' : 'text-gray-400'; ?> mb-1 block"></i>
                            <span class="text-xs font-semibold block"><?php echo $t['label']; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2" id="typeDescription">Showcase platform features and community highlights.</p>
                </div>

                <div data-field="all">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                    <input type="text" name="title" required
                           value="<?php echo htmlspecialchars($editSlide['title'] ?? ''); ?>"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white"
                           placeholder="Slide title" oninput="updatePreview()">
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div data-field="all">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge</label>
                        <input type="text" name="badge"
                               value="<?php echo htmlspecialchars($editSlide['badge'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white"
                               placeholder="e.g. DreamBD Network" oninput="updatePreview()">
                    </div>
                    <div data-field="all">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" min="0"
                               value="<?php echo $editSlide['sort_order'] ?? 0; ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                    </div>
                    <div data-field="all">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image Path</label>
                        <input type="text" name="image_path" value="<?php echo htmlspecialchars($editSlide['image_path'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white font-mono text-xs"
                               placeholder="assets/images/slider/slide1.jpg">
                    </div>
                    <div data-field="all">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">BG Image</label>
                        <input type="text" name="bg_image" value="<?php echo htmlspecialchars($editSlide['bg_image'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white font-mono text-xs"
                               placeholder="Background image URL">
                    </div>
                </div>
                <div data-field="all">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="3" required
                              class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white"
                              oninput="updatePreview()"><?php echo htmlspecialchars($editSlide['description'] ?? ''); ?></textarea>
                </div>

                <!-- Customization Panel - Redesigned (features + leaderboard) -->
                <div data-field="features,leaderboard" class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-800 dark:to-gray-850 rounded-2xl p-5 border-2 border-gray-200/80 dark:border-gray-600/80 shadow-sm">
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-200/60 dark:border-gray-600/60">
                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                            <i class="fas fa-palette text-sm"></i>
                        </span>
                        <div>
                            <span class="text-sm font-bold text-gray-800 dark:text-white">Visual Style</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Customize colors, icons, and overlay for this slide</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Left Column: Badge Icon + Opacity -->
                        <div class="space-y-5">
                            <!-- Icon Picker -->
                            <div class="bg-white dark:bg-gray-700/40 rounded-xl p-3.5 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-icons text-blue-500 text-[10px]"></i> Badge Icon
                                    </label>
                                    <span class="text-[10px] font-mono text-gray-400 bg-gray-100 dark:bg-gray-600 px-2 py-0.5 rounded" id="badgeIconPreview">fa-star</span>
                                </div>
                                <input type="text" name="badge_icon" value="<?php echo htmlspecialchars($editSlide['badge_icon'] ?? 'fa-star'); ?>"
                                       class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-500 rounded-lg text-gray-800 dark:text-white font-mono text-sm mb-2 focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                                       placeholder="fa-star" oninput="updatePreview(); document.getElementById('badgeIconPreview').textContent=this.value||'fa-star'">
                                <div class="flex gap-1.5 flex-wrap">
                                    <?php $icons = ['fa-star','fa-bolt','fa-fire','fa-gem','fa-crown','fa-rocket','fa-heart','fa-bell','fa-flag','fa-globe','fa-sun','fa-moon','fa-wand-magic-sparkles','fa-cloud','fa-code','fa-paintbrush','fa-gear','fa-shield','fa-bomb','fa-feather']; ?>
                                    <?php foreach ($icons as $i => $ic): ?>
                                    <button type="button" onclick="setIcon('badge_icon', '<?php echo $ic; ?>')" class="icon-swatch w-8 h-8 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-blue-300 dark:hover:border-blue-500 hover:text-blue-600 dark:hover:text-blue-400 hover:-translate-y-0.5 active:scale-95 transition-all text-xs text-gray-600 dark:text-gray-300 flex items-center justify-center" title="<?php echo $ic; ?>" data-icon="<?php echo $ic; ?>">
                                        <i class="fas <?php echo $ic; ?>"></i>
                                    </button>
                                    <?php if (($i + 1) % 10 === 0): ?><div class="w-full"></div><?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Opacity Slider -->
                            <div class="bg-white dark:bg-gray-700/40 rounded-xl p-3.5 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-circle-half-stroke text-blue-500 text-[10px]"></i> Overlay Opacity
                                    </label>
                                    <span class="text-xs font-bold text-gray-800 dark:text-white font-mono bg-gray-100 dark:bg-gray-600 px-2.5 py-0.5 rounded" id="opacityValue"><?php echo $editSlide['overlay_opacity'] ?? 0.6; ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] text-gray-400 w-6">0%</span>
                                    <div class="relative flex-1 h-7 flex items-center">
                                        <div class="absolute inset-x-0 h-1.5 rounded-full bg-gradient-to-r from-gray-300 via-blue-400 to-gray-800 dark:from-gray-600 dark:via-blue-500 dark:to-gray-900"></div>
                                        <input type="range" name="overlay_opacity" min="0" max="1" step="0.05"
                                               value="<?php echo $editSlide['overlay_opacity'] ?? 0.6; ?>"
                                               class="relative w-full h-7 appearance-none bg-transparent cursor-pointer z-10 opacity-0"
                                               oninput="updatePreview(); document.getElementById('opacityValue').textContent=this.value; fillOpacityTrack(this)">
                                        <div class="absolute inset-x-0 h-1.5 rounded-full pointer-events-none overflow-hidden">
                                            <div class="h-full rounded-full bg-blue-500 transition-all" id="opacityTrackFill" style="width: <?php echo ((float)($editSlide['overlay_opacity'] ?? 0.6)) * 100; ?>%"></div>
                                        </div>
                                        <!-- Custom thumb -->
                                        <div class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white border-2 border-blue-500 shadow-md pointer-events-none transition-all" id="opacityThumb" style="left: calc(<?php echo ((float)($editSlide['overlay_opacity'] ?? 0.6)) * 100; ?>% - 8px);"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-400 w-6 text-right">100%</span>
                                </div>
                                <div class="flex justify-between mt-2 text-[9px] text-gray-400 dark:text-gray-500">
                                    <span>Transparent</span>
                                    <span>Solid</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Accent + Text Colors -->
                        <div class="space-y-5">
                            <!-- Accent Color -->
                            <div class="bg-white dark:bg-gray-700/40 rounded-xl p-3.5 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-droplet text-pink-500 text-[10px]"></i> Accent Color
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <div class="relative">
                                            <input type="color" name="accent_color" value="<?php echo htmlspecialchars($editSlide['accent_color'] ?? '#3b82f6'); ?>"
                                                   class="w-8 h-8 rounded-lg cursor-pointer block border border-gray-300 dark:border-gray-500" id="accentColorInput"
                                                   oninput="updatePreview(); pickColor(this, 'accent')">
                                        </div>
                                        <input type="text" name="accent_color_hex" value="<?php echo htmlspecialchars($editSlide['accent_color'] ?? '#3b82f6'); ?>"
                                               class="w-20 px-2 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-500 rounded-lg text-gray-800 dark:text-white font-mono text-xs text-center focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                                               id="accentHex" oninput="updatePreview(); document.getElementById('accentColorInput').value=this.value">
                                    </div>
                                </div>
                                <div class="color-palette" data-target="accent_color">
                                    <?php
                                    $colorGroups = [
                                        'Blue' => ['#3b82f6','#2563eb','#1d4ed8','#1e40af'],
                                        'Purple' => ['#a78bfa','#8b5cf6','#7c3aed','#6d28d9'],
                                        'Pink' => ['#f472b6','#ec4899','#db2777','#be185d'],
                                        'Red' => ['#f87171','#ef4444','#dc2626','#b91c1c'],
                                        'Orange' => ['#fb923c','#f97316','#ea580c','#c2410c'],
                                        'Amber' => ['#fbbf24','#f59e0b','#d97706','#b45309'],
                                        'Green' => ['#34d399','#10b981','#059669','#047857'],
                                        'Cyan' => ['#22d3ee','#06b6d4','#0891b2','#0e7490'],
                                        'Gray' => ['#94a3b8','#64748b','#475569','#334155'],
                                        'Dark' => ['#1e293b','#0f172a'],
                                    ];
                                    foreach ($colorGroups as $groupName => $colors):
                                    ?>
                                    <div class="mb-2 last:mb-0">
                                        <div class="text-[9px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5"><?php echo $groupName; ?></div>
                                        <div class="flex gap-1 flex-wrap">
                                            <?php foreach ($colors as $c): ?>
                                            <button type="button" onclick="setColor('accent_color', '<?php echo $c; ?>')"
                                                    class="color-swatch w-7 h-7 rounded-lg border-2 border-transparent hover:scale-110 hover:shadow-lg transition-all duration-150 shadow-sm"
                                                    style="background: <?php echo $c; ?>;" title="<?php echo $c; ?>"
                                                    data-color="<?php echo $c; ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Text Color -->
                            <div class="bg-white dark:bg-gray-700/40 rounded-xl p-3.5 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-font text-green-500 text-[10px]"></i> Text Color
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <div class="relative">
                                            <input type="color" name="text_color" value="<?php echo htmlspecialchars($editSlide['text_color'] ?? '#ffffff'); ?>"
                                                   class="w-8 h-8 rounded-lg cursor-pointer block border border-gray-300 dark:border-gray-500" id="textColorInput"
                                                   oninput="updatePreview(); pickColor(this, 'text')">
                                        </div>
                                        <input type="text" name="text_color_hex" value="<?php echo htmlspecialchars($editSlide['text_color'] ?? '#ffffff'); ?>"
                                               class="w-20 px-2 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-500 rounded-lg text-gray-800 dark:text-white font-mono text-xs text-center focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                                               id="textHex" oninput="updatePreview(); document.getElementById('textColorInput').value=this.value">
                                    </div>
                                </div>
                                <div class="color-palette" data-target="text_color">
                                    <?php
                                    $textGroups = [
                                        'Light' => ['#ffffff','#f8fafc','#f1f5f9','#e2e8f0'],
                                        'Medium' => ['#cbd5e1','#94a3b8','#64748b','#475569'],
                                        'Dark' => ['#334155','#1e293b','#0f172a','#000000'],
                                    ];
                                    foreach ($textGroups as $groupName => $colors):
                                    ?>
                                    <div class="mb-2 last:mb-0">
                                        <div class="text-[9px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5"><?php echo $groupName; ?></div>
                                        <div class="flex gap-1 flex-wrap">
                                            <?php foreach ($colors as $c): ?>
                                            <button type="button" onclick="setColor('text_color', '<?php echo $c; ?>')"
                                                    class="color-swatch w-7 h-7 rounded-lg border-2 border-transparent hover:scale-110 hover:shadow-lg transition-all duration-150 shadow-sm"
                                                    style="background: <?php echo $c; ?>; <?php echo in_array($c, ['#ffffff','#f8fafc','#f1f5f9','#e2e8f0','#cbd5e1']) ? 'border: 2px solid #d1d5db;' : ''; ?>"
                                                    title="<?php echo $c; ?>" data-color="<?php echo $c; ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prize Money (Tournament only) -->
                <div data-field="tournament">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Prize Money</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                        <input type="text" name="prize_money" value="<?php echo htmlspecialchars($editSlide['prize_money'] ?? ''); ?>"
                               class="w-full max-w-xs pl-7 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white"
                               placeholder="1,200">
                    </div>
                </div>

                <!-- Link fields (for tournament + leaderboard + ads) -->
                <div data-field="tournament,leaderboard,ads" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link URL</label>
                        <input type="text" name="link_url" value="<?php echo htmlspecialchars($editSlide['link_url'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white"
                               placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link Text</label>
                        <input type="text" name="link_text" value="<?php echo htmlspecialchars($editSlide['link_text'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white"
                               placeholder="Learn More">
                    </div>
                </div>

                <div data-field="all">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Gradient</label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" name="bg_gradient"
                               value="<?php echo htmlspecialchars($editSlide['bg_gradient'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'); ?>"
                               class="flex-1 px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white font-mono text-sm"
                               placeholder="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
                               oninput="updatePreview()">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $gradients = [
                            ['bg' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'title' => 'Blue-Purple'],
                            ['bg' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'title' => 'Pink-Red'],
                            ['bg' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'title' => 'Light Blue'],
                            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1d4ed8 50%, #0ea5e9 100%)', 'title' => 'Deep Blue'],
                            ['bg' => 'linear-gradient(135deg, #1a0533 0%, #4c1d95 30%, #7c3aed 60%, #2563eb 100%)', 'title' => 'Purple Storm'],
                            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%)', 'title' => 'Dark Slate'],
                            ['bg' => 'linear-gradient(135deg, #1e293b, #334155)', 'title' => 'Slate'],
                            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #991b1b 50%, #dc2626 100%)', 'title' => 'Red Flame'],
                            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #065f46 50%, #10b981 100%)', 'title' => 'Emerald'],
                            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #92400e 50%, #f59e0b 100%)', 'title' => 'Amber'],
                        ];
                        foreach ($gradients as $g):
                        ?>
                        <button type="button" onclick="setGradient('<?php echo addslashes($g['bg']); ?>')" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors" title="<?php echo $g['title']; ?>">
                            <div class="w-8 h-6 rounded" style="background: <?php echo $g['bg']; ?>"></div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Button Config (Features only) -->
            <div class="space-y-4 mb-6" data-field="features">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-6 h-6 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-link text-blue-500 text-xs"></i></span>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-white">Action Buttons</h3>
                    <span class="text-[10px] text-blue-500 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-full font-semibold">Features</span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Button 1 Text</label>
                        <input type="text" name="button1_text" value="<?php echo htmlspecialchars($editSlide['button1_text'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm" oninput="updatePreview()">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Icon</label>
                        <input type="text" name="button1_icon" value="<?php echo htmlspecialchars($editSlide['button1_icon'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono" placeholder="fa-arrow-right">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">CSS Class</label>
                        <input type="text" name="button1_class" value="<?php echo htmlspecialchars($editSlide['button1_class'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Link</label>
                        <input type="text" name="button1_href" value="<?php echo htmlspecialchars($editSlide['button1_href'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono" placeholder="index.php?page=...">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Button 2 Text</label>
                        <input type="text" name="button2_text" value="<?php echo htmlspecialchars($editSlide['button2_text'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm" oninput="updatePreview()">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Icon</label>
                        <input type="text" name="button2_icon" value="<?php echo htmlspecialchars($editSlide['button2_icon'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">CSS Class</label>
                        <input type="text" name="button2_class" value="<?php echo htmlspecialchars($editSlide['button2_class'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Link</label>
                        <input type="text" name="button2_href" value="<?php echo htmlspecialchars($editSlide['button2_href'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono">
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6" data-field="all">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    <option value="active" <?php echo ($editSlide['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($editSlide['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i><?php echo $editSlide ? 'Update Slide' : 'Add Slide'; ?>
                </button>
                <?php if ($editSlide): ?>
                <a href="slider-editor.php" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg transition-colors">
                    Cancel
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Existing Slides with Drag & Drop -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">
                <i class="fas fa-list text-green-500 mr-2"></i>Existing Slides (<?php echo count($slides); ?>)
            </h2>
            <button onclick="saveOrder()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm transition-colors hidden" id="saveOrderBtn">
                <i class="fas fa-save mr-1"></i>Save Order
            </button>
        </div>

        <?php if (empty($slides)): ?>
        <div class="text-center text-red-500 dark:text-red-400">
            <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
            <p>[WARNING] No slides found. Add one first.</p>
        </div>
        <?php else: ?>
        <div class="space-y-4" id="sortableSlides">
            <?php foreach ($slides as $index => $slide): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:shadow-lg transition-shadow cursor-move" data-id="<?php echo $slide['id']; ?>">
                <div class="flex items-stretch">
                    <div class="w-16 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 cursor-move">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="flex-1 h-32 flex items-center" style="background: <?php echo htmlspecialchars($slide['bg_gradient']); ?>;">
                        <div class="px-6 py-3 text-white">
                            <div class="text-xs bg-white/20 inline-block px-2 py-1 rounded-full mb-1"><?php echo htmlspecialchars($slide['badge'] ?? 'Slide'); ?></div>
                            <div class="font-bold text-sm"><?php echo htmlspecialchars(substr($slide['title'] ?? '', 0, 40)); ?></div>
                            <span class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider mt-1 opacity-70">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($slide['slider_type'] ?? 'features'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center px-4 space-x-2">
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="edit_slide">
                            <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
                            <button type="submit" class="p-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                <i class="fas fa-edit"></i>
                            </button>
                        </form>
                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this slide?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="delete_slide">
                            <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
                            <button type="submit" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    // Live Preview Update
    function updatePreview() {
        const preview = document.getElementById('slidePreview');
        const title = document.querySelector('input[name="title"]');
        const badge = document.querySelector('input[name="badge"]');
        const desc = document.querySelector('textarea[name="description"]');
        const gradient = document.querySelector('input[name="bg_gradient"]');
        const btn1Text = document.querySelector('input[name="button1_text"]');
        const btn2Text = document.querySelector('input[name="button2_text"]');
        const badgeIcon = document.querySelector('input[name="badge_icon"]');
        const accentColor = document.querySelector('input[name="accent_color"]');
        const textColor = document.querySelector('input[name="text_color"]');
        const opacity = document.querySelector('input[name="overlay_opacity"]');

        if (!preview) return;
        preview.style.background = gradient.value;
        document.getElementById('previewTitle').textContent = (title?.value || 'Slide Title').substring(0, 50);
        
        const typeLabel = document.getElementById('previewTypeLabel');
        if (typeLabel) {
            const t = document.querySelector('input[name="slider_type"]:checked');
            typeLabel.textContent = t?.value || 'features';
        }
        
        const badgeEl = document.getElementById('previewBadge');
        const iconVal = badgeIcon?.value || 'fa-star';
        badgeEl.innerHTML = `<i class="fas ${iconVal}"></i> <span>${badge?.value || 'Badge'}</span>`;
        
        document.getElementById('previewDesc').textContent = (desc?.value || 'Description here...').substring(0, 100);
        document.getElementById('previewBtn1').textContent = btn1Text?.value || 'Button 1';
        document.getElementById('previewBtn2').textContent = btn2Text?.value || 'Button 2';
        
        // Update accent color hex
        const accentHex = document.getElementById('accentHex');
        if (accentHex && accentColor) accentHex.value = accentColor.value;
        const textHex = document.getElementById('textHex');
        if (textHex && textColor) textHex.value = textColor.value;
        
        // Apply accent to preview elements
        if (badgeEl) badgeEl.style.background = accentColor?.value || '#3b82f6';
        if (document.getElementById('previewBtn1')) document.getElementById('previewBtn1').style.background = accentColor?.value || '#3b82f6';
    }

    function setIcon(field, icon) {
        const input = document.querySelector(`input[name="${field}"]`);
        if (input) { input.value = icon; updatePreview(); }
        document.querySelectorAll('.icon-swatch').forEach(b => {
            b.classList.remove('active');
            if (b.dataset.icon === icon) b.classList.add('active');
        });
        const preview = document.getElementById('badgeIconPreview');
        if (preview) preview.textContent = icon;
    }

    function setColor(field, color) {
        const colorInput = document.querySelector(`input[name="${field}"]`);
        const hexInput = document.querySelector(`input[name="${field}_hex"]`);
        if (colorInput) colorInput.value = color;
        if (hexInput) hexInput.value = color;
        // Highlight selected swatch
        const palette = document.querySelector(`.color-palette[data-target="${field}"]`);
        if (palette) {
            palette.querySelectorAll('.color-swatch').forEach(b => {
                b.classList.remove('active');
                const bg = b.style.backgroundColor;
                if (bg === color || rgbToHex(bg) === color.toLowerCase()) {
                    b.classList.add('active');
                }
            });
        }
        updatePreview();
    }

    function pickColor(input, type) {
        const field = type === 'accent' ? 'accent_color' : 'text_color';
        const hexInput = document.querySelector(`input[name="${field}_hex"]`);
        if (hexInput) hexInput.value = input.value;
        setColor(field, input.value);
    }

    function rgbToHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb.startsWith('#')) return rgb;
        const m = rgb.match(/\d+/g);
        if (!m || m.length < 3) return rgb;
        return '#' + [parseInt(m[0]), parseInt(m[1]), parseInt(m[2])].map(x => x.toString(16).padStart(2,'0')).join('');
    }

    function fillOpacityTrack(range) {
        const pct = ((range.value - range.min) / (range.max - range.min)) * 100;
        const fill = document.getElementById('opacityTrackFill');
        const thumb = document.getElementById('opacityThumb');
        if (fill) fill.style.width = pct + '%';
        if (thumb) thumb.style.left = 'calc(' + pct + '% - 8px)';
    }

    function initIconHighlights() {
        const val = document.querySelector('input[name="badge_icon"]')?.value;
        document.querySelectorAll('.icon-swatch').forEach(b => {
            b.classList.remove('active');
            if (b.dataset.icon === val) b.classList.add('active');
        });
    }

    // Sync color inputs
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize palette and icon highlights
        ['accent_color', 'text_color'].forEach(f => {
            const val = document.querySelector(`input[name="${f}"]`)?.value;
            if (val) setColor(f, val);
        });
        initIconHighlights();
        // Setup opacity track
        const range = document.querySelector('input[name="overlay_opacity"]');
        if (range) fillOpacityTrack(range);
        // Color picker sync
        document.querySelectorAll('input[type="color"]').forEach(c => {
            c.addEventListener('input', function() {
                const parent = this.closest('.flex');
                const textInput = parent ? parent.querySelector('input[type="text"]') : null;
                if (textInput) textInput.value = this.value;
                updatePreview();
            });
        });
        document.querySelectorAll('input[name="accent_color_hex"], input[name="text_color_hex"]').forEach(t => {
            t.addEventListener('input', function() {
                const parent = this.closest('.flex');
                const colorInput = parent ? parent.querySelector('input[type="color"]') : null;
                if (colorInput) colorInput.value = this.value;
                updatePreview();
            });
        });
        updatePreview();
    });

    function setGradient(gradient) {
        document.querySelector('input[name="bg_gradient"]').value = gradient;
        updatePreview();
    }

    // Sortable Slides
    const sortableContainer = document.getElementById('sortableSlides');
    if (sortableContainer) {
        new Sortable(sortableContainer, {
            animation: 150,
            ghostClass: 'opacity-50',
            onSort: function() {
                document.getElementById('saveOrderBtn').classList.remove('hidden');
            }
        });
    }

    function saveOrder() {
        const slides = document.querySelectorAll('#sortableSlides > div');
        const order = Array.from(slides).map((slide, index) => {
            const id = slide.getAttribute('data-id');
            return `slides[${index}]=${id}`;
        }).join('&');

        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="reorder_slides">
            ${Array.from(slides).map((slide, index) =>
                `<input type="hidden" name="slide_order[]" value="${slide.getAttribute('data-id')}">`
            ).join('')}
        `;
        document.body.appendChild(form);
        form.submit();
    }

    // Initialize preview
    updatePreview();
    // Initialize type selector
    const currentType = document.querySelector('input[name="slider_type"]:checked');
    if (currentType) updateSlideType(currentType.value);

    function updateSlideType(type) {
        const descriptions = {
            features: 'Showcase platform features and community highlights with rich gradients.',
            tournament: 'Promote upcoming or live tournaments with prize info and dates.',
            leaderboard: 'Display top players / leaderboard rankings with scores.',
            ads: 'Advertisement banner with sponsor branding and call-to-action links.'
        };
        const desc = document.getElementById('typeDescription');
        if (desc) desc.textContent = descriptions[type] || '';
        
        // Update type option styles
        document.querySelectorAll('.type-option').forEach(el => {
            const radio = el.querySelector('input');
            const isSelected = radio && radio.value === type;
            el.classList.toggle('ring-2', isSelected);
            el.classList.toggle('border-gray-200', !isSelected);
            el.classList.toggle('dark:border-gray-700', !isSelected);
            el.classList.toggle('hover:border-gray-300', !isSelected);
            el.classList.toggle('dark:hover:border-gray-600', !isSelected);
            const icon = el.querySelector('i');
            if (icon) icon.classList.toggle('text-gray-400', !isSelected);
        });

        // Show/hide fields based on type
        document.querySelectorAll('[data-field]').forEach(el => {
            const types = el.dataset.field.split(',');
            if (types.includes('all') || types.includes(type)) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });

        // Update preview style based on type
        const preview = document.getElementById('slidePreview');
        if (preview) {
            preview.className = 'h-48 flex items-center justify-center p-8 transition-all duration-300';
            if (type === 'tournament') {
                preview.classList.add('border-4', 'border-yellow-400/50', 'rounded-2xl');
            } else if (type === 'leaderboard') {
                preview.classList.add('shadow-inner');
            } else if (type === 'ads') {
                preview.classList.add('border', 'border-dashed', 'border-white/30');
            }
        }
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

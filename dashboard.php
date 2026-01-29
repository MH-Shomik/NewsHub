<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION;

// Fetch Stats
try {
    $stats = [];
    
    if ($user['role'] === 'admin') {
        // ADMIN STATS (Global)
        $stats['total_news'] = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
        $stats['active_alerts'] = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_active=1")->fetchColumn();
        $stats['total_views'] = $pdo->query("SELECT SUM(views) FROM news")->fetchColumn();
        
        // Pending Items
        $stats['pending_news'] = $pdo->query("SELECT COUNT(*) FROM news WHERE status='pending'")->fetchColumn();
        $stats['pending_alerts'] = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_active=0")->fetchColumn();
        
        // Recent News (Global)
        $stmt = $pdo->query("
            SELECT n.*, c.name as category_name, u.full_name as author_name 
            FROM news n 
            JOIN categories c ON n.category_id = c.category_id 
            JOIN users u ON n.author_id = u.user_id 
            ORDER BY n.created_at DESC LIMIT 5
        ");
        $recentNews = $stmt->fetchAll();
        
        // Pending News (Recent 5)
        $pendingNewsStmt = $pdo->query("
            SELECT n.*, c.name as category_name, u.full_name as author_name 
            FROM news n 
            JOIN categories c ON n.category_id = c.category_id 
            JOIN users u ON n.author_id = u.user_id 
            WHERE n.status = 'pending'
            ORDER BY n.created_at DESC LIMIT 5
        ");
        $pendingNews = $pendingNewsStmt->fetchAll();

        // Pending Alerts (Recent 5)
        $pendingAlerts = $pdo->query("SELECT * FROM alerts WHERE is_active = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll();
        
    } else {
        // MODERATOR STATS (Personal)
        $uid = $user['user_id'];
        $stats['my_articles'] = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = ?");
        $stats['my_articles']->execute([$uid]); 
        $stats['my_articles'] = $stats['my_articles']->fetchColumn();
        
        $stats['my_pending'] = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = ? AND status = 'pending'");
        $stats['my_pending']->execute([$uid]);
        $stats['my_pending'] = $stats['my_pending']->fetchColumn();
        
        $stats['my_published'] = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = ? AND status = 'published'");
        $stats['my_published']->execute([$uid]);
        $stats['my_published'] = $stats['my_published']->fetchColumn();
        
        $stats['my_views'] = $pdo->prepare("SELECT SUM(views) FROM news WHERE author_id = ?");
        $stats['my_views']->execute([$uid]);
        $stats['my_views'] = $stats['my_views']->fetchColumn() ?: 0;
        
        // Recent News (Personal)
        $stmt = $pdo->prepare("
            SELECT n.*, c.name as category_name, u.full_name as author_name 
            FROM news n 
            JOIN categories c ON n.category_id = c.category_id 
            JOIN users u ON n.author_id = u.user_id 
            WHERE n.author_id = ?
            ORDER BY n.created_at DESC LIMIT 5
        ");
        $stmt->execute([$uid]);
        $recentNews = $stmt->fetchAll();
        $pendingAlerts = []; // Moderators don't approve alerts
        $pendingNews = [];
    }
} catch (PDOException $e) {}

// --- CHART DATA FETCHING ---
try {
    // 1. Weekly Activity (Publications in last 7 days)
    $weekly_activity = [];
    $labels_week = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels_week[] = date('D', strtotime($date));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE DATE(created_at) = ? AND status = 'published'");
        $stmt->execute([$date]);
        $weekly_activity[] = $stmt->fetchColumn();
    }

    // 2. Category Distribution
    $cat_labels = [];
    $cat_data = [];
    $cat_colors = [];
    $colorMap = [
        'Academic' => '#6366f1', 
        'Research' => '#a855f7', 
        'Events' => '#fbbf24', 
        'Sports' => '#f97316', 
        'Notices' => '#64748b'
    ];
    $stmt = $pdo->query("SELECT c.name, COUNT(n.news_id) as count FROM categories c LEFT JOIN news n ON c.category_id = n.category_id AND n.status = 'published' GROUP BY c.category_id");
    while ($row = $stmt->fetch()) {
        $cat_labels[] = $row['name'];
        $cat_data[] = $row['count'];
        $cat_colors[] = $colorMap[$row['name']] ?? '#cbd5e1'; 
    }
} catch (PDOException $e) {
    // Fallback defaults
    $weekly_activity = [0,0,0,0,0,0,0];
    $labels_week = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $cat_labels = ['No Data']; $cat_data = [1];
}

// Pass to JS
echo "<script>window.dashboardData = { 
    weekly: { labels: " . json_encode($labels_week) . ", data: " . json_encode($weekly_activity) . " },
    categories: { labels: " . json_encode($cat_labels) . ", data: " . json_encode($cat_data) . ", colors: " . json_encode($cat_colors) . " }
};</script>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | UIU NewsHub</title>
    <link rel="icon" href="image.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        heading: ['"Outfit"', 'sans-serif'],
                    },
                     colors: {
                        jasmine: { DEFAULT: '#ffe588', 100: '#4f3e00', 200: '#9d7b00', 300: '#ecb900', 400: '#ffd53b', 500: '#ffe588', 600: '#ffeba1', 700: '#fff0b9', 800: '#fff5d0', 900: '#fffae8' },
                        tangerine_dream: { DEFAULT: '#f79d65', 100: '#421b03', 200: '#843707', 300: '#c6520a', 400: '#f37222', 500: '#f79d65', 600: '#f8b083', 700: '#fac4a2', 800: '#fcd8c1', 900: '#fdebe0' },
                        strawberry_red: { DEFAULT: '#f35252', 100: '#3d0404', 200: '#7a0808', 300: '#b70d0d', 400: '#ef1616', 500: '#f35252', 600: '#f57676', 700: '#f89898', 800: '#fababa', 900: '#fddddd' },
                        aquamarine: { DEFAULT: '#5ef2d5', 100: '#053e33', 200: '#0a7d66', 300: '#0fbb98', 400: '#20edc4', 500: '#5ef2d5', 600: '#7ff5dd', 700: '#9ff7e6', 800: '#bffaee', 900: '#dffcf7' },
                        cool_sky: { DEFAULT: '#60b5ff', 100: '#002646', 200: '#004b8d', 300: '#0071d3', 400: '#1b94ff', 500: '#60b5ff', 600: '#81c4ff', 700: '#a0d3ff', 800: '#c0e1ff', 900: '#dff0ff' }
                    },
                    boxShadow: {
                        'soft': '0 20px 40px -15px rgba(0, 0, 0, 0.05)',
                        'glow': '0 0 20px rgba(96, 181, 255, 0.35)',
                        'card': '0 0 0 1px rgba(0,0,0,0.03), 0 2px 8px rgba(0,0,0,0.04)',
                    }
                }
            }
        }
    </script>
    <style>
        .pattern-grid {
            background-color: #f8fafc;
            background-image: linear-gradient(#e2e8f0 1px, transparent 1px), linear-gradient(to right, #e2e8f0 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans selection:bg-cool_sky-500 selection:text-white">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="flex-1 overflow-y-auto pattern-grid relative">
            
            <!-- Header -->
            <header class="h-20 bg-white/70 backdrop-blur-xl border-b border-white/40 sticky top-0 z-10 px-8 flex justify-between items-center shadow-sm">
                <div>
                     <h2 class="font-heading text-xl font-bold text-slate-800">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!</h2>
                     <p class="text-xs text-slate-500 font-medium">Here's what's happening on campus today.</p>
                </div>
                <div class="flex items-center gap-4">
                    <button class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 shadow-sm hover:shadow-md transition-shadow relative">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        <span class="absolute top-2 right-2.5 w-2 h-2 bg-strawberry_red-500 rounded-full border border-white"></span>
                    </button>
                    <a href="index.php" target="_blank" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-full hover:bg-cool_sky-600 transition-colors shadow-lg shadow-slate-900/20 flex items-center gap-2">
                        View Live Site
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>
                </div>
            </header>

            <div class="p-8 max-w-7xl mx-auto space-y-8">
                
                <!-- Notification Banner for Admin -->
                <?php if ($user['role'] === 'admin' && ($stats['pending_news'] > 0 || $stats['pending_alerts'] > 0)): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between shadow-sm animate-pulse-slow">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-amber-800 text-sm">Action Required</h3>
                            <p class="text-xs text-amber-600 font-medium">
                                You have 
                                <?php if($stats['pending_news'] > 0) echo "{$stats['pending_news']} pending article(s)"; ?>
                                <?php if($stats['pending_news'] > 0 && $stats['pending_alerts'] > 0) echo " and "; ?>
                                <?php if($stats['pending_alerts'] > 0) echo "{$stats['pending_alerts']} pending alert(s)"; ?>
                                awaiting approval.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <?php if($stats['pending_news'] > 0): ?>
                            <a href="news_manage.php?status=pending" class="px-3 py-1.5 bg-white border border-amber-200 text-amber-700 text-xs font-bold rounded-lg hover:bg-amber-50">Review News</a>
                        <?php endif; ?>
                        <?php if($stats['pending_alerts'] > 0): ?>
                            <a href="alerts_manage.php" class="px-3 py-1.5 bg-white border border-amber-200 text-amber-700 text-xs font-bold rounded-lg hover:bg-amber-50">Review Alerts</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions Strip -->
                <div class="flex gap-4 mb-4 overflow-x-auto pb-2">
                    <a href="news_create.php" class="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:-translate-y-0.5 transition-all text-sm font-bold whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        New Article
                    </a>
                    <a href="alert_create.php" class="flex items-center gap-2 px-6 py-3 bg-white text-slate-700 rounded-xl border border-slate-200 shadow-sm hover:border-emerald-300 hover:text-emerald-700 transition-all text-sm font-bold whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        Create Alert
                    </a>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="news_manage.php?status=pending" class="flex items-center gap-2 px-6 py-3 bg-amber-50 text-amber-700 rounded-xl border border-amber-200 shadow-sm hover:bg-amber-100 transition-all text-sm font-bold whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                            Pending News
                            <span class="bg-amber-500 text-white text-[10px] px-2 py-0.5 rounded-full shadow-sm ml-1"><?php echo $stats['pending_news']; ?></span>
                        </a>
                        
                        <a href="alerts_manage.php" class="flex items-center gap-2 px-6 py-3 bg-amber-50 text-amber-700 rounded-xl border border-amber-200 shadow-sm hover:bg-amber-100 transition-all text-sm font-bold whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            Pending Alerts
                            <span class="bg-amber-500 text-white text-[10px] px-2 py-0.5 rounded-full shadow-sm ml-1"><?php echo $stats['pending_alerts']; ?></span>
                        </a>
                    <?php endif; ?>

                </div>

                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- ADMIN METRICS -->
                        <!-- Stat Card 1 -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 bg-cool_sky-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-cool_sky-100 text-cool_sky-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full">+12%</span>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo $stats['total_news']; ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Total News Articles</p>
                            </div>
                        </div>

                        <!-- Stat Card 2 -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 bg-strawberry_red-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-strawberry_red-100 text-strawberry_red-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    </div>
                                    
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo $stats['active_alerts']; ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Active Alerts</p>
                            </div>
                        </div>

                        <!-- Stat Card 3 -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                             <div class="absolute -right-6 -top-6 w-24 h-24 bg-aquamarine-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                             <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-aquamarine-100 text-aquamarine-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </div>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo number_format($stats['total_views']); ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Total Platform Views</p>
                            </div>
                        </div>

                        <!-- Stat Card 4 (Users) -->
                         <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                             <div class="absolute -right-6 -top-6 w-24 h-24 bg-tangerine_dream-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                             <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-tangerine_dream-100 text-tangerine_dream-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    </div>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800">3</h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Registered Users</p>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- MODERATOR METRICS -->
                        <!-- My Articles -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 bg-cool_sky-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-cool_sky-100 text-cool_sky-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </div>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo $stats['my_articles']; ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">My Total Articles</p>
                            </div>
                        </div>

                        <!-- Published -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full">Live</span>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo $stats['my_published']; ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Published Articles</p>
                            </div>
                        </div>

                        <!-- Pending -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <span class="text-xs font-bold text-amber-500 bg-amber-50 px-2 py-1 rounded-full">In Review</span>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo $stats['my_pending']; ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Pending Approval</p>
                            </div>
                        </div>

                        <!-- My Views -->
                        <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100 relative overflow-hidden group">
                             <div class="absolute -right-6 -top-6 w-24 h-24 bg-aquamarine-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                             <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 rounded-xl bg-aquamarine-100 text-aquamarine-600 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </div>
                                </div>
                                <h3 class="text-3xl font-heading font-bold text-slate-800"><?php echo number_format($stats['my_views']); ?></h3>
                                <p class="text-slate-500 text-sm font-medium mt-1">Total Views on My Posts</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending News Section (Admin Only) -->
                <?php if ($user['role'] === 'admin' && !empty($pendingNews)): ?>
                <div class="bg-white p-6 rounded-[2rem] shadow-soft border border-amber-100 mb-6">
                    <h3 class="font-heading font-bold text-lg text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Pending News Approval
                    </h3>
                    <div class="space-y-3">
                        <?php foreach($pendingNews as $news): ?>
                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white transition-all group">
                            <div class="flex items-center gap-4">
                                <img src="<?php echo $news['image_url'] ? htmlspecialchars($news['image_url']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=img'; ?>" class="w-12 h-12 rounded-lg object-cover shadow-sm">
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm line-clamp-1"><?php echo htmlspecialchars($news['title']); ?></h4>
                                    <div class="flex items-center gap-2 text-xs text-slate-500">
                                        <span><?php echo htmlspecialchars($news['category_name']); ?></span>
                                        <span>•</span>
                                        <span>By <?php echo htmlspecialchars($news['author_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="news_edit.php?id=<?php echo $news['news_id']; ?>" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-cool_sky-600 transition-colors shadow-lg shadow-slate-900/20">
                                    Review
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending Alerts Section (Admin Only) -->
                <?php if ($user['role'] === 'admin' && !empty($pendingAlerts)): ?>
                <div class="bg-white p-6 rounded-[2rem] shadow-soft border border-amber-100">
                    <h3 class="font-heading font-bold text-lg text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Pending Alerts Approval
                    </h3>
                    <div class="space-y-3">
                        <?php foreach($pendingAlerts as $alert): 
                             $color = ($alert['severity'] == 'danger') ? 'text-strawberry_red-600 bg-strawberry_red-50' : 'text-amber-600 bg-amber-50';
                        ?>
                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full <?php echo $color; ?> flex items-center justify-center text-lg">
                                    <?php echo ($alert['type'] == 'traffic') ? '🚦' : '⚠️'; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($alert['title']); ?></h4>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($alert['message']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="alerts_manage.php?approve_id=<?php echo $alert['alert_id']; ?>" class="px-4 py-2 bg-emerald-500 text-white text-xs font-bold rounded-lg hover:bg-emerald-600 transition-colors shadow-lg shadow-emerald-500/20">
                                    Approve Broadcast
                                </a>
                                <a href="alerts_manage.php" class="p-2 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Views Chart -->
                    <div class="lg:col-span-2 bg-white p-6 rounded-[2rem] shadow-card border border-slate-100">
                         <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-heading font-bold text-lg text-slate-800">Platform Analytics</h3>
                                <p class="text-xs text-slate-500 font-medium">Volume of new articles</p>
                            </div>
                         </div>
                        <div class="relative h-64 w-full">
                            <canvas id="viewsChart"></canvas>
                        </div>
                    </div>

                    <!-- Category Distribution -->
                     <div class="bg-white p-6 rounded-[2rem] shadow-card border border-slate-100">
                        <h3 class="font-heading font-bold text-lg text-slate-800 mb-6">Content Mix</h3>
                        <div class="relative h-48 w-full flex items-center justify-center">
                            <canvas id="categoryChart"></canvas>
                        </div>
                         <div class="mt-6 space-y-3">
                             <div class="flex justify-between items-center text-xs">
                                <span class="flex items-center gap-2 font-bold text-slate-600"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Academic</span>
                                <span class="font-bold text-slate-800">35%</span>
                            </div>
                             <div class="flex justify-between items-center text-xs">
                                <span class="flex items-center gap-2 font-bold text-slate-600"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Events</span>
                                <span class="font-bold text-slate-800">25%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="bg-white rounded-[2.5rem] shadow-soft border border-slate-100 overflow-hidden">
                    <div class="p-8 border-b border-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="font-heading font-bold text-xl text-slate-900">Recent Publications</h3>
                            <p class="text-sm text-slate-500 mt-1">Manage the latest updates posted to the platform.</p>
                        </div>
                        
                        <div class="flex gap-2">
                            <div class="relative">
                                <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:border-cool_sky-400 w-48 transition-all">
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <button class="px-4 py-2 bg-slate-50 text-slate-600 rounded-xl text-sm font-bold border border-slate-200 hover:bg-slate-100">Filter</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 text-slate-400 text-xs font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="px-8 py-4 font-bold">Article Details</th>
                                    <th class="px-6 py-4 font-bold">Category</th>
                                    <th class="px-6 py-4 font-bold">Author</th>
                                    <th class="px-6 py-4 font-bold">Views</th>
                                    <th class="px-6 py-4 font-bold">Status</th>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($recentNews as $item): 
                                    $imgUrl = $item['image_url'] ? $item['image_url'] : "https://placehold.co/100x100/e2e8f0/64748b?text=img";
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-8 py-4">
                                        <div class="flex items-center gap-4">
                                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=img'" class="w-12 h-12 rounded-lg object-cover shadow-sm bg-slate-100 border border-slate-100">
                                            <div>
                                                <a href="news_edit.php?id=<?php echo $item['news_id']; ?>" class="font-bold text-slate-800 line-clamp-1 max-w-[200px] hover:text-cool_sky-600 transition-colors"><?php echo htmlspecialchars($item['title']); ?></a>
                                                <div class="text-xs text-slate-400 mt-1 font-medium"><?php echo date('M d, Y • h:i A', strtotime($item['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-slate-100/80 text-slate-600 border border-slate-200/50">
                                            <?php echo htmlspecialchars($item['category_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                             <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-[10px] font-bold flex items-center justify-center">
                                                <?php echo substr($item['author_name'], 0, 1); ?>
                                             </div>
                                             <span class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($item['author_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-700">
                                        <?php echo number_format($item['views']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($item['status'] == 'published'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Published
                                            </span>
                                        <?php elseif($item['status'] == 'pending'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                Pending Approval
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a href="news_edit.php?id=<?php echo $item['news_id']; ?>" class="p-2 text-slate-400 hover:text-cool_sky-600 hover:bg-cool_sky-50 rounded-lg transition-colors" title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </a>
                                            <a href="news_manage.php?delete_id=<?php echo $item['news_id']; ?>" onclick="return confirm('Are you sure you want to delete this article?');" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Footer -->
                    <div class="px-8 py-5 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center">
                       
                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-white border border-slate-200 text-slate-400 rounded-lg text-xs font-bold hover:bg-slate-50 disabled:opacity-50" disabled>Previous</button>
                            <button class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50 hover:text-cool_sky-600">Next</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <?php include 'includes/dashboard_charts.php'; ?>
</body>
</html>

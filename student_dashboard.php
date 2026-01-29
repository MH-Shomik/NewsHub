<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Students Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// 1. Fetch User Info (Simulated)
$userInfo = [
    'name' => $user_name,
    'id' => $_SESSION['user_id'], 
    'department' => 'CSE', 
    'credits_completed' => 85,
    'cgpa' => 3.76,
    'avatar' => "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=0f172a&color=fff&size=128"
];

// 2. Fetch "For You" Feed
try {
    $feedStmt = $pdo->query("
        SELECT n.*, c.name as category_name, c.slug as category_slug
        FROM news n 
        JOIN categories c ON n.category_id = c.category_id 
        WHERE c.slug IN ('academic', 'notice') AND n.status = 'published'
        ORDER BY n.created_at DESC LIMIT 6
    ");
    $personalizedFeed = $feedStmt->fetchAll();
} catch (PDOException $e) { $personalizedFeed = []; }

// 3. Fetch Trending News
try {
    $trendingStmt = $pdo->query("SELECT news_id, title, views, created_at FROM news WHERE status='published' ORDER BY views DESC LIMIT 5");
    $trendingNews = $trendingStmt->fetchAll();
} catch (PDOException $e) { $trendingNews = []; }

// 4. Fetch Active Alerts
try {
    $alertStmt = $pdo->query("SELECT * FROM alerts WHERE is_active = 1 ORDER BY created_at DESC");
    $activeAlerts = $alertStmt->fetchAll();
} catch (PDOException $e) { $activeAlerts = []; }

function getCategoryColor($slug) {
    $map = [
        'academic' => 'bg-indigo-500 text-white',
        'notice'   => 'bg-amber-400 text-slate-900',
        'research' => 'bg-emerald-500 text-white',
        'events'   => 'bg-rose-500 text-white',
        'sports'   => 'bg-orange-500 text-white'
    ];
    return $map[$slug] ?? 'bg-slate-500 text-white';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | UIU NewsHub</title>
    <link rel="icon" href="image.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
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
                        primary: { DEFAULT: '#6366f1', 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 900: '#312e81' },
                        surface: '#ffffff',
                    },
                     boxShadow: {
                        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.07)',
                        'soft': '0 10px 40px -10px rgba(0,0,0,0.05)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f3f4f6;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-size: 100% 600px;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .glass-panel-dark {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        .ticker-wrap {
            width: 100%;
            overflow: hidden;
            box-sizing: border-box;
            white-space: nowrap;
        }
        .ticker {
            display: inline-block;
            padding-left: 100%;
            animation: ticker 50s linear infinite;
        }
        @keyframes ticker {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
        }
        .animate-bounce-in { animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes bounceIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body class="text-slate-700">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200 shadow-sm">
        <div class="max-w-[1600px] mx-auto px-4 md:px-8 h-20 flex justify-between items-center">
            
            <!-- Logo Section -->
            <div class="flex items-center gap-4">
                 <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
                     <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                 </div>
                 <div class="hidden md:block">
                     <span class="block font-heading font-extrabold text-xl text-slate-800 leading-none tracking-tight">UIU NewsHub</span>
                     <span class="text-[0.65rem] font-bold text-indigo-500 tracking-[0.2em] uppercase">Student Portal</span>
                 </div>
            </div>

            <!-- Central Search Bar (Expanded) -->
            <div class="flex-1 max-w-2xl mx-8 hidden md:block">
                <form action="search.php" method="GET" class="relative group">
                    <input type="text" name="q" placeholder="Search for news, notices, or events..." 
                           class="w-full bg-slate-100/50 border border-slate-200 rounded-full py-3 pl-12 pr-4 text-sm font-medium text-slate-700 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-sm">
                    <svg class="w-5 h-5 text-slate-400 absolute left-4 top-3 transition-colors group-focus-within:text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <div class="absolute right-2 top-2 hidden group-focus-within:flex gap-1">
                        <span class="text-[0.6rem] font-bold bg-slate-100 border border-slate-200 text-slate-500 px-2 py-1 rounded-md">ESC</span>
                    </div>
                </form>
            </div>

            <!-- User Actions -->
            <div class="flex items-center gap-3 md:gap-6">
                <!-- Notifications -->
                <button class="relative w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all group">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <?php if(count($activeAlerts) > 0): ?>
                    <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                    <?php endif; ?>
                </button>

                <!-- Profile Dropdown Trigger -->
                <div class="flex items-center gap-3 pl-6 border-l border-slate-200">
                    <div class="text-right hidden lg:block">
                        <span class="block text-sm font-bold text-slate-800 leading-none mb-1"><?php echo htmlspecialchars($userInfo['name']); ?></span>
                        <span class="block text-[0.65rem] font-bold text-slate-400 uppercase tracking-wide"><?php echo htmlspecialchars($userInfo['department']); ?> • <?php echo $userInfo['id']; ?></span>
                    </div>
                    <div class="relative group cursor-pointer">
                        <div class="w-10 h-10 rounded-full p-0.5 bg-gradient-to-tr from-indigo-500 to-purple-500 shadow-md transition-transform group-hover:scale-105">
                            <img src="<?php echo $userInfo['avatar']; ?>" class="w-full h-full rounded-full border-2 border-white bg-slate-100 object-cover">
                        </div>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-4 w-56 bg-white rounded-2xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 z-[100]">
                            <div class="p-2 space-y-1">
                                <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    My Profile
                                </a>
                                <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    Settings
                                </a>
                                <div class="h-px bg-slate-100 my-1"></div>
                                <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-red-50 text-sm font-bold text-slate-500 hover:text-red-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-[1600px] mx-auto px-4 md:px-8 pb-12">
        
        <!-- Alerts Ticker (Only plays if alerts exist) -->
        <div id="alert-banner" class="mt-6 mb-6 hidden">
             <div class="glass-panel bg-white-500/10 border-red-200 px-4 py-3 rounded-xl flex items-center gap-4 overflow-hidden relative">
                 <div class="bg-red-500 text-red text-xs font-bold px-3 py-1 rounded-lg animate-pulse whitespace-nowrap z-10">LIVE ALERT</div>
                 <div class="flex-1 ticker-wrap overflow-hidden">
                     <div id="alert-ticker" class="ticker text-sm font-medium text-white-200"></div>
                 </div>
             </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left Column: Personal Dashboard (3 Cols) -->
            <aside class="lg:col-span-3 space-y-6">
                
                <!-- Profile & Stats Card -->
                <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                     <!-- Greeting -->
                     <div class="relative z-10 text-center mb-6">
                         <?php 
                            $hour = date('H');
                            $greeting = ($hour < 12) ? 'Good Morning' : (($hour < 18) ? 'Good Afternoon' : 'Good Evening');
                         ?>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1"><?php echo $greeting; ?>,</p>
                        <h2 class="font-heading text-3xl font-extrabold text-slate-800"><?php echo explode(' ', $userInfo['name'])[0]; ?></h2>
                     </div>

                     <div class="flex justify-center mb-8">
                        <div class="relative">
                            <div class="w-28 h-28 rounded-full p-1 bg-gradient-to-tr from-indigo-500 to-purple-500 shadow-xl shadow-indigo-500/20 group-hover:scale-105 transition-transform duration-500">
                                <img src="<?php echo $userInfo['avatar']; ?>" class="w-full h-full rounded-full border-4 border-white object-cover">
                            </div>
                            <span class="absolute bottom-1 right-1 px-3 py-1 bg-white rounded-full text-[0.65rem] font-black text-emerald-500 border border-slate-100 shadow-sm flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ONLINE
                            </span>
                        </div>
                     </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center hover:bg-indigo-50 hover:border-indigo-100 transition-colors group/stat">
                            <span class="block text-3xl font-heading font-black text-slate-800 group-hover/stat:text-indigo-600 transition-colors"><?php echo $userInfo['cgpa']; ?></span>
                            <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-1 block">CGPA</span>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center hover:bg-purple-50 hover:border-purple-100 transition-colors group/stat">
                            <span class="block text-3xl font-heading font-black text-slate-800 group-hover/stat:text-purple-600 transition-colors"><?php echo $userInfo['credits_completed']; ?></span>
                            <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-1 block">Credits</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Menu -->
                <div class="bg-white/50 backdrop-blur-md rounded-3xl p-2 grid grid-cols-2 gap-2 shadow-sm border border-white/40">
                    <a href="index.php" class="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border border-slate-100 hover:border-indigo-200 hover:shadow-md transition-all group cursor-pointer">
                        <div class="w-10 h-10 mb-2 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-600">News Feed</span>
                    </a>
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border border-slate-100 hover:border-emerald-200 hover:shadow-md transition-all group cursor-pointer">
                        <div class="w-10 h-10 mb-2 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-600">Routine</span>
                    </a>
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border border-slate-100 hover:border-orange-200 hover:shadow-md transition-all group cursor-pointer">
                        <div class="w-10 h-10 mb-2 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center group-hover:bg-orange-500 group-hover:text-white transition-colors">
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-600">Payment</span>
                    </a>
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border border-slate-100 hover:border-pink-200 hover:shadow-md transition-all group cursor-pointer">
                        <div class="w-10 h-10 mb-2 rounded-full bg-pink-50 text-pink-500 flex items-center justify-center group-hover:bg-pink-500 group-hover:text-white transition-colors">
                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-600">Support</span>
                    </a>
                </div>

            </aside>

            <!-- Center Column: Main Feed -->
            <div class="lg:col-span-6 space-y-6">
                
                <!-- 1. Active Alerts Section (Prominent) -->
                <div class="glass-panel p-5 rounded-3xl relative overflow-hidden border-2 border-red-100/50">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-red-500/10 rounded-full blur-2xl"></div>
                    <div class="flex items-center justify-between mb-4 relative z-10">
                        <h3 class="font-heading font-bold text-slate-800 flex items-center gap-2 text-lg">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                            System Alerts
                        </h3>
                        <span class="text-xs font-bold text-red-500 bg-red-50 border border-red-100 px-3 py-1 rounded-full shadow-sm">
                            <?php echo count($activeAlerts); ?> Active
                        </span>
                    </div>

                    <?php if(!empty($activeAlerts)): ?>
                        <div class="space-y-3 relative z-10">
                            <?php foreach($activeAlerts as $alert): 
                                $severityClass = $alert['severity'] === 'danger' ? 'bg-red-500 text-white shadow-lg shadow-red-500/20' : ($alert['severity'] === 'warning' ? 'bg-amber-400 text-slate-900' : 'bg-blue-500 text-white');
                                $icon = $alert['severity'] === 'danger' ? '🚨' : ($alert['severity'] === 'warning' ? '⚠️' : 'ℹ️');
                            ?>
                            <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-sm flex gap-4 items-start transition-transform hover:scale-[1.01]">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-xl flex-shrink-0 <?php echo $severityClass; ?>">
                                    <?php echo $icon; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 text-base leading-tight mb-1"><?php echo htmlspecialchars($alert['title']); ?></h4>
                                    <p class="text-sm text-slate-500 leading-relaxed"><?php echo htmlspecialchars($alert['message']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 text-slate-400 text-sm bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <span class="block text-2xl mb-1">🛡️</span>
                            No active system alerts at the moment.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. Notices & Feed Header -->
                <div class="flex items-center justify-between">
                    <h3 class="font-heading text-xl font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-2 h-8 rounded-full bg-indigo-500"></span>
                        Notice Board & Updates
                    </h3>
                </div>

                <!-- Personalized Feed Container -->
                <div id="news-feed-container" class="space-y-4">
                     <!-- Populated by PHP initially, refreshed by JS -->
                     <?php foreach($personalizedFeed as $news): ?>
                        <div class="glass-panel p-6 rounded-3xl hover:-translate-y-1 transition-transform duration-300">
                            <div class="flex items-start gap-4">
                                <div class="hidden sm:flex flex-col items-center justify-center bg-indigo-50/50 w-16 h-16 rounded-2xl border border-indigo-100 flex-shrink-0">
                                    <span class="text-[0.6rem] font-bold text-indigo-400 uppercase tracking-widest"><?php echo date('M', strtotime($news['created_at'])); ?></span>
                                    <span class="text-xl font-heading font-black text-slate-700"><?php echo date('d', strtotime($news['created_at'])); ?></span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="<?php echo getCategoryColor($news['category_slug']); ?> text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                                            <?php echo htmlspecialchars($news['category_name']); ?>
                                        </span>
                                        <span class="text-xs font-bold text-slate-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <?php echo date('h:i A', strtotime($news['created_at'])); ?>
                                        </span>
                                    </div>
                                    <h2 class="font-heading text-xl font-extrabold text-slate-800 mb-2 leading-snug hover:text-indigo-600 transition-colors">
                                        <a href="news_details.php?id=<?php echo $news['news_id']; ?>"><?php echo htmlspecialchars($news['title']); ?></a>
                                    </h2>
                                    <p class="text-slate-500 text-sm leading-relaxed mb-4 line-clamp-2">
                                        <?php echo htmlspecialchars(substr(strip_tags($news['content']), 0, 150)) . '...'; ?>
                                    </p>
                                    <div class="flex items-center gap-4 border-t border-slate-100 pt-3">
                                        <a href="news_details.php?id=<?php echo $news['news_id']; ?>" class="text-xs font-bold text-indigo-500 hover:text-indigo-700 uppercase tracking-wide flex items-center gap-1">
                                            Read Full Notice 
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                     <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: Alerts & Side Widgets -->
            <aside class="lg:col-span-3 space-y-6">
                
                <!-- Quick Links (Replaces Alerts) -->
                <div class="glass-panel p-5 rounded-3xl">
                    <h3 class="font-heading font-bold text-slate-800 mb-4 flex items-center gap-2">
                        🔗 Quick Links
                    </h3>
                    <div class="space-y-2">
                         <a href="https://examcon.uiu.ac.bd/" class="block px-4 py-3 bg-white rounded-xl border border-slate-100 text-slate-600 text-sm font-bold hover:border-indigo-200 hover:text-indigo-600 transition-all flex justify-between items-center group">
                            <span>Exam Schedule</span>
                            <span class="text-slate-300 group-hover:text-indigo-400">→</span>
                        </a>
                        <a href="https://ucam.uiu.ac.bd/Security/Login.aspx" class="block px-4 py-3 bg-white rounded-xl border border-slate-100 text-slate-600 text-sm font-bold hover:border-indigo-200 hover:text-indigo-600 transition-all flex justify-between items-center group">
                            <span>My Grades</span>
                            <span class="text-slate-300 group-hover:text-indigo-400">→</span>
                        </a>
                    </div>
                </div>

                <!-- Trending / Buzz -->
                <div class="glass-panel p-5 rounded-3xl">
                    <h3 class="font-heading font-bold text-slate-800 mb-4 flex items-center gap-2">
                         <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Upcoming
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 group cursor-pointer">
                            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex flex-col items-center justify-center font-bold text-[0.6rem] border border-orange-100 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                                <span>JAN</span><span class="text-base leading-none">12</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-700 text-sm group-hover:text-orange-600 transition-colors">Project Deadline</h4>
                                <span class="text-xs text-slate-400 block">Software Engineering</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 group cursor-pointer">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex flex-col items-center justify-center font-bold text-[0.6rem] border border-indigo-100 group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                                <span>JAN</span><span class="text-base leading-none">15</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-700 text-sm group-hover:text-indigo-600 transition-colors">Course Advising</h4>
                                <span class="text-xs text-slate-400 block">Spring 2026</span>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Trending News List -->
                 <div class="glass-panel p-5 rounded-3xl">
                     <h3 class="font-heading font-bold text-slate-800 mb-4">Trending Now</h3>
                     <ul class="space-y-4">
                         <?php foreach($trendingNews as $idx => $trend): ?>
                         <li class="flex gap-3 items-start group">
                             <span class="text-2xl font-black text-slate-200 group-hover:text-indigo-500 transition-colors -mt-1">0<?php echo $idx+1; ?></span>
                             <div>
                                 <a href="news_details.php?id=<?php echo $trend['news_id']; ?>" class="font-bold text-slate-700 text-sm leading-snug group-hover:text-indigo-600 transition-colors line-clamp-2">
                                     <?php echo htmlspecialchars($trend['title']); ?>
                                 </a>
                                 <span class="text-[0.6rem] font-bold text-slate-400 uppercase tracking-wide mt-1 block"><?php echo number_format($trend['views']); ?> reads</span>
                             </div>
                         </li>
                         <?php endforeach; ?>
                     </ul>
                 </div>

            </aside>
        </div>
    </div>

    <!-- Emergency Modal -->
    <div id="emergency-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md transition-opacity" onclick="closeModal()"></div>
        <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full overflow-hidden relative transform transition-all scale-100 animate-bounce-in border-4 border-red-500">
            <div class="bg-red-500 p-8 text-white text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10"></div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg animate-pulse text-4xl">🚨</div>
                    <h2 class="font-heading text-3xl font-black uppercase tracking-widest leading-none">Emergency</h2>
                    <span class="text-red-100 font-bold tracking-widest text-xs mt-1">CAMPUS ALERT SYSTEM</span>
                </div>
            </div>
            <div class="p-8 text-center bg-white relative z-20">
                <h3 id="modal-title" class="font-heading font-extrabold text-2xl text-slate-900 mb-3"></h3>
                <p id="modal-message" class="text-slate-600 leading-relaxed mb-8 text-lg bg-red-50 p-6 rounded-2xl border border-red-100"></p>
                <button onclick="closeModal()" class="w-full py-4 bg-slate-900 text-white font-bold rounded-2xl hover:bg-red-600 transition-colors shadow-xl transform active:scale-95 text-lg">
                    I Acknowledge
                </button>
            </div>
        </div>
    </div>

    <script>
        let seenAlerts = new Set();
        function closeModal() { document.getElementById('emergency-modal').classList.add('hidden'); }

        // Determine greeting based on time
        (function setGreeting() {
            const hour = new Date().getHours();
            const msg = hour < 12 ? 'Good Morning,' : (hour < 18 ? 'Good Afternoon,' : 'Good Evening,');
            // Assuming the greeting element is the "Good Morning details," one
            document.querySelector('h2.font-heading.text-xl').innerText = msg;
        })();

        // Feed Polling
        function getCategoryColorJS(slug) {
            const map = {
                'academic': 'bg-indigo-500 text-white',
                'notice':   'bg-amber-400 text-slate-900',
                'research': 'bg-emerald-500 text-white',
                'events':   'bg-rose-500 text-white',
                'sports':   'bg-orange-500 text-white'
            };
            return map[slug] || 'bg-slate-500 text-white';
        }

        async function fetchFeed() {
             try {
                const res = await fetch('api/get_feed.php?category=feed');
                const data = await res.json();
                if (data.status === 'success' && data.data.length > 0) {
                    const con = document.getElementById('news-feed-container');
                    let html = '';
                    data.data.forEach(n => {
                        const d = new Date(n.created_at);
                        const month = d.toLocaleString('default', { month: 'short' }).toUpperCase();
                        const day = String(d.getDate()).padStart(2, '0');
                        const time = d.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
                        const color = getCategoryColorJS(n.category_slug);
                        
                        const temp = document.createElement("div"); temp.innerHTML = n.content;
                        const preview = (temp.textContent || temp.innerText || "").substring(0, 150) + '...';

                        const isNotice = n.category_slug === 'notice';
                        const noticeHighlight = isNotice ? '<div class="absolute left-0 top-0 bottom-0 w-1.5 bg-amber-400"></div>' : '';
                        const pulse = isNotice ? '<span class="animate-pulse w-2 h-2 rounded-full bg-amber-400"></span>' : '';

                        html += `
                        <div class="glass-panel p-6 rounded-3xl hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden group">
                            ${noticeHighlight}
                            <div class="flex items-start gap-4">
                                <div class="hidden sm:flex flex-col items-center justify-center bg-indigo-50/50 w-20 h-20 rounded-2xl border border-indigo-100 flex-shrink-0 group-hover:bg-white transition-colors shadow-sm">
                                    <span class="text-[0.6rem] font-bold text-indigo-400 uppercase tracking-widest">${month}</span>
                                    <span class="text-2xl font-heading font-black text-slate-700">${day}</span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="${color} text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider shadow-sm">${n.category_name}</span>
                                            ${pulse}
                                        </div>
                                        <span class="text-xs font-bold text-slate-400 flex items-center gap-1 bg-white px-2 py-1 rounded-md shadow-sm border border-slate-50">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            ${time}
                                        </span>
                                    </div>
                                    <h2 class="font-heading text-2xl font-bold text-slate-900 mb-3 leading-snug hover:text-indigo-600 transition-colors">
                                        <a href="news_details.php?id=${n.news_id}">${n.title}</a>
                                    </h2>
                                    <p class="text-slate-500 text-sm leading-relaxed mb-4 line-clamp-2">${preview}</p>
                                    <div class="flex items-center gap-4 border-t border-slate-100 pt-4">
                                        <a href="news_details.php?id=${n.news_id}" class="px-4 py-2 bg-slate-50 hover:bg-indigo-50 text-indigo-600 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 group-hover:shadow-sm">
                                            Read Full Notice 
                                            <svg class="w-3 h-3 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                    con.innerHTML = html;
                }
             } catch(e) { console.error(e); }
        }

        // Alerts Polling
        async function fetchAlerts() {
            try {
                const res = await fetch('api/get_alerts.php');
                const data = await res.json();
                const banner = document.getElementById('alert-banner');
                const ticker = document.getElementById('alert-ticker');
                
                if (data.status === 'success' && data.data.length > 0) {
                    banner.classList.remove('hidden');
                    let html = '', hasDanger = false, dangerAlert = null;
                    data.data.forEach(a => {
                         html += `<span class="inline-block mr-12 font-bold">${a.title}: <span class="font-normal text-red-900/70">${a.message}</span></span>`;
                         if(a.severity === 'danger') { hasDanger = true; dangerAlert = a; }
                    });
                    ticker.innerHTML = html;

                    if (hasDanger && dangerAlert && !seenAlerts.has(dangerAlert.alert_id)) {
                        document.getElementById('modal-title').innerText = dangerAlert.title;
                        document.getElementById('modal-message').innerText = dangerAlert.message;
                        document.getElementById('emergency-modal').classList.remove('hidden');
                        seenAlerts.add(dangerAlert.alert_id);
                    }
                } else {
                    banner.classList.add('hidden');
                }
            } catch(e) { console.error(e); }
        }

        setInterval(fetchFeed, 60000);
        setInterval(fetchAlerts, 10000);
        fetchAlerts();
    </script>
</body>
</html>

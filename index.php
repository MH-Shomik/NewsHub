<?php
session_start();
require_once 'includes/db_connect.php';

// Helper to map DB categories to new Palette Colors
function getCategoryColor($slug) {
    // Returns Tailwind classes based on category slug
    $map = [
        'academic' => 'bg-cool_sky-500 text-white',
        'research' => 'bg-aquamarine-500 text-slate-900',
        'events'   => 'bg-tangerine_dream-500 text-white',
        'sports'   => 'bg-strawberry_red-500 text-white',
        'notice'   => 'bg-jasmine-400 text-slate-900',
    ];
    return $map[$slug] ?? 'bg-slate-700 text-white';
}

function getPlaceholderImage($category, $seed) {
    // Generates a relevant distinct placeholder color based on category
    // Using placehold.co with custom colors from our palette
    $colors = [
        'academic' => '60b5ff', // cool_sky
        'research' => '5ef2d5', // aquamarine
        'events' => 'f79d65',   // tangerine
        'sports' => 'f35252',   // strawberry
        'notice' => 'ffe588',   // jasmine
    ];
    $hex = $colors[$category] ?? 'cbd5e1';
    $textHex = ($category == 'notice' || $category == 'research') ? '0f172a' : 'ffffff';
    // Use placehold.co
    return "https://placehold.co/800x600/$hex/$textHex?text=" . urlencode(ucfirst($category) . "+Update");
}


// 1. Fetch Active Alerts
try {
    $alertStmt = $pdo->query("SELECT * FROM alerts WHERE is_active = 1 AND (expires_at > NOW() OR expires_at IS NULL) ORDER BY created_at DESC");
    $alerts = $alertStmt->fetchAll();
} catch (PDOException $e) { $alerts = []; }

// 2. Fetch Hero Slides (Featured first, then Urgent)
// Capture category filter
// 2. Fetch Hero Slides (Featured first, then Urgent)
try {
    $heroStmt = $pdo->query("
        SELECT n.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
        FROM news n 
        JOIN categories c ON n.category_id = c.category_id 
        JOIN users u ON n.author_id = u.user_id 
        WHERE n.status = 'published' AND (n.is_hero = 1 OR n.is_urgent = 1)
        ORDER BY n.is_hero DESC, n.created_at DESC LIMIT 5
    ");
    $heroSlides = $heroStmt->fetchAll();
} catch (PDOException $e) { $heroSlides = []; }

// 3. Fetch Latest News
try {
    $sql = "
        SELECT n.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
        FROM news n 
        JOIN categories c ON n.category_id = c.category_id 
        JOIN users u ON n.author_id = u.user_id 
        WHERE n.status = 'published' 
    ";
    
    if (!empty($heroSlides)) {
        $excludeIds = array_map(function($h) { return $h['news_id']; }, $heroSlides);
        if (!empty($excludeIds)) {
            $sql .= " AND n.news_id NOT IN (" . implode(',', $excludeIds) . ")";
        }
    }
    
    $sql .= " ORDER BY n.created_at DESC LIMIT 9";
    
    $newsStmt = $pdo->query($sql);
    $latestNews = $newsStmt->fetchAll();
} catch (PDOException $e) { $latestNews = []; }

// 4. Fetch Categories
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) { $categories = []; }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UIU News Hub | Campus Pulse</title>
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
                        jasmine: { DEFAULT: '#ffe588', 100: '#4f3e00', 200: '#9d7b00', 300: '#ecb900', 400: '#ffd53b', 500: '#ffe588', 600: '#ffeba1', 700: '#fff0b9', 800: '#fff5d0', 900: '#fffae8' },
                        tangerine_dream: { DEFAULT: '#f79d65', 100: '#421b03', 200: '#843707', 300: '#c6520a', 400: '#f37222', 500: '#f79d65', 600: '#f8b083', 700: '#fac4a2', 800: '#fcd8c1', 900: '#fdebe0' },
                        strawberry_red: { DEFAULT: '#f35252', 100: '#3d0404', 200: '#7a0808', 300: '#b70d0d', 400: '#ef1616', 500: '#f35252', 600: '#f57676', 700: '#f89898', 800: '#fababa', 900: '#fddddd' },
                        aquamarine: { DEFAULT: '#5ef2d5', 100: '#053e33', 200: '#0a7d66', 300: '#0fbb98', 400: '#20edc4', 500: '#5ef2d5', 600: '#7ff5dd', 700: '#9ff7e6', 800: '#bffaee', 900: '#dffcf7' },
                        cool_sky: { DEFAULT: '#60b5ff', 100: '#002646', 200: '#004b8d', 300: '#0071d3', 400: '#1b94ff', 500: '#60b5ff', 600: '#81c4ff', 700: '#a0d3ff', 800: '#c0e1ff', 900: '#dff0ff' }
                    },
                    boxShadow: {
                        'soft': '0 20px 40px -15px rgba(0, 0, 0, 0.05)',
                        'glow': '0 0 20px rgba(96, 181, 255, 0.35)',
                    },
                    animation: {
                        'marquee': 'marquee 50s linear infinite',
                        'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                        'blob': 'blob 3s infinite',
                    },
                    keyframes: {
                        marquee: {
                            '0%': { transform: 'translateX(0%)' },
                            '100%': { transform: 'translateX(-100%)' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }
        .hero-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .text-shadow-sm {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-cool_sky-500 selection:text-white overflow-x-hidden">

    <!-- Alerts Banner (Dynamic) -->
    <div id="alert-banner" class="bg-slate-900 border-b border-slate-800 text-white overflow-hidden relative z-50 <?php echo empty($alerts) ? 'hidden' : ''; ?>">
        <div class="max-w-7xl mx-auto flex items-center h-10">
            <div class="bg-strawberry_red-600 text-white font-bold px-4 h-full flex items-center text-xs tracking-wider uppercase flex-shrink-0 z-10 shadow-lg relative">
                <span class="animate-pulse mr-2">●</span> Live Alerts
                <div class="absolute -right-2 top-0 bottom-0 w-4 bg-strawberry_red-600 transform skew-x-12 origin-bottom"></div>
            </div>
            <div class="flex-1 overflow-hidden relative h-full flex items-center bg-slate-900/50 backdrop-blur-sm">
                <div id="alert-ticker" class="animate-marquee whitespace-nowrap flex gap-16 items-center text-xs font-semibold pl-8" >
                    <?php if(!empty($alerts)): ?>
                        <?php foreach ($alerts as $alert): 
                            $icon = 'INFO';
                            $color = 'text-aquamarine-400';
                            if ($alert['severity'] == 'warning') { $color = 'text-jasmine-400'; $icon = '⚠️'; }
                            if ($alert['severity'] == 'danger')  { $color = 'text-strawberry_red-300'; $icon = '🚨'; }
                        ?>
                        <span class="inline-flex items-center gap-2">
                            <span class="<?php echo $color; ?>"><?php echo $icon; ?></span>
                            <span class="text-white tracking-wide"><?php echo htmlspecialchars($alert['title']); ?>:</span>
                            <span class="text-slate-400 font-normal"><?php echo htmlspecialchars($alert['message']); ?></span>
                        </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
    <!-- Student Logged In Nav -->
    <nav class="sticky top-0 w-full z-40 glass-nav transition-all duration-300 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="student_dashboard.php" class="flex items-center gap-3 group">
                    <div class="w-12 h-12">
                        <img src="image.png" alt="Logo" class="w-full h-full object-contain rounded-full drop-shadow-md group-hover:scale-110 transition-transform">
                    </div>
                    <span class="font-heading font-bold text-2xl tracking-tight text-slate-900">UIU <span class="text-cool_sky-500">NewsHub</span></span>
                </a>
                <div class="flex items-center gap-4">
                    <a href="student_dashboard.php" class="px-6 py-2.5 rounded-full bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider hover:bg-cool_sky-50 hover:text-cool_sky-600 transition-colors border border-slate-200">Dashboard</a>
                    <a href="logout.php" class="px-6 py-2.5 rounded-full bg-slate-900 text-white font-bold text-xs uppercase tracking-wider hover:bg-strawberry_red-500 transition-colors shadow-lg shadow-slate-900/10">Sign Out</a>
                </div>
            </div>
        </div>
    </nav>
    <?php else: ?>
    <!-- Public Nav -->
    <nav class="sticky top-0 w-full z-40 glass-nav transition-all duration-300 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-12 h-12">
                         <img src="image.png" alt="Logo" class="w-full h-full object-contain rounded-full drop-shadow-md group-hover:scale-110 transition-transform">
                    </div>
                    <span class="font-heading font-bold text-2xl tracking-tight text-slate-900">
                        UIU <span class="text-cool_sky-500">NewsHub</span>
                    </span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-2 items-center bg-white/60 p-1.5 rounded-full border border-white/60 shadow-sm backdrop-blur-md">
                    <a href="index.php" class="px-6 py-2 rounded-full bg-slate-900 text-white font-bold text-xs uppercase tracking-wider shadow-md">Home</a>
                    <a href="#categories" class="px-6 py-2 rounded-full text-slate-600 hover:text-cool_sky-600 font-bold text-xs uppercase tracking-wider transition-colors hover:bg-white/80">Categories</a>
                    
                    <a href="search.php" class="px-6 py-2 rounded-full text-cool_sky-600 hover:text-cool_sky-700 font-bold text-xs uppercase tracking-wider transition-colors bg-cool_sky-50 hover:bg-cool_sky-100 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        Search
                    </a>
                </div>

                <div class="hidden md:flex items-center gap-4">
                     <a href="login.php" class="group relative px-6 py-2.5 rounded-full bg-white border-2 border-slate-900 text-slate-900 font-bold text-xs uppercase tracking-wider hover:bg-slate-900 hover:text-white transition-all duration-300 overflow-hidden">
                        <span class="relative z-10">Login</span>
                        <div class="absolute inset-0 bg-slate-900 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-300 ease-out"></div>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="hero-pattern min-h-screen pb-20 pt-8 overflow-hidden">

        <!-- Background Blobs -->
        <div class="absolute top-0 left-0 w-full h-[600px] overflow-hidden -z-10 pointer-events-none">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-cool_sky-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
            <div class="absolute top-0 right-1/4 w-96 h-96 bg-aquamarine-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
            <div class="absolute -bottom-32 left-1/2 w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
        </div>

        <!-- Hero Section -->
        <!-- Hero Section -->
        <?php if (!empty($heroSlides)): ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6 -mt-6 relative">
            <div id="hero-carousel" class="relative rounded-[2.5rem] overflow-hidden shadow-2xl h-[600px] border border-white/20 group">
                
                <?php foreach($heroSlides as $index => $heroNews): ?>
                <div class="hero-slide absolute inset-0 transition-opacity duration-700 ease-in-out <?php echo $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0'; ?>" data-index="<?php echo $index; ?>">
                    <!-- Parallax Background Image -->
                    <img src="<?php echo $heroNews['image_url'] ? htmlspecialchars($heroNews['image_url']) : getPlaceholderImage($heroNews['category_slug'], 0); ?>" 
                         alt="Top Story" 
                         onerror="this.onerror=null;this.src='<?php echo getPlaceholderImage($heroNews['category_slug'], 0); ?>';"
                         class="absolute inset-0 w-full h-full object-cover">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent opacity-90"></div>
                    
                    <!-- Hero Content -->
                    <div class="absolute bottom-0 left-0 p-8 md:p-16 w-full md:w-4/5 lg:w-2/3 flex flex-col items-start z-10">
                        <div class="flex flex-wrap items-center gap-3 mb-6 animate-fade-in-up">
                            <?php if($heroNews['is_urgent']): ?>
                            <span class="bg-strawberry_red-500/90 backdrop-blur-sm text-white text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-wider shadow-lg shadow-strawberry_red-500/30 ring-1 ring-white/20">
                                Urgent Update
                            </span>
                            <?php endif; ?>
                            <span class="<?php echo getCategoryColor($heroNews['category_slug']); ?> bg-opacity-90 backdrop-blur-sm text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-wider shadow-lg ring-1 ring-white/20">
                                <?php echo htmlspecialchars($heroNews['category_name']); ?>
                            </span>
                            <span class="text-slate-300 font-bold text-xs uppercase tracking-wider flex items-center gap-2 border-l border-white/20 pl-3">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <?php echo date('F d, Y', strtotime($heroNews['created_at'])); ?>
                            </span>
                        </div>

                        <h1 class="font-heading text-4xl md:text-6xl font-extrabold text-white mb-6 leading-[1.1] drop-shadow-xl tracking-tight">
                            <?php echo htmlspecialchars($heroNews['title']); ?>
                        </h1>
                        
                        <p class="text-slate-200 text-lg md:text-xl mb-8 line-clamp-2 font-medium leading-relaxed max-w-2xl text-shadow-sm border-l-4 border-cool_sky-500 pl-4">
                            <?php echo htmlspecialchars(substr(strip_tags($heroNews['content']), 0, 200)) . '...'; ?>
                        </p>
                        
                        <div class="flex gap-4">
                            <a href="news_details.php?id=<?php echo $heroNews['news_id']; ?>" 
                               class="inline-flex items-center gap-2 px-8 py-4 bg-white text-slate-900 rounded-2xl font-bold hover:bg-cool_sky-500 hover:text-white transition-all shadow-xl hover:shadow-glow transform hover:-translate-y-1 group/btn">
                                Read Full Story
                                <svg class="w-5 h-5 group-hover/btn:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Carousel Controls -->
                <?php if(count($heroSlides) > 1): ?>
                <div class="absolute bottom-16 right-16 z-20 flex gap-4">
                     <button id="prevSlide" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/20 transition-all hover:scale-110">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                     </button>
                     <button id="nextSlide" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/20 transition-all hover:scale-110">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                     </button>
                </div>
                <!-- Indicators -->
                <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 flex gap-2 z-20">
                    <?php foreach($heroSlides as $index => $s): ?>
                    <button class="indicator w-2 h-2 rounded-full transition-all <?php echo $index === 0 ? 'bg-cool_sky-500 w-6' : 'bg-white/50 hover:bg-white'; ?>" data-index="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const slides = document.querySelectorAll('.hero-slide');
                    const indicators = document.querySelectorAll('.indicator');
                    const prevBtn = document.getElementById('prevSlide');
                    const nextBtn = document.getElementById('nextSlide');
                    let currentSlide = 0;
                    const totalSlides = slides.length;
                    let slideInterval;

                    function showSlide(index) {
                        slides.forEach(slide => {
                            slide.classList.remove('opacity-100', 'z-10');
                            slide.classList.add('opacity-0', 'z-0');
                        });
                        indicators.forEach(ind => {
                            ind.classList.remove('bg-cool_sky-500', 'w-6');
                            ind.classList.add('bg-white/50');
                        });

                        slides[index].classList.remove('opacity-0', 'z-0');
                        slides[index].classList.add('opacity-100', 'z-10');
                        
                        if(indicators[index]) {
                            indicators[index].classList.remove('bg-white/50');
                            indicators[index].classList.add('bg-cool_sky-500', 'w-6');
                        }
                        currentSlide = index;
                    }

                    function next() {
                        let nextIndex = (currentSlide + 1) % totalSlides;
                        showSlide(nextIndex);
                    }

                    function prev() {
                        let prevIndex = (currentSlide - 1 + totalSlides) % totalSlides;
                        showSlide(prevIndex);
                    }

                    if(totalSlides > 1) {
                        nextBtn.addEventListener('click', () => {
                            next();
                            resetTimer();
                        });
                        prevBtn.addEventListener('click', () => {
                            prev();
                            resetTimer();
                        });
                        indicators.forEach((ind, idx) => {
                            ind.addEventListener('click', () => {
                                showSlide(idx);
                                resetTimer();
                            });
                        });

                        function startTimer() {
                            slideInterval = setInterval(next, 5000);
                        }
                        function resetTimer() {
                            clearInterval(slideInterval);
                            startTimer();
                        }
                        startTimer();
                    }
                });
            </script>
            
            <!-- Floating Quick Stats -->
            <div class="absolute -bottom-8 right-16 hidden lg:flex gap-6 z-20">
                <div class="glass-card p-6 rounded-2xl shadow-xl hover:-translate-y-2 transition-transform duration-300 group">
                    <div class="text-3xl font-black text-slate-900 mb-1 group-hover:text-cool_sky-600 transition-colors">24/7</div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Campus Access</div>
                </div>
                <div class="glass-card p-6 rounded-2xl shadow-xl hover:-translate-y-2 transition-transform duration-300 group">
                    <div class="text-3xl font-black text-slate-900 mb-1 group-hover:text-cool_sky-600 transition-colors">5k+</div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Students</div>
                </div>
                <div class="glass-card p-6 rounded-2xl shadow-xl hover:-translate-y-2 transition-transform duration-300 group">
                    <div class="text-3xl font-black text-slate-900 mb-1 group-hover:text-cool_sky-600 transition-colors">100%</div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Digitalized</div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                
                <!-- Sidebar: Categories -->
                <aside class="hidden lg:block lg:col-span-3 space-y-8 sticky top-32 h-fit" id="categories">
                    <div class="bg-white rounded-3xl p-6 shadow-soft border border-slate-100">
                        <h3 class="font-heading font-bold text-slate-900 text-xl mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-tangerine_dream-500 rounded-full"></span>
                            Explore Topics
                        </h3>
                        <div class="space-y-2">
                            <a href="index.php" class="category-filter flex items-center justify-between px-4 py-3 rounded-2xl bg-slate-900 text-white font-bold shadow-lg shadow-slate-900/20 transform scale-105 transition-all" data-slug="all">
                                <span>All Stories</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </a>
                            <?php foreach($categories as $cat): ?>
                                <a href="?category=<?php echo urlencode($cat['slug']); ?>" class="category-filter flex items-center justify-between px-4 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-cool_sky-600 font-bold text-sm transition-all group border border-transparent hover:border-cool_sky-100" data-slug="<?php echo $cat['slug']; ?>">
                                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-400 text-[10px] flex items-center justify-center group-hover:bg-cool_sky-100 group-hover:text-cool_sky-600 transition-colors">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Newsletter Card -->
                    <div class="relative overflow-hidden rounded-3xl p-8 bg-gradient-to-br from-indigo-900 to-slate-900 text-white shadow-xl text-center group border border-white/10">
                        <div class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 brightness-100 contrast-150 mix-blend-overlay"></div>
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-cool_sky-500 opacity-20 rounded-full blur-2xl group-hover:opacity-40 transition-opacity duration-500"></div>
                        
                        <div class="relative z-10">
                            <span class="text-4xl mb-4 block animate-bounce filter drop-shadow-md">📬</span>
                            <h3 class="font-heading font-bold text-2xl mb-2">Campus Digest</h3>
                            <p class="text-slate-300 text-sm mb-6 leading-relaxed font-medium">Get the weekly summary of top stories, events, and notices.</p>
                            <div class="relative">
                                <input type="email" placeholder="Student ID / Email" class="w-full pl-4 pr-12 py-3 rounded-xl bg-white/10 border border-white/10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cool_sky-500 text-sm font-bold">
                                <button class="absolute right-1 top-1 bottom-1 px-3 bg-cool_sky-500 text-white rounded-lg hover:bg-cool_sky-400 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- News Grid -->
                <div class="lg:col-span-9" id="latest">
                    <div class="flex items-end justify-between mb-8 border-b border-slate-200 pb-4">
                        <div>
                            <span class="text-tangerine_dream-500 font-black tracking-widest text-xs uppercase mb-2 block flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-tangerine_dream-500 animate-pulse"></span>
                                What's Happening
                            </span>
                            <h2 class="font-heading text-4xl font-extrabold text-slate-900 tracking-tight">
                                <?php echo (isset($categorySlug) && $categorySlug) ? htmlspecialchars(ucfirst($categorySlug)) : 'Latest Updates'; ?>
                            </h2>
                        </div>
                        <div class="hidden sm:block">
                            <a href="search.php" class="text-sm font-bold text-slate-500 hover:text-cool_sky-600 flex items-center gap-2 transition-colors">
                                View Archive
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-10">
                        <?php if (count($latestNews) > 0): ?>
                            <?php foreach($latestNews as $index => $news): 
                                $placeholder = getPlaceholderImage($news['category_slug'], $index);
                            ?>
                            <article class="bg-white rounded-[2rem] shadow-soft hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 overflow-hidden flex flex-col h-full group relative isolate">
                                <!-- Hover Gradient Overlay -->
                                <div class="absolute inset-x-0 bottom-0 h-1.5 bg-gradient-to-r from-cool_sky-500 via-aquamarine-400 to-indigo-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 z-30"></div>

                                <!-- Image Wrapper -->
                                <div class="h-60 overflow-hidden relative">
                                    <div class="absolute top-4 left-4 z-20 flex gap-2">
                                        <span class="<?php echo getCategoryColor($news['category_slug']); ?> text-[10px] font-black px-3 py-1.5 rounded-lg shadow-lg backdrop-blur-md bg-opacity-95 uppercase tracking-wide">
                                            <?php echo htmlspecialchars($news['category_name']); ?>
                                        </span>
                                    </div>
                                    <img src="<?php echo $news['image_url'] ? htmlspecialchars($news['image_url']) : $placeholder; ?>" 
                                         alt="News Thumbnail" 
                                         onerror="this.onerror=null;this.src='<?php echo $placeholder; ?>';"
                                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 group-hover:rotate-1">
                                    
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>
                                    
                                    <!-- Date Badge -->
                                    <div class="absolute bottom-4 right-4 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-lg text-xs font-bold text-slate-800 shadow-sm border border-white/50 z-20 group-hover:-translate-y-1 transition-transform">
                                        <?php echo date('M d', strtotime($news['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="p-6 flex-1 flex flex-col relative z-20 bg-white">
                                    <h3 class="font-heading text-xl font-bold text-slate-900 mb-3 leading-tight group-hover:text-cool_sky-600 transition-colors">
                                        <a href="news_details.php?id=<?php echo $news['news_id']; ?>" class="stretched-link">
                                            <?php echo htmlspecialchars($news['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-slate-500 text-sm mb-5 line-clamp-3 leading-relaxed font-medium">
                                        <?php echo htmlspecialchars(substr(strip_tags($news['content']), 0, 100)) . '...'; ?>
                                    </p>
                                    
                                    <div class="mt-auto pt-4 border-t border-slate-50 flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black border border-slate-200">
                                                <?php echo substr($news['author_name'], 0, 1); ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-slate-700 truncate max-w-[100px]"><?php echo htmlspecialchars($news['author_name']); ?></span>
                                                <span class="text-[10px] font-semibold text-slate-400">Author</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-500 font-bold bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 group-hover:border-cool_sky-100 group-hover:text-cool_sky-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <?php echo number_format($news['views']); ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Empty State -->
                            <div class="col-span-full py-20 text-center bg-white rounded-[2rem] border-2 border-dashed border-slate-200">
                                <span class="bg-slate-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">📭</span>
                                <h3 class="font-heading text-xl font-bold text-slate-900">No updates yet</h3>
                                <p class="text-slate-500 mt-2 font-medium">Check back later for fresh news.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white pt-24 pb-12 relative overflow-hidden">
        <!-- Footer Pattern -->
        <div class="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none" style="background-image: linear-gradient(#ffffff 1px, transparent 1px), linear-gradient(to right, #ffffff 1px, transparent 1px); background-size: 40px 40px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 mb-6">
                         <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cool_sky-500 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-glow">U</div>
                         <span class="font-heading font-bold text-2xl">UIU <span class="text-cool_sky-400">NewsHub</span></span>
                    </div>
                    <p class="text-slate-400 text-base leading-relaxed max-w-sm font-medium">
                        Stay connected with United International University's latest academic updates, research breakthroughs, and vibrant campus life events.
                    </p>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-6 text-lg tracking-tight">Quick Links</h4>
                    <ul class="space-y-3 text-sm text-slate-400 font-medium">
                        <li><a href="#" class="hover:text-cool_sky-400 transition-colors flex items-center gap-2"><span class="w-1 h-1 bg-cool_sky-500 rounded-full opacity-0 hover:opacity-100 transition-opacity"></span>Academic Calendar</a></li>
                        <li><a href="#" class="hover:text-cool_sky-400 transition-colors flex items-center gap-2"><span class="w-1 h-1 bg-cool_sky-500 rounded-full opacity-0 hover:opacity-100 transition-opacity"></span>Transport Schedule</a></li>
                        <li><a href="#" class="hover:text-cool_sky-400 transition-colors flex items-center gap-2"><span class="w-1 h-1 bg-cool_sky-500 rounded-full opacity-0 hover:opacity-100 transition-opacity"></span>Library Portal</a></li>
                        <li><a href="#" class="hover:text-cool_sky-400 transition-colors flex items-center gap-2"><span class="w-1 h-1 bg-cool_sky-500 rounded-full opacity-0 hover:opacity-100 transition-opacity"></span>Student Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-6 text-lg tracking-tight">Connect</h4>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-slate-400 hover:bg-cool_sky-600 hover:text-white hover:border-cool_sky-500 transition-all transform hover:-translate-y-1 shadow-lg">
                            <span class="sr-only">Facebook</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-slate-400 hover:bg-gradient-to-br hover:from-tangerine_dream-500 hover:to-strawberry_red-500 hover:text-white hover:border-transparent transition-all transform hover:-translate-y-1 shadow-lg">
                            <span class="sr-only">Instagram</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.85-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-slate-500 text-sm font-medium">
                <div>&copy; <?php echo date('Y'); ?> UIU NewsHub. All rights reserved.</div>
                <div class="flex items-center gap-6">
                    <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        // Alert Fetching Logic
        function fetchAlerts() {
            fetch('api/get_alerts.php')
                .then(response => response.json())
                .then(data => {
                    const alertContainer = document.getElementById('alert-ticker');
                    const banner = document.getElementById('alert-banner');
                    
                    if (data.status === 'success' && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(alert => {
                            let icon = 'INFO';
                            let color = 'text-aquamarine-400';
                            if (alert.severity === 'warning') { color = 'text-jasmine-400'; icon = '⚠️'; }
                            if (alert.severity === 'danger')  { color = 'text-strawberry_red-300'; icon = '🚨'; }
                            
                            html += `
                                <span class="inline-flex items-center gap-2">
                                    <span class="${color}">${icon}</span>
                                    <span class="text-white tracking-wide">${alert.title}:</span>
                                    <span class="text-slate-400 font-normal">${alert.message}</span>
                                </span>
                            `;
                        });
                        alertContainer.innerHTML = html;
                        banner.classList.remove('hidden');
                    } else {
                        banner.classList.add('hidden');
                    }
                })
                .catch(err => console.error('Error fetching alerts:', err));
        }
        setInterval(fetchAlerts, 30000);

        // News Category AJAX Logic
        document.addEventListener('DOMContentLoaded', () => {
            const categoryLinks = document.querySelectorAll('.category-filter');
            const newsGrid = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3');
            const sectionTitle = document.querySelector('#latest h2');
            
            function fetchNews(categorySlug) {
                // Show loading state (optional, simplified here)
                newsGrid.style.opacity = '0.5';
                
                fetch(`api/fetch_news.php?category=${categorySlug}`)
                    .then(response => response.json())
                    .then(res => {
                        if(res.status === 'success') {
                            updateNewsGrid(res.data);
                            updateTitle(categorySlug);
                            updateActiveLink(categorySlug);
                        }
                    })
                    .catch(e => console.error(e))
                    .finally(() => {
                        newsGrid.style.opacity = '1';
                    });
            }

            function updateNewsGrid(newsItems) {
                if(newsItems.length === 0) {
                    newsGrid.innerHTML = `
                        <div class="col-span-full py-20 text-center bg-white rounded-[2rem] border-2 border-dashed border-slate-200">
                             <span class="bg-slate-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">📭</span>
                             <h3 class="font-heading text-xl font-bold text-slate-900">No updates yet</h3>
                             <p class="text-slate-500 mt-2 font-medium">Check back later for fresh news.</p>
                        </div>
                    `;
                    return;
                }

                const html = newsItems.map(news => `
                    <article class="bg-white rounded-[2rem] shadow-soft hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border border-slate-100 overflow-hidden flex flex-col h-full group relative isolate animate-fade-in-up">
                        <div class="absolute inset-x-0 bottom-0 h-1.5 bg-gradient-to-r from-cool_sky-500 via-aquamarine-400 to-indigo-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 z-30"></div>
                        <div class="h-60 overflow-hidden relative">
                            <div class="absolute top-4 left-4 z-20 flex gap-2">
                                <span class="${news.category_color} text-[10px] font-black px-3 py-1.5 rounded-lg shadow-lg backdrop-blur-md bg-opacity-95 uppercase tracking-wide">
                                    ${news.category_name}
                                </span>
                            </div>
                            <img src="${news.image_url}" 
                                 alt="News Thumbnail" 
                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 group-hover:rotate-1">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>
                            <div class="absolute bottom-4 right-4 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-lg text-xs font-bold text-slate-800 shadow-sm border border-white/50 z-20 group-hover:-translate-y-1 transition-transform">
                                ${news.formatted_date}
                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col relative z-20 bg-white">
                            <h3 class="font-heading text-xl font-bold text-slate-900 mb-3 leading-tight group-hover:text-cool_sky-600 transition-colors">
                                <a href="news_details.php?id=${news.news_id}" class="stretched-link">
                                    ${news.title}
                                </a>
                            </h3>
                            <p class="text-slate-500 text-sm mb-5 line-clamp-3 leading-relaxed font-medium">
                                ${news.excerpt}
                            </p>
                            <div class="mt-auto pt-4 border-t border-slate-50 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-black border border-slate-200">
                                        ${news.author_initial}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-700 truncate max-w-[100px]">${news.author_name}</span>
                                        <span class="text-[10px] font-semibold text-slate-400">Author</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-bold bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 group-hover:border-cool_sky-100 group-hover:text-cool_sky-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    ${news.formatted_views}
                                </div>
                            </div>
                        </div>
                    </article>
                `).join('');
                
                newsGrid.innerHTML = html;
            }

            function updateTitle(slug) {
                 const title = slug === 'all' || !slug ? 'Latest Updates' : slug.charAt(0).toUpperCase() + slug.slice(1);
                 sectionTitle.innerText = title;
            }

            function updateActiveLink(activeSlug) {
                categoryLinks.forEach(link => {
                    const slug = link.getAttribute('data-slug');
                    const span = link.querySelector('span:last-child');
                    
                    if(slug === activeSlug) {
                        link.classList.remove('bg-slate-900', 'text-white'); // Remove default All Stories style if present
                        link.classList.remove('text-slate-600', 'hover:bg-slate-50'); // Remove default other style
                        
                        // Apply Active Style (We'll use the 'Generic' active style from the 'All Stories')
                        // For simplicity, we just add a visual indicator or standard active class
                        link.classList.add('bg-cool_sky-50', 'text-cool_sky-600', 'border-cool_sky-100');
                        if(span) span.classList.add('bg-cool_sky-100', 'text-cool_sky-600');
                    } else {
                        link.classList.remove('bg-cool_sky-50', 'text-cool_sky-600', 'border-cool_sky-100');
                        link.classList.add('text-slate-600');
                        if(span) span.classList.remove('bg-cool_sky-100', 'text-cool_sky-600');
                    }
                    // Reset 'All Stories' specifically if it's not active
                    if(slug === 'all' && activeSlug !== 'all') {
                         link.classList.remove('bg-slate-900', 'text-white');
                         link.classList.add('text-slate-600');
                    }
                    // Set 'All Stories' specific style if it IS active
                    if(slug === 'all' && activeSlug === 'all') {
                        link.classList.add('bg-slate-900', 'text-white');
                        link.classList.remove('text-slate-600', 'bg-cool_sky-50', 'text-cool_sky-600');
                    }
                });
            }

            categoryLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const slug = link.getAttribute('data-slug');
                    fetchNews(slug);
                });
            });
        });
    </script>
</body>
</html>

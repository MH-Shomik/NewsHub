<?php
require_once 'includes/db_connect.php';

// Helper to map DB categories to new Palette Colors (Duplicate for now, best in a shared file later)
function getCategoryColor($slug) {
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
     $colors = [
        'academic' => '60b5ff', 'research' => '5ef2d5', 'events' => 'f79d65', 
        'sports' => 'f35252', 'notice' => 'ffe588'
    ];
    $hex = $colors[$category] ?? 'cbd5e1';
    $textHex = ($category == 'notice' || $category == 'research') ? '0f172a' : 'ffffff';
    return "https://placehold.co/800x600/$hex/$textHex?text=" . urlencode(ucfirst($category) . "+Update");
}

$news_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newsItem = null;

if ($news_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
            FROM news n 
            JOIN categories c ON n.category_id = c.category_id 
            JOIN users u ON n.author_id = u.user_id 
            WHERE n.news_id = ? AND n.status = 'published'
        ");
        $stmt->execute([$news_id]);
        $newsItem = $stmt->fetch();
        
        if ($newsItem) {
            $pdo->prepare("UPDATE news SET views = views + 1 WHERE news_id = ?")->execute([$news_id]);
        }
    } catch (PDOException $e) {}
}

if (!$newsItem) {
    header("Location: index.php");
    exit;
}

try {
    $relStmt = $pdo->prepare("
        SELECT n.*, c.slug as category_slug 
        FROM news n 
        JOIN categories c ON n.category_id = c.category_id
        WHERE n.category_id = ? AND n.news_id != ? AND n.status = 'published' 
        ORDER BY n.created_at DESC LIMIT 3
    ");
    $relStmt->execute([$newsItem['category_id'], $news_id]);
    $relatedNews = $relStmt->fetchAll();
} catch (PDOException $e) { $relatedNews = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($newsItem['title']); ?> | UIU NewsHub</title>
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
                    }
                }
            }
        }
    </script>
    <style>
         .hero-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-cool_sky-500 selection:text-white">

    <!-- Sticky Nav -->
    <nav class="bg-white/80 backdrop-blur-xl border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                 <a href="index.php" class="flex items-center gap-2 group">
                    <div class="w-8 h-8">
                        <img src="image.png" alt="Logo" class="w-full h-full object-contain rounded-full drop-shadow-md group-hover:scale-110 transition-transform">
                    </div>
                    <span class="font-heading font-bold text-xl text-slate-900 tracking-tight">UIU <span class="text-cool_sky-500">NewsHub</span></span>
                </a>
                <a href="index.php" class="text-sm font-semibold text-slate-500 hover:text-cool_sky-600 flex items-center gap-2 transition-colors bg-slate-50 px-4 py-2 rounded-full hover:bg-cool_sky-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to Feed
                </a>
            </div>
        </div>
    </nav>

    <main class="hero-pattern min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            
            <!-- Article Header -->
            <header class="mb-12 text-center">
                <div class="flex items-center justify-center gap-3 mb-8">
                     <span class="<?php echo getCategoryColor($newsItem['category_slug']); ?> text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider shadow-md">
                        <?php echo htmlspecialchars($newsItem['category_name']); ?>
                    </span>
                    <span class="text-slate-400 text-sm font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <?php echo date('F j, Y', strtotime($newsItem['created_at'])); ?>
                    </span>
                </div>
                
                <h1 class="font-heading text-4xl md:text-5xl font-extrabold text-slate-900 leading-tight mb-8 drop-shadow-sm">
                    <?php echo htmlspecialchars($newsItem['title']); ?>
                </h1>

                <!-- Hero Image -->
                <div class="rounded-3xl overflow-hidden shadow-2xl mb-10 border-4 border-white">
                    <img src="<?php echo $newsItem['image_url'] ? htmlspecialchars($newsItem['image_url']) : getPlaceholderImage($newsItem['category_slug'], 0); ?>" 
                         alt="Featured" 
                         class="w-full object-cover max-h-[500px]">
                </div>

                <div class="flex items-center justify-center gap-8 text-slate-500 text-sm border-b border-slate-200 pb-8 mx-auto max-w-2xl">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500">
                             <?php echo substr($newsItem['author_name'], 0, 1); ?>
                        </div>
                        <span class="font-bold text-slate-900"><?php echo htmlspecialchars($newsItem['author_name']); ?></span>
                    </div>
                    <div class="flex items-center gap-1.5" title="Views">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span><?php echo number_format($newsItem['views']); ?> reads</span>
                    </div>
                </div>
            </header>

            <!-- Article Content -->
            <article class="prose prose-lg prose-slate prose-headings:font-heading prose-a:text-cool_sky-600 hover:prose-a:text-cool_sky-500 mx-auto mb-20 bg-white p-8 md:p-12 rounded-3xl shadow-soft border border-slate-100">
                <?php echo nl2br(strip_tags($newsItem['content'])); ?>
            </article>

            <!-- Related News -->
            <?php if (!empty($relatedNews)): ?>
            <section class="border-t border-slate-200 pt-12">
                <div class="flex items-center gap-3 mb-8">
                     <span class="w-1.5 h-8 bg-aquamarine-400 rounded-full"></span>
                     <h3 class="font-heading text-2xl font-bold text-slate-900">More in <?php echo htmlspecialchars($newsItem['category_name']); ?></h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <?php foreach($relatedNews as $index => $rel): 
                        $placeholder = getPlaceholderImage($rel['category_slug'], $index);
                    ?>
                    <a href="news_details.php?id=<?php echo $rel['news_id']; ?>" class="group block bg-white rounded-2xl p-4 shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-100 hover:-translate-y-1">
                        <div class="rounded-xl overflow-hidden h-40 mb-4 relative">
                            <img src="<?php echo $rel['image_url'] ? htmlspecialchars($rel['image_url']) : $placeholder; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        </div>
                        <h4 class="font-heading font-bold text-slate-800 group-hover:text-cool_sky-600 transition-colors line-clamp-2 text-lg leading-tight mb-2">
                            <?php echo htmlspecialchars($rel['title']); ?>
                        </h4>
                        <span class="text-xs text-cool_sky-500 font-semibold bg-cool_sky-50 px-2 py-1 rounded inline-block">
                            <?php echo date('M d', strtotime($rel['created_at'])); ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 mt-20 py-8 text-center text-slate-400 text-sm">
        &copy; <?php echo date('Y'); ?> UIU NewsHub. All rights reserved.
    </footer>

</body>
</html>

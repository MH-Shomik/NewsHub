<?php
session_start();
require_once 'includes/db_connect.php';

// Fetch Categories for dropdown
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) { $categories = []; }

// Handle Search
$search = $_GET['q'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_filter = $_GET['date'] ?? '';

$where_clauses = ["n.status = 'published'"];
$params = [];

if ($search) {
    $where_clauses[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_clauses[] = "n.category_id = ?";
    $params[] = $category_filter;
}

if ($date_filter) {
    $where_clauses[] = "DATE(n.created_at) = ?";
    $params[] = $date_filter;
}

$where_sql = implode(' AND ', $where_clauses);

try {
    $sql = "SELECT n.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
            FROM news n 
            JOIN categories c ON n.category_id = c.category_id 
            JOIN users u ON n.author_id = u.user_id 
            WHERE $where_sql 
            ORDER BY n.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    $results = [];
    $error = "Search failed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search News | UIU NewsHub</title>
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
                        jasmine: { DEFAULT: '#ffe588', 400: '#ffd53b' },
                        tangerine_dream: { DEFAULT: '#f79d65', 500: '#f79d65' },
                        aquamarine: { DEFAULT: '#5ef2d5', 500: '#5ef2d5' },
                        cool_sky: { DEFAULT: '#60b5ff', 500: '#60b5ff', 600: '#1b94ff', 50: '#f0f9ff' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800">

    <!-- Nav -->
    <nav class="bg-white/80 backdrop-blur-xl border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                 <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'student') ? 'student_dashboard.php' : 'index.php'; ?>" class="flex items-center gap-2 group">
                    <div class="w-10 h-10">
                        <img src="image.png" alt="Logo" class="w-full h-full object-contain rounded-full drop-shadow-md group-hover:scale-110 transition-transform">
                    </div>
                    <span class="font-heading font-bold text-xl text-slate-900">NewsHub</span>
                </a>
                <?php if(isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="text-sm font-bold text-slate-500 hover:text-red-500">Logout</a>
                <?php else: ?>
                <a href="login.php" class="text-sm font-bold text-slate-900">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 text-center">
            <h1 class="font-heading text-4xl font-black text-slate-900 mb-4">Search & Filter</h1>
            <p class="text-slate-500 text-lg">Find the specific news or notices you are looking for.</p>
        </div>

        <!-- Filter Form -->
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 mb-12">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Keywords</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="w-full h-12 px-4 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 transition-colors" placeholder="e.g. Exam Schedule...">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Category</label>
                    <select name="category" class="w-full h-12 px-4 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 transition-colors">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="w-full h-12 px-4 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 transition-colors">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="w-full h-12 bg-slate-900 text-white font-bold rounded-xl hover:bg-cool_sky-600 transition-colors shadow-lg">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div>
            <h2 class="font-heading text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                Results found: <span class="bg-cool_sky-100 text-cool_sky-600 px-2 py-0.5 rounded-lg text-sm"><?php echo count($results); ?></span>
            </h2>

            <?php if(count($results) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($results as $news): ?>
                 <article class="group bg-white rounded-[2rem] border border-slate-100 overflow-hidden hover:shadow-xl transition-all duration-500 hover:-translate-y-2 h-full flex flex-col">
                    <div class="h-48 overflow-hidden relative">
                        <?php 
                            $placeholderColor = '2563EB'; // Blue default
                            if(strpos($news['category_slug'], 'event') !== false) $placeholderColor = 'EA580C'; // Orange
                            if(strpos($news['category_slug'], 'sport') !== false) $placeholderColor = '16A34A'; // Green
                            if(strpos($news['category_slug'], 'research') !== false) $placeholderColor = '9333EA'; // Purple
                            $imgUrl = !empty($news['image_url']) ? $news['image_url'] : "https://placehold.co/600x400/$placeholderColor/FFFFFF/png?text=" . urlencode($news['category_name']);
                        ?>
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="News Image" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <span class="absolute bottom-4 left-4 bg-white/90 backdrop-blur-md px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm">
                            <?php echo htmlspecialchars($news['category_name']); ?>
                        </span>
                    </div>
                    
                    <div class="p-6 flex flex-col flex-1">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 mb-3 uppercase tracking-wider">
                            <span><?php echo date('M d, Y', strtotime($news['created_at'])); ?></span>
                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                            <span><?php echo htmlspecialchars($news['author_name']); ?></span>
                        </div>
                        
                        <h3 class="font-heading text-xl font-bold text-slate-900 mb-3 leading-snug group-hover:text-cool_sky-600 transition-colors">
                            <a href="news_details.php?id=<?php echo $news['news_id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="text-slate-500 text-sm line-clamp-3 mb-6 bg-transparent">
                            <?php echo htmlspecialchars(substr(strip_tags($news['content']), 0, 120)) . '...'; ?>
                        </p>
                        
                        <div class="mt-auto pt-6 border-t border-slate-50 flex items-center justify-between">
                            <a href="news_details.php?id=<?php echo $news['news_id']; ?>" class="inline-flex items-center gap-2 text-sm font-bold text-slate-900 group-hover:text-cool_sky-600 transition-colors">
                                Read Full Story
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="font-heading text-xl font-bold text-slate-900">No results found</h3>
                <p class="text-slate-500 mt-2">Try adjusting your search criteria</p>
                <a href="search.php" class="inline-block mt-4 text-cool_sky-600 font-bold hover:underline">Clear Filters</a>
            </div>
            <?php endif; ?>

        </div>
    </main>

</body>
</html>

<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin/Moderator Only)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION;

// Handle Delete Action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        // Check permissions
        $stmt = $pdo->prepare("SELECT author_id FROM news WHERE news_id = ?");
        $stmt->execute([$delete_id]);
        $newsItem = $stmt->fetch();

        if ($newsItem) {
            if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $newsItem['author_id']) {
                $stmt = $pdo->prepare("DELETE FROM news WHERE news_id = ?");
                $stmt->execute([$delete_id]);
                logActivity($pdo, $_SESSION['user_id'], 'Post Deleted', "Deleted news ID: $delete_id");
                header("Location: news_manage.php?msg=deleted");
                exit;
            } else {
                $error = "You do not have permission to delete this article.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error deleting news: " . $e->getMessage();
    }
}

// Fetch News with Filtering
// Fetch News with Filtering
$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where .= " AND n.status = ?";
    $params[] = $status_filter;
}

$totalPages = 1;
try {
    // Basic Pagination
    $page = $_GET['page'] ?? 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Total Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Fetch Items
    $sql = "
        SELECT n.*, c.name as category_name, u.full_name as author_name 
        FROM news n 
        JOIN categories c ON n.category_id = c.category_id 
        JOIN users u ON n.author_id = u.user_id 
        WHERE $where 
        ORDER BY n.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newsItems = $stmt->fetchAll();

} catch (PDOException $e) { $newsItems = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News | UIU NewsHub</title>
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
                        cool_sky: { DEFAULT: '#60b5ff', 500: '#60b5ff', 600: '#1b94ff', 50: '#f0f9ff' },
                        strawberry_red: { DEFAULT: '#f35252', 500: '#f35252', 50: '#fef2f2' },
                        jasmine: { DEFAULT: '#ffe588', 500: '#ffe588', 50: '#fffbeb' },
                        aquamarine: { DEFAULT: '#5ef2d5', 500: '#5ef2d5', 50: '#f0fdfa' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-slate-50">
            <!-- Header -->
            <header class="h-20 bg-white/80 backdrop-blur-md sticky top-0 z-10 border-b border-slate-200 flex justify-between items-center px-8 shadow-sm">
                <h2 class="font-heading text-xl font-bold text-slate-800">Manage News</h2>
                <a href="news_create.php" class="px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-cool_sky-600 transition-colors shadow-lg">create new</a>
            </header>

            <div class="p-8 max-w-7xl mx-auto">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="bg-red-50 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm font-bold border border-red-100 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Article deleted successfully.
                    </div>
                <?php endif; ?>

                <?php
                    // Count stats for tabs
                    $countParams = []; 
                    $baseCountSql = "SELECT COUNT(*) FROM news";
                    
                    // Note: If you want these counts to reflect the SEARCH query, you'd add WHERE here. 
                    // But usually tabs show total counts for that bucket.
                    $allCount = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
                    $pubCount = $pdo->query("SELECT COUNT(*) FROM news WHERE status='published'")->fetchColumn();
                    $pendingCount = $pdo->query("SELECT COUNT(*) FROM news WHERE status='pending'")->fetchColumn();
                ?>

                <!-- Filters -->
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex gap-2 overflow-x-auto pb-2 md:pb-0 w-full md:w-auto">
                        <a href="news_manage.php" class="px-3 py-2 rounded-lg text-sm font-bold transition-colors whitespace-nowrap <?php echo !$status_filter ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'; ?>">
                            All <span class="ml-1 opacity-60 text-xs"><?php echo $allCount; ?></span>
                        </a>
                        <a href="news_manage.php?status=published" class="px-3 py-2 rounded-lg text-sm font-bold transition-colors whitespace-nowrap <?php echo $status_filter === 'published' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'; ?>">
                            Published <span class="ml-1 opacity-60 text-xs"><?php echo $pubCount; ?></span>
                        </a>
                        <a href="news_manage.php?status=pending" class="px-3 py-2 rounded-lg text-sm font-bold transition-colors whitespace-nowrap <?php echo $status_filter === 'pending' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'; ?>">
                            Pending <span class="ml-1 opacity-60 text-xs"><?php echo $pendingCount; ?></span>
                        </a>
                    </div>

                    <form action="" method="GET" class="flex-1 w-full md:w-auto relative max-w-sm">
                        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                        <input type="text" name="q" placeholder="Search articles..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:border-cool_sky-500 font-medium">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </form>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-8 py-5">Article</th>
                                    <th class="px-6 py-5">Category</th>
                                    <th class="px-6 py-5">Author</th>
                                    <th class="px-6 py-5">Stats</th>
                                    <th class="px-6 py-5">Status</th>
                                    <th class="px-6 py-5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($newsItems)): ?>
                                    <tr>
                                        <td colspan="6" class="px-8 py-10 text-center text-slate-500 font-medium">No articles found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($newsItems as $news): 
                                        $img = $news['image_url'] ? $news['image_url'] : 'https://placehold.co/100x100/e2e8f0/64748b?text=img';
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        <td class="px-8 py-5">
                                            <div class="flex items-center gap-4">
                                                <img src="<?php echo htmlspecialchars($img); ?>" onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=img'" class="w-12 h-12 rounded-xl object-cover border border-slate-100">
                                                <div class="max-w-xs">
                                                    <div class="font-bold text-slate-800 line-clamp-1">
                                                        <?php echo htmlspecialchars($news['title']); ?>
                                                        <?php if(isset($news['is_hero']) && $news['is_hero'] == 1): ?>
                                                            <span class="inline-block ml-2 px-2 py-0.5 rounded-md bg-purple-100 text-purple-600 text-[10px] font-black uppercase tracking-wider">Hero</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs text-slate-400 mt-0.5 font-medium"><?php echo date('M d, Y', strtotime($news['created_at'])); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200/50">
                                                <?php echo htmlspecialchars($news['category_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-sm font-semibold text-slate-600"><?php echo htmlspecialchars($news['author_name']); ?></td>
                                        <td class="px-6 py-5">
                                            <div class="flex items-center gap-1 text-xs font-bold text-slate-500">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                <?php echo $news['views']; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <?php if($news['status'] === 'published'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-aquamarine-50 text-aquamarine-600 border border-aquamarine-100">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-aquamarine-500"></span> Published
                                                </span>
                                            <?php elseif($news['status'] === 'pending'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-100">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Pending
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Archived
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-5 text-right">
                                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $news['author_id']): ?>
                                            <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                                <a href="news_edit.php?id=<?php echo $news['news_id']; ?>" class="p-2 text-slate-400 hover:text-cool_sky-600 hover:bg-cool_sky-50 rounded-lg transition-colors" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                </a>
                                                <a href="?delete_id=<?php echo $news['news_id']; ?>" onclick="return confirm('Are you sure you want to delete this article?');" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                    <div class="px-8 py-5 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <div class="flex gap-2">
                             <?php if($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search); ?>" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Previous</a>
                            <?php endif; ?>
                            <?php if($page < $totalPages): ?>
                                <a href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search); ?>" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

</body>
</html>

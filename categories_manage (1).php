<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$success = '';
$error = '';

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $success = "Category created successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        // Check for dependencies
        $check = $pdo->prepare("SELECT COUNT(*) FROM news WHERE category_id = ?");
        $check->execute([$delete_id]);
        if ($check->fetchColumn() > 0) {
            $error = "Cannot delete category because it has related news articles.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$delete_id]);
            $success = "Category deleted successfully!";
        }
    } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

// Fetch Categories
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) { $categories = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | UIU NewsHub</title>
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
                <h2 class="font-heading text-xl font-bold text-slate-800">Manage Categories</h2>
            </header>

            <div class="p-8 max-w-7xl mx-auto">
                <?php if ($success): ?>
                <div class="bg-aquamarine-50 border border-aquamarine-200 text-aquamarine-700 px-6 py-4 rounded-2xl mb-6 font-bold flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="bg-strawberry_red-50 border border-strawberry_red-200 text-strawberry_red-700 px-6 py-4 rounded-2xl mb-6 font-bold flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Create Form -->
                    <div class="lg:col-span-1">
                        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 sticky top-28">
                            <h3 class="font-heading font-bold text-lg text-slate-900 mb-4">Add New Category</h3>
                            <form action="" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="create">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Category Name</label>
                                    <input type="text" name="name" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 transition-all font-bold" placeholder="e.g. Sports" required>
                                </div>
                                <button type="submit" class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-cool_sky-600 transition-all shadow-lg">
                                    Create Category
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List -->
                    <div class="lg:col-span-2">
                         <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="bg-slate-50/50 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4">Name</th>
                                            <th class="px-6 py-4">Slug</th>
                                            <th class="px-6 py-4 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach($categories as $cat): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($cat['name']); ?></td>
                                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($cat['slug']); ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="?delete_id=<?php echo $cat['category_id']; ?>" onclick="return confirm('Delete this category?');" class="text-red-400 hover:text-red-600 font-bold hover:underline text-xs uppercase tracking-wider">Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>

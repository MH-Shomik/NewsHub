<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin/Moderator Only)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}

$news_id = $_GET['id'] ?? null;
if (!$news_id) {
    header("Location: news_manage.php");
    exit;
}

$success = '';
$error = '';

// Fetch Existing Data
try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE news_id = ?");
    $stmt->execute([$news_id]);
    $news = $stmt->fetch();

    if (!$news) {
        header("Location: news_manage.php");
        exit;
    }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// Check permissions: Admin or Author
if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $news['author_id']) {
    header("Location: news_manage.php?error=unauthorized");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = $_POST['category_id'];
    $image_url = trim($_POST['image_url']);
    
    // Handle File Upload
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileName = $_FILES['image_file']['name'];
        $fileSize = $_FILES['image_file']['size'];
        $fileTmp = $_FILES['image_file']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowed)) {
            if ($fileSize < 5000000) { // 5MB limit
                $newFileName = uniqid('news_', true) . '.' . $fileExt;
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $destPath)) {
                    $image_url = $destPath; // Override URL if file uploaded
                } else {
                    $error = "Failed to move uploaded file.";
                }
            } else {
                $error = "File is too large. Max 5MB.";
            }
        } else {
            $error = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP.";
        }
    }
    
    // Moderator: Enforce 'pending' status and cannot set hero
    if ($_SESSION['role'] === 'moderator') {
        $status = 'pending';
        $is_hero = 0;
    } else {
        $status = $_POST['status'];
        $is_hero = isset($_POST['is_hero']) ? 1 : 0;
    }

    if ($error) {
         // Stop
    } elseif (empty($title) || empty($content) || empty($category_id)) {
        $error = "Title, Content, and Category are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, category_id=?, image_url=?, status=?, is_hero=?, updated_at=NOW() WHERE news_id=?");
            $stmt->execute([$title, $content, $category_id, $image_url, $status, $is_hero, $news_id]);
            
            // Refresh Data
            $news['title'] = $title;
            $news['content'] = $content;
            $news['category_id'] = $category_id;
            $news['image_url'] = $image_url;
            $news['status'] = $status;
            $news['is_hero'] = $is_hero;
            
            logActivity($pdo, $_SESSION['user_id'], 'Post Updated', "Updated news: $title");
            $success = ($_SESSION['role'] === 'admin') ? "News article updated successfully!" : "Article updated and submitted for review.";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch Categories
try {
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
} catch (PDOException $e) { $cats = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit News | UIU NewsHub</title>
    <link rel="icon" href="image.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
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
    <style>
        .ck-editor__editable { min-height: 300px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main -->
        <main class="flex-1 overflow-y-auto bg-slate-50">
             <!-- Header -->
            <header class="h-20 bg-white/80 backdrop-blur-md sticky top-0 z-10 border-b border-slate-200 flex justify-between items-center px-8 shadow-sm">
                <h2 class="font-heading text-xl font-bold text-slate-800">Edit Article</h2>
                <a href="news_manage.php" class="text-sm font-bold text-slate-500 hover:text-slate-800">Cancel</a>
            </header>

            <div class="p-8 max-w-4xl mx-auto">
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

                <form action="" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Article Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($news['title']); ?>" class="w-full px-5 py-4 text-lg font-heading font-bold rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 focus:bg-white transition-all" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Category</label>
                            <select name="category_id" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer" required>
                                <?php foreach($cats as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $cat['category_id'] == $news['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div>
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Status</label>
                            <select name="status" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                                <option value="published" <?php echo $news['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="pending" <?php echo $news['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="status" value="pending">
                            <div class="flex items-center">
                                <span class="bg-purple-100 text-purple-700 font-bold px-4 py-2 rounded-lg text-sm">Status: Pending Review</span>
                            </div>
                        <?php endif; ?>
                    </div>

    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Current Image</label>
            <?php if (!empty($news['image_url'])): ?>
                <div class="mb-4 relative group w-fit">
                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>" class="h-32 w-auto rounded-lg shadow-sm border border-slate-200">
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center text-white text-xs font-bold">
                        Current
                    </div>
                </div>
            <?php endif; ?>
            
            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Upload New Image</label>
            <div class="relative">
                <input type="file" name="image_file" id="image_file" class="hidden" onchange="previewImage(this)">
                <label for="image_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:bg-slate-50 hover:border-cool_sky-400 transition-all group">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-8 h-8 mb-3 text-slate-400 group-hover:text-cool_sky-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p class="mb-1 text-sm text-slate-500 font-bold">Click to upload new</p>
                        <p class="text-xs text-slate-400">JPG, PNG, WEBP (Max 5MB)</p>
                    </div>
                </label>
            </div>
            <div id="image-preview" class="mt-4 hidden">
                <p class="text-xs font-bold text-slate-500 mb-2">New Preview:</p>
                <img src="" class="h-32 w-auto rounded-lg shadow-sm border border-slate-200">
            </div>
        </div>
        <div>
            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Or Image URL</label>
            <input type="text" name="image_url" value="<?php echo htmlspecialchars($news['image_url']); ?>" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500" placeholder="https://example.com/image.jpg">
            <p class="text-xs text-slate-400 mt-2 ml-1">Leave empty to keep current image (if not uploading).</p>
        </div>
    </div>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="mb-6 flex items-center gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <input type="checkbox" id="is_hero" name="is_hero" class="w-5 h-5 rounded text-cool_sky-600 focus:ring-cool_sky-500 border-gray-300" <?php echo (isset($news['is_hero']) && $news['is_hero'] == 1) ? 'checked' : ''; ?>>
                        <label for="is_hero" class="text-sm font-bold text-slate-700 cursor-pointer select-none">Feature in Hero Slideshow</label>
                        <span class="text-xs text-slate-400 font-medium ml-auto">Shows at the top of homepage</span>
                    </div>
                    <?php endif; ?>

                    <div class="mb-8">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Content</label>
                        <textarea name="content" id="editor" class="w-full rounded-xl border-slate-200"><?php echo htmlspecialchars($news['content']); ?></textarea>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 py-4 bg-slate-900 text-white font-bold rounded-xl hover:bg-cool_sky-600 transition-all shadow-lg text-lg">
                            Update Story
                        </button>
                        <a href="news_manage.php" class="py-4 px-8 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all text-lg">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

<script>
        ClassicEditor
            .create(document.querySelector('#editor'))
            .catch(error => {
                console.error(error);
            });

        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }
    </script>
</body>
</html>

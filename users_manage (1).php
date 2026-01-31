<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch Users
$search = $_GET['q'] ?? '';
$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) { $users = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | UIU NewsHub</title>
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
                <h2 class="font-heading text-xl font-bold text-slate-800">Manage Users</h2>
            </header>

            <div class="p-8 max-w-7xl mx-auto">
                <!-- Search -->
                <div class="mb-8">
                     <form action="" method="GET" class="relative max-w-md">
                        <input type="text" name="q" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-3 rounded-xl bg-white border border-slate-200 text-sm focus:outline-none focus:border-cool_sky-500 font-medium shadow-sm">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </form>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4">User</th>
                                    <th class="px-6 py-4">Role</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($users as $u): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-500 border border-slate-200">
                                                <?php echo substr($u['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                                <div class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($u['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($u['role'] === 'admin'): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-black bg-slate-900 text-white shadow-lg shadow-slate-900/20">ADMIN</span>
                                        <?php elseif($u['role'] === 'moderator'): ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-600 border border-indigo-100">MODERATOR</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">STUDENT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                         <?php if($u['status'] === 'active'): ?>
                                            <span class="text-emerald-500 font-bold text-xs flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active</span>
                                        <?php else: ?>
                                            <span class="text-red-500 font-bold text-xs flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Banned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="user_edit.php?id=<?php echo $u['user_id']; ?>" class="text-cool_sky-500 hover:text-cool_sky-600 font-bold text-xs uppercase tracking-wider hover:underline">Edit</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>

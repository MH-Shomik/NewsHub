<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) { header("Location: users_manage.php"); exit; }

$success = '';
$error = '';

// Fetch User
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    if (!$u) { header("Location: users_manage.php"); exit; }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $status = $_POST['status'];
    // Optional: Allow name/email edit if needed, but primarily for role management
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, status = ? WHERE user_id = ?");
        $stmt->execute([$role, $status, $user_id]);
        $success = "User updated successfully!";
        // Refresh
        $u['role'] = $role;
        $u['status'] = $status;
    } catch (PDOException $e) { $error = "DB Error: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | UIU NewsHub</title>
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
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-slate-50">
             <!-- Header -->
            <header class="h-20 bg-white/80 backdrop-blur-md sticky top-0 z-10 border-b border-slate-200 flex justify-between items-center px-8 shadow-sm">
                <h2 class="font-heading text-xl font-bold text-slate-800">Edit User Permission</h2>
                <a href="users_manage.php" class="text-sm font-bold text-slate-500 hover:text-slate-800">Cancel</a>
            </header>

            <div class="p-8 max-w-2xl mx-auto">
                
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

                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center font-black text-2xl text-slate-400">
                             <?php echo substr($u['full_name'], 0, 1); ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-xl text-slate-900"><?php echo htmlspecialchars($u['full_name']); ?></h3>
                            <p class="text-slate-500 font-medium"><?php echo htmlspecialchars($u['email']); ?></p>
                        </div>
                    </div>

                    <form action="" method="POST" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Role</label>
                                <select name="role" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                                    <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="moderator" <?php echo $u['role'] === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Status</label>
                                <select name="status" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                                    <option value="active" <?php echo $u['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="banned" <?php echo $u['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                </select>
                            </div>
                        </div>

                         <button type="submit" class="w-full py-4 bg-slate-900 text-white font-bold rounded-xl hover:bg-cool_sky-600 transition-all shadow-lg text-lg">
                            Update Permissions
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>

</body>
</html>

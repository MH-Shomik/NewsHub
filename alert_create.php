<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $type = $_POST['type'];
    $severity = $_POST['severity'];
    $duration = $_POST['duration']; // Hours

    if (empty($title) || empty($message)) {
        $error = "Title and Message are required.";
    } else {
        try {
            $is_active = ($_SESSION['role'] === 'admin') ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO alerts (title, message, type, severity, is_active, expires_at, created_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())");
            $stmt->execute([$title, $message, $type, $severity, $is_active, $duration]);
            
            $action = ($is_active) ? 'Broadcasted alert' : 'Suggested alert';
            logActivity($pdo, $_SESSION['user_id'], 'Alert Created', "$action: $title");
            
            $success = ($is_active) ? "Alert broadcasted successfully!" : "Alert submitted, waiting for admin approval.";
        } catch (PDOException $e) {
            $error = "DB Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Alert | UIU NewsHub</title>
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
                <h2 class="font-heading text-xl font-bold text-slate-800">Broadcast Alert</h2>
                <a href="alerts_manage.php" class="text-sm font-bold text-slate-500 hover:text-slate-800">Cancel</a>
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

                <form action="" method="POST" class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Alert Title</label>
                        <input type="text" name="title" class="w-full px-5 py-4 text-lg font-heading font-bold rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 transition-all" placeholder="e.g. Heavy Traffic" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                             <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Type</label>
                             <select name="type" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                                <option value="traffic">Traffic</option>
                                <option value="weather">Weather</option>
                                <option value="campus">Campus</option>
                                <option value="national">National</option>
                                <option value="emergency">Emergency</option>
                             </select>
                        </div>
                        <div>
                             <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Severity</label>
                             <select name="severity" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                                <option value="info">Info (Blue)</option>
                                <option value="warning">Warning (Yellow)</option>
                                <option value="danger">Danger (Red)</option>
                             </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Duration</label>
                        <select name="duration" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500 cursor-pointer">
                            <option value="1">1 Hour</option>
                            <option value="3">3 Hours</option>
                            <option value="6">6 Hours</option>
                            <option value="12">12 Hours</option>
                            <option value="24">24 Hours</option>
                            <option value="48">2 Days</option>
                            <option value="168">1 Week</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-2 ml-1">Alert will automatically expire after this time.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Message</label>
                        <textarea name="message" rows="4" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:border-cool_sky-500" placeholder="Brief details about the alert..." required></textarea>
                    </div>

                    <button type="submit" class="w-full py-4 bg-strawberry_red-500 text-white font-bold rounded-xl hover:bg-strawberry_red-600 transition-all shadow-lg shadow-strawberry_red-200 text-lg">
                        Broadcast Alert
                    </button>
                </form>
            </div>
        </main>
    </div>

</body>
</html>

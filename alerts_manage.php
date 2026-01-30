<?php
session_start();
require_once 'includes/db_connect.php';

// Auth Check (Admin/Moderator Only)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}

// Handle Approve Action (Admin Only)
if (isset($_GET['approve_id']) && $_SESSION['role'] === 'admin') {
    $approve_id = $_GET['approve_id'];
    try {
        $stmt = $pdo->prepare("UPDATE alerts SET is_active = 1 WHERE alert_id = ?");
        $stmt->execute([$approve_id]);
        logActivity($pdo, $_SESSION['user_id'], 'Alert Approved', "Approved alert ID: $approve_id");
        header("Location: alerts_manage.php?msg=approved");
        exit;
    } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

// Handle Delete Action (Admin Only)
if (isset($_GET['delete_id'])) {
    if ($_SESSION['role'] !== 'admin') {
        $error = "Only admins can delete alerts.";
    } else {
        $delete_id = $_GET['delete_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM alerts WHERE alert_id = ?");
            $stmt->execute([$delete_id]);
            logActivity($pdo, $_SESSION['user_id'], 'Alert Deleted', "Deleted alert ID: $delete_id");
            header("Location: alerts_manage.php?msg=deleted");
            exit;
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// Fetch Alerts
try {
    $stmt = $pdo->query("SELECT * FROM alerts ORDER BY is_active DESC, created_at DESC");
    $alerts = $stmt->fetchAll();
} catch (PDOException $e) { $alerts = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Alerts | UIU NewsHub</title>
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
                <h2 class="font-heading text-xl font-bold text-slate-800">Manage System Alerts</h2>
                <a href="alert_create.php" class="px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-strawberry_red-500 transition-colors shadow-lg">Post Alert</a>
            </header>

            <div class="p-8 max-w-7xl mx-auto">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'approved'): ?>
                    <div class="bg-emerald-50 text-emerald-600 px-4 py-3 rounded-xl mb-4 text-sm font-bold border border-emerald-100 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Alert approved and broadcasted.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="bg-red-50 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm font-bold border border-red-100 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Alert deleted.
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($alerts)): ?>
                        <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-slate-200">
                             <p class="text-slate-500 font-bold">No active alerts.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($alerts as $alert): 
                            $bg = 'bg-white';
                            $border = 'border-slate-100';
                            $iconColor = 'text-slate-400';
                            $icon = 'INFO';
                            
                            if($alert['severity'] === 'danger') {
                                $border = 'border-strawberry_red-100';
                                $iconColor = 'text-strawberry_red-500';
                                $icon = '🚨';
                            } elseif($alert['severity'] === 'warning') {
                                $border = 'border-jasmine-200';
                                $iconColor = 'text-jasmine-500';
                                $icon = '⚠️';
                            }
                        ?>
                        <div class="bg-white p-6 rounded-3xl shadow-sm border <?php echo $border; ?> flex flex-col relative group hover:shadow-lg transition-all">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-xl <?php echo $iconColor; ?>">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="flex gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider px-2 py-1 rounded bg-slate-100 text-slate-500">
                                        <?php echo htmlspecialchars($alert['type']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h3 class="font-heading font-bold text-lg text-slate-900 mb-2 leading-tight">
                                <?php echo htmlspecialchars($alert['title']); ?>
                            </h3>
                            <p class="text-slate-500 text-sm mb-6 flex-1">
                                <?php echo htmlspecialchars($alert['message']); ?>
                            </p>
                            
                            <div class="pt-4 border-t border-slate-50 flex justify-between items-center text-xs">
                                <?php if($alert['is_active']): ?>
                                    <span class="text-emerald-500 font-bold flex items-center gap-1">
                                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> Active
                                    </span>
                                <?php else: ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400 font-bold">Pending Review</span>
                                        <?php if($_SESSION['role'] === 'admin'): ?>
                                            <a href="?approve_id=<?php echo $alert['alert_id']; ?>" class="text-emerald-600 font-bold hover:underline">Approve</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                <a href="?delete_id=<?php echo $alert['alert_id']; ?>" onclick="return confirm('Delete this alert?');" class="text-red-400 hover:text-red-600 font-bold hover:underline">Delete</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

</body>
</html>

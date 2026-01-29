<?php
session_start();
require_once 'includes/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This input can now be an Email OR a Student ID
    $login_id = trim($_POST['login_id']); 
    $password = $_POST['password'];

    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Check against both email and student_id
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR student_id = ?) AND status = 'active'");
            $stmt->execute([$login_id, $login_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                if (in_array($user['role'], ['admin', 'moderator'])) {
                    header("Location: dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } catch (PDOException $e) { 
            // In dev: echo $e->getMessage();
            $error = "System error. Please try again."; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | UIU NewsHub</title>
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
        .mesh-bg {
            background-color: #f0f9ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(203, 100%, 85%, 1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(169, 100%, 85%, 1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(28, 100%, 85%, 1) 0, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="mesh-bg min-h-screen flex items-center justify-center p-4">

    <!-- Back Button -->
    <a href="index.php" class="absolute top-6 left-6 flex items-center gap-2 text-slate-500 hover:text-cool_sky-600 transition-colors font-semibold px-4 py-2 rounded-full hover:bg-white/50">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Home
    </a>

    <div class="max-w-md w-full glass-card p-10 rounded-[2rem] relative overflow-hidden animate-fade-in-up">
        <!-- Decoration Line -->
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-cool_sky-400 via-aquamarine-400 to-tangerine_dream-400"></div>

        <div class="text-center mb-10">
            <div class="w-24 h-24 mx-auto mb-6">
                <img src="image.png" alt="Logo" class="w-full h-full object-contain rounded-full drop-shadow-xl hover:scale-105 transition-transform duration-300">
            </div>
            <h1 class="font-heading text-3xl font-bold text-slate-900">Welcome</h1>
            <p class="text-slate-500 mt-2 font-medium">Please enter your ID or Email</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-strawberry_red-50 border border-strawberry_red-100 text-strawberry_red-600 px-4 py-3 rounded-2xl mb-6 text-sm flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-6">
                <!-- Label updated -->
                <label for="login_id" class="block text-sm font-bold text-slate-700 mb-2 ml-1">Student ID or Email</label>
                <!-- Input name updated -->
                <input type="text" id="login_id" name="login_id" class="w-full px-5 py-4 rounded-2xl bg-white/50 border border-slate-200 focus:outline-none focus:ring-4 focus:ring-cool_sky-100 focus:border-cool_sky-400 transition-all font-medium placeholder-slate-400" placeholder="e.g. 011231011 or admin@uiu.ac.bd" required>
            </div>

            <div class="mb-8">
                <label for="password" class="block text-sm font-bold text-slate-700 mb-2 ml-1">Password</label>
                <input type="password" id="password" name="password" class="w-full px-5 py-4 rounded-2xl bg-white/50 border border-slate-200 focus:outline-none focus:ring-4 focus:ring-cool_sky-100 focus:border-cool_sky-400 transition-all font-medium placeholder-slate-400" placeholder="••••••••" required>
            </div>

            <button type="submit" class="w-full py-4 bg-slate-900 text-white font-bold rounded-2xl hover:bg-cool_sky-600 transition-all shadow-xl hover:shadow-glow transform hover:-translate-y-1 text-lg">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center text-sm font-medium text-slate-400">
            <p>Forgot password? Contact <a href="#" class="text-cool_sky-600 hover:text-cool_sky-700 underline">IT Support</a>.</p>
        </div>
    </div>

</body>
</html>

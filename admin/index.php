<?php
require_once '../config.php';
header('Content-Type: text/html; charset=utf-8');

session_start();

// Database Connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Auto-create site_settings table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(255) NOT NULL UNIQUE,
        val TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Initialize default settings if not exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (`key`, val) VALUES 
        ('brand_name', 'F Mail'),
        ('admin_password', 'admin123'),
        ('allowed_domains', 'dollicons.com,oakon.com,teihu.com,raleigh-construction.com,pastryofistanbul.com,questtechsystems.com'),
        ('smtp_host', 'smtp.gmail.com'),
        ('smtp_port', '587'),
        ('smtp_user', 'your-email@gmail.com'),
        ('smtp_pass', 'your-app-password'),
        ('smtp_enc', 'tls')");
    $stmt->execute();

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ./index.php');
    exit;
}

$is_logged_in = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $current_admin_pass = getSetting($pdo, 'admin_password', 'admin123');
    if ($_POST['password'] === $current_admin_pass) {
        $_SESSION['admin_auth'] = true;
        $is_logged_in = true;
    } else {
        $error = "Invalid Password";
    }
}

if (!$is_logged_in): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - F Mail</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100 w-full max-w-md text-center">
        <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
            <span class="text-white text-3xl font-black italic">F</span>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Admin Access</h2>
        <p class="text-slate-500 text-sm mb-8">Enter password to access dashboard</p>
        
        <form method="POST" class="space-y-4">
            <input type="password" name="password" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-center font-bold tracking-widest" placeholder="••••••••" required autofocus>
            <?php if (isset($error)): ?>
                <p class="text-red-500 text-xs font-bold"><?php echo $error; ?></p>
            <?php endif; ?>
            <button type="submit" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Login</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif;

// Database Connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Stats
$total_accounts = $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
$total_keys = $pdo->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
$active_keys = $pdo->query("SELECT COUNT(*) FROM api_keys WHERE status = 'active'")->fetchColumn();
$recent_activity = $pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 50")->fetchAll();
$all_accounts = $pdo->query("SELECT * FROM accounts ORDER BY created_at DESC LIMIT 100")->fetchAll();

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $setting_key => $val) {
            $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, val) VALUES (?, ?) ON DUPLICATE KEY UPDATE val = ?");
            $stmt->execute([$setting_key, $val, $val]);
        }
    }
    
    // Handle Super Links
    if (isset($_POST['super_links_label']) && is_array($_POST['super_links_label'])) {
        $super_data = [];
        foreach ($_POST['super_links_label'] as $idx => $label) {
            $url = $_POST['super_links_url'][$idx] ?? '';
            $image = $_POST['super_links_image'][$idx] ?? '';
            $status = $_POST['super_links_status'][$idx] ?? 'on';
            $description = $_POST['super_links_description'][$idx] ?? '';
            if (!empty($label) && !empty($url)) {
                $super_data[] = [
                    'label' => $label, 
                    'url' => $url,
                    'image' => $image,
                    'status' => $status,
                    'description' => $description
                ];
            }
        }
        $super_json = json_encode($super_data);
        $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, val) VALUES ('super_links', ?) ON DUPLICATE KEY UPDATE val = ?");
        $stmt->execute([$super_json, $super_json]);
    }

    // Handle More Links
    if (isset($_POST['links_label']) && is_array($_POST['links_label'])) {
        $links_data = [];
        foreach ($_POST['links_label'] as $idx => $label) {
            $url = $_POST['links_url'][$idx] ?? '';
            $image = $_POST['links_image'][$idx] ?? '';
            $status = $_POST['links_status'][$idx] ?? 'on'; // Default to on
            if (!empty($label) && !empty($url)) {
                $links_data[] = [
                    'label' => $label, 
                    'url' => $url,
                    'image' => $image,
                    'status' => $status
                ];
            }
        }
        $links_json = json_encode($links_data);
        $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, val) VALUES ('more_links', ?) ON DUPLICATE KEY UPDATE val = ?");
        $stmt->execute([$links_json, $links_json]);
    }

    // Handle admin password separately
    if (!empty($_POST['admin_password'])) {
        $new_pass = $_POST['admin_password'];
        $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, val) VALUES ('admin_password', ?) ON DUPLICATE KEY UPDATE val = ?");
        $stmt->execute([$new_pass, $new_pass]);
    }
    header('Location: ./index.php?settings_updated=1');
    exit;
}

$brand_name = getSetting($pdo, 'brand_name', 'F Mail');
$allowed_domains = getSetting($pdo, 'allowed_domains', 'dollicons.com,oakon.com,teihu.com,raleigh-construction.com,pastryofistanbul.com,questtechsystems.com');
$admin_pass_db = getSetting($pdo, 'admin_password', ADMIN_PASSWORD);
$favicon_url = getSetting($pdo, 'favicon_url', 'data:image/svg+xml,<svg viewBox=\'0 0 240 180\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'40\' height=\'40\' rx=\'8\' fill=\'%234F46E5\'/><text x=\'10\' y=\'30\' fill=\'white\' style=\'font: italic bold 25px sans-serif;\'>F</text></svg>');
$more_links_json = getSetting($pdo, 'more_links', '[]');
$more_links = json_decode($more_links_json, true) ?: [];
$super_links_json = getSetting($pdo, 'super_links', '[]');
$super_links = json_decode($super_links_json, true) ?: [];

// Fetch SMTP settings from DB after connection
$smtp_host = getSetting($pdo, 'smtp_host', SMTP_HOST);
$smtp_port = getSetting($pdo, 'smtp_port', SMTP_PORT);
$smtp_user = getSetting($pdo, 'smtp_user', SMTP_USER);
$smtp_pass = getSetting($pdo, 'smtp_pass', SMTP_PASS);
$smtp_enc  = getSetting($pdo, 'smtp_enc', SMTP_ENC);

// Update $smtp_config for legacy support if needed
$smtp_config = [
    'host' => $smtp_host,
    'port' => $smtp_port,
    'username' => $smtp_user,
    'password' => $smtp_pass,
    'encryption' => $smtp_enc
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($brand_name); ?></title>
    <link id="admin-favicon" rel="icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .section-hidden { display: none; }
        .nav-active { color: #4F46E5 !important; background-color: #EEF2FF !important; font-weight: 700 !important; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
    </style>
    <script>
        function showSection(sectionId) {
            console.log('Switching to section:', sectionId);
            const sections = document.querySelectorAll('.admin-section');
            sections.forEach(el => el.classList.add('section-hidden'));
            
            const target = document.getElementById('section-' + sectionId);
            if (target) {
                target.classList.remove('section-hidden');
            }
            
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(el => el.classList.remove('nav-active'));
            const activeNav = document.getElementById('nav-' + sectionId);
            if (activeNav) {
                activeNav.classList.add('nav-active');
            }
        }

        window.onload = () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('settings_updated')) {
                showSection('settings');
            }
        };

        function handleFaviconUpload(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('favicon_url_input').value = e.target.result;
                    document.getElementById('admin-favicon').href = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function handleLinkImageUpload(btn) {
            const fileInput = btn.previousElementSibling;
            fileInput.onchange = function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const textInput = btn.parentElement.querySelector('input[type="text"]');
                        textInput.value = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            };
            fileInput.click();
        }

        function addMoreLink() {
            const container = document.getElementById('more-links-container');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 items-end bg-slate-50/50 p-4 rounded-2xl relative group animate-fade-in';
            div.innerHTML = `
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Label</label>
                    <input type="text" name="links_label[]" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="e.g. Facebook">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">URL</label>
                    <input type="text" name="links_url[]" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="https://...">
                </div>
                <div class="space-y-2 relative">
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Icon/Image</label>
                    <div class="flex gap-2">
                        <input type="text" name="links_image[]" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="URL or Data URL">
                        <input type="file" class="hidden" accept="image/*">
                        <button type="button" onclick="handleLinkImageUpload(this)" class="px-3 py-3 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors"><i class="fas fa-image"></i></button>
                        <button type="button" onclick="this.closest('.group').remove()" class="px-3 py-3 text-red-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }
    </script>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-slate-200 flex flex-col fixed inset-y-0 shadow-sm">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-10">
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white text-2xl font-black italic">F</span>
                    </div>
                    <span class="text-2xl font-bold tracking-tight">Admin</span>
                </div>
                
                    <nav class="space-y-1">
                    <button onclick="showSection('overview')" id="nav-overview" class="nav-link w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 rounded-xl transition-all nav-active">
                        <i class="fas fa-chart-line text-lg"></i> Overview
                    </button>
                    <button onclick="showSection('users')" id="nav-users" class="nav-link w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 rounded-xl transition-all">
                        <i class="fas fa-users text-lg"></i> Users
                    </button>
                    <button onclick="showSection('activity')" id="nav-activity" class="nav-link w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 rounded-xl transition-all">
                        <i class="fas fa-list-ul text-lg"></i> Activity
                    </button>
                    <button onclick="showSection('settings')" id="nav-settings" class="nav-link w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 rounded-xl transition-all">
                        <i class="fas fa-cog text-lg"></i> Settings
                    </button>
                </nav>
            </div>
            
            <div class="mt-auto p-6 border-t border-slate-100">
                <a href="?logout=1" class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-500 hover:bg-red-50 rounded-xl transition-all">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8">
            <header class="flex items-center justify-between mb-10">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Dashboard</h1>
                    <p class="text-slate-500 text-sm">Welcome back, Administrator</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Server Status:</span>
                    <span class="flex items-center gap-2 px-3 py-1 bg-emerald-100 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Online
                    </span>
                </div>
            </header>

            <div id="section-overview" class="admin-section">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm group hover:shadow-md transition-all">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Total Accounts</p>
                        <div class="flex items-end justify-between">
                            <h3 class="text-4xl font-black text-slate-900"><?php echo $total_accounts; ?></h3>
                            <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                <i class="fas fa-user text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm group hover:shadow-md transition-all">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">API Keys</p>
                        <div class="flex items-end justify-between">
                            <h3 class="text-4xl font-black text-indigo-600"><?php echo $total_keys; ?></h3>
                            <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                <i class="fas fa-key text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm group hover:shadow-md transition-all">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Active Keys</p>
                        <div class="flex items-end justify-between">
                            <h3 class="text-4xl font-black text-emerald-600"><?php echo $active_keys; ?></h3>
                            <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- User List Preview -->
                    <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                            <h4 class="font-black text-slate-900 uppercase tracking-widest text-sm">Recent Accounts</h4>
                            <button onclick="showSection('users')" class="text-indigo-600 text-xs font-bold hover:underline">View All</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Email Address</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php foreach(array_slice($all_accounts, 0, 5) as $acc): ?>
                                    <tr class="hover:bg-slate-50/50 transition-all">
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-bold text-slate-700"><?php echo $acc['address']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-400 font-medium">
                                            <?php echo date('M d, H:i', strtotime($acc['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Activity Log Preview -->
                    <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                            <h4 class="font-black text-slate-900 uppercase tracking-widest text-sm">Latest Activity</h4>
                            <button onclick="showSection('activity')" class="text-indigo-600 text-xs font-bold hover:underline">View All</button>
                        </div>
                        <div class="p-6 space-y-6">
                            <?php foreach(array_slice($recent_activity, 0, 5) as $act): ?>
                            <div class="flex gap-4 relative">
                                <div class="w-8 h-8 rounded-full <?php 
                                    echo match($act['action']) {
                                        'generate_key' => 'bg-indigo-100 text-indigo-600',
                                        'send_external_mail' => 'bg-pink-100 text-pink-600',
                                        default => 'bg-slate-100 text-slate-600'
                                    };
                                ?> flex items-center justify-center shrink-0 z-10 text-[10px]">
                                    <i class="fas <?php 
                                        echo match($act['action']) {
                                            'generate_key' => 'fa-key',
                                            'send_external_mail' => 'fa-paper-plane',
                                            default => 'fa-bolt'
                                        };
                                    ?>"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="text-xs font-bold text-slate-900"><?php echo str_replace('_', ' ', $act['action']); ?></span>
                                    <p class="text-[11px] text-slate-500 truncate"><?php echo $act['details']; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="section-users" class="admin-section section-hidden">
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-50">
                        <h4 class="font-black text-slate-900 uppercase tracking-widest text-sm">All Accounts</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">ID</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Email Address</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Created At</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach($all_accounts as $acc): ?>
                                <tr class="hover:bg-slate-50 transition-all">
                                    <td class="px-6 py-4 text-sm font-bold text-slate-400"><?php echo $acc['id']; ?></td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-700"><?php echo $acc['address']; ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-500"><?php echo $acc['created_at']; ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-red-500 hover:text-red-700 font-bold text-xs">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="section-settings" class="admin-section section-hidden">
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-50">
                        <h4 class="font-black text-slate-900 uppercase tracking-widest text-sm">Site Settings</h4>
                    </div>
                    <form method="POST" class="p-8 space-y-6">
                        <input type="hidden" name="update_settings" value="1">
                        
                        <?php if (isset($_GET['settings_updated'])): ?>
                        <div class="p-4 bg-emerald-50 border border-emerald-100 text-emerald-600 rounded-2xl text-sm font-bold flex items-center gap-2 mb-6">
                            <i class="fas fa-check-circle"></i> Settings updated successfully!
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-400 uppercase ml-1">Brand Name</label>
                                <input type="text" name="settings[brand_name]" value="<?php echo htmlspecialchars($brand_name); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="e.g. F Mail">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-400 uppercase ml-1">Admin Password</label>
                                <input type="password" name="admin_password" value="<?php echo htmlspecialchars($admin_pass_db); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="••••••••">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase ml-1">Allowed Domains (Comma separated)</label>
                            <textarea name="settings[allowed_domains]" rows="4" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm resize-none" placeholder="dollicons.com,oakon.com,teihu.com..."><?php echo htmlspecialchars($allowed_domains); ?></textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase ml-1">Favicon (Upload from Device or URL)</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <input type="text" id="favicon_url_input" name="settings[favicon_url]" value="<?php echo htmlspecialchars($favicon_url); ?>" class="flex-1 px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="URL or Data SVG">
                                <div class="relative">
                                    <input type="file" id="favicon_file" class="hidden" accept="image/*" onchange="handleFaviconUpload(this)">
                                    <button type="button" onclick="document.getElementById('favicon_file').click()" class="w-full md:w-auto px-6 py-4 bg-slate-100 text-slate-600 font-bold rounded-2xl hover:bg-slate-200 transition-all text-sm flex items-center justify-center gap-2">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 ml-1 italic">Square image (PNG/ICO/SVG) recommended.</p>
                        </div>

                        <script>
                        function handleFaviconUpload(input) {
                            if (input.files && input.files[0]) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    document.getElementById('favicon_url_input').value = e.target.result;
                                    document.getElementById('admin-favicon').href = e.target.result;
                                };
                                reader.readAsDataURL(input.files[0]);
                            }
                        }
                        </script>

                        <!-- Super Links Section -->
                        <div class="pt-6 border-t border-slate-50">
                            <div class="flex items-center justify-between mb-6">
                                <h5 class="font-black text-amber-600 uppercase tracking-widest text-xs">Super Services (Exclusive)</h5>
                                <button type="button" onclick="addSuperLink()" class="bg-amber-100 text-amber-600 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-amber-200 transition-all">+ Add Super Service</button>
                            </div>
                            <div id="super-links-container" class="space-y-4 mb-10">
                                <?php foreach($super_links as $idx => $link): ?>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end bg-amber-50/30 p-4 rounded-2xl relative group border border-amber-100">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-amber-600 uppercase ml-1">Label</label>
                                        <input type="text" name="super_links_label[]" value="<?php echo htmlspecialchars($link['label']); ?>" class="w-full px-4 py-3 bg-white border border-amber-200 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 transition-all text-sm" placeholder="e.g. Super Mail">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-amber-600 uppercase ml-1">URL</label>
                                        <input type="text" name="super_links_url[]" value="<?php echo htmlspecialchars($link['url']); ?>" class="w-full px-4 py-3 bg-white border border-amber-200 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 transition-all text-sm" placeholder="https://...">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-amber-600 uppercase ml-1">Description</label>
                                        <input type="text" name="super_links_description[]" value="<?php echo htmlspecialchars($link['description'] ?? ''); ?>" class="w-full px-4 py-3 bg-white border border-amber-200 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 transition-all text-sm" placeholder="Brief description...">
                                    </div>
                                    <div class="space-y-2 relative">
                                        <label class="text-[10px] font-bold text-amber-600 uppercase ml-1">Icon/Image</label>
                                        <div class="flex gap-2">
                                            <input type="text" name="super_links_image[]" value="<?php echo htmlspecialchars($link['image'] ?? ''); ?>" class="flex-1 px-4 py-3 bg-white border border-amber-200 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 transition-all text-sm" placeholder="URL or Data URL">
                                            <input type="file" class="hidden" accept="image/*" onchange="handleLinkImageUpload(this)">
                                            <button type="button" onclick="this.previousElementSibling.click()" class="px-3 py-3 bg-amber-100 text-amber-600 rounded-xl hover:bg-amber-200 transition-colors"><i class="fas fa-image"></i></button>
                                        </div>
                                    </div>
                                    <div class="md:col-span-4 flex items-center gap-3 bg-white/50 p-2 rounded-xl border border-amber-100 mt-2">
                                        <div class="flex-1 flex items-center gap-3">
                                            <label class="text-[10px] font-bold text-amber-600 uppercase ml-1">Status</label>
                                            <select name="super_links_status[]" class="px-4 py-2 bg-white border border-amber-200 rounded-lg outline-none focus:ring-2 focus:ring-amber-500 transition-all text-xs font-bold">
                                                <option value="on" <?php echo ($link['status'] ?? 'on') === 'on' ? 'selected' : ''; ?>>Active (ON)</option>
                                                <option value="off" <?php echo ($link['status'] ?? 'on') === 'off' ? 'selected' : ''; ?>>Hidden (OFF)</option>
                                            </select>
                                        </div>
                                        <button type="button" onclick="this.closest('.group').remove()" class="px-4 py-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors text-xs font-bold flex items-center gap-2">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- More Links Section -->
                        <div class="pt-6 border-t border-slate-50">
                            <div class="flex items-center justify-between mb-6">
                                <h5 class="font-black text-slate-900 uppercase tracking-widest text-xs">More Links (Social & Others)</h5>
                                <button type="button" onclick="addMoreLink()" class="text-indigo-600 text-[10px] font-black uppercase tracking-widest hover:underline">+ Add New Link</button>
                            </div>
                            <div id="more-links-container" class="space-y-4">
                                <?php foreach($more_links as $idx => $link): ?>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end bg-slate-50/50 p-4 rounded-2xl relative group">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Label</label>
                                        <input type="text" name="links_label[]" value="<?php echo htmlspecialchars($link['label']); ?>" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="e.g. Facebook">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">URL</label>
                                        <input type="text" name="links_url[]" value="<?php echo htmlspecialchars($link['url']); ?>" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="https://...">
                                    </div>
                                    <div class="space-y-2 relative">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Icon/Image</label>
                                        <div class="flex gap-2">
                                            <input type="text" name="links_image[]" value="<?php echo htmlspecialchars($link['image'] ?? ''); ?>" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="URL or Data URL">
                                            <input type="file" class="hidden" accept="image/*" onchange="handleLinkImageUpload(this)">
                                            <button type="button" onclick="this.previousElementSibling.click()" class="px-3 py-3 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors"><i class="fas fa-image"></i></button>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Status & Action</label>
                                        <div class="flex items-center gap-3">
                                            <select name="links_status[]" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm font-bold">
                                                <option value="on" <?php echo ($link['status'] ?? 'on') === 'on' ? 'selected' : ''; ?>>Active (ON)</option>
                                                <option value="off" <?php echo ($link['status'] ?? 'on') === 'off' ? 'selected' : ''; ?>>Hidden (OFF)</option>
                                            </select>
                                            <button type="button" onclick="this.closest('.group').remove()" class="px-3 py-3 text-red-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <script>
                        function handleLinkImageUpload(input) {
                            if (input.files && input.files[0]) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    input.previousElementSibling.value = e.target.result;
                                };
                                reader.readAsDataURL(input.files[0]);
                            }
                        }

                        function addMoreLink() {
                            const container = document.getElementById('more-links-container');
                            const div = document.createElement('div');
                            div.className = 'grid grid-cols-1 md:grid-cols-4 gap-4 items-end bg-slate-50/50 p-4 rounded-2xl relative group animate-fade-in';
                            div.innerHTML = `
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Label</label>
                                    <input type="text" name="links_label[]" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="e.g. Facebook">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">URL</label>
                                    <input type="text" name="links_url[]" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="https://...">
                                </div>
                                <div class="space-y-2 relative">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Icon/Image</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="links_image[]" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="URL or Data URL">
                                        <input type="file" class="hidden" accept="image/*" onchange="handleLinkImageUpload(this)">
                                        <button type="button" onclick="this.previousElementSibling.click()" class="px-3 py-3 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors"><i class="fas fa-image"></i></button>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Status & Action</label>
                                    <div class="flex items-center gap-3">
                                        <select name="links_status[]" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm font-bold">
                                            <option value="on">Active (ON)</option>
                                            <option value="off">Hidden (OFF)</option>
                                        </select>
                                        <button type="button" onclick="this.closest('.group').remove()" class="px-3 py-3 text-red-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            `;
                            container.appendChild(div);
                        }
                        </script>

                        <!-- SMTP Settings -->
                        <div class="pt-6 border-t border-slate-50">
                            <h5 class="font-black text-slate-900 uppercase tracking-widest text-xs mb-6">SMTP Configuration</h5>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">SMTP Host</label>
                                    <input type="text" name="settings[smtp_host]" value="<?php echo htmlspecialchars($smtp_host); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="smtp.gmail.com">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">SMTP Port</label>
                                    <input type="text" name="settings[smtp_port]" value="<?php echo htmlspecialchars($smtp_port); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="587">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">Encryption</label>
                                    <select name="settings[smtp_enc]" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm">
                                        <option value="tls" <?php echo $smtp_enc === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $smtp_enc === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $smtp_enc === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">SMTP Username</label>
                                    <input type="text" name="settings[smtp_user]" value="<?php echo htmlspecialchars($smtp_user); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="email@gmail.com">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">SMTP Password</label>
                                    <input type="password" name="settings[smtp_pass]" value="<?php echo htmlspecialchars($smtp_pass); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm" placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" class="bg-indigo-600 text-white px-8 py-4 rounded-2xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php
require_once 'config.php';
header('Content-Type: application/json');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

function sendMail($to, $subject, $message, $from) {
    global $pdo;
    
    $host = getSetting($pdo, 'smtp_host', SMTP_HOST);
    $port = getSetting($pdo, 'smtp_port', SMTP_PORT);
    $user = getSetting($pdo, 'smtp_user', SMTP_USER);
    $pass = getSetting($pdo, 'smtp_pass', SMTP_PASS);
    $enc  = getSetting($pdo, 'smtp_enc', SMTP_ENC);
    
    // For now, we will use a more robust header set for the native mail() function.
    // In a real web environment, these DB settings would be used with PHPMailer.
    $headers = [
        'From' => $from,
        'Reply-To' => $from,
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ];

    $headerString = "";
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }

    // Try sending
    return @mail($to, $subject, $message, $headerString);
}

function verifyApiKey($pdo, $apiKey) {
    $stmt = $pdo->prepare("SELECT a.address, a.token FROM api_keys ak JOIN accounts a ON ak.user_id = a.id WHERE ak.api_key = ? AND ak.status = 'active'");
    $stmt->execute([$apiKey]);
    return $stmt->fetch();
}

try {
     $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
     
     // Auto-create tables if they don't exist
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
        ('smtp_enc', 'tls'),
        ('favicon_url', 'data:image/svg+xml,<svg viewBox=\'0 0 240 180\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'40\' height=\'40\' rx=\'8\' fill=\'%234F46E5\'/><text x=\'10\' y=\'30\' fill=\'white\' style=\'font: italic bold 25px sans-serif;\'>F</text></svg>'),
        ('more_links', '[{\"label\":\"Facebook\",\"url\":\"https://facebook.com\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/b/b8/2021_Facebook_icon.svg\"},{\"label\":\"Twitter\",\"url\":\"https://twitter.com\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/6/6f/Logo_of_Twitter.svg\"},{\"label\":\"Instagram\",\"url\":\"https://instagram.com\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/e/e7/Instagram_logo_2016.svg\"},{\"label\":\"Telegram\",\"url\":\"https://telegram.org\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg\"},{\"label\":\"YouTube\",\"url\":\"https://youtube.com\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/0/09/YouTube_full-color_icon_%282017%29.svg\"},{\"label\":\"GitHub\",\"url\":\"https://github.com\",\"image\":\"https://upload.wikimedia.org/wikipedia/commons/9/91/Octicons-mark-github.svg\"}]'),
        ('super_links', '[]')");
     $stmt->execute();

     $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
         id INT AUTO_INCREMENT PRIMARY KEY,
         address VARCHAR(255) NOT NULL UNIQUE,
         password VARCHAR(255) NOT NULL,
         token TEXT,
         account_id VARCHAR(255),
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     )");

     $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
         id INT AUTO_INCREMENT PRIMARY KEY,
         user_id INT,
         api_key VARCHAR(64) NOT NULL UNIQUE,
         status ENUM('active', 'revoked') DEFAULT 'active',
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE
     )");

     $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
         id INT AUTO_INCREMENT PRIMARY KEY,
         action VARCHAR(255) NOT NULL,
         details TEXT,
         ip_address VARCHAR(45),
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     )");

} catch (\PDOException $e) {
     header('HTTP/1.1 500 Internal Server Error');
     echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
     exit;
}

function logActivity($pdo, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO activity_log (action, details, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$action, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently to not interrupt main flow
    }
}

    $action = $_GET['action'] ?? '';

    if ($action === 'generate_key') {
        $address = $_GET['address'] ?? '';
        $password = $_GET['password'] ?? '';
        if (!$address) {
            http_response_code(400);
            echo json_encode(['error' => 'Address required']);
            exit;
        }

        try {
            // Check if account exists
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE address = ?");
            $stmt->execute([$address]);
            $account = $stmt->fetch();

            if (!$account) {
                if ($password) {
                    // Auto-register in local DB
                    $stmt = $pdo->prepare("INSERT INTO accounts (address, password) VALUES (?, ?)");
                    $stmt->execute([$address, $password]);
                    $accountId = $pdo->lastInsertId();
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Account not found in local DB. Please provide password to register.']);
                    exit;
                }
            } else {
                $accountId = $account['id'];
            }

            // Generate API key
            $apiKey = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)");
            $stmt->execute([$accountId, $apiKey]);

            logActivity($pdo, 'generate_key', "Address: $address");
            echo json_encode(['api_key' => $apiKey]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'list_keys') {
        $address = $_GET['address'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT ak.api_key, ak.status, ak.created_at FROM api_keys ak JOIN accounts a ON ak.user_id = a.id WHERE a.address = ?");
            $stmt->execute([$address]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'delete_key') {
        $apiKey = $_GET['api_key'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM api_keys WHERE api_key = ?");
            $stmt->execute([$apiKey]);
            logActivity($pdo, 'delete_key', "Key: $apiKey");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'admin_stats') {
        $admin_pass = $_GET['admin_pass'] ?? '';
        if ($admin_pass !== 'admin123') { // Simple hardcoded pass for now
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        try {
            $stats = [
                'total_accounts' => $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
                'total_keys' => $pdo->query("SELECT COUNT(*) FROM api_keys")->fetchColumn(),
                'active_keys' => $pdo->query("SELECT COUNT(*) FROM api_keys WHERE status = 'active'")->fetchColumn(),
                'recent_accounts' => $pdo->query("SELECT address, created_at FROM accounts ORDER BY created_at DESC LIMIT 5")->fetchAll()
            ];
            echo json_encode($stats);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } elseif ($action === 'admin_list_accounts') {
        $admin_pass = $_GET['admin_pass'] ?? '';
        if ($admin_pass !== 'admin123') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        echo json_encode($pdo->query("SELECT * FROM accounts ORDER BY created_at DESC")->fetchAll());
    } elseif ($action === 'admin_delete_account') {
        $admin_pass = $_GET['admin_pass'] ?? '';
        $id = $_GET['id'] ?? '';
        if ($admin_pass !== 'admin123') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, 'admin_delete_account', "Account ID: $id");
        echo json_encode(['success' => true]);
    } elseif ($action === 'admin_update_super_links') {
        $admin_pass = $_GET['admin_pass'] ?? $_POST['admin_pass'] ?? '';
        if ($admin_pass !== 'admin123') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $links = $_POST['links'] ?? '';
        if (!$links) {
            http_response_code(400);
            echo json_encode(['error' => 'Links data required']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE site_settings SET val = ? WHERE `key` = 'super_links'");
        $stmt->execute([$links]);
        logActivity($pdo, 'admin_update_super_links', "Super services updated by admin");
        echo json_encode(['success' => true]);
    } elseif ($action === 'admin_update_links') {
        $admin_pass = $_GET['admin_pass'] ?? $_POST['admin_pass'] ?? '';
        if ($admin_pass !== 'admin123') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $links = $_POST['links'] ?? '';
        if (!$links) {
            http_response_code(400);
            echo json_encode(['error' => 'Links data required']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE site_settings SET val = ? WHERE `key` = 'more_links'");
        $stmt->execute([$links]);
        logActivity($pdo, 'admin_update_links', "Links updated by admin");
        echo json_encode(['success' => true]);
    } elseif ($action === 'admin_list_activity') {
        $admin_pass = $_GET['admin_pass'] ?? '';
        if ($admin_pass !== 'admin123') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        echo json_encode($pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 50")->fetchAll());
    } elseif ($action === 'send_external_mail') {
        $from = $_POST['from'] ?? $_GET['from'] ?? '';
        $to = $_POST['to'] ?? $_GET['to'] ?? '';
        $subject = $_POST['subject'] ?? $_GET['subject'] ?? '';
        $message = $_POST['message'] ?? $_GET['message'] ?? '';
        
        if (!$from || !$to || !$subject || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields (from, to, subject, message) are required']);
            exit;
        }

        try {
            // No domain restriction for premium sending
            if (sendMail($to, $subject, $message, $from)) {
                logActivity($pdo, 'send_external_mail', "From: $from, To: $to (Success)");
                echo json_encode(['success' => true]);
            } else {
                $error = error_get_last();
                $details = "From: $from, To: $to (Failed)";
                if ($error) $details .= " Error: " . $error['message'];
                logActivity($pdo, 'send_external_mail', $details);
                echo json_encode(['success' => false, 'error' => 'Server failed to send email. Check SMTP settings. ' . ($error ? $error['message'] : '')]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'send_test') {
        $address = $_GET['address'] ?? '';
        $sender = $_GET['sender'] ?? 'Test Bot';
        
        if (!$address) {
            http_response_code(400);
            echo json_encode(['error' => 'Address required']);
            exit;
        }

        $subject = BRAND_NAME . " - Test Email from $sender";
        $message = "This is a test email sent at " . date('Y-m-d H:i:s') . " to verify your inbox.";
        
        if (sendMail($address, $subject, $message, "test@mail.tm")) {
            logActivity($pdo, 'send_test', "To: $address, From: $sender (Success)");
            echo json_encode(['success' => true]);
        } else {
            logActivity($pdo, 'send_test', "To: $address, From: $sender (Failed)");
            echo json_encode(['success' => false, 'error' => 'Failed to send test email.']);
        }
    } elseif ($action === 'me') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        if (!$token) {
            echo json_encode(['error' => 'No token']);
            exit;
        }

        $ch = curl_init("https://api.mail.tm/me");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Accept: application/json",
            "Content-Type: application/json"
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            http_response_code($httpCode);
            echo $res;
            exit;
        }

        // Return the response from mail.tm
        $me_data = json_decode($res, true);
        
        // Fetch social links and other settings to include in the response
        $more_links_json = getSetting($pdo, 'more_links', '[]');
        $more_links = json_decode($more_links_json, true) ?: [];
        $brand_name = getSetting($pdo, 'brand_name', 'F Mail');
        $favicon_url = getSetting($pdo, 'favicon_url', '');

        echo json_encode([
            'me' => $me_data,
            'settings' => [
                'brand_name' => $brand_name,
                'favicon_url' => $favicon_url,
                'more_links' => $more_links
            ]
        ]);
    } elseif ($action === 'get_allowed_domains') {
        $domains_str = getSetting($pdo, 'allowed_domains', 'dollicons.com,oakon.com,teihu.com,raleigh-construction.com,pastryofistanbul.com,questtechsystems.com');
        $domains = array_map('trim', explode(',', $domains_str));
        $brand_name = getSetting($pdo, 'brand_name', 'F Mail');
        $favicon_url = getSetting($pdo, 'favicon_url', '');
        $more_links_json = getSetting($pdo, 'more_links', '[]');
        $super_links_json = getSetting($pdo, 'super_links', '[]');
        
        $more_links = json_decode($more_links_json, true);
        if ($more_links === null || !is_array($more_links)) $more_links = [];

        $super_links = json_decode($super_links_json, true);
        if ($super_links === null || !is_array($super_links)) $super_links = [];

        header('Content-Type: application/json');
        echo json_encode([
            'domains' => $domains,
            'settings' => [
                'brand_name' => $brand_name,
                'favicon_url' => $favicon_url,
                'more_links' => $more_links,
                'super_links' => $super_links
            ]
        ]);
        exit;
    } elseif ($action === 'premium_delete_all') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        if (!$token) {
            echo json_encode(['error' => 'No token']);
            exit;
        }

        // Fetch all messages first
        $ch = curl_init("https://api.mail.tm/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]);
        $res = curl_exec($ch);
        $data = json_decode($res, true);
        curl_close($ch);

        $msgs = $data['hydra:member'] ?? [];
        $success_count = 0;
        foreach ($msgs as $m) {
            $id = $m['id'];
            $ch = curl_init("https://api.mail.tm/messages/$id");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $token"
            ]);
            curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 204) {
                $success_count++;
            }
            curl_close($ch);
        }

        logActivity($pdo, 'premium_delete_all', "Deleted $success_count messages");
        echo json_encode(['success' => true, 'deleted' => $success_count]);
    } elseif ($action === 'fetch_mail_via_api') {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        if (!$apiKey) {
            http_response_code(401);
            echo json_encode(['error' => 'API Key required']);
            exit;
        }

        $auth = verifyApiKey($pdo, $apiKey);
        if (!$auth) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid or revoked API Key']);
            exit;
        }

        // Forward request to mail.tm
        $ch = curl_init("https://api.mail.tm/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $auth['token'],
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logActivity($pdo, 'api_fetch_mail', "Address: " . $auth['address']);
        http_response_code($httpCode);
        echo $response;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
?>

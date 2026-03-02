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
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
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
    } catch (Exception $e) {}
}

function verifyApiKey($pdo, $apiKey) {
    $stmt = $pdo->prepare("SELECT a.address, a.token FROM api_keys ak JOIN accounts a ON ak.user_id = a.id WHERE ak.api_key = ? AND ak.status = 'active'");
    $stmt->execute([$apiKey]);
    return $stmt->fetch();
}

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $address = $_POST['address'] ?? $_GET['address'] ?? '';
    $password = $_POST['password'] ?? $_GET['password'] ?? '';
    if (!$address || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Address and password required']);
        exit;
    }
    try {
        $ch = curl_init("https://api.mail.tm/accounts");
        $payload = json_encode(['address' => $address, 'password' => $password]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 201 || $httpCode === 422) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO accounts (address, password) VALUES (?, ?)");
            $stmt->execute([$address, $password]);
            logActivity($pdo, 'register', "New user registered: $address");
            echo json_encode(['success' => true, 'address' => $address]);
        } else {
            $errorData = json_decode($res, true);
            http_response_code($httpCode ?: 500);
            echo json_encode(['error' => $errorData['hydra:description'] ?? 'Registration failed']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'login') {
    $address = $_POST['address'] ?? $_GET['address'] ?? '';
    $password = $_POST['password'] ?? $_GET['password'] ?? '';
    if (!$address || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Address and password required']);
        exit;
    }
    try {
        $ch = curl_init("https://api.mail.tm/token");
        $payload = json_encode(['address' => $address, 'password' => $password]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $data = json_decode($res, true);
            $token = $data['token'];
            $accountId = $data['id'];
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE address = ?");
            $stmt->execute([$address]);
            $account = $stmt->fetch();
            if (!$account) {
                $stmt = $pdo->prepare("INSERT INTO accounts (address, password, token, account_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$address, $password, $token, $accountId]);
            } else {
                $stmt = $pdo->prepare("UPDATE accounts SET password = ?, token = ?, account_id = ? WHERE address = ?");
                $stmt->execute([$password, $token, $accountId, $address]);
            }
            logActivity($pdo, 'login', "User logged in: $address");
            echo json_encode(['success' => true, 'token' => $token, 'address' => $address]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'generate_key') {
    $address = $_GET['address'] ?? '';
    $password = $_GET['password'] ?? '';
    if (!$address || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Address and password required']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE address = ?");
        $stmt->execute([$address]);
        $account = $stmt->fetch();
        if (!$account) {
            $ch = curl_init("https://api.mail.tm/accounts");
            $payload = json_encode(['address' => $address, 'password' => $password]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 201 || $httpCode === 422) {
                $stmt = $pdo->prepare("INSERT INTO accounts (address, password) VALUES (?, ?)");
                $stmt->execute([$address, $password]);
                $accountId = $pdo->lastInsertId();
            } else {
                http_response_code($httpCode ?: 500);
                echo $res; exit;
            }
        } else {
            $accountId = $account['id'];
        }
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)");
        $stmt->execute([$accountId, $apiKey]);
        logActivity($pdo, 'generate_key', "API Key created for: $address");
        echo json_encode(['api_key' => $apiKey]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'log_activity') {
    $auth_apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $log_action = $_GET['log_action'] ?? '';
    $log_details = $_GET['log_details'] ?? '';
    if ($auth_apiKey) {
        $auth = verifyApiKey($pdo, $auth_apiKey);
        if ($auth) {
            logActivity($pdo, $log_action, "User (" . $auth['address'] . "): " . $log_details);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    logActivity($pdo, $log_action, $log_details);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'admin_stats') {
    $admin_pass = $_GET['admin_pass'] ?? '';
    if ($admin_pass !== ADMIN_PASSWORD) {
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
    exit;
}

if ($action === 'admin_list_accounts') {
    $admin_pass = $_GET['admin_pass'] ?? '';
    if ($admin_pass !== ADMIN_PASSWORD) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    echo json_encode($pdo->query("SELECT * FROM accounts ORDER BY created_at DESC")->fetchAll());
    exit;
}

if ($action === 'admin_delete_account') {
    $admin_pass = $_GET['admin_pass'] ?? '';
    $id = $_GET['id'] ?? '';
    if ($admin_pass !== ADMIN_PASSWORD) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
    $stmt->execute([$id]);
    logActivity($pdo, 'admin_delete_account', "Account ID: $id deleted by admin");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'admin_update_links') {
    $admin_pass = $_POST['admin_pass'] ?? $_GET['admin_pass'] ?? '';
    if ($admin_pass !== ADMIN_PASSWORD) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $links = $_POST['links'] ?? '';
    $stmt = $pdo->prepare("UPDATE site_settings SET val = ? WHERE `key` = 'more_links'");
    $stmt->execute([$links]);
    logActivity($pdo, 'admin_update_links', "Links updated by admin");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'admin_list_activity') {
    $admin_pass = $_GET['admin_pass'] ?? '';
    if ($admin_pass !== ADMIN_PASSWORD) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    echo json_encode($pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 100")->fetchAll());
    exit;
}

if ($action === 'get_allowed_domains') {
    $domains_str = getSetting($pdo, 'allowed_domains', 'dollicons.com,oakon.com,teihu.com');
    $domains = array_map('trim', explode(',', $domains_str));
    echo json_encode([
        'domains' => $domains,
        'settings' => [
            'brand_name' => getSetting($pdo, 'brand_name', 'F Mail'),
            'favicon_url' => getSetting($pdo, 'favicon_url', ''),
            'more_links' => json_decode(getSetting($pdo, 'more_links', '[]'), true),
            'super_links' => json_decode(getSetting($pdo, 'super_links', '[]'), true)
        ]
    ]);
    exit;
}

if ($action === 'fetch_mail_via_api') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $auth = verifyApiKey($pdo, $apiKey);
    if (!$auth) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API Key']);
        exit;
    }
    $ch = curl_init("https://api.mail.tm/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $auth['token']]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    logActivity($pdo, 'api_fetch_mail', "Mail fetched via API for: " . $auth['address']);
    http_response_code($httpCode);
    echo $response;
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
?>

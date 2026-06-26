<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// config.php
date_default_timezone_set('Europe/Kyiv');

$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    file_put_contents('debug.txt', "\n[" . date('H:i:s') . "] ПОМИЛКА: Файл .env не знайдено.\n", FILE_APPEND);
    die("Критична помилка: Конфігураційний файл не знайдено.");
}

$env = [];
$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
}

// РЕЄСТРУЄМО ВСІ КОНСТАНТИ
define('BOT_TOKEN', $env['BOT_TOKEN'] ?? '');
define('MODERBOT_TOKEN', $env['MODERBOT_TOKEN'] ?? '');
define('GROUP_CHAT_ID', $env['GROUP_CHAT_ID'] ?? ''); 
define('TEST_GROUP_CHAT_ID', $env['TEST_GROUP_CHAT_ID'] ?? '');
define('PINNED_MSG_ID', 12345); 

define('DB_HOST', $env['DB_HOST'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? ''); 
define('DB_USER', $env['DB_USER'] ?? ''); 
define('DB_PASS', $env['DB_PASS'] ?? '');

$ALLOWED_ADMINS = [
    457725573  => 'SARMAK',
    1071080055 => 'RAM RAM',
    5121009296 => 'Надія Потапенко',
    1322554388 => 'Роман Потапенко',
    2086693312 => 'Сергій Шинкаренко',
    5151274346 => 'Вікторія Таран',
    000000000  => 'Дмитро Таран',
    5274939598 => 'Наташа Кальченко'
];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    file_put_contents('debug.txt', "\n[" . date('H:i:s') . "] ПОМИЛКА БД: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database error.");
}

function sendTelegramRequest($method, $data = []) {
    if (empty(BOT_TOKEN)) {
        file_put_contents('debug.txt', "\n[" . date('H:i:s') . "] ПОМИЛКА: BOT_TOKEN порожній!\n", FILE_APPEND);
        return false;
    }

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
?>
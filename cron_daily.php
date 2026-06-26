<?php
// cron_daily.php
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Europe/Kyiv');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schedules.php';

$token = MODERBOT_TOKEN; 
$group_chat_id = GROUP_CHAT_ID; 

$trains_kyiv = ['779', '775', '143']; 
$trains_sumy = ['776', '66', '144'];

$train_routes = [
    '779' => 'СУМИ → КИЇВ', '775' => 'СУМИ → КИЇВ', '143' => 'СУМИ → Рахів',
    '776' => 'КИЇВ → СУМИ', '66' => 'КИЇВ → СУМИ', '144' => 'Рахів → СУМИ'
];

function areSpecialTrainsActiveLocal($timestamp) {
    $m = (int)date('n', $timestamp);
    $d = (int)date('j', $timestamp);
    $is_even = ($d % 2 === 0);
    
    if ($m == 6 || $m == 7) return $is_even;
    return false;
}

$now = time();
$special_active = areSpecialTrainsActiveLocal($now);

$from_sumy = [];
foreach ($trains_kyiv as $t) {
    if (($t === '143' || $t === '144') && !$special_active) continue;
    if (isset($schedules[$t])) {
        $from_sumy[] = ['id' => $t, 'route' => $train_routes[$t], 'schedule' => $schedules[$t]];
    }
}

$from_kyiv = [];
foreach ($trains_sumy as $t) {
    if (($t === '143' || $t === '144') && !$special_active) continue;
    if (isset($schedules[$t])) {
        $from_kyiv[] = ['id' => $t, 'route' => $train_routes[$t], 'schedule' => $schedules[$t]];
    }
}

// Виправляємо дату та додаємо парність
$date_str = date('d.m.Y', $now);
$day_type = (date('j', $now) % 2 === 0) ? 'парне число' : 'непарне число';

$message = "🚂 Вітаю учасників групи!\n\n📅 Розклад потягів на {$date_str} ({$day_type}):\n\n";

if (!empty($from_sumy)) {
    foreach ($from_sumy as $t) {
        $message .= "🔺 <b>Потяг {$t['id']}</b> (<b>{$t['route']}</b>)\n🔸 ";
        $stops = [];
        foreach ($t['schedule'] as $station => $time) {
            $stops[] = "{$time} {$station}"; 
        }
        $message .= implode(' → ', $stops) . "\n\n";
    }
}

if (!empty($from_kyiv)) {
    foreach ($from_kyiv as $t) {
        $message .= "🔻 <b>Потяг {$t['id']}</b> (<b>{$t['route']}</b>)\n🔸 ";
        $stops = [];
        foreach ($t['schedule'] as $station => $time) {
            $stops[] = "{$time} {$station}"; 
        }
        $message .= implode(' → ', $stops) . "\n\n";
    }
}

$message .= "<b>Бажаю всім спокійного дня та безпечних поїздок! 🇺🇦</b>\nІ не забувайте заглядувати в правила групи 😊\n\n";
    $message .= "<a href=\"https://sarmak.pp.ua/tlgbot/uzbot/calculator/calculator.php\">Інтерактивний КАЛЬКУЛЯТОР ЗАТРИМОК (Тестова версія)</a>, інструкція по користуванню є за посиланням.";

$url = "https://api.telegram.org/bot$token/sendMessage";

$post_fields = [
    'chat_id' => $group_chat_id,
    'text' => $message,
    'parse_mode' => 'HTML',
    'link_preview_options' => json_encode(['is_disabled' => true])
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_exec($ch);
curl_close($ch);
?>
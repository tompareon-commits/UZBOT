<?php
// index.php
require_once 'config.php';
require_once 'schedules.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);
file_put_contents('debug.txt', "\n[" . date('H:i:s') . "] ВХІДНІ: " . $content . "\n", FILE_APPEND);

if (!$update) { http_response_code(200); exit; }

$trains_kyiv = ['779', '775', '143']; 
$trains_sumy = ['66', '776', '144'];  
$train_routes = [
    '779' => 'СУМИ → КИЇВ', '775' => 'СУМИ → КИЇВ', '143' => 'СУМИ → Рахів',
    '776' => 'КИЇВ → СУМИ', '66' => 'КИЇВ → СУМИ', '144' => 'Рахів → СУМИ'
];

$train_urls = [
    '66'  => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=105819&by_id=1',
    '779' => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=105965&by_id=1',
    '143' => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=105663&by_id=1',
    '144' => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=105743&by_id=1',
    '775' => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=103355&by_id=1',
    '776' => 'https://www.uz.gov.ua/passengers/timetable/?ntrain=103356&by_id=1'
];

$stations = ['СУМИ', 'Торопилівка', 'Головашівка', 'Віри', 'Амбари', 'Торохтяний', 'БІЛОПІЛЛЯ', 'ВОРОЖБА', 'Кошари', 'Карпилівка', 'Клепали', 'ПУТИВЛЬ', 'Путійська', 'Грузьке', 'Дубовязівка', 'КОНОТОП', 'БАХМАЧ', 'НІЖИН', 'ДАРНИЦЯ', 'КИЇВ'];
$statuses = ['🟢 Відправляється', '🟡 Прибуває', '🔴 Стоїть', '🔵 Проїзжає'];

function getScheduleContext($train) {
    global $schedules;
    if (!isset($schedules[$train]) || empty($schedules[$train])) return "";

    $now_ts = time();
    
    reset($schedules[$train]);
    $first_station = key($schedules[$train]);
    $first_time = current($schedules[$train]);
    $first_ts = strtotime($first_time);

    if ($first_ts > $now_ts + 43200) $first_ts -= 86400;
    if ($first_ts < $now_ts - 43200) $first_ts += 86400;

    if ($now_ts < $first_ts) {
        return "За розкладом: <b>ще не відправився</b> (відправлення з {$first_station} о {$first_time})\n";
    }

    $passed_station = null;
    $passed_time = null;
    $next_station = null;
    $next_time = null;

    foreach ($schedules[$train] as $station => $time) {
        if (empty($time)) continue;
        $sched_ts = strtotime($time);
        
        if ($sched_ts > $now_ts + 43200) $sched_ts -= 86400;
        if ($sched_ts < $now_ts - 43200) $sched_ts += 86400;
        
        if ($sched_ts <= $now_ts) {
            $passed_station = $station;
            $passed_time = $time;
        } else {
            if ($next_station === null) {
                $next_station = $station;
                $next_time = $time;
            }
        }
    }
    
    if ($next_station === null && $passed_station !== null) {
        return "За розкладом: прибув на кінцеву <b>{$passed_station}</b> ({$passed_time})\n";
    }
    
    $parts = [];
    if ($next_station) $parts[] = "наступна <b>{$next_station}</b> ({$next_time})";
    if ($passed_station) $parts[] = "проїхав <b>{$passed_station}</b> ({$passed_time})";
    
    if (empty($parts)) return "";
    return "За розкладом: " . implode(', ', $parts) . "\n";
}

function getDelayMinutes($train, $station, $actual_time) {
    global $schedules;
    if (!isset($schedules[$train][$station]) || empty($schedules[$train][$station])) return null;

    $scheduled = strtotime($schedules[$train][$station]);
    $actual = strtotime($actual_time);

    $diff = $actual - $scheduled;
    
    if ($diff < -300) { 
        $diff += 86400; 
    } elseif ($diff < 0) {
        $diff = 0;
    }

    return round($diff / 60);
}

function formatDelay($delay) {
    if ($delay === null) return " | 📊 час затримки для цієї станції не розраховується";
    if ($delay <= 0) return " | 📊 За графіком";
    
    $h = floor($delay / 60);
    $m = $delay % 60;
    
    $parts = [];
    if ($h > 0) $parts[] = "{$h} год";
    if ($m > 0) $parts[] = "{$m} хв";
    
    return " | 📊 запізнення " . implode(' ', $parts);
}

global $ALLOWED_ADMINS;

function isAuthorized($user_id) {
    global $ALLOWED_ADMINS;
    return isset($ALLOWED_ADMINS[$user_id]);
}

function getAdminInitials($user_id) {
    global $ALLOWED_ADMINS;
    if (!isset($ALLOWED_ADMINS[$user_id])) return '';
    
    $name = $ALLOWED_ADMINS[$user_id];
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (mb_strlen($w) > 0) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
        }
    }
    return mb_substr($initials, 0, 2); 
}

// ==========================================
// ОБРОБКА ПОВІДОМЛЕНЬ
// ==========================================
$msg_data = null;
if (isset($update['message'])) {
    $msg_data = $update['message'];
} elseif (isset($update['edited_message'])) {
    $msg_data = $update['edited_message'];
}

if ($msg_data) {
    $chat_id = $msg_data['chat']['id'];
    $chat_type = $msg_data['chat']['type'] ?? 'private';
    $text = $msg_data['text'] ?? '';
    $user_id = $msg_data['from']['id'];
    $msg_id = $msg_data['message_id'];
    
    if ($chat_type !== 'private') {
        file_put_contents('debug.txt', "DEBUG GROUP: chat_id=" . $chat_id . " (type: " . gettype($chat_id) . "), GROUP_CHAT_ID=" . GROUP_CHAT_ID . " (type: " . gettype(GROUP_CHAT_ID) . "), TEST_GROUP_CHAT_ID=" . (defined('TEST_GROUP_CHAT_ID') ? TEST_GROUP_CHAT_ID : 'NOT_DEFINED') . " (type: " . (defined('TEST_GROUP_CHAT_ID') ? gettype(TEST_GROUP_CHAT_ID) : 'N/A') . ")\n", FILE_APPEND);
        if (($chat_id == GROUP_CHAT_ID || ($chat_id == TEST_GROUP_CHAT_ID && TEST_GROUP_CHAT_ID !== '')) && !empty($text)) {
            $user_name = trim(($msg_data['from']['first_name'] ?? '') . ' ' . ($msg_data['from']['last_name'] ?? ''));
            if (empty($user_name)) $user_name = 'Анонім';
            
            $file = __DIR__ . '/group_messages.json';
            $messages = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            if (!is_array($messages)) $messages = [];
            
            $exists = false;
            foreach ($messages as &$m) {
                if (isset($m['msg_id']) && $m['msg_id'] == $msg_id) {
                    $m['text'] = $text;
                    $m['time'] = date('H:i');
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $messages[] = [
                    'msg_id' => $msg_id,
                    'user_name' => $user_name,
                    'text' => $text,
                    'time' => date('H:i')
                ];
            }
            
            if (count($messages) > 15) {
                $messages = array_slice($messages, -15);
            }
            
            file_put_contents($file, json_encode($messages));
        }
        http_response_code(200); exit;
    }

    if (!isAuthorized($user_id)) {
        if (strpos($text, '/') === 0) {
            sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "🚫 <b>Доступ лише для репортерів. Якщо бажаєте ним стати звертайтесь до @sarmakey</b>", 'parse_mode' => 'HTML']);
        }
        http_response_code(200); exit;
    }

    $author_name = $ALLOWED_ADMINS[$user_id] ?? 'Unknown';

    if (isset($msg_data['reply_to_message'])) {
        $reply_text = $msg_data['reply_to_message']['text'] ?? '';
        
        if (preg_match('/Введіть станцію для потяга (\d+)/u', $reply_text, $matches)) {
            $train = $matches[1];
            $station_name = trim($text); 
            $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
            $route = $train_routes[$train] ?? '';
            
            $keyboard = []; $row = [];
            foreach ($statuses as $idx => $status) {
                $row[] = ['text' => $status, 'callback_data' => "st|{$train}|{$station_name}|{$idx}"];
                if (count($row) == 2) { $keyboard[] = $row; $row = []; }
            }
            if (!empty($row)) $keyboard[] = $row;
            $keyboard[] = [['text' => '🔙 Назад', 'callback_data' => "b|2|{$train}"]];
            
            $msg = "Крок 3/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n📍 Станція: <b>ст. {$station_name}</b>\n\nОберіть статус:";
            sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $keyboard]]);
        }
        
        if (preg_match('/Коментар для: (\d+) \| (.*?) \| (\d+)(?: \| (.*?))?/u', $reply_text, $matches)) {
            $train = $matches[1]; 
            $location = trim($matches[2]); 
            $status_idx = (int)$matches[3]; 
            $time_str = (isset($matches[4]) && $matches[4] !== '') ? trim($matches[4]) : date('H:i');
            
            $status_text = $statuses[$status_idx]; $comment = mb_substr(trim($text), 0, 250); 
            $route = $train_routes[$train] ?? '';
            $url = $train_urls[$train] ?? '';
            $route_html = $url ? "<a href=\"{$url}\">{$route}</a>" : $route;
            
            $delay = getDelayMinutes($train, $location, $time_str);
            $delay_str = formatDelay($delay);
            
            $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
            $report = "{$arrow} <b>{$train}</b> ({$route_html}) | <b>ст. {$location}</b> | {$status_text} | 🕒 <b>{$time_str}</b>{$delay_str}\n💬 <i>" . htmlspecialchars($comment) . "</i>";
            
            $initials = getAdminInitials($user_id);
            $radar_title = $initials ? "RADAR UZ ({$initials})" : "RADAR UZ";
            
            $preview_msg = "👁 <b>Крок 5/5: ПЕРЕГЛЯД</b>\n\n<b>{$radar_title} інформує:</b>\n" . $report;
            
            $kb = [
                [['text' => '🚀 Відправити в групу', 'callback_data' => "cs|{$train}|{$location}|{$status_idx}|c|{$time_str}"]],
                [['text' => '🔙 Назад до налаштувань', 'callback_data' => "st|{$train}|{$location}|{$status_idx}|{$time_str}"]]
            ];
            
            sendTelegramRequest('sendMessage', [
                'chat_id' => $chat_id, 
                'text' => $preview_msg, 
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => $kb],
                'link_preview_options' => ['is_disabled' => true]
            ]);
        }
        http_response_code(200); exit;
    }

    if (strpos($text, '/start') === 0) {
        $keyboard = [
            [ ['text' => '🔺 779', 'callback_data' => 't|779'], ['text' => '🔺 775', 'callback_data' => 't|775'], ['text' => '🔺 143', 'callback_data' => 't|143'] ],
            [ ['text' => '🔻 66',  'callback_data' => 't|66'],  ['text' => '🔻 776', 'callback_data' => 't|776'], ['text' => '🔻 144', 'callback_data' => 't|144'] ]
        ];
        $msg = "Крок 1/5\n<b>Панель репортера</b>\n\nОберіть номер потяга:";
        sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $keyboard]]);
    }
    
    if (strpos($text, '/logs') === 0) {
        if ($user_id != 1071080055) {
            sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "🚫 <b>Доступ заблоковано.</b>", 'parse_mode' => 'HTML']);
            exit;
        }
        $stmt = $pdo->query("SELECT train_id, location, status, author_name, comment, DATE_FORMAT(created_at, '%d.%m %H:%i') as dt FROM reports_history ORDER BY id DESC LIMIT 15");
        $logs = $stmt->fetchAll();
        
        if (empty($logs)) {
            $msg = "📋 <b>Історія репортів порожня.</b>";
        } else {
            $msg = "📋 <b>Останні 15 дій:</b>\n\n";
            foreach ($logs as $log) {
                $msg .= "🕒 <code>{$log['dt']}</code> | 🚄 <b>{$log['train_id']}</b> | 👤 <b>{$log['author_name']}</b>\n📍 ст. {$log['location']} — {$log['status']}\n";
                if (!empty($log['comment'])) $msg .= "💬 <i>" . htmlspecialchars($log['comment']) . "</i>\n";
                $msg .= "─────────────────\n";
            }
        }
        sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]]);
        http_response_code(200); exit;
    }
    
    http_response_code(200); exit;
}

// ==========================================
// ОБРОБКА КНОПОК
// ==========================================
if (isset($update['callback_query'])) {
    $cq = $update['callback_query'];
    $chat_id = $cq['message']['chat']['id']; 
    $chat_type = $cq['message']['chat']['type'] ?? 'private';
    $msg_id = $cq['message']['message_id'];
    $user_id = $cq['from']['id']; 
    $data = $cq['data']; 
    $parts = explode('|', $data); 
    $action = $parts[0];

    if ($chat_type !== 'private') {
        sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => 'Ця панель працює тільки в особистих повідомленнях бота.', 'show_alert' => true]);
        http_response_code(200); exit;
    }

    if (!isAuthorized($user_id)) {
        sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => '🚫 Доступ лише для репортерів. Якщо бажаєте ним стати звертайтесь до @sarmakey', 'show_alert' => true]);
        http_response_code(200); exit;
    }

    $author_name = $ALLOWED_ADMINS[$user_id] ?? 'Unknown';

    if ($action === 'b') {
        $step = $parts[1];
        if ($step == '1') {
            $keyboard = [
                [ ['text' => '🔺 779', 'callback_data' => 't|779'], ['text' => '🔺 775', 'callback_data' => 't|775'], ['text' => '🔺 143', 'callback_data' => 't|143'] ],
                [ ['text' => '🔻 66',  'callback_data' => 't|66'],  ['text' => '🔻 776', 'callback_data' => 't|776'], ['text' => '🔻 144', 'callback_data' => 't|144'] ]
            ];
            $msg = "Крок 1/5\n<b>Панель репортера</b>\n\nОберіть номер потяга:";
            sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $keyboard]]);
        } elseif ($step == '2') {
            $train = $parts[2]; $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
            $route = $train_routes[$train] ?? '';
            $display = in_array($train, $trains_sumy) ? array_reverse($stations, true) : $stations;
            
            $schedule_text = getScheduleContext($train);
            
            $kb = []; $row = [];
            foreach ($display as $idx => $s) {
                $row[] = ['text' => $s, 'callback_data' => "st|{$train}|{$s}|"]; 
                if (count($row) == 2) { $kb[] = $row; $row = []; }
            }
            if (!empty($row)) $kb[] = $row;
            $kb[] = [['text' => '✍️ Інша станція', 'callback_data' => "sm|{$train}"]];
            $kb[] = [['text' => '🔙 Назад', 'callback_data' => "b|1"]];
            $msg = "Крок 2/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n{$schedule_text}\nДе він зараз?";
            sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
        } elseif ($step == '3') {
            $train = $parts[2]; $loc = $parts[3];
            $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
            $route = $train_routes[$train] ?? '';
            $kb = []; $row = [];
            foreach ($statuses as $idx => $s) {
                $row[] = ['text' => $s, 'callback_data' => "st|{$train}|{$loc}|{$idx}"];
                if (count($row) == 2) { $kb[] = $row; $row = []; }
            }
            if (!empty($row)) $kb[] = $row;
            $kb[] = [['text' => '🔙 Назад', 'callback_data' => "b|2|{$train}"]];
            $msg = "Крок 3/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n📍 Станція: <b>ст. {$loc}</b>\n\nОберіть статус:";
            sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
        }
        http_response_code(200); exit;
    }

    if ($action === 't') {
        $train = $parts[1]; $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
        $route = $train_routes[$train] ?? '';
        $display = in_array($train, $trains_sumy) ? array_reverse($stations, true) : $stations;
        
        $schedule_text = getScheduleContext($train);
        
        $kb = []; $row = [];
        foreach ($display as $idx => $s) {
            $row[] = ['text' => $s, 'callback_data' => "st|{$train}|{$s}|"]; 
            if (count($row) == 2) { $kb[] = $row; $row = []; }
        }
        if (!empty($row)) $kb[] = $row;
        $kb[] = [['text' => '✍️ Інша станція', 'callback_data' => "sm|{$train}"]];
        $kb[] = [['text' => '🔙 Назад', 'callback_data' => "b|1"]];
        $msg = "Крок 2/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n{$schedule_text}\nДе він зараз?";
        sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
    }
    elseif ($action === 'sm') {
        sendTelegramRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
        sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ Введіть станцію для потяга {$parts[1]}:", 'parse_mode' => 'HTML', 'reply_markup' => ['force_reply' => true]]);
    }
    elseif ($action === 'st') {
        $train = $parts[1]; $loc = $parts[2];
        $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
        $route = $train_routes[$train] ?? '';
        
        if (isset($parts[3]) && $parts[3] !== '') {
            $status_idx = $parts[3];
            $status_name = $statuses[$status_idx];
            
            // Заморожуємо час. Якщо його ще немає в параметрах — беремо поточний.
            $time_str = (isset($parts[4]) && $parts[4] !== '') ? $parts[4] : date('H:i');
            
            $ts_current = strtotime($time_str);
            $time_minus = date('H:i', $ts_current - 300);
            $time_plus = date('H:i', $ts_current + 300);
            $time_now = date('H:i'); // Час прямо зараз
            
            $kb = [
                [
                    ['text' => '➖ 5 хв', 'callback_data' => "st|{$train}|{$loc}|{$status_idx}|{$time_minus}"],
                    ['text' => '🔄 Скинути на зараз', 'callback_data' => "st|{$train}|{$loc}|{$status_idx}|{$time_now}"],
                    ['text' => '➕ 5 хв',  'callback_data' => "st|{$train}|{$loc}|{$status_idx}|{$time_plus}"]
                ],
                [['text' => '💬 Свій коментар', 'callback_data' => "ca|{$train}|{$loc}|{$status_idx}|{$time_str}"]],
                [
                    ['text' => '🚑 Евакуація', 'callback_data' => "pr|{$train}|{$loc}|{$status_idx}|e|{$time_str}"],
                    ['text' => '🛠 Поломка', 'callback_data' => "pr|{$train}|{$loc}|{$status_idx}|b|{$time_str}"]
                ],
                [['text' => '👁 Підготувати репорт', 'callback_data' => "pr|{$train}|{$loc}|{$status_idx}|none|{$time_str}"]],
                [['text' => '🔙 Назад', 'callback_data' => "b|3|{$train}|{$loc}"]]
            ];
            
            $msg = "Крок 4/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n📍 Станція: <b>ст. {$loc}</b>\nСтатус: <b>{$status_name}</b>\n\n🕒 Час фіксації: <b>{$time_str}</b>\n\nНалаштуйте час кнопками нижче або підготуйте:";
            sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
        } else {
            $kb = []; $row = [];
            foreach ($statuses as $idx => $s) {
                $row[] = ['text' => $s, 'callback_data' => "st|{$train}|{$loc}|{$idx}"];
                if (count($row) == 2) { $kb[] = $row; $row = []; }
            }
            if (!empty($row)) $kb[] = $row;
            $kb[] = [['text' => '🔙 Назад', 'callback_data' => "b|2|{$train}"]];
            $msg = "Крок 3/5\nПотяг: {$arrow} <b>{$train}</b> ({$route})\n📍 Станція: <b>ст. {$loc}</b>\n\nОберіть статус:";
            sendTelegramRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
        }
    }
    elseif ($action === 'ca') {
        $time_str = isset($parts[4]) ? $parts[4] : date('H:i');
        sendTelegramRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
        sendTelegramRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ Коментар для: {$parts[1]} | {$parts[2]} | {$parts[3]} | {$time_str}", 'parse_mode' => 'HTML', 'reply_markup' => ['force_reply' => true]]);
    }
    elseif ($action === 'pr') { 
        $train = $parts[1]; $location = $parts[2]; $status_idx = (int)$parts[3];
        $comment_type = $parts[4]; 
        $time_str = isset($parts[5]) ? $parts[5] : date('H:i');
        
        $status_text = $statuses[$status_idx];
        $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
        $route = $train_routes[$train] ?? '';
        $url = $train_urls[$train] ?? '';
        $route_html = $url ? "<a href=\"{$url}\">{$route}</a>" : $route;
        
        $delay = getDelayMinutes($train, $location, $time_str);
        $delay_str = formatDelay($delay);
        
        $comment = '';
        if ($comment_type === 'e') $comment = 'Евакуація';
        elseif ($comment_type === 'b') $comment = 'Поломка';
        
        $report = "{$arrow} <b>{$train}</b> ({$route_html}) | <b>ст. {$location}</b> | {$status_text} | 🕒 <b>{$time_str}</b>{$delay_str}";
        if ($comment !== '') $report .= "\n💬 <i>" . htmlspecialchars($comment) . "</i>";
        
        $initials = getAdminInitials($user_id);
        $radar_title = $initials ? "RADAR UZ ({$initials})" : "RADAR UZ";
        
        $preview_msg = "👁 <b>Крок 5/5: ПЕРЕГЛЯД</b>\n\n<b>{$radar_title} інформує:</b>\n" . $report;
        
        $kb = [
            [['text' => '🚀 Відправити в групу', 'callback_data' => "cs|{$train}|{$location}|{$status_idx}|{$comment_type}|{$time_str}"]],
            [['text' => '🔙 Назад до налаштувань', 'callback_data' => "st|{$train}|{$location}|{$status_idx}|{$time_str}"]]
        ];
        
        sendTelegramRequest('editMessageText', [
            'chat_id' => $chat_id, 
            'message_id' => $msg_id, 
            'text' => $preview_msg, 
            'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => $kb],
            'link_preview_options' => ['is_disabled' => true]
        ]);
    }
    elseif ($action === 'cs') { 
        $train = $parts[1]; $location = $parts[2]; $status_idx = (int)$parts[3];
        $comment_type = $parts[4]; 
        $time_str = isset($parts[5]) ? $parts[5] : date('H:i');
        
        $status = $statuses[$status_idx];
        $arrow = in_array($train, $trains_kyiv) ? '🔺' : '🔻';
        $route = $train_routes[$train] ?? '';
        $url = $train_urls[$train] ?? '';
        $route_html = $url ? "<a href=\"{$url}\">{$route}</a>" : $route;
        
        $comment = '';
        if ($comment_type === 'e') $comment = 'Евакуація';
        elseif ($comment_type === 'b') $comment = 'Поломка';
        elseif ($comment_type === 'c') {
            $raw_text = $cq['message']['text'];
            $c_parts = explode('💬 ', $raw_text, 2);
            if (count($c_parts) > 1) {
                $comment = trim($c_parts[1]);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO train_status (train_id, location, status, author_id, comment) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE location=VALUES(location), status=VALUES(status), author_id=VALUES(author_id), comment=VALUES(comment)");
        $stmt->execute([$train, $location, $status, $user_id, $comment]);
        
        $log_stmt = $pdo->prepare("INSERT INTO reports_history (train_id, location, status, author_id, author_name, comment) VALUES (?, ?, ?, ?, ?, ?)");
        $log_stmt->execute([$train, $location, $status, $user_id, $author_name, $comment]);
        
        $delay = getDelayMinutes($train, $location, $time_str);
        $delay_str = formatDelay($delay);
        
        $report = "{$arrow} <b>{$train}</b> ({$route_html}) | <b>ст. {$location}</b> | {$status} | 🕒 <b>{$time_str}</b>{$delay_str}";
        if ($comment !== '') {
            $report .= "\n💬 <i>" . htmlspecialchars($comment) . "</i>";
        }
        
        $initials = getAdminInitials($user_id);
        $radar_title = $initials ? "RADAR UZ ({$initials})" : "RADAR UZ";
        $final_msg = "<b>{$radar_title} інформує:</b>\n" . $report;
        
        sendTelegramRequest('sendMessage', [
            'chat_id' => GROUP_CHAT_ID, 
            'text' => $final_msg, 
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true]
        ]);
        
        sendTelegramRequest('editMessageText', [
            'chat_id' => $chat_id, 
            'message_id' => $msg_id, 
            'text' => "✅ <b>Успішно відправлено в групу!</b>\n\n" . $final_msg, 
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true]
        ]);
    }
    http_response_code(200); exit;
}
?>
<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['image']) || empty($input['image'])) {
    echo json_encode(['success' => false, 'error' => 'No image provided']);
    exit;
}

$base64image = $input['image'];
$comment = $input['comment'] ?? '';

if (preg_match('/^data:image\/(\w+);base64,/', $base64image, $type)) {
    $base64image = substr($base64image, strpos($base64image, ',') + 1);
    $type = strtolower($type[1]);

    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid image type']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid image data']);
    exit;
}

$imageData = base64_decode($base64image);
if ($imageData === false) {
    echo json_encode(['success' => false, 'error' => 'Base64 decode failed']);
    exit;
}

$allowedChats = ['-1003941419523', '-1004459944074'];
$targetChatId = $input['targetChatId'] ?? '-1003941419523';

if (!in_array($targetChatId, $allowedChats)) {
    echo json_encode(['success' => false, 'error' => 'Invalid target chat ID']);
    exit;
}

$tempFile = sys_get_temp_dir() . '/screenshot_' . uniqid() . '.' . $type;
file_put_contents($tempFile, $imageData);

$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);

$postFields = [
    'chat_id' => $targetChatId,
    'photo' => new CURLFile($tempFile),
    'caption' => $comment
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

@unlink($tempFile);

$response = json_decode($result, true);

if ($httpCode === 200 && $response && $response['ok']) {
    echo json_encode(['success' => true]);
} else {
    $errorMsg = $response['description'] ?? 'Telegram API Error';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
}
?>

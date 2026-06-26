<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    $file = __DIR__ . '/../group_messages.json';
    $messages = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($messages)) $messages = [];
    
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

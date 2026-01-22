<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Файл с кодами
$codesFile = 'codes.json';

// Читаем существующие коды
$codes = [];
if (file_exists($codesFile)) {
    $codes = json_decode(file_get_contents($codesFile), true);
}

// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$code = isset($input['code']) ? trim($input['code']) : '';

// Проверяем код
if (empty($code)) {
    echo json_encode([
        'success' => false,
        'message' => 'Код не указан'
    ]);
    exit;
}

// Проверка формата XXX-XXX
if (!preg_match('/^\d{3}-\d{3}$/', $code)) {
    echo json_encode([
        'success' => false,
        'message' => 'Неверный формат кода'
    ]);
    exit;
}

// Проверяем существование кода
if (!isset($codes[$code])) {
    echo json_encode([
        'success' => false,
        'message' => 'Код не найден'
    ]);
    exit;
}

$codeData = $codes[$code];
$timestamp = $codeData['timestamp'];

// Проверяем срок действия (10 минут)
if (time() - $timestamp > 600) { // 10 минут = 600 секунд
    // Удаляем просроченный код
    unset($codes[$code]);
    file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => false,
        'message' => 'Срок действия кода истёк'
    ]);
    exit;
}

// Проверяем, использовался ли уже код
if (isset($codeData['used']) && $codeData['used'] === true) {
    echo json_encode([
        'success' => false,
        'message' => 'Код уже использован'
    ]);
    exit;
}

// Помечаем код как использованный
$codes[$code]['used'] = true;
$codes[$code]['used_at'] = time();
file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));

// Возвращаем успешный ответ
echo json_encode([
    'success' => true,
    'message' => 'Код подтверждён',
    'user_id' => $codeData['user_id'],
    'code' => $code,
    'timestamp' => $timestamp
]);
?>

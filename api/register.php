<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Простая валидация
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Неверный email']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Пароль слишком короткий']);
    exit;
}

// Генерируем ID пользователя
$userId = uniqid('user_', true);

// Сохраняем пользователя (в реальности - в базу данных)
$userData = [
    'id' => $userId,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'created_at' => date('Y-m-d H:i:s'),
    'tariff' => 'basic'
];

// Сохраняем в файл (временно)
$users = json_decode(file_get_contents('users.json'), true) ?? [];
$users[$userId] = $userData;
file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => 'Аккаунт создан',
    'user' => [
        'id' => $userId,
        'email' => $email
    ]
]);
?>

<?php
// api/yookassa-create.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Конфигурация ЮKassa
define('YOOKASSA_SHOP_ID', '1256818');
define('YOOKASSA_SECRET_KEY', 'live_cQ08CM2pew_mp5eFgigDtmvoO-guM_QHyU1FvyuoMVg');

// Получаем данные из запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Неверные данные запроса']);
    exit;
}

// Формируем запрос для ЮKassa
$paymentData = [
    'amount' => $data['amount'],
    'confirmation' => $data['confirmation'],
    'capture' => true,
    'description' => $data['description'],
    'metadata' => $data['metadata']
];

// Добавляем код налога (НДС 20%)
$paymentData['receipt'] = [
    'customer' => [
        'email' => $data['metadata']['user_email']
    ],
    'items' => [
        [
            'description' => $data['description'],
            'quantity' => '1.00',
            'amount' => $data['amount'],
            'vat_code' => 1, // НДС 20%
            'payment_mode' => 'full_payment',
            'payment_subject' => 'service'
        ]
    ]
];

// Создаем платеж в ЮKassa
$ch = curl_init('https://api.yookassa.ru/v3/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Idempotence-Key: ' . uniqid('', true),
    'Authorization: Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['id'])) {
    // Сохраняем информацию о платеже
    $paymentInfo = [
        'payment_id' => $result['id'],
        'user_id' => $data['metadata']['user_id'],
        'user_email' => $data['metadata']['user_email'],
        'tariff' => $data['metadata']['tariff'],
        'tariff_type' => $data['metadata']['tariff_type'],
        'site_url' => $data['metadata']['site_url'],
        'yandex_id' => $data['metadata']['yandex_id'],
        'amount' => $data['amount']['value'],
        'status' => $result['status'],
        'created_at' => date('Y-m-d H:i:s'),
        'confirmation_url' => $result['confirmation']['confirmation_url']
    ];
    
    // Сохраняем в файл (в реальном проекте - в базу данных)
    $payments = json_decode(file_get_contents('payments.json'), true) ?? [];
    $payments[$result['id']] = $paymentInfo;
    file_put_contents('payments.json', json_encode($payments, JSON_PRETTY_PRINT));
    
    // Сохраняем информацию о пользователе
    $users = json_decode(file_get_contents('users.json'), true) ?? [];
    if (!isset($users[$data['metadata']['user_email']])) {
        $users[$data['metadata']['user_email']] = [
            'email' => $data['metadata']['user_email'],
            'user_id' => $data['metadata']['user_id'],
            'tariff' => $data['metadata']['tariff_type'],
            'site_url' => $data['metadata']['site_url'],
            'yandex_id' => $data['metadata']['yandex_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));
    }
    
    // Отправляем ответ
    echo json_encode([
        'success' => true,
        'payment_id' => $result['id'],
        'confirmation_url' => $result['confirmation']['confirmation_url'],
        'message' => 'Платеж создан успешно'
    ]);
    
} else {
    // Ошибка при создании платежа
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при создании платежа: ' . ($result['description'] ?? 'Неизвестная ошибка')
    ]);
}
?>

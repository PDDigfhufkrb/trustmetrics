<?php
// api/yookassa-create.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Разрешаем OPTIONS запросы для CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Конфигурация ЮKassa
define('YOOKASSA_SHOP_ID', '1256818');
define('YOOKASSA_SECRET_KEY', 'live_cQ08CM2pew_mp5eFgigDtmvoO-guM_QHyU1FvyuoMVg');

// Функция для логирования ошибок
function logError($message) {
    $log = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents('error_log.txt', $log, FILE_APPEND);
}

try {
    // Получаем данные из запроса
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Пустой запрос');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Неверный JSON: ' . json_last_error_msg());
    }

    // Проверяем обязательные поля
    $requiredFields = ['amount', 'confirmation', 'description', 'metadata'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Отсутствует обязательное поле: $field");
        }
    }

    // Формируем запрос для ЮKassa
    $paymentData = [
        'amount' => $data['amount'],
        'confirmation' => $data['confirmation'],
        'capture' => true,
        'description' => $data['description'],
        'metadata' => $data['metadata']
    ];

    // Добавляем чек для НДС
    if (isset($data['metadata']['user_email'])) {
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
    }

    // Создаем платеж в ЮKassa
    $ch = curl_init('https://api.yookassa.ru/v3/payments');
    
    $headers = [
        'Content-Type: application/json',
        'Idempotence-Key: ' . uniqid('', true),
        'Authorization: Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY)
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Ошибка CURL: ' . $error);
    }

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

        // Создаем директорию для данных если её нет
        if (!file_exists('data')) {
            mkdir('data', 0755, true);
        }

        // Сохраняем в файл (в реальном проекте - в базу данных)
        $paymentsFile = 'data/payments.json';
        $payments = file_exists($paymentsFile) ? json_decode(file_get_contents($paymentsFile), true) : [];
        $payments[$result['id']] = $paymentInfo;
        file_put_contents($paymentsFile, json_encode($payments, JSON_PRETTY_UNESCAPED_UNICODE));

        // Сохраняем информацию о пользователе
        $usersFile = 'data/users.json';
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        $userEmail = $data['metadata']['user_email'];
        
        if (!isset($users[$userEmail])) {
            $users[$userEmail] = [
                'email' => $userEmail,
                'user_id' => $data['metadata']['user_id'],
                'tariff' => $data['metadata']['tariff_type'],
                'site_url' => $data['metadata']['site_url'],
                'yandex_id' => $data['metadata']['yandex_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'last_payment' => date('Y-m-d H:i:s')
            ];
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_UNESCAPED_UNICODE));
        }

        // Отправляем успешный ответ
        echo json_encode([
            'success' => true,
            'payment_id' => $result['id'],
            'confirmation_url' => $result['confirmation']['confirmation_url'],
            'message' => 'Платеж создан успешно'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Ошибка при создании платежа
        $errorMsg = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
        throw new Exception('Ошибка ЮKassa: ' . $errorMsg . ' (HTTP ' . $httpCode . ')');
    }

} catch (Exception $e) {
    // Логируем ошибку
    logError($e->getMessage());
    
    // Отправляем ошибку
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

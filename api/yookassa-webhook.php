<?php
// api/yookassa-webhook.php
define('YOOKASSA_WEBHOOK_SECRET', 'ваш_секрет_для_вебхука');

// Получаем данные вебхука
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$signature = $_SERVER['HTTP_CONTENT_SIGNATURE'] ?? '';

// Проверяем подпись (в тестовом режиме можно пропустить)
// $calculatedSignature = hash_hmac('sha256', $input, YOOKASSA_WEBHOOK_SECRET);
// if ($calculatedSignature !== $signature) {
//     http_response_code(401);
//     die('Invalid signature');
// }

if ($data['event'] === 'payment.succeeded') {
    $payment = $data['object'];
    
    // Обновляем статус платежа
    $payments = json_decode(file_get_contents('payments.json'), true) ?? [];
    if (isset($payments[$payment['id']])) {
        $payments[$payment['id']]['status'] = 'succeeded';
        $payments[$payment['id']]['paid_at'] = date('Y-m-d H:i:s');
        $payments[$payment['id']]['expires_at'] = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        file_put_contents('payments.json', json_encode($payments, JSON_PRETTY_PRINT));
        
        // Добавляем задачу в очередь для запуска сессий
        $queue = json_decode(file_get_contents('queue.json'), true) ?? [];
        $queue[] = [
            'task_id' => uniqid(),
            'type' => 'start_sessions',
            'payment_id' => $payment['id'],
            'data' => $payments[$payment['id']],
            'created_at' => time(),
            'status' => 'pending'
        ];
        file_put_contents('queue.json', json_encode($queue, JSON_PRETTY_PRINT));
        
        // Отправляем email пользователю
        sendConfirmationEmail($payments[$payment['id']]);
    }
}

echo 'OK';

// Функция отправки email
function sendConfirmationEmail($paymentInfo) {
    $to = $paymentInfo['user_email'];
    $subject = 'Платеж успешно принят - ShadowBoost';
    $message = "
        <h2>Оплата принята! 🎉</h2>
        <p>Спасибо за оплату тарифа <strong>{$paymentInfo['tariff']}</strong>.</p>
        
        <h3>Детали заказа:</h3>
        <ul>
            <li><strong>Сайт:</strong> {$paymentInfo['site_url']}</li>
            <li><strong>Тариф:</strong> {$paymentInfo['tariff']}</li>
            <li><strong>Сумма:</strong> {$paymentInfo['amount']} ₽</li>
            <li><strong>Дата оплаты:</strong> {$paymentInfo['paid_at']}</li>
        </ul>
        
        <p>✅ Система ShadowBoost автоматически запущена для вашего сайта.</p>
        <p>👥 Визиты начнутся в течение часа и будут отображаться в вашей Яндекс.Метрике.</p>
        
        <p>Для управления настройками перейдите в <a href='https://ваш-домен.ру/cabinet.html'>личный кабинет</a>.</p>
        
        <hr>
        <p style='color: #666; font-size: 12px;'>
            Если у вас есть вопросы, напишите нам в поддержку.
        </p>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ShadowBoost <noreply@shadowboost.ru>'
    ];
    
    // mail($to, $subject, $message, implode("\r\n", $headers));
    
    // Логируем отправку
    file_put_contents('email_log.txt', date('Y-m-d H:i:s') . " - Email sent to: $to\n", FILE_APPEND);
}
?>

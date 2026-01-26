<?php
// api/test-payment.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Всегда возвращаем успешный ответ для теста
    echo json_encode([
        'success' => true,
        'payment_id' => 'test_' . uniqid(),
        'confirmation_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=test_' . uniqid(),
        'status' => 'pending',
        'amount' => $input['amount'] ?? '2000.00'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

<?php
// api/yookassa.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'vendor/autoload.php'; // Установите composer require yoomoney/yookassa-sdk-php

use YooKassa\Client;

class YooKassaPayment {
    private $client;
    private $shopId = '1256818';
    private $secretKey = 'live_cQ08CM2pew_mp5eFgigDtmvoO-guM_QHyU1FvyuoMVg';
    
    public function __construct() {
        $this->client = new Client();
        $this->client->setAuth($this->shopId, $this->secretKey);
    }
    
    // Создание платежа
    public function createPayment($data) {
        try {
            $idempotenceKey = uniqid('', true);
            
            $payment = $this->client->createPayment(
                [
                    'amount' => [
                        'value' => $data['amount'],
                        'currency' => 'RUB',
                    ],
                    'confirmation' => [
                        'type' => 'redirect',
                        'return_url' => $data['return_url'],
                    ],
                    'capture' => true,
                    'description' => $data['description'],
                    'metadata' => [
                        'user_id' => $data['user_id'],
                        'email' => $data['email'],
                        'site_url' => $data['site_url'],
                        'yandex_id' => $data['yandex_id'],
                        'tariff' => $data['tariff'],
                        'visits' => $data['visits']
                    ],
                    'receipt' => [
                        'customer' => [
                            'email' => $data['email']
                        ],
                        'items' => [
                            [
                                'description' => 'Тариф "' . $data['tariff_name'] . '" (' . $data['visits'] . ' визитов)',
                                'quantity' => '1',
                                'amount' => [
                                    'value' => $data['amount'],
                                    'currency' => 'RUB'
                                ],
                                'vat_code' => '1',
                                'payment_mode' => 'full_payment',
                                'payment_subject' => 'service'
                            ]
                        ]
                    ]
                ],
                $idempotenceKey
            );
            
            return [
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'confirmation_url' => $payment->confirmation->confirmation_url,
                'amount' => $payment->amount->value
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Проверка статуса платежа
    public function checkPayment($paymentId) {
        try {
            $payment = $this->client->getPaymentInfo($paymentId);
            
            return [
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'paid' => $payment->paid,
                'amount' => $payment->amount->value,
                'metadata' => $payment->metadata
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Обработка вебхука
    public function handleWebhook($data) {
        if (!isset($data['event']) || !isset($data['object'])) {
            return ['success' => false, 'error' => 'Invalid webhook data'];
        }
        
        $payment = $data['object'];
        
        // Сохраняем информацию о платеже
        $this->savePayment($payment);
        
        // Если платеж успешен, активируем сайт
        if ($data['event'] === 'payment.succeeded') {
            $this->activateSite($payment['metadata']);
        }
        
        return ['success' => true];
    }
    
    private function savePayment($payment) {
        $paymentsFile = '../payments.json';
        $payments = file_exists($paymentsFile) ? json_decode(file_get_contents($paymentsFile), true) : [];
        
        $payments[$payment['id']] = [
            'id' => $payment['id'],
            'status' => $payment['status'],
            'amount' => $payment['amount']['value'],
            'currency' => $payment['amount']['currency'],
            'user_id' => $payment['metadata']['user_id'] ?? '',
            'email' => $payment['metadata']['email'] ?? '',
            'site_url' => $payment['metadata']['site_url'] ?? '',
            'tariff' => $payment['metadata']['tariff'] ?? '',
            'paid_at' => date('Y-m-d H:i:s'),
            'data' => $payment
        ];
        
        file_put_contents($paymentsFile, json_encode($payments, JSON_PRETTY_PRINT));
    }
    
    private function activateSite($metadata) {
        $sitesFile = '../sites.json';
        $sites = file_exists($sitesFile) ? json_decode(file_get_contents($sitesFile), true) : [];
        
        $siteId = md5($metadata['user_id'] . $metadata['site_url']);
        
        $sites[$siteId] = [
            'id' => $siteId,
            'user_id' => $metadata['user_id'],
            'email' => $metadata['email'],
            'site_url' => $metadata['site_url'],
            'yandex_id' => $metadata['yandex_id'],
            'tariff' => $metadata['tariff'],
            'visits_monthly' => $metadata['visits'],
            'status' => 'active',
            'activated_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'visits_today' => 0,
            'visits_total' => 0
        ];
        
        file_put_contents($sitesFile, json_encode($sites, JSON_PRETTY_PRINT));
        
        // Запускаем воркер для этого сайта
        $this->startWorker($siteId, $metadata);
    }
    
    private function startWorker($siteId, $metadata) {
        // Добавляем сайт в очередь для воркера
        $queueFile = '../queue.json';
        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
        
        $queue[] = [
            'site_id' => $siteId,
            'action' => 'start_sessions',
            'data' => $metadata,
            'created_at' => time(),
            'status' => 'pending'
        ];
        
        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
        
        // Здесь можно запустить Python воркер
        // exec('python3 worker.py > /dev/null 2>&1 &');
    }
}

// Обработка запросов
$yookassa = new YooKassaPayment();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            $result = $yookassa->createPayment($input);
            echo json_encode($result);
            break;
            
        case 'check':
            $result = $yookassa->checkPayment($input['payment_id']);
            echo json_encode($result);
            break;
            
        case 'webhook':
            $result = $yookassa->handleWebhook($input);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

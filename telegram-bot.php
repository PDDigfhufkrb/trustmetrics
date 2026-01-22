<?php
// ========== НАСТРОЙКИ ==========
$BOT_TOKEN = '7588127144:AAHkj9Qx3Tq5apzfWwQFjYLP8UjFpCOZklU';
$BOT_USERNAME = 'shadowboost_ru_bot';

// ========== ОБРАБОТКА ВХОДЯЩИХ СООБЩЕНИЙ ==========
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Если пришло сообщение
if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $text = $message["text"] ?? '';
    $user_name = $message["from"]["first_name"] ?? 'Пользователь';
    
    // Команда /start
    if ($text === '/start') {
        // Генерируем код
        $code = rand(100, 999) . '-' . rand(100, 999);
        
        // Сохраняем код в файл
        $codes_file = 'codes.json';
        $codes = [];
        
        if (file_exists($codes_file)) {
            $codes = json_decode(file_get_contents($codes_file), true);
        }
        
        $codes[$code] = [
            'chat_id' => $chat_id,
            'user_name' => $user_name,
            'timestamp' => time(),
            'used' => false
        ];
        
        file_put_contents($codes_file, json_encode($codes, JSON_PRETTY_PRINT));
        
        // Отправляем ответ
        $response = "🔐 <b>Код авторизации для ShadowBoost</b>\n\n";
        $response .= "Привет, $user_name!\n\n";
        $response .= "Ваш код для входа в личный кабинет:\n";
        $response .= "<b><code>$code</code></b>\n\n";
        $response .= "<b>Что делать дальше:</b>\n";
        $response .= "1. Вернитесь на сайт shadowboost.ru\n";
        $response .= "2. Введите этот код в поле ввода\n";
        $response .= "3. Нажмите \"Войти\"\n\n";
        $response .= "Код действителен 10 минут.";
        
        sendMessage($chat_id, $response);
    }
    
    // Команда /help
    else if ($text === '/help') {
        $response = "❓ <b>Помощь по ShadowBoost</b>\n\n";
        $response .= "<b>Как войти:</b>\n";
        $response .= "1. Напишите /start для получения кода\n";
        $response .= "2. Получите код вида 123-456\n";
        $response .= "3. Введите код на сайте shadowboost.ru\n\n";
        $response .= "<b>Поддержка:</b>\n";
        $response .= "По вопросам обращайтесь к администратору.";
        
        sendMessage($chat_id, $response);
    }
    
    // Любое другое сообщение
    else if (!empty($text)) {
        $response = "👋 Для получения кода авторизации напишите:\n\n";
        $response .= "<code>/start</code>\n\n";
        $response .= "Или используйте <code>/help</code> для помощи.";
        
        sendMessage($chat_id, $response);
    }
}

// ========== ФУНКЦИЯ ОТПРАВКИ СООБЩЕНИЯ ==========
function sendMessage($chat_id, $text) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// ========== УСТАНОВКА ВЕБХУКА ==========
// Чтобы установить вебхук, открой в браузере:
// https://shadowboost.ru/telegram-bot.php?setup=1
if (isset($_GET['setup'])) {
    $webhook_url = 'https://shadowboost.ru/telegram-bot.php';
    $set_webhook = "https://api.telegram.org/bot$BOT_TOKEN/setWebhook?url=" . urlencode($webhook_url);
    
    $result = file_get_contents($set_webhook);
    
    echo "<h1>Настройка Telegram бота</h1>";
    echo "<p>Вебхук установлен: $webhook_url</p>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
    echo "<p>Бот готов к работе!</p>";
}

// ========== ПРОВЕРКА РАБОТЫ ==========
if (isset($_GET['test'])) {
    echo "<h1>Telegram Bot Test</h1>";
    echo "<p>Бот: @$BOT_USERNAME</p>";
    echo "<p>Токен: ..." . substr($BOT_TOKEN, -8) . "</p>";
    echo "<p>Сервер работает!</p>";
    
    // Проверяем codes.json
    if (file_exists('codes.json')) {
        $codes = json_decode(file_get_contents('codes.json'), true);
        echo "<p>Кодов в базе: " . count($codes) . "</p>";
    }
}
?>

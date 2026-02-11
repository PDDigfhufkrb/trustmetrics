<?php
// ===========================================
// ShadowBoost - ПРИЁМЩИК ЗАДАЧ
// Максимально тупая версия. Работает везде.
// ===========================================

// Разрешаем запросы с любых страниц сайта
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain; charset=utf-8');

// Получаем данные
$site = $_POST['site'] ?? '';
$tariff = $_POST['tariff'] ?? '';
$email = $_POST['email'] ?? '';

// Проверяем, что данные есть
if (empty($site) || empty($tariff)) {
    die('ERROR: Нет данных о сайте или тарифе');
}

// Очищаем от мусора
$site = trim($site);
$tariff = trim($tariff);
$email = trim($email);

// Формируем строку для записи
// ФОРМАТ: ДАТА | EMAIL | САЙТ | ТАРИФ
$line = date('Y-m-d H:i:s') . " | " . $email . " | " . $site . " | " . $tariff . "\n";

// Дописываем в файл tasks.txt (создастся автоматически)
file_put_contents('tasks.txt', $line, FILE_APPEND | LOCK_EX);

// Всё, задача принята
echo 'OK';

// Дополнительно: отправляем в Телеграм, если хочешь
// Раскомментируй строки ниже и вставь свой токен и chat_id
/*
$token = 'ТВОЙ_ТОКЕН_БОТА';
$chat_id = 'ТВОЙ_CHAT_ID';
$message = "🔥 НОВАЯ ЗАДАЧА!\nСайт: $site\nТариф: $tariff\nEmail: $email";
file_get_contents("https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($message));
*/
?>

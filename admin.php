<?php
// ===========================================
// ShadowBoost - АДМИНКА
// Максимально тупая. Просто читает tasks.txt
// ===========================================

// Защита паролем (HTTP Basic Auth)
$valid_passwords = [
    "admin" => "shadow123"  // логин: admin, пароль: shadow123
];

$valid_users = array_keys($valid_passwords);
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if (!in_array($user, $valid_users) || $pass != $valid_passwords[$user]) {
    header('WWW-Authenticate: Basic realm="ShadowBoost Admin"');
    header('HTTP/1.0 401 Unauthorized');
    die('🔐 Доступ только для администратора');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShadowBoost — Админ-панель</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0a0a0f;
            color: white;
            font-family: 'Inter', sans-serif;
            padding: 30px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        h1 {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(30, 30, 46, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px 30px;
            backdrop-filter: blur(20px);
            flex: 1;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #6366f1;
        }
        
        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .tasks-card {
            background: rgba(30, 30, 46, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 30px;
            backdrop-filter: blur(20px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px 10px;
            color: #94a3b8;
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        td {
            padding: 15px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-waiting {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #f59e0b;
        }
        
        .badge-done {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        
        .btn-copy {
            background: #6366f1;
            color: white;
        }
        
        .btn-copy:hover {
            background: #4f52e0;
            transform: translateY(-2px);
        }
        
        .btn-done {
            background: #10b981;
            color: white;
        }
        
        .btn-done:hover {
            background: #0ea271;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .toolbar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .proxy-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .proxy-item {
            display: inline-block;
            background: rgba(255, 255, 255, 0.05);
            padding: 5px 12px;
            border-radius: 20px;
            margin: 5px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 ShadowBoost — Панель управления задачами</h1>
            <div style="display: flex; gap: 10px;">
                <span style="color: #94a3b8;">👤 admin</span>
                <a href="?logout=1" style="color: #ef4444; text-decoration: none;">Выход</a>
            </div>
        </div>
        
        <?php
        // ===========================================
        // ЧИТАЕМ ЗАДАЧИ ИЗ ФАЙЛА
        // ===========================================
        $tasks_file = 'tasks.txt';
        $tasks = [];
        $waiting_count = 0;
        
        if (file_exists($tasks_file)) {
            $content = file_get_contents($tasks_file);
            $lines = explode("\n", trim($content));
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                // Разбираем строку
                $parts = explode(' | ', $line);
                if (count($parts) >= 4) {
                    $task = [
                        'date' => $parts[0],
                        'email' => $parts[1],
                        'site' => $parts[2],
                        'tariff' => $parts[3],
                        'status' => 'waiting'
                    ];
                    $tasks[] = $task;
                    $waiting_count++;
                }
            }
            
            // Сортируем: новые сверху
            $tasks = array_reverse($tasks);
        }
        
        // ===========================================
        // УДАЛЕНИЕ ЗАДАЧИ (если нажали "Выполнено")
        // ===========================================
        if (isset($_GET['delete'])) {
            $delete_index = (int)$_GET['delete'];
            
            if (file_exists($tasks_file)) {
                $lines = file($tasks_file, FILE_IGNORE_NEW_LINES);
                if (isset($lines[$delete_index])) {
                    unset($lines[$delete_index]);
                    file_put_contents($tasks_file, implode("\n", $lines) . "\n");
                }
            }
            
            header('Location: admin.php');
            exit;
        }
        
        // ===========================================
        // ОЧИСТКА ВСЕХ ЗАДАЧ
        // ===========================================
        if (isset($_GET['clear_all'])) {
            file_put_contents($tasks_file, '');
            header('Location: admin.php');
            exit;
        }
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $waiting_count ?></div>
                <div class="stat-label">Ожидают запуска</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($tasks) ?></div>
                <div class="stat-label">Всего задач сегодня</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #10b981;">⚡</div>
                <div class="stat-label">Статус: работаем</div>
            </div>
        </div>
        
        <div class="tasks-card">
            <div class="toolbar">
                <a href="?clear_all=1" onclick="return confirm('Удалить ВСЕ задачи?')" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px;">🗑️ Очистить всё</a>
                <a href="admin.php" style="background: #6366f1; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px;">🔄 Обновить</a>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 20px;">✨</div>
                    <h2 style="margin-bottom: 10px;">Задач пока нет</h2>
                    <p style="color: #94a3b8;">Ждём первую оплату...</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата и время</th>
                            <th>Клиент</th>
                            <th>Сайт</th>
                            <th>Тариф</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $index => $task): ?>
                        <tr>
                            <td style="color: #6366f1; font-weight: 600;">#<?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($task['date']) ?></td>
                            <td><?= htmlspecialchars($task['email']) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($task['site']) ?>" target="_blank" style="color: #94a3b8;">
                                    <?= htmlspecialchars($task['site']) ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $tariff_name = $task['tariff'];
                                $tariff_price = '';
                                
                                if ($task['tariff'] == 'basic') {
                                    $tariff_name = 'Базовый';
                                    $tariff_price = '2 000₽';
                                } elseif ($task['tariff'] == 'optimal') {
                                    $tariff_name = 'Оптима';
                                    $tariff_price = '5 000₽';
                                } elseif ($task['tariff'] == 'pro') {
                                    $tariff_name = 'Профи';
                                    $tariff_price = '10 000₽';
                                }
                                ?>
                                <strong><?= $tariff_name ?></strong>
                                <span style="color: #94a3b8; margin-left: 5px;"><?= $tariff_price ?></span>
                            </td>
                            <td>
                                <span class="badge badge-waiting">⏳ Ожидает</span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($task['site']) ?>')">
                                        📋 Копировать
                                    </button>
                                    <a href="?delete=<?= array_search($task, array_reverse($tasks, true)) ?>" 
                                       class="btn btn-done" 
                                       onclick="return confirm('Отметить как выполненное?')">
                                        ✅ Готово
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <h3 style="margin-bottom: 15px; font-size: 16px;">📌 ИНСТРУКЦИЯ:</h3>
                <ol style="color: #94a3b8; line-height: 1.8; padding-left: 20px;">
                    <li>1️⃣ <strong>Копируй сайт</strong> — нажми "Копировать"</li>
                    <li>2️⃣ <strong>Вставь в свою программу накрутки</strong></li>
                    <li>3️⃣ <strong>Запусти</strong> с нужным тарифом</li>
                    <li>4️⃣ <strong>Нажми "✅ Готово"</strong> — задача исчезнет</li>
                </ol>
            </div>
        </div>
        
        <div class="proxy-list">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="font-size: 16px;">🔌 Резидентские прокси (для вставки в программу)</h3>
                <span style="color: #10b981; font-size: 13px;">● Активны: 8 шт</span>
            </div>
            <div>
                <span class="proxy-item">45.132.184.53:3128</span>
                <span class="proxy-item">94.103.89.129:8080</span>
                <span class="proxy-item">185.244.215.242:4153</span>
                <span class="proxy-item">46.8.220.113:5678</span>
                <span class="proxy-item">176.118.163.174:9999</span>
                <span class="proxy-item">195.133.1.189:1080</span>
                <span class="proxy-item">91.243.44.78:3128</span>
                <span class="proxy-item">188.130.235.166:8080</span>
            </div>
            <p style="color: #f59e0b; font-size: 12px; margin-top: 15px;">
                ⚠️ Прокси в админке — ПРИМЕР. Вставь СВОИ реальные прокси из IPRoyal.
            </p>
        </div>
        
        <div style="margin-top: 30px; color: #94a3b8; font-size: 13px; text-align: center;">
            <p>📁 Файл задач: <?= __DIR__ ?>/tasks.txt</p>
            <p>🕐 Серверное время: <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ Сайт скопирован: ' + text);
        }).catch(function(err) {
            // Fallback
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            alert('✅ Сайт скопирован: ' + text);
        });
    }
    </script>
</body>
</html>

<?php
// install.php - Файл для создания необходимых таблиц

header('Content-Type: text/html; charset=utf-8');

// Данные для подключения
$host = '185.207.214.14';
$dbname = 'gs321012';
$username = 'gs321012';
$password = 'xiSz5Q7GYV40';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Установка системы личного кабинета</h2>";
    
    // Проверяем существование таблицы accounts
    $result = $pdo->query("SHOW TABLES LIKE 'accounts'");
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Таблица accounts уже существует</p>";
        
        // Проверяем наличие необходимых колонок
        $columns = $pdo->query("SHOW COLUMNS FROM accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'login', 'email', 'password', 'money', 'donate', 'admin_level'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (empty($missingColumns)) {
            echo "<p style='color: green;'>✓ Все необходимые колонки присутствуют</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Отсутствуют колонки: " . implode(', ', $missingColumns) . "</p>";
            echo "<p>Вы можете добавить их вручную или использовать следующий запрос:</p>";
            echo "<pre>";
            foreach ($missingColumns as $column) {
                switch ($column) {
                    case 'money':
                        echo "ALTER TABLE accounts ADD COLUMN money INT DEFAULT 0;\n";
                        break;
                    case 'donate':
                        echo "ALTER TABLE accounts ADD COLUMN donate INT DEFAULT 0;\n";
                        break;
                    case 'admin_level':
                        echo "ALTER TABLE accounts ADD COLUMN admin_level TINYINT DEFAULT 0;\n";
                        break;
                }
            }
            echo "</pre>";
        }
    } else {
        // Создаем таблицу accounts
        $sql = "CREATE TABLE `accounts` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `login` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `money` INT DEFAULT 0,
            `donate` INT DEFAULT 0,
            `admin_level` TINYINT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `login_unique` (`login`),
            UNIQUE KEY `email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Таблица accounts создана</p>";
    }
    
    // Создаем таблицу payments
    $result = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Таблица payments уже существует</p>";
    } else {
        $sql = "CREATE TABLE `payments` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `payment_id` VARCHAR(100) NOT NULL,
            `order_id` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `method` VARCHAR(50) NOT NULL,
            `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `user_id_idx` (`user_id`),
            INDEX `order_id_idx` (`order_id`),
            INDEX `status_idx` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Таблица payments создана</p>";
    }
    
    // Создаем таблицу transactions
    $result = $pdo->query("SHOW TABLES LIKE 'transactions'");
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Таблица transactions уже существует</p>";
    } else {
        $sql = "CREATE TABLE `transactions` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `description` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `user_id_idx` (`user_id`),
            INDEX `type_idx` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Таблица transactions создана</p>";
    }
    
    echo "<h3 style='color: green;'>Установка завершена!</h3>";
    echo "<p>Теперь вы можете:</p>";
    echo "<ol>";
    echo "<li>Настроить AnyPay в файле api.php (заменить YOUR_MERCHANT_ID, YOUR_API_KEY, YOUR_SECRET_KEY)</li>";
    echo "<li>Удалить этот файл install.php</li>";
    echo "<li>Проверить работу системы</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}

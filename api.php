<?php
// api.php - Основной backend API для личного кабинета

// Включение вывода ошибок (отключить на продакшене)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Заголовки CORS
header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Настройки сессии
session_set_cookie_params([
    'lifetime' => 86400 * 7, // 7 дней
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// ============ КОНФИГУРАЦИЯ БАЗЫ ДАННЫХ ============
define('DB_HOST', '185.207.214.14');
define('DB_NAME', 'gs321012');
define('DB_USER', 'gs321012');
define('DB_PASS', 'xiSz5Q7GYV40');

// ============ КОНФИГУРАЦИЯ ANYPAY ============
/*
ИНСТРУКЦИЯ ПО НАСТРОЙКЕ ANYPAY:
1. Зарегистрируйтесь на https://anypay.io
2. В личном кабинете AnyPay получите:
   - Merchant ID
   - API ключ (API ID)
   - Секретный ключ (API Key)
3. Вставьте ваши данные ниже:
*/
define('ANYPAY_MERCHANT_ID', 'ВАШ_MERCHANT_ID'); // Заменить на ваш Merchant ID
define('ANYPAY_API_KEY', 'ВАШ_API_КЛЮЧ');        // Заменить на ваш API ключ
define('ANYPAY_SECRET_KEY', 'ВАШ_СЕКРЕТНЫЙ_КЛЮЧ'); // Заменить на ваш секретный ключ

// URL для колбэков (убедитесь, что он доступен извне)
define('ANYPAY_CALLBACK_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api.php?action=payment_callback');
define('ANYPAY_SUCCESS_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']);
define('ANYPAY_FAIL_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']);

// ============ КЛАСС ДЛЯ РАБОТЫ С БАЗОЙ ДАННЫХ ============
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => 'Ошибка подключения к БД: ' . $e->getMessage()]));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// ============ КЛАСС ДЛЯ РАБОТЫ С ANYPAY ============
class AnyPay {
    private $merchantId;
    private $apiKey;
    private $secretKey;
    
    public function __construct($merchantId, $apiKey, $secretKey) {
        $this->merchantId = $merchantId;
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }
    
    // Создание платежа
    public function createPayment($amount, $orderId, $email = null, $method = null) {
        $params = [
            'merchant_id' => $this->merchantId,
            'pay_id' => $orderId,
            'amount' => $amount,
            'currency' => 'RUB',
            'desc' => 'Пополнение баланса на игровом сервере',
            'email' => $email,
            'method' => $method,
            'success_url' => ANYPAY_SUCCESS_URL,
            'fail_url' => ANYPAY_FAIL_URL,
            'callback_url' => ANYPAY_CALLBACK_URL
        ];
        
        // Генерация подписи
        $params['sign'] = $this->generateSignature($params);
        
        // Отправка запроса
        $ch = curl_init('https://anypay.io/api/create-payment');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['result']) && isset($data['result']['payment_url'])) {
                return [
                    'success' => true,
                    'payment_url' => $data['result']['payment_url'],
                    'payment_id' => $data['result']['payment_id']
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Ошибка создания платежа'];
    }
    
    // Проверка подписи callback
    public function verifySignature($params) {
        if (!isset($params['sign'])) {
            return false;
        }
        
        $receivedSign = $params['sign'];
        unset($params['sign']);
        
        $generatedSign = $this->generateSignature($params);
        return hash_equals($generatedSign, $receivedSign);
    }
    
    // Генерация подписи
    private function generateSignature($params) {
        ksort($params);
        $signString = implode(':', $params) . ':' . $this->secretKey;
        return md5($signString);
    }
}

// ============ ОСНОВНОЙ КЛАСС API ============
class Api {
    private $db;
    private $anypay;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->anypay = new AnyPay(ANYPAY_MERCHANT_ID, ANYPAY_API_KEY, ANYPAY_SECRET_KEY);
    }
    
    // Основной обработчик
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            // Аутентификация
            case 'check_auth':
                $this->checkAuth();
                break;
            case 'login':
                $this->login();
                break;
            case 'register':
                $this->register();
                break;
            case 'logout':
                $this->logout();
                break;
                
            // Профиль
            case 'get_profile':
                $this->getProfile();
                break;
            case 'change_nickname':
                $this->changeNickname();
                break;
            case 'change_password':
                $this->changePassword();
                break;
                
            // Платежи
            case 'create_payment':
                $this->createPayment();
                break;
            case 'payment_callback':
                $this->paymentCallback();
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Действие не найдено']);
        }
    }
    
    // ============ МЕТОДЫ АУТЕНТИФИКАЦИИ ============
    
    private function checkAuth() {
        if (isset($_SESSION['user_id'])) {
            $user = $this->getUserById($_SESSION['user_id']);
            echo json_encode([
                'authenticated' => true,
                'user' => $user
            ]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
    }
    
    private function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Не указаны данные для входа']);
            return;
        }
        
        $username = trim($data['username']);
        $password = $data['password'];
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM accounts WHERE login = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Обновляем хэш пароля, если он устарел
                if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $this->db->prepare("UPDATE accounts SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $user['id']]);
                }
                
                // Сохраняем в сессию
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_login'] = $user['login'];
                
                // Не возвращаем пароль в ответе
                unset($user['password']);
                
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }
    }
    
    private function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Не указаны все обязательные поля']);
            return;
        }
        
        $email = trim($data['email']);
        $username = trim($data['username']);
        $password = $data['password'];
        
        // Валидация
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Некорректный email']);
            return;
        }
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            echo json_encode(['success' => false, 'message' => 'Никнейм должен быть от 3 до 50 символов']);
            return;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Пароль должен быть не менее 6 символов']);
            return;
        }
        
        try {
            // Проверяем, существует ли email
            $stmt = $this->db->prepare("SELECT id FROM accounts WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Этот email уже зарегистрирован']);
                return;
            }
            
            // Проверяем, существует ли никнейм
            $stmt = $this->db->prepare("SELECT id FROM accounts WHERE login = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Этот никнейм уже занят']);
                return;
            }
            
            // Хэшируем пароль
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Создаем аккаунт
            $stmt = $this->db->prepare("
                INSERT INTO accounts (login, email, password, money, donate, admin_level, created_at) 
                VALUES (?, ?, ?, 0, 0, 0, NOW())
            ");
            $stmt->execute([$username, $email, $passwordHash]);
            
            echo json_encode(['success' => true, 'message' => 'Регистрация успешна']);
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка регистрации: ' . $e->getMessage()]);
        }
    }
    
    private function logout() {
        session_destroy();
        echo json_encode(['success' => true]);
    }
    
    // ============ МЕТОДЫ ПРОФИЛЯ ============
    
    private function getProfile() {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
            return;
        }
        
        $user = $this->getUserById($_SESSION['user_id']);
        if ($user) {
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        }
    }
    
    private function changeNickname() {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['new_nickname']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Не указаны все данные']);
            return;
        }
        
        $newNickname = trim($data['new_nickname']);
        $password = $data['password'];
        
        if (strlen($newNickname) < 3 || strlen($newNickname) > 50) {
            echo json_encode(['success' => false, 'message' => 'Никнейм должен быть от 3 до 50 символов']);
            return;
        }
        
        try {
            // Проверяем текущий пароль
            $stmt = $this->db->prepare("SELECT password FROM accounts WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Неверный пароль']);
                return;
            }
            
            // Проверяем, не занят ли новый никнейм
            $stmt = $this->db->prepare("SELECT id FROM accounts WHERE login = ? AND id != ?");
            $stmt->execute([$newNickname, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Этот никнейм уже занят']);
                return;
            }
            
            // Обновляем никнейм
            $stmt = $this->db->prepare("UPDATE accounts SET login = ? WHERE id = ?");
            $stmt->execute([$newNickname, $_SESSION['user_id']]);
            
            $_SESSION['user_login'] = $newNickname;
            
            echo json_encode(['success' => true, 'message' => 'Никнейм успешно изменен']);
            
        } catch (PDOException $e) {
            error_log("Change nickname error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка смены никнейма']);
        }
    }
    
    private function changePassword() {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            echo json_encode(['success' => false, 'message' => 'Не указаны все данные']);
            return;
        }
        
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Новый пароль должен быть не менее 6 символов']);
            return;
        }
        
        try {
            // Проверяем текущий пароль
            $stmt = $this->db->prepare("SELECT password FROM accounts WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Неверный текущий пароль']);
                return;
            }
            
            // Хэшируем новый пароль
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // Обновляем пароль
            $stmt = $this->db->prepare("UPDATE accounts SET password = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Пароль успешно изменен']);
            
        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка смены пароля']);
        }
    }
    
    // ============ МЕТОДЫ ПЛАТЕЖЕЙ ============
    
    private function createPayment() {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Неверная сумма']);
            return;
        }
        
        $amount = intval($data['amount']);
        $method = $data['method'] ?? 'card';
        
        // Проверяем минимальную сумму
        if ($amount < 50) {
            echo json_encode(['success' => false, 'message' => 'Минимальная сумма 50 коинов']);
            return;
        }
        
        try {
            // Получаем email пользователя
            $stmt = $this->db->prepare("SELECT email FROM accounts WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            // Создаем уникальный ID заказа
            $orderId = 'DONATE_' . $_SESSION['user_id'] . '_' . time() . '_' . rand(1000, 9999);
            
            // Создаем платеж в AnyPay
            $result = $this->anypay->createPayment($amount, $orderId, $user['email'] ?? null, $method);
            
            if ($result['success']) {
                // Сохраняем информацию о платеже в БД
                $stmt = $this->db->prepare("
                    INSERT INTO payments (user_id, payment_id, order_id, amount, method, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $result['payment_id'],
                    $orderId,
                    $amount,
                    $method
                ]);
                
                echo json_encode([
                    'success' => true,
                    'payment_url' => $result['payment_url'],
                    'payment_id' => $result['payment_id']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            
        } catch (PDOException $e) {
            error_log("Create payment error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка создания платежа']);
        }
    }
    
    private function paymentCallback() {
        // Получаем данные от AnyPay
        $data = $_POST;
        
        // Проверяем подпись
        if (!$this->anypay->verifySignature($data)) {
            error_log("Invalid AnyPay signature");
            die('Invalid signature');
        }
        
        $paymentId = $data['payment_id'] ?? '';
        $orderId = $data['pay_id'] ?? '';
        $status = $data['status'] ?? '';
        $amount = floatval($data['amount'] ?? 0);
        
        try {
            // Находим платеж в БД
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ? AND payment_id = ?");
            $stmt->execute([$orderId, $paymentId]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                error_log("Payment not found: order_id=$orderId, payment_id=$paymentId");
                die('Payment not found');
            }
            
            // Если платеж уже обработан
            if ($payment['status'] === 'completed') {
                die('Already processed');
            }
            
            // Если платеж успешный
            if ($status === 'paid') {
                // Обновляем статус платежа
                $stmt = $this->db->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$payment['id']]);
                
                // Начисляем донат-валюту пользователю
                $stmt = $this->db->prepare("UPDATE accounts SET donate = donate + ? WHERE id = ?");
                $stmt->execute([$amount, $payment['user_id']]);
                
                error_log("Payment completed: user_id={$payment['user_id']}, amount=$amount");
                
                // Можно также добавить запись в историю операций
                $stmt = $this->db->prepare("
                    INSERT INTO transactions (user_id, type, amount, description, created_at) 
                    VALUES (?, 'donate', ?, 'Пополнение баланса через AnyPay', NOW())
                ");
                $stmt->execute([$payment['user_id'], $amount]);
            }
            
            // Отправляем ответ AnyPay
            echo 'OK';
            
        } catch (PDOException $e) {
            error_log("Payment callback error: " . $e->getMessage());
            die('Error');
        }
    }
    
    // ============ ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ============
    
    private function getUserById($userId) {
        try {
            // Сначала проверяем стандартную структуру
            $stmt = $this->db->prepare("
                SELECT id, login, email, money, donate, admin_level, created_at, password 
                FROM accounts 
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            // Если таблица accounts не существует или не содержит нужных полей
            if (!$user) {
                // Пробуем альтернативные варианты имен полей
                $stmt = $this->db->prepare("SHOW COLUMNS FROM accounts");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Адаптируем запрос под реальную структуру
                $selectFields = [];
                foreach ($columns as $column) {
                    $selectFields[] = $column;
                }
                
                $fields = implode(', ', $selectFields);
                $stmt = $this->db->prepare("SELECT $fields FROM accounts WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }
            
            return $user;
            
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
}

// ============ ЗАПУСК API ============
try {
    $api = new Api();
    $api->handleRequest();
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']);
}

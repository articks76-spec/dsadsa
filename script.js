// Основной JavaScript файл для личного кабинета

// ============ КОНФИГУРАЦИЯ ============
const API_BASE_URL = window.location.origin; // Будет использоваться api.php из той же директории
let currentUser = null;
let selectedAmount = 0;
let selectedMethod = 'card';

// ============ ИНИЦИАЛИЗАЦИЯ ============
document.addEventListener('DOMContentLoaded', function() {
    initEventListeners();
    checkAuthStatus();
    updateStatsPreview();
});

// ============ ОСНОВНЫЕ ФУНКЦИИ ============

// Инициализация всех обработчиков событий
function initEventListeners() {
    // Кнопки авторизации
    document.getElementById('loginBtn').addEventListener('click', () => openModal('modalAuth'));
    document.getElementById('registerBtn').addEventListener('click', () => {
        openModal('modalAuth');
        switchTab('register');
    });
    document.getElementById('profileBtn').addEventListener('click', () => openProfile());
    document.getElementById('logoutBtn').addEventListener('click', logout);
    
    // Формы
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('registerForm').addEventListener('submit', handleRegister);
    document.getElementById('changeNicknameForm').addEventListener('submit', handleChangeNickname);
    document.getElementById('changePasswordForm').addEventListener('submit', handleChangePassword);
    
    // Кнопки в профиле
    document.getElementById('changeNicknameBtn').addEventListener('click', () => openModal('modalChangeNickname'));
    document.getElementById('changePasswordBtn').addEventListener('click', () => openModal('modalChangePassword'));
    document.getElementById('donateBtn').addEventListener('click', () => openModal('modalDonate'));
    document.getElementById('refreshBtn').addEventListener('click', loadProfileData);
    document.getElementById('quickDonate').addEventListener('click', () => openModal('modalDonate'));
    document.getElementById('linkSteamBtn').addEventListener('click', linkSteam);
    document.getElementById('supportBtn').addEventListener('click', showSupport);
    
    // Донат
    document.querySelectorAll('.amount-option').forEach(option => {
        option.addEventListener('click', function() {
            selectAmount(this.dataset.amount);
        });
    });
    
    document.querySelectorAll('.method-option').forEach(method => {
        method.addEventListener('click', function() {
            selectPaymentMethod(this.dataset.method);
        });
    });
    
    document.getElementById('customAmount').addEventListener('input', function() {
        selectAmount(this.value);
    });
    
    document.getElementById('createPaymentBtn').addEventListener('click', createPayment);
    
    // Переключение видимости пароля
    document.getElementById('toggleLoginPassword').addEventListener('click', togglePasswordVisibility);
    document.getElementById('toggleRegisterPassword').addEventListener('click', togglePasswordVisibility);
    
    // Закрытие модалок
    document.querySelectorAll('.close-modal').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal.id);
        });
    });
    
    // Закрытие модалок по клику вне
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });
    
    // Переключение табов
    document.querySelectorAll('.tab-btn').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            switchTab(tabId);
        });
    });
}

// ============ АВТОРИЗАЦИЯ ============

// Проверка статуса авторизации
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=check_auth`, {
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.authenticated) {
                currentUser = data.user;
                updateAuthUI(true);
                await loadProfileData();
            } else {
                updateAuthUI(false);
            }
        }
    } catch (error) {
        console.error('Ошибка проверки авторизации:', error);
        updateAuthUI(false);
    }
}

// Обработка входа
async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;
    const messageDiv = document.getElementById('loginMessage');
    
    if (!username || !password) {
        showMessage(messageDiv, 'Заполните все поля', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            updateAuthUI(true);
            closeModal('modalAuth');
            showNotification('Успешный вход!');
            await loadProfileData();
        } else {
            showMessage(messageDiv, data.message || 'Ошибка входа', 'error');
        }
    } catch (error) {
        console.error('Ошибка входа:', error);
        showMessage(messageDiv, 'Ошибка соединения', 'error');
    }
}

// Обработка регистрации
async function handleRegister(e) {
    e.preventDefault();
    
    const email = document.getElementById('registerEmail').value;
    const username = document.getElementById('registerUsername').value;
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('registerConfirmPassword').value;
    const messageDiv = document.getElementById('registerMessage');
    
    // Валидация
    if (!email || !username || !password || !confirmPassword) {
        showMessage(messageDiv, 'Заполните все поля', 'error');
        return;
    }
    
    if (password !== confirmPassword) {
        showMessage(messageDiv, 'Пароли не совпадают', 'error');
        return;
    }
    
    if (password.length < 6) {
        showMessage(messageDiv, 'Пароль должен быть не менее 6 символов', 'error');
        return;
    }
    
    if (username.length < 3) {
        showMessage(messageDiv, 'Никнейм должен быть не менее 3 символов', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ email, username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(messageDiv, 'Регистрация успешна! Теперь войдите в аккаунт.', 'success');
            switchTab('login');
            document.getElementById('loginUsername').value = username;
        } else {
            showMessage(messageDiv, data.message || 'Ошибка регистрации', 'error');
        }
    } catch (error) {
        console.error('Ошибка регистрации:', error);
        showMessage(messageDiv, 'Ошибка соединения', 'error');
    }
}

// Выход из системы
async function logout() {
    try {
        await fetch(`${API_BASE_URL}/api.php?action=logout`, {
            credentials: 'include'
        });
        
        currentUser = null;
        updateAuthUI(false);
        showNotification('Вы вышли из системы');
    } catch (error) {
        console.error('Ошибка выхода:', error);
    }
}

// ============ ПРОФИЛЬ ============

// Загрузка данных профиля
async function loadProfileData() {
    if (!currentUser) return;
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=get_profile`, {
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.success) {
                updateProfileUI(data.user);
                
                // Обновляем currentUser
                currentUser = data.user;
                updateStatsPreview();
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки профиля:', error);
    }
}

// Открытие профиля
async function openProfile() {
    await loadProfileData();
    openModal('modalProfile');
}

// Обновление UI профиля
function updateProfileUI(user) {
    document.getElementById('profileUsername').textContent = user.login || user.username || 'Не указан';
    document.getElementById('profileEmail').textContent = user.email || 'Не указана';
    document.getElementById('profileMoney').textContent = user.money || 0;
    document.getElementById('profileDonate').textContent = user.donate || 0;
    document.getElementById('profileAdminLevel').textContent = user.admin_level || 0;
    document.getElementById('profileCreatedAt').textContent = user.created_at ? 
        new Date(user.created_at).toLocaleDateString('ru-RU') : '-';
    
    // Обновляем текущий ник в форме смены
    document.getElementById('currentNickname').value = user.login || user.username || '';
    
    // Обновляем бейдж админа
    const adminBadge = document.getElementById('adminBadge');
    const adminLevel = user.admin_level || 0;
    
    if (adminLevel >= 5) {
        adminBadge.textContent = 'ADMIN';
        adminBadge.style.background = '#ff0000';
    } else if (adminLevel >= 3) {
        adminBadge.textContent = 'MODER';
        adminBadge.style.background = '#ff9900';
    } else if (adminLevel >= 1) {
        adminBadge.textContent = 'HELPER';
        adminBadge.style.background = '#00aa00';
    } else {
        adminBadge.textContent = 'USER';
        adminBadge.style.background = '#666';
    }
}

// ============ СМЕНА ДАННЫХ ============

// Смена никнейма
async function handleChangeNickname(e) {
    e.preventDefault();
    
    const newNickname = document.getElementById('newNickname').value;
    const password = document.getElementById('nicknamePassword').value;
    const messageDiv = document.getElementById('nicknameMessage');
    
    if (!newNickname || !password) {
        showMessage(messageDiv, 'Заполните все поля', 'error');
        return;
    }
    
    if (newNickname.length < 3) {
        showMessage(messageDiv, 'Никнейм должен быть не менее 3 символов', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=change_nickname`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ 
                new_nickname: newNickname, 
                password: password 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(messageDiv, 'Никнейм успешно изменен!', 'success');
            currentUser.login = newNickname;
            setTimeout(() => {
                closeModal('modalChangeNickname');
                loadProfileData();
            }, 2000);
        } else {
            showMessage(messageDiv, data.message || 'Ошибка смены никнейма', 'error');
        }
    } catch (error) {
        console.error('Ошибка смены никнейма:', error);
        showMessage(messageDiv, 'Ошибка соединения', 'error');
    }
}

// Смена пароля
async function handleChangePassword(e) {
    e.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmNewPassword').value;
    const messageDiv = document.getElementById('passwordMessage');
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        showMessage(messageDiv, 'Заполните все поля', 'error');
        return;
    }
    
    if (newPassword.length < 6) {
        showMessage(messageDiv, 'Новый пароль должен быть не менее 6 символов', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showMessage(messageDiv, 'Пароли не совпадают', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=change_password`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ 
                current_password: currentPassword, 
                new_password: newPassword 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(messageDiv, 'Пароль успешно изменен!', 'success');
            setTimeout(() => {
                closeModal('modalChangePassword');
            }, 2000);
        } else {
            showMessage(messageDiv, data.message || 'Ошибка смены пароля', 'error');
        }
    } catch (error) {
        console.error('Ошибка смены пароля:', error);
        showMessage(messageDiv, 'Ошибка соединения', 'error');
    }
}

// ============ ДОНАТ ============

// Выбор суммы
function selectAmount(amount) {
    selectedAmount = parseInt(amount);
    
    // Обновляем UI
    document.querySelectorAll('.amount-option').forEach(option => {
        option.classList.remove('selected');
        if (parseInt(option.dataset.amount) === selectedAmount) {
            option.classList.add('selected');
        }
    });
    
    // Обновляем кастомное поле
    if (selectedAmount > 0) {
        document.getElementById('customAmount').value = selectedAmount;
    }
    
    updateDonateSummary();
    updateCreatePaymentButton();
}

// Выбор метода оплаты
function selectPaymentMethod(method) {
    selectedMethod = method;
    
    // Обновляем UI
    document.querySelectorAll('.method-option').forEach(option => {
        option.classList.remove('active');
        if (option.dataset.method === method) {
            option.classList.add('active');
        }
    });
    
    updateCreatePaymentButton();
}

// Обновление сводки доната
function updateDonateSummary() {
    const donateAmount = selectedAmount;
    const donateCost = Math.round(selectedAmount * 1); // 1 донат-коин = 1 рубль
    
    document.getElementById('donateAmount').textContent = donateAmount;
    document.getElementById('donateCost').textContent = donateCost;
}

// Обновление кнопки создания платежа
function updateCreatePaymentButton() {
    const button = document.getElementById('createPaymentBtn');
    button.disabled = selectedAmount <= 0;
}

// Создание платежа
async function createPayment() {
    if (selectedAmount <= 0 || !selectedMethod) {
        showDonateMessage('Выберите сумму и способ оплаты', 'error');
        return;
    }
    
    if (!currentUser) {
        showDonateMessage('Требуется авторизация', 'error');
        return;
    }
    
    const button = document.getElementById('createPaymentBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> СОЗДАНИЕ ПЛАТЕЖА...';
    button.disabled = true;
    
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=create_payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ 
                amount: selectedAmount,
                method: selectedMethod
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.payment_url) {
            // Перенаправляем на страницу оплаты
            window.open(data.payment_url, '_blank');
            showDonateMessage('Переход на страницу оплаты...', 'success');
            
            // Закрываем модалку через 2 секунды
            setTimeout(() => {
                closeModal('modalDonate');
            }, 2000);
        } else {
            showDonateMessage(data.message || 'Ошибка создания платежа', 'error');
        }
    } catch (error) {
        console.error('Ошибка создания платежа:', error);
        showDonateMessage('Ошибка соединения', 'error');
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// ============ ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ============

// Обновление UI авторизации
function updateAuthUI(isLoggedIn) {
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const profileBtn = document.getElementById('profileBtn');
    const logoutBtn = document.getElementById('logoutBtn');
    
    if (isLoggedIn) {
        loginBtn.style.display = 'none';
        registerBtn.style.display = 'none';
        profileBtn.style.display = 'flex';
        logoutBtn.style.display = 'flex';
    } else {
        loginBtn.style.display = 'flex';
        registerBtn.style.display = 'flex';
        profileBtn.style.display = 'none';
        logoutBtn.style.display = 'none';
    }
}

// Обновление превью статистики
function updateStatsPreview() {
    const statsPreview = document.getElementById('statsPreview');
    
    if (currentUser) {
        statsPreview.innerHTML = `
            <h3>Ваша статистика:</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
                <div>
                    <small>Игровая валюта:</small>
                    <p style="color: #ff9900; font-weight: bold;">${currentUser.money || 0}</p>
                </div>
                <div>
                    <small>Донат валюта:</small>
                    <p style="color: #ff0000; font-weight: bold;">${currentUser.donate || 0}</p>
                </div>
                <div>
                    <small>Уровень админки:</small>
                    <p style="color: #00ff00; font-weight: bold;">${currentUser.admin_level || 0}</p>
                </div>
                <div>
                    <small>Статус:</small>
                    <p style="color: #00aaff; font-weight: bold;">${currentUser.admin_level >= 1 ? 'Привилегия' : 'Игрок'}</p>
                </div>
            </div>
        `;
    } else {
        statsPreview.innerHTML = `
            <p>Войдите в систему, чтобы увидеть статистику вашего аккаунта</p>
            <button class="btn btn-login" style="margin-top: 10px;" onclick="openModal('modalAuth')">
                <i class="fas fa-sign-in-alt"></i> Войти в систему
            </button>
        `;
    }
}

// Открытие модального окна
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

// Закрытие модального окна
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Очищаем формы
        if (modalId === 'modalAuth') {
            document.getElementById('loginForm').reset();
            document.getElementById('registerForm').reset();
            document.getElementById('loginMessage').innerHTML = '';
            document.getElementById('registerMessage').innerHTML = '';
        }
    }
}

// Переключение табов
function switchTab(tabId) {
    // Обновляем активные кнопки
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    
    // Обновляем активный контент
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === `${tabId}-tab`);
    });
}

// Показ сообщений
function showMessage(element, text, type) {
    element.textContent = text;
    element.className = 'form-message';
    element.classList.add(type);
    
    if (type === 'success') {
        element.style.background = 'rgba(0, 255, 0, 0.1)';
        element.style.color = '#0f0';
        element.style.border = '1px solid #0f0';
    } else {
        element.style.background = 'rgba(255, 0, 0, 0.1)';
        element.style.color = '#f00';
        element.style.border = '1px solid #f00';
    }
    
    // Автоматическое скрытие
    if (type === 'success') {
        setTimeout(() => {
            element.textContent = '';
            element.className = 'form-message';
        }, 5000);
    }
}

// Показ сообщений в донате
function showDonateMessage(text, type) {
    const element = document.getElementById('donateMessage');
    showMessage(element, text, type);
}

// Показ уведомлений
function showNotification(text, type = 'success') {
    const notification = document.getElementById('notification');
    const notificationText = document.getElementById('notificationText');
    
    notificationText.textContent = text;
    
    if (type === 'error') {
        notification.style.background = '#f00';
        notification.querySelector('i').className = 'fas fa-exclamation-circle';
    } else if (type === 'warning') {
        notification.style.background = '#ff9900';
        notification.querySelector('i').className = 'fas fa-exclamation-triangle';
    } else {
        notification.style.background = '#4CAF50';
        notification.querySelector('i').className = 'fas fa-check-circle';
    }
    
    notification.style.display = 'block';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 5000);
}

// Переключение видимости пароля
function togglePasswordVisibility(e) {
    const icon = e.target;
    const input = icon.closest('.form-group').querySelector('input[type="password"], input[type="text"]');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Привязка Steam (заглушка)
function linkSteam() {
    showNotification('Функция привязки Steam в разработке', 'warning');
}

// Техподдержка
function showSupport() {
    showNotification('Свяжитесь с поддержкой: admin@oldschool.gaming', 'info');
}

// Автоматическое обновление данных каждые 30 секунд
setInterval(() => {
    if (currentUser) {
        loadProfileData();
    }
}, 30000);

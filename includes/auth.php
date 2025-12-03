<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Валидация
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Имя пользователя обязательно";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают";
    }
    
    if (userExists($pdo, $username, $email)) {
        $errors[] = "Пользователь с таким именем или email уже существует";
    }
    
    if (empty($errors)) {
        $userId = registerUser($pdo, $username, $email, $password);
        if ($userId) {
            $_SESSION['success_message'] = "Регистрация прошла успешно! Теперь вы можете войти.";
            redirect('../auth/login.php');
        } else {
            $errors[] = "Ошибка при регистрации. Попробуйте позже.";
        }
    }
}

// Обработка формы авторизации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $errors = [];
    
    if (empty($username) || empty($password)) {
        $errors[] = "Заполните все поля";
    }
    
    if (empty($errors)) {
        if (loginUser($pdo, $username, $password)) {
            redirect('../dashboard/index.php');
        } else {
            $errors[] = "Неверное имя пользователя или пароль";
        }
    }
}
?>
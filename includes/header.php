<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$userData = getUserData($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Quiz System'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <h1>Quiz System</h1>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="../dashboard/">Главная</a></li>
                    <?php if ($userData['role'] === 'student'): ?>
                        <li><a href="../quiz/start.php">Пройти тесты</a></li>
                    <?php endif; ?>
                    <?php if ($userData['role'] === 'teacher' || $userData['role'] === 'admin'): ?>
                        <li><a href="../dashboard/my_quizzes.php">Мои тесты</a></li>
                    <?php endif; ?>
                    <li><a href="../dashboard/quiz_results.php">Мои результаты</a></li>
                    <li><a href="../dashboard/profile.php">Профиль</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <span>Привет, <?php echo htmlspecialchars($userData['username']); ?></span>
                <a href="../auth/logout.php" class="btn btn-logout">Выйти</a>
            </div>
        </div>
    </header>
    <main class="container"></main>
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    // Проверяем авторизацию
    if (!isLoggedIn()) {
        throw new Exception('Требуется авторизация');
    }
    
    // Проверяем ID вопроса
    if (empty($_GET['id'])) {
        throw new Exception('ID вопроса не указан');
    }
    
    $questionId = (int)$_GET['id'];
    
    // Проверяем, что тест принадлежит пользователю
    $stmt = $pdo->prepare("
        SELECT q.created_by 
        FROM quizzes q
        JOIN quiz_questions qq ON q.id = qq.quiz_id
        WHERE qq.id = ?
    ");
    $stmt->execute([$questionId]);
    $createdBy = $stmt->fetchColumn();
    
    if ($createdBy != $_SESSION['user_id']) {
        throw new Exception('Нет прав доступа к этому вопросу');
    }
    
    // Удаляем вопрос
    $result = deleteQuestionFromDatabase($pdo, $questionId);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
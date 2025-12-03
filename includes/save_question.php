<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    // Проверяем авторизацию
    if (!isLoggedIn()) {
        throw new Exception('Требуется авторизация');
    }
    
    // Проверяем роль пользователя
    $userData = getUserData($pdo, $_SESSION['user_id']);
    if ($userData['role'] !== 'teacher' && $userData['role'] !== 'admin') {
        throw new Exception('Недостаточно прав');
    }
    
    // Проверяем, что quiz_id принадлежит пользователю
    $quizId = (int)$_POST['quiz_id'];
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
    $stmt->execute([$quizId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Тест не найден или нет прав доступа');
    }
    
    // Подготавливаем данные
    $data = [
        'quiz_id' => $quizId,
        'question_text' => $_POST['question_text'],
        'question_type' => $_POST['question_type'],
        'points' => (int)$_POST['points']
    ];
    
    if (!empty($_POST['question_id'])) {
        $data['question_id'] = (int)$_POST['question_id'];
    }
    
    if (!empty($_FILES['question_image'])) {
        $data['question_image'] = $_FILES['question_image'];
    }
    
    if ($data['question_type'] !== 'text' && !empty($_POST['answers'])) {
        $data['answers'] = json_decode($_POST['answers'], true);
    }
    
    // Сохраняем вопрос
    $result = saveQuestionToDatabase($pdo, $data);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    echo json_encode(['success' => true, 'question_id' => $result['question_id']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
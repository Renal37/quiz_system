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
    
    // Получаем вопрос
    $question = getQuestionWithAnswers($pdo, $questionId);
    if (!$question) {
        throw new Exception('Вопрос не найден');
    }
    
    // Проверяем, что тест принадлежит пользователю
    $stmt = $pdo->prepare("SELECT created_by FROM quizzes WHERE id = ?");
    $stmt->execute([$question['quiz_id']]);
    $createdBy = $stmt->fetchColumn();
    
    if ($createdBy != $_SESSION['user_id']) {
        throw new Exception('Нет прав доступа к этому вопросу');
    }
    
    // Форматируем данные для ответа
    $response = [
        'success' => true,
        'question' => [
            'id' => $question['id'],
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'points' => $question['points'],
            'image' => !empty($question['image']) ? basename($question['image']) : null
        ]
    ];
    
    if (!empty($question['answers'])) {
        $response['question']['answers'] = array_map(function($answer) {
            return [
                'text' => $answer['answer_text'],
                'isCorrect' => (bool)$answer['is_correct']
            ];
        }, $question['answers']);
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
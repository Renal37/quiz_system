<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Проверяем авторизацию
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Получаем ID результата
$resultId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($resultId <= 0) {
    redirect('../dashboard/');
}

// Получаем основной результат
$result = $pdo->prepare("
    SELECT r.*, q.title as quiz_title 
    FROM quiz_results r
    JOIN quizzes q ON r.quiz_id = q.id
    WHERE r.id = ? AND r.user_id = ?
");
$result->execute([$resultId, $_SESSION['user_id']]);
$result = $result->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    redirect('../dashboard/');
}

// Получаем детали результатов
$details = $pdo->prepare("
    SELECT d.*, q.question_text, q.points as max_points
    FROM quiz_result_details d
    JOIN quiz_questions q ON d.question_id = q.id
    WHERE d.result_id = ?
");
$details->execute([$resultId]);
$details = $details->fetchAll(PDO::FETCH_ASSOC);

// Получаем правильные ответы для вопросов
foreach ($details as &$detail) {
    $answers = $pdo->prepare("
        SELECT id, answer_text, is_correct 
        FROM question_answers 
        WHERE question_id = ?
    ");
    $answers->execute([$detail['question_id']]);
    $detail['all_answers'] = $answers->fetchAll(PDO::FETCH_ASSOC);
    
    // Декодируем ответы пользователя
    $detail['user_answers'] = json_decode($detail['user_answers'], true) ?? $detail['user_answers'];
}
unset($detail);

$pageTitle = "Результаты теста";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="results-container">
    <h2>Результаты теста: <?php echo htmlspecialchars($result['quiz_title']); ?></h2>
    
    <div class="result-summary">
        <div class="result-card">
            <h3>Ваш результат</h3>
            <div class="result-score">
                <?php echo $result['score']; ?> из <?php echo $result['max_score']; ?> баллов
            </div>
            <div class="result-percentage">
                <?php echo round($result['score'] / $result['max_score'] * 100); ?>%
            </div>
        </div>
        
        <div class="result-stats">
            <p>Правильных ответов: 
                <?php echo array_reduce($details, function($carry, $item) {
                    return $carry + ($item['is_correct'] ? 1 : 0);
                }, 0); ?> из <?php echo count($details); ?>
            </p>
            <p>Время завершения: <?php echo date('d.m.Y H:i', strtotime($result['completed_at'])); ?></p>
        </div>
    </div>
    
    <div class="result-details">
        <h3>Детализация ответов</h3>
        
        <?php foreach ($details as $index => $detail): ?>
            <div class="question-result <?php echo $detail['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <div class="question-header">
                    <h4>Вопрос #<?php echo $index + 1; ?></h4>
                    <div class="question-points">
                        <?php echo $detail['points']; ?> из <?php echo $detail['max_points']; ?> баллов
                    </div>
                </div>
                
                <div class="question-text">
                    <?php echo htmlspecialchars($detail['question_text']); ?>
                </div>
                
                <div class="correct-answers">
                    <p>Правильные ответы:</p>
                    <ul>
                        <?php foreach ($detail['all_answers'] as $answer): ?>
                            <?php if ($answer['is_correct']): ?>
                                <li><?php echo htmlspecialchars($answer['answer_text']); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="user-answers">
                    <p>Ваши ответы:</p>
                    <?php if (is_array($detail['user_answers'])): ?>
                        <ul>
                            <?php foreach ($detail['user_answers'] as $userAnswer): ?>
                                <?php if (is_numeric($userAnswer)): ?>
                                    <?php 
                                    $answerText = array_reduce($detail['all_answers'], function($carry, $item) use ($userAnswer) {
                                        return $item['id'] == $userAnswer ? $item['answer_text'] : $carry;
                                    }, '');
                                    ?>
                                    <li><?php echo htmlspecialchars($answerText); ?></li>
                                <?php else: ?>
                                    <li><?php echo htmlspecialchars($userAnswer); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php echo htmlspecialchars($detail['user_answers']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="result-actions">
        <a href="../dashboard/" class="btn btn-primary">Вернуться в личный кабинет</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
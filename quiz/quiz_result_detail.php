<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

$resultId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($resultId <= 0) {
    header('Location: ../../dashboard/');
    exit();
}

// Получаем основную информацию о результате
$result = $pdo->prepare("
    SELECT r.*, q.title as quiz_title, q.description as quiz_description, 
           q.time_limit, u.username as creator_name, 
           GROUP_CONCAT(c.name SEPARATOR ', ') as categories
    FROM quiz_results r
    JOIN quizzes q ON r.quiz_id = q.id
    JOIN users u ON q.created_by = u.id
    LEFT JOIN quiz_category_relations qcr ON q.id = qcr.quiz_id
    LEFT JOIN quiz_categories c ON qcr.category_id = c.id
    WHERE r.id = ? AND r.user_id = ?
    GROUP BY r.id
");
$result->execute([$resultId, $_SESSION['user_id']]);
$result = $result->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error_message'] = "Результат не найден";
    redirect('../../dashboard/');
}

// Рассчитываем время прохождения
$startTime = strtotime($result['started_at'] ?? $result['completed_at']);
$endTime = strtotime($result['completed_at']);
$timeTaken = $endTime - $startTime;

// Получаем детали результатов
$details = $pdo->prepare("
    SELECT d.*, q.question_text, q.question_type, q.points as max_points
    FROM quiz_result_details d
    JOIN quiz_questions q ON d.question_id = q.id
    WHERE d.result_id = ?
    ORDER BY d.id ASC
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
    $detail['user_answers'] = json_decode($detail['user_answers'], true) ?? [$detail['user_answers']];
}
unset($detail);

$pageTitle = "Детали результата: " . htmlspecialchars($result['quiz_title']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="result-detail-header">
        <a href="../../dashboard/quiz_results.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Назад к результатам
        </a>
        
        <h1><?php echo htmlspecialchars($result['quiz_title']); ?></h1>
        
        <?php if (!empty($result['quiz_description'])): ?>
            <p class="quiz-description"><?php echo htmlspecialchars($result['quiz_description']); ?></p>
        <?php endif; ?>
        
        <div class="result-meta">
            <div class="meta-item">
                <i class="fas fa-user"></i> Создатель: <?php echo htmlspecialchars($result['creator_name']); ?>
            </div>
            
            <?php if (!empty($result['categories'])): ?>
                <div class="meta-item">
                    <i class="fas fa-tag"></i> Категории: <?php echo htmlspecialchars($result['categories']); ?>
                </div>
            <?php endif; ?>
            
            <div class="meta-item">
                <i class="fas fa-calendar-alt"></i> Дата прохождения: <?php echo date('d.m.Y H:i', $endTime); ?>
            </div>
            
            <div class="meta-item">
                <i class="fas fa-stopwatch"></i> Затраченное время: <?php echo gmdate("i мин s сек", $timeTaken); ?>
            </div>
            
            <?php if ($result['time_limit']): ?>
                <div class="meta-item">
                    <i class="fas fa-clock"></i> Лимит времени: <?php echo $result['time_limit']; ?> мин
                </div>
            <?php endif; ?>
        </div>
        
        <div class="result-summary">
            <div class="summary-card">
                <div class="score-display">
                    <span class="score"><?php echo $result['score']; ?></span>
                    <span class="divider">/</span>
                    <span class="max-score"><?php echo $result['max_score']; ?></span>
                </div>
                <div class="score-label">набранных баллов</div>
            </div>
            
            <div class="summary-card">
                <div class="percentage">
                    <?php echo round($result['score'] / $result['max_score'] * 100); ?>%
                </div>
                <div class="percentage-label">правильных ответов</div>
            </div>
            
            <div class="summary-card">
                <div class="correct-answers">
                    <?php 
                    $correctCount = array_reduce($details, function($carry, $item) {
                        return $carry + ($item['is_correct'] ? 1 : 0);
                    }, 0);
                    echo $correctCount;
                    ?>
                    <span class="divider">/</span>
                    <?php echo count($details); ?>
                </div>
                <div class="answers-label">верных ответов</div>
            </div>
        </div>
    </div>
    
    <div class="result-questions">
        <h2>Детализация по вопросам</h2>
        
        <?php foreach ($details as $index => $detail): ?>
            <div class="question-result <?php echo $detail['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <div class="question-header">
                    <h3>Вопрос #<?php echo $index + 1; ?></h3>
                    <div class="question-points">
                        <?php echo $detail['points']; ?> из <?php echo $detail['max_points']; ?> баллов
                    </div>
                </div>
                
                <div class="question-text">
                    <?php echo htmlspecialchars($detail['question_text']); ?>
                </div>
                
                <?php if ($detail['question_type'] !== 'text'): ?>
                    <div class="correct-answers-section">
                        <h4>Правильные ответы:</h4>
                        <ul class="correct-answers-list">
                            <?php foreach ($detail['all_answers'] as $answer): ?>
                                <?php if ($answer['is_correct']): ?>
                                    <li><?php echo htmlspecialchars($answer['answer_text']); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="user-answers-section">
                    <h4>Ваши ответы:</h4>
                    <?php if (!empty($detail['user_answers'])): ?>
                        <ul class="user-answers-list">
                            <?php foreach ($detail['user_answers'] as $userAnswer): ?>
                                <?php if (is_numeric($userAnswer) && $detail['question_type'] !== 'text'): ?>
                                    <?php 
                                    $answerText = '';
                                    foreach ($detail['all_answers'] as $answer) {
                                        if ($answer['id'] == $userAnswer) {
                                            $answerText = $answer['answer_text'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <li class="<?php echo in_array($userAnswer, array_column(array_filter($detail['all_answers'], function($a) { return $a['is_correct']; }), 'id')) ? 'correct' : 'incorrect'; ?>">
                                        <?php echo htmlspecialchars($answerText); ?>
                                    </li>
                                <?php else: ?>
                                    <li class="<?php echo $detail['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                        <?php echo htmlspecialchars($userAnswer); ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Ответ не предоставлен</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
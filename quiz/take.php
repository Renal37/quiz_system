<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-functions.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) {
    redirect('../dashboard/');
}

$quiz = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$quiz->execute([$quizId]);
$quiz = $quiz->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    redirect('../dashboard/');
}

// Инициализация сессии теста
if (!isset($_SESSION['quiz_progress'][$quizId])) {
    $questions = $pdo->prepare("
        SELECT id FROM quiz_questions 
        WHERE quiz_id = ? 
        ORDER BY question_order ASC
    ");
    $questions->execute([$quizId]);
    $questionIds = $questions->fetchAll(PDO::FETCH_COLUMN);

    $_SESSION['quiz_progress'][$quizId] = [
        'start_time' => time(),
        'time_limit' => $quiz['time_limit'] ? $quiz['time_limit'] * 60 : null,
        'question_ids' => $questionIds,
        'current_question_index' => 0,
        'questions_answered' => 0,
        'answers' => [],
        'score' => 0,
        'active_bonus' => null
    ];
}

$progress = &$_SESSION['quiz_progress'][$quizId];

// Обработка использования бонуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_bonus'])) {
    $userBonusId = (int)$_POST['bonus_id'];
    $availableBonuses = getUserBonuses($pdo, $_SESSION['user_id'], $quizId);
    
    foreach ($availableBonuses as $bonus) {
        if ($bonus['user_bonus_id'] == $userBonusId) {
            $progress['active_bonus'] = $bonus;
            useBonus($pdo, $userBonusId);
            break;
        }
    }
    
    redirect("take.php?id=$quizId");
}

// Обработка ответа на вопрос
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $currentQuestionId = $progress['question_ids'][$progress['current_question_index']];
    $question = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
    $question->execute([$currentQuestionId]);
    $question = $question->fetch(PDO::FETCH_ASSOC);

    $userAnswers = $_POST['answers'] ?? [];
    if (!is_array($userAnswers)) {
        $userAnswers = [$userAnswers];
    }

    // Применяем активный бонус
    $result = processQuestionAnswer($pdo, $question, $userAnswers, $progress['active_bonus']);

    // Сохраняем результат
    $progress['answers'][$currentQuestionId] = [
        'user_answers' => $userAnswers,
        'is_correct' => $result['is_correct'],
        'points' => $result['points'],
        'used_bonus' => $progress['active_bonus']['code'] ?? null
    ];
    
    $progress['score'] += $result['points'];
    $progress['questions_answered']++;
    $progress['active_bonus'] = null; // Сбрасываем бонус после использования

    // Выдача нового бонуса каждые 10 вопросов
    if ($progress['questions_answered'] > 0 && $progress['questions_answered'] % 10 === 0) {
        $newBonus = giveBonusToUser($pdo, $_SESSION['user_id'], $quizId);
        $_SESSION['bonus_message'] = "Получен бонус: " . $newBonus['name'];
    }

    $progress['current_question_index']++;

    // Проверка завершения теста
    if ($progress['current_question_index'] >= count($progress['question_ids'])) {
        $resultId = saveQuizResult($pdo, $quizId, $_SESSION['user_id'], $progress);
        redirect("results.php?id=$resultId");
    }
    
    redirect("take.php?id=$quizId");
}

// Проверка времени
$timeLeft = null;
if ($progress['time_limit'] !== null) {
    $timeLeft = $progress['time_limit'] - (time() - $progress['start_time']);
    if ($timeLeft <= 0) {
        $resultId = saveQuizResult($pdo, $quizId, $_SESSION['user_id'], $progress);
        redirect("results.php?id=$resultId&timeout=1");
    }
}

// Получение текущего вопроса
$currentQuestionId = $progress['question_ids'][$progress['current_question_index']];
$question = $pdo->prepare("
    SELECT q.*, qi.image_path as image
    FROM quiz_questions q
    LEFT JOIN question_images qi ON q.id = qi.question_id
    WHERE q.id = ?
");
$question->execute([$currentQuestionId]);
$question = $question->fetch(PDO::FETCH_ASSOC);

// Получение вариантов ответов
$answers = [];
if ($question['question_type'] !== 'text') {
    $answers = $pdo->prepare("
        SELECT * FROM question_answers 
        WHERE question_id = ?
        ORDER BY id ASC
    ");
    $answers->execute([$question['id']]);
    $answers = $answers->fetchAll(PDO::FETCH_ASSOC);
}

// Применение бонуса 50/50 (если активен)
$filteredAnswers = $answers;
if (isset($progress['active_bonus']) && $progress['active_bonus']['code'] === 'fifty_fifty') {
    $filteredAnswers = applyFiftyFiftyBonus($answers);
}

// Применение бонуса дополнительного времени
if (isset($progress['active_bonus']) && $progress['active_bonus']['code'] === 'extra_time') {
    if ($progress['time_limit'] !== null) {
        $progress['time_limit'] += 30;
        $timeLeft += 30;
        $_SESSION['time_added'] = true;
    }
}

// Получение доступных бонусов
$availableBonuses = getUserBonuses($pdo, $_SESSION['user_id'], $quizId);

$pageTitle = "Прохождение теста: " . htmlspecialchars($quiz['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_SESSION['bonus_message'])): ?>
    <div class="bonus-notification">
        <?php echo htmlspecialchars($_SESSION['bonus_message']); ?>
        <button class="close-notification">&times;</button>
    </div>
    <?php unset($_SESSION['bonus_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['time_added'])): ?>
    <div class="time-added-message">
        Добавлено 30 секунд!
    </div>
    <?php unset($_SESSION['time_added']); ?>
<?php endif; ?>

<div class="quiz-container">
    <div class="bonuses-container">
        <h3>Доступные бонусы:</h3>
        <div class="bonuses-list">
            <?php if (empty($availableBonuses)): ?>
                <p>Нет доступных бонусов</p>
            <?php else: ?>
                <?php foreach ($availableBonuses as $bonus): ?>
                    <form method="post" class="bonus-form">
                        <input type="hidden" name="bonus_id" value="<?php echo $bonus['user_bonus_id']; ?>">
                        <button type="submit" name="use_bonus" class="bonus-btn <?php echo $bonus['code']; ?>"
                                data-bonus="<?php echo $bonus['code']; ?>"
                                onclick="return confirm('Использовать бонус \"<?php echo $bonus['name']; ?>\"?')">
                            <img src="../assets/images/bonuses/<?php echo $bonus['icon']; ?>.png" 
                                 alt="<?php echo $bonus['name']; ?>">
                            <span class="bonus-tooltip"><?php echo $bonus['description']; ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="quiz-header">
        <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
        <div class="quiz-progress">
            Вопрос <?php echo $progress['current_question_index'] + 1; ?> из <?php echo count($progress['question_ids']); ?>
        </div>
        <?php if ($progress['time_limit'] !== null): ?>
            <div class="quiz-timer" id="quiz-timer">
                Осталось времени: <span id="time-left"><?php echo gmdate("i:s", $timeLeft); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="question-container <?php echo isset($progress['active_bonus']) && $progress['active_bonus']['code'] === 'fifty_fifty' ? 'fifty-fifty-applied' : ''; ?>">
        <form action="take.php?id=<?php echo $quizId; ?>" method="post">
            <div class="question">
                <h3><?php echo htmlspecialchars($question['question_text']); ?></h3>
                
                <?php if (!empty($question['image'])): ?>
                    <div class="question-image">
                        <img src="../<?php echo htmlspecialchars($question['image']); ?>" alt="Изображение вопроса">
                    </div>
                <?php endif; ?>
                
                <div class="answers">
                    <?php if ($question['question_type'] === 'text'): ?>
                        <textarea name="answers[]" required></textarea>
                    <?php else: ?>
                        <?php foreach ($filteredAnswers as $answer): ?>
                            <div class="answer-option <?php echo $answer['is_correct'] ? 'correct-answer' : ''; ?>">
                                <?php if ($question['question_type'] === 'single'): ?>
                                    <input type="radio" name="answers[]" id="answer-<?php echo $answer['id']; ?>" 
                                           value="<?php echo $answer['id']; ?>" required>
                                <?php else: ?>
                                    <input type="checkbox" name="answers[]" id="answer-<?php echo $answer['id']; ?>" 
                                           value="<?php echo $answer['id']; ?>">
                                <?php endif; ?>
                                <label for="answer-<?php echo $answer['id']; ?>">
                                    <?php echo htmlspecialchars($answer['answer_text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="quiz-actions">
                <button type="submit" name="submit_answer" class="btn btn-primary">
                    <?php echo ($progress['current_question_index'] + 1 === count($progress['question_ids'])) ? 
                        'Завершить тест' : 'Следующий вопрос'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($progress['time_limit'] !== null): ?>
<script>
// Таймер обратного отсчета
let timeLeft = <?php echo $timeLeft; ?>;
    
const timer = setInterval(function() {
    timeLeft--;
    
    if (timeLeft <= 0) {
        clearInterval(timer);
        window.location.href = "results.php?id=<?php echo $quizId; ?>&timeout=1";
    }
    
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    document.getElementById('time-left').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}, 1000);
</script>
<?php endif; ?>

<script src="../assets/js/quiz-bonuses.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
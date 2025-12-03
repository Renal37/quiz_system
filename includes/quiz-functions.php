<?php
require_once __DIR__ . '/functions.php';

/**
 * Получает информацию о тесте
 */
function getQuiz($pdo, $quizId) {
    $stmt = $pdo->prepare("
        SELECT q.*, 
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
        FROM quizzes q
        WHERE q.id = ?
    ");
    $stmt->execute([$quizId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Получает количество вопросов в тесте
 */
function getQuestionCount($pdo, $quizId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    return $stmt->fetchColumn();
}

/**
 * Получает активную попытку прохождения теста
 */
function getActiveAttempt($pdo, $quizId, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM quiz_results 
        WHERE quiz_id = ? AND user_id = ? AND completed_at IS NULL
    ");
    $stmt->execute([$quizId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Создает новую попытку прохождения теста
 */
function createQuizAttempt($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT INTO quiz_results 
        (quiz_id, user_id, total_questions, started_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$data['quiz_id'], $data['user_id'], $data['total_questions']]);
    
    $attemptId = $pdo->lastInsertId();
    
    // Получаем полные данные о созданной попытке
    $stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE id = ?");
    $stmt->execute([$attemptId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Получает текущий вопрос для прохождения
 */
function getCurrentQuestion($pdo, $resultId) {
    // Получаем уже отвеченные вопросы
    $stmt = $pdo->prepare("
        SELECT question_id FROM user_answers 
        WHERE result_id = ?
    ");
    $stmt->execute([$resultId]);
    $answeredQuestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Получаем следующий неотвеченный вопрос
    $query = "
        SELECT qq.*, qi.image_path as image
        FROM quiz_questions qq
        LEFT JOIN question_images qi ON qq.id = qi.question_id
        WHERE qq.quiz_id = (
            SELECT quiz_id FROM quiz_results WHERE id = ?
        )
    ";
    
    if (!empty($answeredQuestions)) {
        $query .= " AND qq.id NOT IN (" . implode(',', array_fill(0, count($answeredQuestions), '?')) . ")";
    }
    
    $query .= " ORDER BY qq.question_order LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    
    $params = [$resultId];
    if (!empty($answeredQuestions)) {
        $params = array_merge($params, $answeredQuestions);
    }
    
    $stmt->execute($params);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        return null;
    }
    
    // Получаем варианты ответов (если вопрос не текстовый)
    if ($question['question_type'] !== 'text') {
        $stmt = $pdo->prepare("
            SELECT * FROM question_answers 
            WHERE question_id = ?
            ORDER BY id
        ");
        $stmt->execute([$question['id']]);
        $question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $question;
}

/**
 * Обрабатывает ответ на вопрос
 */
/**
 * Обрабатывает ответ пользователя на вопрос теста с учетом активных бонусов
 * 
 * @param PDO $pdo Объект подключения к базе данных
 * @param array $question Данные вопроса (id, question_text, question_type, points)
 * @param array $userAnswers Ответы пользователя (массив или строка)
 * @param array|null $activeBonus Активный бонус или null
 * @return array Результат проверки ['is_correct' => bool, 'points' => int]
 */

 function processQuestionAnswer(PDO $pdo, array $question, array $userAnswers, ?array $activeBonus= null ): array {
    $basePoints = $question['points'];
    $bonusType = $question['code'];
    $result = [
        'is_correct' => false,
        'points' => 0
    ];

    // Определяем множитель очков на основе бонуса
    $pointMultiplier = 1;
    if ($activeBonus !== null) {
        if (in_array($activeBonus['code'], ['double_points', 'double_danger'])) {
            $pointMultiplier = 2;
        }
    }
    
    // Логика проверки ответа (без изменений)
    switch ($question['question_type']) {
            case 'single':
                // Вопрос с одним правильным ответом
    
                // Получаем ID правильного ответа из базы
                $stmt = $pdo->prepare("
                    SELECT id FROM question_answers 
                    WHERE question_id = ? AND is_correct = 1
                    LIMIT 1
                ");
                $stmt->execute([$question['id']]);
                $correctAnswerId = $stmt->fetchColumn();
    
                // Проверяем ответ пользователя
                if (!empty($userAnswers)) {
                    // Приводим ответ пользователя к числу (на случай строкового значения)
                    $userAnswer = is_array($userAnswers) ? (int)$userAnswers[0] : (int)$userAnswers;
    
                    if ($userAnswer === (int)$correctAnswerId) {
                        // Правильный ответ
                        $result['is_correct'] = true;
                        $result['points'] = $question['points'] * $pointMultiplier;
                    } elseif ($activeBonus !== null && $activeBonus['code'] === 'double_danger') {
                        // Неправильный ответ с бонусом "Двойная опасность"
                        $result['points'] = -$question['points'] * $pointMultiplier;
                    }
                }
            break;
        case 'multiple':
            // Вопрос с несколькими правильными ответами

            // Получаем все правильные ответы
            $stmt = $pdo->prepare("
                SELECT id FROM question_answers 
                WHERE question_id = ? AND is_correct = 1
            ");
            $stmt->execute([$question['id']]);
            $correctAnswerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Нормализуем ответы пользователя (преобразуем в массив чисел)
            $userAnswerIds = [];
            if (!empty($userAnswers)) {
                $userAnswerIds = array_map('intval', (array)$userAnswers);
            }

            // Проверяем, что все правильные ответы выбраны и нет лишних
            $correctSelected = array_intersect($userAnswerIds, $correctAnswerIds);
            $incorrectSelected = array_diff($userAnswerIds, $correctAnswerIds);

            if (count($correctSelected) === count($correctAnswerIds) && empty($incorrectSelected)) {
                // Все правильно
                $result['is_correct'] = true;
                $result['points'] = $question['points'] * $pointMultiplier;
            } elseif ($activeBonus !== null && $activeBonus['code'] === 'double_danger') {
                // Неправильный ответ с бонусом "Двойная опасность"
                $result['points'] = -$question['points'] * $pointMultiplier;
            }
            break;
        case 'text':
            // Вопрос с текстовым ответом

            // Получаем правильный ответ из базы
            $stmt = $pdo->prepare("
                SELECT answer_text FROM question_answers 
                WHERE question_id = ? AND is_correct = 1
                LIMIT 1
            ");
            $stmt->execute([$question['id']]);
            $correctAnswerText = $stmt->fetchColumn();

            // Проверяем ответ пользователя (без учета регистра и лишних пробелов)
            if (!empty($userAnswers)) {
                $userAnswerText = is_array($userAnswers) ? $userAnswers[0] : $userAnswers;
                $normalizedUserAnswer = strtolower(trim($userAnswerText));
                $normalizedCorrectAnswer = strtolower(trim($correctAnswerText));

                if ($normalizedUserAnswer === $normalizedCorrectAnswer) {
                    // Правильный ответ
                    $result['is_correct'] = true;
                    $result['points'] = $question['points'] * $pointMultiplier;
                } elseif ($activeBonus !== null && $activeBonus['code'] === 'double_danger') {
                    // Неправильный ответ с бонусом "Двойная опасность"
                    $result['points'] = -$question['points'] * $pointMultiplier;
                }
            }
            break;
    }
    
    // Применяем эффекты бонусов
    if ($bonusType === 'double_points' && $result['is_correct']) {
        $result = applyDoublePoints($result, $basePoints);
    } 
    elseif ($bonusType === 'double_danger') {
        $result = applyDoubleDanger($result, $basePoints);
    }
    
    return $result;
}

/**
 * Вычисляет прогресс прохождения теста
 */
function calculateProgress($pdo, $resultId) {
    // Получаем общее количество вопросов
    $stmt = $pdo->prepare("
        SELECT total_questions FROM quiz_results 
        WHERE id = ?
    ");
    $stmt->execute([$resultId]);
    $totalQuestions = $stmt->fetchColumn();
    
    // Получаем количество отвеченных вопросов
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_answers 
        WHERE result_id = ?
    ");
    $stmt->execute([$resultId]);
    $answeredQuestions = $stmt->fetchColumn();
    
    return $answeredQuestions / $totalQuestions;
}

/**
 * Обновляет прогресс прохождения теста
 */
function updateQuizProgress($pdo, $resultId, $progress) {
    $stmt = $pdo->prepare("
        UPDATE quiz_results 
        SET progress = ?
        WHERE id = ?
    ");
    $stmt->execute([$progress, $resultId]);
}

/**
 * Завершает попытку прохождения теста
 */
function completeQuizAttempt($pdo, $resultId) {
    // Подсчитываем общий балл
    $stmt = $pdo->prepare("
        SELECT SUM(points_earned) FROM user_answers 
        WHERE result_id = ?
    ");
    $stmt->execute([$resultId]);
    $score = $stmt->fetchColumn();
    
    // Обновляем запись о результате
    $stmt = $pdo->prepare("
        UPDATE quiz_results 
        SET score = ?, completed_at = CURRENT_TIMESTAMP, progress = 1
        WHERE id = ?
    ");
    $stmt->execute([$score, $resultId]);
}

/**
 * Получает результат теста
 */
function getQuizResult($pdo, $resultId, $userId) {
    $stmt = $pdo->prepare("
        SELECT qr.*, q.title as quiz_title, q.time_limit,
        (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = qr.quiz_id AND user_id = ?) as attempts
        FROM quiz_results qr
        JOIN quizzes q ON qr.quiz_id = q.id
        WHERE qr.id = ? AND qr.user_id = ?
    ");
    $stmt->execute([$userId, $resultId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Получает детализированные результаты теста
 */
function getQuizResultDetails($pdo, $resultId) {
    // Получаем все вопросы теста с ответами пользователя
    $stmt = $pdo->prepare("
        SELECT 
            qq.id, qq.question_text, qq.question_type, qq.question_order, qq.points,
            ua.id as user_answer_id, ua.answer_text as user_answer_text, ua.is_correct, ua.points_earned,
            qi.image_path as image
        FROM quiz_questions qq
        LEFT JOIN user_answers ua ON qq.id = ua.question_id AND ua.result_id = ?
        LEFT JOIN question_images qi ON qq.id = qi.question_id
        WHERE qq.quiz_id = (SELECT quiz_id FROM quiz_results WHERE id = ?)
        ORDER BY qq.question_order
    ");
    $stmt->execute([$resultId, $resultId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Дополняем данные для каждого вопроса
    foreach ($questions as &$question) {
        if ($question['question_type'] !== 'text') {
            // Получаем ответы пользователя
            $stmt = $pdo->prepare("
                SELECT qa.id, qa.answer_text, qa.is_correct
                FROM user_answer_selections uas
                JOIN question_answers qa ON uas.answer_id = qa.id
                WHERE uas.user_answer_id = ?
            ");
            $stmt->execute([$question['user_answer_id']]);
            $question['user_answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Получаем правильные ответы
            $stmt = $pdo->prepare("
                SELECT answer_text 
                FROM question_answers 
                WHERE question_id = ? AND is_correct = 1
            ");
            $stmt->execute([$question['id']]);
            $question['correct_answers'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Для текстовых вопросов получаем правильный ответ (если есть)
            $stmt = $pdo->prepare("
                SELECT answer_text 
                FROM question_answers 
                WHERE question_id = ? AND is_correct = 1
                LIMIT 1
            ");
            $stmt->execute([$question['id']]);
            $question['correct_answer_text'] = $stmt->fetchColumn();
        }
    }
    
    return $questions;
}

function saveQuizResult(PDO $pdo, int $quizId, int $userId, array $progress) {
    try {
        $pdo->beginTransaction();
        
        // Получаем максимальное количество баллов (сумма баллов за все вопросы)
        $stmt = $pdo->prepare("
            SELECT SUM(points) 
            FROM quiz_questions 
            WHERE quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        $maxScore = $stmt->fetchColumn();
        
        // Сохраняем основной результат
        $stmt = $pdo->prepare("
            INSERT INTO quiz_results 
            (quiz_id, user_id, score, max_score, total_questions, completed_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $quizId,
            $userId,
            $progress['score'],
            $maxScore,
            count($progress['question_ids'])
        ]);
        $resultId = $pdo->lastInsertId();
        
        // Сохраняем детали по каждому вопросу
        $stmt = $pdo->prepare("
            INSERT INTO quiz_result_details 
            (result_id, question_id, user_answers, is_correct, points) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($progress['answers'] as $questionId => $answer) {
            // Преобразуем ответы пользователя в строку JSON
            $userAnswers = is_array($answer['user_answers']) ? 
                json_encode($answer['user_answers']) : $answer['user_answers'];
            
            $stmt->execute([
                $resultId,
                $questionId,
                $userAnswers,
                $answer['is_correct'] ? 1 : 0,
                $answer['points']
            ]);
        }
        
        $pdo->commit();
        
        // Очищаем прогресс теста
        unset($_SESSION['quiz_progress'][$quizId]);
        
        return $resultId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Функция для получения случайного бонуса
function getRandomBonus(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM bonuses ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Функция для выдачи бонуса пользователю
function giveBonusToUser(PDO $pdo, int $userId, int $quizId): array 
{
    try {
        $pdo->beginTransaction();
        
        // Удаляем неиспользованные бонусы
        $pdo->prepare("DELETE FROM user_bonuses WHERE user_id = ? AND quiz_id = ? AND is_used = FALSE")
            ->execute([$userId, $quizId]);
        
        // Получаем случайный бонус (исключая уже использованные в этом тесте)
        $stmt = $pdo->prepare("
            SELECT b.* 
            FROM bonuses b
            WHERE b.id NOT IN (
                SELECT ub.bonus_id 
                FROM user_bonuses ub 
                WHERE ub.user_id = ? AND ub.quiz_id = ? AND ub.is_used = TRUE
            )
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute([$userId, $quizId]);
        $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Если все бонусы уже использовались, выбираем любой
        if (!$bonus) {
            $stmt = $pdo->query("SELECT * FROM bonuses ORDER BY RAND() LIMIT 1");
            $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Выдаем бонус пользователю
        $stmt = $pdo->prepare("
            INSERT INTO user_bonuses (user_id, bonus_id, quiz_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $bonus['id'], $quizId]);
        
        $pdo->commit();
        return $bonus;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Ошибка при выдаче бонуса: " . $e->getMessage());
    }
}

// Функция для проверки бонусов пользователя
function getUserBonuses(PDO $pdo, int $userId, int $quizId) {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.id as user_bonus_id 
        FROM user_bonuses ub
        JOIN bonuses b ON ub.bonus_id = b.id
        WHERE ub.user_id = ? AND ub.quiz_id = ? AND ub.is_used = FALSE
    ");
    $stmt->execute([$userId, $quizId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для применения бонуса 50/50
function applyFiftyFifty(PDO $pdo, int $questionId, array $answers) {
    // Получаем правильные ответы
    $correctAnswers = array_filter($answers, function($a) { return $a['is_correct']; });
    $incorrectAnswers = array_filter($answers, function($a) { return !$a['is_correct']; });
    
    // Оставляем 1 правильный и 1 неправильный ответ
    shuffle($incorrectAnswers);
    $incorrectAnswers = array_slice($incorrectAnswers, 0, 1);
    
    return array_merge($correctAnswers, $incorrectAnswers);
}

// Функция для использования бонуса
function useBonus(PDO $pdo, int $userBonusId) {
    $pdo->prepare("UPDATE user_bonuses SET is_used = TRUE WHERE id = ?")
        ->execute([$userBonusId]);
}

function applyFiftyFiftyBonus(array $answers): array {
    $correctAnswers = array_filter($answers, function($a) { return $a['is_correct']; });
    $incorrectAnswers = array_filter($answers, function($a) { return !$a['is_correct']; });
    
    // Оставляем 1 правильный и 1 неправильный ответ
    shuffle($incorrectAnswers);
    return array_merge(
        $correctAnswers,
        array_slice($incorrectAnswers, 0, 1)
    );
}

function applyDoublePoints(array $result, int $basePoints): array {
    $result['points'] = $basePoints * 2;
    return $result;
}

function applyDoubleDanger(array $result, int $basePoints): array {
    $result['points'] = $result['is_correct'] ? $basePoints * 2 : -$basePoints * 2;
    return $result;
}
?>
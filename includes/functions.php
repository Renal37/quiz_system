<?php
require_once 'config.php';

// Функция для проверки авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Функция для получения данных пользователя
function getUserData($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT u.*, up.full_name, up.avatar FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Функция для проверки существования пользователя
function userExists($pdo, $username, $email) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    return $stmt->fetch() ? true : false;
}

// Функция для регистрации нового пользователя
function registerUser($pdo, $username, $email, $password, $role = 'student') {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $role]);
    
    $userId = $pdo->lastInsertId();
    
    // Создаем профиль пользователя
    $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    
    return $userId;
}

// Функция для авторизации пользователя
function loginUser($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    
    return false;
}

// Функция для обновления времени последнего входа
function updateLastLogin($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$userId]);
}

// Функция для получения созданных тестов пользователя
function getUserQuizzes($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для получения результатов тестов пользователя
function getUserQuizResults($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT qr.*, q.title 
        FROM quiz_results qr
        JOIN quizzes q ON qr.quiz_id = q.id
        WHERE qr.user_id = ?
        ORDER BY qr.completed_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Получает тип вопроса в читаемом формате

function getQuestionTypeName($type) {
   $types = [
       'single' => 'Один ответ',
       'multiple' => 'Несколько ответов',
       'text' => 'Текстовый ответ'
   ];
   return $types[$type] ?? $type;
}

/**
* Получает данные вопроса с ответами
*/
function getQuestionWithAnswers($pdo, $questionId) {
   // Получаем сам вопрос
   $stmt = $pdo->prepare("
       SELECT q.*, qi.image_path as image
       FROM quiz_questions q
       LEFT JOIN question_images qi ON q.id = qi.question_id
       WHERE q.id = ?
   ");
   $stmt->execute([$questionId]);
   $question = $stmt->fetch(PDO::FETCH_ASSOC);
   
   if (!$question) {
       return null;
   }
   
   // Получаем ответы (если вопрос не текстовый)
   if ($question['question_type'] !== 'text') {
       $stmt = $pdo->prepare("SELECT * FROM question_answers WHERE question_id = ?");
       $stmt->execute([$questionId]);
       $question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
   }
   
   return $question;
}

/**
* Сохраняет вопрос в базу данных
*/
function saveQuestionToDatabase($pdo, $data) {
   try {
       $pdo->beginTransaction();
       
       // Сохраняем основной вопрос
       if (!empty($data['question_id'])) {
           // Обновление существующего вопроса
           $stmt = $pdo->prepare("
               UPDATE quiz_questions 
               SET question_text = ?, question_type = ?, points = ?
               WHERE id = ?
           ");
           $stmt->execute([
               $data['question_text'],
               $data['question_type'],
               $data['points'],
               $data['question_id']
           ]);
           $questionId = $data['question_id'];
       } else {
           // Создание нового вопроса
           // Сначала получаем максимальный порядковый номер
           $stmt = $pdo->prepare("
               SELECT MAX(question_order) FROM quiz_questions 
               WHERE quiz_id = ?
           ");
           $stmt->execute([$data['quiz_id']]);
           $order = $stmt->fetchColumn() + 1;
           
           $stmt = $pdo->prepare("
               INSERT INTO quiz_questions 
               (quiz_id, question_text, question_type, points, question_order)
               VALUES (?, ?, ?, ?, ?)
           ");
           $stmt->execute([
               $data['quiz_id'],
               $data['question_text'],
               $data['question_type'],
               $data['points'],
               $order
           ]);
           $questionId = $pdo->lastInsertId();
       }
       
       // Обработка изображения
       if (!empty($data['question_image'])) {
           // Удаляем старое изображение, если есть
           $stmt = $pdo->prepare("SELECT image_path FROM question_images WHERE question_id = ?");
           $stmt->execute([$questionId]);
           $oldImage = $stmt->fetchColumn();
           
           if ($oldImage && file_exists('../' . $oldImage)) {
               unlink('../' . $oldImage);
           }
           
           // Загружаем новое изображение
           $uploadDir = '../uploads/question_images/';
           if (!file_exists($uploadDir)) {
               mkdir($uploadDir, 0777, true);
           }
           
           $fileExt = pathinfo($data['question_image']['name'], PATHINFO_EXTENSION);
           $fileName = 'question_' . $questionId . '_' . time() . '.' . $fileExt;
           $uploadPath = $uploadDir . $fileName;
           
           if (move_uploaded_file($data['question_image']['tmp_name'], $uploadPath)) {
               // Обновляем запись в базе
               $pdo->prepare("DELETE FROM question_images WHERE question_id = ?")->execute([$questionId]);
               $pdo->prepare("
                   INSERT INTO question_images (question_id, image_path)
                   VALUES (?, ?)
               ")->execute([$questionId, 'uploads/question_images/' . $fileName]);
           }
       }
       
       // Обработка ответов (только для не текстовых вопросов)
       if ($data['question_type'] !== 'text' && !empty($data['answers'])) {
           // Удаляем старые ответы
           $pdo->prepare("DELETE FROM question_answers WHERE question_id = ?")->execute([$questionId]);
           
           // Добавляем новые ответы
           $stmt = $pdo->prepare("
               INSERT INTO question_answers 
               (question_id, answer_text, is_correct)
               VALUES (?, ?, ?)
           ");
           
           foreach ($data['answers'] as $answer) {
               $stmt->execute([
                   $questionId,
                   $answer['text'],
                   $answer['isCorrect'] ? 1 : 0
               ]);
           }
       }
       
       $pdo->commit();
       return ['success' => true, 'question_id' => $questionId];
   } catch (Exception $e) {
       $pdo->rollBack();
       return ['success' => false, 'message' => $e->getMessage()];
   }
}

/**
* Удаляет вопрос из базы данных
*/
function deleteQuestionFromDatabase($pdo, $questionId) {
   try {
       $pdo->beginTransaction();
       
       // Получаем изображение для удаления
       $stmt = $pdo->prepare("SELECT image_path FROM question_images WHERE question_id = ?");
       $stmt->execute([$questionId]);
       $imagePath = $stmt->fetchColumn();
       
       // Удаляем вопрос (каскадно удалятся ответы и связи)
       $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ?");
       $stmt->execute([$questionId]);
       
       // Удаляем изображение, если есть
       if ($imagePath && file_exists('../' . $imagePath)) {
           unlink('../' . $imagePath);
       }
       
       $pdo->commit();
       return ['success' => true];
   } catch (Exception $e) {
       $pdo->rollBack();
       return ['success' => false, 'message' => $e->getMessage()];
   }
}

?>


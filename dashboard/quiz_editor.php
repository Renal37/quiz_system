<?php
$pageTitle = "Редактор теста";
require_once __DIR__ . '/../includes/header.php';

// Проверяем, является ли пользователь преподавателем
if ($userData['role'] !== 'teacher' && $userData['role'] !== 'admin') {
    redirect('../dashboard/');
}

// Проверяем ID теста
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) {
    redirect('my_quizzes.php');
}

// Проверяем, что тест принадлежит пользователю
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND created_by = ?");
$stmt->execute([$quizId, $_SESSION['user_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    redirect('my_quizzes.php');
}

// Получаем вопросы теста
$questions = $pdo->prepare("
    SELECT q.*, 
    (SELECT COUNT(*) FROM question_answers WHERE question_id = q.id) as answer_count
    FROM quiz_questions q
    WHERE q.quiz_id = ?
    ORDER BY q.question_order ASC
");
$questions->execute([$quizId]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);

// Получаем категории теста
$quizCategories = $pdo->prepare("
    SELECT c.id, c.name 
    FROM quiz_category_relations r
    JOIN quiz_categories c ON r.category_id = c.id
    WHERE r.quiz_id = ?
");
$quizCategories->execute([$quizId]);
$quizCategories = $quizCategories->fetchAll(PDO::FETCH_ASSOC);

// Получаем теги теста
$quizTags = $pdo->prepare("
    SELECT t.id, t.name 
    FROM quiz_tag_relations r
    JOIN quiz_tags t ON r.tag_id = t.id
    WHERE r.quiz_id = ?
");
$quizTags->execute([$quizId]);
$quizTags = $quizTags->fetchAll(PDO::FETCH_ASSOC);

// Обработка обновления теста
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $timeLimit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Название теста обязательно";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Обновляем тест
            $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, is_published = ?, time_limit = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $description, $isPublished, $timeLimit, $quizId]);
            
            // Обновляем категории
            $pdo->prepare("DELETE FROM quiz_category_relations WHERE quiz_id = ?")->execute([$quizId]);
            if (!empty($_POST['categories'])) {
                $stmt = $pdo->prepare("INSERT INTO quiz_category_relations (quiz_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $categoryId) {
                    $stmt->execute([$quizId, (int)$categoryId]);
                }
            }
            
            // Обновляем теги
            $pdo->prepare("DELETE FROM quiz_tag_relations WHERE quiz_id = ?")->execute([$quizId]);
            if (!empty($_POST['tags'])) {
                $stmtSelect = $pdo->prepare("SELECT id FROM quiz_tags WHERE name = ?");
                $stmtInsert = $pdo->prepare("INSERT INTO quiz_tags (name) VALUES (?)");
                $stmtRelate = $pdo->prepare("INSERT INTO quiz_tag_relations (quiz_id, tag_id) VALUES (?, ?)");
                
                foreach ($_POST['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    $stmtSelect->execute([$tagName]);
                    $tagId = $stmtSelect->fetchColumn();
                    
                    if (!$tagId) {
                        $stmtInsert->execute([$tagName]);
                        $tagId = $pdo->lastInsertId();
                    }
                    
                    $stmtRelate->execute([$quizId, $tagId]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Тест успешно обновлен!";
            redirect("quiz_editor.php?id=$quizId");
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка при обновлении теста: " . $e->getMessage();
        }
    }
}

// Получаем список категорий для выбора
$categories = $pdo->query("SELECT * FROM quiz_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="quiz-editor-page">
    <div class="editor-header">
        <h2>Редактор теста: <?php echo htmlspecialchars($quiz['title']); ?></h2>
        <div class="quiz-status">
            Статус: <span class="<?php echo $quiz['is_published'] ? 'published' : 'draft'; ?>">
                <?php echo $quiz['is_published'] ? 'Опубликован' : 'Черновик'; ?>
            </span>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="editor-tabs">
        <button class="tab-btn active" data-tab="settings">Настройки теста</button>
        <button class="tab-btn" data-tab="questions">Вопросы</button>
        <button class="tab-btn" data-tab="preview">Предпросмотр</button>
    </div>
    
    <div class="tab-content active" id="settings-tab">
        <form action="" method="post" class="quiz-form">
            <div class="form-section">
                <h3>Основная информация</h3>
                
                <div class="form-group">
                    <label for="title">Название теста *</label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($quiz['title']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Описание теста</label>
                    <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="time_limit">Лимит времени (минут)</label>
                        <input type="number" id="time_limit" name="time_limit" min="1" value="<?php echo $quiz['time_limit']; ?>">
                        <small>Оставьте пустым, если ограничения нет</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_published" value="1" <?php echo $quiz['is_published'] ? 'checked' : ''; ?>>
                            <span>Опубликовать</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Категории</h3>
                <div class="categories-select">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>"
                                    <?php echo in_array($category['id'], array_column($quizCategories, 'id')) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Нет доступных категорий</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Теги</h3>
                <div class="form-group">
                    <label for="tags">Теги (через запятую)</label>
                    <input type="text" id="tags" name="tags" placeholder="математика, физика, 10 класс" 
                           value="<?php echo htmlspecialchars(implode(', ', array_column($quizTags, 'name'))); ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_quiz" class="btn btn-primary">Сохранить изменения</button>
            </div>
        </form>
    </div>
    
    <div class="tab-content" id="questions-tab">
        <div class="questions-actions">
            <button class="btn btn-primary" id="add-question-btn">Добавить вопрос</button>
            <button class="btn btn-secondary" id="reorder-questions-btn">Изменить порядок</button>
        </div>
        
        <div class="questions-list">
            <?php if (empty($questions)): ?>
                <div class="alert alert-info">
                    <p>В этом тесте пока нет вопросов. Нажмите "Добавить вопрос", чтобы начать.</p>
                </div>
            <?php else: ?>
                <?php foreach ($questions as $question): ?>
                    <div class="question-card" data-question-id="<?php echo $question['id']; ?>">
                        <div class="question-header">
                            <h4>Вопрос #<?php echo $question['question_order']; ?></h4>
                            <div class="question-meta">
                                <span>Тип: <?php echo getQuestionTypeName($question['question_type']); ?></span>
                                <span>Баллы: <?php echo $question['points']; ?></span>
                                <span>Ответов: <?php echo $question['answer_count']; ?></span>
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                        </div>
                        
                        <div class="question-actions">
                            <a href="#" class="btn btn-edit edit-question-btn">Редактировать</a>
                            <a href="#" class="btn btn-delete delete-question-btn">Удалить</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="tab-content" id="preview-tab">
        <div class="preview-container">
            <p>Предпросмотр теста будет доступен здесь</p>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования вопроса -->
<div class="modal" id="question-modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3 id="modal-title">Добавить вопрос</h3>
        
        <form id="question-form">
            <input type="hidden" id="question-id" value="">
            <input type="hidden" id="quiz-id" value="<?php echo $quizId; ?>">
            
            <div class="form-group">
                <label for="question-text">Текст вопроса *</label>
                <textarea id="question-text" required rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="question-type">Тип вопроса *</label>
                    <select id="question-type" required>
                        <option value="single">Один правильный ответ</option>
                        <option value="multiple">Несколько правильных ответов</option>
                        <option value="text">Текстовый ответ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="question-points">Баллы *</label>
                    <input type="number" id="question-points" min="1" value="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="question-image">Изображение (опционально)</label>
                <input type="file" id="question-image" accept="image/*">
                <div id="image-preview"></div>
            </div>
            
            <div id="answers-section">
                <h4>Варианты ответов</h4>
                <div id="answers-list">
                    <!-- Ответы будут добавляться здесь динамически -->
                </div>
                <button type="button" id="add-answer-btn" class="btn btn-secondary">Добавить ответ</button>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить вопрос</button>
                <button type="button" class="btn btn-cancel close-modal">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/quiz-builder.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = "Создание теста";
require_once __DIR__ . '/../includes/header.php';

// Проверяем, является ли пользователь преподавателем
if ($userData['role'] !== 'teacher' && $userData['role'] !== 'admin') {
    redirect('../dashboard/');
}

// Обработка создания теста
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
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
            
            // Создаем тест
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, created_by, is_published, time_limit) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $_SESSION['user_id'], $isPublished, $timeLimit]);
            
            $quizId = $pdo->lastInsertId();
            
            // Обрабатываем категории
            if (!empty($_POST['categories'])) {
                $stmt = $pdo->prepare("INSERT INTO quiz_category_relations (quiz_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $categoryId) {
                    $stmt->execute([$quizId, (int)$categoryId]);
                }
            }
            
            // Обрабатываем теги
            if (!empty($_POST['tags'])) {
                // Сначала проверяем существование тегов и создаем новые при необходимости
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
            
            $_SESSION['success_message'] = "Тест успешно создан! Теперь вы можете добавить вопросы.";
            redirect("quiz_editor.php?id=$quizId");
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка при создании теста: " . $e->getMessage();
        }
    }
}

// Получаем список категорий для выбора
$categories = $pdo->query("SELECT * FROM quiz_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="quiz-builder-page">
    <h2>Создание нового теста</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form action="" method="post" class="quiz-form">
        <div class="form-section">
            <h3>Основная информация</h3>
            
            <div class="form-group">
                <label for="title">Название теста *</label>
                <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Описание теста</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="time_limit">Лимит времени (минут)</label>
                    <input type="number" id="time_limit" name="time_limit" min="1" value="<?php echo htmlspecialchars($_POST['time_limit'] ?? ''); ?>">
                    <small>Оставьте пустым, если ограничения нет</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_published" value="1" <?php echo isset($_POST['is_published']) ? 'checked' : ''; ?>>
                        <span>Опубликовать сразу</span>
                    </label>
                    <small>Если не отмечено, тест сохранится как черновик</small>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Категории</h3>
            <div class="categories-select">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>">
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
                <label for="tags">Добавьте теги (через запятую)</label>
                <input type="text" id="tags" name="tags" placeholder="математика, физика, 10 класс" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                <small>Теги помогают находить тест в поиске</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="create_quiz" class="btn btn-primary">Создать тест</button>
            <a href="my_quizzes.php" class="btn btn-cancel">Отмена</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = "Мои тесты";
require_once __DIR__ . '/../includes/header.php';

// Проверяем, является ли пользователь преподавателем
if ($userData['role'] !== 'teacher' && $userData['role'] !== 'admin') {
    redirect('../dashboard/');
}

// Удаление теста
if (isset($_GET['delete'])) {
    $quizId = (int)$_GET['delete'];
    
    // Проверяем, что тест принадлежит пользователю
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
    $stmt->execute([$quizId, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$quizId]);
        $_SESSION['success_message'] = "Тест успешно удален!";
    }
    
    redirect('my_quizzes.php');
}

// Получаем все тесты пользователя
$quizzes = getUserQuizzes($pdo, $_SESSION['user_id']);
?>

<div class="quizzes-page">
    <div class="page-header">
        <h2>Мои тесты</h2>
        <a href="quiz_builder.php" class="btn btn-primary">Создать новый тест</a>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($quizzes)): ?>
        <div class="alert alert-info">
            <p>У вас пока нет созданных тестов. Нажмите "Создать новый тест", чтобы начать.</p>
        </div>
    <?php else: ?>
        <div class="quizzes-list">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="quiz-card">
                    <div class="quiz-info">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p><?php echo htmlspecialchars($quiz['description'] ?? 'Без описания'); ?></p>
                        <div class="quiz-meta">
                            <span>Статус: <?php echo $quiz['is_published'] ? 'Опубликован' : 'Черновик'; ?></span>
                            <span>Создан: <?php echo date('d.m.Y', strtotime($quiz['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="quiz-actions">
                        <a href="quiz_editor.php?id=<?php echo $quiz['id']; ?>" class="btn btn-edit">Редактировать</a>
                        <a href="?delete=<?php echo $quiz['id']; ?>" class="btn btn-delete" onclick="return confirm('Вы уверены, что хотите удалить этот тест?')">Удалить</a>
                        <a href="#" class="btn btn-preview">Предпросмотр</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Получаем все результаты пользователя
$results = $pdo->prepare("
    SELECT r.*, q.title as quiz_title, q.description as quiz_description, 
           q.time_limit, u.username as creator_name
    FROM quiz_results r
    JOIN quizzes q ON r.quiz_id = q.id
    JOIN users u ON q.created_by = u.id
    WHERE r.user_id = ?
    ORDER BY r.completed_at DESC
");
$results->execute([$_SESSION['user_id']]);
$results = $results->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Мои результаты тестов";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Мои результаты тестов</h1>
    
    <?php if (empty($results)): ?>
        <div class="alert alert-info">
            Вы пока не прошли ни одного теста.
        </div>
    <?php else: ?>
        <div class="results-list">
            <?php foreach ($results as $result): ?>
                <a href="../quiz/quiz_result_detail.php?id=<?php echo $result['id']; ?>" class="result-card">
                    <div class="result-card-header">
                        <h3><?php echo htmlspecialchars($result['quiz_title']); ?></h3>
                        <span class="completion-date">
                            <?php echo date('d.m.Y H:i', strtotime($result['completed_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="result-card-body">
                        <div class="result-score">
                            <span class="score"><?php echo $result['score']; ?></span>
                            <span class="divider">/</span>
                            <span class="max-score"><?php echo $result['max_score']; ?></span>
                            <span class="score-label">баллов</span>
                        </div>
                        
                        <div class="result-percentage">
                            <?php echo round($result['score'] / $result['max_score'] * 100); ?>%
                        </div>
                        
                        <div class="result-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i> Создатель: <?php echo htmlspecialchars($result['creator_name']); ?>
                            </span>
                            <?php if ($result['time_limit']): ?>
                                <span class="meta-item">
                                    <i class="fas fa-clock"></i> Лимит: <?php echo $result['time_limit']; ?> мин
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
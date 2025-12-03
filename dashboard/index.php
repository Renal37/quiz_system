<?php
$pageTitle = "Личный кабинет";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-summary">
        <h2>Добро пожаловать, <?php echo htmlspecialchars($userData['full_name'] ?? $userData['username']); ?>!</h2>
        
        <div class="stats-cards">
            <?php if ($userData['role'] === 'teacher' || $userData['role'] === 'admin'): ?>
                <div class="stat-card">
                    <h3>Созданные тесты</h3>
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE created_by = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $quizCount = $stmt->fetchColumn();
                    ?>
                    <p class="stat-value"><?php echo $quizCount; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <h3>Пройденные тесты</h3>
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_results WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $resultsCount = $stmt->fetchColumn();
                ?>
                <p class="stat-value"><?php echo $resultsCount; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Последний вход</h3>
                <p class="stat-value">
                    <?php 
                    if ($userData['last_login']) {
                        echo date('d.m.Y H:i', strtotime($userData['last_login']));
                    } else {
                        echo 'Первый вход';
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="recent-activities">
        <h3>Последняя активность</h3>
        <div class="activity-list">
            <?php
            $stmt = $pdo->prepare("
                SELECT q.title, r.score, r.max_score, r.completed_at 
                FROM quiz_results r
                JOIN quizzes q ON r.quiz_id = q.id
                WHERE r.user_id = ?
                ORDER BY r.completed_at DESC
                LIMIT 5
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($activities):
                foreach ($activities as $activity):
            ?>
                <div class="activity-item">
                    <p>
                        <strong><?php echo htmlspecialchars($activity['title']); ?></strong> - 
                        <?php echo $activity['score']; ?> из <?php echo $activity['max_score']; ?> баллов
                    </p>
                    <small><?php echo date('d.m.Y H:i', strtotime($activity['completed_at'])); ?></small>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <p>Пока нет активности</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Получаем все категории для фильтра
$categories = $pdo->query("SELECT id, name FROM quiz_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Получаем все теги для фильтра
$tags = $pdo->query("SELECT id, name FROM quiz_tags ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Обработка параметров фильтрации и поиска
$searchQuery = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$tagFilter = $_GET['tag'] ?? '';

// Базовый запрос
$query = "
    SELECT q.*, 
    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
    (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id AND user_id = ?) as attempts,
    GROUP_CONCAT(DISTINCT c.name) as categories,
    GROUP_CONCAT(DISTINCT t.name) as tags
    FROM quizzes q
    LEFT JOIN quiz_category_relations qcr ON q.id = qcr.quiz_id
    LEFT JOIN quiz_categories c ON qcr.category_id = c.id
    LEFT JOIN quiz_tag_relations qtr ON q.id = qtr.quiz_id
    LEFT JOIN quiz_tags t ON qtr.tag_id = t.id
    WHERE q.is_published = 1
";

$params = [$_SESSION['user_id']];

// Добавляем условия поиска
if (!empty($searchQuery)) {
    $query .= " AND (q.title LIKE ? OR q.description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    array_push($params, $searchTerm, $searchTerm);
}

// Добавляем фильтр по категории
if (!empty($categoryFilter)) {
    $query .= " AND qcr.category_id = ?";
    array_push($params, $categoryFilter);
}

// Добавляем фильтр по тегу
if (!empty($tagFilter)) {
    $query .= " AND qtr.tag_id = ?";
    array_push($params, $tagFilter);
}

// Завершаем запрос
$query .= " GROUP BY q.id ORDER BY q.title";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Доступные тесты";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="quiz-start-page">
    <h2>Доступные тесты</h2>
    
    <!-- Форма поиска и фильтрации -->
    <div class="quiz-filters">
        <form method="get" class="filter-form">
            <div class="form-row">
                <div class="form-group search-group">
                    <input type="text" name="search" placeholder="Поиск по названию или описанию" 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="search-btn">🔍</button>
                </div>
                
                <div class="form-group">
                    <select name="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo $category['id'] == $categoryFilter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <select name="tag">
                        <option value="">Все теги</option>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>" 
                                <?php echo $tag['id'] == $tagFilter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-filter">Применить</button>
                <?php if (!empty($searchQuery) || !empty($categoryFilter) || !empty($tagFilter)): ?>
                    <a href="start.php" class="btn btn-reset">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if (empty($quizzes)): ?>
        <div class="alert alert-info">
            <p>По вашему запросу тестов не найдено.</p>
        </div>
    <?php else: ?>
        <div class="quizzes-grid">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="quiz-card">
                    <div class="quiz-card-header">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <?php if ($quiz['time_limit']): ?>
                            <span class="time-limit">⏱ <?php echo $quiz['time_limit']; ?> мин</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="quiz-card-body">
                        <p><?php echo htmlspecialchars($quiz['description'] ?? 'Без описания'); ?></p>
                        
                        <div class="quiz-meta">
                            <span>Вопросов: <?php echo $quiz['question_count']; ?></span>
                            <span>Попыток: <?php echo $quiz['attempts']; ?></span>
                        </div>
                        
                        <?php if (!empty($quiz['categories'])): ?>
                            <div class="quiz-categories">
                                <strong>Категории:</strong>
                                <?php 
                                $cats = explode(',', $quiz['categories']);
                                foreach ($cats as $cat): ?>
                                    <span class="category-tag"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($quiz['tags'])): ?>
                            <div class="quiz-tags">
                                <strong>Теги:</strong>
                                <?php 
                                $tags = explode(',', $quiz['tags']);
                                foreach ($tags as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="quiz-card-footer">
                        <a href="take.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                            <?php echo $quiz['attempts'] > 0 ? 'Попробовать снова' : 'Начать тест'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
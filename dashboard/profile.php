<?php
$pageTitle = "Мой профиль";
require_once __DIR__ . '/../includes/header.php';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    
    // Обработка загрузки аватара
    $avatar = $userData['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExt;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
            // Удаляем старый аватар, если он существует
            if ($avatar && file_exists('../' . $avatar)) {
                unlink('../' . $avatar);
            }
            $avatar = 'uploads/avatars/' . $fileName;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE user_profiles SET full_name = ?, bio = ?, avatar = ? WHERE user_id = ?");
    $stmt->execute([$full_name, $bio, $avatar, $_SESSION['user_id']]);
    
    $_SESSION['success_message'] = "Профиль успешно обновлен!";
    redirect('profile.php');
}

// Получаем актуальные данные профиля
$stmt = $pdo->prepare("SELECT full_name, bio, avatar FROM user_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="profile-page">
    <h2>Мой профиль</h2>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="profile-form">
        <form action="" method="post" enctype="multipart/form-data">
            <div class="avatar-section">
                <div class="avatar-preview">
                    <?php if (!empty($profile['avatar'])): ?>
                        <img src="../<?php echo htmlspecialchars($profile['avatar']); ?>" alt="Аватар">
                    <?php else: ?>
                        <div class="avatar-placeholder">Нет аватара</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="avatar">Изменить аватар</label>
                    <input type="file" id="avatar" name="avatar" accept="image/*">
                </div>
            </div>
            
            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($userData['username']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label for="full_name">Полное имя</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="bio">О себе</label>
                <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary">Сохранить изменения</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
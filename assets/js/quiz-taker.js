document.addEventListener('DOMContentLoaded', function() {
    // Таймер для теста с ограничением по времени
    const timerElement = document.getElementById('quiz-timer');
    if (timerElement) {
        const timeLimit = parseInt(timerElement.dataset.timeLimit) * 60; // в секундах
        const startedAt = parseInt(timerElement.dataset.startedAt);
        const now = Math.floor(Date.now() / 1000);
        const elapsed = now - startedAt;
        let remainingTime = timeLimit - elapsed;
        
        // Если время вышло, перенаправляем на страницу результата
        if (remainingTime <= 0) {
            window.location.href = window.location.href.replace('take.php', 'result.php');
            return;
        }
        
        // Обновляем отображение таймера
        function updateTimerDisplay() {
            const hours = Math.floor(remainingTime / 3600);
            const minutes = Math.floor((remainingTime % 3600) / 60);
            const seconds = remainingTime % 60;
            
            document.getElementById('time-display').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateTimerDisplay();
        
        // Запускаем таймер
        const timer = setInterval(function() {
            remainingTime--;
            updateTimerDisplay();
            
            if (remainingTime <= 0) {
                clearInterval(timer);
                window.location.href = window.location.href.replace('take.php', 'result.php');
            }
        }, 1000);
    }
    
    // Подтверждение перед завершением теста
    const quizForm = document.querySelector('.quiz-question-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function(e) {
            const submitBtn = e.submitter;
            if (submitBtn.name === 'submit_answer' && submitBtn.textContent.includes('Завершить')) {
                if (!confirm('Вы уверены, что хотите завершить тест?')) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // Обработка нажатия клавиши Escape для отмены (если есть модальные окна)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal[style="display: block;"]');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    });
});
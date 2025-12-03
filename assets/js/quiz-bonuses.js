document.addEventListener('DOMContentLoaded', function() {
    // Обработка бонусов
    const bonusForms = document.querySelectorAll('.bonus-form');
    
    bonusForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Использовать этот бонус на текущем вопросе?')) {
                e.preventDefault();
            }
        });
    });

    // Обработка двойной опасности
    const answerForms = document.querySelectorAll('form[action*="take.php"]');
    
    answerForms.forEach(form => {
        form.addEventListener('submit', function() {
            const activeBonus = document.querySelector('.bonus-btn[data-bonus="double_danger"]');
            
            if (activeBonus && activeBonus.classList.contains('active')) {
                if (!confirm('Вы используете бонус "Двойная опасность". За неправильный ответ вы потеряете очки. Продолжить?')) {
                    return false;
                }
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Обработка бонуса 50/50
    document.querySelectorAll('.bonus-btn[data-bonus="fifty_fifty"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Использовать бонус "50/50"? Будут оставлены один правильный и один неправильный ответ.')) {
                document.querySelector('.question-container').classList.add('fifty-fifty-applied');
            }
        });
    });

    // Обработка дополнительного времени
    document.querySelectorAll('.bonus-btn[data-bonus="extra_time"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Использовать бонус "Дополнительное время"? Будет добавлено 30 секунд.')) {
                const timeMessage = document.createElement('div');
                timeMessage.className = 'time-added-message';
                timeMessage.textContent = 'Добавлено 30 секунд!';
                document.querySelector('.quiz-header').appendChild(timeMessage);
                setTimeout(() => timeMessage.remove(), 3000);
            }
        });
    });

    // Подсветка активных бонусов
    document.querySelectorAll('.bonus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.add('bonus-active');
            setTimeout(() => this.classList.remove('bonus-active'), 1000);
        });
    });
});
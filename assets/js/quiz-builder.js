document.addEventListener('DOMContentLoaded', function() {
    // Управление вкладками
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Убираем активные классы
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Добавляем активные классы
            this.classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
    
    // Модальное окно для вопросов
    const questionModal = document.getElementById('question-modal');
    const addQuestionBtn = document.getElementById('add-question-btn');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const questionForm = document.getElementById('question-form');
    const answersList = document.getElementById('answers-list');
    const addAnswerBtn = document.getElementById('add-answer-btn');
    const questionTypeSelect = document.getElementById('question-type');
    
    // Открытие модального окна для нового вопроса
    addQuestionBtn.addEventListener('click', function() {
        document.getElementById('modal-title').textContent = 'Добавить вопрос';
        document.getElementById('question-id').value = '';
        document.getElementById('question-text').value = '';
        document.getElementById('question-points').value = '1';
        document.getElementById('image-preview').innerHTML = '';
        answersList.innerHTML = '';
        questionTypeSelect.value = 'single';
        toggleAnswersSection();
        questionModal.style.display = 'block';
    });
    
    // Закрытие модального окна
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            questionModal.style.display = 'none';
        });
    });
    
    // Закрытие при клике вне модального окна
    window.addEventListener('click', function(e) {
        if (e.target === questionModal) {
            questionModal.style.display = 'none';
        }
    });
    
    // Добавление варианта ответа
    addAnswerBtn.addEventListener('click', function() {
        addAnswerField();
    });
    
    // Изменение типа вопроса
    questionTypeSelect.addEventListener('change', function() {
        toggleAnswersSection();
    });
    
    // Обработка формы вопроса
    questionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        saveQuestion();
    });
    
    // Функция для добавления поля ответа
    function addAnswerField(answer = { text: '', isCorrect: false }) {
        const answerId = Date.now();
        const answerDiv = document.createElement('div');
        answerDiv.className = 'answer-item';
        answerDiv.dataset.answerId = answerId;
        
        answerDiv.innerHTML = `
            <div class="answer-content">
                <input type="${questionTypeSelect.value === 'multiple' ? 'checkbox' : 'radio'}" 
                       name="correct-answer" ${answer.isCorrect ? 'checked' : ''}>
                <input type="text" class="answer-text" value="${answer.text || ''}" placeholder="Текст ответа" required>
            </div>
            <button type="button" class="btn btn-delete delete-answer-btn">Удалить</button>
        `;
        
        answersList.appendChild(answerDiv);
        
        // Обработка удаления ответа
        answerDiv.querySelector('.delete-answer-btn').addEventListener('click', function() {
            answerDiv.remove();
        });
    }
    
    // Функция для переключения секции ответов
    function toggleAnswersSection() {
        const answersSection = document.getElementById('answers-section');
        if (questionTypeSelect.value === 'text') {
            answersSection.style.display = 'none';
        } else {
            answersSection.style.display = 'block';
            // Добавляем первый ответ, если список пуст
            if (answersList.children.length === 0) {
                addAnswerField();
            }
        }
    }
    
    // Функция для сохранения вопроса
    function saveQuestion() {
        const questionId = document.getElementById('question-id').value;
        const quizId = document.getElementById('quiz-id').value;
        const questionText = document.getElementById('question-text').value;
        const questionType = questionTypeSelect.value;
        const points = document.getElementById('question-points').value;
        const imageFile = document.getElementById('question-image').files[0];
        
        // Валидация
        if (!questionText.trim()) {
            alert('Введите текст вопроса');
            return;
        }
        
        if (questionType !== 'text' && answersList.children.length === 0) {
            alert('Добавьте хотя бы один вариант ответа');
            return;
        }
        
        // Сбор данных ответов (для не текстовых вопросов)
        let answers = [];
        if (questionType !== 'text') {
            const answerItems = document.querySelectorAll('.answer-item');
            answerItems.forEach(item => {
                const text = item.querySelector('.answer-text').value;
                const isCorrect = item.querySelector('input[type="checkbox"], input[type="radio"]').checked;
                
                if (text.trim()) {
                    answers.push({ text, isCorrect });
                }
            });
            
            if (answers.length === 0) {
                alert('Добавьте хотя бы один вариант ответа');
                return;
            }
            
            if (questionType === 'single' && answers.filter(a => a.isCorrect).length !== 1) {
                alert('Для вопроса с одним правильным ответом должен быть выбран ровно один правильный вариант');
                return;
            }
        }
        
        // Формируем FormData для отправки
        const formData = new FormData();
        formData.append('quiz_id', quizId);
        formData.append('question_text', questionText);
        formData.append('question_type', questionType);
        formData.append('points', points);
        
        if (questionId) {
            formData.append('question_id', questionId);
        }
        
        if (questionType !== 'text') {
            formData.append('answers', JSON.stringify(answers));
        }
        
        if (imageFile) {
            formData.append('question_image', imageFile);
        }
        
        // Отправка данных на сервер
        fetch('../includes/save_question.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Вопрос успешно сохранен!');
                questionModal.style.display = 'none';
                location.reload(); // Перезагружаем страницу для обновления списка вопросов
            } else {
                alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при сохранении вопроса');
        });
    }
    
    // Обработка кликов на кнопки редактирования вопросов
    document.querySelectorAll('.edit-question-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const questionCard = this.closest('.question-card');
            const questionId = questionCard.dataset.questionId;
            
            try {
                // Загружаем данные вопроса
                const response = await fetch(`../includes/get_question.php?id=${questionId}`);
                const data = await response.json();
                
                if (data.success) {
                    const question = data.question;
                    
                    // Заполняем форму
                    document.getElementById('modal-title').textContent = 'Редактировать вопрос';
                    document.getElementById('question-id').value = question.id;
                    document.getElementById('question-text').value = question.question_text;
                    document.getElementById('question-points').value = question.points;
                    questionTypeSelect.value = question.question_type;
                    
                    // Очищаем и заполняем ответы
                    answersList.innerHTML = '';
                    if (question.answers && question.answers.length > 0) {
                        question.answers.forEach(answer => {
                            addAnswerField(answer);
                        });
                    }
                    
                    // Показываем изображение, если есть
                    const imagePreview = document.getElementById('image-preview');
                    imagePreview.innerHTML = '';
                    if (question.image) {
                        const img = document.createElement('img');
                        img.src = `../uploads/question_images/${question.image}`;
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '200px';
                        imagePreview.appendChild(img);
                    }
                    
                    toggleAnswersSection();
                    questionModal.style.display = 'block';
                } else {
                    alert('Ошибка загрузки вопроса: ' + (data.message || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Произошла ошибка при загрузке вопроса');
            }
        });
    });
    
    // Обработка удаления вопросов
    document.querySelectorAll('.delete-question-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const questionCard = this.closest('.question-card');
            const questionId = questionCard.dataset.questionId;
            
            if (!confirm('Вы уверены, что хотите удалить этот вопрос?')) {
                return;
            }
            
            try {
                const response = await fetch(`../includes/delete_question.php?id=${questionId}`);
                const data = await response.json();
                
                if (data.success) {
                    questionCard.remove();
                    alert('Вопрос успешно удален');
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Произошла ошибка при удалении вопроса');
            }
        });
    });
});
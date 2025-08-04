jQuery(document).ready(function($) {
    'use strict';
    
    let isLoading = false;
    
    // Инициализация
    function init() {
        loadTweets(1);
        setupCharCounter();
        setupFormValidation();
    }
    
    // Загрузка твитов
    function loadTweets(page = 1) {
        if (isLoading) return;
        
        isLoading = true;
        $('#tweets').addClass('loading');
        
        $.ajax({
            url: tweetWallSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_tweets',
                page: page
            },
            success: function(response) {
                if (response.success) {
                    $('#tweets').html(response.data.tweets).removeClass('loading').addClass('fade-in');
                    $('#pagination').html(response.data.pagination);
                } else {
                    showError('Ошибка при загрузке твитов');
                }
            },
            error: function() {
                showError('Произошла ошибка при загрузке твитов');
            },
            complete: function() {
                isLoading = false;
            }
        });
    }
    
    // Отправка твита
    $('#tweet-form').on('submit', function(e) {
        e.preventDefault();
        
        let content = $('[name="tweet_content"]').val().trim();
        if (!content) {
            showError('Пожалуйста, введите текст');
            return;
        }
        
        if (content.length > 280) {
            showError('Максимум 280 символов');
            return;
        }
        
        let submitButton = $(this).find('button[type="submit"]');
        let originalText = submitButton.text();
        
        submitButton.prop('disabled', true).text('Публикация...');
        
        $.ajax({
            url: tweetWallSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_tweet',
                content: content,
                nonce: tweetWallSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('[name="tweet_content"]').val('');
                    updateCharCounter();
                    loadTweets(1);
                    showSuccess('Твит успешно опубликован!');
                } else {
                    showError(response.data || 'Произошла ошибка при публикации');
                }
            },
            error: function() {
                showError('Произошла ошибка при отправке запроса');
            },
            complete: function() {
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Добавление хештега
    $('#add-hashtag').on('click', function() {
        let textarea = $('[name="tweet_content"]');
        let currentContent = textarea.val();
        let cursorPos = textarea[0].selectionStart;
        
        let beforeCursor = currentContent.substring(0, cursorPos);
        let afterCursor = currentContent.substring(cursorPos);
        
        let hashtagText = '#';
        if (beforeCursor && !beforeCursor.endsWith(' ')) {
            hashtagText = ' #';
        }
        
        let newContent = beforeCursor + hashtagText + afterCursor;
        textarea.val(newContent);
        
        // Установка курсора после хештега
        let newCursorPos = cursorPos + hashtagText.length;
        textarea[0].setSelectionRange(newCursorPos, newCursorPos);
        textarea.focus();
        
        updateCharCounter();
    });
    
    // Счетчик символов
    function setupCharCounter() {
        $('[name="tweet_content"]').on('input', updateCharCounter);
    }
    
    function updateCharCounter() {
        let content = $('[name="tweet_content"]').val();
        let remaining = 280 - content.length;
        let counter = $('.char-counter span');
        
        counter.text(remaining);
        
        if (remaining < 20) {
            counter.parent().addClass('warning');
        } else {
            counter.parent().removeClass('warning');
        }
    }
    
    // Валидация формы
    function setupFormValidation() {
        $('[name="tweet_content"]').on('paste', function(e) {
            setTimeout(function() {
                let content = $('[name="tweet_content"]').val();
                if (content.length > 280) {
                    $('[name="tweet_content"]').val(content.substring(0, 280));
                    showError('Текст был обрезан до 280 символов');
                }
                updateCharCounter();
            }, 10);
        });
    }
    
    // Пагинация
    $(document).on('click', '.page-numbers', function(e) {
        e.preventDefault();
        
        let page = $(this).data('page');
        if (!page) return;
        
        loadTweets(page);
        
        // Плавная прокрутка к началу твитов
        $('html, body').animate({
            scrollTop: $('#tweet-wall').offset().top - 50
        }, 500);
    });
    
    // Лайки
    $(document).on('click', '.like-button', function(e) {
        e.preventDefault();
        
        let button = $(this);
        let post_id = button.data('post-id');
        
        if (button.hasClass('processing')) return;
        button.addClass('processing');
        
        $.ajax({
            url: tweetWallSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'toggle_like',
                post_id: post_id,
                nonce: tweetWallSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.find('.like-count').text(response.data.like_count);
                    
                    if (response.data.is_liked) {
                        button.addClass('liked').attr('title', 'Убрать лайк');
                    } else {
                        button.removeClass('liked').attr('title', 'Лайкнуть');
                    }
                } else {
                    showError(response.data || 'Произошла ошибка при обработке лайка');
                }
            },
            error: function() {
                showError('Произошла ошибка при отправке запроса');
            },
            complete: function() {
                button.removeClass('processing');
            }
        });
    });
    
    // Уведомления
    function showError(message) {
        showNotification(message, 'error');
    }
    
    function showSuccess(message) {
        showNotification(message, 'success');
    }
    
    function showNotification(message, type) {
        // Удаляем существующие уведомления
        $('.notification').remove();
        
        let notification = $('<div class="notification notification-' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        // Показываем уведомление
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
        
        // Скрываем через 4 секунды
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 4000);
    }
    
    // Автосохранение черновика
    let draftTimer;
    $('[name="tweet_content"]').on('input', function() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function() {
            let content = $('[name="tweet_content"]').val();
            if (content.trim()) {
                localStorage.setItem('aliprofi_tweet_draft', content);
            } else {
                localStorage.removeItem('aliprofi_tweet_draft');
            }
        }, 1000);
    });
    
    // Восстановление черновика
    function restoreDraft() {
        let draft = localStorage.getItem('aliprofi_tweet_draft');
        if (draft && !$('[name="tweet_content"]').val()) {
            $('[name="tweet_content"]').val(draft);
            updateCharCounter();
        }
    }
    
    // Очистка черновика при успешной публикации
    function clearDraft() {
        localStorage.removeItem('aliprofi_tweet_draft');
    }
    
    // Горячие клавиши
    $('[name="tweet_content"]').on('keydown', function(e) {
        // Ctrl/Cmd + Enter для отправки
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            e.preventDefault();
            $('#tweet-form').submit();
        }
    });
    
    // Инициализация
    init();
    restoreDraft();
    
    // Обновление черновика при успешной публикации
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (settings.data && settings.data.includes('action=add_tweet')) {
            let response = JSON.parse(xhr.responseText);
            if (response.success) {
                clearDraft();
            }
        }
    });
});

// Стили для уведомлений
jQuery(document).ready(function($) {
    if (!$('#aliprofi-notifications-css').length) {
        $('head').append(`
            <style id="aliprofi-notifications-css">
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 10px;
                    color: white;
                    font-weight: 600;
                    z-index: 10000;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                    max-width: 350px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                }
                .notification.show {
                    transform: translateX(0);
                }
                .notification-success {
                    background: linear-gradient(135deg, #17bf63, #0e9f4f);
                }
                .notification-error {
                    background: linear-gradient(135deg, #e1306c, #c13584);
                }
                @media (max-width: 768px) {
                    .notification {
                        top: 10px;
                        right: 10px;
                        left: 10px;
                        max-width: none;
                        transform: translateY(-100%);
                    }
                    .notification.show {
                        transform: translateY(0);
                    }
                }
            </style>
        `);
    }
});

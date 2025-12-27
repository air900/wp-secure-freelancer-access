document.addEventListener('DOMContentLoaded', function() {

    // Инициализация для каждой группы (Страницы и Записи)
    ['allowed_pages', 'allowed_posts'].forEach(target => {
        initGroup(target);
    });

    // Обработка индикатора несохраненных изменений
    const form = document.querySelector('form');
    const unsavedIndicator = document.querySelector('.sfaccess-unsaved-indicator');

    if (form && unsavedIndicator) {
        form.addEventListener('change', () => {
            unsavedIndicator.style.display = 'inline-block';
        });
    }

    // Temporary Access Schedule toggle
    const enableSchedule = document.getElementById('sfaccess-enable-schedule');
    const scheduleFields = document.getElementById('sfaccess-schedule-fields');
    const scheduleNotice = document.getElementById('sfaccess-schedule-notice');

    if (enableSchedule && scheduleFields && scheduleNotice) {
        enableSchedule.addEventListener('change', function() {
            if (this.checked) {
                scheduleFields.style.display = '';
                scheduleNotice.style.display = '';
            } else {
                scheduleFields.style.display = 'none';
                scheduleNotice.style.display = 'none';
            }
        });
    }
});

function initGroup(target) {
    const container = document.querySelector(`.sfaccess-content-list[data-content-type="${target}"]`);
    if (!container) return;

    const controls = {
        search: document.querySelector(`.sfaccess-search-input[data-target="${target}"]`),
        status: document.querySelector(`.sfaccess-status-filter[data-target="${target}"]`),
        sort: document.querySelector(`.sfaccess-sort-select[data-target="${target}"]`),
        visibility: document.querySelector(`.sfaccess-visibility-filter[data-target="${target}"]`),
        counter: document.querySelector(`.sfaccess-counter[data-target="${target}"]`),
        selectAll: document.querySelector(`.sfaccess-select-all[data-target="${target}"]`),
        selectPub: document.querySelector(`.sfaccess-select-published[data-target="${target}"]`),
        deselectAll: document.querySelector(`.sfaccess-deselect-all[data-target="${target}"]`)
    };

    const items = Array.from(container.querySelectorAll('label'));

    // --- Основная функция фильтрации ---
    const filterItems = () => {
        const searchValue = controls.search.value.toLowerCase();
        const statusValue = controls.status.value;
        const visibilityValue = controls.visibility.value;
        
        let visibleCount = 0;

        items.forEach(item => {
            const title = (item.dataset.title || '').toLowerCase();
            const id = item.querySelector('input').value;
            const status = item.dataset.status;
            const isChecked = item.querySelector('input').checked;

            // Проверки
            const matchesSearch = title.includes(searchValue) || id.includes(searchValue);
            const matchesStatus = statusValue === 'all' || status === statusValue;
            const matchesVisibility = visibilityValue === 'all' || 
                                      (visibilityValue === 'selected' && isChecked) || 
                                      (visibilityValue === 'unselected' && !isChecked);

            if (matchesSearch && matchesStatus && matchesVisibility) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Обновление счетчика
        if (controls.counter) {
            controls.counter.textContent = `${visibleCount} / ${items.length}`;
        }

        updateBadges(target);
    };

    // --- Сортировка ---
    const sortItems = () => {
        const sortType = controls.sort.value;
        
        items.sort((a, b) => {
            let valA, valB;

            switch (sortType) {
                case 'title':
                    valA = a.dataset.title;
                    valB = b.dataset.title;
                    return valA.localeCompare(valB);
                case 'id':
                    valA = parseInt(a.querySelector('input').value);
                    valB = parseInt(b.querySelector('input').value);
                    return valA - valB;
                case 'date-modified':
                    valA = parseInt(a.dataset.dateModified || 0);
                    valB = parseInt(b.dataset.dateModified || 0);
                    return valB - valA; // Newest first
                case 'date-created':
                default:
                    valA = parseInt(a.dataset.dateCreated || 0);
                    valB = parseInt(b.dataset.dateCreated || 0);
                    return valB - valA; // Newest first
            }
        });

        // Перемещаем элементы в DOM
        items.forEach(item => container.appendChild(item));
    };

    // --- Слушатели событий ---
    if (controls.search) controls.search.addEventListener('input', filterItems);
    if (controls.status) controls.status.addEventListener('change', filterItems);
    if (controls.visibility) controls.visibility.addEventListener('change', filterItems);
    if (controls.sort) controls.sort.addEventListener('change', sortItems);

    // Обновление при клике на чекбокс (для фильтра "Selected Only" и бейджей)
    container.addEventListener('change', (e) => {
        if (e.target.type === 'checkbox') {
            if (controls.visibility.value !== 'all') {
                filterItems();
            } else {
                updateBadges(target);
            }
        }
    });

    // --- Кнопки массового выбора ---
    if (controls.selectAll) {
        controls.selectAll.addEventListener('click', () => {
            items.forEach(item => {
                if (item.style.display !== 'none') item.querySelector('input').checked = true;
            });
            filterItems();
        });
    }

    if (controls.deselectAll) {
        controls.deselectAll.addEventListener('click', () => {
            items.forEach(item => {
                if (item.style.display !== 'none') item.querySelector('input').checked = false;
            });
            filterItems();
        });
    }

    if (controls.selectPub) {
        controls.selectPub.addEventListener('click', () => {
            items.forEach(item => {
                if (item.style.display !== 'none' && item.dataset.status === 'publish') {
                    item.querySelector('input').checked = true;
                }
            });
            filterItems();
        });
    }

    // Инициализация
    sortItems();
    filterItems();
}

// --- Бейджи (Сводка выбранного) ---
function updateBadges(target) {
    const container = document.querySelector(`.sfaccess-content-list[data-content-type="${target}"]`);
    const badgeContainer = document.getElementById(target === 'allowed_pages' ? 'sfaccess-badges-pages' : 'sfaccess-badges-posts');
    const countSpan = document.getElementById(target === 'allowed_pages' ? 'sfaccess-badge-pages-count' : 'sfaccess-badge-posts-count');
    const summaryBlock = document.getElementById('sfaccess-selected-summary');
    const totalSpan = document.getElementById('sfaccess-selected-total');

    if (!container || !badgeContainer) return;

    // Очистка
    badgeContainer.innerHTML = '';
    
    const checkedItems = Array.from(container.querySelectorAll('input:checked'));
    
    checkedItems.forEach(input => {
        const label = input.closest('label');
        const title = label.dataset.title;
        const id = input.value;
        
        const badge = document.createElement('span');
        badge.className = 'sfaccess-badge';
        badge.textContent = `${id}: ${title}`;
        badgeContainer.appendChild(badge);
    });

    // Обновление счетчиков
    if (countSpan) countSpan.textContent = checkedItems.length;
    
    // Общий счетчик
    const totalPages = parseInt(document.getElementById('sfaccess-badge-pages-count')?.textContent || 0);
    const totalPosts = parseInt(document.getElementById('sfaccess-badge-posts-count')?.textContent || 0);
    if (totalSpan) totalSpan.textContent = totalPages + totalPosts;
    
    if (summaryBlock) summaryBlock.style.display = (totalPages + totalPosts) > 0 ? 'block' : 'none';
}
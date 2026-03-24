/**
 * Diploma — Course Editor
 * Handles lesson drag-reorder within the course edit page
 */
(function () {
    'use strict';

    const lessonList = document.getElementById('lesson-list');
    if (!lessonList) return;

    const reorderUrl = lessonList.dataset.reorderUrl;
    let draggedItem = null;

    lessonList.addEventListener('dragstart', function (e) {
        draggedItem = e.target.closest('li');
        if (draggedItem) {
            draggedItem.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    lessonList.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const target = e.target.closest('li');
        if (target && target !== draggedItem) {
            const rect = target.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;

            if (e.clientY < midY) {
                lessonList.insertBefore(draggedItem, target);
            } else {
                lessonList.insertBefore(draggedItem, target.nextSibling);
            }
        }
    });

    lessonList.addEventListener('dragend', function () {
        if (draggedItem) {
            draggedItem.style.opacity = '1';
            draggedItem = null;
            saveOrder();
        }
    });

    // Make items draggable via handle
    lessonList.querySelectorAll('li').forEach(function (li) {
        li.setAttribute('draggable', 'true');
    });

    function saveOrder() {
        if (!reorderUrl) return;

        const ids = Array.from(lessonList.querySelectorAll('li'))
            .map(function (li) { return li.dataset.id; });

        const data = new FormData();
        ids.forEach(function (id) { data.append('ids[]', id); });
        data.append(Craft.csrfTokenName, Craft.csrfTokenValue);

        fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: data,
        });
    }
})();

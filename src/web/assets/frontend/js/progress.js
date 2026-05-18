/**
 * Diploma — Progress Tracking (Frontend)
 * Handles AJAX lesson completion
 */
(function () {
    'use strict';

    document.querySelectorAll('.diploma-lesson-complete-btn').forEach(function (btn) {
        if (btn.classList.contains('completed')) return;

        btn.addEventListener('click', function () {
            const lessonId = btn.dataset.lessonId;
            const csrfName = btn.dataset.csrfName;
            const csrfValue = btn.dataset.csrfValue;

            btn.disabled = true;
            btn.textContent = 'Completing...';

            const data = new FormData();
            data.append('lessonId', lessonId);
            data.append(csrfName, csrfValue);

            const url = (window.DiplomaUrls && window.DiplomaUrls.completeLesson) || '/diploma/api/complete-lesson';

            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: data,
            })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.success) {
                    btn.textContent = 'Completed';
                    btn.classList.add('completed');

                    // Update progress bar if present
                    const progressBar = document.querySelector('.diploma-progress-bar .progress-fill');
                    if (progressBar && result.progress) {
                        progressBar.style.width = result.progress.percentage + '%';
                    }

                    const progressText = document.querySelector('.diploma-progress-text');
                    if (progressText && result.progress) {
                        progressText.textContent = result.progress.completedLessons + ' / ' + result.progress.totalLessons + ' lessons (' + result.progress.percentage + '%)';
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Mark Complete';
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Mark Complete';
            });
        });
    });
})();

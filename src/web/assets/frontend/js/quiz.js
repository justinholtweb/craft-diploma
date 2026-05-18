/**
 * Diploma — Quiz Frontend
 * Timer and form submission handling
 */
(function () {
    'use strict';

    const quizForm = document.querySelector('.diploma-quiz-form');
    if (!quizForm) return;

    const timerEl = document.querySelector('.diploma-quiz-timer');
    const timeLimit = parseInt(quizForm.dataset.timeLimit || '0', 10);

    // Timer
    if (timerEl && timeLimit > 0) {
        let remaining = timeLimit;

        function updateTimer() {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timerEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (remaining <= 60) {
                timerEl.classList.add('warning');
            }

            if (remaining <= 0) {
                clearInterval(timerInterval);
                quizForm.submit();
                return;
            }

            remaining--;
        }

        updateTimer();
        var timerInterval = setInterval(updateTimer, 1000);
    }

    // Form submission via AJAX
    quizForm.addEventListener('submit', function (e) {
        if (quizForm.dataset.ajax !== 'true') return;

        e.preventDefault();
        const submitBtn = quizForm.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }

        const formData = new FormData(quizForm);

        const url = (window.DiplomaUrls && window.DiplomaUrls.submitQuiz) || '/diploma/api/submit-quiz';

        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData,
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                // Show results
                const resultsDiv = document.createElement('div');
                resultsDiv.className = 'diploma-quiz-results';
                resultsDiv.innerHTML = '<h3>Quiz Results</h3>'
                    + '<p>Score: <strong>' + result.score + '%</strong></p>'
                    + '<p>Points: ' + result.pointsEarned + ' / ' + result.pointsPossible + '</p>'
                    + '<p>' + (result.passed ? 'Passed!' : 'Not passed.') + '</p>';

                quizForm.replaceWith(resultsDiv);
            } else {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Quiz';
                }
            }
        });
    });
})();

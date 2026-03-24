/**
 * Diploma — Quiz Builder
 * Handles question CRUD and answer management via AJAX
 */
(function () {
    'use strict';

    const builder = document.getElementById('quiz-builder');
    if (!builder) return;

    const quizId = builder.dataset.quizId;

    // Question form state
    const questionTypes = [
        { value: 'multipleChoice', label: 'Multiple Choice' },
        { value: 'trueFalse', label: 'True/False' },
        { value: 'shortAnswer', label: 'Short Answer' },
        { value: 'matching', label: 'Matching' },
    ];

    // Add "Add Question" button after builder
    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn';
    addBtn.textContent = 'Add Question';
    addBtn.style.marginTop = '12px';
    builder.parentNode.insertBefore(addBtn, builder.nextSibling);

    addBtn.addEventListener('click', function () {
        showQuestionForm();
    });

    function showQuestionForm(existingQuestion) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-shade';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';

        const modal = document.createElement('div');
        modal.style.cssText = 'background:white;border-radius:8px;padding:24px;width:600px;max-height:80vh;overflow-y:auto;';

        const title = existingQuestion ? 'Edit Question' : 'Add Question';
        modal.innerHTML = '<h2>' + title + '</h2>';

        const form = document.createElement('form');

        // Question type
        let typeHtml = '<div class="field"><label>Type</label><select name="questionType">';
        questionTypes.forEach(function (qt) {
            const selected = existingQuestion && existingQuestion.questionType === qt.value ? ' selected' : '';
            typeHtml += '<option value="' + qt.value + '"' + selected + '>' + qt.label + '</option>';
        });
        typeHtml += '</select></div>';

        // Question text
        const qText = existingQuestion ? existingQuestion.questionText : '';
        typeHtml += '<div class="field"><label>Question</label><textarea name="questionText" rows="3" style="width:100%">' + qText + '</textarea></div>';

        // Points
        const pts = existingQuestion ? existingQuestion.points : 1;
        typeHtml += '<div class="field"><label>Points</label><input type="number" name="points" value="' + pts + '" min="0"></div>';

        // Explanation
        const expl = existingQuestion ? (existingQuestion.explanation || '') : '';
        typeHtml += '<div class="field"><label>Explanation (optional)</label><textarea name="explanation" rows="2" style="width:100%">' + expl + '</textarea></div>';

        // Answers section
        typeHtml += '<div id="answers-section"><h3>Answers</h3><div id="answers-list"></div>';
        typeHtml += '<button type="button" id="add-answer-btn" class="btn small">Add Answer</button></div>';

        typeHtml += '<hr><div class="buttons"><button type="submit" class="btn submit">Save</button> <button type="button" class="btn cancel-btn">Cancel</button></div>';

        form.innerHTML = typeHtml;
        modal.appendChild(form);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Populate existing answers
        const answersList = form.querySelector('#answers-list');
        if (existingQuestion && existingQuestion.answers) {
            existingQuestion.answers.forEach(function (a, i) {
                addAnswerRow(answersList, i, a.answerText, a.isCorrect);
            });
        }

        // Add answer button
        let answerCount = existingQuestion ? existingQuestion.answers.length : 0;
        form.querySelector('#add-answer-btn').addEventListener('click', function () {
            addAnswerRow(answersList, answerCount++, '', false);
        });

        // Cancel
        form.querySelector('.cancel-btn').addEventListener('click', function () {
            document.body.removeChild(overlay);
        });

        // Submit
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveQuestion(form, existingQuestion ? existingQuestion.id : null, overlay);
        });
    }

    function addAnswerRow(container, index, text, isCorrect) {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:4px;';
        row.innerHTML = '<input type="text" name="answers[' + index + '][answerText]" value="' + (text || '') + '" style="flex:1" placeholder="Answer text">'
            + '<label><input type="checkbox" name="answers[' + index + '][isCorrect]" value="1"' + (isCorrect ? ' checked' : '') + '> Correct</label>'
            + '<button type="button" class="btn small" onclick="this.parentNode.remove()">×</button>';
        container.appendChild(row);
    }

    function saveQuestion(form, questionId, overlay) {
        const formData = new FormData(form);
        formData.append('quizId', quizId);
        if (questionId) formData.append('questionId', questionId);
        formData.append(Craft.csrfTokenName, Craft.csrfTokenValue);

        fetch(Craft.actionUrl + '/diploma/questions/save', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.body.removeChild(overlay);
                location.reload();
            }
        });
    }
})();

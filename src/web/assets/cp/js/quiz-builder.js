/**
 * Diploma — Quiz Builder
 * Handles question CRUD and answer management via AJAX
 */
(function ($) {
    'use strict';

    const builder = document.getElementById('quiz-builder');
    if (!builder) return;

    const quizId = builder.dataset.quizId;

    const questionTypes = [
        { value: 'multipleChoice', label: 'Multiple Choice' },
        { value: 'trueFalse', label: 'True/False' },
        { value: 'shortAnswer', label: 'Short Answer' },
        { value: 'matching', label: 'Matching' },
    ];

    // Inject the "Add Question" button below the builder
    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn submit add icon';
    addBtn.textContent = 'Add Question';
    addBtn.style.marginTop = '14px';
    builder.parentNode.insertBefore(addBtn, builder.nextSibling);

    addBtn.addEventListener('click', function () {
        showQuestionForm();
    });

    function showQuestionForm(existingQuestion) {
        const $modal = $('<form class="modal diploma-question-modal"/>');
        const $body = $('<div class="body"/>').appendTo($modal);

        // Header
        $('<header class="header"/>')
            .append($('<h1/>').text(existingQuestion ? 'Edit Question' : 'Add Question'))
            .prependTo($modal);

        // Question type
        const $typeField = field('Type').appendTo($body);
        const $typeSelectWrap = $('<div class="select"/>').appendTo($typeField.find('.input'));
        const $typeSelect = $('<select name="questionType"/>').appendTo($typeSelectWrap);
        questionTypes.forEach(function (qt) {
            const $opt = $('<option/>').val(qt.value).text(qt.label);
            if (existingQuestion && existingQuestion.questionType === qt.value) {
                $opt.attr('selected', 'selected');
            }
            $opt.appendTo($typeSelect);
        });

        // Question text
        const $qField = field('Question', 'The question prompt shown to learners.').appendTo($body);
        $('<textarea name="questionText" rows="3" class="text fullwidth nicetext"/>')
            .val(existingQuestion ? existingQuestion.questionText : '')
            .appendTo($qField.find('.input'));

        // Points
        const $pointsField = field('Points').appendTo($body);
        $('<input type="number" name="points" min="0" class="text"/>')
            .val(existingQuestion ? existingQuestion.points : 1)
            .appendTo($pointsField.find('.input'));

        // Explanation
        const $explField = field('Explanation', 'Shown after the learner submits an answer. Optional.').appendTo($body);
        $('<textarea name="explanation" rows="2" class="text fullwidth nicetext"/>')
            .val(existingQuestion && existingQuestion.explanation ? existingQuestion.explanation : '')
            .appendTo($explField.find('.input'));

        // Answers section
        $('<hr>').appendTo($body);
        $('<h2/>').text('Answers').appendTo($body);
        $('<p class="light"/>').text('Mark each correct option. For True/False, add two answers.').appendTo($body);

        const $answersList = $('<div class="diploma-answer-list"/>').appendTo($body);

        let answerCount = 0;
        if (existingQuestion && existingQuestion.answers) {
            existingQuestion.answers.forEach(function (a) {
                addAnswerRow($answersList, answerCount++, a.answerText, a.isCorrect);
            });
        }

        const $addAnswerBtn = $('<button type="button" class="btn small add icon"/>').text('Add Answer');
        $addAnswerBtn.css('margin-top', '8px').appendTo($body);
        $addAnswerBtn.on('click', function () {
            addAnswerRow($answersList, answerCount++, '', false);
        });

        // Footer
        const $footer = $('<div class="footer"/>').appendTo($modal);
        const $buttons = $('<div class="buttons right"/>').appendTo($footer);
        const $cancelBtn = $('<button type="button" class="btn">Cancel</button>').appendTo($buttons);
        const $saveBtn = $('<button type="submit" class="btn submit">Save</button>').appendTo($buttons);

        const modal = new Garnish.Modal($modal, {
            autoShow: true,
            hideOnEsc: true,
            hideOnShadeClick: true,
            shadeClass: 'modal-shade dark',
            resizable: false,
        });

        // Garnish locks the modal's inline height/min-height at init.
        // Clear them so our CSS max-height (85vh) actually constrains the modal.
        const clearInlineSize = function () {
            $modal.css({ height: '', 'min-height': '', 'min-width': '' });
            if (modal.updateSizeAndPosition) modal.updateSizeAndPosition();
        };
        clearInlineSize();
        $addAnswerBtn.on('click', clearInlineSize);

        $cancelBtn.on('click', function () {
            modal.hide();
        });

        $modal.on('submit', function (e) {
            e.preventDefault();
            $saveBtn.addClass('disabled').attr('disabled', true).text('Saving…');
            saveQuestion($modal.get(0), existingQuestion ? existingQuestion.id : null, modal, $saveBtn);
        });
    }

    function field(label, instructions) {
        const $field = $('<div class="field"/>');
        const $heading = $('<div class="heading"/>').appendTo($field);
        $('<label/>').text(label).appendTo($heading);
        if (instructions) {
            $('<div class="instructions"/>').append($('<p/>').text(instructions)).appendTo($heading);
        }
        $('<div class="input ltr"/>').appendTo($field);
        return $field;
    }

    function addAnswerRow($container, index, text, isCorrect) {
        const $row = $('<div class="diploma-answer-row"/>').appendTo($container);
        $('<input type="text" class="text fullwidth" placeholder="Answer text"/>')
            .attr('name', 'answers[' + index + '][answerText]')
            .val(text || '')
            .appendTo($row);
        const $correct = $('<label class="diploma-answer-correct"/>').appendTo($row);
        $('<input type="checkbox" value="1"/>')
            .attr('name', 'answers[' + index + '][isCorrect]')
            .prop('checked', !!isCorrect)
            .appendTo($correct);
        $correct.append(document.createTextNode(' Correct'));
        const $delBtn = $('<button type="button" class="btn small delete icon" title="Remove"/>').appendTo($row);
        $delBtn.on('click', function () {
            $row.remove();
        });
    }

    function saveQuestion(form, questionId, modal, $saveBtn) {
        const formData = new FormData(form);
        formData.append('quizId', quizId);
        if (questionId) formData.append('questionId', questionId);
        formData.append(Craft.csrfTokenName, Craft.csrfTokenValue);

        fetch(Craft.getActionUrl('diploma/questions/save'), {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.message) {
                    Craft.cp.displayNotice(data.message);
                }
                if (data && data.success !== false) {
                    modal.hide();
                    location.reload();
                } else {
                    $saveBtn.removeClass('disabled').removeAttr('disabled').text('Save');
                    Craft.cp.displayError((data && data.message) || 'Couldn\'t save question.');
                }
            })
            .catch(function () {
                $saveBtn.removeClass('disabled').removeAttr('disabled').text('Save');
                Craft.cp.displayError('Couldn\'t save question.');
            });
    }
})(jQuery);

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';

requireAdmin();

/* DELETE QUESTION */
if (isset($_POST['delete_question'])) {
    $id = (int)$_POST['question_id'];

    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['flash'] = [
        'message' => 'Question deleted.',
        'type' => 'success'
    ];

    header("Location: manage_questions.php");
    exit;
}

/* UPDATE QUESTION + OPTIONS */
if (isset($_POST['update_question'])) {
    $id = (int)$_POST['question_id'];
    $question_text = trim($_POST['question_text']);
    $answer_type = $_POST['answer_type'] ?? 'single';

    $existingOptions = $_POST['options'] ?? [];
    $newOptions = $_POST['new_options'] ?? [];
    $deleteOptions = $_POST['delete_options'] ?? [];
    $rawCorrectOptions = $_POST['correct_options'] ?? [];

    if (!in_array($answer_type, ['single', 'multiple'])) {
        $answer_type = 'single';
    }

    $deleteOptions = array_filter($deleteOptions);
    $deleteOptions = array_map('intval', $deleteOptions);

    $existingCorrectOptions = [];
    $newCorrectOptions = [];

    foreach ($rawCorrectOptions as $value) {
        if (strpos((string)$value, 'new_') === 0) {
            $newCorrectOptions[] = str_replace('new_', '', $value);
        } else {
            $existingCorrectOptions[] = (int)$value;
        }
    }

    try {
        $pdo->beginTransaction();

        if ($question_text === '') {
            throw new Exception('Question text is required.');
        }

        $stmt = $pdo->prepare("
            UPDATE questions
            SET question_text = ?, answer_type = ?
            WHERE id = ?
        ");
        $stmt->execute([$question_text, $answer_type, $id]);

        /* DELETE OPTIONS */
        if (!empty($deleteOptions)) {
            $placeholders = implode(',', array_fill(0, count($deleteOptions), '?'));

            $deleteStmt = $pdo->prepare("
                DELETE FROM question_options
                WHERE question_id = ?
                  AND id IN ($placeholders)
            ");

            $deleteStmt->execute(array_merge([$id], $deleteOptions));
        }

        /* UPDATE EXISTING OPTIONS */
        foreach ($existingOptions as $optionId => $optionText) {
            $optionId = (int)$optionId;

            if (in_array($optionId, $deleteOptions)) {
                continue;
            }

            $optionText = trim($optionText);

            if ($optionText === '') {
                throw new Exception('Option text cannot be empty.');
            }

            $isCorrect = in_array($optionId, $existingCorrectOptions) ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE question_options
                SET option_text = ?, is_correct = ?
                WHERE id = ? AND question_id = ?
            ");
            $stmt->execute([$optionText, $isCorrect, $optionId, $id]);
        }

        /* INSERT NEW OPTIONS */
        $newOptionIds = [];

        $insertOptionStmt = $pdo->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct)
            VALUES (?, ?, 0)
        ");

        foreach ($newOptions as $tmpIndex => $optionText) {
            $optionText = trim($optionText);

            if ($optionText === '') {
                continue;
            }

            $insertOptionStmt->execute([$id, $optionText]);
            $newOptionIds[(string)$tmpIndex] = (int)$pdo->lastInsertId();
        }

        /* MARK NEW OPTIONS AS CORRECT IF SELECTED */
        foreach ($newCorrectOptions as $tmpIndex) {
            if (isset($newOptionIds[(string)$tmpIndex])) {
                $newCorrectId = $newOptionIds[(string)$tmpIndex];

                $stmt = $pdo->prepare("
                    UPDATE question_options
                    SET is_correct = 1
                    WHERE id = ? AND question_id = ?
                ");
                $stmt->execute([$newCorrectId, $id]);
            }
        }

        /* VALIDATION AFTER CHANGES */
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM question_options
            WHERE question_id = ?
        ");
        $countStmt->execute([$id]);
        $optionCount = (int)$countStmt->fetchColumn();

        if ($optionCount < 2) {
            throw new Exception('A question must have at least 2 options.');
        }

        $correctCountStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM question_options
            WHERE question_id = ? AND is_correct = 1
        ");
        $correctCountStmt->execute([$id]);
        $correctCount = (int)$correctCountStmt->fetchColumn();

        if ($correctCount < 1) {
            throw new Exception('Select at least one correct answer.');
        }

        if ($answer_type === 'single' && $correctCount !== 1) {
            throw new Exception('Single answer questions must have exactly one correct answer.');
        }

        $pdo->commit();

        $_SESSION['flash'] = [
            'message' => 'Question updated successfully.',
            'type' => 'success'
        ];

        header("Location: manage_questions.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash'] = [
            'message' => 'Failed to update question: ' . $e->getMessage(),
            'type' => 'error'
        ];

        header("Location: manage_questions.php?edit_id=" . $id);
        exit;
    }
}

/* LOAD QUESTION FOR EDIT */
$editQuestion = null;
$options = [];

if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];

    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    $editQuestion = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $options = $stmt->fetchAll();
}

/* LOAD ALL QUESTIONS */
$stmt = $pdo->query("
    SELECT q.id, q.question_text, q.image_path, q.answer_type, c.name AS category_name
    FROM questions q
    LEFT JOIN categories c ON q.category_id = c.id
    ORDER BY q.id DESC
");

$questions = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <?php displayFlash(); ?>

    <?php if ($editQuestion): ?>
        <div class="card" style="margin-bottom: 20px;">
            <h2>Edit Question</h2>

            <form method="POST" id="editQuestionForm">
                <input type="hidden" name="question_id" value="<?= $editQuestion['id'] ?>">

                <div style="margin-bottom: 15px;">
                    <label>Question Text</label><br>
                    <textarea name="question_text" rows="4" style="width: 100%;" required><?= htmlspecialchars($editQuestion['question_text']) ?></textarea>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Answer Type</label><br>
                    <select name="answer_type" required>
                        <option value="single" <?= ($editQuestion['answer_type'] ?? 'single') === 'single' ? 'selected' : '' ?>>
                            Single correct answer
                        </option>
                        <option value="multiple" <?= ($editQuestion['answer_type'] ?? 'single') === 'multiple' ? 'selected' : '' ?>>
                            Multiple correct answers
                        </option>
                    </select>
                </div>

                <h3>Options</h3>
                <p>
                    A question must have at least 2 options.
                    For single answer type, select exactly one correct answer.
                </p>

                <div id="optionsContainer">
                    <?php foreach ($options as $index => $opt): ?>
                        <div
                            class="form-group option-row"
                            data-option-id="<?= $opt['id'] ?>"
                        >
                            <label>Option <?= $index + 1 ?></label>

                            <input
                                type="text"
                                name="options[<?= $opt['id'] ?>]"
                                value="<?= htmlspecialchars($opt['option_text']) ?>"
                                required
                            >

                            <label>
                                <input
                                    type="checkbox"
                                    name="correct_options[]"
                                    value="<?= $opt['id'] ?>"
                                    <?= $opt['is_correct'] ? 'checked' : '' ?>
                                >
                                Correct
                            </label>

                            <button type="button" class="remove-option-btn">
                                Remove
                            </button>

                            <input
                                type="hidden"
                                name="delete_options[]"
                                value=""
                                class="delete-marker"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="addOptionBtn" class="btn btn-secondary">
                    Add Option
                </button>

                <br><br>

                <button type="submit" name="update_question" class="btn btn-primary">
                    Update Question
                </button>

                <a href="manage_questions.php" style="margin-left: 10px;">
                    Cancel
                </a>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h1>Manage Questions</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Question</th>
                    <th>Category</th>
                    <th>Answer Type</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><?= $q['id'] ?></td>
                        <td><?= htmlspecialchars($q['question_text']) ?></td>
                        <td><?= htmlspecialchars($q['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($q['answer_type'] ?? 'single') ?></td>
                        <td>
                            <?php if ($q['image_path']): ?>
                                <img src="/music-exam-system/<?= htmlspecialchars($q['image_path']) ?>" class="thumb" alt="Question image">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="manage_questions.php?edit_id=<?= $q['id'] ?>">
                                <button type="button">Edit</button>
                            </a>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <button type="submit" name="delete_question">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const answerType = document.querySelector('select[name="answer_type"]');
    const optionsContainer = document.getElementById('optionsContainer');
    const addOptionBtn = document.getElementById('addOptionBtn');

    let newOptionIndex = 0;

    if (!answerType || !optionsContainer || !addOptionBtn) return;

    function getRows() {
        return Array.from(optionsContainer.querySelectorAll('.option-row'))
            .filter(row => row.style.display !== 'none');
    }

    function getCorrectBoxes() {
        return getRows()
            .map(row => row.querySelector('input[name="correct_options[]"]'))
            .filter(Boolean);
    }

    function refreshLabels() {
        getRows().forEach((row, index) => {
            const label = row.querySelector('label');
            label.textContent = 'Option ' + (index + 1);
        });
    }

    function enforceSingleSelection(changedBox) {
        if (answerType.value === 'single' && changedBox.checked) {
            getCorrectBoxes().forEach(box => {
                if (box !== changedBox) {
                    box.checked = false;
                }
            });
        }
    }

    function bindRow(row) {
        const checkbox = row.querySelector('input[name="correct_options[]"]');
        const removeBtn = row.querySelector('.remove-option-btn');

        if (checkbox) {
            checkbox.addEventListener('change', function () {
                enforceSingleSelection(this);
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const visibleRows = getRows();

                if (visibleRows.length <= 2) {
                    alert('A question must have at least 2 options.');
                    return;
                }

                const optionId = row.dataset.optionId;

                if (optionId) {
                    const hiddenDelete = row.querySelector('.delete-marker');
                    hiddenDelete.value = optionId;

                    const textInput = row.querySelector('input[type="text"]');
                    const correctInput = row.querySelector('input[name="correct_options[]"]');

                    if (textInput) {
                        textInput.required = false;
                    }

                    if (correctInput) {
                        correctInput.checked = false;
                    }

                    row.style.display = 'none';
                } else {
                    row.remove();
                }

                refreshLabels();
            });
        }
    }

    optionsContainer.querySelectorAll('.option-row').forEach(bindRow);

    addOptionBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'form-group option-row';

        row.innerHTML = `
            <label>Option</label>

            <input
                type="text"
                name="new_options[${newOptionIndex}]"
                required
            >

            <label>
                <input
                    type="checkbox"
                    name="correct_options[]"
                    value="new_${newOptionIndex}"
                >
                Correct
            </label>

            <button type="button" class="remove-option-btn">
                Remove
            </button>
        `;

        optionsContainer.appendChild(row);
        bindRow(row);
        refreshLabels();

        newOptionIndex++;
    });

    answerType.addEventListener('change', function () {
        if (answerType.value === 'single') {
            let foundOne = false;

            getCorrectBoxes().forEach(box => {
                if (box.checked) {
                    if (!foundOne) {
                        foundOne = true;
                    } else {
                        box.checked = false;
                    }
                }
            });
        }
    });

    refreshLabels();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
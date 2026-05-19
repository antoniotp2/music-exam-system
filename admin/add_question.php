<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = trim($_POST['question_text'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $answerType = $_POST['answer_type'] ?? 'single';
    $optionsRaw = $_POST['options'] ?? [];
    $correctIndexes = $_POST['correct_answers'] ?? [];

    if (!in_array($answerType, ['single', 'multiple'])) {
        $answerType = 'single';
    }

    $options = [];
    $oldToNewIndex = [];

    foreach ($optionsRaw as $oldIndex => $optionText) {
        $optionText = trim($optionText);

        if ($optionText !== '') {
            $oldToNewIndex[(int)$oldIndex] = count($options);
            $options[] = $optionText;
        }
    }

    $correctIndexes = array_map('intval', $correctIndexes);

    $cleanCorrectIndexes = [];

    foreach ($correctIndexes as $oldIndex) {
        if (isset($oldToNewIndex[$oldIndex])) {
            $cleanCorrectIndexes[] = $oldToNewIndex[$oldIndex];
        }
    }

    $cleanCorrectIndexes = array_unique($cleanCorrectIndexes);

    if ($questionText === '' || count($options) < 2 || empty($cleanCorrectIndexes)) {
        setFlash('error', 'Question must have at least 2 options and at least one correct answer.');
        header("Location: add_question.php");
        exit;
    }

    if ($answerType === 'single' && count($cleanCorrectIndexes) !== 1) {
        setFlash('error', 'Single answer questions must have exactly one correct answer.');
        header("Location: add_question.php");
        exit;
    }

    $imagePath = null;

    if (!empty($_FILES['question_image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/questions/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['question_image']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['question_image']['name']);
        $targetPath = $uploadDir . $fileName;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (in_array($_FILES['question_image']['type'], $allowedTypes)) {
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $imagePath = 'uploads/questions/' . $fileName;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO questions (question_text, image_path, category_id, answer_type)
            VALUES (:question_text, :image_path, :category_id, :answer_type)
        ");

        $stmt->execute([
            'question_text' => $questionText,
            'image_path' => $imagePath,
            'category_id' => $categoryId,
            'answer_type' => $answerType
        ]);

        $questionId = $pdo->lastInsertId();

        $optionStmt = $pdo->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct)
            VALUES (:question_id, :option_text, :is_correct)
        ");

        foreach ($options as $index => $optionText) {
            $optionStmt->execute([
                'question_id' => $questionId,
                'option_text' => $optionText,
                'is_correct' => in_array($index, $cleanCorrectIndexes) ? 1 : 0
            ]);
        }

        $pdo->commit();
        setFlash('success', 'Question added successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setFlash('error', 'Failed to add question: ' . $e->getMessage());
    }

    header("Location: add_question.php");
    exit;
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="card">
        <h1>Add Question</h1>
        <?php displayFlash(); ?>

        <form method="POST" enctype="multipart/form-data" class="form">
            <div class="form-group">
                <label>Question Text</label>
                <textarea name="question_text" rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Answer Type</label>
                <select name="answer_type" required>
                    <option value="single">Single correct answer</option>
                    <option value="multiple">Multiple correct answers</option>
                </select>
            </div>

            <div class="form-group">
                <label>Optional Question Image</label>
                <input type="file" name="question_image" accept="image/*">
            </div>

            <h3>Options</h3>
            <p>
                Add at least 2 options. For single answer type, select exactly one correct answer.
            </p>

            <div id="optionsContainer">
                <div class="form-group option-row">
                    <label>Option 1</label>
                    <input type="text" name="options[0]" required>
                    <label>
                        <input type="checkbox" name="correct_answers[]" value="0">
                        Correct
                    </label>
                    <button type="button" class="remove-option-btn" style="display:none;">Remove</button>
                </div>

                <div class="form-group option-row">
                    <label>Option 2</label>
                    <input type="text" name="options[1]" required>
                    <label>
                        <input type="checkbox" name="correct_answers[]" value="1">
                        Correct
                    </label>
                    <button type="button" class="remove-option-btn" style="display:none;">Remove</button>
                </div>
            </div>

            <button type="button" id="addOptionBtn" class="btn btn-secondary">Add Option</button>

            <br><br>

            <button type="submit" class="btn btn-primary">Save Question</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const answerType = document.querySelector('select[name="answer_type"]');
    const optionsContainer = document.getElementById('optionsContainer');
    const addOptionBtn = document.getElementById('addOptionBtn');

    let optionIndex = 2;

    function getCorrectBoxes() {
        return document.querySelectorAll('input[name="correct_answers[]"]');
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

    function updateRemoveButtons() {
        const rows = optionsContainer.querySelectorAll('.option-row');
        rows.forEach(row => {
            const btn = row.querySelector('.remove-option-btn');
            btn.style.display = rows.length > 2 ? 'inline-block' : 'none';
        });
    }

    function refreshLabels() {
        const rows = optionsContainer.querySelectorAll('.option-row');
        rows.forEach((row, index) => {
            row.querySelector('label').textContent = 'Option ' + (index + 1);
        });
    }

    function bindRow(row) {
        const checkbox = row.querySelector('input[type="checkbox"]');
        const removeBtn = row.querySelector('.remove-option-btn');

        checkbox.addEventListener('change', function () {
            enforceSingleSelection(this);
        });

        removeBtn.addEventListener('click', function () {
            row.remove();
            refreshLabels();
            updateRemoveButtons();
        });
    }

    optionsContainer.querySelectorAll('.option-row').forEach(bindRow);

    addOptionBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'form-group option-row';

        row.innerHTML = `
            <label>Option</label>
            <input type="text" name="options[${optionIndex}]" required>
            <label>
                <input type="checkbox" name="correct_answers[]" value="${optionIndex}">
                Correct
            </label>
            <button type="button" class="remove-option-btn">Remove</button>
        `;

        optionsContainer.appendChild(row);
        optionIndex++;

        bindRow(row);
        refreshLabels();
        updateRemoveButtons();
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

    updateRemoveButtons();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
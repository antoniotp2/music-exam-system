<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = trim($_POST['question_text'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $options = $_POST['options'] ?? [];
    $correctIndex = $_POST['correct_answer'] ?? '';

    if ($questionText === '' || count($options) !== 4 || $correctIndex === '') {
        setFlash('error', 'All question fields are required.');
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
            INSERT INTO questions (question_text, image_path, category_id)
            VALUES (:question_text, :image_path, :category_id)
        ");
        $stmt->execute([
            'question_text' => $questionText,
            'image_path' => $imagePath,
            'category_id' => $categoryId
        ]);

        $questionId = $pdo->lastInsertId();

        $optionStmt = $pdo->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct)
            VALUES (:question_id, :option_text, :is_correct)
        ");

        foreach ($options as $index => $optionText) {
            $optionStmt->execute([
                'question_id' => $questionId,
                'option_text' => trim($optionText),
                'is_correct' => ((string)$index === (string)$correctIndex) ? 1 : 0
            ]);
        }

        $pdo->commit();
        setFlash('success', 'Question added successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to add question.');
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
                <label>Optional Question Image</label>
                <input type="file" name="question_image" accept="image/*">
            </div>

            <div class="form-group">
                <label>Option 1</label>
                <input type="text" name="options[]" required>
            </div>

            <div class="form-group">
                <label>Option 2</label>
                <input type="text" name="options[]" required>
            </div>

            <div class="form-group">
                <label>Option 3</label>
                <input type="text" name="options[]" required>
            </div>

            <div class="form-group">
                <label>Option 4</label>
                <input type="text" name="options[]" required>
            </div>

            <div class="form-group">
                <label>Correct Answer</label>
                <select name="correct_answer" required>
                    <option value="">Select correct option</option>
                    <option value="0">Option 1</option>
                    <option value="1">Option 2</option>
                    <option value="2">Option 3</option>
                    <option value="3">Option 4</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Save Question</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
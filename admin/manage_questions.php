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
    $correct_option = $_POST['correct_option'] ?? null;

    if ($question_text === '' || empty($_POST['options']) || !$correct_option) {
        $_SESSION['flash'] = [
    'message' => 'Please fill all fields correctly.',
    'type' => 'error'
];
        header("Location: manage_questions.php?edit_id=" . $id);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE questions SET question_text = ? WHERE id = ?");
    $stmt->execute([$question_text, $id]);

    foreach ($_POST['options'] as $option_id => $option_text) {
        $option_text = trim($option_text);
        $is_correct = ((int)$correct_option === (int)$option_id) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE question_options
            SET option_text = ?, is_correct = ?
            WHERE id = ? AND question_id = ?
        ");
        $stmt->execute([$option_text, $is_correct, $option_id, $id]);
    }

    $_SESSION['flash'] = [
    'message' => 'Question updated successfully.',
    'type' => 'success'
];
    header("Location: manage_questions.php");
    exit;
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
    SELECT q.id, q.question_text, q.image_path, c.name AS category_name
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

            <form method="POST">
                <input type="hidden" name="question_id" value="<?= $editQuestion['id'] ?>">

                <div style="margin-bottom: 15px;">
                    <label>Question Text</label><br>
                    <textarea name="question_text" rows="4" style="width: 100%;" required><?= htmlspecialchars($editQuestion['question_text']) ?></textarea>
                </div>

                <h3>Options</h3>

                <?php foreach ($options as $opt): ?>
                    <div style="margin-bottom: 10px;">
                        <input
                            type="text"
                            name="options[<?= $opt['id'] ?>]"
                            value="<?= htmlspecialchars($opt['option_text']) ?>"
                            style="width: 70%;"
                            required
                        >

                        <label style="margin-left: 10px;">
                            <input
                                type="radio"
                                name="correct_option"
                                value="<?= $opt['id'] ?>"
                                <?= $opt['is_correct'] ? 'checked' : '' ?>
                                required
                            >
                            Correct
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" name="update_question">Update Question</button>
                <a href="manage_questions.php" style="margin-left: 10px;">Cancel</a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
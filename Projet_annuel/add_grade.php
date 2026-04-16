<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: notes.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$subjectName = trim($_POST["subject_name"] ?? "");
$evaluationName = trim($_POST["evaluation_name"] ?? "");
$evaluationDate = $_POST["evaluation_date"] ?? "";
$coefficient = (float) ($_POST["coefficient"] ?? 0);
$grade = (float) ($_POST["grade"] ?? -1);

if (
    $subjectName === "" ||
    $evaluationName === "" ||
    $evaluationDate === "" ||
    $coefficient <= 0 ||
    $grade < 0 ||
    $grade > 20
) {
    header("Location: notes.php?message=" . urlencode("Donnees invalides."));
    exit;
}

$subjectIdStmt = $pdo->prepare("SELECT id FROM subjects WHERE user_id = :user_id AND subject_name = :subject_name LIMIT 1");
$subjectIdStmt->execute([
    "user_id" => $userId,
    "subject_name" => $subjectName
]);
$subjectRow = $subjectIdStmt->fetch();
$subjectId = $subjectRow ? (int) $subjectRow["id"] : null;

$insert = $pdo->prepare(
    "INSERT INTO grades (user_id, subject_id, subject_name, evaluation_name, evaluation_date, coefficient, grade)
     VALUES (:user_id, :subject_id, :subject_name, :evaluation_name, :evaluation_date, :coefficient, :grade)"
);
$insert->execute([
    "user_id" => $userId,
    "subject_id" => $subjectId,
    "subject_name" => $subjectName,
    "evaluation_name" => $evaluationName,
    "evaluation_date" => $evaluationDate,
    "coefficient" => $coefficient,
    "grade" => $grade
]);

header("Location: notes.php?message=" . urlencode("Note ajoutee."));
exit;
?>

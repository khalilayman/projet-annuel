<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$subjectsCountStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM subjects WHERE user_id = :user_id");
$subjectsCountStmt->execute(["user_id" => $userId]);
$subjectsCount = (int) (($subjectsCountStmt->fetch()["total"] ?? 0));

$avgStmt = $pdo->prepare("SELECT COALESCE(SUM(grade * coefficient) / NULLIF(SUM(coefficient), 0), 0) AS avg_grade FROM grades WHERE user_id = :user_id");
$avgStmt->execute(["user_id" => $userId]);
$avgGrade = (float) (($avgStmt->fetch()["avg_grade"] ?? 0));

$budgetStmt = $pdo->prepare(
    "SELECT
        (SELECT COALESCE(SUM(monthly_income), 0) FROM budget WHERE user_id = :user_id_budget) AS income,
        (SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE user_id = :user_id_expense) AS expense"
);
$budgetStmt->execute([
    "user_id_budget" => $userId,
    "user_id_expense" => $userId
]);
$budget = $budgetStmt->fetch() ?: ["income" => 0, "expense" => 0];
$budgetLeft = (float) $budget["income"] - (float) $budget["expense"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tout est pret - Student Hub</title>
    <link rel="stylesheet" href="css/etape3.css">
</head>
<body>
    <div class="onboarding-container">
        <div class="success-container">
            <div class="success-icon"><img src="images/check.png" alt="Success"></div>
            <h1>Tout est pret !</h1>
            <p class="subtitle">Ton parcours academique est maintenant personnalise.</p>

            <div class="profile-cards">
                <div class="profile-card">
                    <div class="profile-card-icon academic"><img src="images/cap.png" alt=""></div>
                    <p class="profile-card-label">ACADEMIC</p>
                    <h3><?= $subjectsCount ?> matieres</h3>
                </div>
                <div class="profile-card">
                    <div class="profile-card-icon budget"><img src="images/cash.png" alt=""></div>
                    <p class="profile-card-label">BUDGET</p>
                    <h3><?= round($budgetLeft, 2) ?> EUR</h3>
                </div>
                <div class="profile-card">
                    <div class="profile-card-icon interests"><img src="images/star.png" alt=""></div>
                    <p class="profile-card-label">NOTES</p>
                    <h3>Moyenne <?= round($avgGrade, 2) ?>/20</h3>
                </div>
            </div>

            <a href="dashboard.php" class="btn-continue">Decouvrir mon tableau de bord</a>
        </div>
    </div>
    <footer>© STUDENT HUB HORIZON EDUCATION SYSTEMS</footer>
</body>
</html>

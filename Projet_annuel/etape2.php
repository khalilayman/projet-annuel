<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$monthYear = date("Y-m-01");
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $income = (float) ($_POST["monthly_income"] ?? 0);
    $upsert = $pdo->prepare(
        "INSERT INTO budget (user_id, monthly_income, month_year)
         VALUES (:user_id, :monthly_income, :month_year)
         ON DUPLICATE KEY UPDATE monthly_income = VALUES(monthly_income)"
    );
    $upsert->execute([
        "user_id" => $userId,
        "monthly_income" => $income,
        "month_year" => $monthYear
    ]);
    $message = "Budget enregistre.";
}

$budgetStmt = $pdo->prepare("SELECT monthly_income FROM budget WHERE user_id = :user_id ORDER BY month_year DESC LIMIT 1");
$budgetStmt->execute(["user_id" => $userId]);
$monthlyIncome = (float) (($budgetStmt->fetch()["monthly_income"] ?? 0));

$fixedStmt = $pdo->prepare(
    "SELECT expense_category, COALESCE(SUM(expense_amount), 0) AS total
     FROM expenses
     WHERE user_id = :user_id
     GROUP BY expense_category
     ORDER BY total DESC
     LIMIT 3"
);
$fixedStmt->execute(["user_id" => $userId]);
$fixedExpenses = $fixedStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure ton budget - Student Hub</title>
    <link rel="stylesheet" href="css/etape2.css">
</head>
<body>
    <div class="onboarding-container">
        <div class="progress-indicator">
            <div class="progress-bar active"></div>
            <div class="progress-bar active"></div>
            <div class="progress-bar"></div>
        </div>

        <p class="step-label">Etape 2 sur 3 • 66% complete</p>
        <h1>Configure ton budget</h1>
        <p class="subtitle">Ajuste tes revenus et depenses pour une recommandation personnalisee.</p>
        <?php if ($message !== ""): ?>
            <p><?= $message ?></p>
        <?php endif; ?>

        <div class="budget-section">
            <h3>Revenu mensuel net</h3>
            <form method="POST" action="etape2.php">
                <div class="budget-input-wrapper">
                    <span class="currency-symbol">EUR</span>
                    <input type="number" step="0.01" name="monthly_income" class="budget-input" value="<?= (string) $monthlyIncome ?>">
                </div>
                <button class="btn-continue budget-submit-btn" type="submit">Enregistrer budget</button>
            </form>
        </div>

        <div class="budget-section">
            <h3>Depenses fixes</h3>
            <?php if (count($fixedExpenses) === 0): ?>
                <div class="expense-item"><div class="expense-info"><div class="expense-details"><h4>Aucune depense</h4></div></div></div>
            <?php else: ?>
                <?php foreach ($fixedExpenses as $expense): ?>
                    <div class="expense-item">
                        <div class="expense-info">
                            <div class="expense-icon"><img src="images/house.png" alt=""></div>
                            <div class="expense-details">
                                <h4><?= $expense["expense_category"] ?> - <?= round((float) $expense["total"], 2) ?> EUR</h4>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="navigation-buttons">
            <a href="etape1.php"><button class="btn-back"><img src="images/left.png" alt="">Precedent</button></a>
            <a href="etape3.php"><button class="btn-continue">Continuer</button></a>
        </div>
    </div>
    <footer>© STUDENT HUB HORIZON EDUCATION SYSTEMS</footer>
</body>
</html>

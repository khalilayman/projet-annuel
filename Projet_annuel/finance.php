<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formType = $_POST["form_type"] ?? "";

    if ($formType === "budget") {
        $monthlyIncome = (float) ($_POST["monthly_income"] ?? 0);
        if ($monthlyIncome >= 0) {
            $monthYear = date("Y-m-01");
            $budgetInsert = $pdo->prepare(
                "INSERT INTO budget (user_id, monthly_income, month_year)
                 VALUES (:user_id, :monthly_income, :month_year)
                 ON DUPLICATE KEY UPDATE monthly_income = VALUES(monthly_income)"
            );
            $budgetInsert->execute([
                "user_id" => $userId,
                "monthly_income" => $monthlyIncome,
                "month_year" => $monthYear
            ]);
            $message = "Budget enregistre.";
        } else {
            $message = "Montant du budget invalide.";
        }
    }

    if ($formType === "expense") {
        $title = trim($_POST["expense_title"] ?? "");
        $category = trim($_POST["expense_category"] ?? "");
        $amount = (float) ($_POST["expense_amount"] ?? 0);
        $date = $_POST["expense_date"] ?? date("Y-m-d");

        if ($title !== "" && $category !== "" && $amount > 0) {
            $expenseInsert = $pdo->prepare(
                "INSERT INTO expenses (user_id, expense_title, expense_category, expense_amount, expense_date)
                 VALUES (:user_id, :expense_title, :expense_category, :expense_amount, :expense_date)"
            );
            $expenseInsert->execute([
                "user_id" => $userId,
                "expense_title" => $title,
                "expense_category" => $category,
                "expense_amount" => $amount,
                "expense_date" => $date
            ]);

            $transactionInsert = $pdo->prepare(
                "INSERT INTO transactions (user_id, transaction_name, transaction_category, transaction_amount, transaction_type, transaction_date)
                 VALUES (:user_id, :name, :category, :amount, 'expense', :date)"
            );
            $transactionInsert->execute([
                "user_id" => $userId,
                "name" => $title,
                "category" => $category,
                "amount" => $amount,
                "date" => $date
            ]);

            $message = "Depense ajoutee.";
        } else {
            $message = "Informations de depense invalides.";
        }
    }
}

$totalsStmt = $pdo->prepare(
    "SELECT
        (SELECT COALESCE(SUM(monthly_income), 0) FROM budget WHERE user_id = :user_id_budget) AS total_income,
        (SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE user_id = :user_id_expenses) AS total_expenses"
);
$totalsStmt->execute([
    "user_id_budget" => $userId,
    "user_id_expenses" => $userId
]);
$totals = $totalsStmt->fetch() ?: ["total_income" => 0, "total_expenses" => 0];
$totalIncome = (float) $totals["total_income"];
$totalExpenses = (float) $totals["total_expenses"];
$totalBalance = $totalIncome - $totalExpenses;

$latestBudgetStmt = $pdo->prepare("SELECT monthly_income FROM budget WHERE user_id = :user_id ORDER BY month_year DESC LIMIT 1");
$latestBudgetStmt->execute(["user_id" => $userId]);
$latestBudget = (float) (($latestBudgetStmt->fetch()["monthly_income"] ?? 0));
$budgetProgress = $latestBudget > 0 ? min(100, max(0, ($totalExpenses / $latestBudget) * 100)) : 0;

$catStmt = $pdo->prepare(
    "SELECT expense_category, COALESCE(SUM(expense_amount), 0) AS total
     FROM expenses
     WHERE user_id = :user_id
     GROUP BY expense_category
     ORDER BY total DESC
     LIMIT 3"
);
$catStmt->execute(["user_id" => $userId]);
$categories = $catStmt->fetchAll();

$weeklyStmt = $pdo->prepare(
    "SELECT YEARWEEK(expense_date, 1) AS yw, COALESCE(SUM(expense_amount), 0) AS total
     FROM expenses
     WHERE user_id = :user_id
       AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 35 DAY)
     GROUP BY YEARWEEK(expense_date, 1)
     ORDER BY yw ASC"
);
$weeklyStmt->execute(["user_id" => $userId]);
$weeklyRaw = $weeklyStmt->fetchAll();

$weeklyTotals = array_fill(0, 5, 0.0);
$weeklyLabels = [];
$rawCount = count($weeklyRaw);
if ($rawCount > 0) {
    $slice = array_slice($weeklyRaw, -5);
    $sliceCount = count($slice);
    $weeklyLabels = array_fill(0, 5, "");
    for ($i = 0; $i < $sliceCount; $i++) {
        $targetIndex = 5 - $sliceCount + $i;
        $weeklyTotals[$targetIndex] = (float) $slice[$i]["total"];
        $yw = (string) $slice[$i]["yw"];
        $year = (int) substr($yw, 0, 4);
        $week = (int) substr($yw, 4, 2);
        $date = new DateTime();
        $date->setISODate($year, $week);
        $weeklyLabels[$targetIndex] = strtoupper($date->format("M")) . " " . $date->format("d");
    }
}
if (count($weeklyLabels) === 0) {
    $weeklyLabels = [];
    for ($i = 4; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-" . ($i * 7) . " days");
        $weeklyLabels[] = strtoupper($date->format("M")) . " " . $date->format("d");
    }
}

$maxWeekly = max($weeklyTotals);
if ($maxWeekly <= 0) {
    $maxWeekly = 1;
}

$chartPoints = [];
for ($i = 0; $i < 5; $i++) {
    $x = $i * 200;
    $y = 160 - (($weeklyTotals[$i] / $maxWeekly) * 120);
    $chartPoints[] = [(int) $x, (int) round($y)];
}

$linePath = "M " . $chartPoints[0][0] . " " . $chartPoints[0][1];
for ($i = 1; $i < count($chartPoints); $i++) {
    $linePath .= " L " . $chartPoints[$i][0] . " " . $chartPoints[$i][1];
}
$areaPath = $linePath . " L 800 190 L 0 190 Z";

$circleLength = 440;
$donutSegments = [];
$donutOffset = 0.0;
$colors = ["#F97316", "#10B981", "#3B7EF8"];
foreach ($categories as $index => $cat) {
    $amount = (float) $cat["total"];
    $ratio = $totalExpenses > 0 ? ($amount / $totalExpenses) : 0;
    $length = $ratio * $circleLength;
    $donutSegments[] = [
        "color" => $colors[$index % count($colors)],
        "length" => $length,
        "offset" => -$donutOffset
    ];
    $donutOffset += $length;
}

$txnStmt = $pdo->prepare(
    "SELECT transaction_name, transaction_category, transaction_amount, transaction_type, transaction_date
     FROM transactions
     WHERE user_id = :user_id
     ORDER BY transaction_date DESC, id DESC
     LIMIT 4"
);
$txnStmt->execute(["user_id" => $userId]);
$transactions = $txnStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - Student Hub</title>
    <link rel="stylesheet" href="css/finance.css">
</head>
<body>
    <aside class="sidebar">
        <div class="logo-section">
            <div class="logo-icon"><img src="/images/rocket.png" alt=""></div>
            <div class="logo-text">
                <h2>Student HUB</h2>
                <p>Student Life Manager</p>
            </div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item"><span class="icon"><img src="/images/acceuil.png" alt=""></span><span>Accueil</span></a>
            <a href="notes.php" class="nav-item"><span class="icon"><img src="/images/notes.png" alt=""></span><span>Notes</span></a>
            <a href="finance.php" class="nav-item active"><span class="icon"><img src="/images/finance.png" alt=""></span><span>Finance</span></a>
            <a href="planning.php" class="nav-item"><span class="icon"><img src="/images/planning.png" alt=""></span><span>Planning</span></a>
            <a href="logout.php" class="nav-item"><span>Deconnexion</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="search-bar">
                <span class="search-icon"><img src="/images/loupe.png" alt=""></span>
                <input type="text" placeholder="Rechercher un cours, une depense...">
            </div>
            <div class="header-actions">
                <div class="user-profile">
                    <div class="user-info">
                        <p class="user-name"><?= $_SESSION["user_name"] ?? "Ayman Khalil" ?></p>
                        <p class="user-major"><?= $_SESSION["major"] ?? "L1 Informatique" ?></p>
                    </div>
                    <img src="/images/profile.png" alt="">
                </div>
            </div>
        </header>

        <?php if ($message !== ""): ?>
            <p class="finance-message"><?= $message ?></p>
        <?php endif; ?>

        <div class="finance-actions">
            <button id="showBudgetFormBtn" class="action-btn budget">+ Ajouter un budget</button>
            <button id="showExpenseFormBtn" class="action-btn expense">+ Ajouter une depense</button>
        </div>

        <div id="budgetModal" class="finance-modal hidden">
            <div class="finance-modal-card">
                <div class="finance-modal-header">
                    <h3>Ajouter un budget</h3>
                    <button id="closeBudgetModalBtn" type="button" class="finance-modal-close">×</button>
                </div>
                <form id="budgetForm" method="POST" action="finance.php">
                    <input type="hidden" name="form_type" value="budget">
                    <label>Budget mensuel (EUR)</label>
                    <input id="monthly_income" type="number" step="0.01" min="0" name="monthly_income" placeholder="Ex: 1200">
                    <div class="finance-modal-actions">
                        <button id="cancelBudgetModalBtn" type="button" class="submit-btn cancel-btn">Annuler</button>
                        <button type="submit" class="submit-btn">Enregistrer budget</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="expenseModal" class="finance-modal hidden">
            <div class="finance-modal-card">
                <div class="finance-modal-header">
                    <h3>Ajouter une depense</h3>
                    <button id="closeExpenseModalBtn" type="button" class="finance-modal-close">×</button>
                </div>
                <form id="expenseForm" method="POST" action="finance.php">
                    <input type="hidden" name="form_type" value="expense">
                    <label>Titre</label>
                    <input id="expense_title" type="text" name="expense_title" placeholder="Ex: Courses Monoprix">
                    <label>Categorie</label>
                    <input id="expense_category" type="text" name="expense_category" placeholder="Ex: Courses">
                    <label>Montant (EUR)</label>
                    <input id="expense_amount" type="number" step="0.01" min="0.01" name="expense_amount" placeholder="Ex: 32.50">
                    <label>Date</label>
                    <input id="expense_date" type="date" name="expense_date" value="<?= date("Y-m-d") ?>">
                    <div class="finance-modal-actions">
                        <button id="cancelExpenseModalBtn" type="button" class="submit-btn cancel-btn">Annuler</button>
                        <button type="submit" class="submit-btn">Enregistrer depense</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="finance-top-grid">
            <div class="balance-card">
                <div class="card-label">Total Balance</div>
                <div class="balance-amount"><?= round($totalBalance, 2) ?> EUR</div>
                <div class="balance-change positive"><img src="/images/trend-up.png" alt=""> Calculé depuis la base</div>
            </div>

            <div class="budget-card">
                <div class="card-label">Budget Progress</div>
                <div class="budget-info"><span class="budget-amount"><?= round($totalExpenses, 2) ?> EUR</span><span class="budget-total">/ <?= round($latestBudget, 2) ?> EUR</span></div>
                <div class="budget-progress-bar"><div id="budgetFill" class="budget-fill" data-width="<?= round($budgetProgress, 1) ?>"></div></div>
                <div class="budget-meta"><span class="budget-percent"><?= round($budgetProgress, 1) ?>% depensé</span><span class="budget-days">Mois en cours</span></div>
            </div>
        </div>

        <div class="chart-section">
            <div class="chart-header">
                <div><h3>Spending Evolution</h3><p class="chart-subtitle">Total expenditure over the last 30 days</p></div>
                <div class="chart-tabs">
                    <button class="chart-tab">Daily</button>
                    <button class="chart-tab active">Weekly</button>
                    <button class="chart-tab">Monthly</button>
                </div>
            </div>
            <div class="spending-chart">
                <svg viewBox="0 0 800 200" class="chart-svg">
                    <defs>
                        <linearGradient id="chartGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#4F7EEB" stop-opacity="0.3" />
                            <stop offset="100%" stop-color="#4F7EEB" stop-opacity="0.05" />
                        </linearGradient>
                    </defs>
                    <path d="<?= $areaPath ?>" fill="url(#chartGradient)" stroke="none"/>
                    <path d="<?= $linePath ?>" fill="none" stroke="#4F7EEB" stroke-width="3"/>
                </svg>
            </div>
            <div class="chart-labels">
                <?php foreach ($weeklyLabels as $label): ?>
                    <span><?= $label ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="finance-bottom-grid">
            <div class="category-card">
                <h3>Expenses by Category</h3>
                <div class="donut-chart">
                    <svg viewBox="0 0 200 200" class="donut-svg">
                        <circle cx="100" cy="100" r="70" fill="none" stroke="#E5E7EB" stroke-width="25"/>
                        <?php foreach ($donutSegments as $segment): ?>
                            <circle
                                cx="100"
                                cy="100"
                                r="70"
                                fill="none"
                                stroke="<?= $segment["color"] ?>"
                                stroke-width="25"
                                stroke-dasharray="<?= round($segment["length"], 2) ?> 440"
                                stroke-dashoffset="<?= round($segment["offset"], 2) ?>"
                                transform="rotate(-90 100 100)"
                            />
                        <?php endforeach; ?>
                    </svg>
                    <div class="donut-center">
                        <div class="donut-amount"><?= round($totalExpenses, 2) ?> EUR</div>
                        <div class="donut-label">Total Spent</div>
                    </div>
                </div>
                <div class="category-legend">
                    <?php if (count($categories) === 0): ?>
                        <div class="legend-item"><span class="legend-label">Aucune depense</span></div>
                    <?php else: ?>
                        <?php foreach ($categories as $index => $cat): ?>
                            <?php
                            $color = $colors[$index % count($colors)];
                            $percent = $totalExpenses > 0 ? ((float) $cat["total"] * 100 / $totalExpenses) : 0;
                            ?>
                            <div class="legend-item">
                                <span class="legend-dot legend-dot-<?= $index % count($colors) ?>"></span>
                                <span class="legend-label"><?= $cat["expense_category"] ?></span>
                                <span class="legend-value"><?= round($percent, 1) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="transactions-card">
                <div class="card-header"><h3>Recent Transactions</h3></div>
                <div class="transaction-list">
                    <?php if (count($transactions) === 0): ?>
                        <div class="transaction-item"><div class="transaction-info"><p class="transaction-name">Aucune transaction</p></div></div>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <?php $isIncome = $t["transaction_type"] === "income"; ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <p class="transaction-name"><?= $t["transaction_name"] ?></p>
                                    <p class="transaction-meta"><?= $t["transaction_category"] ?> • <?= $t["transaction_date"] ?></p>
                                </div>
                                <div class="transaction-amount <?= $isIncome ? "positive" : "negative" ?>">
                                    <?= $isIncome ? "+" : "-" ?><?= round((float) $t["transaction_amount"], 2) ?> EUR
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script>
        const budgetFill = document.getElementById("budgetFill");
        if (budgetFill) {
            const width = parseFloat(budgetFill.dataset.width || "0");
            budgetFill.style.width = Math.max(0, Math.min(100, width)) + "%";
        }

        const budgetBtn = document.getElementById("showBudgetFormBtn");
        const expenseBtn = document.getElementById("showExpenseFormBtn");
        const budgetModal = document.getElementById("budgetModal");
        const expenseModal = document.getElementById("expenseModal");
        const closeBudgetModalBtn = document.getElementById("closeBudgetModalBtn");
        const closeExpenseModalBtn = document.getElementById("closeExpenseModalBtn");
        const cancelBudgetModalBtn = document.getElementById("cancelBudgetModalBtn");
        const cancelExpenseModalBtn = document.getElementById("cancelExpenseModalBtn");

        budgetBtn.addEventListener("click", function () {
            budgetModal.classList.remove("hidden");
        });

        expenseBtn.addEventListener("click", function () {
            expenseModal.classList.remove("hidden");
        });

        closeBudgetModalBtn.addEventListener("click", function () { budgetModal.classList.add("hidden"); });
        closeExpenseModalBtn.addEventListener("click", function () { expenseModal.classList.add("hidden"); });
        cancelBudgetModalBtn.addEventListener("click", function () { budgetModal.classList.add("hidden"); });
        cancelExpenseModalBtn.addEventListener("click", function () { expenseModal.classList.add("hidden"); });
        budgetModal.addEventListener("click", function (e) { if (e.target === budgetModal) budgetModal.classList.add("hidden"); });
        expenseModal.addEventListener("click", function (e) { if (e.target === expenseModal) expenseModal.classList.add("hidden"); });

        document.getElementById("budgetForm").addEventListener("submit", function (e) {
            const value = parseFloat(document.getElementById("monthly_income").value);
            if (isNaN(value) || value < 0) {
                e.preventDefault();
                alert("Budget invalide.");
            }
        });

        document.getElementById("expenseForm").addEventListener("submit", function (e) {
            const title = document.getElementById("expense_title").value.trim();
            const category = document.getElementById("expense_category").value.trim();
            const amount = parseFloat(document.getElementById("expense_amount").value);
            if (!title || !category || isNaN(amount) || amount <= 0) {
                e.preventDefault();
                alert("Veuillez remplir correctement la depense.");
            }
        });
    </script>
</body>
</html>

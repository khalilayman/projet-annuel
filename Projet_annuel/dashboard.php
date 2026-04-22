<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$avgStmt = $pdo->prepare("SELECT COALESCE(SUM(grade * coefficient) / NULLIF(SUM(coefficient), 0), 0) AS avg_grade FROM grades WHERE user_id = :user_id");
$avgStmt->execute(["user_id" => $userId]);
$avgGrade = (float) ($avgStmt->fetch()["avg_grade"] ?? 0);

$budgetStmt = $pdo->prepare(
    "SELECT monthly_income,
            (SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE user_id = :user_id_exp) AS total_expenses
     FROM budget
     WHERE user_id = :user_id_budget
     ORDER BY month_year DESC
     LIMIT 1"
);
$budgetStmt->execute([
    "user_id_exp" => $userId,
    "user_id_budget" => $userId
]);
$budgetRow = $budgetStmt->fetch();
$monthlyIncome = (float) ($budgetRow["monthly_income"] ?? 0);
$totalExpenses = (float) ($budgetRow["total_expenses"] ?? 0);
$remainingBudget = $monthlyIncome - $totalExpenses;

$eventsStmt = $pdo->prepare("SELECT event_title, event_day, start_time FROM events WHERE user_id = :user_id ORDER BY id DESC LIMIT 2");
$eventsStmt->execute(["user_id" => $userId]);
$events = $eventsStmt->fetchAll();

$planningStmt = $pdo->prepare("SELECT event_title, event_type FROM events WHERE user_id = :user_id ORDER BY id DESC LIMIT 3");
$planningStmt->execute(["user_id" => $userId]);
$planningItems = $planningStmt->fetchAll();

$gradesStmt = $pdo->prepare("SELECT evaluation_name, evaluation_date, grade FROM grades WHERE user_id = :user_id ORDER BY evaluation_date DESC, id DESC LIMIT 3");
$gradesStmt->execute(["user_id" => $userId]);
$recentGrades = $gradesStmt->fetchAll();

$expensesStmt = $pdo->prepare("SELECT expense_title, expense_date, expense_amount FROM expenses WHERE user_id = :user_id ORDER BY expense_date DESC, id DESC LIMIT 3");
$expensesStmt->execute(["user_id" => $userId]);
$recentExpenses = $expensesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Hub</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <aside class="sidebar">
        <div class="logo-section">
            <div class="logo-icon">
                <img src="/images/rocket.png" alt="">
            </div>
            <div class="logo-text">
                <h2>Student HUB</h2>
                <p>Student Life Manager</p>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <span class="icon"><img src="/images/acceuil.png" alt=""></span>
                <span>Accueil</span>
            </a>
            <a href="notes.php" class="nav-item">
                <span class="icon"><img src="/images/notes.png" alt=""></span>
                <span>Notes</span>
            </a>
            <a href="finance.php" class="nav-item">
                <span class="icon"><img src="/images/finance.png" alt=""></span>
                <span>Finance</span>
            </a>
            <a href="planning.php" class="nav-item">
                <span class="icon"><img src="/images/planning.png" alt=""></span>
                <span>Planning Personnel</span>
            </a>
            <a href="logout.php" class="nav-item">
                <span>Deconnexion</span>
            </a> 
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="search-bar">
                <span class="search-icon"><img src="/images/loupe.png" alt=""></span>
                <input type="text" placeholder="Rechercher un cours, une depense...">
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <p class="user-name"><?= $_SESSION["user_name"] ?? "Ayman Khalil" ?></p>
                    <p class="user-major"><?= $_SESSION["major"] ?? "L1 Informatique" ?></p>
                </div>
                <img src="images/profile.png" alt="">
            </div>
        </header>

        <div class="welcome-banner">
            <div class="banner-content">
                <h1>Bonjour, <?= $_SESSION["user_name"] ?? "Ayman" ?>!</h1>
                <p>Vous avez 3 cours aujourd'hui et votre planning est pret.</p>
            </div>
            <div class="banner-illustration">
                <img src="/images/rocket_banner.png" alt="">
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Moyenne academique</span>
                    <span class="stat-trend positive"><img src="/images/trend-up.png" alt=""> Depuis vos notes</span>
                </div>
                <div class="stat-value"><?= round($avgGrade, 2) ?> <span class="stat-max">/20</span></div>
                <div class="progress-bar"><div class="progress-fill"></div></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Budget mensuel</span>
                    <span class="stat-trend negative"><img src="/images/carte.png" alt=""> Revenu - depenses</span>
                </div>
                <div class="stat-value"><?= round($remainingBudget, 2) ?> <span class="stat-currency">EUR restants</span></div>
                <a href="finance.php"><button class="btn-add-expense">Ajouter depense</button></a>
            </div>

            <div class="stat-card events-card">
                <div class="stat-header">
                    <span class="stat-label">Evenements (<?= count($events) ?>)</span>
                    <img class="icon-btn-small" src="/images/planning.png" alt="">
                </div>
                <div class="event-list">
                    <?php if (count($events) === 0): ?>
                        <div class="event-item"><div class="event-details"><p class="event-title">Aucun evenement</p></div></div>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-item">
                                <span class="event-dot blue"></span>
                                <div class="event-details">
                                    <p class="event-title"><?= $event["event_title"] ?></p>
                                    <p class="event-time"><?= $event["event_day"] ?>, <?= substr($event["start_time"], 0, 5) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title"><h3>Planning</h3></div>
                    <a href="planning.php" class="link-manage">Gerer</a>
                </div>
                <div class="habit-list">
                    <?php if (count($planningItems) === 0): ?>
                        <div class="habit-item"><div class="habit-info"><p class="habit-name">Aucun cours</p><p class="habit-type">Ajoutez un evenement</p></div></div>
                    <?php else: ?>
                        <?php foreach ($planningItems as $item): ?>
                            <div class="habit-item">
                                <div class="habit-info">
                                    <p class="habit-name"><?= $item["event_title"] ?></p>
                                    <p class="habit-type"><?= $item["event_type"] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="content-card">
                <div class="card-header"><h3>Notes Recentes</h3></div>
                <div class="notes-list">
                    <?php if (count($recentGrades) === 0): ?>
                        <div class="note-item"><div class="note-info"><p class="note-title">Aucune note</p></div></div>
                    <?php else: ?>
                        <?php foreach ($recentGrades as $grade): ?>
                            <div class="note-item">
                                <div class="note-badge js">NT</div>
                                <div class="note-info">
                                    <p class="note-title"><?= $grade["evaluation_name"] ?></p>
                                    <p class="note-date"><?= $grade["evaluation_date"] ?></p>
                                </div>
                                <div class="note-score"><?= round((float) $grade["grade"], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header"><h3>Depenses Recentes</h3></div>
                <div class="expenses-list">
                    <?php if (count($recentExpenses) === 0): ?>
                        <div class="expense-item"><div class="expense-info"><p class="expense-title">Aucune depense</p></div></div>
                    <?php else: ?>
                        <?php foreach ($recentExpenses as $expense): ?>
                            <div class="expense-item">
                                <div class="expense-icon cart"><img src="/images/cart.png" alt=""></div>
                                <div class="expense-info">
                                    <p class="expense-title"><?= $expense["expense_title"] ?></p>
                                    <p class="expense-date"><?= $expense["expense_date"] ?></p>
                                </div>
                                <div class="expense-amount negative">-<?= round((float) $expense["expense_amount"], 2) ?>EUR</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

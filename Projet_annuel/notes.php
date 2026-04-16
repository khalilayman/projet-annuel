<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = $_GET["message"] ?? "";

$gradesStmt = $pdo->prepare(
    "SELECT subject_name, evaluation_name, evaluation_date, coefficient, grade
     FROM grades
     WHERE user_id = :user_id
     ORDER BY evaluation_date DESC, id DESC"
);
$gradesStmt->execute(["user_id" => $userId]);
$grades = $gradesStmt->fetchAll();

$avgStmt = $pdo->prepare("SELECT COALESCE(SUM(grade * coefficient) / NULLIF(SUM(coefficient), 0), 0) AS avg_grade FROM grades WHERE user_id = :user_id");
$avgStmt->execute(["user_id" => $userId]);
$avgGrade = (float) (($avgStmt->fetch()["avg_grade"] ?? 0));

$creditsStmt = $pdo->prepare("SELECT COALESCE(SUM(credits), 0) AS total_credits FROM subject_averages WHERE user_id = :user_id");
$creditsStmt->execute(["user_id" => $userId]);
$totalCredits = (int) (($creditsStmt->fetch()["total_credits"] ?? 0));

$subjectAvgStmt = $pdo->prepare(
    "SELECT subject_name, ROUND(COALESCE(SUM(grade * coefficient) / NULLIF(SUM(coefficient), 0), 0), 2) AS avg_subject
     FROM grades
     WHERE user_id = :user_id
     GROUP BY subject_name
     ORDER BY avg_subject DESC
     LIMIT 2"
);
$subjectAvgStmt->execute(["user_id" => $userId]);
$subjectTrends = $subjectAvgStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - Student HUB</title>
    <link rel="stylesheet" href="css/notes.css">
</head>
<body>
<aside class="sidebar">
    <div class="logo-section">
        <div class="logo-icon"><img src="/images/rocket.png" alt="Logo"></div>
        <div class="logo-text"><h2>Student HUB</h2><p>Student Life Manager</p></div>
    </div>
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <span class="icon"><img src="/images/acceuil.png" alt=""></span>
            <span>Accueil</span>
        </a>
        <a href="notes.php" class="nav-item active">
            <span class="icon"><img src="/images/notes.png" alt=""></span>
            <span>Notes</span>
        </a>
        <a href="finance.php" class="nav-item">
            <span class="icon"><img src="/images/finance.png" alt=""></span>
            <span>Finance</span>
        </a>
        <a href="planning.php" class="nav-item">
            <span class="icon"><img src="/images/planning.png" alt=""></span>
            <span>Planning</span>
        </a>
        <a href="logout.php" class="nav-item"><span>Deconnexion</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Notes & Performance</h1>
            <p class="page-subtitle">Bonjour <?= $_SESSION["user_name"] ?? "Etudiant" ?></p>
        </div>
        <button id="showAddGradeForm" class="btn-primary">+ Ajouter une note</button>
    </div>

    <?php if ($message !== ""): ?>
        <p class="form-message success"><?= $message ?></p>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-header">
                <span class="stat-label">Moyenne generale</span>
                <span class="stat-badge positive">Live</span>
            </div>
            <div class="stat-value"><?= round($avgGrade, 2) ?><span class="stat-max">/20</span></div>
            <div class="mini-chart">
                <div class="mini-bar mini-h-40"></div>
                <div class="mini-bar mini-h-52"></div>
                <div class="mini-bar mini-h-48"></div>
                <div class="mini-bar mini-h-63"></div>
                <div class="mini-bar mini-h-70"></div>
                <div class="mini-bar mini-h-78"></div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-header">
                <span class="stat-label">Credits ECTS</span>
                <span class="stat-badge info">DB</span>
            </div>
            <div class="stat-value"><?= $totalCredits ?><span class="stat-max">/60</span></div>
            <div class="progress-bar-stat">
                <div id="creditsProgress" class="progress-fill-stat" data-width="<?= min(100, ($totalCredits / 60) * 100) ?>"></div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-header">
                <span class="stat-label">Evaluations</span>
                <span class="stat-badge success">Total</span>
            </div>
            <div class="stat-value"><?= count($grades) ?><span class="stat-max"> notes</span></div>
            <p class="stat-note">Derniere mise a jour en temps reel.</p>
        </div>
    </div>

    <div id="addGradeForm" class="simple-form-card hidden">
        <form id="gradeForm" method="POST" action="add_grade.php">
            <input id="subject_name" type="text" name="subject_name" placeholder="Matiere (ex: Maths)">
            <input id="evaluation_name" type="text" name="evaluation_name" placeholder="Evaluation (ex: DS1)">
            <input id="evaluation_date" type="date" name="evaluation_date">
            <input id="coefficient" type="number" step="0.1" min="0.1" name="coefficient" placeholder="Coefficient">
            <input id="grade" type="number" step="0.01" min="0" max="20" name="grade" placeholder="Note /20">
            <button type="submit">Enregistrer</button>
        </form>
    </div>

    <div class="evaluations-section">
        <div class="section-header"><h2>Detail des evaluations</h2></div>
        <table class="evaluations-table">
            <thead>
            <tr>
                <th>Matiere</th>
                <th>Evaluation</th>
                <th>Date</th>
                <th>Coef.</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($grades) === 0): ?>
                <tr><td colspan="5">Aucune note enregistree.</td></tr>
            <?php else: ?>
                <?php foreach ($grades as $g): ?>
                    <tr>
                        <td><?= $g["subject_name"] ?></td>
                        <td><?= $g["evaluation_name"] ?></td>
                        <td><?= $g["evaluation_date"] ?></td>
                        <td><?= $g["coefficient"] ?></td>
                        <td><?= $g["grade"] ?>/20</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bottom-section">
        <div class="simulator-card">
            <h3>Simulateur de moyenne</h3>
            <p class="simulator-desc">Estimez rapidement votre moyenne finale.</p>
            <div class="projection-result">
                <span class="projection-label">Projection</span>
                <span class="projection-value"><?= round($avgGrade, 2) ?>/20</span>
            </div>
            <p class="projection-impact">Basé sur vos notes actuelles.</p>
        </div>

        <div class="trends-card">
            <div class="card-header">
                <h3>Tendances par matiere</h3>
            </div>
            <?php if (count($subjectTrends) === 0): ?>
                <div class="trend-item">
                    <div class="trend-label">Aucune donnee</div>
                    <div class="trend-value">Ajoutez des notes</div>
                </div>
            <?php else: ?>
                <?php foreach ($subjectTrends as $trend): ?>
                    <?php $barWidth = min(100, ($trend["avg_subject"] / 20) * 100); ?>
                    <div class="trend-item">
                        <div class="trend-label"><?= $trend["subject_name"] ?></div>
                        <div class="trend-value"><?= $trend["avg_subject"] ?>/20</div>
                        <div class="trend-bars">
                            <div class="trend-bar trend-w-40"></div>
                            <div class="trend-bar trend-w-55"></div>
                            <div class="trend-bar trend-w-65"></div>
                            <div class="trend-bar trend-w-75"></div>
                            <div class="trend-bar active trend-dynamic" data-width="<?= $barWidth ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const gradeBtn = document.getElementById("showAddGradeForm");
const gradeFormBlock = document.getElementById("addGradeForm");
gradeBtn.addEventListener("click", function () {
    gradeFormBlock.classList.toggle("hidden");
});

document.getElementById("gradeForm").addEventListener("submit", function (e) {
    const subject = document.getElementById("subject_name").value.trim();
    const evaluation = document.getElementById("evaluation_name").value.trim();
    const date = document.getElementById("evaluation_date").value;
    const coefficient = parseFloat(document.getElementById("coefficient").value);
    const grade = parseFloat(document.getElementById("grade").value);

    if (!subject || !evaluation || !date) {
        e.preventDefault();
        alert("Merci de remplir tous les champs.");
        return;
    }

    if (isNaN(coefficient) || coefficient <= 0) {
        e.preventDefault();
        alert("Coefficient invalide.");
        return;
    }

    if (isNaN(grade) || grade < 0 || grade > 20) {
        e.preventDefault();
        alert("La note doit etre entre 0 et 20.");
    }
});

const creditsProgress = document.getElementById("creditsProgress");
if (creditsProgress) {
    const width = parseFloat(creditsProgress.dataset.width || "0");
    creditsProgress.style.width = Math.max(0, Math.min(100, width)) + "%";
}

document.querySelectorAll(".trend-dynamic").forEach(function (el) {
    const width = parseFloat(el.dataset.width || "0");
    el.style.width = Math.max(0, Math.min(100, width)) + "%";
});
</script>
</body>
</html>

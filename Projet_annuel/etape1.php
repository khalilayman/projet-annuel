<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subjectName = trim($_POST["subject_name"] ?? "");
    if ($subjectName !== "") {
        $insert = $pdo->prepare("INSERT IGNORE INTO subjects (user_id, subject_name) VALUES (:user_id, :subject_name)");
        $insert->execute([
            "user_id" => $userId,
            "subject_name" => $subjectName
        ]);
        $message = "Matiere enregistree.";
    }
}

$subjectsStmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE user_id = :user_id ORDER BY id DESC");
$subjectsStmt->execute(["user_id" => $userId]);
$subjects = $subjectsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajoute tes matieres - Student Hub</title>
    <link rel="stylesheet" href="css/etape1.css">
</head>
<body>
    <div class="onboarding-container">
        <div class="progress-indicator">
            <div class="progress-bar active"></div>
            <div class="progress-bar active"></div>
            <div class="progress-bar"></div>
        </div>
        <p class="step-label">Etape 1 sur 3 • 33% complete</p>
        <h1>Ajoute tes <span class="highlight">matieres</span></h1>
        <p class="subtitle">Configure ton parcours academique pour recevoir des recommandations personnalisees.</p>
        <?php if ($message !== ""): ?>
            <p><?= $message ?></p>
        <?php endif; ?>

        <form method="POST" action="etape1.php">
            <input type="text" name="subject_name" class="subject-input" placeholder="Ajouter une matiere manuellement">
            <button class="add-manual-btn" type="submit">Ajouter une matiere manuellement</button>
        </form>

        <?php foreach ($subjects as $subject): ?>
            <div class="subject-card"><div class="subject-info"><h4><?= $subject["subject_name"] ?></h4></div><div class="add-icon">+</div></div>
        <?php endforeach; ?>

        <div class="navigation-buttons">
            <a href="login.php"><button class="btn-back"><img src="images/left.png" alt="">Precedent</button></a>
            <a href="etape2.php"><button class="btn-continue">Continuer</button></a>
        </div>
    </div>
    <footer>© STUDENT HUB HORIZON EDUCATION SYSTEMS</footer>
</body>
</html>

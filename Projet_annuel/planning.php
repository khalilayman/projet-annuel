<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = $_GET["message"] ?? "";

$stmt = $pdo->prepare("SELECT subject_name, created_at FROM subjects WHERE user_id = :user_id ORDER BY id DESC");
$stmt->execute(["user_id" => $userId]);
$subjects = $stmt->fetchAll();

$eventsStmt = $pdo->prepare(
    "SELECT event_title, event_type, event_day, start_time, end_time, event_location
     FROM events
     WHERE user_id = :user_id
     ORDER BY FIELD(event_day, 'LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'), start_time"
);
$eventsStmt->execute(["user_id" => $userId]);
$events = $eventsStmt->fetchAll();

$days = ["LUNDI", "MARDI", "MERCREDI", "JEUDI", "VENDREDI"];
$eventsByDay = [];
foreach ($days as $day) {
    $eventsByDay[$day] = [];
}
foreach ($events as $event) {
    $day = strtoupper($event["event_day"]);
    if (!isset($eventsByDay[$day])) {
        $eventsByDay[$day] = [];
    }
    $eventsByDay[$day][] = $event;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Planning - Student Hub</title>
    <link rel="stylesheet" href="css/planning.css">
</head>
<body>
<aside class="sidebar">
    <div class="logo-section">
        <div class="logo-icon"><img src="images/rocket.png" alt=""></div>
        <div class="logo-text">
            <h2>Student Hub</h2>
            <p>Student Life Manager</p>
        </div>
    </div>
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <span class="icon"><img src="/images/acceuil.png" alt=""></span>
            <span>Dashboard</span>
        </a>
        <a href="notes.php" class="nav-item">
            <span class="icon"><img src="/images/notes.png" alt=""></span>
            <span>Notes</span>
        </a>
        <a href="finance.php" class="nav-item">
            <span class="icon"><img src="/images/finance.png" alt=""></span>
            <span>Finances</span>
        </a>
        <a href="planning.php" class="nav-item active">
            <span class="icon"><img src="/images/planning.png" alt=""></span>
            <span>Planning</span>
        </a>
        <a href="logout.php" class="nav-item"><span>Deconnexion</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="planning-header">
        <div>
            <h1>Mon Planning</h1>
            <p class="week-subtitle">Semaine de cours</p>
        </div>
        <button id="showAddSubjectForm" class="btn-add-subject"><span class="icon">+</span> Ajouter une matiere</button>
    </div>

    <?php if ($message !== ""): ?>
        <p class="form-message success"><?= $message ?></p>
    <?php endif; ?>

    <div class="calendar-container">
        <?php foreach ($days as $index => $day): ?>
            <div class="day-column">
                <div class="day-header">
                    <span class="day-name"><?= $day ?></span>
                    <span class="day-number"><?= 23 + $index ?></span>
                </div>
                <div class="time-slots">
                    <?php if (count($eventsByDay[$day]) === 0): ?>
                        <div class="course-block blue">
                            <div class="course-time">--:-- - --:--</div>
                            <div class="course-title">Aucun cours</div>
                            <div class="course-location">Ajoutez une matiere</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($eventsByDay[$day] as $event): ?>
                            <div class="course-block <?= $event["event_title"] ?>" >
                                <div class="course-time"><?= substr($event["start_time"], 0, 5) ?> - <?= substr($event["end_time"], 0, 5) ?></div>
                                <div class="course-title"><?= $event["event_title"] ?></div>
                                <div class="course-location"><?= $event["event_location"] ?: "Salle non renseignee" ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="subjectModal" class="modal-overlay hidden">
        <div class="modal-card">
            <div class="modal-header">
                <h3><span class="modal-title-icon">◎</span> Ajouter une matiere</h3>
                <button id="closeModalBtn" type="button" class="modal-close">×</button>
            </div>
            <form id="subjectForm" method="POST" action="add_subject.php">
                <label>Nom de la matiere</label>
                <input id="subject_name" type="text" name="subject_name" placeholder="Ex: Intelligence Artificielle">

                <div class="row-two">
                    <div>
                        <label>Salle</label>
                        <input id="room" type="text" name="room" placeholder="Ex: Amphi 3">
                    </div>
                    <div>
                        <label>Professeur</label>
                        <input id="teacher" type="text" name="teacher" placeholder="Ex: Dr. Martin">
                    </div>
                </div>

                <label>Jour de la semaine</label>
                <select id="event_day" name="event_day">
                    <option value="LUNDI">Lundi</option>
                    <option value="MARDI">Mardi</option>
                    <option value="MERCREDI">Mercredi</option>
                    <option value="JEUDI">Jeudi</option>
                    <option value="VENDREDI">Vendredi</option>
                </select>

                <div class="row-two">
                    <div>
                        <label>Heure de debut</label>
                        <input id="start_time" type="time" name="start_time" value="08:00">
                    </div>
                    <div>
                        <label>Heure de fin</label>
                        <input id="end_time" type="time" name="end_time" value="10:00">
                    </div>
                </div>

                <label>Couleur</label>
                <div class="color-row">
                    <label class="color-choice"><input type="radio" name="event_type" value="course" checked><span class="dot blue"></span></label>
                    <label class="color-choice"><input type="radio" name="event_type" value="tp"><span class="dot green"></span></label>
                    <label class="color-choice"><input type="radio" name="event_type" value="exam"><span class="dot orange"></span></label>
                    <label class="color-choice"><input type="radio" name="event_type" value="project"><span class="dot pink"></span></label>
                    <label class="color-choice"><input type="radio" name="event_type" value="other"><span class="dot purple"></span></label>
                </div>

                <div class="modal-actions">
                    <button id="cancelModalBtn" type="button" class="btn-cancel">Annuler</button>
                    <button type="submit" class="btn-confirm">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
const btn = document.getElementById("showAddSubjectForm");
const modal = document.getElementById("subjectModal");
const closeBtn = document.getElementById("closeModalBtn");
const cancelBtn = document.getElementById("cancelModalBtn");
btn.addEventListener("click", function () {
    modal.classList.remove("hidden");
});
closeBtn.addEventListener("click", function () {
    modal.classList.add("hidden");
});
cancelBtn.addEventListener("click", function () {
    modal.classList.add("hidden");
});
modal.addEventListener("click", function (e) {
    if (e.target === modal) {
        modal.classList.add("hidden");
    }
});

document.getElementById("subjectForm").addEventListener("submit", function (e) {
    const subjectName = document.getElementById("subject_name").value.trim();
    const start = document.getElementById("start_time").value;
    const end = document.getElementById("end_time").value;
    if (!subjectName) {
        e.preventDefault();
        alert("Merci d'entrer le nom de la matiere.");
        return;
    }
    if (start >= end) {
        e.preventDefault();
        alert("Heure de fin invalide.");
    }
});
</script>
</body>
</html>

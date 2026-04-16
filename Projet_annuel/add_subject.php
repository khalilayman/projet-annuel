<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: planning.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$subjectName = trim($_POST["subject_name"] ?? "");
$room = trim($_POST["room"] ?? "");
$teacher = trim($_POST["teacher"] ?? "");
$eventDay = strtoupper(trim($_POST["event_day"] ?? "LUNDI"));
$startTime = trim($_POST["start_time"] ?? "08:00");
$endTime = trim($_POST["end_time"] ?? "10:00");
$eventType = trim($_POST["event_type"] ?? "course");

if ($subjectName === "") {
    header("Location: planning.php?message=" . urlencode("Matiere invalide."));
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (user_id, subject_name) VALUES (:user_id, :subject_name)");
    $stmt->execute([
        "user_id" => $userId,
        "subject_name" => $subjectName
    ]);

    $location = trim($room . " • " . $teacher, " •");
    $eventStmt = $pdo->prepare(
        "INSERT INTO events (user_id, event_title, event_type, event_location, event_day, start_time, end_time)
         VALUES (:user_id, :event_title, :event_type, :event_location, :event_day, :start_time, :end_time)"
    );
    $eventStmt->execute([
        "user_id" => $userId,
        "event_title" => $subjectName,
        "event_type" => $eventType,
        "event_location" => $location,
        "event_day" => $eventDay,
        "start_time" => $startTime . ":00",
        "end_time" => $endTime . ":00"
    ]);

    header("Location: planning.php?message=" . urlencode("Matiere ajoutee au planning."));
} catch (PDOException $e) {
    header("Location: planning.php?message=" . urlencode("Erreur lors de l'ajout."));
}
exit;
?>

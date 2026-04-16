<?php
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: planning.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $major = trim($_POST["major"] ?? "L1 Informatique");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($fullName === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caracteres.";
    } elseif ($password !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute(["email" => $email]);
        if ($check->fetch()) {
            $error = "Cet email existe deja.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (full_name, email, password, major) VALUES (:full_name, :email, :password, :major)");
            $insert->execute([
                "full_name" => $fullName,
                "email" => $email,
                "password" => $hashedPassword,
                "major" => $major
            ]);

            $newUserId = (int) $pdo->lastInsertId();
            $_SESSION["user_id"] = $newUserId;
            $_SESSION["user_name"] = $fullName;
            $_SESSION["major"] = $major;
            header("Location: etape1.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/connexion.css">
    <title>Inscription</title>
</head>
<body>
<div class="container">
    <div class="panneau_gauche">
        <div class="container2">
            <div class="logo">
                <img src="images/logo.png" alt="">
                <h1>Student Hub</h1>
            </div>
            <h2>Menez votre parcours universitaire.</h2>
            <p class="subtitle">Cree un compte rapidement pour commencer.</p>
        </div>
    </div>

    <div class="panneau_droite">
        <div class="tabs">
            <a class="tab" href="login.php">Connexion</a>
            <button class="tab active" type="button">Inscription</button>
        </div>

        <h2>Cree ton compte etudiant</h2>
        <p class="intro">Commence ton aventure...</p>

        <?php if ($error !== ""): ?>
            <p class="form-message error"><?= $error ?></p>
        <?php endif; ?>
        <?php if ($success !== ""): ?>
            <p class="form-message success"><?= $success ?></p>
        <?php endif; ?>

        <form id="signupForm" method="POST" action="inscription.php">
            <label>NOM COMPLET</label>
            <input id="full_name" type="text" name="full_name" placeholder="Nom complet">

            <label>EMAIL</label>
            <input id="email" type="email" name="email" placeholder="Email">

            <label>FILIERE</label>
            <input id="major" type="text" name="major" placeholder="L1 Informatique" value="L1 Informatique">

            <label>MOT DE PASSE</label>
            <input id="password" type="password" name="password" placeholder="Mot de passe">

            <label>CONFIRMER LE MOT DE PASSE</label>
            <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirmer le mot de passe">

            <button class="btn-primary" type="submit">Creer mon compte</button>
            <div class="login-link">
                <p>Deja un compte?</p>
                <a href="login.php">Connecte-toi</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById("signupForm").addEventListener("submit", function (e) {
    const fullName = document.getElementById("full_name").value.trim();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (!fullName || !email || !password || !confirmPassword) {
        e.preventDefault();
        alert("Merci de remplir tous les champs.");
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert("Email invalide.");
        return;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert("Le mot de passe doit contenir au moins 6 caracteres.");
        return;
    }

    if (password !== confirmPassword) {
        e.preventDefault();
        alert("Les mots de passe ne correspondent pas.");
    }
});
</script>
</body>
</html>

<?php
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, major FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(["email" => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["user_name"] = $user["full_name"];
            $_SESSION["major"] = $user["major"];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Email ou mot de passe incorrect.";
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
    <title>Connexion</title>
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
            <p class="subtitle">Connecte-toi pour gerer tes notes, matieres et planning.</p>
        </div>
    </div>

    <div class="panneau_droite">
        <div class="tabs">
            <button class="tab active" type="button">Connexion</button>
            <a class="tab" href="inscription.php">Inscription</a>
        </div>
        <h2>Connecte-toi a ton compte</h2>
        <p class="intro">Reprends ton aventure...</p>

        <?php if ($error !== ""): ?>
            <p class="form-message error"><?= $error ?></p>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <label>EMAIL</label>
            <input id="email" type="email" name="email" placeholder="Email">

            <label>MOT DE PASSE</label>
            <input id="password" type="password" name="password" placeholder="Mot de passe">

            <button class="btn-primary" type="submit">Se connecter</button>
            <div class="login-link">
                <p>Vous n'avez pas de compte?</p>
                <a href="inscription.php">Creer un compte</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById("loginForm").addEventListener("submit", function (e) {
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();

    if (!email || !password) {
        e.preventDefault();
        alert("Merci de remplir tous les champs.");
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert("Email invalide.");
    }
});
</script>
</body>
</html>

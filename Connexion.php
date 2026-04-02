<?php
// Démarrage de la session et inclusion des fonctions de gestion de fichiers json
session_start();
require_once "includes/json_data.php";

// Traitement du formulaire si POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Identifiants admin prédéfinis
    $adminEmail = "admin@site.com";
    $adminPassword = "admin123";

    // Vérification admin
    if ($email === $adminEmail && $password === $adminPassword) {
        $_SESSION['role'] = "admin";
        // Redirection vers la page admin
        header("Location: admin.php");
        exit;
    }

    // Vérification client via JSON
    $users = readJson("users.json");

    foreach ($users as $user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $_SESSION['role'] = "client";
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['email'] = $user['email'];

            // Redirection vers la page client
            header("Location: client.php");
            exit;
        }
    }

    // Si identifiants incorrects
    header("Location: connexion.php?error=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Paradise Island</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)),
                        url('images/ile3.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .login-header {
            background: #0d6efd;
            color: white;
            text-align: center;
            padding: 30px 20px 20px;
        }

        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .login-header p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
        }

        .btn-login {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }

        .back-link {
            text-decoration: none;
            font-size: 0.95rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="card login-card">
        <div class="login-header">
            <h1>Paradise Island</h1>
            <p>Connexion à votre espace</p>
        </div>

        <div class="card-body p-4">
            <!-- Message d'erreur si identifiants incorrects -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    Email ou mot de passe incorrect.
                </div>
            <?php endif; ?>

            <!-- Formulaire de connexion -->
            <form action="connexion.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Adresse email</label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        placeholder="exemple@mail.com" 
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="Votre mot de passe" 
                        required
                    >
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-login">
                        Se connecter
                    </button>
                </div>
            </form>

            <div class="text-center">
                <a href="index.php" class="back-link">← Retour à l’accueil</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
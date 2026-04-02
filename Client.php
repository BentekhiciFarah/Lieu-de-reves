<?php
session_start();
// Inclure les fonctions de gestion des données JSON
require_once "includes/json_data.php";

// Vérification connexion
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté ou n'est pas un client
    header("Location: connexion.php");
    exit;
}
// Récupérer l'email de l'utilisateur connecté
$email = $_SESSION['email'] ?? '';

// Charger les réservations
$reservations = readJson("reservation.json") ?: [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes réservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">

<div class="container">
    <h1>Bienvenue <?= htmlspecialchars($_SESSION['nom']) ?></h1>

    <h3 class="mt-4">Mes réservations</h3>

    <?php
    $found = false;

    // Afficher les réservations de l'utilisateur connecté
    foreach ($reservations as $res):
        // Vérifier si la réservation appartient à l'utilisateur connecté
        if (($res['email'] ?? '') === $email):
            // Marquer qu'une réservation a été trouvée
            $found = true;
    ?>

        <div class="card mb-3">
            <div class="card-body">
                <p><strong>Dates :</strong> <?= $res['date_debut'] ?> → <?= $res['date_fin'] ?></p>
                <p><strong>Chambre :</strong> <?= $res['type_chambre'] ?></p>
                <p><strong>Personnes :</strong> <?= $res['nb_personnes'] ?></p>

                <p><strong>Statut :</strong>
                    <?php if (strpos($res['statut'], 'attente') !== false): ?>
                        <span class="badge bg-warning text-dark">En attente</span>

                    <?php elseif (strpos($res['statut'], 'valid') !== false): ?>
                        <span class="badge bg-success">Validée</span>

                    <?php else: ?>
                        <span class="badge bg-danger">Refusée</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

    <?php
        endif;
    endforeach;
    ?>

    <?php if (!$found): ?>
        <div class="alert alert-info">Aucune réservation trouvée.</div>
    <?php endif; ?>

</div>

</body>
</html>
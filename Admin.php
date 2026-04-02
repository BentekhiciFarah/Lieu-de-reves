<?php
session_start();
require_once "includes/json_data.php";

// Vérification accès admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: connexion.php");
    exit;
}

// Message admin après redirection
$messageAdmin = $_SESSION['message_admin'] ?? "";
unset($_SESSION['message_admin']);

// Nombre total de chambres
$chambresDisponibles = [
    "bungalow" => 5,
    "villa" => 3,
    "suite" => 2
];

// Lecture des réservations
$reservations = readJson("reservation.json") ?: [];

// Lecture des prestations clients
$prestations_client = readJson("prestations_client.json") ?: [];

// Calcul chambres réservées
$chambresReservees = [
    "bungalow" => 0,
    "villa" => 0,
    "suite" => 0
];
foreach ($reservations as $r) {
    if (
        isset($r['type_chambre'], $r['statut']) &&
        $r['statut'] === 'validée' &&
        isset($chambresReservees[$r['type_chambre']])
    ) {
        $chambresReservees[$r['type_chambre']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gestion des réservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">

<div class="container">
    <h1 class="mb-4">Gestion des réservations</h1>

    <!-- Affichage message admin -->
    <?php if (!empty($messageAdmin)): ?>
        <div class="alert alert-info">
            <pre class="mb-0"><?= htmlspecialchars($messageAdmin) ?></pre>
        </div>
    <?php endif; ?>

    <!-- État des chambres -->
    <h3>État des chambres</h3>
    <table class="table table-bordered bg-white">
        <thead class="table-dark">
            <tr>
                <th>Type de chambre</th>
                <th>Réservées</th>
                <th>Disponibles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($chambresDisponibles as $type => $total): ?>
                <tr>
                    <td><?= ucfirst($type) ?></td>
                    <td><?= $chambresReservees[$type] ?></td>
                    <td><?= $total - $chambresReservees[$type] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr class="my-4">

    <!-- Affichage des réservations classiques en attente -->
    <h3>Demandes de réservation en attente</h3>
    <?php
    $enAttente = false;
    foreach ($reservations as $res):
        if (($res['statut'] ?? '') === 'en_attente'):
            $enAttente = true;
    ?>
        <div class="card mb-3">
            <div class="card-body">
                <p><strong>Nom :</strong> <?= htmlspecialchars($res['nom'] ?? '') ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($res['email'] ?? '') ?></p>
                <p><strong>Dates :</strong> <?= htmlspecialchars($res['date_debut'] ?? '') ?> → <?= htmlspecialchars($res['date_fin'] ?? '') ?></p>
                <p><strong>Type chambre :</strong> <?= htmlspecialchars($res['type_chambre'] ?? '') ?></p>
                <p><strong>Nombre de personnes :</strong> <?= htmlspecialchars($res['nb_personnes'] ?? '') ?></p>
                <p><strong>Activités :</strong>
                    <?php
                    if (!empty($res['activites']) && is_array($res['activites'])) {
                        echo htmlspecialchars(implode(", ", $res['activites']));
                    } else {
                        echo "Aucune";
                    }
                    ?>
                </p>

                <form action="admin.php" method="POST" class="d-inline">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                    <button type="submit" name="action" value="valider" class="btn btn-success btn-sm">Valider</button>
                    <button type="submit" name="action" value="refuser" class="btn btn-danger btn-sm">Refuser</button>
                </form>
            </div>
        </div>
    <?php
        endif;
    endforeach;
    ?>

    <?php if (!$enAttente): ?>
        <div class="alert alert-secondary">
            Aucune demande en attente.
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <!-- Affichage des prestations en attente -->
    <h3>Demandes de prestations / activités en attente</h3>
    <?php
        $prestationsEnAttente = false;
    // Parcours des prestations demandées par les clients
    foreach ($prestations_client as &$pc):
        // Correction : accepter à la fois 'attente' et 'en_attente'
        if (in_array($pc['statut'] ?? '', ['attente', 'en_attente'])):
            $prestationsEnAttente = true;

            // Récupérer les valeurs existantes pour éviter les erreurs
            $prestationId = $pc['prestation']['id'] ?? '';
            $adresse = $pc['adresse'] ?? '';
            $heure = $pc['heure'] ?? '';
    ?>
        <div class="card mb-3">
            <div class="card-body">

                <p><strong>Client :</strong> <?= htmlspecialchars($pc['user_email'] ?? 'Email inconnu') ?></p>
                <p><strong>Prestation :</strong> <?= htmlspecialchars($pc['prestation']['name'] ?? 'Nom inconnu') ?></p>
                <p><strong>Description :</strong> <?= htmlspecialchars($pc['prestation']['description'] ?? '') ?></p>

                <!-- Formulaire pour que l'admin valide ou refuse -->
                <form action="Admin.php" method="POST" class="d-inline">
                    <input type="hidden" name="prestation_id" value="<?= htmlspecialchars($prestationId) ?>">

                    <!-- Saisie manuelle de l'adresse par l'admin -->
                    <div class="mb-2">
                        <label for="adresse_<?= $prestationId ?>">Adresse :</label>
                        <input type="text" id="adresse_<?= $prestationId ?>" name="adresse" value="<?= htmlspecialchars($adresse) ?>" class="form-control" required>
                    </div>

                    <!-- Saisie manuelle de l'heure par l'admin -->
                    <div class="mb-2">
                        <label for="heure_<?= $prestationId ?>">Heure :</label>
                        <input type="time" id="heure_<?= $prestationId ?>" name="heure" value="<?= htmlspecialchars($heure) ?>" class="form-control" required>
                    </div>

                    <!-- Boutons valider/refuser -->
                    <button type="submit" name="action" value="valider" class="btn btn-success btn-sm">Valider</button>
                    <button type="submit" name="action" value="refuser" class="btn btn-danger btn-sm">Refuser</button>
                </form>
            </div>
        </div>
    <?php
        endif;
    endforeach;
    unset($pc);
    ?>

    <?php if (!$prestationsEnAttente): ?>
        <div class="alert alert-secondary">
            Aucune demande de prestation en attente.
        </div>
    <?php endif; ?>

</div>

</body>
</html>
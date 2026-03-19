<?php
session_start();
require_once "includes/json_data.php"; // readJson(), writeJson(), generateId()

// Vérification accès admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    // Redirection vers la page de connexion si pas admin
    header("Location: connexion.php");
    exit;
}

// Définir le nombre de chambres par type
$chambresDisponibles = [
    "bungalow" => 5,
    "villa" => 3,
    "suite" => 2
];

// --- Traitement des actions POST ---
$messageAdmin = ""; // Message à afficher à l'admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'ID de la réservation et l'action (valider/refuser)
    $reservationId = $_POST['reservation_id'] ?? '';
    $action = $_POST['action'] ?? '';

    // Lire les réservations et les utilisateurs
    $reservations = readJson("reservation.json") ?: [];
    $users = readJson("users.json") ?: [];

    // Trouver la réservation correspondante
    foreach ($reservations as &$res) {
        // Si c'est la bonne réservation
        if ($res['id'] === $reservationId) {
            // Si validé
            if ($action === 'valider') {

                // Vérifier s'il reste des chambres libres
                $type = $res['type_chambre'];
                $countReserved = 0;
                foreach ($reservations as $r) {
                    if ($r['type_chambre'] === $type && $r['statut'] === 'validée') {
                        $countReserved++;
                    }
                }
                // Si pas de chambres disponibles, refuser et afficher message
                if ($countReserved >= $chambresDisponibles[$type]) {
                    $messageAdmin = "Impossible de valider : plus de chambres disponibles pour $type.";
                } else {
                    // Valider réservation
                    $res['statut'] = 'validée';

                    // Création du compte client
                    $motDePasse = bin2hex(random_bytes(5)); // mot de passe aléatoire
                    $users[] = [
                        "id" => generateId(),
                        "nom" => $res['nom'],
                        "email" => $res['email'],
                        "password" => $motDePasse
                    ];
                    writeJson("users.json", $users);

                    // Préparer le message type pour l'admin
                    $messageAdmin = "Réservation validée pour {$res['nom']} ({$res['email']})\n"
                                  . "Mot de passe temporaire : $motDePasse\n"
                                  . "Merci d'envoyer ce mot de passe au client.";
                }
            
            // Si refusé
            } elseif ($action === 'refuser') {
                $res['statut'] = 'refusée';
                $messageAdmin = "Réservation refusée pour {$res['nom']} ({$res['email']})";
            }
            break;
        }
    }
    // Libérer la variable de référence
    unset($res);

    // Sauvegarder les modifications
    writeJson("reservation.json", $reservations);
    // Redirection pour éviter le resubmission du formulaire
    header("Location: admin.php");
    exit;
}

// Lecture des réservations pour affichage
$reservations = readJson("reservation.json") ?: [];

// Calcul du nombre de chambres déjà réservées
$chambresReservees = [
    "bungalow" => 0,
    "villa" => 0,
    "suite" => 0
];

// Compter les réservations validées par type de chambre
foreach ($reservations as $r) {
    if ($r['statut'] === 'validée') {
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
<body class="p-4">

<h1>Gestion des réservations</h1>

<!-- Affichage du message type pour l'admin -->
<?php if (!empty($messageAdmin)): ?>
    <div class="alert alert-info">
        <pre><?= htmlspecialchars($messageAdmin) ?></pre>
    </div>
<?php endif; ?>

<!-- Tableau des chambres disponibles / réservées -->
<h3>État des chambres</h3>
<table class="table table-bordered w-50">
    <thead>
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

<hr>

<h3>Demandes de réservation en attente</h3>

<?php foreach ($reservations as $res): ?>
    <?php if ($res['statut'] === 'en_attente'): ?>
        <div class="reservation-card border p-3 mb-3">
            <p><strong>Nom :</strong> <?= htmlspecialchars($res['nom']) ?></p>
            <p><strong>Email :</strong> <?= htmlspecialchars($res['email']) ?></p>
            <p><strong>Dates :</strong> <?= $res['date_debut'] ?> → <?= $res['date_fin'] ?></p>
            <p><strong>Type chambre :</strong> <?= $res['type_chambre'] ?></p>
            <p><strong>Nombre de personnes :</strong> <?= $res['nb_personnes'] ?></p>
            <p><strong>Activités :</strong> <?= implode(", ", $res['activites'] ?? []) ?></p>

            <form action="admin.php" method="POST" class="d-inline">
                <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                <button type="submit" name="action" value="valider" class="btn btn-success btn-sm">Valider</button>
                <button type="submit" name="action" value="refuser" class="btn btn-danger btn-sm">Refuser</button>
            </form>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>
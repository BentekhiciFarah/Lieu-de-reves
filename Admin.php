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

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationId = $_POST['reservation_id'] ?? '';
    $action = $_POST['action'] ?? '';

    $reservations = readJson("reservation.json") ?: [];
    $users = readJson("users.json") ?: [];

    foreach ($reservations as &$res) {
        if ($res['id'] === $reservationId) {

            if ($action === 'valider') {
                $type = $res['type_chambre'];
                $countReserved = 0;

                foreach ($reservations as $r) {
                    if (
                        isset($r['type_chambre'], $r['statut']) &&
                        $r['type_chambre'] === $type &&
                        $r['statut'] === 'validée'
                    ) {
                        $countReserved++;
                    }
                }

                if (!isset($chambresDisponibles[$type])) {
                    $messageAdmin = "Type de chambre inconnu : " . htmlspecialchars($type);
                } elseif ($countReserved >= $chambresDisponibles[$type]) {
                    $messageAdmin = "Impossible de valider : plus de chambres disponibles pour $type.";
                } else {
                    $res['statut'] = 'validée';

                    // Vérifier si l'utilisateur existe déjà
                    $userExiste = false;
                    foreach ($users as $u) {
                        if (($u['email'] ?? '') === $res['email']) {
                            $userExiste = true;
                            break;
                        }
                    }

                    if (!$userExiste) {
                        $motDePasse = bin2hex(random_bytes(5));

                        $users[] = [
                            "id" => generateId(),
                            "nom" => $res['nom'],
                            "email" => $res['email'],
                            "password" => $motDePasse,
                            "role" => "client"
                        ];

                        writeJson("users.json", $users);

                        $messageAdmin = "Réservation validée pour {$res['nom']} ({$res['email']})\n"
                                      . "Mot de passe temporaire : $motDePasse\n"
                                      . "Merci d'envoyer ce mot de passe au client.";
                    } else {
                        $messageAdmin = "Réservation validée pour {$res['nom']} ({$res['email']}).\n"
                                      . "Le client possède déjà un compte.";
                    }
                }

            } elseif ($action === 'refuser') {
                $res['statut'] = 'refusée';
                $messageAdmin = "Réservation refusée pour {$res['nom']} ({$res['email']})";
            }

            break;
        }
    }
    unset($res);

    writeJson("reservation.json", $reservations);

    $_SESSION['message_admin'] = $messageAdmin;
    header("Location: admin.php");
    exit;
}

// Lecture des réservations
$reservations = readJson("reservation.json") ?: [];

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

    <?php if (!empty($messageAdmin)): ?>
        <div class="alert alert-info">
            <pre class="mb-0"><?= htmlspecialchars($messageAdmin) ?></pre>
        </div>
    <?php endif; ?>

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

    <h3>Réservations validées</h3>

    <?php
    $validees = false;
    foreach ($reservations as $res):
        if (($res['statut'] ?? '') === 'validée'):
            $validees = true;
    ?>
        <div class="card mb-3 border-success">
            <div class="card-body">
                <p><strong>Nom :</strong> <?= htmlspecialchars($res['nom']) ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($res['email']) ?></p>
                <p><strong>Dates :</strong> <?= $res['date_debut'] ?> → <?= $res['date_fin'] ?></p>
                <p><strong>Chambre :</strong> <?= $res['type_chambre'] ?></p>
                <span class="badge bg-success">Validée</span>
            </div>
        </div>
    <?php
        endif;
    endforeach;
    ?>

    <?php if (!$validees): ?>
        <div class="alert alert-secondary">Aucune réservation validée.</div>
    <?php endif; ?>
</div>

</body>
</html>
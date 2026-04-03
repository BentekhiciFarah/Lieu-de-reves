<?php
session_start();
require_once "includes/json_data.php";



// Vérification accès admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: Connexion.php");
    exit;
}

// Message admin après redirection
$messageAdmin = $_SESSION['message_admin'] ?? "";
unset($_SESSION['message_admin']);

$activitiesData = readJson("activities.json") ?: [];
 // Création d'une map id → nom pour les activités
$activitiesMap = [];

// Normalisation des activités pour éviter les erreurs d'affichage
foreach ($activitiesData as $act) {
    $activitiesMap[$act['id']] = $act['nom'];
}


// Traitement du formulaire de validation/refus de réservation

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action'])) {
    $reservationId = $_POST['reservation_id'];
    $action = $_POST['action'];

    $reservations = readJson("reservation.json");
    $users = readJson("users.json");

    // Nombre total de chambres disponibles par type
    $chambresDisponibles = [
        'bungalow' => 5,
        'villa' => 3,
        'suite' => 2
    ];

    $messageAdmin = "Aucune action effectuée.";
    $reservationTrouvee = false;

    foreach ($reservations as &$res) {
        if (($res['id'] ?? '') == $reservationId) {
            $reservationTrouvee = true;

            $type = strtolower(trim($res['type_chambre'] ?? ''));

            // Normalisation des types
            if ($type === 'bungalow sur pilotis' || $type === 'bungalow') {
                $type = 'bungalow';
            } elseif ($type === 'villa sur la plage' || $type === 'villa') {
                $type = 'villa';
            } elseif ($type === 'suite avec piscine privée' || $type === 'suite') {
                $type = 'suite';
            }

            if ($action === 'valider') {
                // Compter les chambres déjà validées du même type
                $countReserved = 0;
                foreach ($reservations as $r) {
                    $typeR = strtolower(trim($r['type_chambre'] ?? ''));

                    if ($typeR === 'bungalow sur pilotis' || $typeR === 'bungalow') {
                        $typeR = 'bungalow';
                    } elseif ($typeR === 'villa sur la plage' || $typeR === 'villa') {
                        $typeR = 'villa';
                    } elseif ($typeR === 'suite avec piscine privée' || $typeR === 'suite') {
                        $typeR = 'suite';
                    }

                    if (
                        $typeR === $type &&
                        ($r['statut'] ?? '') === 'validée' &&
                        ($r['id'] ?? '') != $reservationId
                    ) {
                        $countReserved++;
                    }
                }

                if (!isset($chambresDisponibles[$type])) {
                    $messageAdmin = "Type de chambre inconnu pour cette réservation.";
                } elseif ($countReserved >= $chambresDisponibles[$type]) {
                    $messageAdmin = "Impossible de valider : plus de chambres disponibles pour $type.";
                } else {
                    // Valider la réservation
                    $res['statut'] = 'validée';

                    // Vérifier si le client existe déjà
                    $userExiste = false;
                    foreach ($users as $user) {
                        if (($user['email'] ?? '') === ($res['email'] ?? '')) {
                            $userExiste = true;
                            break;
                        }
                    }

                    if (!$userExiste) {
                        // Générer un mot de passe temporaire
                        $motDePasse = bin2hex(random_bytes(5));

                        // Créer le compte client
                        $users[] = [
                            "id" => generateId($users),
                            "nom" => $res['nom'] ?? '',
                            "prenom" => "",
                            "email" => $res['email'] ?? '',
                            "password" => password_hash($motDePasse, PASSWORD_DEFAULT),
                            "role" => "client"
                        ];

                        writeJson("users.json", $users);

                        $messageAdmin = "Réservation validée pour {$res['nom']} ({$res['email']}). "
                                      . "Mot de passe temporaire : {$motDePasse}. "
                                      . "Merci d'envoyer ce mot de passe au client.";
                    } else {
                        $messageAdmin = "Réservation validée pour {$res['nom']} ({$res['email']}). "
                                      . "Le client possède déjà un compte.";
                    }
                }

                        } elseif ($action === 'refuser') {
                $res['statut'] = 'refusée';
                $messageAdmin = "Réservation refusée pour {$res['nom']} ({$res['email']}).";

            } elseif ($action === 'maj_arrhes') {
                $montantArrhes = (float)($_POST['arrhes'] ?? 0);

                if ($montantArrhes < 0) {
                    $montantArrhes = 0;
                }

                $res['arrhes'] = $montantArrhes;
                $messageAdmin = "Arrhes enregistrées pour {$res['nom']} ({$res['email']}) : {$montantArrhes} €.";

            } elseif ($action === 'maj_reduction') {
                $reduction = (int)($_POST['reduction_prestations'] ?? 0);

                if (!in_array($reduction, [0, 10, 20, 50])) {
                    $reduction = 0;
                }

                $res['reduction_prestations'] = $reduction;
                $messageAdmin = "Réduction sur prestations enregistrée pour {$res['nom']} ({$res['email']}) : -{$reduction}%";
            }

            break;
        }
    }
    unset($res);

    if ($reservationTrouvee) {
        writeJson("reservation.json", $reservations);
    } else {
        $messageAdmin = "Réservation introuvable.";
    }

    $_SESSION['message_admin'] = $messageAdmin;

    header("Location: Admin.php");
    exit;
}


// AJAX pour mise à jour du statut prestation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prestation_id'], $_POST['action']) 
    && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    $prestationId = $_POST['prestation_id'];
    $action = $_POST['action'];
    $adresse = $_POST['adresse'] ?? '';
    $heure = $_POST['heure'] ?? '';

    $prestations_client = readJson("prestations_client.json") ?: [];
    $updated = false;
    $nouveau_statut = '';

    foreach ($prestations_client as &$pc) {
        if (($pc['prestation']['id'] ?? '') == $prestationId) {
            if ($action === 'valider') {
                $pc['statut'] = 'validée';
                $pc['adresse'] = $adresse;
                $pc['heure'] = $heure;
                $nouveau_statut = 'validée';
                $updated = true;
                $message = "Prestation validée";
            } elseif ($action === 'refuser') {
                $pc['statut'] = 'refusée';
                $pc['adresse'] = '';
                $pc['heure'] = '';
                $nouveau_statut = 'refusée';
                $updated = true;
                $message = "Prestation refusée";
            }
            break;
        }
    }
    unset($pc);

    if ($updated) {
        writeJson("prestations_client.json", $prestations_client);
        echo json_encode(["success" => true, "message" => $message, "nouveau_statut" => $nouveau_statut]);
    } else {
        echo json_encode(["success" => false, "message" => "Prestation introuvable"]);
    }
    exit; // terminer le script pour AJAX
}
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
    $type = strtolower(trim($r['type_chambre'] ?? ''));

    if ($type === 'bungalow sur pilotis' || $type === 'bungalow') {
        $type = 'bungalow';
    } elseif ($type === 'villa sur la plage' || $type === 'villa') {
        $type = 'villa';
    } elseif ($type === 'suite avec piscine privée' || $type === 'suite') {
        $type = 'suite';
    }

    if (($r['statut'] ?? '') === 'validée' && isset($chambresReservees[$type])) {
        $chambresReservees[$type]++;
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        $(".prestation-form").on("submit", function(e){
            e.preventDefault();
            const form = $(this);
            const prestationId = form.find("input[name='prestation_id']").val();
            const action = $(document.activeElement).val(); // bouton cliqué
            const adresse = form.find("input[name='adresse']").val();
            const heure = form.find("input[name='heure']").val();

            $.ajax({
                url: "Admin.php",
                method: "POST",
                dataType: "json",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                data: { prestation_id: prestationId, action: action, adresse: adresse, heure: heure },
                success: function(res){
                    alert(res.message);
                    if(res.success){
                        // mettre à jour le statut visible
                        form.closest(".card-body").find("p strong:contains('Prestation')").next().text(res.nouveau_statut);
                        form.remove(); // supprimer formulaire après action
                    }
                },
                error: function(){
                    alert("Erreur AJAX");
                }
            });
        });
    });
</script>
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

                            $nomsActivites = [];

                            foreach ($res['activites'] as $id) {
                                if (isset($activitiesMap[$id])) {
                                    $nomsActivites[] = $activitiesMap[$id];
                                } else {
                                    $nomsActivites[] = "Activité inconnue";
                                }
                            }

                            echo htmlspecialchars(implode(", ", $nomsActivites));

                        } else {
                            echo "Aucune";
                        }
                    ?>
                </p>

                <form action="Admin.php" method="POST" class="d-inline">
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
                <form class="d-inline prestation-form">
                    <input type="hidden" name="prestation_id" value="<?= htmlspecialchars($prestationId) ?>">
                    <div class="mb-2">
                        <label for="adresse_<?= $prestationId ?>">Adresse :</label>
                        <input type="text" id="adresse_<?= $prestationId ?>" name="adresse" value="<?= htmlspecialchars($adresse) ?>" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label for="heure_<?= $prestationId ?>">Heure :</label>
                        <input type="time" id="heure_<?= $prestationId ?>" name="heure" value="<?= htmlspecialchars($heure) ?>" class="form-control" required>
                    </div>
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


    <h3>Réservations validées</h3>
    <?php
    $valides = false;
    foreach ($reservations as $res):
        if (($res['statut'] ?? '') === 'validée'):
            $valides = true;

            $arrhes = (float)($res['arrhes'] ?? 0);
            $reductionPrestations = (int)($res['reduction_prestations'] ?? 0);
    ?>
        <div class="card mb-3">
            <div class="card-body">
                <p><strong>Nom :</strong> <?= htmlspecialchars($res['nom'] ?? '') ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($res['email'] ?? '') ?></p>
                <p><strong>Dates :</strong> <?= htmlspecialchars($res['date_debut'] ?? '') ?> → <?= htmlspecialchars($res['date_fin'] ?? '') ?></p>
                <p><strong>Type chambre :</strong> <?= htmlspecialchars($res['type_chambre'] ?? '') ?></p>
                <p><strong>Nombre de personnes :</strong> <?= htmlspecialchars($res['nb_personnes'] ?? '') ?></p>
                <p><strong>Enregistrer l'avance :</strong> <?= number_format($arrhes, 2, ',', ' ') ?> €</p>
                <p><strong>Réduction actuelle sur prestations :</strong> <?= $reductionPrestations ?> %</p>

                <div class="row">
                    <div class="col-md-6">
                        <form action="Admin.php" method="POST" class="border rounded p-3 bg-light mb-2">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                            <label class="form-label"><strong>Enregistrer l'avance</strong></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="arrhes"
                                class="form-control mb-2"
                                value="<?= htmlspecialchars($arrhes) ?>"
                            >
                            <button type="submit" name="action" value="maj_arrhes" class="btn btn-primary btn-sm">
                                Enregistrer l'avance
                            </button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <form action="Admin.php" method="POST" class="border rounded p-3 bg-light mb-2">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                            <label class="form-label"><strong>Réduction sur prestations</strong></label>
                            <select name="reduction_prestations" class="form-select mb-2">
                                <option value="0" <?= ($reductionPrestations === 0) ? 'selected' : '' ?>>0%</option>
                                <option value="10" <?= ($reductionPrestations === 10) ? 'selected' : '' ?>>10%</option>
                                <option value="20" <?= ($reductionPrestations === 20) ? 'selected' : '' ?>>20%</option>
                                <option value="50" <?= ($reductionPrestations === 50) ? 'selected' : '' ?>>50%</option>
                            </select>
                            <button type="submit" name="action" value="maj_reduction" class="btn btn-warning btn-sm">
                                Enregistrer réduction
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php
        endif;
    endforeach;
    ?>

    <?php if (!$valides): ?>
        <div class="alert alert-secondary">
            Aucune réservation validée.
        </div>
    <?php endif; ?>

    <hr class="my-4">

</body>
</html>
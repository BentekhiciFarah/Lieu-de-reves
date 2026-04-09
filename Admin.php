<?php
/*
* Admin.php : page d'administration pour la gestion des réservations, accessible uniquement aux utilisateurs avec le rôle "admin".
* Permet de valider ou refuser les demandes de réservation, d'enregistrer une avance et une réduction sur prestations pour les réservations validées.
* Affiche également un tableau de bord avec le nombre de chambres réservées et disponibles, et une section pour les demandes d'activités en attente.
*/
session_start();
// Inclure le fichier de gestion des données JSON pour lire et écrire les données de réservation, utilisateurs, prestations, etc.
require_once "includes/json_data.php";


// Redirection vers la page de connexion si l'utilisateur n'est pas admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: Connexion.php");
    exit;
}

// Récupération du message de session (s'il existe) pour l'afficher une seule fois dans la page admin,
// puis suppression de ce message de la session
$messageAdmin = $_SESSION['message_admin'] ?? "";
unset($_SESSION['message_admin']);


/* 
 * deux paramètres : reservation_id et action.
 * Il gère quatre actions distinctes : valider, refuser, maj_avance, maj_reduction.
 *
 * A la fin, selon que la requête vient d'AJAX ou d'un formulaire classique,
 * il répond différemment :
 *   - AJAX    : retourne du JSON et termine avec exit()
 *   - Classique : stocke le message en session et redirige
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action'])) {
    // récupérer les paramètres de la requête POST
    $reservationId = $_POST['reservation_id'];
    $action = $_POST['action'];

    // Lire les réservations et les utilisateurs depuis les fichiers JSON pour modification
    $reservations = readJson("reservation.json"); 
    $users = readJson("users.json"); // Lecture des utilisateurs pour éventuellement créer un compte client lors de la validation d'une réservation

    // Nombre total de chambres pour les vérifications de validation
    $chambresDisponibles = [
        'bungalow' => 10,
        'villa' => 8,
        'suite' => 10
    ];

    // initialisation du msg admin pour le retour d'information à afficher après l'action
    $messageAdmin = "Aucune action effectuée.";
    // flag pour vérifier si la réservation ciblée par l'action a été trouvée
    $reservationTrouvee = false;

    // Parcourir les réservations pour trouver celle à modifier et appliquer l'action demandée (valider/refuser/maj_arrhes/maj_reduction)
    foreach ($reservations as &$res) {
        // Vérification de l'ID de réservation pour trouver la réservation ciblée par l'action
        if (($res['id'] ?? '') == $reservationId) {
            $reservationTrouvee = true;

            // // Récupérer le type de chambre de la réservation en cours
            $type = strtolower(trim($res['type_chambre'] ?? ''));
            
            // Action de validation : vérifier la disponibilité des chambres du même type avant de valider
            if ($action === 'valider') {
                // Compter les chambres déjà validées du même type
                $countReserved = 0;
                foreach ($reservations as $r) {
                    $typeR = strtolower(trim($r['type_chambre'] ?? ''));

                    // Incrémenter le compteur si une réservation du même type est déjà validée (et ce n'est pas la réservation en cours de validation)
                    if (
                        $typeR === $type &&
                        ($r['statut'] ?? '') === 'validée' &&
                        ($r['id'] ?? '') != $reservationId
                    ) {
                        $countReserved++;
                    }
                }

                // Vérifier la disponibilité avant de valider la réservation
                if (!isset($chambresDisponibles[$type])) {
                    $messageAdmin = "Type de chambre inconnu pour cette réservation.";
                } elseif ($countReserved >= $chambresDisponibles[$type]) {
                    $messageAdmin = "Impossible de valider : plus de chambres disponibles pour $type.";
                } else {
                    // Valider la réservation
                    $res['statut'] = 'validée';

                    // Vérifier si le client existe déjà pour éviter de créer un compte en double
                    $userExiste = false;
                    foreach ($users as $user) {
                        if (($user['email'] ?? '') === ($res['email'] ?? '')) {
                            $userExiste = true;
                            break;
                        }
                    }

                    if (!$userExiste) {
                        // Générer un mot de passe temporaire pour le client (10 caractères aléatoires)
                        $motDePasse = bin2hex(random_bytes(5));

                        // Créer le compte client avec les informations de la réservation et le mot de passe temporaire
                        $users[] = [
                            "id" => generateId($users),
                            "nom" => $res['nom'] ?? '',
                            "prenom" => "",
                            "email" => $res['email'] ?? '',
                            "password" => password_hash($motDePasse, PASSWORD_DEFAULT), // Hasher le mot de passe pour la sécurité
                            "role" => "client"
                        ];

                        writeJson("users.json", $users); // Enregistrer le nouveau compte client dans le fichier JSON

                        // Message d'information pour l'admin avec le mot de passe temporaire à transmettre au client
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

            } elseif ($action === 'maj_arrhes') { // Mise à jour du montant des arrhes (avance) pour une réservation validée
                $montantArrhes = (float)($_POST['arrhes'] ?? 0); // Récupérer le montant des arrhes depuis le formulaire, avec une valeur par défaut de 0 si non fourni

                if ($montantArrhes < 0) {
                    $montantArrhes = 0;
                }

                $res['arrhes'] = $montantArrhes;
                $messageAdmin = "Arrhes enregistrées pour {$res['nom']} ({$res['email']}) : {$montantArrhes} €.";

            } elseif ($action === 'maj_reduction') { // Mise à jour du pourcentage de réduction sur prestations pour une réservation validée
                $reduction = (int)($_POST['reduction_prestations'] ?? 0); // Récupérer le pourcentage de réduction depuis le formulaire

                if (!in_array($reduction, [0, 10, 20, 50])) { // Vérifier que la réduction est bien dans les valeurs autorisées (0%, 10%, 20%, 50%)
                    $reduction = 0;
                }

                // Enregistrer la réduction dans la réservation
                $res['reduction_prestations'] = $reduction;
                $messageAdmin = "Réduction sur prestations enregistrée pour {$res['nom']} ({$res['email']}) : -{$reduction}%";
            }

            break;
        }
    }
    unset($res); // Briser la référence pour éviter les modifications involontaires après la boucle


    // Si la réservation ciblée par l'action a été trouvée et modifiée, enregistrer les changements dans le fichier JSON
    if ($reservationTrouvee) {
        writeJson("reservation.json", $reservations);
    } else {
        $messageAdmin = "Réservation introuvable.";
    }


    // Si la requête est une requête AJAX (indiquée par l'en-tête X-Requested-With), 
    //retourner une réponse JSON avec le résultat de l'action et les chambres réservées mises à jour 
    //pour rafraîchir le tableau sans recharger la page 

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        // Recalculer les chambres réservées pour mettre à jour le tableau
        $resAfter = readJson("reservation.json") ?: [];
        $chRes = ['bungalow' => 0, 'villa' => 0, 'suite' => 0];
        // Parcourir les réservations pour compter les chambres réservées de chaque type après la modification
        foreach ($resAfter as $r) {
            $t = strtolower(trim($r['type_chambre'] ?? ''));
            if (($r['statut'] ?? '') === 'validée' && isset($chRes[$t])) $chRes[$t]++;
        }
        echo json_encode([
            'success'           => $reservationTrouvee,
            'message'           => $messageAdmin,
            'chambres_reservees' => $chRes
        ]);
        exit;
    }

    $_SESSION['message_admin'] = $messageAdmin;
    header("Location: Admin.php");
    exit;
}

// Nombre total de chambres
$chambresDisponibles = [
    "bungalow" => 10,
    "villa" => 8,
    "suite" => 10
];

// Lecture des réservations pour affichage et calcul des chambres réservées
$reservations = readJson("reservation.json") ?: [];

// Handler AJAX GET : retourne les réservations validées pour affichage dans la section correspondante
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['section']) && $_GET['section'] === 'validees') {
    header('Content-Type: application/json'); // Indiquer que la réponse est au format JSON
    $validees = array_values(array_filter($reservations, function($r) { // Filtrer les réservations pour ne garder que celles qui sont validées
        return ($r['statut'] ?? '') === 'validée';
    }));
    echo json_encode($validees);
    exit;
}


// Handler AJAX GET : retourne les demandes d'activités en attente pour toutes les dates, 
//avec les informations de réservation associées, pour affichage dans la section correspondante
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['section']) && $_GET['section'] === 'activites_all') {
    header('Content-Type: application/json');
    $demandes     = readJson("activity_requests.json") ?: [];
    $reservations = readJson("reservation.json") ?: [];

    $resMap = []; // Créer une map des réservations par ID pour un accès lors de l'association avec les demandes d'activités
    foreach ($reservations as $res) $resMap[$res['id']] = $res;

    $resultat = [];
    // Parcourir les demandes d'activités pour trouver celles qui sont en attente et associer les informations de réservation correspondantes
    foreach ($demandes as $dem) {
        if (($dem['statut'] ?? '') !== 'en_attente') continue;
        $res = $resMap[$dem['reservation_id']] ?? null; 
        if (!$res) continue;
        $resultat[] = array_merge($dem, [
            'reservation_date_debut'   => $res['date_debut'] ?? '',
            'reservation_date_fin'     => $res['date_fin'] ?? '',
            'reservation_nb_personnes' => (int)($res['nb_personnes'] ?? 0)
        ]);
    }

    // Trier par date de début de réservation
    usort($resultat, fn($a, $b) => strcmp($a['reservation_date_debut'], $b['reservation_date_debut']));

    echo json_encode($resultat);
    exit;
}



// Lecture des animateurs pour la planification des activités
$animateurs = readJson("animateurs.json") ?: [];

// Calcul chambres réservées
$chambresReservees = [
    "bungalow" => 0,
    "villa" => 0,
    "suite" => 0
];

// Parcourir les réservations pour compter le nombre de chambres réservées de chaque type (statut validée)
foreach ($reservations as $r) {
    $type = strtolower(trim($r['type_chambre'] ?? ''));

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
// Données des chambres disponibles pour les calculs côté client
var chambresDisponibles = <?= json_encode($chambresDisponibles) ?>; // Données des chambres réservées pour les calculs côté client

// Fonction d'échappement pour éviter les injections HTML dans les messages et contenus dynamiques
function escHtml(str) {
    return $('<div>').text(String(str || '')).html();
}

// Affiche un message en haut de page sans rechargement
function showMessage(message, type) {
    type = type || 'info';
    $('#messageAdmin').html('<div class="alert alert-' + type + '"><pre class="mb-0">' + escHtml(message) + '</pre></div>');
    $('html, body').animate({ scrollTop: 0 }, 300);
}

// Met à jour le tableau des chambres disponibles/réservées
function updateChambresTable(chambresReservees) {
    $.each(chambresReservees, function(type, reservees) {
        var row = $('#chambresTableBody tr[data-type="' + type + '"]');// Mettre à jour le nombre de chambres réservées et disponibles dans le tableau
        row.find('.chambre-reservee').text(reservees); // Mettre à jour le nombre de chambres réservées pour ce type
        row.find('.chambre-dispo').text(chambresDisponibles[type] - reservees); // Mettre à jour le nombre de chambres disponibles en soustrayant les réservées du total disponible pour ce type
    });
}


// Construit le HTML d'une carte de réservation validée avec les informations et les formulaires de mise à jour des arrhes et réduction
function renderValidatedCard(res) {
    var arrhes    = parseFloat(res.arrhes || 0).toFixed(2);
    var reduction = parseInt(res.reduction_prestations || 0);
    return '<div class="card mb-3" id="validated_card_' + res.id + '">' +
        '<div class="card-body">' +
        '<p><strong>Nom :</strong> ' + escHtml(res.nom) + '</p>' +
        '<p><strong>Email :</strong> ' + escHtml(res.email) + '</p>' +
        '<p><strong>Dates :</strong> ' + escHtml(res.date_debut) + ' → ' + escHtml(res.date_fin) + '</p>' +
        '<p><strong>Type chambre :</strong> ' + escHtml(res.type_chambre) + '</p>' +
        '<p><strong>Personnes :</strong> ' + escHtml(res.nb_personnes) + '</p>' +
        '<p class="arrhes-display"><strong>Avance enregistrée :</strong> ' + arrhes.replace('.', ',') + ' €</p>' +
        '<p class="reduction-display"><strong>Réduction sur prestations :</strong> ' + reduction + ' %</p>' +
        '<div class="row">' +
            '<div class="col-md-6">' +
                '<form class="border rounded p-3 bg-light mb-2 arrhes-form">' +
                    '<input type="hidden" name="reservation_id" value="' + res.id + '">' +
                    '<label class="form-label"><strong>Enregistrer l\'avance</strong></label>' +
                    '<input type="number" step="0.01" min="0" name="arrhes" class="form-control mb-2" value="' + arrhes + '">' +
                    '<button type="submit" class="btn btn-primary btn-sm" value="maj_arrhes">Enregistrer l\'avance</button>' +
                '</form>' +
            '</div>' +
            '<div class="col-md-6">' +
                '<form class="border rounded p-3 bg-light mb-2 reduction-form">' +
                    '<input type="hidden" name="reservation_id" value="' + res.id + '">' +
                    '<label class="form-label"><strong>Réduction sur prestations</strong></label>' +
                    '<select name="reduction_prestations" class="form-select mb-2">' +
                        '<option value="0"'  + (reduction === 0  ? ' selected' : '') + '>0%</option>'  +
                        '<option value="10"' + (reduction === 10 ? ' selected' : '') + '>-10%</option>' +
                        '<option value="20"' + (reduction === 20 ? ' selected' : '') + '>-20%</option>' +
                        '<option value="50"' + (reduction === 50 ? ' selected' : '') + '>-50%</option>' +
                    '</select>' +
                    '<button type="submit" class="btn btn-warning btn-sm" value="maj_reduction">Enregistrer réduction</button>' +
                '</form>' +
            '</div>' +
        '</div>' +
        '</div></div>';
}

// Charge la section des réservations validées via AJAX
function loadValidatedSection() {
    $.ajax({
        url: 'Admin.php',
        method: 'GET',
        data: { section: 'validees' },
        dataType: 'json'
    }).done(function(reservations) {
        var container = $('#validatedContainer');
        container.empty();
        if (reservations.length === 0) {
            container.html('<div class="alert alert-secondary">Aucune réservation validée.</div>');
        } else {
            $.each(reservations, function(i, res) { // Parcourir les réservations validées et construire une carte pour chacune
                container.append(renderValidatedCard(res));
            });
        }
    }).fail(function() {
        $('#validatedContainer').html('<div class="alert alert-danger">Erreur de chargement.</div>');
    });
}

var lastClickedAction = ''; 

$(document).ready(function(){

    // Charger la section validées au chargement de la page
    loadValidatedSection();

    // Mémoriser le bouton cliqué avant soumission du formulaire
    $(document).on('click', 'button[type="submit"]', function() {
        lastClickedAction = $(this).val();
    });

    // --- Formulaires valider / refuser une réservation ---
    $(document).on('submit', '.reservation-form', function(e) {
        e.preventDefault();
        var form          = $(this);
        var card          = form.closest('.card');
        var action        = lastClickedAction;
        var reservationId = form.find('input[name="reservation_id"]').val();
       
        // Envoyer la requête AJAX pour valider ou refuser la réservation, avec l'ID de réservation et l'action à effectuer
        $.ajax({
            url: 'Admin.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { reservation_id: reservationId, action: action }
        }).done(function(res) {
            showMessage(res.message, res.success ? 'info' : 'danger');
            if (res.success) {
                card.fadeOut(400, function() {
                    $(this).remove();
                    if ($('#pendingContainer .card').length === 0) {
                        $('#pendingContainer').html('<div class="alert alert-secondary">Aucune demande en attente.</div>');
                    }
                });
                if (res.chambres_reservees) updateChambresTable(res.chambres_reservees);
                if (action === 'valider') loadValidatedSection();
            }
        }).fail(function() { showMessage('Erreur de communication avec le serveur.', 'danger'); });
    });



    // --- Formulaires mise à jour arrhes 
    $(document).on('submit', '.arrhes-form', function(e) {
        e.preventDefault();
        var form          = $(this);
        var reservationId = form.find('input[name="reservation_id"]').val();
        var arrhes        = form.find('input[name="arrhes"]').val();

        $.ajax({
            url: 'Admin.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { reservation_id: reservationId, action: 'maj_arrhes', arrhes: arrhes }
        }).done(function(res) {
            showMessage(res.message, res.success ? 'success' : 'danger');
            if (res.success) {
                form.closest('.card').find('.arrhes-display').html(
                    '<strong>Avance enregistrée :</strong> ' +
                    parseFloat(arrhes).toFixed(2).replace('.', ',') + ' €'
                );
            }
        }).fail(function() { showMessage('Erreur de communication avec le serveur.', 'danger'); });
    });

    // --- Formulaires réduction ---
    $(document).on('submit', '.reduction-form', function(e) {
        e.preventDefault();
        var form          = $(this);
        var reservationId = form.find('input[name="reservation_id"]').val();
        var reduction     = form.find('select[name="reduction_prestations"]').val();

        $.ajax({
            url: 'Admin.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { reservation_id: reservationId, action: 'maj_reduction', reduction_prestations: reduction }
        }).done(function(res) {
            showMessage(res.message, res.success ? 'success' : 'danger');
            if (res.success) {
                form.closest('.card').find('.reduction-display').html(
                    '<strong>Réduction sur prestations :</strong> ' + reduction + ' %'
                );
            }
        }).fail(function() { showMessage('Erreur de communication avec le serveur.', 'danger'); });
    });

});
</script>
<body class="p-4 bg-light">

<div class="container">
    <h1 class="mb-4">Gestion des réservations</h1>


    <!-- Affichage du message de session (s'il existe) pour informer l'admin des actions effectuées, des erreurs -->
    <div id="messageAdmin">
    <?php if (!empty($messageAdmin)): ?>
        <div class="alert alert-info">
            <pre class="mb-0"><?= htmlspecialchars($messageAdmin) ?></pre>
        </div>
    <?php endif; ?>
    </div>


    <!-- Affichage de l'état des chambres (nombre réservées / disponibles) dans un tableau -->
    <h3>État des chambres</h3>
    <table class="table table-bordered bg-white">
        <thead class="table-dark">
            <tr>
                <th>Type de chambre</th>
                <th>Réservées</th>
                <th>Disponibles</th>
            </tr>
        </thead>
        <tbody id="chambresTableBody">
            <?php foreach ($chambresDisponibles as $type => $total): ?>
                <tr data-type="<?= $type ?>">
                    <td><?= ucfirst($type) ?></td>
                    <td class="chambre-reservee"><?= $chambresReservees[$type] ?></td>
                    <td class="chambre-dispo"><?= $total - $chambresReservees[$type] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr class="my-4">


    <!-- Affichage des demandes de réservation en attente avec les formulaires de validation/refus, et les informations de chaque demande -->
    <h3>Demandes de réservation en attente</h3>
    <div id="pendingContainer">
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

                <form class="d-inline reservation-form">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                    <button type="submit" class="btn btn-success btn-sm" value="valider">Valider</button>
                    <button type="submit" class="btn btn-danger btn-sm" value="refuser">Refuser</button>
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
    </div>


    <hr class="my-4">


    <!-- Affichage des réservations validées avec les formulaires de mise à jour des arrhes et réduction, et les informations de chaque réservation validée -->
    <h3>Réservations validées</h3>
    <div id="validatedContainer">
        <div class="text-center text-muted py-3"><em>Chargement...</em></div>
    </div>

    <hr class="my-4">

    <!-- Gestion des demandes d'activités par journée -->
    <h3>Demandes d'activités par journée</h3>
    <div class="card p-3 mb-3 bg-white">
        <div class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label for="activityDatePicker" class="form-label mb-1">Filtrer par date</label>
                <input type="date" id="activityDatePicker" class="form-control">
            </div>
            <button class="btn btn-primary" id="loadActivityRequestsBtn">Filtrer</button>
            <button class="btn btn-secondary" id="loadAllActivityRequestsBtn">Voir toutes les dates</button>
        </div>
    </div>
    <div id="activityRequestsContainer"></div>

</body>
<script>
// Données animateurs et activités injectées depuis PHP
var animateurs = <?= json_encode($animateurs) ?>;

// Construit le HTML de la section demandes d'activités groupées par activité
function renderActivityRequests(requests, date) {
    var container = $('#activityRequestsContainer');
    container.empty();

    // Afficher un message si aucune demande d'activité en attente pour la date sélectionnée
    if (requests.length === 0) {
        container.html('<div class="alert alert-secondary">Aucune demande d\'activité en attente pour cette date.</div>');
        return;
    }

    // Grouper les demandes par activité pour les afficher ensemble dans une même carte
    var groups = {};
    $.each(requests, function(i, req) {
        var key = req.activity_id;// Utiliser l'ID de l'activité comme clé de regroupement
        if (!groups[key]) {
            groups[key] = { activity_id: req.activity_id, activity_nom: req.activity_nom, requests: [] };
        }
        groups[key].requests.push(req); // Ajouter la demande à son groupe d'activité correspondant
    });

    // Construire le select des animateurs (réutilisé dans chaque groupe)
    var animateursOptions = '<option value="">-- Choisir un animateur --</option>';
    $.each(animateurs, function(i, a) {
        animateursOptions += '<option value="' + escHtml(a.nom) + '">' + escHtml(a.nom) + '</option>';
    });

    $.each(groups, function(activityId, group) {// Parcourir les groupes d'activités pour construire une carte pour chaque activité avec ses demandes associées
        var html = '<div class="card mb-4"><div class="card-header"><strong>' +
            escHtml(group.activity_nom) + '</strong></div><div class="card-body">' +
            '<form class="plan-activity-form" data-activity-id="' + activityId + '" data-date="' + escHtml(date) + '">' +

            // Checkboxes des demandes
            '<p class="mb-2"><strong>Demandes à inclure :</strong></p>';

        $.each(group.requests, function(i, req) { // Parcourir les demandes d'une même activité pour construire une checkbox pour chacune avec les informations de la demande
            var creneauLabel = { heure: 'À l\'heure', 'demi-journee': 'Demi-journée', journee: 'Journée' }[req.creneau] || req.creneau;
            html += '<div class="form-check mb-1">' +
                '<input class="form-check-input" type="checkbox" name="request_ids[]" value="' + req.id + '" id="req_' + req.id + '">' +
                '<label class="form-check-label" for="req_' + req.id + '">' +
                '<strong>' + escHtml(req.user_nom) + '</strong> (' + escHtml(req.user_email) + ')' +
                ' — ' + creneauLabel +
                ' — ' + req.nb_personnes + ' pers.' +
                ' — séjour : ' + escHtml(req.reservation_date_debut) + ' → ' + escHtml(req.reservation_date_fin) +
                (req.message ? '<br><em class="text-muted ms-3">&laquo; ' + escHtml(req.message) + ' &raquo;</em>' : '') +
                '</label></div>';
        });


        // Champs de planification (date, animateur, heure, créneau) et bouton de soumission pour planifier l'activité avec les demandes sélectionnées
        html += '<div class="row mt-3 g-2">' +
            (date === 'all' ?
                '<div class="col-md-3">' +
                    '<label class="form-label">Date <span class="text-danger">*</span></label>' +
                    '<input type="date" name="date_planification" class="form-control" required>' +
                '</div>' : '') +
            '<div class="col-md-4">' +
                '<label class="form-label">Animateur</label>' +
                '<select name="animateur" class="form-select" required>' + animateursOptions + '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label">Heure de début</label>' +
                '<input type="time" name="heure" class="form-control" required>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label">Créneau</label>' +
                '<select name="creneau" class="form-select">' +
                    '<option value="heure">À l\'heure</option>' +
                    '<option value="demi-journee">Demi-journée</option>' +
                    '<option value="journee">Journée</option>' +
                '</select>' +
            '</div>' +
        '</div>' +
        '<button type="submit" class="btn btn-success mt-3">Planifier cette activité</button>' +
        '</form></div></div>';

        container.append(html);
    });
}

// Gestion des interactions pour les demandes d'activités : chargement, planification, etc.
$(document).ready(function(){ 

    // Charger les demandes d'activités pour la date sélectionnée
    $('#loadActivityRequestsBtn').on('click', function() {
        var date = $('#activityDatePicker').val();
        if (!date) {
            alert('Veuillez sélectionner une date.');
            return;
        }
        $.ajax({
            url: 'includes/api/activite.php',
            method: 'GET',
            data: { action: 'par_date', date: date },
            dataType: 'json'
        }).done(function(requests) {
            renderActivityRequests(requests, date);
        }).fail(function() { showMessage('Erreur de chargement des demandes.', 'danger'); });
    });

    // Charger toutes les demandes d'activités en attente pour toutes les dates
    $('#loadAllActivityRequestsBtn').on('click', function() {
        $.ajax({
            url: 'Admin.php',
            method: 'GET',
            data: { section: 'activites_all' },
            dataType: 'json'
        }).done(function(requests) {
            renderActivityRequests(requests, 'all');
        }).fail(function() { showMessage('Erreur de chargement.', 'danger'); });
    });

    // Planifier une activité
    $(document).on('submit', '.plan-activity-form', function(e) {
        e.preventDefault();
        var form       = $(this);
        var activityId = form.data('activity-id');
        var date       = form.data('date');

        // Vérifier qu'au moins une demande est cochée
        var checked = form.find('input[name="request_ids[]"]:checked');
        if (checked.length === 0) {
            alert('Veuillez sélectionner au moins une demande.');
            return;
        }

        // En mode "toutes les dates", la date vient du champ du formulaire
        var dateFinale = (date === 'all') ? form.find('input[name="date_planification"]').val() : date;
        if (!dateFinale) { alert('Veuillez saisir une date.'); return; }
        var data = form.serialize() + '&activity_id=' + activityId + '&date=' + encodeURIComponent(dateFinale) + '&action=planifier';

        $.ajax({
            url: 'includes/api/activite.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: data
        }).done(function(res) {
            showMessage(res.message, res.success ? 'success' : 'danger');
            if (res.success) {
                $('#loadActivityRequestsBtn').trigger('click');
            }
        }).fail(function() { showMessage('Erreur de communication avec le serveur.', 'danger'); });
    });
});
</script>
</html>
<?php
/* Client.php : Espace client permettant à un client connecté de voir ses réservations, demander des prestations et des activités, et effectuer un paiement
* Sans rechargement de la page grâce à l'utilisation d'AJAX pour les interactions avec les prestations, activités et la facture.
*/

// reprendre la session de l'utilisateur connecté
session_start();
// Inclure les fonctions de gestion des données JSON
require_once "includes/json_data.php";
require_once "includes/api/facture.php";

// Vérification connexion
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté ou n'est pas un client
    header("Location: connexion.php");
    exit;
}
// Récupérer l'email de l'utilisateur connecté
$email = $_SESSION['email'] ?? '';

// Charger les données nécessaires si elles existent sinon initialiser à des tableaux vides
$reservations      = readJson("reservation.json") ?: [];
$prestations_client = readJson("prestations_client.json") ?: [];
$roomTypes         = readJson("room_types.json") ?: [];
$activities        = readJson("activities.json") ?: [];
$plannedActivities = readJson("planned_activities.json") ?: [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vos réservations et prestations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">

<div class="container">
    <!-- Afficher un message de bienvenue avec le nom de l'utilisateur -->
    <h1>Bienvenue <?= htmlspecialchars($_SESSION['nom']) ?></h1>

    <!-- Section réservations -->
    <h3 class="mt-4">Mes réservations</h3>

    <?php
    // Variable pour suivre si au moins une réservation est trouvée et si une réservation validée existe
    $found = false;
    
    // Pour afficher la section prestations uniquement si une réservation est validée
    $hasValidReservation = false;

    // Parcourir les réservations pour trouver celles de l'utilisateur connecté
    foreach ($reservations as $res):
    // Vérifier si la réservation appartient à l'utilisateur connecté
    if (($res['email'] ?? '') === $email):
        $found = true;
        if ($res['statut'] === 'validée') $hasValidReservation = true;

        // Récupérer les prestations liées à cette réservation
        $resPrestations = array_values(array_filter($prestations_client, function($p) use ($email, $res){
            // On vérifie que la prestation appartient à l'utilisateur connecté et est liée à la réservation en cours
            return $p['user_email'] === $email && $p['reservation_id'] == $res['id'];
        }));

        // Calculer la facture prévisionnelle pour cette réservation si elle est validée
        // La facture sera mise à jour dynamiquement via AJAX après l'ajout de prestations ou d'activités
        // La fonction calculerFactureReservation est définie dans includes/api/facture.php 
        $facture = null;
        // Seule une réservation validée génère une facture prévisionnelle
        if (($res['statut'] ?? '') === 'validée') {
            // On passe les prestations du client, les types de chambre et les activités planifiées pour calculer la facture
            $facture = calculerFactureReservation($res, $roomTypes, $prestations_client, $plannedActivities);
        }
?>

        <div class="card mb-3" id="card_resa_<?= htmlspecialchars($res['id']) ?>">
            <div class="card-body">
                <!-- Afficher les détails de la réservation -->
                <p><strong>Dates :</strong> <?= $res['date_debut'] ?> → <?= $res['date_fin'] ?></p>
                <p><strong>Chambre :</strong> <?= $res['type_chambre'] ?></p>
                <p><strong>Personnes :</strong> <?= $res['nb_personnes'] ?></p>

                <!-- Afficher le statut de la réservation avec une couleur différente selon le statut -->
                <p><strong>Statut :</strong>
                    <!-- badge dynamique pour appliquer le css en fonction du statut de la réservation -->
                    <span id="statut_resa_<?= htmlspecialchars($res['id']) ?>"
                        class="badge
                        <?php
                            // Appliquer une classe de couleur différente selon le statut de la réservation
                            // un ternaire imbriqué a été utilisé pour simplifier la logique d'affichage des classes
                            // vert si validé sinon rouge si refusé sinon orange pour les autres statuts (en attente, etc.)
                            echo ($res['statut'] === 'validée') ? 'bg-success' :
                                (($res['statut'] === 'refusée') ? 'bg-danger' : 'bg-warning text-dark');
                        ?>">
                        <!-- Afficher le statut avec la première lettre en majuscule grace à la fonction ucfirst -->
                        <!-- htmlspecialchars est utilisé pour sécuriser l'affichage -->
                        <?= htmlspecialchars(ucfirst($res['statut'])) ?>
                    </span>
                </p>

                <!-- Liste des prestations : mise à jour via AJAX après ajout -->
                <div id="prestations_list_<?= htmlspecialchars($res['id']) ?>">
                <!-- affichage initial des prestations liées à cette réservation -->
                <?php if(!empty($resPrestations)): ?>
                    <p><strong>Prestations choisies :</strong></p>
                    <ul>
                        <!-- Parcourir les prestations liées à cette réservation et les afficher -->
                        <?php foreach($resPrestations as $p): ?>
                            <li>
                                <!-- Afficher le nom de la prestation et son statut -->
                                <?= htmlspecialchars($p['prestation']['name']) ?> - <em><?= htmlspecialchars($p['statut']) ?></em>
                                <!-- Si la prestation est validée, afficher les détails supplémentaires (adresse et heure) -->
                                <?php if($p['statut'] === 'validée'): ?>
                                    <!-- si les détails ne sont pas encore définis, afficher "à définir" pour éviter d'avoir des champs vides -->
                                    (Adresse: <?= htmlspecialchars($p['adresse'] ?? 'à définir') ?>,
                                    Heure: <?= htmlspecialchars($p['heure'] ?? 'à définir') ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </div>
        </div>
    
    <!-- Afficher le formulaire de demande d'activité uniquement si la réservation est validée -->
    <?php if (($res['statut'] ?? '') === 'validée'): ?>

        <!-- Formulaire de demande d'activité -->
        <div class="card mb-3 border-info">
            <div class="card-header bg-info text-white">Demander une activité</div>
            <div class="card-body">
                <form class="activity-request-form"
                      data-reservation-id="<?= htmlspecialchars($res['id']) ?>"
                      data-max-personnes="<?= (int)($res['nb_personnes'] ?? 1) ?>">
                    <div class="row g-2">
                        <!-- Colonne pour choisir l'activité parmi les activités disponibles -->
                        <div class="col-md-4">
                            <label class="form-label">Activité</label>
                            <!--select pour créer une liste déroulante des activités disponibles, 
                            avec les contraintes de participants affichées entre parenthèses -->
                            <select name="activity_id" class="form-select" required>
                                <option value="">-- Choisir --</option>
                                <!-- Parcourir les activités disponibles et les afficher dans la liste déroulante -->
                                <?php foreach ($activities as $act): ?>
                                    <!-- balise option pour définir un choix dans la liste déroulante
                                    avec la valeur de l'id de l'activité et le nom de l'activité affiché -->
                                    <option value="<?= $act['id'] ?>">
                                        <?= htmlspecialchars($act['nom']) ?>
                                        (<?= $act['min_participants'] ?>–<?= $act['max_participants'] ?> pers.)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Colonne pour choisir le créneau de l'activité (à l'heure, demi-journée, journée) -->
                        <div class="col-md-3">
                            <!-- balise label pour le champ de sélection du créneau -->
                            <label class="form-label">Créneau</label>
                            <!-- select pour créer une liste déroulante des créneaux disponibles pour l'activité -->
                            <select name="creneau" class="form-select" required>
                                <!-- balise option pour définir les choix de créneaux disponibles -->
                                <option value="heure">À l'heure</option>
                                <option value="demi-journee">Demi-journée</option>
                                <option value="journee">Journée</option>
                            </select>
                        </div>

                        <!-- Colonne pour choisir le nombre de personnes participant à l'activité
                        avec des contraintes basées sur la réservation -->
                        <div class="col-md-2">
                            <label class="form-label">Nb personnes</label>
                            <!-- input de type number pour saisir le nombre de participants
                             avec des limites basées sur le nombre de personnes de la réservation -->
                            <input type="number" name="nb_personnes" class="form-control"
                                   min="1" max="<?= (int)($res['nb_personnes'] ?? 1) ?>"
                                   value="1" required> <!-- obliger l'utilisateur à remplir -->
                        </div>
                    </div>
                    
                    <!-- champ pour choisir la date souhaitée pour l'activité
                    avec des limites basées sur les dates de la réservation -->
                    <div class="Date de résa">
                        <div class="col-md-4">
                            <!-- balise label pour un champ de saisie de date -->
                            <label class="form-label">Date souhaitée</label>
                            <!-- input de type date pour saisir la date souhaitée pour l'activité
                             avec des limites min et max basées sur les dates de début et fin de la réservation -->
                            <input type="date"
                                name="date_souhaitee"
                                class="form-control"
                                min="<?= htmlspecialchars($res['date_debut']) ?>"
                                max="<?= htmlspecialchars($res['date_fin']) ?>"
                                value="<?= htmlspecialchars($res['date_debut']) ?>"
                                required> <!-- obliger l'utilisateur à remplir -->
                        </div>
                    </div>

                    <!-- champ facultatif pour ajouter des précisions -->
                    <div class="mt-2">
                        <label class="form-label">Précisions (optionnel)</label>
                        <!-- champs de saisie multiple avec textarea et placeholder pour afficher un texte d'exemple -->
                        <textarea name="message" class="form-control" rows="2"
                                  placeholder="Ex : pas le premier jour, préférence matin..."></textarea>
                    </div>
                    <!-- bouton pour soumettre la demande d'activité -->
                    <button type="submit" class="btn btn-info btn-sm mt-2 text-white">Envoyer la demande</button>
                </form>
            </div>
        </div>

        <!-- Activités planifiées pour cette réservation (chargées via AJAX) -->
        <div id="planned_activities_<?= htmlspecialchars($res['id']) ?>"></div>

    <?php endif; ?>
    
    <!-- Afficher la facture prévisionnelle et le bouton de paiement uniquement si la réservation est validée -->
    <?php if ($facture): ?>
        <hr>
        <!-- Facture : mise à jour via AJAX après ajout de prestation ou activité -->
        <div id="facture_<?= htmlspecialchars($res['id']) ?>">
        <h5>Facture prévisionnelle</h5>
        <table class="table table-bordered table-sm bg-white">
            <!-- en tête du tableau avec thead pour les colonnes de désignation et montant -->
            <thead class="table-light">
                <tr>
                    <th>Désignation</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <!-- corps du tableau avec tbody pour afficher les lignes de la facture -->
            <tbody>
                <!-- Parcourir les lignes de la facture et les afficher dans le tableau -->
                <?php foreach ($facture['lignes'] as $ligne): ?>
                    <tr>
                        <!-- Afficher le label de la ligne de facture -->
                        <td><?= htmlspecialchars($ligne['label']) ?></td>
                        <!-- Afficher le montant de la ligne de facture formaté en euros avec
                        2 décimales, une virgule comme séparateur décimal et un espace comme séparateur de milliers -->
                        <td><?= number_format($ligne['montant'], 2, ',', ' ') ?> €</td>
                    </tr>
                <?php endforeach; ?>

                <!-- Ligne de total prévisionnel avec une classe pour la différencier visuellement -->
                <tr class="table-secondary">
                    <th>Total prévisionnel</th>
                    <!-- afficher le champ total de la facture -->
                    <th><?php echo number_format($facture['total'], 2, ',', ' '); ?> €</th>
                </tr>
            </tbody>
        </table>
        </div>
    
    
    <!-- affichafe du bouton de paiement uniquement si le total de la facture est supérieur à 0 -->
    <?php if ($facture['total'] > 0): ?>

        <!-- Bouton pour simuler le paiement de la réservation, avec un modal pour saisir les informations de carte bancaire -->
        <button class="btn btn-success mt-2"
                data-bs-toggle="modal"
                data-bs-target="#modalPaiement_<?= $res['id'] ?>"
                data-total="<?= number_format($facture['total'], 2, '.', '') ?>">
             Payer 
        </button>

        <!-- Modal de paiement pour simuler la saisie des informations de carte bancaire -->
        <div class="modal fade" id="modalPaiement_<?= $res['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Paiement de la réservation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="form-paiement" data-reservation-id="<?= $res['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Titulaire de la carte</label>
                                <input type="text" name="titulaire" class="form-control"
                                       placeholder="Jean Dupont" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numéro de carte</label>
                                <input type="text" name="numero_carte" class="form-control"
                                       placeholder="1234 5678 9012 3456"
                                       maxlength="19" required>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Date d'expiration</label>
                                    <input type="text" name="expiration" class="form-control"
                                           placeholder="MM/AA" maxlength="5" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" name="cvv" class="form-control"
                                           placeholder="123" maxlength="3" required>
                                </div>
                            </div>
                            <div class="alert alert-warning py-2">
                                <small> Simulation uniquement — aucune donnée réelle n'est traitée</small>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                Confirmer le paiement 
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>  <!-- fin if ($facture) -->

    <?php
        endif;
    endforeach;
    ?>


    <!-- message info si aucune résa -->
    <?php if (!$found): ?>
        <div class="alert alert-info">Aucune réservation trouvée.</div>
    <?php endif; ?>
    
    <!-- afficher la section prestations disponibles uniquement si au moins
    une réservation est validée, car les prestations ne peuvent être ajoutées que pour des réservations validées -->
    <?php if ($hasValidReservation): ?>
        <!-- Section prestations pour les réservations validées -->
        <div id="prestationsSection" class="mt-5">
            <h3>Prestations disponibles</h3>
            <div id="prestationsContainer" class="row"></div>
        </div>
    <?php endif; ?>
</div>

<!-- Inclure les scripts nécessaires pour Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Inclure jQuery pour faciliter les requêtes AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Du ajax pour gérer les interactions sans rechargement de la page :
ajout de prestations, chargement des activités planifiées, mise à jour de la facture, etc. --> 

<script>
// Met à jour la carte de réservation avec les nouvelles prestations et la facture après l'ajout d'une prestation
function updateReservationCard(reservationId) {
    // Recharger les prestations liées à cette réservation et la facture via AJAX
    $.ajax({
        // chemin vers le script php qui envoie les informations de la réservation
        url: 'includes/api/facture.php',
        // Requête de type GET pour récupérer les données
        method: 'GET',
        // Récupérer les details de la résa d'identifiant reservationId
        data: { action: 'detail', reservation_id: reservationId },
        // Données récupérées au format JSON
        dataType: 'json'
    // est exécuté lorsque la requête AJAX est terminée avec succès, avec la réponse du serveur passée en paramètre (res)
    }).done(function(res) {
        // Si ça échoue, on ne fait rien et on ne met pas à jour
        if (!res.success) return;

        // Mettre à jour la liste des prestations
        
        // ##prestations_list_ pour cibler la div qui contient la liste des prestations de cette réservation
        var prestList = $('#prestations_list_' + reservationId);
        // vérifier si la réponse contient des prestations et si la liste n'est pas vide
        if (res.prestations && res.prestations.length > 0) {
            var html = '<p><strong>Prestations choisies :</strong></p><ul>';
            // parcourir res avec each et générer le HTML pour chaque préstation
            // i : index de l'élément, p : élément lui-même
            $.each(res.prestations, function(i, p) {
                html += '<li>' + $('<div>').text(p.prestation.name).html() +
                        ' - <em>' + $('<div>').text(p.statut).html() + '</em>';
                // Si la prestation est validée, afficher les détails supplémentaires (adresse et heure)
                if (p.statut === 'validée') {
                    html += ' (Adresse: ' + $('<div>').text(p.adresse || 'à définir').html() +
                            ', Heure: ' + $('<div>').text(p.heure || 'à définir').html() + ')';
                }
                html += '</li>';
            });
            // Fermer la balise ul après avoir ajouté toutes les prestations
            html += '</ul>';
            // Mettre à jour le contenu de la div avec la nouvelle liste de prestations
            prestList.html(html);
        }

        // Mettre à jour la facture 
        var factureDiv = $('#facture_' + reservationId);
        // vérifier si la div de la facture existe et si la réponse contient une facture
        if (factureDiv.length && res.facture) {
            // Générer les lignes de la facture à partir des données de la réponse
            var rows = '';
            // Parcourir les lignes de la facture et les ajouter au HTML
            $.each(res.facture.lignes, function(i, ligne) {
                // Formater le montant de la ligne de facture en euros avec 2 décimales -->
                var montant = parseFloat(ligne.montant).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
                rows += '<tr><td>' + $('<div>').text(ligne.label).html() +
                        '</td><td>' + montant + ' €</td></tr>';
            });
            // Ajouter la ligne de total prévisionnel à la fin du tableau avec une classe pour la différencier visuellement
            var total = parseFloat(res.facture.total).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
            rows += '<tr class="table-secondary"><th>Total prévisionnel</th><th>' + total + ' €</th></tr>';
            // Mettre à jour le contenu du tbody de la facture avec les nouvelles lignes générées
            factureDiv.find('tbody').html(rows);
        }
    });
}

// Charge et affiche les activités planifiées d'une réservation
function loadPlannedActivities(reservationId) {
    $.ajax({
        url: 'includes/api/activite.php',
        method: 'GET',
        data: { action: 'client', reservation_id: reservationId },
        dataType: 'json'
    }).done(function(activities) {
        // Cibler la div qui contiendra les activités planifiées pour cette réservation
        var container = $('#planned_activities_' + reservationId);
        // Si aucune activité n'est planifiée, afficher un message d'information
        if (activities.length === 0) {
            container.html('<p class="text-muted"><small>Aucune activité planifiée pour l\'instant.</small></p>');
            return;
        }
        // Générer le HTML pour afficher les activités planifiées dans une carte Bootstrap
        var html = '<div class="card mb-3 border-success">' +
            '<div class="card-header bg-success text-white">Activités planifiées</div>' +
            '<div class="card-body"><ul class="list-group list-group-flush">';

        // Parcourir les activités planifiées et générer le HTML pour chacune d'elles
        $.each(activities, function(i, pa) {
            // Formater la date de l'activité au format français (jour/mois/année)
            var dateFormate = new Date(pa.date).toLocaleDateString('fr-FR');
            // Déterminer le label du créneau en fonction de sa valeur (heure, demi-journée, journée)
            var creneauLabel = { heure: 'à l\'heure', 'demi-journee': 'demi-journée', journee: 'journée' }[pa.creneau] || pa.creneau;
            html += '<li class="list-group-item">' +
                '<strong>' + $('<div>').text(pa.activity_nom).html() + '</strong> — ' +
                dateFormate + ' à ' + (pa.heure || '?') + ' (' + creneauLabel + ')' +
                ' — Animateur : ' + $('<div>').text(pa.animateur).html() +
                '<br><small class="text-muted">Participants : ';

            // Parcourir les participants de l'activité et générer une liste de leurs noms avec le nombre de personnes associées
            var noms = [];
            $.each(pa.participants, function(j, p) { noms.push($('<div>').text(p.user_nom + ' (' + p.nb_personnes + ' pers.)').html()); });
            html += noms.join(', ') + '</small>';

            // Si des messages sont associés à des participants, les afficher en dessous de l'activité
            var messagesHtml = '';
            $.each(pa.participants, function(j, p) {
                if (p.message_participant) {
                    messagesHtml += '<div class="text-muted small mt-1">' +
                        '<em>' + $('<div>').text(p.user_nom).html() + ' : &laquo; ' +
                        $('<div>').text(p.message_participant).html() + ' &raquo;</em></div>';
                }
            });

            // Si au moins un message est présent, l'afficher dans une div séparée en dessous de l'activité
            if (messagesHtml) html += '<div class="mt-1">' + messagesHtml + '</div>';

            // Formulaire pour ajouter un message à l'activité, avec un champ de saisie et un bouton d'envoi
            html += '<form class="activity-message-form mt-2" data-planned-id="' + pa.id + '">' +
                '<div class="input-group input-group-sm">' +
                '<input type="text" name="message" class="form-control" placeholder="Ajouter un message pour les participants...">' +
                '<button type="submit" class="btn btn-outline-secondary btn-sm">Envoyer</button>' +
                '</div></form>';

            html += '</li>';
        });
        // Fermer les balises ul, div.card-body et div.card après avoir ajouté toutes les activités
        html += '</ul></div></div>';
        // Mettre à jour le contenu de la div avec les activités planifiées pour cette réservation
        container.html(html);
    });
}

// Lorsque le document est prêt, exécuter les fonctions pour charger les activités planifiées et gérer les interactions avec les prestations et la facture
$(document).ready(function(){
    <?php if ($hasValidReservation): ?>

    // Charger les activités planifiées pour chaque réservation validée au démarrage
    <?php foreach ($reservations as $res):
        if (($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée'): ?>
    // Appeler la fonction JavaScript pour charger les activités planifiées de cette réservation
    loadPlannedActivities('<?= htmlspecialchars($res['id']) ?>');
    <?php endif; endforeach; ?>

    // jQuery écoute l’événement submit sur tous les formulaires ayant la classe
    //  .activity-request-form
    // On utilise on pour etre sur que ça fonctionne même pour les formulaires ajoutés dynamiquement après le chargement de la page
    // function(e) : fonction callback exécutée au moment où le formulaire est soumis
    // e : objet de l'événement qui contient des informations sur l'événement qd le formulaire est soumis
    $(document).on('submit', '.activity-request-form', function(e) {
        // Empêcher le comportement par défaut du formulaire (rechargement de la page)
        e.preventDefault();
        // récupérer le formulaire soumis et le mettre dans form
        var form          = $(this);
        // récupérer le id de la réservation 
        var reservationId = form.data('reservation-id');
        // savoir pour quelle réservation la demande est faite
        var btn           = form.find('button[type="submit"]');
        // Désactiver le bouton pour éviter les clics multiples pendant le traitement de la demande
        btn.prop('disabled', true);
        
        // Envoyer la demande d'activité via AJAX en POST avec les données du formulaire,
        //  l'identifiant de la réservation et l'action à effectuer
        $.ajax({
            url: 'includes/api/activite.php',
            // Requête de type POST pour envoyer les données de la demande d'activité
            method: 'POST',
            // Récupérer les données du formulaire, ajouter l'identifiant de la réservation et l'action à effectuer
            data: form.serialize() + '&reservation_id=' + reservationId + '&action=demande',
            dataType: 'json'
        }).done(function(res) {
            // Afficher un message d'alerte avec la réponse du serveur (succès ou erreur)
            alert(res.message);
            if (res.success) form[0].reset();
            btn.prop('disabled', false);
        }).fail(function() {
            // Afficher un message d'alerte en cas d'erreur lors de l'envoi de la demande
            alert('Erreur lors de l\'envoi de la demande.');
            btn.prop('disabled', false);
        });
    });

    // Envoyer un message sur une activité planifiée via AJAX
    $(document).on('submit', '.activity-message-form', function(e) {
        // Empêcher le comportement par défaut du formulaire (rechargement de la page)
        e.preventDefault();
        var form      = $(this);
        // Récupérer l'identifiant de l'activité planifiée à partir des données du formulaire
        var plannedId = form.data('planned-id');
        // Récupérer le message saisi par l'utilisateur, en supprimant les espaces inutiles au début et à la fin
        var message   = form.find('input[name="message"]').val().trim();
        // Si le message est vide, ne pas envoyer la requête et afficher une alerte
        var resId     = form.closest('[id^="planned_activities_"]').attr('id').replace('planned_activities_', '');
        
        // requête AJAX pour envoyer le message à l'activité planifiée, avec l'action "message",
        //  l'identifiant de l'activité planifiée et le message lui-même
        $.ajax({
            url: 'includes/api/activite.php',
            method: 'POST',
            data: { action: 'message', planned_id: plannedId, message: message },
            dataType: 'json'
        }).done(function(res) {
            // Afficher un message d'alerte avec la réponse du serveur
            alert(res.message);
            if (res.success) loadPlannedActivities(resId);
        }).fail(function() { alert('Erreur lors de l\'envoi du message.'); });
    });

    // Charger le catalogue des prestations disponibles via AJAX
    $.ajax({
        // chemin vers le script php qui envoie la liste des prestations disponibles
        url: 'includes/api/prestation.php',
        // Requête de type GET pour récupérer les données
        method: 'GET',
        data: { action: 'liste' },
        dataType: 'json'
    }).done(function(data) {
        // Générer le HTML pour afficher les prestations disponibles dans des cartes Bootstrap
        var html = '';
        $.each(data, function(i, p) {
            html += '<div class="col-md-6 col-lg-4 mb-4">' +
                '<div class="card h-100"><div class="card-body d-flex flex-column">' +
                '<h5 class="card-title">' + $('<div>').text(p.name).html() + '</h5>' +
                '<p class="card-text">' + $('<div>').text(p.description).html() + '</p>' +
                '<p class="mt-auto"><strong>' + p.price + '€</strong> - <small>' + $('<div>').text(p.type_tarification).html() + '</small></p>' +
                '<button class="btn btn-primary btn-sm mt-2 add-prestation" data-id="' + p.id + '">Ajouter</button>' +
                '</div></div></div>';
        });
        // Mettre à jour le contenu de la div avec les prestations disponibles
        $('#prestationsContainer').html(html);
    });

    // Ajouter une prestation via AJAX et mettre à jour la carte sans rechargement
    $(document).on('click', '.add-prestation', function() {
        // Récupérer le bouton cliqué et l'identifiant de la prestation à ajouter
        var btn          = $(this);
        // Récupérer l'identifiant de la prestation à ajouter à partir des données du bouton
        var prestationId = btn.data('id');
        // Désactiver le bouton pour éviter les clics multiples pendant le traitement de l'ajout
        btn.prop('disabled', true);
        
        // Envoyer la requête AJAX pour ajouter la prestation à la réservation, 
        // avec l'action "ajouter" et l'identifiant de la prestation
        $.ajax({
            // chemin vers le script php qui gère l'ajout de la prestation à la réservation
            url: 'includes/api/prestation.php',
            // method post car on envoie au serveur 
            method: 'POST',
            // données envoyées avec l'action à ajouter et l'identifiant de la prestation à ajouter
            data: { action: 'ajouter', id: prestationId },
            dataType: 'json'
        }).done(function(res) {
            // Afficher un message d'alerte avec la réponse du serveur
            alert(res.message);
            // Si l'ajout de la prestation a réussi et que la réponse contient l'identifiant de la réservation,
            if (res.success && res.reservation_id) updateReservationCard(res.reservation_id);
            // Réactiver le bouton après le traitement de la requête
            btn.prop('disabled', false);
        }).fail(function() {
            // Afficher un message d'alerte en cas d'erreur lors de l'ajout de la prestation
            alert("Erreur lors de l'ajout de la prestation.");
            btn.prop('disabled', false);
        });
    });

    // Formatage automatique du numéro de carte bancaire pour ajouter des espaces tous les 4 chiffres et limiter à 16 chiffres
    $(document).on('input', 'input[name="numero_carte"]', function() {
        var val = $(this).val().replace(/\D/g, '').substring(0, 16);
        $(this).val(val.replace(/(.{4})/g, '$1 ').trim());
    });

    // Soumission du formulaire de paiement (simulation)
    $(document).on('submit', '.form-paiement', function(e) {
        e.preventDefault();
        // Récupérer l'identifiant de la réservation à partir des données du formulaire
        var reservationId = $(this).data('reservation-id');
        // Récupérer le bouton de soumission du formulaire pour le désactiver pendant le traitement
        var btn = $(this).find('button[type="submit"]');
        // Désactiver le bouton pour éviter les clics multiples pendant le traitement du paiement
        btn.prop('disabled', true).text('Traitement en cours...');

        // Simulation d'un délai de traitement
        setTimeout(function() {
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalPaiement_' + reservationId));
            // Si le modal existe, le fermer pour revenir à la carte de réservation
            if (modal) modal.hide();
            // Cacher le bouton payer de cette réservation
            $('button[data-bs-target="#modalPaiement_' + reservationId + '"]').hide();
            alert(' Paiement simulé avec succès !');
            btn.prop('disabled', false);
        }, 1500);
    });
    <?php endif; ?>
});

// Vérifie les statuts des réservations toutes les 5 secondes
function refreshReservations() {
    $.ajax({
        // chemin vers le script php qui envoie les statuts actuels des réservations du client
        url: 'refresh_reservations.php',
        method: 'GET',
        dataType: 'json'
    }).done(function(data) {
        // Parcourir les données reçues et mettre à jour les badges de statut de chaque réservation
        $.each(data, function(i, res) {
            var badge = $('#statut_resa_' + res.id);
            // Si le badge existe, mettre à jour son texte et sa classe en fonction du nouveau statut de la réservation
            if (badge.length) {
                badge.text(res.statut.charAt(0).toUpperCase() + res.statut.slice(1));
                badge.removeClass('bg-success bg-warning bg-danger text-dark');
                if (res.statut === 'validée')       badge.addClass('bg-success');
                else if (res.statut === 'refusée')  badge.addClass('bg-danger');
                else                                badge.addClass('bg-warning text-dark');
            }
        });
    });
}
// Appeler la fonction de rafraîchissement des réservations toutes les 5 secondes pour vérifier les changements de statut
setInterval(refreshReservations, 5000);
</script>
</body>
</html>
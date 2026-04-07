<?php
session_start();
// Inclure les fonctions de gestion des données JSON
require_once "includes/json_data.php";

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
    <h1>Bienvenue <?= htmlspecialchars($_SESSION['nom']) ?></h1>

    <h3 class="mt-4">Mes réservations</h3>

    <?php
    $found = false;
    $hasValidReservation = false; // Pour afficher la section prestations uniquement si une réservation est validée

    // Parcourir les réservations pour trouver celles de l'utilisateur connecté
    foreach ($reservations as $res):
    // Vérifier si la réservation appartient à l'utilisateur connecté
    if (($res['email'] ?? '') === $email):
        $found = true;
        if ($res['statut'] === 'validée') $hasValidReservation = true;

        // Récupérer les prestations liées à cette réservation
        $resPrestations = array_values(array_filter($prestations_client, function($p) use ($email, $res){
            return $p['user_email'] === $email && $p['reservation_id'] == $res['id'];
        }));

        $facture = null;
        if (($res['statut'] ?? '') === 'validée') {
            $facture = calculerFactureReservation($res, $roomTypes, $prestations_client, $plannedActivities);
        }
?>

        <div class="card mb-3" id="card_resa_<?= htmlspecialchars($res['id']) ?>">
            <div class="card-body">
                <p><strong>Dates :</strong> <?= $res['date_debut'] ?> → <?= $res['date_fin'] ?></p>
                <p><strong>Chambre :</strong> <?= $res['type_chambre'] ?></p>
                <p><strong>Personnes :</strong> <?= $res['nb_personnes'] ?></p>

                <p><strong>Statut :</strong>
                    <span id="statut_resa_<?= htmlspecialchars($res['id']) ?>"
                        class="badge
                        <?php
                            echo ($res['statut'] === 'validée') ? 'bg-success' :
                                (($res['statut'] === 'refusée') ? 'bg-danger' : 'bg-warning text-dark');
                        ?>">
                        <?= htmlspecialchars(ucfirst($res['statut'])) ?>
                    </span>
                </p>

                <!-- Liste des prestations : mise à jour via AJAX après ajout -->
                <div id="prestations_list_<?= htmlspecialchars($res['id']) ?>">
                <?php if(!empty($resPrestations)): ?>
                    <p><strong>Prestations choisies :</strong></p>
                    <ul>
                        <?php foreach($resPrestations as $p): ?>
                            <li>
                                <?= htmlspecialchars($p['prestation']['name']) ?> - <em><?= htmlspecialchars($p['statut']) ?></em>
                                <?php if($p['statut'] === 'validée'): ?>
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

            <?php if (($res['statut'] ?? '') === 'validée'): ?>

        <!-- Formulaire de demande d'activité -->
        <div class="card mb-3 border-info">
            <div class="card-header bg-info text-white">Demander une activité</div>
            <div class="card-body">
                <form class="activity-request-form"
                      data-reservation-id="<?= htmlspecialchars($res['id']) ?>"
                      data-max-personnes="<?= (int)($res['nb_personnes'] ?? 1) ?>">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Activité</label>
                            <select name="activity_id" class="form-select" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($activities as $act): ?>
                                    <option value="<?= $act['id'] ?>">
                                        <?= htmlspecialchars($act['nom']) ?>
                                        (<?= $act['min_participants'] ?>–<?= $act['max_participants'] ?> pers.)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Créneau</label>
                            <select name="creneau" class="form-select" required>
                                <option value="heure">À l'heure</option>
                                <option value="demi-journee">Demi-journée</option>
                                <option value="journee">Journée</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nb personnes</label>
                            <input type="number" name="nb_personnes" class="form-control"
                                   min="1" max="<?= (int)($res['nb_personnes'] ?? 1) ?>"
                                   value="1" required>
                        </div>
                    </div>
                    
                    <div class="Date de résa">
                        <div class="col-md-4">
                            <label class="form-label">Date souhaitée</label>
                            <input type="date"
                                name="date_souhaitee"
                                class="form-control"
                                min="<?= htmlspecialchars($res['date_debut']) ?>"
                                max="<?= htmlspecialchars($res['date_fin']) ?>"
                                value="<?= htmlspecialchars($res['date_debut']) ?>"
                                required>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Précisions (optionnel)</label>
                        <textarea name="message" class="form-control" rows="2"
                                  placeholder="Ex : pas le premier jour, préférence matin..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-info btn-sm mt-2 text-white">Envoyer la demande</button>
                </form>
            </div>
        </div>

        <!-- Activités planifiées pour cette réservation (chargées via AJAX) -->
        <div id="planned_activities_<?= htmlspecialchars($res['id']) ?>"></div>

    <?php endif; ?>

    <?php if ($facture): ?>
        <hr>
        <!-- Facture : mise à jour via AJAX après ajout de prestation ou activité -->
        <div id="facture_<?= htmlspecialchars($res['id']) ?>">
        <h5>Facture prévisionnelle</h5>
        <table class="table table-bordered table-sm bg-white">
            <thead class="table-light">
                <tr>
                    <th>Désignation</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facture['lignes'] as $ligne): ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['label']) ?></td>
                        <td><?= number_format($ligne['montant'], 2, ',', ' ') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-secondary">
                    <th>Total prévisionnel</th>
                    <th><?= number_format($facture['total'], 2, ',', ' ') ?> €</th>
                </tr>
            </tbody>
        </table>
        </div>
                <?php if ($facture['total'] > 0): ?>
        <button class="btn btn-success mt-2"
                data-bs-toggle="modal"
                data-bs-target="#modalPaiement_<?= $res['id'] ?>"
                data-total="<?= number_format($facture['total'], 2, '.', '') ?>">
             Payer 
        </button>

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



    <?php if (!$found): ?>
        <div class="alert alert-info">Aucune réservation trouvée.</div>
    <?php endif; ?>

    <?php if ($hasValidReservation): ?>
        <!-- Section prestations pour les réservations validées -->
        <div id="prestationsSection" class="mt-5">
            <h3>Prestations disponibles</h3>
            <div id="prestationsContainer" class="row"></div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Met à jour la liste des prestations et la facture d'une réservation sans rechargement
function updateReservationCard(reservationId) {
    $.ajax({
        url: 'includes/api/facture.php',
        method: 'GET',
        data: { action: 'detail', reservation_id: reservationId },
        dataType: 'json'
    }).done(function(res) {
        if (!res.success) return;

        // Mettre à jour la liste des prestations
        var prestList = $('#prestations_list_' + reservationId);
        if (res.prestations && res.prestations.length > 0) {
            var html = '<p><strong>Prestations choisies :</strong></p><ul>';
            $.each(res.prestations, function(i, p) {
                html += '<li>' + $('<div>').text(p.prestation.name).html() +
                        ' - <em>' + $('<div>').text(p.statut).html() + '</em>';
                if (p.statut === 'validée') {
                    html += ' (Adresse: ' + $('<div>').text(p.adresse || 'à définir').html() +
                            ', Heure: ' + $('<div>').text(p.heure || 'à définir').html() + ')';
                }
                html += '</li>';
            });
            html += '</ul>';
            prestList.html(html);
        }

        // Mettre à jour la facture
        var factureDiv = $('#facture_' + reservationId);
        if (factureDiv.length && res.facture) {
            var rows = '';
            $.each(res.facture.lignes, function(i, ligne) {
                var montant = parseFloat(ligne.montant).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
                rows += '<tr><td>' + $('<div>').text(ligne.label).html() +
                        '</td><td>' + montant + ' €</td></tr>';
            });
            var total = parseFloat(res.facture.total).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
            rows += '<tr class="table-secondary"><th>Total prévisionnel</th><th>' + total + ' €</th></tr>';
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
        var container = $('#planned_activities_' + reservationId);
        if (activities.length === 0) {
            container.html('<p class="text-muted"><small>Aucune activité planifiée pour l\'instant.</small></p>');
            return;
        }
        var html = '<div class="card mb-3 border-success">' +
            '<div class="card-header bg-success text-white">Activités planifiées</div>' +
            '<div class="card-body"><ul class="list-group list-group-flush">';

        $.each(activities, function(i, pa) {
            var dateFormate = new Date(pa.date).toLocaleDateString('fr-FR');
            var creneauLabel = { heure: 'à l\'heure', 'demi-journee': 'demi-journée', journee: 'journée' }[pa.creneau] || pa.creneau;
            html += '<li class="list-group-item">' +
                '<strong>' + $('<div>').text(pa.activity_nom).html() + '</strong> — ' +
                dateFormate + ' à ' + (pa.heure || '?') + ' (' + creneauLabel + ')' +
                ' — Animateur : ' + $('<div>').text(pa.animateur).html() +
                '<br><small class="text-muted">Participants : ';

            var noms = [];
            $.each(pa.participants, function(j, p) { noms.push($('<div>').text(p.user_nom + ' (' + p.nb_personnes + ' pers.)').html()); });
            html += noms.join(', ') + '</small>';

            var messagesHtml = '';
            $.each(pa.participants, function(j, p) {
                if (p.message_participant) {
                    messagesHtml += '<div class="text-muted small mt-1">' +
                        '<em>' + $('<div>').text(p.user_nom).html() + ' : &laquo; ' +
                        $('<div>').text(p.message_participant).html() + ' &raquo;</em></div>';
                }
            });
            if (messagesHtml) html += '<div class="mt-1">' + messagesHtml + '</div>';

            html += '<form class="activity-message-form mt-2" data-planned-id="' + pa.id + '">' +
                '<div class="input-group input-group-sm">' +
                '<input type="text" name="message" class="form-control" placeholder="Ajouter un message pour les participants...">' +
                '<button type="submit" class="btn btn-outline-secondary btn-sm">Envoyer</button>' +
                '</div></form>';

            html += '</li>';
        });
        html += '</ul></div></div>';
        container.html(html);
    });
}

$(document).ready(function(){
    <?php if ($hasValidReservation): ?>

    // Charger les activités planifiées pour chaque réservation validée au démarrage
    <?php foreach ($reservations as $res):
        if (($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée'): ?>
    loadPlannedActivities('<?= htmlspecialchars($res['id']) ?>');
    <?php endif; endforeach; ?>

    // Soumettre une demande d'activité via AJAX
    $(document).on('submit', '.activity-request-form', function(e) {
        e.preventDefault();
        var form          = $(this);
        var reservationId = form.data('reservation-id');
        var btn           = form.find('button[type="submit"]');
        btn.prop('disabled', true);

        $.ajax({
            url: 'includes/api/activite.php',
            method: 'POST',
            data: form.serialize() + '&reservation_id=' + reservationId + '&action=demande',
            dataType: 'json'
        }).done(function(res) {
            alert(res.message);
            if (res.success) form[0].reset();
            btn.prop('disabled', false);
        }).fail(function() {
            alert('Erreur lors de l\'envoi de la demande.');
            btn.prop('disabled', false);
        });
    });

    // Envoyer un message sur une activité planifiée via AJAX
    $(document).on('submit', '.activity-message-form', function(e) {
        e.preventDefault();
        var form      = $(this);
        var plannedId = form.data('planned-id');
        var message   = form.find('input[name="message"]').val().trim();
        var resId     = form.closest('[id^="planned_activities_"]').attr('id').replace('planned_activities_', '');

        $.ajax({
            url: 'includes/api/activite.php',
            method: 'POST',
            data: { action: 'message', planned_id: plannedId, message: message },
            dataType: 'json'
        }).done(function(res) {
            alert(res.message);
            if (res.success) loadPlannedActivities(resId);
        }).fail(function() { alert('Erreur lors de l\'envoi du message.'); });
    });

    // Charger le catalogue des prestations disponibles via AJAX
    $.ajax({
        url: 'includes/api/prestation.php',
        method: 'GET',
        data: { action: 'liste' },
        dataType: 'json'
    }).done(function(data) {
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
        $('#prestationsContainer').html(html);
    });

    // Ajouter une prestation via AJAX et mettre à jour la carte sans rechargement
    $(document).on('click', '.add-prestation', function() {
        var btn          = $(this);
        var prestationId = btn.data('id');
        btn.prop('disabled', true);

        $.ajax({
            url: 'includes/api/prestation.php',
            method: 'POST',
            data: { action: 'ajouter', id: prestationId },
            dataType: 'json'
        }).done(function(res) {
            alert(res.message);
            if (res.success && res.reservation_id) updateReservationCard(res.reservation_id);
            btn.prop('disabled', false);
        }).fail(function() {
            alert("Erreur lors de l'ajout de la prestation.");
            btn.prop('disabled', false);
        });
    });

    // Formatage automatique du numéro de carte (groupes de 4)
    $(document).on('input', 'input[name="numero_carte"]', function() {
        var val = $(this).val().replace(/\D/g, '').substring(0, 16);
        $(this).val(val.replace(/(.{4})/g, '$1 ').trim());
    });

    // Soumission du formulaire de paiement (simulation)
    $(document).on('submit', '.form-paiement', function(e) {
        e.preventDefault();
        var reservationId = $(this).data('reservation-id');
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Traitement en cours...');

        // Simulation d'un délai de traitement
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalPaiement_' + reservationId));
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
        url: 'refresh_reservations.php',
        method: 'GET',
        dataType: 'json'
    }).done(function(data) {
        $.each(data, function(i, res) {
            var badge = $('#statut_resa_' + res.id);
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

setInterval(refreshReservations, 5000);
</script>
</body>
</html>
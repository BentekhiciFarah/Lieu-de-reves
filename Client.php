<?php
session_start();
// Inclure les fonctions de gestion des données JSON
require_once "includes/json_data.php";

require_once "includes/json_data.php";
require_once "facture.php";

// Vérification connexion
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté ou n'est pas un client
    header("Location: connexion.php");
    exit;
}
// Récupérer l'email de l'utilisateur connecté
$email = $_SESSION['email'] ?? '';

$reservations = readJson("reservation.json") ?: [];
$prestations_client = readJson("prestations_client.json") ?: [];
$roomTypes = readJson("room_types.json") ?: [];
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
            $facture = calculerFactureReservation($res, $roomTypes, $prestations_client);
        }
?>

        <div class="card mb-3">
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

                <?php if(!empty($resPrestations)): ?>
                    <p><strong>Prestations choisies :</strong></p>
                    <ul>
                        <?php foreach($resPrestations as $p): ?>
                            <li>
                                <?= htmlspecialchars($p['prestation']['name']) ?> - <em><?= $p['statut'] ?></em>
                                <?php if($p['statut'] === 'validée'): ?>
                                    (Adresse: <?= $p['adresse'] ?? 'à définir' ?>,
                                    Heure: <?= $p['heure'] ?? 'à définir' ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

            <?php if ($facture): ?>
        <hr>
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
    <?php endif; ?>

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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    <?php if ($hasValidReservation): ?>
    // Charger prestations via AJAX
    $.ajax({
        url: 'get_prestations.php', // Fichier qui retourne le JSON des prestations
        method: 'GET',
        dataType: 'json',
        success: function(data){
            let html = '';
            data.forEach(p => {
                html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${p.name}</h5>
                            <p class="card-text">${p.description}</p>
                            <p class="mt-auto"><strong>${p.price}€</strong> - <small>${p.type_tarification}</small></p>
                            <button class="btn btn-primary btn-sm mt-2 add-prestation" data-id="${p.id}">Ajouter</button>
                        </div>
                    </div>
                </div>`;
            });
            $("#prestationsContainer").html(html);
        }
    });

    // Ajouter prestation via AJAX
    $(document).on("click", ".add-prestation", function(){
        const prestationId = $(this).data("id");
        $.ajax({
            url: "add_prestation.php",
            method: "POST",
            data: { id: prestationId },
            dataType: "json",
            success: function(res){
                alert(res.message);
                location.reload(); // Recharge la page pour afficher la nouvelle prestation
            },
            error: function(){
                alert("Erreur lors de l'ajout de la prestation.");
            }
        });
    });
    <?php endif; ?>
});

// mettre à jour le statut de la réservation
function refreshReservations() {
    $.ajax({
        url: 'refresh_reservations.php', // un petit fichier qui renvoie uniquement tes réservations client en JSON
        method: 'GET',
        dataType: 'json',
        success: function(data){
            data.forEach(res => {
                const badge = $("#statut_resa_" + res.id);
                if(badge.length){
                    // Mettre à jour texte
                    badge.text(res.statut.charAt(0).toUpperCase() + res.statut.slice(1));

                    // Mettre à jour couleur
                    badge.removeClass("bg-success bg-warning bg-danger text-dark");
                    if(res.statut === 'validée') badge.addClass("bg-success");
                    else if(res.statut === 'refusée') badge.addClass("bg-danger");
                    else badge.addClass("bg-warning text-dark");
                }
            });
        }
    });
}

// Vérifie les statuts toutes les 5 secondes
setInterval(refreshReservations, 5000);
</script>
</body>
</html>
<?php
// Démarrer la session pour récupérer l'utilisateur connecté
session_start();

// Vérifier que l'utilisateur est un client
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// Inclure la fonction pour lire et écrire les fichiers JSON
require_once "includes/json_data.php";

// Vérifier que l'ID de la prestation est passé en POST
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de prestation manquant']);
    exit;
}

$prestationId = $_POST['id'];
$email = $_SESSION['email'] ?? '';

// Lire les prestations disponibles
$prestations = readJson("prestations.json") ?: [];
$prestations_client = readJson("prestations_client.json") ?: [];

// Lire les réservations
$reservations = readJson("reservation.json") ?: [];

// Chercher la **réservation validée la plus récente** de l'utilisateur
$validReservation = null;
foreach ($reservations as $res) {
    if (($res['email'] ?? '') === $email && $res['statut'] === 'validée') {
        $validReservation = $res;
    }
}

if (!$validReservation) {
    echo json_encode(['success' => false, 'message' => 'Aucune réservation validée trouvée']);
    exit;
}

// Chercher la prestation sélectionnée dans le fichier prestations.json
$prestation = null;
foreach ($prestations as $p) {
    if ($p['id'] == $prestationId) {
        $prestation = $p;
        break;
    }
}

if (!$prestation) {
    echo json_encode(['success' => false, 'message' => 'Prestation introuvable']);
    exit;
}

// Vérifier si l'utilisateur a déjà ajouté cette prestation pour cette réservation
foreach ($prestations_client as $p) {
    if ($p['user_email'] === $email && $p['reservation_id'] === $validReservation['id'] && $p['prestation']['id'] == $prestationId) {
        echo json_encode(['success' => false, 'message' => 'Vous avez déjà choisi cette prestation']);
        exit;
    }
}

// Ajouter la prestation dans le fichier prestations_client.json
$newPrestation = [
    'user_email' => $email,
    'reservation_id' => $validReservation['id'],
    'prestation' => $prestation,
    'statut' => 'en_attente', // L'admin doit valider ou refuser
    'adresse' => '',           // L'admin remplira manuellement
    'heure' => ''              // L'admin remplira manuellement
];

$prestations_client[] = $newPrestation;

// Enregistrer le fichier JSON mis à jour
writeJson("prestations_client.json", $prestations_client);

// Retourner le résultat avec l'ID de réservation pour permettre la mise à jour du DOM
echo json_encode([
    'success'        => true,
    'message'        => 'Prestation ajoutée avec succès, en attente de validation',
    'reservation_id' => $validReservation['id']
]);
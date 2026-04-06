<?php
session_start();
require_once "includes/json_data.php";

header('Content-Type: application/json');

// Seul un client connecté peut soumettre une demande
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$reservationId = $_POST['reservation_id'] ?? '';
$activityId    = (int)($_POST['activity_id'] ?? 0);
$creneau       = $_POST['creneau'] ?? '';
$nbPersonnes   = (int)($_POST['nb_personnes'] ?? 1);
$message       = trim($_POST['message'] ?? '');
$email         = $_SESSION['email'] ?? '';

// Validation du créneau
if (!in_array($creneau, ['heure', 'demi-journee', 'journee'])) {
    echo json_encode(['success' => false, 'message' => 'Créneau invalide']);
    exit;
}

// Vérifier que la réservation appartient au client et est validée
$reservations = readJson("reservation.json") ?: [];
$reservation  = null;
foreach ($reservations as $res) {
    if ($res['id'] == $reservationId && ($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée') {
        $reservation = $res;
        break;
    }
}

if (!$reservation) {
    echo json_encode(['success' => false, 'message' => 'Réservation introuvable ou non validée']);
    exit;
}

// Valider le nombre de personnes
$maxPersonnes = (int)($reservation['nb_personnes'] ?? 1);
if ($nbPersonnes < 1 || $nbPersonnes > $maxPersonnes) {
    echo json_encode(['success' => false, 'message' => "Nombre de personnes invalide (max : {$maxPersonnes})"]);
    exit;
}

// Vérifier que l'activité existe
$activities = readJson("activities.json") ?: [];
$activity   = null;
foreach ($activities as $act) {
    if ($act['id'] == $activityId) {
        $activity = $act;
        break;
    }
}

if (!$activity) {
    echo json_encode(['success' => false, 'message' => 'Activité introuvable']);
    exit;
}

// Enregistrer la demande
$requests = readJson("activity_requests.json") ?: [];

$newRequest = [
    'id'             => generateId(),
    'reservation_id' => $reservation['id'],
    'user_email'     => $email,
    'user_nom'       => $reservation['nom'] ?? '',
    'activity_id'    => $activityId,
    'activity_nom'   => $activity['nom'],
    'creneau'        => $creneau,
    'nb_personnes'   => $nbPersonnes,
    'message'        => $message,
    'statut'         => 'en_attente',
    'date_creation'  => date('Y-m-d H:i:s')
];

$requests[] = $newRequest;
writeJson("activity_requests.json", $requests);

echo json_encode([
    'success'    => true,
    'message'    => 'Demande envoyée avec succès',
    'request_id' => $newRequest['id']
]);

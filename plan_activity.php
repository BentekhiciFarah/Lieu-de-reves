<?php
session_start();
require_once "includes/json_data.php";

header('Content-Type: application/json');

// Seul l'admin peut planifier une activité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$activityId = (int)($_POST['activity_id'] ?? 0);
$date       = $_POST['date'] ?? '';
$heure      = $_POST['heure'] ?? '';
$animateur  = trim($_POST['animateur'] ?? '');
$creneau    = $_POST['creneau'] ?? 'heure';
$requestIds = $_POST['request_ids'] ?? [];

if (!$activityId || !$date || !$animateur || empty($requestIds)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes (activité, date, animateur, demandes)']);
    exit;
}

if (!in_array($creneau, ['heure', 'demi-journee', 'journee'])) {
    echo json_encode(['success' => false, 'message' => 'Créneau invalide']);
    exit;
}

// Récupérer l'activité pour vérifier les contraintes min/max participants
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

// Récupérer les demandes sélectionnées
$allRequests      = readJson("activity_requests.json") ?: [];
$selectedRequests = [];
foreach ($allRequests as $req) {
    if (in_array((string)$req['id'], array_map('strval', $requestIds))) {
        $selectedRequests[] = $req;
    }
}

if (empty($selectedRequests)) {
    echo json_encode(['success' => false, 'message' => 'Aucune demande sélectionnée']);
    exit;
}

// Vérifier le nombre minimum de participants
$totalPersonnes = 0;
foreach ($selectedRequests as $req) {
    $totalPersonnes += (int)($req['nb_personnes'] ?? 1);
}

$minParticipants = (int)($activity['min_participants'] ?? 1);
if ($totalPersonnes < $minParticipants) {
    echo json_encode([
        'success' => false,
        'message' => "Participants insuffisants : minimum {$minParticipants} requis pour cette activité (sélectionnés : {$totalPersonnes})"
    ]);
    exit;
}

$maxParticipants = (int)($activity['max_participants'] ?? PHP_INT_MAX);
if ($totalPersonnes > $maxParticipants) {
    echo json_encode([
        'success' => false,
        'message' => "Trop de participants : maximum {$maxParticipants} pour cette activité (sélectionnés : {$totalPersonnes})"
    ]);
    exit;
}

// Construire la liste des participants
$participants = [];
foreach ($selectedRequests as $req) {
    $participants[] = [
        'reservation_id'      => $req['reservation_id'],
        'user_email'          => $req['user_email'],
        'user_nom'            => $req['user_nom'],
        'nb_personnes'        => (int)($req['nb_personnes'] ?? 1),
        'message_participant' => ''
    ];
}

// Créer l'activité planifiée
$plannedActivities = readJson("planned_activities.json") ?: [];

$newPlanned = [
    'id'            => generateId(),
    'activity_id'   => $activityId,
    'activity_nom'  => $activity['nom'],
    'prix_par_pers' => (float)($activity['prix'] ?? 0),
    'date'          => $date,
    'heure'         => $heure,
    'creneau'       => $creneau,
    'animateur'     => $animateur,
    'request_ids'   => $requestIds,
    'participants'  => $participants,
    'date_creation' => date('Y-m-d H:i:s')
];

$plannedActivities[] = $newPlanned;
writeJson("planned_activities.json", $plannedActivities);

// Marquer les demandes sélectionnées comme planifiées
foreach ($allRequests as &$req) {
    if (in_array((string)$req['id'], array_map('strval', $requestIds))) {
        $req['statut'] = 'planifiee';
    }
}
unset($req);
writeJson("activity_requests.json", $allRequests);

$dateFormate = (new DateTime($date))->format('d/m/Y');
echo json_encode([
    'success' => true,
    'message' => "Activité « {$activity['nom']} » planifiée le {$dateFormate} à {$heure} avec {$animateur}"
]);

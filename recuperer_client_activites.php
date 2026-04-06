<?php
session_start();
require_once "includes/json_data.php";

header('Content-Type: application/json');

// Seul un client connecté peut consulter ses activités planifiées
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$reservationId = $_GET['reservation_id'] ?? '';
$email         = $_SESSION['email'] ?? '';

if (!$reservationId) {
    echo json_encode([]);
    exit;
}

$plannedActivities = readJson("planned_activities.json") ?: [];
$result = [];

foreach ($plannedActivities as $pa) {
    foreach (($pa['participants'] ?? []) as $participant) {
        // Inclure si le client est participant via cette réservation
        if ($participant['reservation_id'] == $reservationId && ($participant['user_email'] ?? '') === $email) {
            $result[] = $pa;
            break;
        }
    }
}

echo json_encode($result);

<?php
session_start();
require_once "includes/json_data.php";
require_once "facture.php";

// Vérification : seul un client connecté peut accéder à sa facture
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$reservationId = $_GET['reservation_id'] ?? '';
$email = $_SESSION['email'] ?? '';

if (!$reservationId) {
    echo json_encode(['success' => false, 'message' => 'ID de réservation manquant']);
    exit;
}

$reservations      = readJson("reservation.json") ?: [];
$roomTypes         = readJson("room_types.json") ?: [];
$prestationsAll    = readJson("prestations_client.json") ?: [];
$plannedActivities = readJson("planned_activities.json") ?: [];

foreach ($reservations as $res) {
    if ($res['id'] == $reservationId && ($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée') {

        $facture = calculerFactureReservation($res, $roomTypes, $prestationsAll, $plannedActivities);

        // Prestations liées à cette réservation
        $prestations = array_values(array_filter($prestationsAll, function($p) use ($email, $reservationId) {
            return ($p['user_email'] ?? '') === $email && $p['reservation_id'] == $reservationId;
        }));

        header('Content-Type: application/json');
        echo json_encode([
            'success'        => true,
            'reservation_id' => $reservationId,
            'facture'        => $facture,
            'prestations'    => $prestations
        ]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Réservation introuvable']);

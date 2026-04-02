<?php
session_start();
require_once "includes/json_data.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    http_response_code(403);
    exit;
}

$email = $_SESSION['email'] ?? '';
$reservations = readJson("reservation.json") ?: [];

$clientReservations = array_values(array_filter($reservations, function($r) use($email){
    return ($r['email'] ?? '') === $email;
}));

// On renvoie seulement l'id et le statut
$result = array_map(function($r){
    return ['id' => $r['id'], 'statut' => $r['statut']];
}, $clientReservations);

header('Content-Type: application/json');
echo json_encode($result);
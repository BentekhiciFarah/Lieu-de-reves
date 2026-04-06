<?php
session_start();
require_once "includes/json_data.php";

header('Content-Type: application/json');

// Seul l'admin peut consulter les demandes par date
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$date = $_GET['date'] ?? '';

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$requests     = readJson("activity_requests.json") ?: [];
$reservations = readJson("reservation.json") ?: [];

// Construire une map reservation_id → réservation pour accès rapide
$resMap = [];
foreach ($reservations as $res) {
    $resMap[$res['id']] = $res;
}

// Garder les demandes en_attente dont le séjour couvre la date sélectionnée
// (date_debut <= date < date_fin : la date doit être pendant le séjour)
$result = [];
foreach ($requests as $req) {
    if (($req['statut'] ?? '') !== 'en_attente') continue;

    $res = $resMap[$req['reservation_id']] ?? null;
    if (!$res) continue;

    $dateDebut = $res['date_debut'] ?? '';
    $dateFin   = $res['date_fin'] ?? '';

    // La demande est visible sur chaque jour du séjour (réplication)
    if ($date >= $dateDebut && $date < $dateFin) {
        $result[] = array_merge($req, [
            'reservation_date_debut'   => $dateDebut,
            'reservation_date_fin'     => $dateFin,
            'reservation_nb_personnes' => (int)($res['nb_personnes'] ?? 0)
        ]);
    }
}

echo json_encode($result);

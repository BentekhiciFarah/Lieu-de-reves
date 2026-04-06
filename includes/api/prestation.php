<?php
session_start();
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── GET action=liste ── Client : catalogue des prestations disponibles
    case 'liste':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode([]);
            exit;
        }

        $prestations = readJson("prestations.json") ?: [];
        echo json_encode($prestations);
        break;

    // ── POST action=ajouter ── Client : choisir une prestation pour sa réservation
    case 'ajouter':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de prestation manquant']);
            exit;
        }

        $prestationId     = $_POST['id'];
        $email            = $_SESSION['email'] ?? '';

        $prestations        = readJson("prestations.json") ?: [];
        $prestationsClient  = readJson("prestations_client.json") ?: [];
        $reservations       = readJson("reservation.json") ?: [];

        // Réservation validée la plus récente du client
        $reservationValide = null;
        foreach ($reservations as $res) {
            if (($res['email'] ?? '') === $email && $res['statut'] === 'validée') {
                $reservationValide = $res;
            }
        }

        if (!$reservationValide) {
            echo json_encode(['success' => false, 'message' => 'Aucune réservation validée trouvée']);
            exit;
        }

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

        // Vérifier si déjà ajoutée pour cette réservation
        foreach ($prestationsClient as $p) {
            if ($p['user_email'] === $email && $p['reservation_id'] === $reservationValide['id'] && $p['prestation']['id'] == $prestationId) {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà choisi cette prestation']);
                exit;
            }
        }

        $nouvellePrestation = [
            'user_email'     => $email,
            'reservation_id' => $reservationValide['id'],
            'prestation'     => $prestation,
            'statut'         => 'validée',
            'adresse'        => '',
            'heure'          => ''
        ];

        $prestationsClient[] = $nouvellePrestation;
        writeJson("prestations_client.json", $prestationsClient);

        echo json_encode([
            'success'        => true,
            'message'        => 'Prestation ajoutée avec succès, en attente de validation',
            'reservation_id' => $reservationValide['id']
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}

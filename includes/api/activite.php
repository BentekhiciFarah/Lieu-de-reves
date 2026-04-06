<?php
session_start();
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── GET action=par_date ── Admin : demandes en attente pour une date donnée
    case 'par_date':
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

        $demandes     = readJson("activity_requests.json") ?: [];
        $reservations = readJson("reservation.json") ?: [];

        $resMap = [];
        foreach ($reservations as $res) {
            $resMap[$res['id']] = $res;
        }

        $resultat = [];
        foreach ($demandes as $dem) {
            if (($dem['statut'] ?? '') !== 'en_attente') continue;

            $res = $resMap[$dem['reservation_id']] ?? null;
            if (!$res) continue;

            $dateDebut = $res['date_debut'] ?? '';
            $dateFin   = $res['date_fin'] ?? '';

            if ($date >= $dateDebut && $date < $dateFin) {
                $resultat[] = array_merge($dem, [
                    'reservation_date_debut'   => $dateDebut,
                    'reservation_date_fin'     => $dateFin,
                    'reservation_nb_personnes' => (int)($res['nb_personnes'] ?? 0)
                ]);
            }
        }

        echo json_encode($resultat);
        break;

    // ── GET action=client ── Client : activités planifiées pour une réservation
    case 'client':
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

        $activitesPlanifiees = readJson("planned_activities.json") ?: [];
        $resultat = [];

        foreach ($activitesPlanifiees as $pa) {
            foreach (($pa['participants'] ?? []) as $participant) {
                if ($participant['reservation_id'] == $reservationId && ($participant['user_email'] ?? '') === $email) {
                    $resultat[] = $pa;
                    break;
                }
            }
        }

        echo json_encode($resultat);
        break;

    // ── POST action=demande ── Client : soumettre une demande d'activité
    case 'demande':
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

        if (!in_array($creneau, ['heure', 'demi-journee', 'journee'])) {
            echo json_encode(['success' => false, 'message' => 'Créneau invalide']);
            exit;
        }

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

        $maxPersonnes = (int)($reservation['nb_personnes'] ?? 1);
        if ($nbPersonnes < 1 || $nbPersonnes > $maxPersonnes) {
            echo json_encode(['success' => false, 'message' => "Nombre de personnes invalide (max : {$maxPersonnes})"]);
            exit;
        }

        $activites = readJson("activities.json") ?: [];
        $activite  = null;
        foreach ($activites as $act) {
            if ($act['id'] == $activityId) {
                $activite = $act;
                break;
            }
        }

        if (!$activite) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable']);
            exit;
        }

        $demandes = readJson("activity_requests.json") ?: [];

        $nouvelleDemande = [
            'id'             => generateId(),
            'reservation_id' => $reservation['id'],
            'user_email'     => $email,
            'user_nom'       => $reservation['nom'] ?? '',
            'activity_id'    => $activityId,
            'activity_nom'   => $activite['nom'],
            'creneau'        => $creneau,
            'nb_personnes'   => $nbPersonnes,
            'message'        => $message,
            'statut'         => 'en_attente',
            'date_creation'  => date('Y-m-d H:i:s')
        ];

        $demandes[] = $nouvelleDemande;
        writeJson("activity_requests.json", $demandes);

        echo json_encode([
            'success'    => true,
            'message'    => 'Demande envoyée avec succès',
            'request_id' => $nouvelleDemande['id']
        ]);
        break;

    // ── POST action=message ── Client : ajouter un message à une activité planifiée
    case 'message':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $plannedId = $_POST['planned_id'] ?? '';
        $message   = trim($_POST['message'] ?? '');
        $email     = $_SESSION['email'] ?? '';

        if (!$plannedId) {
            echo json_encode(['success' => false, 'message' => 'ID de l\'activité manquant']);
            exit;
        }

        $activitesPlanifiees = readJson("planned_activities.json") ?: [];
        $mis_a_jour = false;

        foreach ($activitesPlanifiees as &$pa) {
            if ((string)$pa['id'] === (string)$plannedId) {
                foreach ($pa['participants'] as &$participant) {
                    if (($participant['user_email'] ?? '') === $email) {
                        $participant['message_participant'] = $message;
                        $mis_a_jour = true;
                        break;
                    }
                }
                unset($participant);
                break;
            }
        }
        unset($pa);

        if (!$mis_a_jour) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable ou vous n\'êtes pas participant']);
            exit;
        }

        writeJson("planned_activities.json", $activitesPlanifiees);
        echo json_encode(['success' => true, 'message' => 'Message enregistré']);
        break;

    // ── POST action=planifier ── Admin : planifier une activité à partir de demandes
    case 'planifier':
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

        $activites = readJson("activities.json") ?: [];
        $activite  = null;
        foreach ($activites as $act) {
            if ($act['id'] == $activityId) {
                $activite = $act;
                break;
            }
        }

        if (!$activite) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable']);
            exit;
        }

        $toutesLesDemandes  = readJson("activity_requests.json") ?: [];
        $demandesSelectionnees = [];
        foreach ($toutesLesDemandes as $dem) {
            if (in_array((string)$dem['id'], array_map('strval', $requestIds))) {
                $demandesSelectionnees[] = $dem;
            }
        }

        if (empty($demandesSelectionnees)) {
            echo json_encode(['success' => false, 'message' => 'Aucune demande sélectionnée']);
            exit;
        }

        $totalPersonnes = 0;
        foreach ($demandesSelectionnees as $dem) {
            $totalPersonnes += (int)($dem['nb_personnes'] ?? 1);
        }

        $minParticipants = (int)($activite['min_participants'] ?? 1);
        if ($totalPersonnes < $minParticipants) {
            echo json_encode([
                'success' => false,
                'message' => "Participants insuffisants : minimum {$minParticipants} requis pour cette activité (sélectionnés : {$totalPersonnes})"
            ]);
            exit;
        }

        $maxParticipants = (int)($activite['max_participants'] ?? PHP_INT_MAX);
        if ($totalPersonnes > $maxParticipants) {
            echo json_encode([
                'success' => false,
                'message' => "Trop de participants : maximum {$maxParticipants} pour cette activité (sélectionnés : {$totalPersonnes})"
            ]);
            exit;
        }

        $participants = [];
        foreach ($demandesSelectionnees as $dem) {
            $participants[] = [
                'reservation_id'      => $dem['reservation_id'],
                'user_email'          => $dem['user_email'],
                'user_nom'            => $dem['user_nom'],
                'nb_personnes'        => (int)($dem['nb_personnes'] ?? 1),
                'message_participant' => ''
            ];
        }

        $activitesPlanifiees = readJson("planned_activities.json") ?: [];

        $nouvellePlanification = [
            'id'            => generateId(),
            'activity_id'   => $activityId,
            'activity_nom'  => $activite['nom'],
            'prix_par_pers' => (float)($activite['prix'] ?? 0),
            'date'          => $date,
            'heure'         => $heure,
            'creneau'       => $creneau,
            'animateur'     => $animateur,
            'request_ids'   => $requestIds,
            'participants'  => $participants,
            'date_creation' => date('Y-m-d H:i:s')
        ];

        $activitesPlanifiees[] = $nouvellePlanification;
        writeJson("planned_activities.json", $activitesPlanifiees);

        foreach ($toutesLesDemandes as &$dem) {
            if (in_array((string)$dem['id'], array_map('strval', $requestIds))) {
                $dem['statut'] = 'planifiee';
            }
        }
        unset($dem);
        writeJson("activity_requests.json", $toutesLesDemandes);

        $dateFormate = (new DateTime($date))->format('d/m/Y');
        echo json_encode([
            'success' => true,
            'message' => "Activité « {$activite['nom']} » planifiée le {$dateFormate} à {$heure} avec {$animateur}"
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}

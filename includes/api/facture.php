<?php

// ── Fonctions de calcul 

function calculerNombreNuits($dateDebut, $dateFin) {
    $debut    = new DateTime($dateDebut);
    $fin      = new DateTime($dateFin);
    $interval = $debut->diff($fin);
    return max(1, (int)$interval->days);
}

function getPrixChambreParNuit($typeChambre, $roomTypes) {
    foreach ($roomTypes as $room) {
        if (($room['type'] ?? '') === $typeChambre) {
            return (float)($room['prix_par_nuit'] ?? 0);
        }
    }
    return 0;
}

function getNomChambre($typeChambre, $roomTypes) {
    foreach ($roomTypes as $room) {
        if (($room['type'] ?? '') === $typeChambre) {
            return $room['nom'] ?? $typeChambre;
        }
    }
    return $typeChambre;
}

function calculerFactureReservation($reservation, $roomTypes, $prestationsClient, $activitesPlanifiees = []) {
    $lignes = [];

    $nbNuits     = calculerNombreNuits($reservation['date_debut'], $reservation['date_fin']);
    $prixParNuit = getPrixChambreParNuit($reservation['type_chambre'], $roomTypes);
    $nomChambre  = getNomChambre($reservation['type_chambre'], $roomTypes);

    $montantHebergement = $nbNuits * $prixParNuit;

    $lignes[] = [
        'label'   => "Hébergement - {$nomChambre} ({$nbNuits} nuit(s))",
        'montant' => $montantHebergement
    ];

    $totalPrestations = 0;

    foreach ($prestationsClient as $pc) {
        if (($pc['reservation_id'] ?? null) == ($reservation['id'] ?? null)) {
            $prix     = (float)($pc['prestation']['price'] ?? $pc['prestation']['prix'] ?? 0);
            $quantite = (int)($pc['quantite'] ?? 1);
            $nom      = $pc['prestation']['name'] ?? $pc['prestation']['nom'] ?? 'Prestation';
            $montant  = $prix * $quantite;

            $lignes[] = [
                'label'   => "Prestation - {$nom} x{$quantite}",
                'montant' => $montant
            ];

            $totalPrestations += $montant;
        }
    }

    foreach ($activitesPlanifiees as $pa) {
        // Ignorer les activités avec une date invalide
        if (empty($pa['date']) || !strtotime($pa['date'])) continue;

        foreach (($pa['participants'] ?? []) as $participant) {
            if ($participant['reservation_id'] == ($reservation['id'] ?? null)) {
                $nbPers  = (int)($participant['nb_personnes'] ?? 1);
                $prix    = (float)($pa['prix_par_pers'] ?? 0);
                $montant = $prix * $nbPers;
                $date    = (new DateTime($pa['date']))->format('d/m/Y');

                $lignes[] = [
                    'label'   => "Activité - {$pa['activity_nom']} le {$date} ({$nbPers} pers.)",
                    'montant' => $montant
                ];
                break;
            }
        }
    }

    $reduction = (float)($reservation['reduction_prestations'] ?? 0);

    if ($reduction > 0 && $totalPrestations > 0) {
        $lignes[] = [
            'label'   => "Réduction sur prestations (-{$reduction}%)",
            'montant' => -($totalPrestations * ($reduction / 100))
        ];
    }

    $arrhes = (float)($reservation['arrhes'] ?? 0);

    if ($arrhes > 0) {
        $lignes[] = [
            'label'   => "Arrhes versées",
            'montant' => -$arrhes
        ];
    }

    $total = 0;
    foreach ($lignes as $ligne) {
        $total += $ligne['montant'];
    }

    return [
        'nb_nuits' => $nbNuits,
        'lignes'   => $lignes,
        'total'    => $total
    ];
}

// ── Endpoint API (uniquement si appelé directement) 

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    session_start();
    require_once __DIR__ . '/../json_data.php';

    header('Content-Type: application/json');

    $action = $_REQUEST['action'] ?? '';

    switch ($action) {

        // ── GET action=detail ── Client : facture d'une réservation
        case 'detail':
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès refusé']);
                exit;
            }

            $reservationId = $_GET['reservation_id'] ?? '';
            $email         = $_SESSION['email'] ?? '';

            if (!$reservationId) {
                echo json_encode(['success' => false, 'message' => 'ID de réservation manquant']);
                exit;
            }

            $reservations        = readJson("reservation.json") ?: [];
            $roomTypes           = readJson("room_types.json") ?: [];
            $prestationsClient   = readJson("prestations_client.json") ?: [];
            $activitesPlanifiees = readJson("planned_activities.json") ?: [];

            foreach ($reservations as $res) {
                if ($res['id'] == $reservationId && ($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée') {

                    $facture = calculerFactureReservation($res, $roomTypes, $prestationsClient, $activitesPlanifiees);

                    $prestations = array_values(array_filter($prestationsClient, function($p) use ($email, $reservationId) {
                        return ($p['user_email'] ?? '') === $email && $p['reservation_id'] == $reservationId;
                    }));

                    echo json_encode([
                        'success'        => true,
                        'reservation_id' => $reservationId,
                        'facture'        => $facture,
                        'prestations'    => $prestations
                    ]);
                    exit;
                }
            }

            echo json_encode(['success' => false, 'message' => 'Réservation introuvable']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
}

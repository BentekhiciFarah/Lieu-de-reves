<?php

// fonction pour calculer le nombre de nuits entre deux dates
function calculerNombreNuits($dateDebut, $dateFin) {
    // Convertir les chaînes de caractères en objets DateTime
    $debut = new DateTime($dateDebut);
    $fin = new DateTime($dateFin);
    // Calculer la différence en jours entre les deux dates
    $interval = $debut->diff($fin);
    // Retourner le nombre de jours, en s'assurant que c'est au moins 1 nuit
    return max(1, (int)$interval->days);
}

// fonction pour obtenir le prix par nuit d'une chambre en fonction de son type
function getPrixChambreParNuit($typeChambre, $chambre) {
    // Parcourir les types de chambres pour trouver celui qui correspond au type demandé
    foreach ($chambre as $room) {
        if (($room['type'] ?? '') === $typeChambre) {
            // Retourner le prix par nuit de ce type de chambre, ou 0 si non défini
            return (float)($room['prix_par_nuit'] ?? 0);
        }
    }
    return 0;
}

// fonction pour obtenir le nom d'une chambre en fonction de son type
function getNomChambre($typeChambre, $roomTypes) {
    // Parcourir les types de chambres pour trouver celui qui correspond au type demandé
    foreach ($roomTypes as $room) {
        // Si le type de chambre correspond, retourner son nom (ou le type lui-même si le nom n'est pas défini)
        if (($room['type'] ?? '') === $typeChambre) {
            return $room['nom'] ?? $typeChambre;
        }
    }
    // Si aucun type de chambre ne correspond, retourner le type lui-même comme nom
    return $typeChambre;
}

// fonction principale pour calculer la facture d'une réservation
function calculerFactureReservation($reservation, $roomTypes, $prestationsClient, $plannedActivities = []) {
    $lignes = [];
    // calcul du nombre de nuits et du montant de l'hébergement
    $nbNuits = calculerNombreNuits($reservation['date_debut'], $reservation['date_fin']);
    $prixParNuit = getPrixChambreParNuit($reservation['type_chambre'], $roomTypes);
    $nomChambre = getNomChambre($reservation['type_chambre'], $roomTypes);

    // calcul du montant de l'hébergement
    $montantHebergement = $nbNuits * $prixParNuit;

    // ajout de la ligne d'hébergement à la facture
    $lignes[] = [
        'label' => "Hébergement - {$nomChambre} ({$nbNuits} nuit(s))",
        'montant' => $montantHebergement
    ];

    $totalPrestations = 0;

    // Prestations associées à cette réservation
    foreach ($prestationsClient as $pc) {
        // Vérifier si la prestation est liée à la réservation en comparant les identifiants de réservation
        if (($pc['reservation_id'] ?? null) == ($reservation['id'] ?? null)) {
            // Calculer le montant de la prestation en fonction du prix et de la quantité
            $prix = (float)$pc['prestation']['price'];
            $quantite = (int)($pc['quantite'] ?? 1);
            $nom = $pc['prestation']['name'];

            $montant = $prix * $quantite;

            // Ajouter une ligne pour cette prestation à la facture
            $lignes[] = [
                'label' => "Prestation - {$nom} x{$quantite}",
                'montant' => $montant
            ];
            // Ajouter le montant de cette prestation au total des prestations pour calculer la réduction éventuelle
            $totalPrestations += $montant;
        }
    }

    // Activités planifiées pour cette réservation
    foreach ($plannedActivities as $pa) {
        // Vérifier si l'activité est liée à la réservation en comparant les identifiants de réservation dans les participants
        foreach (($pa['participants'] ?? []) as $participant) {
            // Si le participant est lié à la réservation, ajouter une ligne pour cette activité à la facture
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

    // Calcul de la réduction éventuelle sur les prestations
    $reduction = (float)($reservation['reduction_prestations'] ?? 0);

    // Si une réduction est définie et qu'il y a des prestations, calculer le montant de la réduction et l'ajouter à la facture
    if ($reduction > 0 && $totalPrestations > 0) {
        $montantReduction = -($totalPrestations * ($reduction / 100));

        // Ajouter une ligne pour la réduction sur les prestations à la facture
        $lignes[] = [
            'label' => "Réduction sur prestations (-{$reduction}%)",
            'montant' => $montantReduction
        ];
    }

    // Calcul des euros versées, qui seront déduites du total à payer
    $euros = (float)($reservation['arrhes'] ?? 0);

    // Si des euros ont été versés, les ajouter à la facture en tant que montant négatif pour les déduire du total
    if ($euros > 0) {
        $lignes[] = [
            'label' => "Arrhes versées",
            'montant' => -$euros
        ];
    }

    // Calcul du total de la facture en sommant les montants de toutes les lignes
    $total = 0;
    foreach ($lignes as $ligne) {
        $total += $ligne['montant'];
    }

    // Retourner un tableau contenant le nombre de nuits, les lignes de la facture et le total à payer
    return [
        'nb_nuits' => $nbNuits,
        'lignes' => $lignes,
        'total' => $total
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

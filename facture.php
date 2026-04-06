<?php

function calculerNombreNuits($dateDebut, $dateFin) {
    $debut = new DateTime($dateDebut);
    $fin = new DateTime($dateFin);
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

function calculerFactureReservation($reservation, $roomTypes, $prestationsClient, $plannedActivities = []) {
    $lignes = [];

    $nbNuits = calculerNombreNuits($reservation['date_debut'], $reservation['date_fin']);
    $prixParNuit = getPrixChambreParNuit($reservation['type_chambre'], $roomTypes);
    $nomChambre = getNomChambre($reservation['type_chambre'], $roomTypes);

    $montantHebergement = $nbNuits * $prixParNuit;

    $lignes[] = [
        'label' => "Hébergement - {$nomChambre} ({$nbNuits} nuit(s))",
        'montant' => $montantHebergement
    ];

    $totalPrestations = 0;

    foreach ($prestationsClient as $pc) {
        if (($pc['reservation_id'] ?? null) == ($reservation['id'] ?? null)) {
            $prix = (float)($pc['prestation']['price'] ?? $pc['prestation']['prix'] ?? 0);
            $quantite = (int)($pc['quantite'] ?? 1);
            $nom = $pc['prestation']['name'] ?? $pc['prestation']['nom'] ?? 'Prestation';

            $montant = $prix * $quantite;

            $lignes[] = [
                'label' => "Prestation - {$nom} x{$quantite}",
                'montant' => $montant
            ];

            $totalPrestations += $montant;
        }
    }

    // Activités planifiées pour cette réservation
    foreach ($plannedActivities as $pa) {
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
        $montantReduction = -($totalPrestations * ($reduction / 100));

        $lignes[] = [
            'label' => "Réduction sur prestations (-{$reduction}%)",
            'montant' => $montantReduction
        ];
    }

    $arrhes = (float)($reservation['arrhes'] ?? 0);

    if ($arrhes > 0) {
        $lignes[] = [
            'label' => "Arrhes versées",
            'montant' => -$arrhes
        ];
    }

    $total = 0;
    foreach ($lignes as $ligne) {
        $total += $ligne['montant'];
    }

    return [
        'nb_nuits' => $nbNuits,
        'lignes' => $lignes,
        'total' => $total
    ];
}
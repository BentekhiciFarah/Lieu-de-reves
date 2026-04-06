<?php
session_start();
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$nom         = trim($_POST['nom'] ?? '');
$email       = trim($_POST['email'] ?? '');
$dateDebut   = $_POST['date_debut'] ?? '';
$dateFin     = $_POST['date_fin'] ?? '';
$nbPersonnes = (int)($_POST['nb_personnes'] ?? 0);
$typeChambre = $_POST['type_chambre'] ?? '';
$activites   = $_POST['activites'] ?? [];
$message     = trim($_POST['message'] ?? '');

// Validation des champs obligatoires
if (!$nom || !$email || !$dateDebut || !$dateFin || !$nbPersonnes || !$typeChambre) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide.']);
    exit;
}

if ($dateFin < $dateDebut) {
    echo json_encode(['success' => false, 'message' => 'La date de fin doit être postérieure ou égale à la date de début.']);
    exit;
}

if ($dateDebut < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'La date de début ne peut pas être dans le passé.']);
    exit;
}

if ($nbPersonnes < 1) {
    echo json_encode(['success' => false, 'message' => 'Le nombre de personnes doit être supérieur à 0.']);
    exit;
}

// Validation des activités sélectionnées
$activitiesData = readJson("activities.json") ?: [];

foreach ($activites as $activiteId) {
    $activiteTrouvee = false;

    foreach ($activitiesData as $activite) {
        if ((string)$activite['id'] === (string)$activiteId) {
            $activiteTrouvee = true;
            $nomActivite     = $activite['nom'] ?? 'Activité';
            $minParticipants = (int)($activite['min_participants'] ?? 1);
            $maxParticipants = (int)($activite['max_participants'] ?? PHP_INT_MAX);

            if ($nbPersonnes < $minParticipants) {
                echo json_encode(['success' => false, 'message' => "L'activité \"{$nomActivite}\" nécessite au minimum {$minParticipants} participants."]);
                exit;
            }
            if ($nbPersonnes > $maxParticipants) {
                echo json_encode(['success' => false, 'message' => "L'activité \"{$nomActivite}\" accepte au maximum {$maxParticipants} participants."]);
                exit;
            }
            break;
        }
    }

    if (!$activiteTrouvee) {
        echo json_encode(['success' => false, 'message' => 'Une activité sélectionnée est invalide.']);
        exit;
    }
}

// Enregistrement de la réservation
$reservations = readJson("reservation.json") ?: [];

$nouvelleReservation = [
    'id'            => generateId(),
    'nom'           => $nom,
    'email'         => $email,
    'date_debut'    => $dateDebut,
    'date_fin'      => $dateFin,
    'nb_personnes'  => $nbPersonnes,
    'type_chambre'  => $typeChambre,
    'activites'     => $activites,
    'message'       => $message,
    'statut'        => 'en_attente',
    'date_creation' => date('Y-m-d H:i:s')
];

$reservations[] = $nouvelleReservation;
writeJson("reservation.json", $reservations);

echo json_encode([
    'success' => true,
    'message' => 'Votre demande de réservation a été envoyée. L\'administrateur la validera prochainement.'
]);

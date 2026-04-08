<?php
/*
 * reservation.php : enregistrement d'une nouvelle demande de réservation.
 * 
 * recevoir les données du formulaire de réservation, les valider, et les
 * enregistrer dans reservation.json avec le statut "en_attente".
 *
 * Le fichier est appelé en AJAX depuis le formulaire de réservation public.
 * Toute réponse est retournée en JSON, succès ou erreur.
 */
session_start();
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

// methode HTTP attendue : POST pour pouvoir envoyer les données du formulaire
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération et validation des données du formulaire de réservation
$nom         = trim($_POST['nom'] ?? '');
$email       = trim($_POST['email'] ?? '');
$dateDebut   = $_POST['date_debut'] ?? '';
$dateFin     = $_POST['date_fin'] ?? '';
$nbPersonnes = (int)($_POST['nb_personnes'] ?? 0);
$typeChambre = $_POST['type_chambre'] ?? '';
$message     = trim($_POST['message'] ?? '');

// Validation des champs obligatoires
if (!$nom || !$email || !$dateDebut || !$dateFin || !$nbPersonnes || !$typeChambre) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

// Vérification que le mail est valide
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide.']);
    exit;
}

// Vérification que les dates sont valides et cohérentes
if ($dateFin < $dateDebut) {
    echo json_encode(['success' => false, 'message' => 'La date de fin doit être postérieure ou égale à la date de début.']);
    exit;
}
if ($dateDebut < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'La date de début ne peut pas être dans le passé.']);
    exit;
}

// Vérification que le nombre de personnes est positif
if ($nbPersonnes < 1) {
    echo json_encode(['success' => false, 'message' => 'Le nombre de personnes doit être supérieur à 0.']);
    exit;
}

// Enregistrement de la réservation
$reservations = readJson("reservation.json") ?: [];

// Création d'un nouvel objet de réservation avec les données validées et un statut "en_attente"
$nouvelleReservation = [
    'id'            => generateId(),
    'nom'           => $nom,
    'email'         => $email,
    'date_debut'    => $dateDebut,
    'date_fin'      => $dateFin,
    'nb_personnes'  => $nbPersonnes,
    'type_chambre'  => $typeChambre,
    'message'       => $message,
    'statut'        => 'en_attente',
    'date_creation' => date('Y-m-d H:i:s')
];

$reservations[] = $nouvelleReservation;
// Écrire le tableau mis à jour dans le fichier JSON pour persister les données
writeJson("reservation.json", $reservations);

// Retourner une réponse indiquant le succès de l'opération
echo json_encode([
    'success' => true,
    'message' => 'Votre demande de réservation a été envoyée. L\'administrateur la validera prochainement.'
]);

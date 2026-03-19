<?php
// Commencer une session
session_start();
require_once "includes/json_data.php"; // Chargement du fichier avec les fct gestion json

// Formulaire back
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? '';
    $nbPersonnes = $_POST['nb_personnes'] ?? '';
    $typeChambre = $_POST['type_chambre'] ?? '';

    // Vérification des champs minimals 
    if (!$nom || !$email || !$dateDebut || !$dateFin || !$nbPersonnes || !$typeChambre) {
        echo json_encode([
            "success" => false,
            "error" => "Veuillez remplir tous les champs obligatoires."
        ]);
        exit;
    }

    // Lecture du fichier de réservations
    $reservations = readJson("reservation.json") ?: [];

    // Création d'un ID unique pour la réservation
    $id = generateId();

    // Préparer l'objet réservation
    $nouvelleReservation = [
        "id" => $id,
        "nom" => $nom,
        "email" => $email,
        "date_debut" => $dateDebut,
        "date_fin" => $dateFin,
        "nb_personnes" => $nbPersonnes,
        "type_chambre" => $typeChambre,
        "activites" => $activites,
        "statut" => "en_attente", // statut initial
        "date_creation" => date("Y-m-d H:i:s")
    ];

    // Ajouter la réservation
    $reservations[] = $nouvelleReservation;

    // Sauvegarder dans reservation.json
    writeJson("reservation.json", $reservations);

    echo json_encode([
        "success" => true,
        "message" => "Votre demande de réservation a été envoyée. L’administrateur la validera prochainement."
    ]);
    exit;
}
?>
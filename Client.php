<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    header("Location: connexion.html");
    exit;
}

echo "Bienvenue " . $_SESSION['nom'];

// Afficher les réservations du client
$reservationsJson = file_get_contents("reservations.json");
$reservations = json_decode($reservationsJson, true);

$userId = $_SESSION['id'];

?>


// Proposer des préstations avec possibilité de les ajouter à la réservation


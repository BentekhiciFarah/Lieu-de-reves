<?php
session_start();
// Inclure les fonctions de gestion des données JSON
require_once "includes/json_data.php";

// get_reservation.php - Fournit les réservations d'un client au format JSON
header('Content-Type: application/json');

// Vérification connexion
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    // Retourner une réponse JSON vide si l'utilisateur n'est pas connecté ou n'est pas un client
    echo json_encode([]);
    exit;
}

// Récupérer l'email de l'utilisateur connecté
$email = $_SESSION['email'];

// Charger les réservations
$reservations = readJson("reservation.json") ?: [];

// Filtrer les réservations pour ne retourner que celles de l'utilisateur connecté
$result = [];

// Afficher les réservations de l'utilisateur connecté
foreach ($reservations as $res) {
    if (($res['email'] ?? '') === $email) {
        $result[] = $res;
    }
}

// Retourner les réservations au format JSON
echo json_encode($result);
exit;
<?php
// Démarrer la session pour récupérer l'utilisateur connecté
session_start();

// Vérifier que l'utilisateur est un client
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    // Si ce n'est pas un client, renvoyer un JSON vide
    echo json_encode([]);
    exit;
}

// Inclure la fonction pour lire les fichiers JSON
require_once "includes/json_data.php";

// Lire le fichier JSON des prestations
$prestations = readJson("prestations.json") ?: [];

// Retourner les prestations sous forme JSON
header('Content-Type: application/json');
echo json_encode($prestations);
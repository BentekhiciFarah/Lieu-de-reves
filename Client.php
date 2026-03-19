<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "client") {
    header("Location: connexion.html");
    exit;
}

echo "Bienvenue " . $_SESSION['nom'];
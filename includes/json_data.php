<?php
    // Gestion de la base de données JSON

    // Chemin vers le fichier JSON passé en paramètre
    function jsonPath($fileName) {
        return __DIR__ . '/../data/' . $fileName;
    }

    // Retourner le contenu du fichier json passé en paramètre
    function readJson($fileName) {
        // Récupérer le chemin du fichier JSON
        $chemin = jsonPath($fileName);
        // Vérifier si le fichier existe
        if (file_exists($chemin)) {
            // Lire le contenu du fichier JSON
            $jsonContent = file_get_contents($chemin);
            // Convertir le contenu JSON en tableau associatif
            $data = json_decode($jsonContent, true);
        } else {
            // Si le fichier n'existe pas, retourner un tableau vide
            return [];
        }
    }
?>

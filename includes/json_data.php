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
            return $data ? $data : [];
        } else {
            // Si le fichier n'existe pas, retourner un tableau vide
            return [];
        }
    }
    // ecrire sur un fichier json
    function writeJson($fileName, $data) {
        // Chemin vers le fichier json
        $chemin = jsonPath($fileName);
        // chemin formaté 
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        
        file_put_contents($chemin, $jsonContent); 
    }

    // Ajouter un élément
    function addToJson($fileName, $newElement) {
        $data = readJson($fileName);

        $data[] = $newElement;

        writeJson($fileName, $data); 
    }

    // Générer un id unique 
    function generateId () {
        return time() . rand(1000, 9999);
    }

    // Mettre à jour un élément à partir de son id
    function updateById($fileName, $id, $newData) {
        $data = readJson($fileName);

        foreach ($data as $index => $element) {
            if($element['id'] == $id) {
                $data[$index] = array_merge($element, $newData);
            }
        }

        writeJson($fileName, $data);
    }

    // Supprimer un élément à partir de son id
    function deleteById($fileName, $id) {
        $data = readJson($fileName);

        $data = array_filter($data, function($element) use ($id) {
            return $element['id'] != $id;
        });

        // Réindexer le tableau
        $data = array_values($data);

        writeJson($fileName, $data);
    }

    // Trouver toutes les données d'un client 
    function findAllBy($fileName, $key, $value) {
        $data = readJson($fileName);
        $results = [];

        foreach ($data as $element) {
            if (isset($element[$key]) && $element[$key] == $value) {
                $results[] = $element;
            }
        }

        return $results;
    }
?>

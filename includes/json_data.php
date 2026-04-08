<?php
    // Gestion de la base de données JSON

    // Chemin vers le fichier JSON passé en paramètre
    function jsonPath($fileName) {
        return __DIR__ . '/../data/' . $fileName;
    }

    // Retourner le contenu du fichier json passé en paramètre
    function readJson($fileName) {
        $chemin = jsonPath($fileName);
        if (!file_exists($chemin)) return [];

        $f = fopen($chemin, 'r');
        // Verrou partagé : plusieurs lectures simultanées autorisées
        if (!flock($f, LOCK_SH)) {
            fclose($f);
            return [];
        }
        $taille = filesize($chemin);
        $jsonContent = $taille > 0 ? fread($f, $taille) : '[]';
        flock($f, LOCK_UN);
        fclose($f);

        $data = json_decode($jsonContent, true);
        return $data ? $data : [];
    }

    // Ecrire sur un fichier json avec verrou exclusif
    function writeJson($fileName, $data) {
        $chemin = jsonPath($fileName);
        // c+ : lecture/écriture, crée le fichier s'il n'existe pas
        $f = fopen($chemin, 'c+');
        // Verrou exclusif : aucune autre lecture/écriture simultanée
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            http_response_code(409); // Conflict
            return;
        }
        // Convertir les données en JSON formaté
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        // Vider le fichier avant d'écrire les nouvelles données
        ftruncate($f, 0);
        // Revenir au début du fichier pour écrire
        fseek($f, 0);
        // Écrire le contenu JSON dans le fichier
        fwrite($f, $jsonContent);
        // Libérer le verrou et fermer le fichier
        flock($f, LOCK_UN);
        // fermer le fichier après l'écriture
        fclose($f);
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

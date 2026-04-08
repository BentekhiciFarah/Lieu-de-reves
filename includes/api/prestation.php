<?php
/*
 * prestation.php :  fichier de gestion des prestations
 *
 * Ce fichier gère deux actions distinctes avec switch
 *
 *   - liste   : retourne le catalogue de toutes les prestations disponibles pour affichage
 *               côté client dans la page client.php
 *
 *   - ajouter : enregistre le choix d'une prestation par un client pour sa réservation en cours
 *
 * Ce fichier est appelé exclusivement en AJAX depuis client.php.
*/
session_start();
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    /*
     * ACTION : liste
     * Méthode HTTP attendue : GET
     * Rôle requis           : client
     *
     * Retourne l'intégralité du catalogue des prestations disponibles dans l'hôtel.
     * C'est cet appel qui alimente dynamiquement la section "Prestations disponibles"
     * de la page client.php au chargement de la page, via $.ajax.
     */
    case 'liste':

        // vérification du rôle de l'utilisateur : seul un client peut accéder à la liste des prestations
         if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode([]);
            exit;
        }

        // Lecture du catalogue des prestations depuis le fichier JSON et retour en JSON
        $prestations = readJson("prestations.json") ?: [];
        // Retourner la liste des prestations au format JSON pour affichage côté client
        echo json_encode($prestations);
        break;

     /*
     * ACTION : ajouter
     * Méthode HTTP attendue : POST
     * Rôle requis           : client
     *
     * Enregistre le choix d'une prestation par le client pour sa réservation
     * en cours. Cette action effectue cinq vérifications dans l'ordre avant
     * d'écrire quoi que ce soit :
     *
     *   1. Le client est bien connecté
     *   2. L'identifiant de la prestation est présent dans la requête
     *   3. Le client possède au moins une réservation avec le statut "validée"
     *   4. La prestation demandée existe bien dans le catalogue
     *   5. Le client n'a pas déjà choisi cette même prestation pour cette réservation
     *
     * Si toutes ces vérifications passent, la prestation est ajoutée au fichier
     * prestations_client.json avec le statut "validée" par défaut.
     */
    case 'ajouter':
        // Vérification du rôle de l'utilisateur : seul un client peut ajouter une prestation
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        // Vérification de la présence de l'identifiant de la prestation dans la requête POST
        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de prestation manquant']);
            exit;
        }

        // Récupération de l'identifiant de la prestation et de l'email du client depuis la session
        $prestationId     = $_POST['id'];
        $email            = $_SESSION['email'] ?? '';

        $prestations        = readJson("prestations.json") ?: [];
        $prestationsClient  = readJson("prestations_client.json") ?: [];
        $reservations       = readJson("reservation.json") ?: [];

        // Vérification que le client possède au moins une réservation avec le statut "validée"
        $reservationValide = null;
        foreach ($reservations as $res) {
            if (($res['email'] ?? '') === $email && $res['statut'] === 'validée') {
                $reservationValide = $res;
            }
        }

        // Si aucune réservation validée n'est trouvée pour ce client, retourner une erreur
        if (!$reservationValide) {
            echo json_encode(['success' => false, 'message' => 'Aucune réservation validée trouvée']);
            exit;
        }

        // Vérification que la prestation demandée existe bien dans le catalogue des prestations
        $prestation = null;
        foreach ($prestations as $p) {
            if ($p['id'] == $prestationId) {
                $prestation = $p;
                break;
            }
        }

        // Si la prestation n'est pas trouvée dans le catalogue, retourner une erreur
        if (!$prestation) {
            echo json_encode(['success' => false, 'message' => 'Prestation introuvable']);
            exit;
        }

        // Vérifier si déjà ajoutée pour cette réservation (pas + de 1)
        foreach ($prestationsClient as $p) {
            if ($p['user_email'] === $email && $p['reservation_id'] === $reservationValide['id'] && $p['prestation']['id'] == $prestationId) {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà choisi cette prestation']);
                exit;
            }
        }

        // Toutes les vérifications sont passées, on peut ajouter la prestation pour cette réservation
        $nouvellePrestation = [
            'user_email'     => $email,
            'reservation_id' => $reservationValide['id'],
            'prestation'     => $prestation,
            'statut'         => 'validée',
            'adresse'        => '',
            'heure'          => ''
        ];

        // Ajouter la nouvelle prestation au tableau des prestations client et écrire dans le fichier JSON
        $prestationsClient[] = $nouvellePrestation;
        // Écrire le tableau mis à jour dans le fichier JSON pour persister les données
        writeJson("prestations_client.json", $prestationsClient);

        // Retourner une réponse indiquant le succès de l'opération et l'ID de la réservation associée
        echo json_encode([
            'success'        => true,
            'message'        => 'Prestation ajoutée avec succès, en attente de validation',
            'reservation_id' => $reservationValide['id']
        ]);
        break;
    // Action inconnue : retourner une erreur 400 Bad Request
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}

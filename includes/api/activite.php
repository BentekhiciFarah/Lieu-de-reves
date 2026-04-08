<?php
session_start();
// import des fonctions de lecture/écriture JSON et de génération d'ID
require_once __DIR__ . '/../json_data.php';

header('Content-Type: application/json');

/* Ce fichier gère les différentes actions liées aux activités
// Les actions possibles sont déterminées par le paramètre "action" dans la requête
 * Les quatre actions disponibles sont :
 *   - par_date  : réservé à l'admin, retourne les demandes en attente pour une date donnée
 *   - client    : réservé au client, retourne ses activités planifiées pour une réservation
 *   - demande   : réservé au client, soumet une nouvelle demande d'activité
 *   - message   : réservé au client, ajoute un message sur une activité planifiée
 *   - planifier : réservé à l'admin, transforme des demandes en activité planifiée
 */

/*
 * Récupération de l'action demandée.
 * On utilise $_REQUEST plutôt que $_GET ou $_POST pour accepter les deux méthodes HTTP
 * par défaut si le paramètre est absent, ce qui déclenchera le cas "default" du switch.
 */
$action = $_REQUEST['action'] ?? '';

// En fonction de l'action demandée, on exécute le code correspondant
switch ($action) {

    /*
     * ACTION : par_date
     * Méthode HTTP : GET
     * Rôle requis   : admin
     *
     * Retourne toutes les demandes d'activités d'une réservation qui sont en attente
     * pour une date donnée.
     * afficher les demandes à traiter jour par jour.
     */
    case 'par_date':
        // Vérification de l'authentification et du rôle
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([]);
            exit;
        }

        // Récupération et validation du paramètre 
        $date = $_GET['date'] ?? '';

        /*
         * Validation du format de la date avec une expression régulière.
         * Le format attendu est AAAA-MM-JJ (ex : 2025-07-14).
         * Si la date est absente ou mal formée, on retourne un tableau vide
         * plutôt qu'une erreur, pour ne pas casser l'interface JavaScript.
         */
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode([]);
            exit;
        }

        // Lecture des demandes d'activités et des réservations depuis les fichiers JSON
        $demandes     = readJson("activity_requests.json") ?: [];
        $reservations = readJson("reservation.json") ?: [];

        /*
         * On construit une "map" (tableau associatif) des réservations indexées par leur id.
         * Cela évite de faire une double boucle imbriquée plus bas :
         * au lieu de parcourir toutes les réservations pour chaque demande,
         * on accède directement à $resMap[$dem['reservation_id']] en temps constant.
         */
        $resMap = [];
        foreach ($reservations as $res) {
            $resMap[$res['id']] = $res;
        }

        /*
        * On ne traite que les demandes encore en attente.
        * Les demandes déjà planifiées ou refusées sont ignorées.
         */
        $resultat = [];
        foreach ($demandes as $dem) {
            // Si la demande n'est pas en attente, on passe à la suivante
            if (($dem['statut'] ?? '') !== 'en_attente') continue;

            // On récupère la réservation associée à la demande
            // Si aucune réservation n'est trouvée (ce qui ne devrait pas arriver), on ignore la demande
            $res = $resMap[$dem['reservation_id']] ?? null;
            if (!$res) continue;

            $dateDebut = $res['date_debut'] ?? '';
            $dateFin   = $res['date_fin'] ?? '';

            // On vérifie que la date demandée est bien dans l'intervalle de la réservation
            if ($date >= $dateDebut && $date < $dateFin) {
                /*
                 * array_merge fusionne les données de la demande avec des informations
                 * supplémentaires tirées de la réservation, pour éviter à l'interface
                 * admin de faire un appel supplémentaire pour les obtenir.
                 */
                $resultat[] = array_merge($dem, [
                    'reservation_date_debut'   => $dateDebut,
                    'reservation_date_fin'     => $dateFin,
                    'reservation_nb_personnes' => (int)($res['nb_personnes'] ?? 0)
                ]);
            }
        }
        // On retourne le résultat au format JSON
        echo json_encode($resultat);
        break;

    /*
     * ACTION : client
     * Méthode HTTP : GET
     * Rôle requis   : client
     *
     * Retourne la liste des activités planifiées auxquelles le client connecté
     * participe, pour une réservation donnée. Utilisé par la page client pour
     * afficher le bloc "Activités planifiées" sous chaque réservation.
     */
    case 'client':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            http_response_code(403);
            echo json_encode([]);
            exit;
        }

        $reservationId = $_GET['reservation_id'] ?? '';
        $email         = $_SESSION['email'] ?? '';

        if (!$reservationId) {
            echo json_encode([]);
            exit;
        }

        $activitesPlanifiees = readJson("planned_activities.json") ?: [];
        $resultat = [];

        // On parcourt toutes les activités planifiées pour trouver celles où le client est participant
        foreach ($activitesPlanifiees as $pa) {
             /*
             * Chaque activité planifiée contient un tableau "participants".
             * On parcourt ce tableau pour vérifier si le client connecté
             * (identifié par son email de session et l'id de sa réservation)
             * figure parmi les participants.
             *
             * Dès qu'on trouve une correspondance, on ajoute l'activité au résultat
             * et on sort de la boucle interne avec "break" pour ne pas l'ajouter
             * plusieurs fois si le client apparaît en doublon dans les participants.
             */
            foreach (($pa['participants'] ?? []) as $participant) {
                if ($participant['reservation_id'] == $reservationId && ($participant['user_email'] ?? '') === $email) {
                    $resultat[] = $pa;
                    break;
                }
            }
        }

        echo json_encode($resultat);
        break;

    // Backend pour gérer une demande d'activité de la part d'un client
    case 'demande':
        // Vérification de l'authentification et du rôle
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            // Accès refusé pour les non-clients
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        // Vérification de la méthode HTTP : on n'accepte que les POST pour créer une demande
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Méthode non autorisée
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        // Récupération et validation des données envoyées par le client
        $reservationId = $_POST['reservation_id'] ?? '';
        $activityId    = (int)($_POST['activity_id'] ?? 0);
        $creneau       = $_POST['creneau'] ?? '';
        $nbPersonnes   = (int)($_POST['nb_personnes'] ?? 1);
        $message       = trim($_POST['message'] ?? '');
        $email         = $_SESSION['email'] ?? '';

        // Validation des données 
        if (!in_array($creneau, ['heure', 'demi-journee', 'journee'])) {
            echo json_encode(['success' => false, 'message' => 'Créneau invalide']);
            exit;
        }

        // Vérifier que la réservation existe, appartient à l'utilisateur et est validée
        $reservations = readJson("reservation.json") ?: [];
        $reservation  = null;
        // On cherche la bonne réservation
        foreach ($reservations as $res) {
            if ($res['id'] == $reservationId && ($res['email'] ?? '') === $email && ($res['statut'] ?? '') === 'validée') {
                $reservation = $res;
                break;
            }
        }

        // Si aucune réservation valide n'est trouvée, on retourne une erreur
        if (!$reservation) {
            echo json_encode(['success' => false, 'message' => 'Réservation introuvable ou non validée']);
            exit;
        }

        // Utiliser la date souhaitée si fournie, sinon utiliser la date de début de la réservation
        $date = $_POST['date_souhaitee'] ?? $reservation['date_debut'];

        // Valider que la date est dans l'intervalle de la réservation
        if ($date < $reservation['date_debut'] || $date > $reservation['date_fin']) {
            echo json_encode(['success' => false, 'message' => 'Date hors de la période de réservation.']);
            exit;
        }

        // Valider que le nombre de personnes demandé est cohérent avec la réservation
        $maxPersonnes = (int)($reservation['nb_personnes'] ?? 1);
        if ($nbPersonnes < 1 || $nbPersonnes > $maxPersonnes) {
            echo json_encode(['success' => false, 'message' => "Nombre de personnes invalide (max : {$maxPersonnes})"]);
            exit;
        }

        // Vérifier que l'activité existe dans la base de données
        $activites = readJson("activities.json") ?: [];
        $activite  = null;
        foreach ($activites as $act) {
            if ($act['id'] == $activityId) {
                $activite = $act;
                break;
            }
        }

        // Si l'activité n'est pas trouvée, on retourne une erreur
        if (!$activite) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable']);
            exit;
        }

        // Créer une nouvelle demande d'activité et l'enregistrer dans le fichier JSON
        $demandes = readJson("activity_requests.json") ?: [];

        $nouvelleDemande = [
            'id'             => generateId(),
            'reservation_id' => $reservation['id'],
            'user_email'     => $email,
            'user_nom'       => $reservation['nom'] ?? '',
            'activity_id'    => $activityId,
            'activity_nom'   => $activite['nom'],
            'creneau'        => $creneau,
            'date_souhaitee' => $date,
            'nb_personnes'   => $nbPersonnes,
            'message'        => $message,
            'statut'         => 'en_attente',
            'date_creation'  => date('Y-m-d H:i:s')
        ];

        // On ajoute la nouvelle demande au tableau des demandes et on l'enregistre
        $demandes[] = $nouvelleDemande;
        writeJson("activity_requests.json", $demandes);

        // On retourne une réponse de succès avec l'ID de la nouvelle demande
        echo json_encode([
            'success'    => true,
            'message'    => 'Demande envoyée avec succès',
            'request_id' => $nouvelleDemande['id']
        ]);
        break;

    /*
     * ACTION : message
     * Méthode HTTP : POST
     * Rôle requis   : client
     *
     * Permet à un client participant à une activité planifiée d'ajouter ou de modifier
     * son message personnel visible par les autres participants et l'animateur.
     */
    case 'message':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        // Vérification de la méthode HTTP : on n'accepte que les POST pour modifier un message
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        // Récupération des données envoyées par le client
        $plannedId = $_POST['planned_id'] ?? '';
        $message   = trim($_POST['message'] ?? '');
        $email     = $_SESSION['email'] ?? '';

        // Validation de l'ID de l'activité planifiée
        if (!$plannedId) {
            echo json_encode(['success' => false, 'message' => 'ID de l\'activité manquant']);
            exit;
        }

        // On charge les activités planifiées pour trouver celle à modifier
        $activitesPlanifiees = readJson("planned_activities.json") ?: [];

        // On utilise une variable pour suivre si on a trouvé et mis à jour le message du participant
        $mis_a_jour = false;

        // On parcourt les activités planifiées pour trouver celle qui correspond à l'ID fourni
        foreach ($activitesPlanifiees as &$pa) {
            // Si on trouve l'activité planifiée correspondante, on cherche le participant dans la liste
            if ((string)$pa['id'] === (string)$plannedId) {
                // On parcourt les participants de cette activité pour trouver celui qui correspond à l'email du client connecté
                foreach ($pa['participants'] as &$participant) {
                    if (($participant['user_email'] ?? '') === $email) {
                        // Si on trouve le participant, on met à jour son message et on marque que la mise à jour a été faite
                        $participant['message_participant'] = $message;
                        $mis_a_jour = true;
                        break;
                    }
                }
                // unset pour libérer la référence à $participant, même si on sort déjà de la boucle avec break
                unset($participant);
                break;
            }
        }
        // unset pour libérer la référence à $pa et éviter les effets de bord
        unset($pa);

        // Si aucune activité planifiée n'a été trouvée avec ce participant, on retourne une erreur
        if (!$mis_a_jour) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable ou vous n\'êtes pas participant']);
            exit;
        }

        // Si on a mis à jour le message, on enregistre les modifications dans le fichier JSON
        writeJson("planned_activities.json", $activitesPlanifiees);
        // On retourne une réponse de succès
        echo json_encode(['success' => true, 'message' => 'Message enregistré']);
        break;

    /*
     * ACTION : planifier
     * Méthode HTTP : POST
     * Rôle requis   : admin
     *
     * Permet à un admin de transformer une ou plusieurs demandes d'activités en attente
     * en une activité planifiée concrète, avec une date, une heure et un animateur.
     *
     * Cette action réalise trois choses à la fois :
     *   1. Vérifie que le nombre total de participants respecte les limites de l'activité
     *   2. Crée une nouvelle entrée dans planned_activities.json
     *   3. Met à jour le statut des demandes concernées à "planifiee" dans activity_requests.json
     */
    case 'planifier':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        // Vérification de la méthode HTTP : on n'accepte que les POST pour créer une planification
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        // Récupération et validation des données envoyées par l'admin
        $activityId = (int)($_POST['activity_id'] ?? 0);
        $date       = $_POST['date'] ?? '';
        $heure      = $_POST['heure'] ?? '';
        $animateur  = trim($_POST['animateur'] ?? '');
        $creneau    = $_POST['creneau'] ?? 'heure';
        $requestIds = $_POST['request_ids'] ?? [];

        // Vérifier que la date est valide (format YYYY-MM-DD)
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Date invalide.']);
            exit;
        }

        // Validation des données : tous les champs sont obligatoires, et il doit y avoir au moins une demande sélectionnée
        if (!$activityId || !$date || !$animateur || empty($requestIds)) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes (activité, date, animateur, demandes)']);
            exit;
        }

        // Validation du créneau
        if (!in_array($creneau, ['heure', 'demi-journee', 'journee'])) {
            echo json_encode(['success' => false, 'message' => 'Créneau invalide']);
            exit;
        }

        // Vérifier que l'activité existe dans la base de données
        $activites = readJson("activities.json") ?: [];
        $activite  = null;
        // On cherche l'activité correspondante à l'ID fourni
        foreach ($activites as $act) {
            if ($act['id'] == $activityId) {
                $activite = $act;
                break;
            }
        }   
        
        // Si l'activité n'est pas trouvée, on retourne une erreur
        if (!$activite) {
            echo json_encode(['success' => false, 'message' => 'Activité introuvable']);
            exit;
        }

        // On charge toutes les demandes d'activités pour trouver celles qui correspondent aux IDs sélectionnés
        $toutesLesDemandes  = readJson("activity_requests.json") ?: [];
        $demandesSelectionnees = [];
        foreach ($toutesLesDemandes as $dem) {
            if (in_array((string)$dem['id'], array_map('strval', $requestIds))) {
                $demandesSelectionnees[] = $dem;
            }
        }

        // Si aucune demande sélectionnée n'est trouvée, on retourne une erreur
        if (empty($demandesSelectionnees)) {
            echo json_encode(['success' => false, 'message' => 'Aucune demande sélectionnée']);
            exit;
        }

        // Calculer le nombre total de personnes concernées par les demandes sélectionnées
        $totalPersonnes = 0;
        foreach ($demandesSelectionnees as $dem) {
            // On additionne le nombre de personnes de chaque demande, en prenant en compte la valeur par défaut de 1 si le champ est absent
            $totalPersonnes += (int)($dem['nb_personnes'] ?? 1);
        }

        $minParticipants = (int)($activite['min_participants'] ?? 1);
        // Vérifier que le nombre total de personnes respecte les limites de l'activité
        if ($totalPersonnes < $minParticipants) {
            echo json_encode([
                'success' => false,
                'message' => "Participants insuffisants : minimum {$minParticipants} requis pour cette activité (sélectionnés : {$totalPersonnes})"
            ]);
            exit;
        }

        // Vérifier que le nombre total de personnes ne dépasse pas le maximum autorisé pour l'activité
        $maxParticipants = (int)($activite['max_participants'] ?? PHP_INT_MAX);
        if ($totalPersonnes > $maxParticipants) {
            echo json_encode([
                'success' => false,
                'message' => "Trop de participants : maximum {$maxParticipants} pour cette activité (sélectionnés : {$totalPersonnes})"
            ]);
            exit;
        }

        // Si toutes les validations sont passées, on peut créer la nouvelle activité planifiée
        $participants = [];
        foreach ($demandesSelectionnees as $dem) {
            $participants[] = [
                'reservation_id'      => $dem['reservation_id'],
                'user_email'          => $dem['user_email'],
                'user_nom'            => $dem['user_nom'],
                'nb_personnes'        => (int)($dem['nb_personnes'] ?? 1),
                'message_participant' => ''
            ];
        }

        // On charge les activités planifiées existantes pour y ajouter la nouvelle
        $activitesPlanifiees = readJson("planned_activities.json") ?: [];

        // Création de la nouvelle activité planifiée avec les données fournies et les informations tirées des demandes sélectionnées
        $nouvellePlanification = [
            'id'            => generateId(),
            'activity_id'   => $activityId,
            'activity_nom'  => $activite['nom'],
            'prix_par_pers' => (float)($activite['prix'] ?? 0),
            'date'          => $date,
            'heure'         => $heure,
            'creneau'       => $creneau,
            'animateur'     => $animateur,
            'request_ids'   => $requestIds,
            'participants'  => $participants,
            'date_creation' => date('Y-m-d H:i:s')
        ];

        // On ajoute la nouvelle planification au tableau des activités planifiées et on l'enregistre
        $activitesPlanifiees[] = $nouvellePlanification;
        writeJson("planned_activities.json", $activitesPlanifiees);

        // Après avoir créé l'activité planifiée, on met à jour le statut des demandes concernées à "planifiee"
        foreach ($toutesLesDemandes as &$dem) {
            if (in_array((string)$dem['id'], array_map('strval', $requestIds))) {
                $dem['statut'] = 'planifiee';
            }
        }
        unset($dem);
        // On enregistre les modifications des demandes dans le fichier JSON
        writeJson("activity_requests.json", $toutesLesDemandes);

        // On retourne une réponse de succès avec un message récapitulatif de la planification créée
        $dateFormate = (new DateTime($date))->format('d/m/Y');
        echo json_encode([
            'success' => true,
            'message' => "Activité « {$activite['nom']} » planifiée le {$dateFormate} à {$heure} avec {$animateur}"
        ]);
        break;
    // Si l'action demandée ne correspond à aucun des cas précédents, on retourne une erreur 400 Bad Request avec un message d'erreur
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}

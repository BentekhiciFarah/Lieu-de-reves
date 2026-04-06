<?php
session_start();
require_once "includes/json_data.php";

header('Content-Type: application/json');

// Seul un client connecté peut ajouter un message
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$plannedId = $_POST['planned_id'] ?? '';
$message   = trim($_POST['message'] ?? '');
$email     = $_SESSION['email'] ?? '';

if (!$plannedId) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'activité manquant']);
    exit;
}

$plannedActivities = readJson("planned_activities.json") ?: [];
$updated = false;

foreach ($plannedActivities as &$pa) {
    if ((string)$pa['id'] === (string)$plannedId) {
        foreach ($pa['participants'] as &$participant) {
            if (($participant['user_email'] ?? '') === $email) {
                $participant['message_participant'] = $message;
                $updated = true;
                break;
            }
        }
        unset($participant);
        break;
    }
}
unset($pa);

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Activité introuvable ou vous n\'êtes pas participant']);
    exit;
}

writeJson("planned_activities.json", $plannedActivities);
echo json_encode(['success' => true, 'message' => 'Message enregistré']);

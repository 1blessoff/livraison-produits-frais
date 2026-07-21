<?php
// ajax/temoignage_handler.php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? 'Anonyme');
    $prenom = trim($_POST['prenom'] ?? 'Client');
    $message = trim($_POST['message'] ?? '');
    $note = (int)($_POST['note'] ?? 5);
    $notifier = isset($_POST['notifier']) && $_POST['notifier'] == '1' ? 1 : 0;
    $rappeler_le = isset($_POST['rappeler_le']) && !empty($_POST['rappeler_le']) ? $_POST['rappeler_le'] : null;
    
    // Validation
    if (empty($message) || strlen($message) < 5) {
        echo json_encode(['success' => false, 'message' => 'Le message doit contenir au moins 5 caractères.']);
        exit();
    }
    
    if ($note < 1 || $note > 5) {
        echo json_encode(['success' => false, 'message' => 'Note invalide.']);
        exit();
    }
    
    if ($user_id == 0) {
        echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
        exit();
    }
    
    try {
        // Vérifier si l'utilisateur a déjà témoigné (sauf si c'est un rappel)
        $check = $pdo->prepare("SELECT id, rappeler_le FROM temoignages WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $check->execute([$user_id]);
        $existing = $check->fetch();
        
        // Si l'utilisateur a déjà témoigné et que c'est un rappel, on met à jour
        if ($existing && $notifier == 0) {
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà donné votre avis. Merci !']);
            exit();
        }
        
        // Si c'est un rappel, mettre à jour le témoignage existant
        if ($existing && $notifier == 1) {
            $stmt = $pdo->prepare("
                UPDATE temoignages 
                SET message = ?, note = ?, visible = 0, rappeler_le = ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$message, $note, $rappeler_le, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Votre avis a été mis à jour !']);
            exit();
        }
        
        // Sinon, insérer un nouveau témoignage
        $stmt = $pdo->prepare("
            INSERT INTO temoignages (user_id, nom, prenom, message, note, visible, rappeler_le) 
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$user_id, $nom, $prenom, $message, $note, $rappeler_le]);
        
        echo json_encode(['success' => true, 'message' => 'Témoignage envoyé avec succès !']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
exit();
?>
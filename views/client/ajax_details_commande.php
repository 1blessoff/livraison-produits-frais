<?php
// views/client/ajax_details_commande.php
session_start();

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour ne pas casser le JSON
error_reporting(0);
ini_set('display_errors', 0);

// Vérification de la connexion
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Veuillez vous reconnecter.']);
    exit();
}

// Vérification de l'ID - CORRECTION ICI
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de commande manquant ou invalide. ID reçu: ' . ($_GET['id'] ?? 'rien')]);
    exit();
}

$commande_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../../config/database.php';

try {
    // 1. Récupération de la commande
    $stmtCmd = $pdo->prepare("SELECT * FROM commandes WHERE id = ? AND user_id = ?");
    $stmtCmd->execute([$commande_id, $user_id]);
    $commande = $stmtCmd->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        echo json_encode(['success' => false, 'message' => 'Commande introuvable pour cet utilisateur. ID: ' . $commande_id]);
        exit();
    }

    // 2. Récupération des articles de la commande
    $stmtItems = $pdo->prepare("
        SELECT ci.*, p.nom, p.image 
        FROM commande_items ci
        LEFT JOIN produits p ON ci.produit_id = p.id
        WHERE ci.commande_id = ?
    ");
    $stmtItems->execute([$commande_id]);
    $articles = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupération de l'adresse
    $adresse_text = "Adresse non renseignée";
    if (!empty($commande['adresse_id'])) {
        $stmtAdresse = $pdo->prepare("SELECT adresse, ville FROM adresses WHERE id = ?");
        $stmtAdresse->execute([$commande['adresse_id']]);
        $adresse = $stmtAdresse->fetch(PDO::FETCH_ASSOC);
        if ($adresse) {
            $adresse_text = htmlspecialchars($adresse['adresse'] . ', ' . $adresse['ville']);
        }
    }

    // 4. Formatage des articles
    $articles_data = [];
    foreach ($articles as $article) {
        // Gestion de l'image
        $imgUrl = '../../uploads/default_product.jpg';
        if (!empty($article['image']) && file_exists(__DIR__ . '/../../uploads/' . $article['image'])) {
            $imgUrl = '../../uploads/' . $article['image'];
        }
        
        $articles_data[] = [
            'nom' => htmlspecialchars($article['nom'] ?? 'Produit inconnu'),
            'quantite' => intval($article['quantite']),
            'prix' => number_format(floatval($article['prix']), 0, ',', ' '),
            'total' => number_format(floatval($article['prix']) * intval($article['quantite']), 0, ',', ' '),
            'image' => $imgUrl
        ];
    }

    // 5. Envoi de la réponse
    echo json_encode([
        'success' => true,
        'date' => isset($commande['date_commande']) ? date('d/m/Y à H:i', strtotime($commande['date_commande'])) : 'Date inconnue',
        'statut' => htmlspecialchars($commande['statut'] ?? 'En attente'),
        'total' => number_format(floatval($commande['total']), 0, ',', ' '),
        'adresse' => $adresse_text,
        'articles' => $articles_data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur technique: ' . $e->getMessage()
    ]);
}
?>
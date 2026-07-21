<?php
// views/public/deconnexion.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // 1. On nettoie l'ancienne sauvegarde du panier en BDD pour ce client
    $stmtDelete = $pdo->prepare("DELETE FROM paniers_sauvegardes WHERE user_id = ?");
    $stmtDelete->execute([$user_id]);

    // 2. Si le panier de session n'est pas vide, on le sauvegarde en BDD
    if (!empty($_SESSION['panier'])) {
        $stmtInsert = $pdo->prepare("INSERT INTO paniers_sauvegardes (user_id, categorie_id, quantite) VALUES (?, ?, ?)");
        foreach ($_SESSION['panier'] as $product_id => $item) {
            $qte = $item['quantite'] ?? $item['qte'] ?? 1;
            $stmtInsert->execute([$user_id, $product_id, $qte]);
        }
    }
}

// 3. Destruction classique de la session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. ✅ Redirection vers l'accueil (corrigée)
header("Location: ../../index.php");
exit();
?>
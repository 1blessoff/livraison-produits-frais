<?php
// views/public/ajouter_panier.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// On vérifie qu'on a bien reçu un ID de produit valide dans l'URL
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);

    try {
        // 1. On va chercher le VRAI produit en BDD (celui que l'admin vient de créer)
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND actif = 1");
        $stmt->execute([$product_id]);
        $produit = $stmt->fetch();

        // Si le produit existe et qu'il y a du stock
        if ($produit && $produit['stock'] > 0) {
            
            // 2. Si le panier n'existe pas encore dans la session, on le crée vide
            if (!isset($_SESSION['panier'])) {
                $_SESSION['panier'] = [];
            }

            // 3. Si le produit est DEJA dans le panier, on augmente juste sa quantité
            if (isset($_SESSION['panier'][$product_id])) {
                // Optionnel : vérifier si on ne dépasse pas le stock disponible
                if ($_SESSION['panier'][$product_id]['quantite'] < $produit['stock']) {
                    $_SESSION['panier'][$product_id]['quantite']++;
                }
            } else {
                // 4. Sinon, c'est la première fois qu'on l'ajoute, on charge ses données
                $_SESSION['panier'][$product_id] = [
                    'id'          => $produit['id'],
                    'nom'         => $produit['nom'],
                    'description' => $produit['description'],
                    'prix'        => $produit['prix'],
                    'quantite'    => 1,
                    'image'       => $produit['image'] // Garde le nom de l'image
                ];
            }
        }
    } catch (\PDOException $e) {
        // En cas d'erreur BDD, on log l'erreur pour le debug
        error_log("Erreur ajout panier : " . $e->getMessage());
    }
}

// 5. On redirige immédiatement vers la page du panier pour voir le résultat
header('Location: panier.php');
exit();
<?php
// views/public/panier.php
session_start();
require_once __DIR__ . '/../../config/database.php';

$message = "";


//  1. TRAITEMENT DES ACTIONS DU PANIER (Mise à jour / Suppression)

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($product_id > 0 && isset($_SESSION['panier'][$product_id])) {
        
        // Action : Augmenter la quantité (+)
        if ($action === 'increase') {
            try {
                // On vérifie le stock réel en BDD avant d'ajouter
                $stmt = $pdo->prepare("SELECT stock FROM produits WHERE id = ?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch();
                
                if ($prod && $_SESSION['panier'][$product_id]['quantite'] < $prod['stock']) {
                    $_SESSION['panier'][$product_id]['quantite']++;
                } else {
                    $message = "Désolé, le stock maximal disponible pour cet article est atteint.";
                }
            } catch (\PDOException $e) {
                // Erreur BDD silencieuse
            }
        }
        
        // Action : Diminuer la quantité (-)
        if ($action === 'decrease') {
            if ($_SESSION['panier'][$product_id]['quantite'] > 1) {
                $_SESSION['panier'][$product_id]['quantite']--;
            } else {
                // Si la quantité tombe à 0, on retire carrément le produit
                unset($_SESSION['panier'][$product_id]);
            }
        }
        
        // Action : Supprimer l'article de la ligne (Poubelle)
        if ($action === 'remove') {
            unset($_SESSION['panier'][$product_id]);
            $message = "L'article a été retiré de votre panier.";
        }
        
        // On recharge la page proprement pour appliquer les calculs sans re-soumettre l'URL
        header('Location: panier.php');
        exit();
    }
}

// =========================================================================
// 2. CALCULS DES TOTAUX
// =========================================================================
$total_general = 0;
$cart_count = 0;

if (!empty($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $total_general += $item['prix'] * $item['quantite'];
        $cart_count += $item['quantite'];
    }
}


// =========================================================================
// FRAIS DE LIVRAISON
// =========================================================================
$frais_livraison = 0;
$zone_livraison = $_SESSION['zone_livraison'] ?? 'pointe_noire';

// Définir les frais selon la zone
$frais_par_zone = [
    'pointe_noire' => 500,   // 500 FCFA
    'brazzaville' => 1000,    // 1000 FCFA
    'autres' => 2000           // 2000 FCFA
];

// Frais gratuits si commande > 35000 FCFA
if ($total_general >= 35000) {
    $frais_livraison = 0;
    $message_livraison = "Livraison offerte !";
} else {
    $frais_livraison = $frais_par_zone[$zone_livraison] ?? 2000;
    $message_livraison = number_format($frais_livraison, 0, ',', ' ') . " FCFA";
}

$total_avec_livraison = $total_general + $frais_livraison;


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Votre Panier | EMMA+</title>
    
<!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #ffffff; 
            color: #111111;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
        }
        
        /* Navbar minimaliste - Responsive */
        .navbar-brand { 
            font-weight: 900; 
            font-size: 26px; 
            letter-spacing: 3px; 
            color: #111111 !important; 
            text-decoration: none; 
        }
        
        .nav-link-custom { 
            color: #111111; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 13px; 
            letter-spacing: 1px; 
            text-decoration: none; 
        }
        
        .nav-link-custom:hover { 
            text-decoration: underline; 
        }

        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 20px;
                letter-spacing: 2px;
            }
            
            .nav-link-custom {
                font-size: 11px;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 18px;
                letter-spacing: 1.5px;
            }
        }

        .page-title { 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-size: 24px; 
            border-bottom: 2px solid #111; 
            padding-bottom: 10px; 
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 20px;
                letter-spacing: 1.5px;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 18px;
                letter-spacing: 1px;
            }
        }
        
        /* Table / Recap Style ASOS - Version Responsive */
        .cart-table th { 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 1px; 
            font-weight: 700; 
            color: #666; 
            padding: 15px 0; 
            border-bottom: 1px solid #111; 
        }
        
        .cart-table td { 
            padding: 20px 0; 
            vertical-align: middle; 
            border-bottom: 1px solid #f0f0f0; 
        }
        
        .product-img-cart { 
            width: 70px; 
            height: 85px; 
            object-fit: cover; 
            border: 1px solid #eee; 
        }
        
        /* Version mobile de la table - transformation en cartes */
        @media (max-width: 768px) {
            .cart-table thead {
                display: none;
            }
            
            .cart-table tbody tr {
                display: block;
                margin-bottom: 20px;
                border: 1px solid #e5e5e5;
                padding: 15px;
                position: relative;
            }
            
            .cart-table tbody td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 0;
                border: none;
                text-align: right;
            }
            
            .cart-table tbody td:before {
                content: attr(data-label);
                font-weight: 700;
                text-transform: uppercase;
                font-size: 11px;
                letter-spacing: 1px;
                color: #666;
                margin-right: 15px;
                text-align: left;
            }
            
            .cart-table tbody td:first-child {
                justify-content: center;
            }
            
            .cart-table tbody td:first-child:before {
                display: none;
            }
            
            .product-img-cart {
                width: 100px;
                height: 120px;
            }
            
            .qty-value {
                min-width: 40px;
            }
        }
        
        @media (max-width: 480px) {
            .cart-table tbody td {
                flex-wrap: wrap;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .cart-table tbody td:before {
                margin-bottom: 8px;
                display: block;
            }
            
            .cart-table tbody td:first-child {
                align-items: center;
            }
            
            .d-flex.align-items-center.justify-content-center {
                justify-content: flex-start !important;
                margin-top: 10px;
            }
        }
        
        /* Boutons de quantité carrés */
        .qty-btn { 
            border: 1px solid #111111; 
            background: #ffffff; 
            color: #111111; 
            width: 30px; 
            height: 30px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            text-decoration: none; 
            font-size: 14px; 
            transition: all 0.2s; 
        }
        
        .qty-btn:hover { 
            background: #111111; 
            color: #ffffff; 
        }
        
        .qty-value { 
            width: 40px; 
            text-align: center; 
            font-size: 14px; 
            font-weight: 700; 
            display: inline-block; 
        }

        /* Bloc Résumé de commande - Responsive */
        .summary-box { 
            background-color: #fafafa; 
            border: 1px solid #e5e5e5; 
            padding: 30px; 
        }
        
        .summary-title { 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-size: 16px; 
            margin-bottom: 20px; 
        }
        
        @media (max-width: 768px) {
            .summary-box {
                padding: 20px;
                margin-top: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .summary-box {
                padding: 15px;
            }
        }
        
        /* Boutons d'action massifs */
        .btn-asos-dark { 
            background-color: #111111; 
            color: #ffffff; 
            border: none; 
            border-radius: 0 !important; 
            padding: 15px 30px; 
            font-size: 13px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            text-decoration: none; 
            display: block; 
            text-align: center; 
            width: 100%; 
            transition: background 0.2s; 
        }
        
        .btn-asos-dark:hover { 
            background-color: #2d2d2d; 
            color: #ffffff; 
        }
        
        @media (max-width: 576px) {
            .btn-asos-dark {
                padding: 12px 20px;
                font-size: 11px;
                letter-spacing: 1.5px;
            }
        }
        
        .btn-link-continue { 
            color: #111111; 
            text-transform: uppercase; 
            font-size: 12px; 
            font-weight: 700; 
            letter-spacing: 1px; 
            text-decoration: underline; 
        }
        
        .btn-link-continue:hover { 
            color: #666; 
        }
        
        /* Container responsive */
        .container {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }
        
        @media (max-width: 576px) {
            .container {
                padding-right: 12px;
                padding-left: 12px;
            }
        }
        
        /* Alert responsive */
        .alert {
            font-size: 13px;
        }
        
        @media (max-width: 576px) {
            .alert {
                font-size: 12px;
                padding: 10px 12px !important;
            }
        }
        
        /* Espacements généraux */
        .mb-5 {
            margin-bottom: 2rem !important;
        }
        
        @media (max-width: 768px) {
            .mb-5 {
                margin-bottom: 1.5rem !important;
            }
        }
        
        /* Badge panier responsive */
        .badge.bg-dark {
            font-size: 8px !important;
            padding: 3px 5px !important;
        }
        
        @media (max-width: 576px) {
            .badge.bg-dark {
                font-size: 7px !important;
                padding: 2px 4px !important;
            }
        }
        
        /* Panier vide responsive */
        .text-center.py-5.border {
            padding: 40px 20px !important;
        }
        
        @media (max-width: 576px) {
            .text-center.py-5.border {
                padding: 30px 15px !important;
            }
            
            .btn-asos-dark.d-inline-block.w-auto {
                width: auto !important;
                display: inline-block !important;
                padding: 10px 25px !important;
            }
        }
        
        /* Ajustement des colonnes sur mobile */
        @media (max-width: 768px) {
            .row.g-5 {
                --bs-gutter-y: 1rem;
            }
            
            .col-lg-8, .col-lg-4 {
                width: 100%;
            }
        }
        
        /* Icônes responsives */
        .bi.bi-bag.fs-5, .bi.bi-person.fs-5 {
            font-size: 1.25rem;
        }
        
        @media (max-width: 576px) {
            .bi.bi-bag.fs-5, .bi.bi-person.fs-5 {
                font-size: 1rem;
            }
        }
        
        /* Zone de texte responsive */
        .text-muted.small {
            font-size: 0.875rem;
        }
        
        @media (max-width: 576px) {
            .text-muted.small {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-5">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">EMMA+</a>
            <div class="ms-auto d-flex align-items-center gap-4">
                <a href="boutique.php" class="nav-link-custom">Boutique</a>
                <a href="panier.php" class="nav-link-custom position-relative border-bottom border-dark fw-bold">
                    <i class="bi bi-bag fs-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-dark text-white" style="font-size: 9px;">
                            <?= $cart_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php 
                    // Déterminer le rôle et le bon dashboard
                    $role = $_SESSION['user_role'] ?? 'client';
                    if ($role === 'admin') {
                        $dashboardLink = '../admin/dashboard.php';
                    } elseif ($role === 'employee') {
                        $dashboardLink = '../employee/dashboard.php';
                    } else {
                        $dashboardLink = '../client/dashboard.php';
                    }
                    ?>
                    <a href="<?= $dashboardLink ?>" class="nav-link-custom" title="Mon Espace">
                        <i class="bi bi-person fs-5"></i>
                    </a>
                <?php else: ?>
                    <a href="connexion.php" class="nav-link-custom" title="Se connecter">
                        <i class="bi bi-person fs-5"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container mb-5">
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-dark border-0 rounded-0 small py-3 mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title mb-5">Mon Panier</h1>

        <?php if (empty($_SESSION['panier'])): ?>
            <!-- CAS 1 : LE PANIER EST VIDE -->
            <div class="text-center py-5 border my-4">
                <i class="bi bi-bag-x fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted small mb-4">Votre panier est actuellement vide.</p>
                <a href="boutique.php" class="btn-asos-dark d-inline-block w-auto px-5">Découvrir nos produits</a>
            </div>
        <?php else: ?>
            <!-- CAS 2 : LE PANIER CONTIENT DES ARTICLES -->
            <div class="row g-5">
                
                <!-- COLONNE GAUCHE : LISTE DES ARTICLES -->
                <div class="col-lg-8">
                    <div class="table-responsive">
                        <table class="table cart-table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" colspan="2">Détails du produit</th>
                                    <th scope="col" class="text-center" style="width: 150px;">Quantité</th>
                                    <th scope="col" class="text-end" style="width: 120px;">Prix unitaire</th>
                                    <th scope="col" class="text-end" style="width: 120px;">Total</th>
                                    <th scope="col" class="text-center" style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['panier'] as $item): ?>
                                    <?php 
                                        $imgUrl = (strpos($item['image'], 'http') === 0) ? $item['image'] : '../../uploads/' . $item['image'];
                                        $sous_total = $item['prix'] * $item['quantite'];
                                    ?>
                                    <tr>
                                        <!-- Image -->
                                        <td style="width: 90px;" data-label="">
                                            <img src="<?= htmlspecialchars($imgUrl) ?>" class="product-img-cart" alt="Img">
                                        </td>
                                        <!-- Nom -->
                                        <td data-label="Produit">
                                            <strong class="d-block" style="font-size: 15px;"><?= htmlspecialchars($item['nom']) ?></strong>
                                            <span class="text-muted small d-block mt-1"><?= htmlspecialchars(substr($item['description'] ?? '', 0, 45)) ?>...</span>
                                        </td>
                                        <!-- Selecteur Quantité (- / +) -->
                                        <td class="text-center" data-label="Quantité">
                                            <div class="d-flex align-items-center justify-content-center justify-content-md-center">
                                                <a href="panier.php?action=decrease&id=<?= $item['id'] ?>" class="qty-btn">-</a>
                                                <span class="qty-value"><?= $item['quantite'] ?></span>
                                                <a href="panier.php?action=increase&id=<?= $item['id'] ?>" class="qty-btn">+</a>
                                            </div>
                                        </td>
                                        <!-- Prix Unitaire -->
                                        <td class="text-end fw-semibold" style="font-size: 14px; white-space: nowrap;" data-label="Prix unitaire">
                                            <?= number_format($item['prix'], 0, ',', ' ') ?> F
                                        </td>
                                        <!-- Prix Total de la ligne -->
                                        <td class="text-end fw-bold" style="font-size: 14px; white-space: nowrap;" data-label="Total">
                                            <?= number_format($sous_total, 0, ',', ' ') ?> F
                                        </td>
                                        <!-- Bouton Supprimer -->
                                        <td class="text-center" data-label="">
                                            <a href="panier.php?action=remove&id=<?= $item['id'] ?>" class="text-danger" title="Retirer cet article" onclick="return confirm('Retirer cet article du panier ?');">
                                                <i class="bi bi-trash3 fs-5"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <a href="boutique.php" class="btn-link-continue"><i class="bi bi-arrow-left me-2"></i>Continuer mes achats</a>
                    </div>
                    
                </div>
                
                <!-- COLONNE DROITE : RÉSUMÉ & VALIDATION -->
                <div class="col-lg-4">
                    <div class="summary-box shadow-sm">
                        <h2 class="summary-title">Résumé de la commande</h2>
                        
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                            <span class="text-muted small">Articles (<?= $cart_count ?>)</span>
                            <span class="fw-semibold"><?= number_format($total_general, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Sous-total</span>
                            <span class="fw-semibold"><?= number_format($total_general, 0, ',', ' ') ?> FCFA</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Frais de livraison</span>
                            <span class="<?= $frais_livraison == 0 ? 'text-success' : 'text-dark' ?> fw-semibold">
                                <?php if ($frais_livraison == 0): ?>
                                    <i class="bi bi-gift me-1"></i> Offerts !
                                <?php else: ?>
                                    <?= number_format($frais_livraison, 0, ',', ' ') ?> FCFA
                                <?php endif; ?>
                            </span>
                       </div>

                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <span class="text-muted small">Total à payer</span>
                            <span class="fw-bold fs-5"><?= number_format($total_avec_livraison, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        
                        <!-- Logique intelligente pour le bouton de commande -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <!-- Connecté -> Passe à la caisse -->
                            <a href="passer_commande.php" class="btn-asos-dark">Passer la commande</a>
                        <?php else: ?>
                            <!-- Non connecté -> Obligation de se loguer avant de commander -->
                            <a href="connexion.php?redirect=panier" class="btn-asos-dark">Se connecter pour commander</a>
                            <p class="text-center text-muted mt-2 mb-0" style="font-size: 11px;">Une connexion est requise pour finaliser la livraison.</p>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
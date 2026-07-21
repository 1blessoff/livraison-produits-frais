<?php
// views/public/boutique.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

// =========================================================================
// 🔍 1. RÉCUPÉRATION DES CATÉGORIES POUR LE FILTRE
// =========================================================================
$categories = [];
try {
    $stmtCat = $pdo->query("SELECT * FROM categories ORDER BY nom ASC");
    $categories = $stmtCat->fetchAll();
} catch (\PDOException $e) {
    $categories = [];
}

// =========================================================================
// 2. GESTION DU FILTRE PAR CATÉGORIE
// =========================================================================
$category_filter = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;

try {
    if ($category_filter > 0) {
        $stmtProd = $pdo->prepare("SELECT * FROM produits WHERE actif = 1 AND categorie_id = ? ORDER BY id DESC");
        $stmtProd->execute([$category_filter]);
    } else {
        $stmtProd = $pdo->query("SELECT * FROM produits WHERE actif = 1 ORDER BY id DESC");
    }
    $produits = $stmtProd->fetchAll();
} catch (\PDOException $e) {
    die("Erreur lors du chargement des produits : " . $e->getMessage());
}

// Compteur d'articles dans le panier
$cart_count = 0;
if (!empty($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $cart_count += $item['quantite'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Boutique | EMMA+</title>
    
    <!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #f8f9fa; 
            color: #111111; 
        }
        
        /* Navbar minimaliste */
        .navbar-brand { 
            font-weight: 900; 
            font-size: clamp(20px, 3vw, 26px);
            letter-spacing: 3px; 
            color: #111111 !important; 
            text-decoration: none; 
        }
        .nav-link-custom { 
            color: #111111; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: clamp(11px, 1.2vw, 13px);
            letter-spacing: 1px; 
            text-decoration: none; 
        }
        .nav-link-custom:hover { 
            text-decoration: underline; 
        }

        .page-title { 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-size: clamp(22px, 4vw, 28px);
        }
        
        /* Menu des Filtres - RESPONSIVE */
        .filter-wrapper {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 30px;
            padding: 0 5px;
        }
        
        .filter-link { 
            color: #666666; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: clamp(9px, 1.2vw, 12px);
            letter-spacing: 1px; 
            text-decoration: none; 
            padding: 5px 10px; 
            border: 1px solid transparent; 
            transition: all 0.2s ease; 
            white-space: nowrap;
        }
        .filter-link:hover { 
            color: #111111; 
        }
        .filter-link.active { 
            color: #111111; 
            border-color: #111111; 
            font-weight: 700; 
        }

        /* Carte produit avec bordures */
        .product-card { 
            border: 1px solid #e5e5e5;
            background: #ffffff;
            padding: 10px;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .product-card:hover {
            border-color: #111111;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }

        .img-container { 
            position: relative; 
            width: 100%; 
            padding-bottom: 100%; /* Carré parfait */
            overflow: hidden; 
            background-color: #f9f9f9; 
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .product-img { 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: transform 0.6s ease, filter 0.6s ease, opacity 0.6s ease;
        }
        
        /* Animation au survol de l'image */
        .img-container:hover .product-img { 
            transform: scale(1.08);
            filter: brightness(1.05);
        }
        
        /* Effet de zoom avec une légère ombre */
        .product-card:hover .img-container .product-img {
            transform: scale(1.08);
            filter: brightness(1.05) contrast(1.02);
        }
        
        /* Animation d'apparition des cartes au chargement */
        .product-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Délai d'animation pour chaque carte */
        <?php foreach ($produits as $index => $prod): ?>
            .product-card:nth-child(<?= $index + 1 ?>) {
                animation-delay: <?= min($index * 0.05, 0.5) ?>s;
            }
        <?php endforeach; ?>
        
        .product-name { 
            font-weight: 700; 
            font-size: clamp(12px, 1.5vw, 15px); 
            color: #111111; 
            margin-bottom: 4px;
            text-decoration: none;
            transition: color 0.2s ease;
            display: inline-block;
        }
        
        .product-name:hover {
            color: #333333;
            text-decoration: underline;
        }
        
        .product-description {
            font-size: clamp(10px, 1.2vw, 12px);
            color: #666666;
            line-height: 1.4;
            margin-bottom: 8px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price { 
            font-weight: 800; 
            font-size: clamp(13px, 1.6vw, 16px); 
            color: #111111;
            margin-bottom: 10px;
        }
        
        .btn-add-quick { 
            display: block;
            width: 100%;
            background-color: #111111; 
            color: #ffffff; 
            border: none; 
            text-transform: uppercase; 
            font-size: clamp(9px, 1.1vw, 11px); 
            font-weight: 700; 
            letter-spacing: 1px; 
            padding: 8px 4px; 
            text-align: center; 
            text-decoration: none; 
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        
        .btn-add-quick:hover {
            background-color: #333333;
            color: #ffffff;
        }
        
        .btn-add-quick.disabled,
        .btn-add-quick:disabled {
            background-color: #999999;
            cursor: not-allowed;
        }
        
        .badge-out { 
            position: absolute; 
            top: 8px; 
            left: 8px; 
            background-color: #111111; 
            color: #ffffff; 
            border-radius: 0; 
            font-size: clamp(7px, 0.9vw, 10px); 
            text-transform: uppercase; 
            font-weight: 700; 
            padding: 3px 6px; 
            z-index: 2;
        }
        
        .stock-faible {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background-color: #ffc107;
            color: #111111;
            font-size: clamp(6px, 0.8vw, 9px);
            font-weight: 700;
            padding: 2px 5px;
            text-transform: uppercase;
            z-index: 2;
        }

        /* ============================================
        BOUTON RETOUR ACCUEIL
        ============================================ */
        .btn-back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #111111;
            font-weight: 600;
            font-size: clamp(11px, 1.2vw, 14px);
            text-decoration: none;
            padding: 6px 12px;
            border: 1px solid #e5e5e5;
            background: #ffffff;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn-back-home:hover {
            background: #111111;
            color: #ffffff;
            border-color: #111111;
            transform: translateX(-5px);
        }
        
        .btn-back-home i {
            font-size: clamp(14px, 1.5vw, 18px);
        }

        /* ============================================
        BOUTON RETOUR EN HAUT (FLOATING)
        ============================================ */
        #scrollToTop {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: #111111;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        
        #scrollToTop.visible {
            opacity: 1;
            visibility: visible;
        }
        
        #scrollToTop:hover {
            background: #333333;
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        #scrollToTop:active {
            transform: scale(0.95);
        }

        /* ============================================
        FOOTER - IDENTIQUE À LA PAGE D'ACCUEIL
        ============================================ */
        footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e5e5e5;
            font-size: 13px;
            color: #555555;
        }
        
        footer h6 {
            color: #111111;
            font-weight: 700;
            text-transform: uppercase;
            font-size: clamp(12px, 1.1vw, 13px);
            letter-spacing: 1px;
            margin-bottom: 22px;
        }
        
        footer a {
            color: #666666;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        footer a:hover {
            color: #111111;
            text-decoration: underline;
        }
        
        /* Icônes sociales dans le footer */
        .social-icons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #333333;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background-color: #111111;
            color: #ffffff;
            transform: translateY(-3px);
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .social-icons a i {
            line-height: 1;
        }
        
        /* Ajustement footer mobile */
        @media (max-width: 767px) {
            footer .col-md-4 {
                text-align: center;
                margin-bottom: 30px;
            }
            
            footer .ps-md-5 {
                padding-left: 0 !important;
            }
            
            footer .col-6 {
                text-align: center;
            }
            
            footer .col-6:last-child {
                margin-top: 20px;
            }
            
            .social-icons {
                justify-content: center;
            }
        }

        /* Ajustement social icons responsive */
        @media (max-width: 576px) {
            .social-icons a {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }
        }
        
        /* RESPONSIVE - Ajustements pour petits écrans */
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 20px;
                letter-spacing: 2px;
            }
            
            .nav-link-custom {
                font-size: 11px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .filter-link {
                font-size: 9px;
                padding: 4px 8px;
                white-space: nowrap;
            }
            
            .filter-wrapper {
                gap: 4px;
                margin-bottom: 20px;
            }
            
            .product-card {
                padding: 8px;
            }
            
            .product-name {
                font-size: 12px;
            }
            
            .product-description {
                font-size: 10px;
                -webkit-line-clamp: 2;
            }
            
            .product-price {
                font-size: 13px;
            }
            
            .btn-add-quick {
                font-size: 9px;
                padding: 6px 4px;
            }
            
            .badge-out {
                font-size: 7px;
                padding: 2px 5px;
                top: 5px;
                left: 5px;
            }
            
            .stock-faible {
                font-size: 6px;
                padding: 2px 4px;
                bottom: 5px;
                right: 5px;
            }
            
            /* Ajustement animation mobile */
            .product-card {
                animation-duration: 0.4s;
            }
            
            /* Ajustement bouton retour en haut */
            #scrollToTop {
                width: 44px;
                height: 44px;
                font-size: 18px;
                bottom: 20px;
                right: 20px;
            }
            
            .btn-back-home {
                font-size: 11px;
                padding: 5px 10px;
            }
        }

        /* Ajustements pour tablettes */
        @media (min-width: 577px) and (max-width: 992px) {
            .filter-link {
                font-size: 10px;
                padding: 4px 10px;
            }
            
            .product-card {
                padding: 10px;
            }
            
            .filter-wrapper {
                gap: 5px;
            }
        }

        /* Pour très grands écrans */
        @media (min-width: 1400px) {
            .container {
                max-width: 1320px;
            }
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-4">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">EMMA+</a>
            <div class="ms-auto d-flex align-items-center gap-3 gap-sm-4">
                <a href="boutique.php" class="nav-link-custom fw-bold border-bottom border-dark d-none d-sm-inline">Boutique</a>
                <a href="panier.php" class="nav-link-custom position-relative">
                    <i class="bi bi-bag fs-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-dark text-white" style="font-size: 9px;">
                            <?= $cart_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../public/connexion.php" class="nav-link-custom" title="Mon Espace"><i class="bi bi-person fs-5"></i></a>
            </div>
        </div>
    </header>

    <div class="container mb-5">
        
        <!-- ============================================
        BOUTON RETOUR À L'ACCUEIL
        ============================================ -->
        <a href="../../index.php" class="btn-back-home">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
        
        <div class="text-center my-4 my-md-5">
            <h1 class="page-title mb-2">Le Marché EMMA+</h1>
            <p class="text-muted small px-3">Vos produits vivriers frais et paniers maraîchers livrés à domicile sur Pointe-Noire.</p>
        </div>

        <!-- Filtres avec meilleure responsivité -->
        <div class="filter-wrapper">
            <a href="boutique.php" class="filter-link <?= $category_filter === 0 ? 'active' : '' ?>">Tout voir</a>
            <?php foreach ($categories as $cat): ?>
                <a href="boutique.php?categorie=<?= $cat['id'] ?>" class="filter-link <?= $category_filter === $cat['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['nom']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($produits)): ?>
            <div class="text-center py-5 border bg-white">
                <i class="bi bi-basket fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted small">Aucun produit disponible dans cette catégorie pour le moment.</p>
                <a href="boutique.php" class="text-dark small fw-bold text-uppercase">Retourner à tout voir</a>
            </div>
        <?php else: ?>
            <!-- Grille responsive améliorée -->
            <div class="row g-2 g-sm-3 g-md-4">
                <?php foreach ($produits as $prod): ?>
                    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                        <div class="product-card">
                            
                            <div class="img-container">
                                <?php 
                                    // Gestion robuste des images
                                    $imageFile = !empty($prod['image']) ? $prod['image'] : '';
                                    $imagePath = __DIR__ . '/../../uploads/' . $imageFile;
                                    
                                    if (!empty($imageFile) && file_exists($imagePath) && filesize($imagePath) > 0) {
                                        $imgUrl = '../../uploads/' . $imageFile;
                                    } else {
                                        $imgUrl = 'https://via.placeholder.com/300x300/111111/FFFFFF?text=' . urlencode($prod['nom']);
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($imgUrl) ?>" 
                                     alt="<?= htmlspecialchars($prod['nom']) ?>" 
                                     class="product-img" 
                                     loading="lazy"
                                     onerror="this.src='https://via.placeholder.com/300x300/111111/FFFFFF?text=<?= urlencode($prod['nom']) ?>'">
                                
                                <?php if ($prod['stock'] <= 0): ?>
                                    <span class="badge-out">Rupture</span>
                                <?php elseif ($prod['stock'] < 5): ?>
                                    <span class="stock-faible">Stock faible</span>
                                <?php endif; ?>
                            </div>

                            <!-- NOM DU PRODUIT CLICKABLE -->
                            <?php 
                                // Utiliser le slug si disponible, sinon l'ID
                                $productLink = !empty($prod['slug']) 
                                    ? 'produit.php?slug=' . urlencode($prod['slug']) 
                                    : 'produit.php?id=' . $prod['id'];
                            ?>
                            <a href="<?= $productLink ?>" class="product-name">
                                <?= htmlspecialchars($prod['nom']) ?>
                            </a>
                            
                            <div class="product-description">
                                <?php 
                                    $desc = !empty($prod['description']) ? trim($prod['description']) : 'Aucune description disponible';
                                    if (strlen($desc) > 80) {
                                        echo htmlspecialchars(substr($desc, 0, 80)) . '...';
                                    } else {
                                        echo htmlspecialchars($desc);
                                    }
                                ?>
                            </div>
                            
                            <div class="product-price"><?= number_format($prod['prix'], 0, ',', ' ') ?> FCFA</div>
                            
                            <?php if ($prod['stock'] > 0): ?>
                                <a href="ajouter_panier.php?id=<?= $prod['id'] ?>&quantite=1" class="btn-add-quick">
                                    <i class="bi bi-bag-plus me-3"></i>Ajouter au panier
                                </a>
                            <?php else: ?>
                                <button class="btn-add-quick" disabled>
                                    <i class="bi bi-x-circle me-2"></i>Épuisé
                                </button>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ============================================
    FOOTER - IDENTIQUE À LA PAGE D'ACCUEIL
    ============================================ -->
    <footer class="py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <!-- À propos -->
                <div class="col-md-4">
                    <h6>À propos de Emma+</h6>
                    <p class="lh-lg" style="color: #777;">Emma+, Projet fait par kouka <br> parfait christ Étudiant en GI licence2.</p>
                    
                    <!-- Liens sociaux -->
                    <div class="social-icons">
                        <a href="https://www.instagram.com/blessplyer/" target="_blank" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://about.me/christ.kouka" target="_blank" title="About.me">
                            <i class="bi bi-person-badge"></i>
                        </a>
                        <a href="https://github.com/1blessoff" target="_blank" title="Github">
                            <i class="bi bi-github"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Liens Utiles -->
                <div class="col-6 col-md-4 ps-md-5">
                    <h6>Liens Utiles</h6>
                    <ul class="list-unstyled lh-lg">
                        <li><a href="boutique.php">Catalogue</a></li>
                        <li><a href="cgu.php">Conditions d'Utilisation</a></li>
                        <li><a href="inscription.php">Créer un compte</a></li>
                    </ul>
                </div>
                
                <!-- Service Client  -->
                <div class="col-6 col-md-4">
                    <h6>Service Client</h6>
                    <p class="mb-2"><i class="bi bi-telephone me-2"></i>+242 05 532 22 33</p>
                    <p><i class="bi bi-envelope me-2"></i> koukachrist48@gmail.com</p>
                </div>
            </div>
            
            <div class="text-center pt-4 mt-4 border-top text-muted small">
                <p class="mb-0">&copy; <?= date('Y') ?> Emma+ Inter. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- ============================================
    BOUTON RETOUR EN HAUT (FLOATING)
    ============================================ -->
    <button id="scrollToTop" title="Retour en haut">
        <i class="bi bi-chevron-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Gestion des erreurs de chargement d'images
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.product-img');
            images.forEach(function(img) {
                img.addEventListener('error', function() {
                    const productName = this.alt || 'Product';
                    this.src = 'https://via.placeholder.com/300x300/111111/FFFFFF?text=' + encodeURIComponent(productName);
                });
            });
        });

        // ============================================
        // BOUTON RETOUR EN HAUT (SCROLL TO TOP)
        // ============================================
        (function() {
            'use strict';
            
            const scrollBtn = document.getElementById('scrollToTop');
            
            if (scrollBtn) {
                // Afficher/masquer le bouton selon la position de défilement
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        scrollBtn.classList.add('visible');
                    } else {
                        scrollBtn.classList.remove('visible');
                    }
                });

                // Remonter en haut en douceur
                scrollBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });

                // Gestion du clic avec touche pour accessibilité
                scrollBtn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                });
            }
        })();
    </script>
</body>
</html>
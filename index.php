<?php
// 1. INCLUSION DE LA CONNEXION À LA BASE DE DONNÉES
session_start();
require_once __DIR__ . '/config/database.php';

// ============================================
// GESTION DE LA REDIRECTION POUR "DONNER AVIS"
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'donner_avis') {
    if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'client') {
        header('Location: views/client/ajouter_temoignage.php');
        exit();
    } else {
        $_SESSION['redirect_after_login'] = 'views/client/ajouter_temoignage.php';
        header('Location: views/public/connexion.php');
        exit();
    }
}

try {
    // 2. RÉCUPÉRATION DES CATÉGORIES DEPUIS LA BD
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
    $categories = $stmt->fetchAll();
} catch (\PDOException $e) {
    $categories = [];
    error_log("Erreur lors de la récupération des catégories : " . $e->getMessage());
}

// ============================================
// RÉCUPÉRATION DES TÉMOIGNAGES VISIBLES
// ============================================
$temoignages = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM temoignages 
        WHERE visible = 1 
        ORDER BY created_at DESC 
        LIMIT 6
    ");
    $temoignages = $stmt->fetchAll();
} catch (\PDOException $e) {
    $temoignages = [];
    error_log("Erreur lors de la récupération des témoignages : " . $e->getMessage());
}

// Récupération du nombre d'articles dans le panier
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
    <meta name="description" content="Emma+ - Panier de la Ménagère, produits frais livrés à domicile">
    <title>Emma+ | Panier de la Ménagère</title>
    
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="favicon.ico">
    
    <style>
        /* ============================================
        VARIABLES & RESET
        ============================================ */
        :root {
            --primary: #111111;
            --secondary: #666666;
            --light-bg: #f8f9fa;
            --border-color: #e5e5e5;
            --transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #ffffff;
            color: var(--primary);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ============================================
        NAVBAR PROFESSIONNELLE
        ============================================ */
        .navbar {
            border-bottom: 1px solid var(--border-color);
            padding: 14px 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: clamp(22px, 3vw, 28px);
            letter-spacing: 4px;
            color: var(--primary) !important;
            transition: var(--transition);
        }
        
        .navbar-brand:hover {
            opacity: 0.7;
        }
        
        .nav-link {
            font-size: clamp(11px, 1.1vw, 13px);
            font-weight: 600;
            text-transform: uppercase;
            color: var(--primary) !important;
            padding: 6px 14px !important;
            letter-spacing: 1.5px;
            position: relative;
            transition: var(--transition);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
            transform: translateX(-50%);
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 60%;
        }
        
        .nav-link:hover {
            color: var(--primary) !important;
        }
        
        .nav-link.active {
            font-weight: 700;
        }
        
        .nav-icon {
            font-size: clamp(18px, 2vw, 22px);
            color: var(--primary);
            margin-left: clamp(14px, 2vw, 24px);
            position: relative;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-icon:hover {
            color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .nav-icon .badge-cart {
            position: absolute;
            top: -8px;
            right: -10px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        /* ============================================
        HERO BANNER
        ============================================ */
        .hero-banner {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                        url('https://images.unsplash.com/photo-1542838132-92c53300491e?q=80&w=1974&auto=format&fit=crop') 
                        no-repeat center center / cover;
            min-height: clamp(350px, 70vh, 650px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-align: center;
            padding: 60px 20px;
            position: relative;
        }
        
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(to top, rgba(0,0,0,0.3), transparent);
            pointer-events: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }
        
        .hero-title {
            font-size: clamp(32px, 7vw, 56px);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 6px;
            margin-bottom: 20px;
            text-shadow: 0 2px 30px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease;
        }
        
        .hero-subtitle {
            font-size: clamp(14px, 1.8vw, 18px);
            letter-spacing: 4px;
            font-weight: 300;
            margin-bottom: 35px;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.4s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================
        BOUTONS
        ============================================ */
        .btn-primary-custom {
            background: #ffffff;
            color: var(--primary);
            border: 2px solid #ffffff;
            border-radius: 0;
            padding: clamp(12px, 1.5vw, 16px) clamp(30px, 5vw, 50px);
            font-size: clamp(11px, 1.1vw, 13px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-primary-custom:hover {
            background: transparent;
            color: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-secondary-custom {
            background: transparent;
            color: #ffffff;
            border: 2px solid #ffffff;
            border-radius: 0;
            padding: clamp(12px, 1.5vw, 16px) clamp(30px, 5vw, 50px);
            font-size: clamp(11px, 1.1vw, 13px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-secondary-custom:hover {
            background: #ffffff;
            color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* ============================================
        SECTION CATÉGORIES
        ============================================ */
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-size: clamp(24px, 3.5vw, 32px);
            margin-bottom: 12px;
        }
        
        .section-divider {
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 0 auto;
        }
        
        .section-subtitle {
            color: var(--secondary);
            font-size: clamp(14px, 1.2vw, 16px);
            margin-top: 15px;
            font-weight: 300;
        }
        
        .category-card {
            text-decoration: none;
            display: block;
            transition: var(--transition);
        }
        
        .category-card:hover {
            transform: translateY(-8px);
        }
        
        .category-img-wrapper {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            background: #f4f4f4;
            border: 1px solid #f0f0f0;
        }
        
        .category-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .category-card:hover .category-img-wrapper img {
            transform: scale(1.08);
        }
        
        .category-img-wrapper::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.1), transparent);
            opacity: 0;
            transition: var(--transition);
        }
        
        .category-card:hover .category-img-wrapper::after {
            opacity: 1;
        }
        
        .category-title {
            font-size: clamp(12px, 1.3vw, 15px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 14px;
            color: var(--primary);
            text-align: center;
            transition: var(--transition);
        }
        
        .category-card:hover .category-title {
            color: var(--secondary);
        }
        
        /* ============================================
        TÉMOIGNAGES - CARROUSEL
        ============================================ */
        .testimonials-wrapper {
            background: var(--light-bg);
            padding: 30px 0;
            mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
            -webkit-mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
        }
        
        .testimonials-track {
            animation: scrollTestimonials 28s linear infinite;
            width: fit-content;
            display: flex;
        }
        
        .testimonials-track:hover {
            animation-play-state: paused;
        }
        
        @keyframes scrollTestimonials {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .testimonial-slide {
            width: 300px;
            min-width: 300px;
            padding: 0 12px;
        }
        
        .testimonial-card {
            border: 1px solid var(--border-color);
            background: #ffffff;
            padding: 22px 20px;
            min-height: 170px;
            transition: var(--transition);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            height: 100%;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.07);
        }
        
        .testimonial-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        
        .testimonial-stars {
            color: #f59e0b;
            font-size: 13px;
            letter-spacing: 1px;
        }
        
        .testimonial-text {
            font-style: italic;
            font-size: 14px;
            line-height: 1.7;
            color: #444;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .testimonial-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--primary);
        }
        
        .testimonial-date {
            font-size: 11px;
            color: #999;
        }
        
        /* ============================================
        FOOTER COMPACT & PROFESSIONNEL
        ============================================ */
        footer {
            background: var(--light-bg);
            border-top: 1px solid var(--border-color);
            color: #555;
            font-size: 13px;
            padding: 40px 0 30px;
        }
        
        footer h6 {
            color: var(--primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }
        
        footer p,
        footer li {
            font-size: 13px;
            line-height: 1.6;
        }
        
        footer a {
            color: var(--secondary);
            text-decoration: none;
            transition: var(--transition);
            font-size: 13px;
        }
        
        footer a:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        .footer-text {
            color: #777;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .social-icons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e9ecef;
            color: #333;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .social-icons a:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-3px);
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        footer .list-unstyled {
            margin-bottom: 0;
        }
        
        footer .list-unstyled li {
            padding: 2px 0;
        }
        
        footer .list-unstyled li a {
            font-size: 13px;
        }
        
        .footer-contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #777;
            margin-bottom: 4px;
        }
        
        .footer-contact-item i {
            font-size: 14px;
            color: var(--primary);
            width: 18px;
            text-align: center;
        }
        
        .footer-contact-item a {
            color: #777;
            font-size: 13px;
        }
        
        .footer-contact-item a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            margin-top: 25px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: #999;
        }
        
        .footer-bottom p {
            margin-bottom: 0;
            font-size: 12px;
        }
        
        /* ============================================
        RESPONSIVE
        ============================================ */
        @media (max-width: 991px) {
            .navbar-nav {
                text-align: center;
                padding: 15px 0;
            }
            
            .nav-link::after {
                display: none;
            }
            
            .nav-link {
                padding: 10px 0 !important;
            }
            
            .d-flex.align-items-center {
                justify-content: center;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid var(--border-color);
            }
            
            .nav-icon {
                margin: 0 18px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-banner {
                min-height: 300px;
                padding: 40px 16px;
            }
            
            .hero-title {
                letter-spacing: 3px;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-primary-custom,
            .btn-secondary-custom {
                width: 100%;
                text-align: center;
                padding: 14px 20px;
            }
            
            .testimonial-slide {
                width: 220px;
                min-width: 220px;
                padding: 0 8px;
            }
            
            .testimonials-track {
                animation-duration: 20s;
            }
            
            .testimonial-card {
                padding: 16px 14px;
                min-height: 140px;
            }
            
            .testimonial-text {
                font-size: 12px;
                -webkit-line-clamp: 2;
            }
            
            .testimonial-avatar {
                width: 36px;
                height: 36px;
                font-size: 12px;
            }
            
            .row.g-4 {
                --bs-gutter-y: 1rem;
                --bs-gutter-x: 0.75rem;
            }
            
            /* Footer mobile */
            footer {
                padding: 30px 0 20px;
            }
            
            footer .col-md-4 {
                text-align: center;
                margin-bottom: 25px;
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
            
            .footer-contact-item {
                justify-content: center;
            }
            
            footer .ps-md-5 {
                padding-left: 0 !important;
            }
        }
        
        @media (max-width: 400px) {
            .testimonial-slide {
                width: 180px;
                min-width: 180px;
            }
            
            .testimonials-track {
                animation-duration: 16s;
            }
            
            .testimonial-card {
                padding: 12px 10px;
                min-height: 120px;
            }
            
            .testimonial-text {
                font-size: 11px;
            }
            
            .testimonial-avatar {
                width: 30px;
                height: 30px;
                font-size: 10px;
            }
            
            .testimonial-name {
                font-size: 11px;
            }
        }
        
        @media (min-width: 577px) and (max-width: 991px) {
            .testimonial-slide {
                width: 260px;
                min-width: 260px;
            }
            
            .testimonials-track {
                animation-duration: 24s;
            }
        }
        
        /* ============================================
        SCROLLBAR PERSONNALISÉE
        ============================================ */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #333;
        }
    </style>
</head>
<body>

    <!-- ============================================
    NAVBAR
    ============================================ -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">EMMA+</a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="views/public/boutique.php">Catalogue</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="views/public/contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center mt-3 mt-lg-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="views/client/dashboard.php" class="nav-icon" title="Mon Compte">
                            <i class="bi bi-person"></i>
                        </a>
                    <?php else: ?>
                        <a href="views/public/connexion.php" class="nav-icon" title="Se connecter">
                            <i class="bi bi-person"></i>
                        </a>
                    <?php endif; ?>
                    
                    <a href="views/public/panier.php" class="nav-icon" title="Mon Panier">
                        <i class="bi bi-bag"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="badge-cart"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================
    HERO BANNER
    ============================================ -->
    <header class="hero-banner">
        <div class="hero-content">
            <h1 class="hero-title">Le Panier de la Ménagère</h1>
            <p class="hero-subtitle">Vos produits frais commandés en ligne, livrés chez vous</p>
            <div class="hero-buttons">
                <a href="views/public/inscription.php" class="btn-primary-custom">Créer un compte</a>
                <a href="views/public/connexion.php" class="btn-secondary-custom">Se connecter</a>
            </div>
        </div>
    </header>

    <!-- ============================================
    CATÉGORIES
    ============================================ -->
    <main class="container my-5 py-4">
        <div class="section-header">
            <h2 class="section-title">Parcourir nos rayons</h2>
            <div class="section-divider"></div>
            <p class="section-subtitle">Découvrez notre sélection de produits frais et de qualité</p>
        </div>

        <div class="row g-3 g-sm-4">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): 
                    // Récupérer la première image de produit de cette catégorie
                    $imageSrc = 'https://via.placeholder.com/600x600.png?text=' . urlencode($cat['nom']);
                    
                    try {
                        $stmtImg = $pdo->prepare("SELECT image FROM produits WHERE categorie_id = ? AND actif = 1 LIMIT 1");
                        $stmtImg->execute([$cat['id']]);
                        $produit = $stmtImg->fetch();
                        
                        if ($produit && !empty($produit['image'])) {
                            $imagePath = __DIR__ . '/uploads/' . $produit['image'];
                            if (file_exists($imagePath)) {
                                $imageSrc = 'uploads/' . $produit['image'];
                            }
                        }
                    } catch (\PDOException $e) {
                        // Garder l'image par défaut
                    }
                    
                    $lienCategorie = isset($_SESSION['user_id']) 
                        ? "views/public/boutique.php?categorie=" . $cat['id']
                        : "../views/public/connexion.php";
                ?>
                    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                        <a href="<?= $lienCategorie ?>" class="category-card">
                            <div class="category-img-wrapper shadow-sm">
                                <img src="<?= htmlspecialchars($imageSrc) ?>" 
                                     alt="<?= htmlspecialchars($cat['nom']) ?>" 
                                     loading="lazy">
                            </div>
                            <div class="category-title"><?= htmlspecialchars($cat['nom']) ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 border border-dashed text-muted">
                        <i class="bi bi-shop fs-1 d-block mb-3 text-secondary"></i>
                        <p class="mb-0 fw-bold text-uppercase small" style="letter-spacing: 1px;">Aucun rayon disponible</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ============================================
    TÉMOIGNAGES
    ============================================ -->
    <?php if (!empty($temoignages)): ?>
    <section class="container-fluid my-5 py-4 border-top px-0">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title" style="font-size: clamp(20px, 2.8vw, 28px);">Ils nous font confiance</h2>
                <div class="section-divider"></div>
                <p class="section-subtitle">Ce que nos clients disent de leur expérience</p>
            </div>
        </div>

        <div class="testimonials-wrapper position-relative overflow-hidden">
            <div class="testimonials-track">
                <?php foreach (array_merge($temoignages, $temoignages) as $index => $t): ?>
                    <div class="testimonial-slide flex-shrink-0" data-index="<?= $index ?>">
                        <div class="testimonial-card shadow-sm">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="testimonial-avatar">
                                    <?= strtoupper(substr($t['prenom'], 0, 1) . substr($t['nom'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="testimonial-name"><?= htmlspecialchars($t['prenom'] . ' ' . $t['nom']) ?></div>
                                    <div class="testimonial-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $t['note'] ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="testimonial-text mb-0">"<?= htmlspecialchars($t['message']) ?>"</p>
                            <small class="testimonial-date d-block mt-2">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.querySelector('.testimonials-track');
        if (track) {
            track.addEventListener('mouseenter', function() {
                this.style.animationPlayState = 'paused';
            });
            track.addEventListener('mouseleave', function() {
                this.style.animationPlayState = 'running';
            });

            function adjustSpeed() {
                const width = window.innerWidth;
                if (width < 400) track.style.animationDuration = '16s';
                else if (width < 576) track.style.animationDuration = '20s';
                else if (width < 992) track.style.animationDuration = '24s';
                else track.style.animationDuration = '28s';
            }
            
            adjustSpeed();
            window.addEventListener('resize', adjustSpeed);
        }
    });
    </script>
    <?php endif; ?>

    <!-- ============================================
    FOOTER COMPACT
    ============================================ -->
    <footer>
        <div class="container">
            <div class="row g-4">
                <!-- À propos -->
                <div class="col-md-4">
                    <h6>À propos</h6>
                    <p class="footer-text">
                        Emma+, Projet fait par 
                        kouka parfait christ Étudiant en GI licence2.
                    </p>
                    <div class="social-icons">
                        <a href="https://www.instagram.com/blessplyer/" target="_blank" title="Instagram" aria-label="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://about.me/christ.kouka" target="_blank" title="About.me" aria-label="About.me">
                            <i class="bi bi-person-badge"></i>
                        </a>
                        <a href="https://github.com/1blessoff" target="_blank" title="GitHub" aria-label="GitHub">
                            <i class="bi bi-github"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Liens Utiles -->
                <div class="col-6 col-md-4 ps-md-5">
                    <h6>Liens Utiles</h6>
                    <ul class="list-unstyled">
                        <li><a href="views/public/boutique.php">Catalogue</a></li>
                        <li><a href="views/public/cgu.php">Conditions d'Utilisation</a></li>
                        <li><a href="views/public/inscription.php">Créer un compte</a></li>
                    </ul>
                </div>
                
                <!-- Service Client -->
                <div class="col-6 col-md-4">
                    <h6>Service Client</h6>
                    <div class="footer-contact-item">
                        <i class="bi bi-telephone"></i>
                        <a href="tel:+24205532233">+242 05 532 22 33</a>
                    </div>
                    <div class="footer-contact-item">
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:koukachrist48@gmail.com">koukachrist48@gmail.com</a>
                    </div>
                    <div class="footer-contact-item">
                        <i class="bi bi-clock"></i>
                        <span>Lun-Ven: 8h-18h</span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Emma+ Inter. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
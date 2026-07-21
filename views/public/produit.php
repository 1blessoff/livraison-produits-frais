<?php
// views/public/produit.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

// Récupérer le slug depuis l'URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: boutique.php');
    exit();
}

// Récupérer le produit par son slug
try {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE slug = ? AND actif = 1");
    $stmt->execute([$slug]);
    $produit = $stmt->fetch();
} catch (\PDOException $e) {
    die("Erreur lors du chargement du produit : " . $e->getMessage());
}

// Si le produit n'existe pas
if (!$produit) {
    header('Location: boutique.php');
    exit();
}

// Compteur d'articles dans le panier
$cart_count = 0;
if (!empty($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $cart_count += $item['quantite'];
    }
}

// Déterminer le stock restant
$stock_restant = $produit['stock'];
$stock_status = '';
$stock_class = '';

if ($stock_restant <= 0) {
    $stock_status = 'Rupture de stock';
    $stock_class = 'text-danger';
} elseif ($stock_restant < 5) {
    $stock_status = 'Plus que ' . $stock_restant . ' exemplaires !';
    $stock_class = 'text-warning';
} else {
    $stock_status = 'En stock';
    $stock_class = 'text-success';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produit['nom']) ?> | EMMA+</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #f8f9fa; 
            color: #111111; 
        }
        
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
        
        .product-img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border: 1px solid #e5e5e5;
        }
        
        .product-price {
            font-size: 28px;
            font-weight: 900;
            color: #111111;
        }
        
        .btn-asos-dark { 
            background-color: #111111; 
            color: #ffffff; 
            border: none; 
            border-radius: 0 !important; 
            padding: 15px 30px; 
            font-size: 14px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            text-decoration: none; 
            display: inline-block; 
            transition: background 0.2s; 
        }
        
        .btn-asos-dark:hover { 
            background-color: #333333; 
            color: #ffffff; 
        }
        
        .btn-asos-outline {
            background-color: transparent;
            color: #111111;
            border: 1px solid #111111;
            border-radius: 0 !important;
            padding: 15px 30px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-asos-outline:hover {
            background-color: #111111;
            color: #ffffff;
        }
        
        .quantity-input {
            width: 80px;
            padding: 10px;
            text-align: center;
            border: 1px solid #e5e5e5;
            border-radius: 0;
        }
        
        .stock-info {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #111;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-4">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">EMMA+</a>
            <div class="ms-auto d-flex align-items-center gap-4">
                <a href="boutique.php" class="nav-link-custom">Boutique</a>
                <a href="panier.php" class="nav-link-custom position-relative">
                    <i class="bi bi-bag fs-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-dark text-white" style="font-size: 9px;">
                            <?= $cart_count ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../client/dashboard.php" class="nav-link-custom" title="Mon Espace"><i class="bi bi-person fs-5"></i></a>
            </div>
        </div>
    </header>

    <div class="container mb-5">
        <div class="row g-5">
            
            <!-- Colonne image -->
            <div class="col-md-6">
                <?php 
                    $imgUrl = (!empty($produit['image']) && file_exists(__DIR__ . '/../../uploads/' . $produit['image'])) 
                        ? '../../uploads/' . $produit['image'] 
                        : 'https://via.placeholder.com/600x600?text=' . urlencode($produit['nom']);
                ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" class="product-img w-100">
            </div>
            
            <!-- Colonne informations -->
            <div class="col-md-6">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="boutique.php">Boutique</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($produit['nom']) ?></li>
                    </ol>
                </nav>
                
                <h1 class="display-5 fw-bold mb-3"><?= htmlspecialchars($produit['nom']) ?></h1>
                
                <div class="stock-info <?= $stock_class ?>">
                    <i class="bi bi-box me-2"></i><?= $stock_status ?>
                </div>
                
                <div class="product-price mb-4">
                    <?= number_format($produit['prix'], 0, ',', ' ') ?> FCFA
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-bold text-uppercase small mb-2">Description</h5>
                    <p class="text-muted">
                        <?= !empty($produit['description']) ? nl2br(htmlspecialchars($produit['description'])) : 'Aucune description disponible pour ce produit.' ?>
                    </p>
                </div>
                
                <?php if ($produit['stock'] > 0): ?>
                    <form action="ajouter_panier.php" method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                        <input type="hidden" name="id" value="<?= $produit['id'] ?>">
                        
                        <div>
                            <label for="quantite" class="form-label small fw-bold text-uppercase">Quantité</label>
                            <input type="number" name="quantite" id="quantite" class="quantity-input form-control" value="1" min="1" max="<?= $produit['stock'] ?>">
                        </div>
                        
                        <div class="mt-3 mt-sm-0">
                            <button type="submit" class="btn-asos-dark">
                                <i class="bi bi-bag-plus me-2"></i>Ajouter au panier
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <button class="btn-asos-dark" disabled style="background-color: #999; cursor: not-allowed;">
                        <i class="bi bi-x-circle me-2"></i>Rupture de stock
                    </button>
                <?php endif; ?>
                
                <div class="mt-4 pt-3 border-top">
                    <a href="boutique.php" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-2"></i>Continuer mes achats
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
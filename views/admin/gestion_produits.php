<?php
// views/admin/gestion_produits.php
session_start();

function compresserEtConvertirEnWebp(string $sourcePath, string $destinationPath, int $nouvelleLargeur = 800, int $qualite = 80): bool {
    // Sécurité : Vérifier si le fichier temporaire existe bien
    if (!file_exists($sourcePath) || filesize($sourcePath) === 0) {
        return false;
    }

    // 1. Récupérer les infos de l'image d'origine
    $infosImage = getimagesize($sourcePath);
    if ($infosImage === false) {
        return false; // Fichier non reconnu comme une image
    }

    list($largeurOrigine, $hauteurOrigine, $typeOrigine) = $infosImage;
    
    // 2. Calculer la nouvelle hauteur proportionnelle
    $ratio = $largeurOrigine / $hauteurOrigine;
    if ($largeurOrigine > $nouvelleLargeur) {
        $nouvelleHauteur = (int)($nouvelleLargeur / $ratio);
    } else {
        $nouvelleLargeur = $largeurOrigine;
        $nouvelleHauteur = $hauteurOrigine;
    }

    // 3. Créer une image de travail vierge aux nouvelles dimensions
    $imageDest = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);

    // 4. Charger l'image d'origine selon son format
    switch ($typeOrigine) {
        case IMAGETYPE_JPEG:
            $imageSource = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $imageSource = imagecreatefrompng($sourcePath);
            // Préserver la transparence du PNG
            imagealphablending($imageDest, false);
            imagesavealpha($imageDest, true);
            break;
        case IMAGETYPE_WEBP:
            $imageSource = imagecreatefromwebp($sourcePath);
            break;
        default:
            imagedestroy($imageDest);
            return false; // Format non supporté
    }

    if (!$imageSource) {
        imagedestroy($imageDest);
        return false;
    }

    // 5. Redimensionner l'image proprement
    imagecopyresampled($imageDest, $imageSource, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeurOrigine, $hauteurOrigine);

    // 6. Sauvegarder au format WebP
    $resultat = imagewebp($imageDest, $destinationPath, $qualite);

    // 7. Libérer la mémoire
    imagedestroy($imageSource);
    imagedestroy($imageDest);

    return $resultat;
}

// 1. PROTECTION ACCÈS ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/connexion.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$message = "";
$error = "";

// =========================================================================
// 1. TRAITEMENT DE L'AJOUT D'UN PRODUIT (Formulaire soumis via Pop-up)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom = trim($_POST['nom'] ?? '');
    $categorie_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $prix = intval($_POST['prix'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $actif = isset($_POST['actif']) ? 1 : 0;
    
    $image_name = "default.jpg";
    
    // Gestion de l'upload et de la compression de l'image
    $image_name = "default.jpg"; // Image par défaut si aucun fichier n'est envoyé
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $original_name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Extensions d'origine autorisées avant conversion
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            // ✨ ICI : On force l'extension finale en .webp et on définit le chemin
            $image_name = time() . '_' . uniqid() . '.webp';
            $upload_dir = __DIR__ . '/../../uploads/';
            
            // Créer le dossier uploads s'il n'existe pas encore
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $chemin_final = $upload_dir . $image_name;
            
            // ICI : On appelle la fonction magique à la place de move_uploaded_file
            if (!compresserEtConvertirEnWebp($file_tmp, $chemin_final, 800, 80)) {
                $error = "Erreur lors de la compression et de l'optimisation de l'image.";
            }
        } else {
            $error = "Format d'image non valide (uniquement JPG, JPEG, PNG, WEBP).";
        }
    }

    if (empty($error)) {
        if (empty($nom) || $prix <= 0) {
            $error = "Le nom et un prix valide sont obligatoires.";
        } else {
            try {
                $sqlInsert = "INSERT INTO produits (categorie_id, nom, description, prix, stock, image, actif) 
                              VALUES (:categorie_id, :nom, :description, :prix, :stock, :image, :actif)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    'categorie_id' => $categorie_id,
                    'nom'          => $nom,
                    'description'  => $description,
                    'prix'         => $prix,
                    'stock'        => $stock,
                    'image'        => $image_name,
                    'actif'        => $actif
                ]);
                $message = "Produit ajouté avec succès !";
            } catch (\PDOException $e) {
                $error = "Erreur d'insertion en BDD : " . $e->getMessage();
            }
        }
    }
}

// =========================================================================
// 2. TRAITEMENT DE LA MODIFICATION D'UN PRODUIT (Via Pop-up)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_produit = intval($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $categorie_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $prix = intval($_POST['prix'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $actif = isset($_POST['actif']) ? 1 : 0;
    
    if (empty($nom) || $prix <= 0 || $id_produit <= 0) {
        $error = "La modification a échoué. Le nom et un prix valide sont requis.";
    } else {
        try {
            $stmtImg = $pdo->prepare("SELECT image FROM produits WHERE id = ?");
            $stmtImg->execute([$id_produit]);
            $current_product = $stmtImg->fetch();
            $image_name = $current_product['image'] ?? 'default.jpg';

            // Gestion de la nouvelle image si fournie dans le pop-up
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['image']['tmp_name'];
                    $original_name = basename($_FILES['image']['name']);
                    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_ext, $allowed_extensions)) {
                        // ✨ ICI : On génère le nom en .webp pour la nouvelle image
                        $image_name = time() . '_' . uniqid() . '.webp';
                        $upload_dir = __DIR__ . '/../../uploads/';
                        
                        $chemin_final = $upload_dir . $image_name;
                        
                        // 🚀 ICI : On compresse la nouvelle image modifiée
                        if (!compresserEtConvertirEnWebp($file_tmp, $chemin_final, 800, 80)) {
                            $error = "Erreur lors de la compression de la nouvelle image.";
                        }
                    } else {
                        $error = "Format d'image mis à jour non valide.";
                    }
                }

            if (empty($error)) {
                $sqlUpdate = "UPDATE produits 
                              SET categorie_id = ?, nom = ?, description = ?, prix = ?, stock = ?, image = ?, actif = ? 
                              WHERE id = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$categorie_id, $nom, $description, $prix, $stock, $image_name, $actif, $id_produit]);
                $message = "Produit mis à jour avec succès !";
            }
        } catch (\PDOException $e) {
            $error = "Erreur lors de la modification en BDD : " . $e->getMessage();
        }
    }
}                                                                                                                                         


// 3. TRAITEMENT DE LA SUPPRESSION D'UN PRODUIT

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_produit = intval($_GET['id']);
    try {
        $stmtDelete = $pdo->prepare("DELETE FROM produits WHERE id = ?");
        $stmtDelete->execute([$id_produit]);
        $message = "Produit supprimé avec succès !";
    } catch (\PDOException $e) {
        $message = "Impossible de supprimer le produit encours de commandes";
        //$error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}


//  4. MODIFICATION RAPIDE DU STATUT ACTIF

if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $id_produit = intval($_GET['id']);
    try {
        $stmtToggle = $pdo->prepare("UPDATE produits SET actif = NOT actif WHERE id = ?");
        $stmtToggle->execute([$id_produit]);
        header('Location: gestion_produits.php');
        exit();
    } catch (\PDOException $e) {
        $error = "Erreur de changement de statut : " . $e->getMessage();
    }
}

// 5. RÉCUPÉRATION DES CATÉGORIES

$categories = [];
try {
    $stmtCat = $pdo->query("SELECT * FROM categories ORDER BY nom ASC");
    $categories = $stmtCat->fetchAll();
} catch (\PDOException $e) {
    $categories = [];
}


// 6. RÉCUPÉRATION DE TOUS LES PRODUITS
try {
    $stmt = $pdo->query("SELECT * FROM produits ORDER BY id DESC");
    $produits = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Erreur de récupération des produits : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits | EMMA+</title>

    <!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        /* Reset et base */
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #fcfcfc; 
            color: #111111; 
            overflow-x: hidden;
        }
        
        /* Navbar responsive */
        .navbar-brand { 
            font-weight: 900; 
            font-size: clamp(18px, 2.5vw, 24px); 
            letter-spacing: 3px; 
            color: #111111 !important; 
        }
        
        .navbar-brand span {
            font-size: clamp(9px, 1vw, 12px);
        }
        
        .nav-link-admin { 
            font-size: clamp(11px, 1.1vw, 13px); 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 1px; 
            color: #111; 
            text-decoration: none; 
            padding: 5px 0;
        }
        
        .nav-link-admin:hover, .nav-link-admin.active { 
            text-decoration: underline; 
        }
        
        /* Ajustement navbar mobile */
        @media (max-width: 991px) {
            .navbar-nav {
                text-align: center;
                padding: 10px 0;
            }
            
            .navbar-nav .nav-link-admin {
                padding: 8px 0;
                display: block;
            }
            
            .ms-auto.d-flex.align-items-center {
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #e5e5e5;
                gap: 8px !important;
            }
            
            .navbar-toggler {
                border: 1px solid #111 !important;
                border-radius: 0 !important;
                padding: 6px 10px;
            }
            
            .navbar-toggler:focus {
                box-shadow: none;
            }
        }
        
        /* Page header responsive */
        .page-header { 
            background-color: #ffffff; 
            border-bottom: 1px solid #e5e5e5; 
            padding: clamp(15px, 3vw, 30px) 0; 
            margin-bottom: clamp(15px, 3vw, 30px); 
        }
        
        .page-header h1 {
            font-size: clamp(20px, 3vw, 28px);
        }
        
        .page-header .text-muted {
            font-size: clamp(10px, 1vw, 12px);
        }
        
        .section-title { 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-size: clamp(13px, 1.5vw, 16px); 
            margin-bottom: 20px; 
            border-bottom: 2px solid #111; 
            padding-bottom: 5px;
        }
        
        .table-custom { 
            background: #ffffff; 
            border: 1px solid #e5e5e5; 
            border-radius: 0; 
        }
        
        .table-custom th { 
            text-transform: uppercase; 
            font-size: clamp(9px, 0.9vw, 11px); 
            letter-spacing: 1px; 
            font-weight: 700; 
            color: #666; 
            background-color: #fafafa; 
            border-bottom: 2px solid #111111; 
            padding: clamp(8px, 1vw, 15px); 
        }
        
        .table-custom td { 
            padding: clamp(8px, 1vw, 15px); 
            vertical-align: middle; 
            border-bottom: 1px solid #eee; 
        }
        
        .form-control, .form-select { 
            border-radius: 0 !important; 
            border: 1px solid #a1a1a1; 
            padding: clamp(8px, 1vw, 10px); 
            font-size: clamp(13px, 1vw, 14px); 
        }
        
        .form-control:focus, .form-select:focus { 
            border-color: #111; 
            box-shadow: none; 
        }
        
        .product-img-mini { 
            width: clamp(40px, 5vw, 50px); 
            height: clamp(48px, 6vw, 60px); 
            object-fit: cover; 
            border: 1px solid #eee; 
        }
        
        .btn-asos-dark { 
            background-color: #111111; 
            color: #ffffff; 
            border: none; 
            border-radius: 0 !important; 
            padding: clamp(10px, 1.2vw, 12px) clamp(15px, 2vw, 25px); 
            font-size: clamp(11px, 1vw, 13px); 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            text-decoration: none; 
            display: inline-block; 
            transition: background-color 0.3s ease;
        }
        
        .btn-asos-dark:hover { 
            background-color: #2d2d2d; 
            color: #ffffff; 
        }
        
        .btn-outline-custom { 
            border: 1px solid #111111; 
            background: transparent; 
            color: #111111; 
            border-radius: 0 !important; 
            padding: clamp(4px, 0.5vw, 6px) clamp(6px, 0.8vw, 12px); 
            font-size: clamp(10px, 0.9vw, 12px); 
            text-transform: uppercase; 
            font-weight: 700; 
            text-decoration: none; 
            transition: all 0.2s ease;
        }
        
        .btn-outline-custom:hover { 
            background: #111111; 
            color: #ffffff; 
        }
        
        .badge-stock { 
            border-radius: 0; 
            font-size: clamp(8px, 0.8vw, 10px); 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.6vw, 8px); 
            text-transform: uppercase; 
            font-weight: 700; 
        }
        
        /* Style des Modales EMMA+ */
        .modal-content { 
            border-radius: 0 !important; 
            border: 1px solid #111; 
        }
        
        .modal-header { 
            background-color: #111; 
            color: #fff; 
            border-radius: 0; 
        }
        
        .modal-header .btn-close { 
            filter: invert(1); 
        }
        
        .modal-title {
            font-size: clamp(14px, 1.5vw, 18px);
        }
        
        /* Footer Institutionnel ASOS - Responsive */
        footer {
            background-color: whitesmoke;
            border-top: 1px solid #e5e5e5;
            font-size: clamp(12px, 1vw, 13px);
            color: #555555;
        }
        
        footer h6 {
            color: #111111;
            font-weight: 700;
            text-transform: uppercase;
            font-size: clamp(11px, 1vw, 13px);
            letter-spacing: 1px;
            margin-bottom: 22px;
        }
        
        footer a {
            color: #666666;
            text-decoration: none;
        }
        
        footer a:hover {
            color: #111111;
            text-decoration: underline;
        }
        
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
        }
        
        /* RESPONSIVE - Tablette */
        @media (max-width: 991px) {
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                gap: 12px;
                align-items: stretch !important;
            }
            
            .d-flex.justify-content-between.align-items-center.mb-4 .section-title {
                text-align: center;
            }
            
            .d-flex.justify-content-between.align-items-center.mb-4 .btn-asos-dark {
                text-align: center;
                width: 100%;
            }
            
            .btn-asos-dark {
                width: 100%;
                text-align: center;
            }
        }
        
        /* RESPONSIVE - Mobile */
        @media (max-width: 576px) {
            .page-header {
                padding: 15px 0;
            }
            
            .page-header .container {
                padding: 0 15px;
            }
            
            .page-header h1 {
                font-size: 20px;
            }
            
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            /* Table responsive */
            .table-responsive {
                border: none;
            }
            
            .table-custom td {
                padding: 10px 8px;
            }
            
            .table-custom th {
                padding: 10px 8px;
                font-size: 9px;
            }
            
            .product-img-mini {
                width: 35px;
                height: 45px;
            }
            
            .table-custom td strong {
                font-size: 12px !important;
            }
            
            .table-custom td .text-muted {
                font-size: 10px !important;
            }
            
            .d-flex.justify-content-center.gap-1 {
                flex-direction: column;
                gap: 4px !important;
            }
            
            .btn-outline-custom {
                padding: 4px 8px;
                font-size: 10px;
                display: inline-block;
                text-align: center;
                width: 100%;
            }
            
            .badge-stock {
                font-size: 8px;
                padding: 3px 6px;
            }
            
            /* Modales mobile */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px !important;
            }
            
            .modal-footer {
                flex-direction: column;
                gap: 8px;
            }
            
            .modal-footer .btn-asos-dark,
            .modal-footer .btn-outline-secondary {
                width: 100%;
                text-align: center;
            }
            
            .row .col-6 {
                padding-left: 6px;
                padding-right: 6px;
            }
            
            .form-control, .form-select {
                font-size: 14px;
                padding: 8px 10px;
            }
        }
        
        /* RESPONSIVE - Très petits écrans */
        @media (max-width: 380px) {
            .table-custom td,
            .table-custom th {
                padding: 6px 4px;
            }
            
            .product-img-mini {
                width: 28px;
                height: 38px;
            }
            
            .table-custom td strong {
                font-size: 10px !important;
            }
            
            .table-custom td .text-muted {
                font-size: 8px !important;
            }
            
            .btn-outline-custom {
                font-size: 8px;
                padding: 3px 5px;
            }
        }
        
        /* Alertes responsive */
        .alert {
            border-radius: 0 !important;
            padding: clamp(10px, 1.5vw, 16px) !important;
            font-size: clamp(12px, 1vw, 14px);
        }
        
        /* Ajustement des modales sur mobile */
        @media (max-width: 576px) {
            .modal-header h5 {
                font-size: 14px;
            }
            
            .modal-header .btn-close {
                font-size: 12px;
            }
        }
        
        /* Scroll horizontal pour la table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Amélioration des boutons d'action */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons .btn-outline-custom {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand" href="#">EMMA+ <span style="font-size: 12px; font-weight: 400; letter-spacing: 1px;">BACKOFFICE</span></a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-4 gap-3">
                    <a class="nav-link-admin" href="dashboard.php">Dashboard</a>
                    <a class="nav-link-admin" href="gestion_commandes.php">Commandes</a>
                </div>
            </div>
            
            <div class="ms-auto d-flex align-items-center gap-3 mt-3 mt-lg-0">
                <a href="../public/boutique.php" target="_blank" class="btn btn-outline-custom btn-sm">
                    <i class="bi bi-eye me-1"></i> <span class="d-none d-sm-inline">Voir la boutique</span>
                    <span class="d-inline d-sm-none">Boutique</span>
                </a>
                <a href="../public/deconnexion.php" class="btn btn-danger btn-sm rounded-0 text-white"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <div class="container">
            <span class="text-muted small text-uppercase" style="letter-spacing: 1px;">Catalogue</span>
            <h1 class="m-0 fw-black" style="font-weight: 900;">Gestion du Catalogue</h1>
        </div>
    </div>

    <div class="container mb-5">
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success border-0 rounded-0 small py-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 rounded-0 small py-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="section-title m-0 border-0 pb-0">Articles en ligne (<?= count($produits) ?>)</h2>
            <button type="button" class="btn-asos-dark" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-2"></i> Ajouter un produit
            </button>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (empty($produits)): ?>
                    <div class="text-center py-5 bg-white border">
                        <i class="bi bi-box-seam fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted small mb-0">Aucun produit trouvé dans la base de données.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle m-0">
                            <thead>
                                <tr>
                                    <th scope="col" style="min-width: 50px;">Image</th>
                                    <th scope="col" style="min-width: 100px;">Nom</th>
                                    <th scope="col" class="text-center" style="min-width: 60px;">Stock</th>
                                    <th scope="col" class="text-end" style="min-width: 80px;">Prix</th>
                                    <th scope="col" class="text-center" style="min-width: 70px;">Statut</th>
                                    <th scope="col" class="text-center" style="min-width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $prod): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                                $imgUrl = (strpos($prod['image'], 'http') === 0) ? $prod['image'] : '../../uploads/' . $prod['image'];
                                            ?>
                                            <img src="<?= htmlspecialchars($imgUrl) ?>" class="product-img-mini" alt="Img" loading="lazy">
                                        </td>
                                        <td>
                                            <strong style="font-size: clamp(12px, 1.2vw, 14px);" class="d-block"><?= htmlspecialchars($prod['nom']) ?></strong>
                                            <span class="text-muted d-none d-md-inline" style="font-size:clamp(10px, 0.9vw, 11px);"><?= htmlspecialchars(substr($prod['description'] ?? '', 0, 70)) ?>...</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($prod['stock'] > 5): ?>
                                                <span class="badge bg-success badge-stock"><?= $prod['stock'] ?></span>
                                            <?php elseif ($prod['stock'] > 0): ?>
                                                <span class="badge bg-warning text-dark badge-stock"><?= $prod['stock'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger badge-stock">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold" style="font-size: clamp(12px, 1vw, 13px); white-space: nowrap;"><?= number_format($prod['prix'], 0, ',', ' ') ?> F</td>
                                        <td class="text-center">
                                            <a href="gestion_produits.php?action=toggle_status&id=<?= $prod['id'] ?>" class="text-decoration-none">
                                                <?php if ($prod['actif']): ?>
                                                    <span class="badge bg-dark badge-stock" style="cursor: pointer;">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary badge-stock" style="cursor: pointer;">Masqué</span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-outline-custom edit-btn" style="padding: 4px 6px; font-size: clamp(10px, 0.8vw, 11px);" title="Modifier"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editModal"
                                                        data-id="<?= $prod['id'] ?>"
                                                        data-nom="<?= htmlspecialchars($prod['nom']) ?>"
                                                        data-cat="<?= $prod['categorie_id'] ?>"
                                                        data-prix="<?= $prod['prix'] ?>"
                                                        data-stock="<?= $prod['stock'] ?>"
                                                        data-desc="<?= htmlspecialchars($prod['description'] ?? '') ?>"
                                                        data-actif="<?= $prod['actif'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="gestion_produits.php?action=delete&id=<?= $prod['id'] ?>" class="btn-outline-custom text-danger border-danger" style="padding: 4px 6px; font-size: clamp(10px, 0.8vw, 11px);" title="Supprimer" onclick="return confirm('Voulez-vous vraiment supprimer ce produit ?');"><i class="bi bi-trash3"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODALE AJOUT -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-uppercase fw-bold fs-6" id="addModalLabel"><i class="bi bi-plus-square me-2"></i> Ajouter un produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gestion_produits.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Nom du produit *</label>
                            <input type="text" name="nom" class="form-control" placeholder="ex: Panier de Tomates Fraîches" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Catégorie</label>
                            <select name="categorie_id" class="form-select">
                                <option value="">-- Choisir une catégorie --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 mb-3 mb-sm-0">
                                <label class="form-label small fw-bold text-uppercase">Prix (FCFA) *</label>
                                <input type="number" name="prix" min="1" class="form-control" placeholder="5000" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">Stock Initial</label>
                                <input type="number" name="stock" min="0" class="form-control" value="10">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provenance, poids, détails..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Image du produit</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" name="actif" id="add-actif" checked style="cursor: pointer;">
                            <label class="form-check-label small fw-bold text-uppercase" for="add-actif" style="cursor: pointer;">Rendre le produit visible</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-0 text-uppercase fw-bold" style="font-size:clamp(11px, 1vw, 12px); padding:clamp(8px, 1vw, 10px) clamp(15px, 2vw, 20px);" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-asos-dark">Enregistrer le produit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODALE ÉDITION -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-uppercase fw-bold fs-6" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i> Modifier le produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gestion_produits.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Nom du produit *</label>
                            <input type="text" name="nom" id="edit-nom" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Catégorie</label>
                            <select name="categorie_id" id="edit-cat" class="form-select">
                                <option value="">-- Choisir une catégorie --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 mb-3 mb-sm-0">
                                <label class="form-label small fw-bold text-uppercase">Prix (FCFA) *</label>
                                <input type="number" name="prix" id="edit-prix" min="1" class="form-control" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">Stock</label>
                                <input type="number" name="stock" id="edit-stock" min="0" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <textarea name="description" id="edit-desc" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Changer l'image (Optionnel)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted" style="font-size: 11px;">Laissez vide pour conserver l'image actuelle.</small>
                        </div>

                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" name="actif" id="edit-actif" value="1" style="cursor: pointer;">
                            <label class="form-check-label small fw-bold text-uppercase" for="edit-actif" style="cursor: pointer;">Produit en ligne / visible</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-0 text-uppercase fw-bold" style="font-size:clamp(11px, 1vw, 12px); padding:clamp(8px, 1vw, 10px) clamp(15px, 2vw, 20px);" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn-asos-dark">Sauvegarder les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- FOOTER -->
    <footer class="py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h6>À propos de Emma+</h6>
                    <p class="lh-lg" style="color: #777;">Emma+ facilite votre quotidien en vous proposant le meilleur des marchés locaux directement sur votre table, livré avec soin par nos équipes.</p>
                </div>
                <div class="col-6 col-md-4 ps-md-5">
                    <h6>Liens Utiles</h6>
                    <ul class="list-unstyled lh-lg">
                        <li><a href="../public/boutique.php">Le Catalogue</a></li>
                        <li><a href="../public/cgu.php">Conditions d'Utilisation</a></li>
                        <li><a href="../public/inscription.php">Créer un compte</a></li>
                    </ul>
                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS pour la modale de modification rapide (Crayon)
        const editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit-id').value = this.getAttribute('data-id');
                document.getElementById('edit-nom').value = this.getAttribute('data-nom');
                document.getElementById('edit-cat').value = this.getAttribute('data-cat') || "";
                document.getElementById('edit-prix').value = this.getAttribute('data-prix');
                document.getElementById('edit-stock').value = this.getAttribute('data-stock');
                document.getElementById('edit-desc').value = this.getAttribute('data-desc');
                
                const isActif = this.getAttribute('data-actif') == "1";
                document.getElementById('edit-actif').checked = isActif;
            });
        });
    </script>
</body>
</html>
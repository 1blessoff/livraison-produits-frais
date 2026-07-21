<?php
// views/admin/gestion_commandes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

$message = "";
$status = "";

// Mise à jour du statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $commande_id = intval($_POST['commande_id'] ?? 0);
    $nouveau_statut = trim($_POST['statut'] ?? '');
    $statuts_autorises = ['en_attente', 'en_livraison', 'livree', 'annulee'];

    // ✅ Vérifier que la commande n'est pas déjà annulée
    $stmtCheck = $pdo->prepare("SELECT statut FROM commandes WHERE id = ?");
    $stmtCheck->execute([$commande_id]);
    $commandeActuelle = $stmtCheck->fetch();

    if ($commandeActuelle && $commandeActuelle['statut'] === 'annulee') {
        $status = "danger";
        $message = "Impossible de modifier une commande déjà annulée.";
    } elseif ($commande_id > 0 && in_array($nouveau_statut, $statuts_autorises)) {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmtUpdate->execute([$nouveau_statut, $commande_id]);

            // si annulation - restitue le stock
            if ($nouveau_statut === 'annulee') {
                $stmtStock = $pdo->prepare("
                    UPDATE produits p 
                    JOIN commande_items ci ON ci.produit_id = p.id
                    SET p.stock = p.stock + ci.quantite 
                    WHERE ci.commande_id = ?
                ");
                $stmtStock->execute([$commande_id]);
            }

            $status = "success";
            $message = "Statut de la commande #$commande_id mis à jour.";

            header("Location: gestion_commandes.php");
            exit();

        } catch (\PDOException $e) {
            $status = "danger";
            $message = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupération des commandes
try {
    $stmt = $pdo->query("
        SELECT c.id, c.total, c.statut, c.date_commande, u.nom, u.prenom, u.email 
        FROM commandes c
        LEFT JOIN users u ON c.user_id = u.id
        ORDER BY c.id DESC
    ");
    $commandes = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupération des articles pour chaque commande
$items_par_commande = [];
try {
    $stmtItems = $pdo->query("
        SELECT ci.commande_id, ci.produit_id, ci.quantite, ci.prix, 
               p.nom AS produit_nom, p.image AS produit_image
        FROM commande_items ci
        JOIN produits p ON ci.produit_id = p.id
    ");
    while ($item = $stmtItems->fetch()) {
        $items_par_commande[$item['commande_id']][] = $item;
    }
} catch (\PDOException $e) {
    $error_articles = "Erreur SQL : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes | Admin EMMA+</title>

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
            background: #f8f9fa; 
            font-family: 'Helvetica Neue', sans-serif; 
            overflow-x: hidden;
        }
        
        /* Navbar responsive */
        .navbar-brand { 
            font-weight: 900; 
            font-size: clamp(18px, 2.5vw, 24px); 
            letter-spacing: 2px; 
            color: #111 !important; 
        }
        
        .navbar-brand span {
            font-size: clamp(11px, 1vw, 14px);
        }
        
        @media (max-width: 576px) {
            .navbar .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .navbar .btn-outline-danger {
                font-size: 11px;
                padding: 4px 10px;
            }
        }
        
        /* Container principal responsive */
        .admin-container { 
            max-width: 1200px; 
            margin: clamp(15px, 3vw, 40px) auto; 
            background: white; 
            padding: clamp(15px, 3vw, 30px); 
            border: 1px solid #e5e5e5; 
        }
        
        .admin-container h2 {
            font-size: clamp(18px, 2.5vw, 24px);
        }
        
        /* Table responsive */
        .table th { 
            font-size: clamp(9px, 0.9vw, 11px); 
            font-weight: 700; 
            text-transform: uppercase; 
            background: #f1f1f1; 
            border-bottom: 2px solid #111; 
            padding: clamp(8px, 1vw, 12px);
        }
        
        .table td {
            padding: clamp(8px, 1vw, 12px);
            vertical-align: middle;
        }
        
        .badge-status { 
            font-size: clamp(8px, 0.8vw, 10px); 
            font-weight: 700; 
            text-transform: uppercase; 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px); 
            border-radius: 0; 
            display: inline-block;
            white-space: nowrap;
        }
        
        .status-en_attente { background: #ffeeba; color: #856404; }
        .status-en_livraison { background: #cff4fc; color: #055160; }
        .status-livree { background: #d1e7dd; color: #0f5132; }
        .status-annulee { background: #f8d7da; color: #842029; }
        
        .btn-update { 
            background: #111; 
            color: white; 
            border: none; 
            border-radius: 0; 
            font-size: clamp(10px, 0.9vw, 11px); 
            padding: clamp(4px, 0.5vw, 6px) clamp(8px, 1vw, 10px); 
            transition: background 0.3s ease;
        }
        
        .btn-update:hover {
            background: #333;
            color: white;
        }
        
        .btn-update:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-details { 
            border: 1px solid #111; 
            background: transparent; 
            border-radius: 0; 
            font-size: clamp(10px, 0.9vw, 11px); 
            font-weight: 700; 
            padding: clamp(3px, 0.4vw, 5px) clamp(8px, 1vw, 12px); 
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-details:hover { 
            background: #111; 
            color: white; 
        }
        
        .modal-content { 
            border-radius: 0; 
            border: 1px solid #111; 
        }
        
        .modal-header { 
            background: #111; 
            color: white; 
            border-radius: 0; 
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .modal-title {
            font-size: clamp(14px, 1.5vw, 18px);
        }
        
        .product-img-mini { 
            width: clamp(35px, 4vw, 50px); 
            height: clamp(42px, 5vw, 60px); 
            object-fit: cover; 
            border: 1px solid #eee; 
        }
        
        .form-select-sm { 
            width: auto; 
            min-width: clamp(100px, 12vw, 150px); 
            border-radius: 0; 
            font-size: clamp(11px, 0.9vw, 12px);
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px);
        }
        
        /* RESPONSIVE - Tablette */
        @media (max-width: 991px) {
            .admin-container {
                margin: 15px 10px;
                padding: 20px 15px;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Masquer certaines colonnes sur tablette */
            .table th:nth-child(3),
            .table td:nth-child(3) {
                display: none;
            }
        }
        
        /* RESPONSIVE - Mobile */
        @media (max-width: 767px) {
            .admin-container {
                margin: 10px 5px;
                padding: 15px 10px;
                border: none;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            
            .table {
                font-size: 12px;
            }
            
            .table td {
                padding: 8px 5px;
            }
            
            .table th {
                padding: 8px 5px;
                font-size: 8px;
            }
            
            /* Colonnes masquées sur mobile */
            .table th:nth-child(2) small,
            .table td:nth-child(2) small {
                display: none !important;
            }
            
            .table th:nth-child(3),
            .table td:nth-child(3) {
                display: none !important;
            }
            
            .table th:nth-child(6),
            .table td:nth-child(6) {
                display: none !important;
            }
            
            /* Boutons d'action en colonne */
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 4px !important;
                align-items: stretch;
            }
            
            .form-select-sm {
                min-width: 100%;
                width: 100%;
                font-size: 12px;
            }
            
            .btn-update {
                width: 100%;
                padding: 6px;
                font-size: 12px;
            }
            
            .btn-details {
                width: 100%;
                text-align: center;
                padding: 6px 8px;
                font-size: 10px;
            }
            
            /* Numéro de commande */
            .table td:first-child {
                font-size: 12px;
            }
            
            /* Total */
            .table td:nth-child(4) {
                font-size: 12px;
            }
        }
        
        /* RESPONSIVE - Très petits écrans */
        @media (max-width: 400px) {
            .admin-container {
                padding: 10px 5px;
            }
            
            .table {
                font-size: 10px;
            }
            
            .table td {
                padding: 4px 3px;
            }
            
            .table th {
                padding: 4px 3px;
                font-size: 7px;
            }
            
            .badge-status {
                font-size: 7px;
                padding: 2px 4px;
            }
            
            .btn-details {
                font-size: 8px;
                padding: 4px 6px;
            }
            
            .product-img-mini {
                width: 25px;
                height: 35px;
            }
        }
        
        /* Modale responsive */
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px !important;
            }
            
            .modal-body .table-sm {
                font-size: 11px;
            }
            
            .modal-body .table-sm td {
                padding: 6px 4px;
            }
            
            .modal-footer .btn {
                width: 100%;
                margin: 2px 0;
            }
        }
        
        /* Alertes responsive */
        .alert {
            border-radius: 0 !important;
            padding: clamp(10px, 1.5vw, 16px) !important;
            font-size: clamp(12px, 1vw, 14px);
        }
        
        /* Lien retour */
        .mt-4.pt-3.border-top a {
            font-size: clamp(11px, 1vw, 13px);
        }
        
        /* Colonnes avec largeur minimale pour éviter l'écrasement */
        .table th:first-child,
        .table td:first-child {
            min-width: 80px;
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 80px;
        }
        
        .table th:nth-child(5),
        .table td:nth-child(5) {
            min-width: 80px;
        }
        
        .table th:last-child,
        .table td:last-child {
            min-width: 160px;
        }
        
        @media (max-width: 767px) {
            .table th:first-child,
            .table td:first-child {
                min-width: 50px;
            }
            
            .table th:nth-child(4),
            .table td:nth-child(4) {
                min-width: 60px;
            }
            
            .table th:nth-child(5),
            .table td:nth-child(5) {
                min-width: 60px;
            }
            
            .table th:last-child,
            .table td:last-child {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>

<header class="navbar navbar-light bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">EMMA+ <span class="text-muted">| Admin</span></a>
        <a href="../public/deconnexion.php" class="btn btn-sm btn-outline-danger rounded-0">Déconnexion</a>
    </div>
</header>

<div class="container">
    <div class="admin-container shadow-sm">
        <h2 class="fw-bold mb-4"><i class="bi bi-box-seam me-2"></i> Gestion des Commandes</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $status ?> rounded-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle border">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client</th>
                        <th class="d-none d-md-table-cell">Date</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th class="d-none d-md-table-cell">Détails</th>
                        <th>Modifier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commandes)): ?>
                        <tr><td colspan="7" class="text-center py-5">Aucune commande</td></tr>
                    <?php else: ?>
                        <?php foreach ($commandes as $cmd): 
                            $badgeClass = 'status-en_attente';
                            $statut_affichage = 'En attente';
                            
                            if ($cmd['statut'] === 'en_livraison') {
                                $badgeClass = 'status-en_livraison';
                                $statut_affichage = 'En livraison';
                            } elseif ($cmd['statut'] === 'livree') {
                                $badgeClass = 'status-livree';
                                $statut_affichage = 'Livrée';
                            } elseif ($cmd['statut'] === 'annulee') {
                                $badgeClass = 'status-annulee';
                                $statut_affichage = 'Annulée';
                            }
                        ?>
                            <tr>
                                <td class="fw-bold">#<?= $cmd['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? 'Inconnu')) ?><br>
                                    <small class="text-muted d-none d-sm-inline"><?= htmlspecialchars($cmd['email'] ?? '') ?></small>
                                </td>
                                <td class="d-none d-md-table-cell"><?= isset($cmd['date_commande']) ? date('d/m/Y H:i', strtotime($cmd['date_commande'])) : 'Date inconnue' ?></td>
                                <td class="fw-bold"><?= number_format($cmd['total'], 0, ',', ' ') ?> FCFA</td>
                                <td><span class="badge-status <?= $badgeClass ?>"><?= $statut_affichage ?></span></td>
                                <td class="d-none d-md-table-cell">
                                    <button class="btn-details" data-bs-toggle="modal" data-bs-target="#modal<?= $cmd['id'] ?>">
                                        <i class="bi bi-eye"></i> <span class="d-none d-lg-inline">Articles</span>
                                    </button>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-2 flex-wrap flex-sm-nowrap">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="commande_id" value="<?= $cmd['id'] ?>">
                                        
                                        <?php if ($cmd['statut'] === 'annulee'): ?>
                                            <!-- ✅ Commande annulée : select désactivé -->
                                            <select name="statut" class="form-select form-select-sm" disabled>
                                                <option value="annulee" selected>Annulée</option>
                                            </select>
                                            <input type="hidden" name="statut" value="annulee">
                                            <button class="btn-update" type="button" disabled>
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php else: ?>
                                            <!-- ✅ Commande active : modifiable -->
                                            <select name="statut" class="form-select form-select-sm">
                                                <option value="en_attente" <?= $cmd['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                                <option value="en_livraison" <?= $cmd['statut'] === 'en_livraison' ? 'selected' : '' ?>>En livraison</option>
                                                <option value="livree" <?= $cmd['statut'] === 'livree' ? 'selected' : '' ?>>Livrée</option>
                                                <option value="annulee" <?= $cmd['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                            </select>
                                            <button class="btn-update" type="submit"><i class="bi bi-check-lg"></i></button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 pt-3 border-top">
        <a href="dashboard.php" class="text-dark text-decoration-none small fw-bold text-uppercase">
            <i class="bi bi-arrow-left me-2"></i>Retour au tableau de bord
        </a>
    </div>
</div>

<!-- MODALES -->
<?php foreach ($commandes as $cmd): ?>
<div class="modal fade" id="modal<?= $cmd['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Commande N°<?= $cmd['id'] ?> - Articles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <?php if (!empty($items_par_commande[$cmd['id']])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:50px;">Image</th>
                                    <th>Produit</th>
                                    <th class="text-center" style="min-width:50px;">Qté</th>
                                    <th class="text-end" style="min-width:80px;">Prix unitaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_par_commande[$cmd['id']] as $item): 
                                    $img = '../../uploads/' . htmlspecialchars($item['produit_image']);
                                    if (!file_exists(__DIR__ . '/../../uploads/' . $item['produit_image']) || empty($item['produit_image'])) {
                                        $img = '../../uploads/default_product.jpg';
                                    }
                                ?>
                                    <tr>
                                        <td><img src="<?= $img ?>" class="product-img-mini" loading="lazy"></td>
                                        <td><?= htmlspecialchars($item['produit_nom']) ?></td>
                                        <td class="text-center">x<?= $item['quantite'] ?></td>
                                        <td class="text-end"><?= number_format($item['prix'], 0, ',', ' ') ?> FCFA</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                    <td class="text-end fw-bold"><?= number_format($cmd['total'], 0, ',', ' ') ?> FCFA</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted my-3">Aucun article trouvé</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-0 text-uppercase fw-bold" style="font-size:clamp(11px, 1vw, 12px); padding:clamp(8px, 1vw, 10px) clamp(15px, 2vw, 20px);" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
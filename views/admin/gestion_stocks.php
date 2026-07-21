<?php
// views/admin/gestion_stocks.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/connexion.php');
    exit();
}


require_once __DIR__ . '/../../config/database.php';

$message = "";
$error = "";
$search = $_GET['search'] ?? '';
$filtre_stock = $_GET['filtre'] ?? 'tous';

// Mise à jour rapide du stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $id_produit = intval($_POST['id'] ?? 0);
    $nouveau_stock = intval($_POST['stock'] ?? 0);
    
    if ($id_produit > 0 && $nouveau_stock >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE produits SET stock = ? WHERE id = ?");
            $stmt->execute([$nouveau_stock, $id_produit]);
            $message = "Stock mis à jour !";
        } catch (\PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupération des produits avec filtres
try {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND p.nom LIKE ?";
        $params[] = "%$search%";
    }
    
    if ($filtre_stock === 'rupture') {
        $sql .= " AND p.stock = 0";
    } elseif ($filtre_stock === 'faible') {
        $sql .= " AND p.stock > 0 AND p.stock < 10";
    } elseif ($filtre_stock === 'suffisant') {
        $sql .= " AND p.stock >= 10";
    }
    
    $sql .= " ORDER BY p.stock ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll();
    
    // Stats
    $total_produits = count($produits);
    $rupture = count(array_filter($produits, fn($p) => $p['stock'] == 0));
    $faible = count(array_filter($produits, fn($p) => $p['stock'] > 0 && $p['stock'] < 10));
    $suffisant = $total_produits - $rupture - $faible;
    
} catch (\PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stocks_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nom', 'Catégorie', 'Stock', 'Prix', 'Statut']);
    
    foreach ($produits as $prod) {
        $statut = $prod['stock'] == 0 ? 'Rupture' : ($prod['stock'] < 10 ? 'Faible' : 'Suffisant');
        fputcsv($output, [
            $prod['id'],
            $prod['nom'],
            $prod['categorie_nom'] ?? '-',
            $prod['stock'],
            $prod['prix'],
            $statut
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Stocks | EMMA+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Helvetica Neue', sans-serif; }
        .admin-container { max-width: 1300px; margin: 40px auto; background: white; padding: 30px; border: 1px solid #e5e5e5; }
        .table th { font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f1f1f1; border-bottom: 2px solid #111; }
        .statut-rupture { background: #f8d7da; color: #842029; font-weight: bold; }
        .statut-faible { background: #ffeeba; color: #856404; font-weight: bold; }
        .statut-suffisant { background: #d1e7dd; color: #0f5132; }
        .btn-update-stock { background: #111; color: white; border: none; border-radius: 0; font-size: 11px; padding: 4px 10px; }
        .stat-card { background: white; border: 1px solid #e5e5e5; padding: 20px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: 900; }
    </style>
</head>
<body>

<header class="navbar navbar-light bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">EMMA+ <span class="fs-6 text-muted">| Admin</span></a>
        <div class="d-flex gap-3">
            <a href="gestion_produits.php" class="nav-link">Produits</a>
            <a href="gestion_commandes.php" class="nav-link">Commandes</a>
            <a href="gestion_stocks.php" class="nav-link fw-bold border-bottom border-dark">Stocks</a>
            <a href="../public/deconnexion.php" class="btn btn-sm btn-outline-danger rounded-0">Déconnexion</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="admin-container shadow-sm">
        <h2 class="h4 fw-bold mb-4"><i class="bi bi-box-seam me-2"></i> Gestion des Stocks</h2>

        <?php if ($message): ?>
            <div class="alert alert-success rounded-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_produits ?></div>
                    <div class="text-muted small">Total produits</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-danger"><?= $rupture ?></div>
                    <div class="text-muted small">En rupture</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?= $faible ?></div>
                    <div class="text-muted small">Stock faible</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?= $suffisant ?></div>
                    <div class="text-muted small">Stock suffisant</div>
                </div>
            </div>
        </div>

        <!-- FILTRES ET RECHERCHE -->
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-5">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control rounded-0" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-dark rounded-0"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <a href="?filtre=tous&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filtre_stock === 'tous' ? 'btn-dark' : 'btn-outline-dark' ?> rounded-0">Tous</a>
                    <a href="?filtre=rupture&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filtre_stock === 'rupture' ? 'btn-dark' : 'btn-outline-dark' ?> rounded-0">Rupture</a>
                    <a href="?filtre=faible&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filtre_stock === 'faible' ? 'btn-dark' : 'btn-outline-dark' ?> rounded-0">Faible</a>
                    <a href="?filtre=suffisant&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filtre_stock === 'suffisant' ? 'btn-dark' : 'btn-outline-dark' ?> rounded-0">Suffisant</a>
                </div>
            </div>
            <div class="col-md-3 text-end">
                <a href="?export=csv&search=<?= urlencode($search) ?>&filtre=<?= $filtre_stock ?>" class="btn btn-outline-success rounded-0">
                    <i class="bi bi-download"></i> Exporter CSV
                </a>
            </div>
        </div>

        <!-- TABLEAU DES STOCKS -->
        <div class="table-responsive">
            <table class="table align-middle border">
                <thead>
                    <tr>
                        <th>ID</th><th>Produit</th><th>Catégorie</th><th>Stock</th><th>Prix</th><th>Statut</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produits)): ?>
                        <tr><td colspan="7" class="text-center py-5">Aucun produit trouvé</td></tr>
                    <?php else: ?>
                        <?php foreach ($produits as $prod): 
                            $statut_class = '';
                            $statut_texte = '';
                            if ($prod['stock'] == 0) {
                                $statut_class = 'statut-rupture';
                                $statut_texte = 'RUPTURE';
                            } elseif ($prod['stock'] < 10) {
                                $statut_class = 'statut-faible';
                                $statut_texte = 'Stock faible';
                            } else {
                                $statut_class = 'statut-suffisant';
                                $statut_texte = 'Suffisant';
                            }
                        ?>
                            <tr>
                                <td><?= $prod['id'] ?></td>
                                <td><strong><?= htmlspecialchars($prod['nom']) ?></strong></td>
                                <td><?= htmlspecialchars($prod['categorie_nom'] ?? '-') ?></td>
                                <td class="fw-bold"><?= $prod['stock'] ?></td>
                                <td><?= number_format($prod['prix'], 0, ',', ' ') ?> FCFA</td>
                                <td><span class="badge <?= $statut_class ?> rounded-0"><?= $statut_texte ?></span></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                        <input type="number" name="stock" value="<?= $prod['stock'] ?>" class="form-control form-control-sm rounded-0" style="width: 80px;" min="0">
                                        <button type="submit" class="btn-update-stock"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
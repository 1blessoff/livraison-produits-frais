<?php
// 1. SÉCURISATION DE L'ACCÈS
session_start();
require_once __DIR__ . '/../../config/database.php';

// Vérification admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /403.php');
    exit();
}

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================

$message = '';
$message_type = '';

// Ajouter un témoignage (admin manuellement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $user_id = (int)$_POST['user_id'];
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $message_text = trim($_POST['message']);
    $note = (int)$_POST['note'];
    $visible = isset($_POST['visible']) ? 1 : 0;
    
    if (empty($nom) || empty($prenom) || empty($message_text)) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO temoignages (user_id, nom, prenom, message, note, visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $nom, $prenom, $message_text, $note, $visible]);
            $message = "Témoignage ajouté avec succès !";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Modifier un témoignage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $message_text = trim($_POST['message']);
    $note = (int)$_POST['note'];
    $visible = isset($_POST['visible']) ? 1 : 0;
    
    if (empty($nom) || empty($prenom) || empty($message_text)) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE temoignages SET nom = ?, prenom = ?, message = ?, note = ?, visible = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $message_text, $note, $visible, $id]);
            $message = "Témoignage modifié avec succès !";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Supprimer un témoignage
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM temoignages WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Témoignage supprimé !";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "danger";
    }
}

// Changer la visibilité (AJAX)
if (isset($_GET['toggle_visible']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE temoignages SET visible = NOT visible WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Visibilité modifiée.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================

// Filtres
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM temoignages WHERE 1=1";
$params = [];

if ($filter === 'visible') {
    $sql .= " AND visible = 1";
} elseif ($filter === 'hidden') {
    $sql .= " AND visible = 0";
} elseif ($filter === 'high') {
    $sql .= " AND note >= 4";
} elseif ($filter === 'low') {
    $sql .= " AND note <= 2";
}

if (!empty($search)) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$temoignages = $stmt->fetchAll();

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN visible = 1 THEN 1 ELSE 0 END) as visibles,
        SUM(CASE WHEN visible = 0 THEN 1 ELSE 0 END) as caches,
        ROUND(AVG(note), 1) as note_moyenne
    FROM temoignages
")->fetch();

// Récupérer les clients pour le formulaire d'ajout
$clients = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE role = 'client' ORDER BY nom")->fetchAll();

// Récupérer un témoignage pour modification si edit_id est passé
$edit_temoignage = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM temoignages WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_temoignage = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des témoignages | EMMA+</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #fcfcfc;
            color: #111111;
        }
        
        .admin-sidebar {
            background-color: #111111;
            color: #ffffff;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background: #111;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 20px;
        }
        
        .sidebar-brand {
            font-weight: 900;
            font-size: 22px;
            letter-spacing: 3px;
            text-transform: uppercase;
            padding: 25px 20px;
            border-bottom: 1px solid #2d2d2d;
            text-align: center;
        }
        
        .sidebar-brand a {
            color: white;
            text-decoration: none;
        }
        
        .nav-admin .nav-link {
            color: #aaaaaa;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px;
            border-bottom: 1px solid #1a1a1a;
            transition: all 0.2s ease;
        }
        
        .nav-admin .nav-link:hover, 
        .nav-admin .nav-link.active {
            color: #ffffff;
            background-color: #222222;
        }
        
        .nav-admin .nav-link i {
            margin-right: 12px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            padding: 40px;
            transition: all 0.3s ease;
        }
        
        .page-title {
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            padding: 20px 25px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 900;
            color: #111111;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #666666;
            letter-spacing: 1px;
        }
        
        .filter-btn {
            border: 1px solid #e5e5e5;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            color: #111;
            transition: all 0.2s ease;
            display: inline-block;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background-color: #111;
            color: #fff;
        }
        
        .testimonial-card {
            background: white;
            border: 1px solid #e5e5e5;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .testimonial-text {
            font-style: italic;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .stars {
            color: #ffc107;
            font-size: 14px;
        }
        
        .badge-visible {
            background-color: #28a745;
            color: white;
            font-size: 10px;
            padding: 3px 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-hidden {
            background-color: #dc3545;
            color: white;
            font-size: 10px;
            padding: 3px 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-asos-sm {
            background-color: #111111;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 0 !important;
            padding: 8px 15px;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-asos-sm:hover {
            background-color: #333333;
            color: #ffffff;
        }
        
        .btn-asos-outline-sm {
            background-color: transparent;
            color: #111111;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 0 !important;
            padding: 7px 14px;
            border: 1px solid #111111;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-asos-outline-sm:hover {
            background-color: #111111;
            color: #ffffff;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 997;
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
            
            .admin-sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                z-index: 998;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            
            .admin-sidebar.open {
                left: 0;
            }
            
            .main-content {
                padding: 20px 15px;
                margin-top: 50px;
            }
            
            .page-title {
                font-size: 18px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .filter-btn {
                font-size: 10px;
                padding: 6px 12px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 10px;
                margin-top: 60px;
            }
            
            .filter-btn {
                font-size: 9px;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>

<button class="sidebar-toggle" id="sidebarToggle">
    <i class="bi bi-list"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="container-fluid">
    <div class="row">
        
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0 admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <a href="dashboard.php">EMMA+</a>
            </div>
            <ul class="nav flex-column nav-admin">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_produits.php">
                        <i class="bi bi-box-seam"></i> Produits
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_stocks.php">
                        <i class="bi bi-graph-down"></i> Stocks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_commandes.php">
                        <i class="bi bi-receipt"></i> Commandes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="gestion_temoignages.php">
                        <i class="bi bi-chat-quote"></i> Témoignages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_utilisateurs.php">
                        <i class="bi bi-people"></i> Utilisateurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="modifier_profil_admin.php">
                        <i class="bi bi-person-circle"></i> Mon profil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../public/deconnexion.php">
                        <i class="bi bi-box-arrow-left"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>

        <!-- Contenu Principal -->
        <div class="col-md-9 col-lg-10 main-content">
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h1 class="page-title mb-0">Gestion des témoignages</h1>
                <button type="button" class="btn btn-dark rounded-0" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Ajouter un témoignage
                </button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show rounded-0" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-3">
                    <div class="stat-card shadow-sm">
                        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card shadow-sm border-success">
                        <div class="stat-value text-success"><?= $stats['visibles'] ?? 0 ?></div>
                        <div class="stat-label">Visibles</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card shadow-sm border-danger">
                        <div class="stat-value text-danger"><?= $stats['caches'] ?? 0 ?></div>
                        <div class="stat-label">Cachés</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="stat-card shadow-sm border-warning">
                        <div class="stat-value text-warning"><?= $stats['note_moyenne'] ?? '0.0' ?></div>
                        <div class="stat-label">Note moyenne</div>
                    </div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="gestion_temoignages.php" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="gestion_temoignages.php?filter=visible" class="filter-btn <?= $filter === 'visible' ? 'active' : '' ?>">Visibles</a>
                <a href="gestion_temoignages.php?filter=hidden" class="filter-btn <?= $filter === 'hidden' ? 'active' : '' ?>">Cachés</a>
                <a href="gestion_temoignages.php?filter=high" class="filter-btn <?= $filter === 'high' ? 'active' : '' ?>">4+ étoiles</a>
                <a href="gestion_temoignages.php?filter=low" class="filter-btn <?= $filter === 'low' ? 'active' : '' ?>">1-2 étoiles</a>
                
                <form method="GET" class="d-flex ms-auto">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control form-control-sm rounded-0" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>" style="width: 200px;">
                    <button type="submit" class="btn btn-dark btn-sm rounded-0">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>

            <!-- Liste des témoignages -->
            <?php if (!empty($temoignages)): ?>
                <?php foreach ($temoignages as $t): ?>
                    <div class="testimonial-card shadow-sm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; flex-shrink: 0;">
                                        <span class="fw-bold"><?= strtoupper(substr($t['prenom'], 0, 1) . substr($t['nom'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($t['prenom'] . ' ' . $t['nom']) ?></h6>
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $t['note']): ?>
                                                    <i class="bi bi-star-fill"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span class="text-muted small ms-1">(<?= $t['note'] ?>/5)</span>
                                        </div>
                                    </div>
                                    <span class="<?= $t['visible'] ? 'badge-visible' : 'badge-hidden' ?> ms-auto">
                                        <?= $t['visible'] ? '✓ Visible' : '✗ Caché' ?>
                                    </span>
                                </div>
                                <p class="testimonial-text mb-2">"<?= htmlspecialchars($t['message']) ?>"</p>
                                <small class="text-muted">Posté le <?= date('d/m/Y à H:i', strtotime($t['created_at'])) ?></small>
                            </div>
                            <div class="col-md-4 text-end d-flex flex-column justify-content-center gap-2 mt-3 mt-md-0">
                                <div>
                                    <a href="gestion_temoignages.php?toggle_visible=1&id=<?= $t['id'] ?>" class="btn-asos-outline-sm">
                                        <i class="bi bi-eye<?= $t['visible'] ? '-slash' : '' ?> me-1"></i>
                                        <?= $t['visible'] ? 'Cacher' : 'Afficher' ?>
                                    </a>
                                    <a href="gestion_temoignages.php?edit_id=<?= $t['id'] ?>" class="btn-asos-outline-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $t['id'] ?>">
                                        <i class="bi bi-pencil me-1"></i> Modifier
                                    </a>
                                    <a href="gestion_temoignages.php?delete=<?= $t['id'] ?>" class="btn-asos-outline-sm text-danger border-danger" onclick="return confirm('Supprimer ce témoignage ?')">
                                        <i class="bi bi-trash me-1"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Modification -->
                    <div class="modal fade" id="editModal<?= $t['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content rounded-0">
                                <div class="modal-header">
                                    <h5 class="modal-title">Modifier le témoignage</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nom</label>
                                                <input type="text" name="nom" class="form-control rounded-0" value="<?= htmlspecialchars($t['nom']) ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénom</label>
                                                <input type="text" name="prenom" class="form-control rounded-0" value="<?= htmlspecialchars($t['prenom']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Message</label>
                                            <textarea name="message" class="form-control rounded-0" rows="4" required><?= htmlspecialchars($t['message']) ?></textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Note (1-5)</label>
                                                <select name="note" class="form-select rounded-0">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <option value="<?= $i ?>" <?= $t['note'] == $i ? 'selected' : '' ?>><?= $i ?> étoile<?= $i > 1 ? 's' : '' ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check mt-4">
                                                    <input type="checkbox" name="visible" class="form-check-input" id="edit_visible_<?= $t['id'] ?>" <?= $t['visible'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="edit_visible_<?= $t['id'] ?>">Visible sur le site</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-dark rounded-0">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 border">
                    <i class="bi bi-chat-quote fs-1 text-muted d-block mb-3"></i>
                    <p class="text-muted">Aucun témoignage trouvé.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Ajout -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un témoignage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client</label>
                        <select name="user_id" class="form-select rounded-0">
                            <option value="0">Client non connecté</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?> (<?= htmlspecialchars($client['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control rounded-0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control rounded-0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control rounded-0" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Note (1-5)</label>
                            <select name="note" class="form-select rounded-0">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == 5 ? 'selected' : '' ?>><?= $i ?> étoile<?= $i > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="visible" class="form-check-input" id="add_visible" checked>
                                <label class="form-check-label" for="add_visible">Visible sur le site</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-dark rounded-0">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle sidebar sur mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function openSidebar() {
        adminSidebar.classList.add('open');
        sidebarOverlay.style.display = 'block';
    }
    
    function closeSidebar() {
        adminSidebar.classList.remove('open');
        sidebarOverlay.style.display = 'none';
    }
    
    sidebarToggle.addEventListener('click', openSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);
    
    const sidebarLinks = document.querySelectorAll('.nav-admin .nav-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
</script>
</body>
</html>
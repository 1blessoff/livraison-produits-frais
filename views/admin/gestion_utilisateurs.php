<?php
// views/admin/gestion_utilisateurs.php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/connexion.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$message = "";
$error = "";
$search = $_GET['search'] ?? '';

// Activer/Désactiver un utilisateur
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("UPDATE users SET actif = NOT actif WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Statut de l'utilisateur mis à jour";
        header("Location: gestion_utilisateurs.php?search=" . urlencode($search));
        exit();
    } catch (\PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// ✅ AJOUT : Changer le rôle d'un utilisateur
if (isset($_GET['action']) && $_GET['action'] === 'change_role' && isset($_GET['id']) && isset($_GET['role'])) {
    $user_id = intval($_GET['id']);
    $nouveau_role = trim($_GET['role']);
    $roles_autorises = ['client', 'livreur', 'admin'];
    
    if (in_array($nouveau_role, $roles_autorises)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$nouveau_role, $user_id]);
            $message = "Rôle de l'utilisateur mis à jour";
            header("Location: gestion_utilisateurs.php?search=" . urlencode($search));
            exit();
        } catch (\PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Rôle invalide";
    }
}

// Récupération des utilisateurs
try {
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM commandes WHERE user_id = u.id) as nb_commandes,
            (SELECT SUM(total) FROM commandes WHERE user_id = u.id) as total_achats
            FROM users u 
            WHERE 1=1";
    
    if (!empty($search)) {
        $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }
    
    $sql .= " ORDER BY u.id DESC";
    
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $utilisateurs = $stmt->fetchAll();
    
    // Statistiques
    $total_clients = count($utilisateurs);
    $clients_actifs = $total_clients;
    $clients_inactifs = 0;

    if (isset($utilisateurs[0]['actif'])) {
        $clients_actifs = count(array_filter($utilisateurs, function($u){
            return isset($u['actif']) && $u['actif'] == 1;
        }));
        $clients_inactifs = $total_clients - $clients_actifs;
    }
    
    $total_commandes = array_sum(array_map(function ($u){
        return $u['nb_commandes'];
    }, $utilisateurs));

    $ca_total = array_sum(array_map(function ($u){
        return $u['total_achats'];
    }, $utilisateurs));
    
} catch (\PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Rôle', 'Commandes', 'Total achats', 'Date inscription', 'Statut']);
    
    foreach ($utilisateurs as $user) {
        fputcsv($output, [
            $user['id'],
            $user['nom'],
            $user['prenom'],
            $user['email'],
            $user['telephone'] ?? '-',
            $user['role'] ?? 'client',
            $user['nb_commandes'],
            number_format($user['total_achats'], 0, ',', ' ') . ' FCFA',
            date('d/m/Y', strtotime($user['created_at'] ?? 'now')),
            isset($user['actif']) && $user['actif'] == 1 ? 'Actif' : 'Inactif'
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients | Admin EMMA+</title>
    
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
            max-width: 1400px; 
            margin: clamp(15px, 3vw, 40px) auto; 
            background: white; 
            padding: clamp(15px, 3vw, 30px); 
            border: 1px solid #e5e5e5; 
        }
        
        .admin-container h2 {
            font-size: clamp(18px, 2.5vw, 24px);
        }
        
        /* Cartes statistiques responsive */
        .stat-card { 
            background: white; 
            border: 1px solid #e5e5e5; 
            padding: clamp(15px, 2vw, 20px); 
            text-align: center; 
        }
        
        .stat-number { 
            font-size: clamp(22px, 3.5vw, 28px); 
            font-weight: 900; 
        }
        
        .stat-card .text-muted {
            font-size: clamp(10px, 1vw, 12px);
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 12px 10px;
            }
            
            .stat-number {
                font-size: 20px;
            }
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
        
        /* Badges */
        .badge-actif { 
            background: #d1e7dd; 
            color: #0f5132; 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px); 
            font-size: clamp(8px, 0.8vw, 10px); 
            font-weight: 700; 
            display: inline-block;
        }
        
        .badge-inactif { 
            background: #f8d7da; 
            color: #842029; 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px); 
            font-size: clamp(8px, 0.8vw, 10px); 
            font-weight: 700; 
            display: inline-block;
        }
        
        .badge-role { 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px); 
            font-size: clamp(8px, 0.8vw, 10px); 
            font-weight: 700; 
            border-radius: 0; 
            display: inline-block;
        }
        
        .badge-admin { background: #111; color: white; }
        .badge-livreur { background: #17a2b8; color: white; }
        .badge-client { background: #6c757d; color: white; }
        
        /* Boutons */
        .btn-sm-custom { 
            border: 1px solid #111; 
            background: transparent; 
            border-radius: 0; 
            font-size: clamp(9px, 0.8vw, 11px); 
            padding: clamp(3px, 0.4vw, 5px) clamp(6px, 0.8vw, 10px); 
            text-decoration: none; 
            display: inline-block; 
            margin-bottom: 2px; 
            transition: all 0.3s ease;
        }
        
        .btn-sm-custom:hover { 
            background: #111; 
            color: white; 
        }
        
        .btn-asos-sm { 
            background: #111; 
            color: white; 
            border: none; 
            border-radius: 0; 
            font-size: clamp(10px, 0.9vw, 11px); 
            padding: clamp(6px, 0.8vw, 8px) clamp(10px, 1.5vw, 15px); 
            text-decoration: none; 
            display: inline-block; 
            transition: background 0.3s ease;
        }
        
        .btn-asos-sm:hover { 
            background: #333; 
            color: white; 
        }
        
        .btn-outline-success { 
            border: 1px solid #198754; 
            background: transparent; 
            border-radius: 0; 
            padding: clamp(6px, 0.8vw, 8px) clamp(10px, 1.5vw, 15px); 
            text-decoration: none; 
            display: inline-block; 
            font-size: clamp(10px, 0.9vw, 11px);
            transition: all 0.3s ease;
        }
        
        .btn-outline-success:hover { 
            background: #198754; 
            color: white; 
        }
        
        .form-control { 
            border-radius: 0; 
            border: 1px solid #ccc; 
            padding: clamp(6px, 0.8vw, 8px) clamp(8px, 1vw, 12px); 
            font-size: clamp(13px, 1vw, 14px);
        }
        
        /* Actions des rôles */
        .role-actions { 
            display: flex; 
            gap: 3px; 
            flex-wrap: wrap; 
        }
        
        .role-actions .btn-sm-custom { 
            font-size: clamp(8px, 0.7vw, 9px); 
            padding: clamp(2px, 0.3vw, 4px) clamp(4px, 0.5vw, 6px); 
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
            
            .table th:nth-child(6),
            .table td:nth-child(6) {
                display: none;
            }
            
            .table th:nth-child(5),
            .table td:nth-child(5) {
                min-width: 100px;
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
                font-size: 11px;
            }
            
            .table td {
                padding: 6px 4px;
            }
            
            .table th {
                padding: 6px 4px;
                font-size: 8px;
            }
            
            /* Colonnes masquées sur mobile */
            .table th:nth-child(1),
            .table td:nth-child(1) {
                display: none;
            }
            
            .table th:nth-child(3),
            .table td:nth-child(3) {
                display: none;
            }
            
            .table th:nth-child(5),
            .table td:nth-child(5) {
                display: none;
            }
            
            .table th:nth-child(6),
            .table td:nth-child(6) {
                display: none;
            }
            
            .table th:nth-child(8),
            .table td:nth-child(8) {
                display: none;
            }
            
            /* Actions en colonne */
            .role-actions {
                flex-direction: column;
                gap: 2px;
            }
            
            .role-actions .btn-sm-custom {
                font-size: 9px;
                padding: 3px 6px;
                text-align: center;
                width: 100%;
            }
        }
        
        /* RESPONSIVE - Très petits écrans */
        @media (max-width: 400px) {
            .admin-container {
                padding: 10px 5px;
            }
            
            .table {
                font-size: 9px;
            }
            
            .table td {
                padding: 4px 3px;
            }
            
            .table th {
                padding: 4px 3px;
                font-size: 7px;
            }
            
            .badge-actif, .badge-inactif, .badge-role {
                font-size: 7px;
                padding: 2px 4px;
            }
        }
        
        /* Barre de recherche responsive */
        @media (max-width: 767px) {
            .row.g-3.mb-4.align-items-center {
                flex-direction: column;
                gap: 10px;
            }
            
            .row.g-3.mb-4.align-items-center .col-md-6 {
                width: 100%;
            }
            
            .row.g-3.mb-4.align-items-center .col-md-6.text-end {
                text-align: center !important;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 6px !important;
            }
            
            .d-flex.gap-2 .btn-asos-sm {
                width: 100%;
                text-align: center;
            }
            
            .d-flex.gap-2 .btn-sm-custom {
                text-align: center;
            }
            
            .btn-outline-success {
                width: 100%;
                text-align: center;
            }
        }
        
        /* Statistiques responsive */
        @media (max-width: 576px) {
            .row.g-3.mb-4 {
                gap: 6px;
            }
            
            .row.g-3.mb-4 .col-md-3 {
                padding: 0 4px;
            }
        }
        
        /* Résumé responsive */
        .bg-light .row.text-center {
            gap: 0;
        }
        
        @media (max-width: 576px) {
            .bg-light .row.text-center .col-4 {
                padding: 0 4px;
            }
            
            .bg-light .row.text-center .col-4 strong {
                font-size: 13px;
            }
            
            .bg-light .row.text-center .col-4 .text-muted {
                font-size: 9px;
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
        
        /* Largeurs minimales pour les colonnes */
        .table th:nth-child(2),
        .table td:nth-child(2) {
            min-width: 100px;
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 60px;
        }
        
        .table th:nth-child(7),
        .table td:nth-child(7) {
            min-width: 60px;
        }
        
        .table th:last-child,
        .table td:last-child {
            min-width: 120px;
        }
        
        @media (max-width: 767px) {
            .table th:last-child,
            .table td:last-child {
                min-width: 80px;
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
        <h2 class="fw-bold mb-4"><i class="bi bi-people me-2"></i> Gestion des Utilisateurs</h2>

        <?php if ($message): ?>
            <div class="alert alert-success rounded-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- STATISTIQUES -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_clients ?></div>
                    <div class="text-muted small">Total utilisateurs</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?= $clients_actifs ?></div>
                    <div class="text-muted small">Comptes actifs</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?= $clients_inactifs ?></div>
                    <div class="text-muted small">Comptes inactifs</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_commandes ?></div>
                    <div class="text-muted small">Commandes totales</div>
                </div>
            </div>
        </div>

        <!-- RECHERCHE ET EXPORT -->
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-12 col-md-6">
                <form method="GET" class="d-flex gap-2 flex-wrap flex-sm-nowrap">
                    <input type="text" name="search" class="form-control rounded-0" placeholder="Rechercher par nom, prénom ou email..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-asos-sm"><i class="bi bi-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="gestion_utilisateurs.php" class="btn-sm-custom">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-12 col-md-6 text-md-end">
                <a href="?export=csv&search=<?= urlencode($search) ?>" class="btn-outline-success">
                    <i class="bi bi-download"></i> Exporter CSV
                </a>
            </div>
        </div>

        <!-- TABLEAU DES UTILISATEURS -->
        <div class="table-responsive">
            <table class="table align-middle border">
                <thead>
                    <tr>
                        <th class="d-none d-md-table-cell">ID</th>
                        <th>Client</th>
                        <th class="d-none d-md-table-cell">Email</th>
                        <th class="text-center">Commandes</th>
                        <th class="d-none d-md-table-cell text-end">Total achats</th>
                        <th class="d-none d-lg-table-cell">Inscription</th>
                        <th>Statut</th>
                        <th class="d-none d-lg-table-cell">Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($utilisateurs)): ?>
                        <tr><td colspan="9" class="text-center py-5">Aucun utilisateur trouvé</td></tr>
                    <?php else: ?>
                        <?php foreach ($utilisateurs as $user): ?>
                            <tr>
                                <td class="d-none d-md-table-cell"><?= $user['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                                    <div class="d-block d-md-none text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="text-center">
                                    <?php if ($user['nb_commandes'] > 0): ?>
                                        <span class="badge bg-dark"><?= $user['nb_commandes'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell text-end fw-bold">
                                    <?= $user['total_achats'] ? number_format($user['total_achats'], 0, ',', ' ') . ' FCFA' : '-' ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php 
                                        $date_inscription = $user['created_at'] ?? $user['date_inscription'] ?? null;
                                        echo $date_inscription ? date('d/m/Y', strtotime($date_inscription)) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php if (isset($user['actif']) && $user['actif'] == 1): ?>
                                        <span class="badge-actif">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-inactif">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge-role badge-admin">Admin</span>
                                    <?php elseif ($user['role'] === 'livreur'): ?>
                                        <span class="badge-role badge-livreur">Livreur</span>
                                    <?php else: ?>
                                        <span class="badge-role badge-client">Client</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="role-actions">
                                        <a href="modifier_utilisateur.php?id=<?= $user['id'] ?>" class="btn-sm-custom" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?action=toggle_status&id=<?= $user['id'] ?>&search=<?= urlencode($search) ?>" 
                                           class="btn-sm-custom"
                                           onclick="return confirm('<?= (isset($user['actif']) && $user['actif'] == 1) ? 'Désactiver' : 'Activer' ?> ce compte ?')">
                                            <?php if (isset($user['actif']) && $user['actif'] == 1): ?>
                                                <i class="bi bi-ban"></i>
                                            <?php else: ?>
                                                <i class="bi bi-check-circle"></i>
                                            <?php endif; ?>
                                        </a>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <a href="?action=change_role&id=<?= $user['id'] ?>&role=livreur&search=<?= urlencode($search) ?>" 
                                               class="btn-sm-custom d-none d-sm-inline-block"
                                               onclick="return confirm('Changer <?= $user['prenom'] ?> en livreur ?')"
                                               title="Changer en livreur">
                                                <i class="bi bi-truck"></i>
                                            </a>
                                            <a href="?action=change_role&id=<?= $user['id'] ?>&role=client&search=<?= urlencode($search) ?>" 
                                               class="btn-sm-custom d-none d-sm-inline-block"
                                               onclick="return confirm('Changer <?= $user['prenom'] ?> en client ?')"
                                               title="Changer en client">
                                                <i class="bi bi-person"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Labels pour les rôles sur mobile -->
                                    <div class="d-block d-lg-none mt-1">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge-role badge-admin" style="font-size:8px;">Admin</span>
                                        <?php elseif ($user['role'] === 'livreur'): ?>
                                            <span class="badge-role badge-livreur" style="font-size:8px;">Livreur</span>
                                        <?php else: ?>
                                            <span class="badge-role badge-client" style="font-size:8px;">Client</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- RÉSUMÉ -->
        <div class="mt-4 p-3 bg-light">
            <div class="row text-center">
                <div class="col-4">
                    <span class="text-muted small">Chiffre d'affaires total</span><br>
                    <strong><?= number_format($ca_total, 0, ',', ' ') ?> FCFA</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted small">Moyenne par client</span><br>
                    <strong><?= $total_clients > 0 ? number_format($ca_total / $total_clients, 0, ',', ' ') : 0 ?> FCFA</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted small">Commandes/client</span><br>
                    <strong><?= $total_clients > 0 ? round($total_commandes / $total_clients, 1) : 0 ?></strong>
                </div>
            </div>
        </div>

        <!-- Lien retour -->
        <div class="mt-4 pt-3 border-top">
            <a href="dashboard.php" class="text-dark text-decoration-none small fw-bold text-uppercase">
                <i class="bi bi-arrow-left me-2"></i> Retour au tableau de bord
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
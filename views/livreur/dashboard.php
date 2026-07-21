<?php
// views/livreur/dashboard.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'livreur') {
    header('Location: ../public/connexion.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$user_id = $_SESSION['user_id'];

// Récupérer les infos du livreur
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

// Récupérer les commandes à livrer (statut = en_livraison)
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.nom, u.prenom, u.email, u.telephone, a.adresse, a.ville 
        FROM commandes c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN adresses a ON c.adresse_id = a.id
        WHERE c.statut = 'en_livraison'
        ORDER BY c.date_commande ASC
    ");
    $stmt->execute();
    $commandes_a_livrer = $stmt->fetchAll();
} catch (\PDOException $e) {
    $commandes_a_livrer = [];
}

// ✅ Récupérer l'historique des livraisons (statut = livree)
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.nom, u.prenom, u.email, u.telephone, a.adresse, a.ville 
        FROM commandes c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN adresses a ON c.adresse_id = a.id
        WHERE c.statut = 'livree'
        ORDER BY c.date_commande DESC
        LIMIT 20
    ");
    $stmt->execute();
    $historique_livraisons = $stmt->fetchAll();
} catch (\PDOException $e) {
    $historique_livraisons = [];
}

// Marquer une commande comme livrée
if (isset($_GET['action']) && $_GET['action'] === 'livrer' && isset($_GET['id'])) {
    $commande_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livree' WHERE id = ?");
        $stmt->execute([$commande_id]);
        header('Location: dashboard.php?success=Commande livrée avec succès');
        exit();
    } catch (\PDOException $e) {
        header('Location: dashboard.php?error=Erreur lors de la validation');
        exit();
    }
}

// Traitement du profil
$message_profil = "";
$error_profil = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($nom) || empty($prenom)) {
        $error_profil = "Le nom et le prénom sont obligatoires.";
    } else {
        try {
            $sql = "UPDATE users SET nom = ?, prenom = ? WHERE id = ?";
            $params = [$nom, $prenom, $user_id];
            
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error_profil = "Le mot de passe doit contenir au moins 6 caractères.";
                } elseif ($password !== $confirm_password) {
                    $error_profil = "Les mots de passe ne correspondent pas.";
                } else {
                    $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "UPDATE users SET nom = ?, prenom = ?, password = ? WHERE id = ?";
                    $params = [$nom, $prenom, $password_hashed, $user_id];
                }
            }
            
            if (empty($error_profil)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                
                $message_profil = "Profil mis à jour avec succès !";
                
                $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmtUser->execute([$user_id]);
                $user = $stmtUser->fetch();
            }
        } catch (\PDOException $e) {
            $error_profil = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Livreur | EMMA+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Helvetica Neue', sans-serif; }
        .dashboard-container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border: 1px solid #e5e5e5; }
        .card-livraison { border: 1px solid #e5e5e5; margin-bottom: 20px; transition: transform 0.2s; }
        .card-livraison:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .badge-livraison { background: #ffc107; color: #111; }
        .badge-livree { background: #d1e7dd; color: #0f5132; border: 1px solid #0f5132; }
        .btn-livrer { background: #28a745; color: white; border: none; border-radius: 0; padding: 8px 20px; }
        .btn-livrer:hover { background: #1e7e34; }
        .info-client { background: #f8f9fa; padding: 10px; margin-top: 10px; border-left: 3px solid #ffc107; }
        .historique-card { border: 1px solid #e5e5e5; margin-bottom: 10px; padding: 15px; background: #fafafa; }
        .historique-card:hover { background: #f5f5f5; }

        /* Menu dropdown */
        .navbar .dropdown-menu {
            border-radius: 0;
            border: 1px solid #e5e5e5;
            padding: 0;
            min-width: 280px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .navbar .dropdown-item {
            padding: 12px 20px;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
            color: #111;
        }
        .navbar .dropdown-item:last-child {
            border-bottom: none;
        }
        .navbar .dropdown-item:hover {
            background: #f8f9fa;
        }
        .profile-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid #111;
            object-fit: cover;
            margin-bottom: 8px;
        }
        .profile-name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
        }
        .profile-role {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .dropdown-divider-custom {
            border-top: 1px solid #e5e5e5;
            margin: 0;
        }
        .dropdown-profile-form {
            padding: 15px 20px;
            background: #fafafa;
        }
        .dropdown-profile-form .form-control {
            border-radius: 0;
            border: 1px solid #ddd;
            font-size: 12px;
            padding: 6px 10px;
        }
        .dropdown-profile-form .form-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 3px;
        }
        .dropdown-profile-form .btn-asos-sm {
            background: #111;
            color: white;
            border: none;
            border-radius: 0;
            padding: 6px 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            width: 100%;
        }
        .dropdown-profile-form .btn-asos-sm:hover {
            background: #333;
        }
        .profile-email-disabled {
            background: #f0f0f0;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .form-group-sm {
            margin-bottom: 8px;
        }
        .user-icon-btn {
            font-size: 24px;
            color: #111;
            padding: 0;
            border: none;
            background: none;
            cursor: pointer;
            transition: color 0.2s;
        }
        .user-icon-btn:hover {
            color: #666;
        }
        .nav-link-custom {
            color: #111;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 1px;
            text-decoration: none;
        }
        .nav-link-custom:hover {
            text-decoration: underline;
        }
        
        /* Section historique */
        .historique-section h4 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 10px;
        }
        .historique-date {
            font-size: 11px;
            color: #999;
        }
    </style>
</head>
<body>

<header class="navbar navbar-light bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">EMMA+ <span class="fs-6 text-muted">| Livreur</span></a>
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="nav-link-custom d-none d-sm-inline">Livraisons</a>
            
            <!-- DROPDOWN PROFIL -->
            <div class="dropdown">
                <button class="user-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="profile-header">
                        <div>
                            <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../../uploads/avatars/' . $user['avatar'])): ?>
                                <img src="../../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="profile-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="profile-avatar d-flex align-items-center justify-content-center bg-light" style="font-size: 30px; color: #666;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                        <div class="profile-role">Livreur</div>
                        <div class="text-muted small mt-1"><?= htmlspecialchars($user['email']) ?></div>
                    </li>
                    <li><hr class="dropdown-divider-custom"></li>
                    <li>
                        <form action="" method="POST" class="dropdown-profile-form">
                            <input type="hidden" name="action" value="update_profil">
                            <div class="row">
                                <div class="col-6 form-group-sm">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                                </div>
                                <div class="col-6 form-group-sm">
                                    <label class="form-label">Prénom</label>
                                    <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group-sm">
                                <label class="form-label">Email (non modifiable)</label>
                                <input type="email" class="form-control profile-email-disabled" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <div class="form-group-sm">
                                <label class="form-label">Nouveau mot de passe</label>
                                <input type="password" name="password" class="form-control" placeholder="Laisser vide pour ne pas changer">
                            </div>
                            <div class="form-group-sm">
                                <label class="form-label">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Répétez le mot de passe">
                            </div>
                            <?php if ($message_profil): ?>
                                <div class="alert alert-success rounded-0 py-1 small mt-2 mb-2"><?= htmlspecialchars($message_profil) ?></div>
                            <?php endif; ?>
                            <?php if ($error_profil): ?>
                                <div class="alert alert-danger rounded-0 py-1 small mt-2 mb-2"><?= htmlspecialchars($error_profil) ?></div>
                            <?php endif; ?>
                            <button type="submit" class="btn-asos-sm mt-2">Enregistrer</button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider-custom"></li>
                    <li>
                        <a href="../public/deconnexion.php" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

<div class="container">
    <div class="dashboard-container shadow-sm">
        <h2 class="h4 fw-bold mb-4">
            <i class="bi bi-truck me-2"></i> Commandes à livrer
            <span class="badge bg-warning rounded-0 ms-2"><?= count($commandes_a_livrer) ?></span>
        </h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success rounded-0 alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger rounded-0 alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($commandes_a_livrer)): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <p class="mt-2 text-muted">Aucune commande à livrer pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($commandes_a_livrer as $cmd): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-livraison p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold fs-5">#<?= $cmd['id'] ?></span>
                                <span class="badge badge-livraison rounded-0 px-3 py-2">
                                    <i class="bi bi-clock-history me-1"></i> À livrer
                                </span>
                            </div>
                            <hr>
                            <div class="mb-2">
                                <strong class="d-block mb-1"><i class="bi bi-person me-1"></i> Client</strong>
                                <span><?= htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']) ?></span>
                            </div>
                            <div class="mb-2">
                                <strong class="d-block mb-1"><i class="bi bi-telephone me-1"></i> Téléphone</strong>
                                <span><?= htmlspecialchars($cmd['telephone'] ?? 'Non renseigné') ?></span>
                            </div>
                            <div class="info-client small">
                                <strong><i class="bi bi-geo-alt me-1"></i> Adresse</strong><br>
                                <?= htmlspecialchars($cmd['adresse'] ?? 'Adresse non renseignée') ?><br>
                                <?= htmlspecialchars($cmd['ville'] ?? '') ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <div>
                                    <span class="text-muted small">Montant</span>
                                    <span class="fw-bold d-block"><?= number_format($cmd['total'], 0, ',', ' ') ?> FCFA</span>
                                </div>
                                <a href="?action=livrer&id=<?= $cmd['id'] ?>" class="btn-livrer" onclick="return confirm('Confirmer la livraison de la commande #<?= $cmd['id'] ?> ?')">
                                    <i class="bi bi-check-lg me-1"></i> Livrée
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SECTION HISTORIQUE DES LIVRAISONS -->
        <div class="historique-section mt-5 pt-4 border-top">
            <h4 class="fw-bold text-uppercase mb-3">
                <i class="bi bi-clock-history me-2"></i> Historique des livraisons
                <span class="badge bg-secondary rounded-0 ms-2"><?= count($historique_livraisons) ?></span>
            </h4>

            <?php if (empty($historique_livraisons)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    <p class="small">Aucune livraison effectuée pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>N°</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique_livraisons as $cmd): ?>
                                <tr>
                                    <td class="fw-bold">#<?= $cmd['id'] ?></td>
                                    <td><?= htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']) ?></td>
                                    <td><?= number_format($cmd['total'], 0, ',', ' ') ?> FCFA</td>
                                    <td class="historique-date"><?= date('d/m/Y H:i', strtotime($cmd['date_commande'])) ?></td>
                                    <td><span class="badge badge-livree rounded-0">Livrée</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="bg-white border-top py-4 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 text-muted small">&copy; <?= date('Y') ?> EMMA+ - Tous droits réservés.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="dashboard.php" class="text-muted text-decoration-none small me-3">
                    <i class="bi bi-house"></i> Accueil
                </a>
                <a href="../public/deconnexion.php" class="text-muted text-decoration-none small text-danger">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// views/client/dashboard.php
session_start();

require_once __DIR__ . '/../../includes/redirect.php';

// Si non connecté ou mauvais rôle, rediriger
if (!estConnecte() || $_SESSION['user_role'] !== 'client') {
    header('Location: 403.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$user_id = $_SESSION['user_id'];

// 2. RÉCUPÉRATION DES INFOS FRAÎCHES
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die("Utilisateur introuvable.");
    }
} catch (\PDOException $e) {
    die("Erreur de récupération des données : " . $e->getMessage());
}

// 3. RÉCUPÉRATION DE LA DERNIÈRE COMMANDE
$derniere_commande = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $derniere_commande = $stmt->fetch();
} catch (\PDOException $e) {
    $derniere_commande = null;
}

// 4. RÉCUPÉRATION DES COMMANDES EN COURS
$commandes_encours = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE user_id = ? AND statut IN ('en_attente', 'en_livraison') ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $commandes_encours = $stmt->fetchAll();
} catch (\PDOException $e) {
    $commandes_encours = [];
}

// 5. VÉRIFIER SI LE CLIENT A DÉJÀ DONNÉ SON AVIS
$a_deja_avis = false;
try {
    $stmt = $pdo->prepare("SELECT id FROM temoignages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        $a_deja_avis = true;
    }
} catch (\PDOException $e) {
    $a_deja_avis = false;
}

// 6. NOTIFICATIONS
$notifications = [];

if (!empty($_SESSION['panier'])) {
    $notifications[] = [
        'type' => 'warning', 
        'icone' => 'cart', 
        'message' => 'Vous avez des articles dans votre panier. Pensez à finaliser votre commande !',
        'lien' => '../public/panier.php'
    ];
}

if (empty($derniere_commande)) {
    $notifications[] = [
        'type' => 'primary',
        'icone' => 'hand-thumbs-up',
        'message' => 'Découvrez nos produits frais et passez votre première commande.',
        'lien' => '../public/boutique.php'
    ];
}

foreach ($commandes_encours as $cmd) {
    if (isset($cmd['date_commande']) && strtotime($cmd['date_commande']) < strtotime('-5 days')) {
        $notifications[] = [
            'type' => 'info',
            'icone' => 'clock-history',
            'message' => 'Votre commande N°' . $cmd['id'] . ' est en traitement depuis plus de 5 jours. Contactez-nous.',
            'lien' => 'historique_commande.php'
        ];
        break;
    }
}

// Si le client n'a pas encore donné son avis
if (!$a_deja_avis && !empty($derniere_commande) && $derniere_commande['statut'] === 'livree') {
    $notifications[] = [
        'type' => 'success',
        'icone' => 'star',
        'message' => 'Vous avez reçu votre commande ! Partagez votre expérience avec nous.',
        'lien' => 'ajouter_temoignage.php'
    ];
}

// =========================================================================
// TRAITEMENT DU FORMULAIRE DE MODIFICATION (via Modal)
// =========================================================================
$message_modal = "";
$error_modal = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');
    
    if (empty($nom) || empty($prenom)) {
        $error_modal = "Le nom et le prénom sont obligatoires.";
    } else {
        try {
            // Mise à jour des informations
            if (!empty($mot_de_passe) && strlen($mot_de_passe) >= 6) {
                $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, password = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $mot_de_passe_hash, $user_id]);
                $message_modal = "Vos informations ont été mises à jour avec succès !";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $user_id]);
                $message_modal = "Vos informations ont été mises à jour avec succès !";
            }
            
            // Mise à jour de la session
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_prenom'] = $prenom;
            
            // Recharger les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (\PDOException $e) {
            $error_modal = "Erreur de mise à jour : " . $e->getMessage();
        }
    }
}

// Fonction pour obtenir les initiales
function getInitiales($prenom, $nom) {
    $initiales = '';
    if (!empty($prenom)) {
        $initiales .= strtoupper(substr($prenom, 0, 1));
    }
    if (!empty($nom)) {
        $initiales .= strtoupper(substr($nom, 0, 1));
    }
    return $initiales ?: 'U';
}

$initiales = getInitiales($user['prenom'] ?? '', $user['nom'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Tableau de bord | EMMA+</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #f8f9fa; 
            color: #111111; 
        }
        
        /* HEADER */
        .navbar-custom {
            background: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            padding: 10px 0;
        }
        
        .navbar-brand { 
            font-weight: 900; 
            font-size: 24px; 
            letter-spacing: 3px; 
            color: #111111 !important; 
            text-decoration: none; 
        }
        
        .nav-link-custom { 
            font-size: 12px; 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 1px; 
            color: #111; 
            text-decoration: none; 
            padding: 8px 0;
        }
        .nav-link-custom:hover { 
            text-decoration: underline; 
        }
        
        /* Menu déroulant utilisateur */
        .user-dropdown-toggle {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #111;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        
        .user-dropdown-toggle:hover {
            opacity: 0.7;
        }
        
        .user-avatar-initials {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #111111;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        
        .user-info-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.2;
        }
        
        .user-info-header .user-name {
            font-weight: 700;
            font-size: 14px;
            color: #111;
        }
        
        .user-info-header .user-role {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dropdown-toggle::after {
            margin-left: 5px;
            font-size: 10px;
        }
        
        .dropdown-menu-custom {
            border-radius: 0;
            border: 1px solid #e5e5e5;
            padding: 5px 0;
            min-width: 200px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            margin-top: 10px;
        }
        
        .dropdown-menu-custom .dropdown-item {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
            color: #111;
            transition: all 0.2s;
        }
        
        .dropdown-menu-custom .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .dropdown-menu-custom .dropdown-item i {
            width: 20px;
            margin-right: 12px;
            font-size: 14px;
        }
        
        .dropdown-divider {
            margin: 5px 0;
            border-top: 1px solid #e5e5e5;
        }
        
        .dropdown-item.text-danger {
            color: #dc3545 !important;
        }
        
        .dropdown-item.text-danger:hover {
            background: #fdf0f0;
        }
        
        /* PAGE */
        .page-header { 
            background: #ffffff; 
            border-bottom: 1px solid #e5e5e5; 
            padding: 25px 0; 
            margin-bottom: 40px; 
        }
        
        .client-box { 
            background: #ffffff; 
            border: 1px solid #e5e5e5; 
            padding: 30px; 
            text-align: center; 
        }
        
        .btn-asos-sm { 
            border: 1px solid #111111; 
            background: transparent; 
            color: #111111; 
            border-radius: 0 !important; 
            padding: 6px 15px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            text-decoration: none; 
            transition: all 0.2s; 
            display: inline-block; 
        }
        .btn-asos-sm:hover { 
            background: #111111; 
            color: #ffffff; 
        }
        
        .card-shortcut { 
            border: 1px solid #e5e5e5; 
            background: #fff; 
            border-radius: 0; 
            padding: 25px; 
            transition: transform 0.2s, border-color 0.2s; 
            text-decoration: none; 
            color: #111; 
            display: block; 
            height: 100%; 
        }
        .card-shortcut:hover { 
            transform: translateY(-3px); 
            border-color: #111; 
            color: #111; 
        }
        .card-shortcut i { 
            font-size: 28px; 
            margin-bottom: 15px; 
            display: block; 
        }
        .shortcut-title { 
            font-weight: 700; 
            text-transform: uppercase; 
            font-size: 14px; 
            letter-spacing: 1px; 
            margin-bottom: 5px; 
        }
        
        /* BOUTON TÉMOIGNAGE SPÉCIAL */
        .btn-temoignage {
            background: #111111;
            color: #ffffff;
            border: 1px solid #111111;
            border-radius: 0;
            padding: 12px 20px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 1px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-temoignage:hover {
            background: #ffffff;
            color: #111111;
        }
        
        .status-badge { 
            border-radius: 0; 
            font-size: 9px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            padding: 3px 8px; 
            display: inline-block; 
        }
        .status-attente { 
            background-color: #111111; 
            color: #ffffff; 
        }
        .status-livre { 
            background-color: #d1e7dd; 
            color: #0f5132; 
            border: 1px solid #0f5132;
        }
        .status-annulee { 
            background-color: #f8d7da; 
            color: #842029; 
            border: 1px solid #842029;
        }
        
        .alert-warning { background-color: #fff3cd; border-left: 3px solid #ffc107; border-radius: 0; }
        .alert-info { background-color: #cfe2ff; border-left: 3px solid #0d6efd; border-radius: 0; }
        .alert-primary { background-color: #cfe2ff; border-left: 3px solid #0d6efd; border-radius: 0; }
        .alert-success { background-color: #d1e7dd; border-left: 3px solid #198754; border-radius: 0; }
        
        /* MODAL PERSONNALISÉ */
        .modal-custom .modal-content {
            border-radius: 0;
            border: 1px solid #e5e5e5;
            padding: 10px;
        }
        
        .modal-custom .modal-header {
            border-bottom: 1px solid #e5e5e5;
            padding: 20px 25px 15px;
        }
        
        .modal-custom .modal-header .modal-title {
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 18px;
        }
        
        .modal-custom .modal-body {
            padding: 25px;
        }
        
        .modal-custom .modal-footer {
            border-top: 1px solid #e5e5e5;
            padding: 15px 25px 20px;
        }
        
        .modal-custom .form-control {
            border-radius: 0;
            border: 1px solid #e5e5e5;
            padding: 12px 15px;
        }
        
        .modal-custom .form-control:focus {
            border-color: #111;
            box-shadow: none;
        }
        
        .modal-custom .form-control:disabled {
            background-color: #f8f9fa;
            color: #999;
            cursor: not-allowed;
        }
        
        .modal-custom .form-label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #555;
        }
        
        .btn-modal-update {
            background: #111;
            color: #fff;
            border: none;
            border-radius: 0;
            padding: 12px 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        
        .btn-modal-update:hover {
            background: #333;
            color: #fff;
        }
        
        .btn-modal-close {
            background: transparent;
            border: 1px solid #e5e5e5;
            border-radius: 0;
            padding: 12px 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #111;
            transition: all 0.3s;
        }
        
        .btn-modal-close:hover {
            background: #f8f9fa;
        }
        
        .alert-modal {
            border-radius: 0;
            padding: 12px 15px;
            font-size: 13px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 576px) {
            .user-info-header .user-name {
                font-size: 12px;
            }
            .user-info-header .user-role {
                font-size: 10px;
            }
            .user-avatar-initials {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            .modal-custom .modal-body {
                padding: 15px;
            }
            .navbar-brand {
                font-size: 18px;
            }
            .btn-temoignage {
                font-size: 11px;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR AVEC MENU DÉROULANT UTILISATEUR -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">EMMA+</a>
            
            <div class="ms-auto d-flex align-items-center gap-3 gap-sm-4">
                <a href="../public/boutique.php" class="nav-link-custom d-none d-sm-inline">Boutique</a>
                
                <!-- Menu déroulant utilisateur -->
                <div class="dropdown">
                    <button class="user-dropdown-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-initials">
                            <?= htmlspecialchars($initiales) ?>
                        </div>
                        <div class="user-info-header">
                            <span class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="bi bi-person-gear"></i> Modifier mon profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="historique_commande.php">
                                <i class="bi bi-box-seam"></i> Mes commandes
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../public/panier.php">
                                <i class="bi bi-bag"></i> Mon panier
                            </a>
                        </li>
                       
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../public/deconnexion.php">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- ENTÊTE -->
    <div class="page-header">
        <div class="container">
            <span class="text-muted small text-uppercase" style="letter-spacing: 1px;">Espace Client</span>
            <h1 class="m-0" style="font-weight: 900; text-transform: uppercase; font-size: 24px;">Tableau de bord</h1>
        </div>
    </div>

    <!-- CONTENU PRINCIPAL -->
    <div class="container mb-5">
        <div class="row g-4">
            
            <!-- RACCOURCIS -->
            <div class="col-12">
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <a href="historique_commande.php" class="card-shortcut shadow-sm">
                            <i class="bi bi-box-seam text-dark"></i>
                            <div class="shortcut-title">Mes commandes</div>
                            <p class="text-muted small mb-0">Suivre et consulter vos achats.</p>
                        </a>
                    </div>

                    <div class="col-sm-4">
                        <a href="../public/panier.php" class="card-shortcut shadow-sm">
                            <i class="bi bi-bag text-dark"></i>
                            <div class="shortcut-title">Mon Panier</div>
                            <p class="text-muted small mb-0">Finaliser vos achats en cours.</p>
                        </a>
                    </div>
                    
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="col-12">
                <?php if (!empty($notifications)): ?>
                <div class="client-box shadow-sm mb-4 text-start">
                    <h3 class="fw-bold fs-6 text-uppercase mb-3">
                        <i class="bi bi-bell me-2"></i> Notifications
                        <span class="badge bg-dark rounded-0 ms-2"><?= count($notifications) ?></span>
                    </h3>
                    
                    <?php foreach ($notifications as $notif): ?>
                        <div class="alert alert-<?= $notif['type'] ?> py-2 small mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-<?= $notif['icone'] ?> me-2"></i>
                                <?= htmlspecialchars($notif['message']) ?>
                            </div>
                            <?php if (isset($notif['lien'])): ?>
                                <a href="<?= $notif['lien'] ?>" class="text-dark fw-bold small text-decoration-none">Voir →</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- DERNIÈRE COMMANDE -->
            <div class="col-12">
                <?php if ($derniere_commande): ?>
                <div class="client-box shadow-sm mb-4 text-start">
                    <h3 class="fw-bold fs-6 text-uppercase mb-3">
                        <i class="bi bi-clock-history me-2"></i> Dernière commande
                    </h3>
                    
                    <?php
                        $status_class = 'status-attente';
                        $statut_affichage = '';

                        if($derniere_commande['statut'] === 'en_attente') { 
                            $status_class = 'status-attente'; 
                            $statut_affichage = 'En attente';
                        } elseif($derniere_commande['statut'] === 'en_livraison') { 
                            $status_class = 'status-livre'; 
                            $statut_affichage = 'En livraison';
                        } elseif($derniere_commande['statut'] === 'livree') { 
                            $status_class = 'status-livre'; 
                            $statut_affichage = 'Livrée';
                        } elseif ($derniere_commande['statut'] === 'annulee'){
                            $status_class = 'status-annulee';
                            $statut_affichage = 'Annulée';
                        }
                    ?>
                    
                    <div>
                        <div>
                            <span class="text-muted small">Commande</span>
                            <span class="fw-bold">N°<?= $derniere_commande['id'] ?></span>
                            <span class="status-badge <?= $status_class ?> ms-2"><?= htmlspecialchars($statut_affichage) ?></span>
                            
                            <p class="mt-2 mb-0">
                                <span class="text-muted small">Total : </span>
                                <span class="fw-bold"><?= number_format($derniere_commande['total'], 0, ',', ' ') ?> FCFA</span>
                            </p>
                            
                            <?php if(isset($derniere_commande['date_commande'])): ?>
                                <p class="text-muted small mb-0 mt-1">
                                    <i class="bi bi-calendar me-1"></i> 
                                    <?= date('d/m/Y', strtotime($derniere_commande['date_commande'])) ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Bouton témoignage si commande livrée -->
                            <?php if ($derniere_commande['statut'] === 'livree' && !$a_deja_avis): ?>
                                <div class="mt-3">
                                    <a href="ajouter_temoignage.php" class="btn-temoignage">
                                        <i class="bi bi-star me-2"></i> Donner mon avis sur cette commande
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-end">
                        <a href="historique_commande.php" class="btn-asos-sm">Voir les détails</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MODAL POPUP MODIFICATION PROFIL -->
    <!-- ================================================================ -->
    <div class="modal fade modal-custom" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">
                        <i class="bi bi-person-gear me-2"></i> Modifier mon profil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="modal-body">
                        
                        <?php if ($message_modal): ?>
                            <div class="alert alert-success alert-modal">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($message_modal) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_modal): ?>
                            <div class="alert alert-danger alert-modal">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($error_modal) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Nom -->
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" 
                                   value="<?= htmlspecialchars($user['nom'] ?? '') ?>" required>
                        </div>
                        
                        <!-- Prénom -->
                        <div class="mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" 
                                   value="<?= htmlspecialchars($user['prenom'] ?? '') ?>" required>
                        </div>
                        
                        <!-- Email (GRISÉ et non modifiable) -->
                        <div class="mb-3">
                            <label class="form-label">Adresse Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                   disabled readonly>
                            <small class="text-muted" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i>
                                L'adresse email n'est pas modifiable.
                            </small>
                        </div>
                        
                        <!-- Mot de passe -->
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" 
                                   placeholder="Laisser vide pour conserver le mot de passe actuel" 
                                   minlength="6">
                            <small class="text-muted" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i>
                                Minimum 6 caractères. Laissez vide pour ne pas modifier.
                            </small>
                        </div>
                        
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-close" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i> Annuler
                        </button>
                        <button type="submit" class="btn-modal-update">
                            <i class="bi bi-save me-2"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Réinitialiser les messages d'alerte lors de la fermeture du modal
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editProfileModal');
            modal.addEventListener('hidden.bs.modal', function() {
                const alerts = modal.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            });
            
            // Ouvrir le modal automatiquement si des messages sont présents
            <?php if ($message_modal || $error_modal): ?>
                var myModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
                myModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>
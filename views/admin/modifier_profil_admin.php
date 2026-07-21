<?php
// views/admin/modifier_profil_admin.php
session_start();

require_once __DIR__ . '/../../config/database.php';



$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Récupérer les infos actuelles
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: dashboard.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($nom) || empty($prenom)) {
        $error = "Le nom et le prénom sont obligatoires.";
    } else {
        try {
            // Mettre à jour nom et prénom
            $sql = "UPDATE users SET nom = ?, prenom = ? WHERE id = ?";
            $params = [$nom, $prenom, $user_id];
            
            // Si mot de passe renseigné, le modifier aussi
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = "Le mot de passe doit contenir au moins 6 caractères.";
                } elseif ($password !== $confirm_password) {
                    $error = "Les mots de passe ne correspondent pas.";
                } else {
                    $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "UPDATE users SET nom = ?, prenom = ?, password = ? WHERE id = ?";
                    $params = [$nom, $prenom, $password_hashed, $user_id];
                }
            }
            
            if (empty($error)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                
                $message = "Votre profil a été mis à jour avec succès !";
                
                // Recharger les données
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (\PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon profil | Admin EMMA+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Helvetica Neue', sans-serif; }
        .profile-container { max-width: 600px; margin: 50px auto; background: white; padding: 40px; border: 1px solid #e5e5e5; }
        .form-control:focus { border-color: #111; box-shadow: none; }
        .form-control-disabled { background-color: #e9ecef; cursor: not-allowed; }
        .btn-asos-dark { background: #111; color: white; border: none; border-radius: 0; padding: 12px 25px; font-weight: 700; text-transform: uppercase; width: 100%; }
        .btn-asos-dark:hover { background: #333; }
        .btn-asos-outline { border: 1px solid #111; background: transparent; color: #111; border-radius: 0; padding: 10px 20px; font-weight: 700; text-transform: uppercase; width: 100%; text-decoration: none; display: inline-block; text-align: center; }
        .btn-asos-outline:hover { background: #111; color: white; }
        .navbar-brand { font-weight: 900; font-size: 24px; letter-spacing: 3px; color: #111111 !important; text-decoration: none; }
        .nav-link-custom { font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; color: #111; text-decoration: none; }
        .nav-link-custom:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <!-- NAVBAR SIMPLE -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">EMMA+ <span style="font-size: 12px; font-weight: 400;">| Admin</span></a>
            <div class="ms-auto d-flex align-items-center gap-4">
                <a href="dashboard.php" class="nav-link-custom">Dashboard</a>
                <a href="gestion_produits.php" class="nav-link-custom">Produits</a>
                <a href="gestion_commandes.php" class="nav-link-custom">Commandes</a>
                <a href="gestion_utilisateurs.php" class="nav-link-custom">Clients</a>
                <a href="../public/deconnexion.php" class="nav-link-custom text-danger">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="m-0" style="font-weight: 900; text-transform: uppercase; font-size: 20px;">
                    <i class="bi bi-person-circle me-2"></i> Mon profil
                </h2>
                <a href="dashboard.php" class="text-dark text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success rounded-0"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger rounded-0"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-uppercase">Nom *</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-uppercase">Prénom *</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Email (non modifiable)</label>
                    <input type="email" class="form-control form-control-disabled" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small class="text-muted">L'email sert d'identifiant et ne peut pas être modifié.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Téléphone (non modifiable)</label>
                    <input type="tel" class="form-control form-control-disabled" value="<?= htmlspecialchars($user['telephone'] ?? 'Non renseigné') ?>" disabled>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Rôle</label>
                    <input type="text" class="form-control form-control-disabled" value="Administrateur" disabled>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Nouveau mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="Laisser vide pour ne pas changer">
                </div>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase">Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Répétez le nouveau mot de passe">
                </div>
                
                <button type="submit" class="btn-asos-dark mb-3">Enregistrer les modifications</button>
                <a href="dashboard.php" class="btn-asos-outline">Annuler</a>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
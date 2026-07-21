<?php
// views/admin/modifier_utilisateur.php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
   header('Location: ../public/connexion.php');
   exit();
}



require_once __DIR__ . '/../../config/database.php';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    header('Location: gestion_utilisateurs.php');
    exit();
}

$message = "";
$error = "";

// Récupérer les infos de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: gestion_utilisateurs.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $actif = isset($_POST['actif']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($nom) || empty($prenom)) {
        $error = "Le nom et le prénom sont obligatoires.";
    } else {
        try {
            // Mettre à jour nom, prénom et statut
            $sql = "UPDATE users SET nom = ?, prenom = ?, actif = ? WHERE id = ?";
            $params = [$nom, $prenom, $actif, $user_id];
            
            // Si mot de passe renseigné, le modifier aussi
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = "Le mot de passe doit contenir au moins 6 caractères.";
                } elseif ($password !== $confirm_password) {
                    $error = "Les mots de passe ne correspondent pas.";
                } else {
                    $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "UPDATE users SET nom = ?, prenom = ?, actif = ?, password = ? WHERE id = ?";
                    $params = [$nom, $prenom, $actif, $password_hashed, $user_id];
                }
            }
            
            if (empty($error)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "Utilisateur modifié avec succès !";
                
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
    <title>Modifier un utilisateur | Admin EMMA+</title>
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
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
        input:checked + .slider { background-color: #111; }
        input:checked + .slider:before { transform: translateX(26px); }
        .slider.round { border-radius: 34px; }
        .slider.round:before { border-radius: 50%; }
    </style>
</head>
<body>

<header class="navbar navbar-light bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">EMMA+ <span class="fs-6 text-muted">| Admin</span></a>
        <a href="../public/deconnexion.php" class="btn btn-sm btn-outline-danger rounded-0">Déconnexion</a>
    </div>
</header>

<div class="container">
    <div class="profile-container shadow-sm">
        <h2 class="text-center mb-4" style="font-weight: 900; text-transform: uppercase; font-size: 22px;">Modifier un utilisateur</h2>
        
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
                <small class="text-muted">L'email ne peut pas être modifié.</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase">Téléphone (non modifiable)</label>
                <input type="tel" class="form-control form-control-disabled" value="<?= htmlspecialchars($user['telephone'] ?? 'Non renseigné') ?>" disabled>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase">Statut du compte</label>
                <div class="d-flex align-items-center gap-3">
                    <label class="switch">
                        <input type="checkbox" name="actif" <?= $user['actif'] == 1 ? 'checked' : '' ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="small" id="statut_label"><?= $user['actif'] == 1 ? 'Actif' : 'Inactif' ?></span>
                </div>
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
            <a href="gestion_utilisateurs.php" class="btn-asos-outline">Retour à la liste des clients</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mettre à jour le texte du statut quand le toggle change
    const toggleSwitch = document.querySelector('input[name="actif"]');
    const statutLabel = document.getElementById('statut_label');
    
    if (toggleSwitch) {
        toggleSwitch.addEventListener('change', function() {
            statutLabel.textContent = this.checked ? 'Actif' : 'Inactif';
        });
    }
</script>
</body>
</html>
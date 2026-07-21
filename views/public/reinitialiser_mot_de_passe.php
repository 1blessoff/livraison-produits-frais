<?php
// views/public/reinitialiser_mot_de_passe.php
session_start();
require_once __DIR__ . '/../../config/database.php';

$message = "";
$status = "";
$show_form = false;

// 1. Vérifier la présence du Token dans l'URL
$token = $_GET['token'] ?? $_POST['token'] ?? null;

if (!$token) {
    $status = "danger";
    $message = "Jeton d'authentification manquant ou invalide.";
} else {
    // 2. Vérifier si le token existe et n'est pas expiré
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $show_form = true; // Le jeton est bon, on peut afficher le formulaire
    } else {
        $status = "danger";
        $message = "Ce lien de récupération a expiré ou est invalide. Veuillez refaire une demande.";
    }
}

// 3. Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $status = "danger";
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $status = "danger";
        $message = "Les deux mots de passe ne correspondent pas.";
    } else {
        // Tout est parfait ! On crypte le nouveau mot de passe
        $new_hash = password_hash($password, PASSWORD_BCRYPT);

        // On met à jour la BDD et on NETTOIE le token pour qu'il ne serve plus jamais
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        $update->execute([$new_hash, $user['id']]);

        $status = "success";
        $message = "Votre mot de passe a été modifié avec succès ! Vous pouvez maintenant vous connecter.";
        $show_form = false; // On cache le formulaire puisqu'il a réussi
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe | EMMA+</title>

    
<!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #fcfcfc; color: #111111; }
        .navbar-brand { font-weight: 900; font-size: 26px; letter-spacing: 3px; color: #111111 !important; text-decoration: none; }
        .login-box { max-width: 450px; margin: 80px auto; padding: 40px; background: #fff; border: 1px solid #e5e5e5; }
        .btn-asos-dark { background-color: #111111; color: #ffffff; border: none; border-radius: 0 !important; padding: 12px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; width: 100%; transition: background 0.2s; }
        .btn-asos-dark:hover { background-color: #2d2d2d; color: #ffffff; }
        .form-control { border-radius: 0 !important; border-color: #b5b5b5; padding: 12px; font-size: 14px; }
        .form-control:focus { border-color: #111; box-shadow: none; }
    </style>
</head>
<body>

    <header class="navbar navbar-light bg-white border-bottom py-3">
        <div class="container justify-content-center">
            <a class="navbar-brand" href="index.php">EMMA+</a>
        </div>
    </header>

    <div class="container">
        <div class="login-box shadow-sm">
            <h2 class="text-center mb-4" style="font-weight: 900; text-transform: uppercase; font-size: 20px; letter-spacing: 1px;">Nouveau Mot de passe</h2>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $status ?> small rounded-0" role="alert">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <form action="" method="POST">
                    <!-- Champ masqué pour conserver le token lors de la soumission -->
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted" style="font-size: 11px;">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 caractères" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-uppercase fw-bold text-muted" style="font-size: 11px;">Confirmez le mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Saisir à nouveau" required>
                    </div>
                    
                    <button type="submit" class="btn-asos-dark">Enregistrer le nouveau mot de passe</button>
                </form>
            <?php else: ?>
                <div class="text-center mt-4">
                    <a href="connexion.php" class="btn-asos-dark text-decoration-none">Se connecter maintenant</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>




  
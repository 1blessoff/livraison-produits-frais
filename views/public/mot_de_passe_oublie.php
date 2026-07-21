<?php
// views/public/mot_de_passe_oublie.php
session_start();
require_once __DIR__ . '/../../config/database.php';

$message = "";
$status = ""; // 'success' ou 'danger'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        // 1. Vérifier si l'email existe en BDD
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Générer un token unique hautement sécurisé
            $token = bin2hex(random_bytes(32));
            
            // 3. Définir une date d'expiration (Ex: +30 minutes)
            $date_expiration = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // 4. Sauvegarder le token et l'expiration chez l'utilisateur
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
            $update->execute([$token, $date_expiration, $email]);

            // 5. Construire le lien de réinitialisation
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $lien = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reinitialiser_mot_de_passe.php?token=" . $token;

            // 6. Préparation du contenu du mail
            $sujet = "Réinitialisation de votre mot de passe - EMMA+";
            $contenuMail = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #111; }
                        .btn { display: inline-block; padding: 12px 25px; background-color: #111; color: #fff !important; text-decoration: none; font-weight: bold; text-transform: uppercase; font-size: 13px; }
                    </style>
                </head>
                <body>
                    <h2>Demande de nouveau mot de passe</h2>
                    <p>Bonjour,</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte EMMA+.</p>
                    <p>Cliquez sur le bouton ci-dessous pour configurer un nouveau mot de passe (Lien valide pendant 30 minutes) :</p>
                    <p><a href='$lien' class='btn'>Réinitialiser mon mot de passe</a></p>
                    <p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.</p>
                    <hr>
                    <small>L'équipe EMMA+</small>
                </body>
                </html>
            ";

            // ---- CONTOURNEMENT GOOGLE APPS SCRIPT (À LA PLACE DE @mail) ----
            // Remplace la valeur ci-dessous par l'adresse exacte fournie par Google (.exec)
            $urlGoogleApp = "https://script.google.com/macros/s/AKfycbwGYTojaJeLBJuIhXyNwdXY5bXBUdp_vSeF2N91tpMR2wl4wc7wv_cyDfIH0MxEt0GG/exec";
            
            $donnees = [
                'cle' => 'BlessPlus_Gateway_7859!_Secure@', // Identique à celle définie dans Google Script
                'email' => $email,
                'sujet' => $sujet,
                'message' => $contenuMail
            ];

            // Envoi des données vers les serveurs de Google en HTTPS via cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlGoogleApp);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($donnees));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
            $reponseGoogle = curl_exec($ch);
            curl_close($ch);
            // ---- FIN DU CONTOURNEMENT ----

            $status = "success";
            $message = "Vous allez recevoir un lien de de réinitialisation par mail.";

        } else {
            // Sécurité : si l'email n'existe pas
            $status = "danger";
            $message = "L'email que vous avez saisi n'existe pas.";
        }
    } else {
        $status = "danger";
        $message = "Veuillez saisir une adresse email valide.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié | EMMA+</title>

    
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
            <h2 class="text-center mb-4" style="font-weight: 900; text-transform: uppercase; font-size: 20px; letter-spacing: 1px;">Mot de passe oublié</h2>
            <p class="text-muted text-center small mb-4">Saisissez votre adresse email. Nous vous enverrons un lien pour créer un nouveau mot de passe.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $status ?> small rounded-0" role="alert">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label class="form-label small text-uppercase fw-bold text-muted" style="font-size: 11px;">Adresse email</label>
                    <input type="email" name="email" class="form-control" placeholder="exemple@mail.com" required>
                </div>
                
                <button type="submit" class="btn-asos-dark mb-3">Envoyer le lien</button>
            </form>

            <div class="text-center mt-3">
                <a href="connexion.php" class="text-dark small text-uppercase fw-bold text-decoration-underline" style="font-size: 12px;">Retour à la connexion</a>
            </div>
        </div>
    </div>

</body>
</html>
<?php
// views/public/verifier_email.php
session_start();

// Vérification d'accès
if (!isset($_SESSION['user_temp_id'])) {
    header('Location: inscription.php?error=acces_non_autorise');
    exit();
}

$user_id = $_SESSION['user_temp_id'];

require_once __DIR__ . '/../../config/database.php';

// Vérifier que l'utilisateur existe
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Si l'utilisateur n'existe pas
if (!$user || empty($user)) {
    unset($_SESSION['user_temp_id']);
    header('Location: inscription.php?error=account_not_find&msg=Compte introuvable, veuillez vous réinscrire');
    exit();
}

// Vérifier si le compte est déjà vérifié
if (!empty($user['email_verified_at'])) {
    header('Location: connexion.php?info=compte_deja_verifie');
    exit();
}

$message = "";
$error = "";
$email = $user['email'];
$temps_restant = 0;

// Fonction pour envoyer le code OTP
function envoyerCodeOTP($email, $code, $pdo, $user_id) {
    $urlGoogleScript = "https://script.google.com/macros/s/AKfycbwGYTojaJeLBJuIhXyNwdXY5bXBUdp_vSeF2N91tpMR2wl4wc7wv_cyDfIH0MxEt0GG/exec";
    
    $sujet = "Code de vérification - EMMA+";
    $contenu = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #111; }
            .code { background: #f4f4f4; padding: 15px; text-align: center; letter-spacing: 5px; font-size: 32px; font-weight: bold; }
        </style>
    </head>
    <body>
        <h2>Bienvenue sur EMMA+</h2>
        <p>Voici votre code de vérification :</p>
        <div class='code'>$code</div>
        <p>Ce code expire dans <strong>10 minutes</strong>.</p>
        <p>Si vous n'êtes pas à l'origine de cette inscription, ignorez cet email.</p>
        <hr>
        <small>L'équipe EMMA+</small>
    </body>
    </html>
    ";
    
    $donnees = [
        'cle' => 'BlessPlus_Gateway_7859!_Secure@',
        'email' => $email,
        'sujet' => $sujet,
        'message' => $contenu
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlGoogleScript);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($donnees));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    
    // Mettre à jour la date du dernier envoi
    $stmt = $pdo->prepare("UPDATE users SET dernier_envoi_code = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
}

// Générer un code aléatoire de 6 chiffres
function genererCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Vérifier si on peut renvoyer un code (temps d'attente)
function peutRenvoyerCode($dernier_envoi, $tentative, &$temps_restant) {
    if (!$dernier_envoi) return true;
    
    // Définir le temps d'attente selon les tentatives
    if ($tentative >= 5) {
        $temps_attente = 300; // 5 minutes
    } elseif ($tentative >= 3) {
        $temps_attente = 120; // 2 minutes
    } else {
        $temps_attente = 60; // 1 minute
    }
    
    $temps_ecoule = time() - strtotime($dernier_envoi);
    $temps_restant = $temps_attente - $temps_ecoule;
    
    return $temps_ecoule >= $temps_attente;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // RENVOYER LE CODE
    if (isset($_POST['resend'])) {
        $tentative = (int)$user['tentative_verification'];
        $dernier_envoi = $user['dernier_envoi_code'];
        
        if (peutRenvoyerCode($dernier_envoi, $tentative, $temps_restant)) {
            $nouveau_code = genererCode();
            $date_expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, code_expires_at = ?, dernier_envoi_code = NOW() WHERE id = ?");
            $stmt->execute([$nouveau_code, $date_expiration, $user_id]);
            
            // Incrémenter le compteur de tentatives
            $stmt = $pdo->prepare("UPDATE users SET tentative_verification = tentative_verification + 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Envoyer le nouveau code
            envoyerCodeOTP($email, $nouveau_code, $pdo, $user_id);
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = "Un nouveau code a été envoyé à votre adresse email.";
        } else {
            $error = "Veuillez attendre " . ceil($temps_restant) . " secondes avant de renvoyer un code.";
        }
    }
    
    // VÉRIFIER LE CODE
    elseif (isset($_POST['verify'])) {
        $code_saisi = trim($_POST['code']);
        
        if (empty($code_saisi)) {
            $error = "Veuillez saisir le code de vérification.";
        } elseif (!isset($user['verification_code']) || empty($user['verification_code'])) {
            $error = "Aucun code trouvé. Cliquez sur 'Renvoyer' pour recevoir un nouveau code.";
        } elseif ($user['verification_code'] != $code_saisi) {
            $error = "Code incorrect. Veuillez réessayer.";
            // Incrémenter le compteur de tentatives
            $stmt = $pdo->prepare("UPDATE users SET tentative_verification = tentative_verification + 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (strtotime($user['code_expires_at']) < time()) {
            $error = "Le code a expiré. Cliquez sur 'Renvoyer' pour recevoir un nouveau code.";
        } else {
            // Code valide : activer le compte
            $stmt = $pdo->prepare("UPDATE users SET email_verified_at = NOW(), actif = 1, verification_code = NULL, code_expires_at = NULL, tentative_verification = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Connecter l'utilisateur automatiquement
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = 'client';
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            
            unset($_SESSION['user_temp_id']);
            
            header('Location: ../client/dashboard.php');
            exit();
        }
    }
}

// Générer un code initial si pas encore fait
if (empty($user['verification_code'])) {
    $code = genererCode();
    $date_expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, code_expires_at = ?, tentative_verification = 0, dernier_envoi_code = NOW() WHERE id = ?");
    $stmt->execute([$code, $date_expiration, $user_id]);
    envoyerCodeOTP($email, $code, $pdo, $user_id);
    $message = "Un code de vérification a été envoyé à votre adresse email.";
    
    // Recharger les données
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculer le temps restant pour le timer
if ($user['dernier_envoi_code']) {
    $tentative = (int)$user['tentative_verification'];
    if ($tentative >= 5) {
        $temps_attente = 300;
    } elseif ($tentative >= 3) {
        $temps_attente = 120;
    } else {
        $temps_attente = 60;
    }
    $temps_ecoule = time() - strtotime($user['dernier_envoi_code']);
    if ($temps_ecoule < $temps_attente) {
        $temps_restant = $temps_attente - $temps_ecoule;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification email | EMMA+</title>

    <!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background: #f8f9fa; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verify-wrapper {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .verify-container { 
            background: #ffffff; 
            padding: 40px 30px; 
            border: 1px solid #e5e5e5;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .verify-container .icon-circle {
            width: 70px;
            height: 70px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #111;
        }
        
        .verify-container h2 {
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 900;
            letter-spacing: 1px;
            color: #111;
        }
        
        .verify-container .subtitle {
            font-size: clamp(13px, 1.8vw, 15px);
            color: #666;
            margin-bottom: 25px;
        }
        
        .code-input { 
            font-size: clamp(22px, 4vw, 28px); 
            letter-spacing: 12px; 
            text-align: center; 
            font-weight: 700;
            border: 2px solid #e5e5e5;
            padding: 15px 10px;
            height: auto;
            transition: border-color 0.3s ease;
            border-radius: 0;
        }
        
        .code-input:focus {
            border-color: #111;
            box-shadow: none;
        }
        
        .code-input::placeholder {
            letter-spacing: 5px;
            font-weight: 400;
            color: #ccc;
            font-size: clamp(16px, 3vw, 20px);
        }
        
        .btn-asos-dark { 
            background: #111; 
            color: #ffffff; 
            border: none; 
            border-radius: 0; 
            padding: 14px 20px; 
            width: 100%; 
            font-weight: 700; 
            text-transform: uppercase;
            font-size: clamp(13px, 1.5vw, 15px);
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-asos-dark:hover { 
            background: #333; 
            color: #ffffff;
        }
        
        .btn-asos-dark:active {
            transform: scale(0.98);
        }
        
        .resend-section {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        
        .resend-btn { 
            background: none; 
            border: none; 
            color: #111; 
            text-decoration: underline; 
            font-size: clamp(12px, 1.4vw, 14px);
            font-weight: 600;
            padding: 5px 10px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .resend-btn:hover:not(:disabled) { 
            color: #333;
        }
        
        .resend-btn:disabled { 
            color: #999; 
            text-decoration: none; 
            cursor: not-allowed;
        }
        
        .timer { 
            font-size: clamp(12px, 1.4vw, 14px); 
            color: #666;
            font-weight: 600;
            min-width: 60px;
            display: inline-block;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #999;
            text-decoration: none;
            font-size: clamp(12px, 1.3vw, 14px);
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #111;
        }
        
        .alert {
            border-radius: 0;
            font-size: clamp(13px, 1.4vw, 14px);
            padding: 12px 15px;
        }
        
        .alert-success {
            background: #f0f8f0;
            border: 1px solid #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #fdf0f0;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .email-info {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: clamp(12px, 1.3vw, 14px);
            color: #666;
            word-break: break-all;
        }
        
        .email-info strong {
            color: #111;
        }

        /* RESPONSIVE */
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .verify-container { 
                padding: 25px 18px; 
            }
            
            .verify-container .icon-circle {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .code-input { 
                letter-spacing: 8px;
                padding: 12px 8px;
            }
            
            .code-input::placeholder {
                letter-spacing: 3px;
                font-size: 14px;
            }
            
            .btn-asos-dark {
                padding: 12px 15px;
            }
            
            .resend-section {
                flex-direction: column;
                gap: 5px;
            }
            
            .resend-btn {
                font-size: 13px;
            }
            
            .timer {
                font-size: 13px;
            }
        }
        
        @media (max-width: 400px) {
            .verify-container { 
                padding: 20px 12px; 
            }
            
            .code-input { 
                letter-spacing: 5px;
                padding: 10px 6px;
                font-size: 20px;
            }
            
            .code-input::placeholder {
                letter-spacing: 2px;
                font-size: 12px;
            }
        }
        
        @media (min-width: 768px) and (max-width: 992px) {
            .verify-container {
                padding: 45px 35px;
            }
        }
        
        /* Animation d'apparition */
        .verify-container {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<div class="verify-wrapper">
    <div class="verify-container shadow-sm">
        
        <div class="text-center">
            <div class="icon-circle">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h2>Vérifiez votre email</h2>
            <p class="subtitle">Pour des raisons de securites saisissez le code qui vous a ete envoyez.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success rounded-0 small mb-3">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0 small mb-3">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase" style="letter-spacing: 0.5px; color: #555;">
                    Code de vérification
                </label>
                <input type="text" 
                       name="code" 
                       class="form-control code-input" 
                       placeholder="------" 
                       maxlength="6" 
                       required 
                       autofocus
                       inputmode="numeric"
                       pattern="[0-9]*">
            </div>
            
            <button type="submit" name="verify" class="btn-asos-dark mb-3">
                <i class="bi bi-check-lg me-2"></i> Vérifier mon compte
            </button>
        </form>

        <div class="resend-section">
            <form method="POST" class="d-inline">
                <button type="submit" name="resend" class="resend-btn" <?= $temps_restant > 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-arrow-repeat me-1"></i> Renvoyer le code
                </button>
            </form>
            <span class="timer" id="timer">
                <?= $temps_restant > 0 ? '(' . $temps_restant . 's)' : '' ?>
            </span>
        </div>

        <div class="text-center mt-3">
            <a href="connexion.php" class="back-link">
                <i class="bi bi-arrow-left me-1"></i> Retour à la connexion
            </a>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Compte à rebours
var tempsRestant = <?= $temps_restant ?>;
var timerInterval = null;
var timerElement = document.getElementById('timer');
var btnResend = document.querySelector('.resend-btn');

function startTimer(seconds) {
    if (timerInterval) clearInterval(timerInterval);
    
    if (seconds > 0) {
        if (btnResend) btnResend.disabled = true;
        if (timerElement) timerElement.innerHTML = '(' + seconds + 's)';
        
        timerInterval = setInterval(function() {
            seconds--;
            if (seconds <= 0) {
                clearInterval(timerInterval);
                if (timerElement) timerElement.innerHTML = '';
                if (btnResend) btnResend.disabled = false;
            } else {
                if (timerElement) timerElement.innerHTML = '(' + seconds + 's)';
            }
        }, 1000);
    } else {
        if (btnResend) btnResend.disabled = false;
        if (timerElement) timerElement.innerHTML = '';
    }
}

if (tempsRestant > 0) {
    startTimer(tempsRestant);
}

// Empêcher les caractères non numériques dans le champ code
document.querySelector('.code-input').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Auto-soumission quand 6 chiffres sont saisis (optionnel)
document.querySelector('.code-input').addEventListener('input', function(e) {
    if (this.value.length === 6) {
        // Décommenter pour auto-soumettre
        // this.closest('form').submit();
    }
});
</script>
</body>
</html>
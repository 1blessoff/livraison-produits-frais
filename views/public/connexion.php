<?php
// 1. DÉMARRAGE DE LA SESSION & INCLUSION
session_start();
// Vérification centralisée
require_once __DIR__ . '/../../includes/redirect.php';

// Si déjà connecté, rediriger vers le bon dashboard
if (estConnecte()) {
    redirigerVersDashboard();
}

require_once __DIR__ . '/../../config/database.php'; 

// 2. GÉNÉRATION DU TOKEN CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$email = "";
$success = "";

if (!empty($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); 
}

// 3. TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Erreur de sécurité. Veuillez rafraîchir la page et réessayer.";
    } else {
        $email_input = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email_input) || empty($password)) {
            $errors[] = "Veuillez remplir tous les champs.";
        } else {
            $email = strtolower($email_input);
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                $passwordIsValid = false;
                if ($user) {
                    if (password_verify($password, $user['password'])) {
                        $passwordIsValid = true;
                    } else if ($user['password'] === $password) {
                        $passwordIsValid = true;
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmtUpdate->execute([$newHash, $user['id']]);
                    }
                }

                if ($user && $passwordIsValid) {
                    
                    // Vérification compte actif
                    if (isset($user['actif']) && $user['actif'] == 0) {
                        $errors[] = "Vous n'avez plus accès à votre compte.";
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id']     = $user['id'];
                        $_SESSION['user_nom']    = $user['nom'];
                        $_SESSION['user_prenom'] = $user['prenom'];
                        $_SESSION['user_role']   = $user['role']; 

                        if (isset($_POST['remember_me'])) {
                            $token = bin2hex(random_bytes(32)); 
                            $token_hashed = hash('sha256', $token);
                            
                            $stmtToken = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                            $stmtToken->execute([$token_hashed, $user['id']]);

                            $cookie_value = $user['id'] . ':' . $token;
                            setcookie('remember_me_cookie', $cookie_value, time() + (30 * 24 * 3600), "/", "", false, true);
                        }

                        // Vérifier s'il y a une URL de redirection
                        if (isset($_SESSION['redirect_after_login'])) {
                            $redirect_url = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']);
                            header('Location: ' . $redirect_url);
                            exit();
                        }

                        // REDIRECTION SELON LE RÔLE
                        if ($user['role'] === 'admin') {
                            header('Location: ../admin/dashboard.php');
                            exit();
                        } elseif ($user['role'] === 'livreur') {
                            header('Location: ../livreur/dashboard.php');
                            exit();
                        } else {
                            header('Location: ../client/dashboard.php');
                            exit();
                        }
                    }
                } else {
                    $errors[] = "Adresse email ou mot de passe incorrect.";
                }

            } catch (\PDOException $e) {
                $errors[] = "Une erreur est survenue. Veuillez réessayer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connectez-vous à votre compte EMMA+ pour commander vos produits frais">
    <title>Se connecter | EMMA+</title>

    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="favicon.ico">
    
    <style>
        /* ============================================
        VARIABLES & RESET
        ============================================ */
        :root {
            --primary: #111111;
            --secondary: #666666;
            --light-bg: #f8f9fa;
            --border-color: #e5e5e5;
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #ffffff;
            color: var(--primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* ============================================
        NAVBAR
        ============================================ */
        .navbar {
            border-bottom: 1px solid var(--border-color);
            padding: 16px 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-weight: 900;
            font-size: clamp(22px, 3vw, 28px);
            letter-spacing: 4px;
            color: var(--primary) !important;
            transition: var(--transition);
        }
        
        .navbar-brand:hover {
            opacity: 0.7;
        }
        
        /* ============================================
        CONTAINER PRINCIPAL
        ============================================ */
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-color: var(--light-bg);
        }
        
        .login-container {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            padding: 40px 35px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            transition: var(--transition);
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .login-container:hover {
            box-shadow: 0 4px 40px rgba(0,0,0,0.05);
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
        
        /* ============================================
        TITRE
        ============================================ */
        .login-title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: clamp(20px, 2.5vw, 24px);
            margin-bottom: 8px;
            text-align: center;
        }
        
        .login-subtitle {
            color: var(--secondary);
            font-size: clamp(13px, 1.2vw, 14px);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .login-divider {
            width: 40px;
            height: 2px;
            background: var(--primary);
            margin: 0 auto 30px;
        }
        
        /* ============================================
        FORMULAIRES
        ============================================ */
        .form-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--secondary);
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        
        .form-control {
            border-radius: 0 !important;
            border: 1px solid #d0d0d0;
            padding: 11px 14px;
            font-size: 14px;
            transition: var(--transition);
            background: #fcfcfc;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.05);
            background: #ffffff;
        }
        
        .form-control::placeholder {
            color: #b0b0b0;
            font-size: 13px;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        .form-control.is-valid {
            border-color: #28a745;
        }
        
        /* ============================================
        CHECKBOX REMEMBER ME
        ============================================ */
        .remember-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0 25px 0;
            padding: 8px 12px;
            background: #f9f9f9;
            border-left: 2px solid var(--primary);
        }
        
        .remember-wrapper input[type="checkbox"] {
            width: 17px;
            height: 17px;
            min-width: 17px;
            border: 2px solid #999;
            border-radius: 0;
            cursor: pointer;
            accent-color: var(--primary);
            transition: var(--transition);
        }
        
        .remember-wrapper input[type="checkbox"]:checked {
            border-color: var(--primary);
        }
        
        .remember-wrapper label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #555;
            letter-spacing: 0.5px;
            cursor: pointer;
            margin: 0;
        }
        
        .remember-wrapper label:hover {
            color: var(--primary);
        }
        
        /* ============================================
        LIEN MOT DE PASSE OUBLIÉ
        ============================================ */
        .forgot-link {
            color: var(--secondary);
            font-size: 12px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        /* ============================================
        BOUTON
        ============================================ */
        .btn-login {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 0 !important;
            padding: 14px 20px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            background: #222222;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        /* ============================================
        LIENS
        ============================================ */
        .register-link {
            color: var(--primary);
            text-decoration: underline;
            font-weight: 700;
            transition: var(--transition);
        }
        
        .register-link:hover {
            color: var(--secondary);
        }
        
        .back-link {
            color: var(--secondary);
            text-decoration: none;
            font-size: 13px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .back-link:hover {
            color: var(--primary);
            transform: translateX(-3px);
        }
        
        /* ============================================
        ALERTES
        ============================================ */
        .alert-custom {
            border-radius: 0;
            border-left: 3px solid;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        .alert-custom.alert-danger {
            border-left-color: #dc3545;
            background: #fef2f2;
            color: #991b1b;
        }
        
        .alert-custom.alert-success {
            border-left-color: #28a745;
            background: #f0fdf4;
            color: #166534;
        }
        
        .alert-custom ul {
            margin: 0;
            padding-left: 18px;
        }
        
        .alert-custom ul li {
            margin-bottom: 2px;
        }
        
        /* ============================================
        RESPONSIVE
        ============================================ */
        @media (max-width: 576px) {
            .login-wrapper {
                padding: 20px 12px;
            }
            
            .login-container {
                padding: 28px 18px;
            }
            
            .login-title {
                font-size: 20px;
                letter-spacing: 1.5px;
            }
            
            .login-divider {
                margin-bottom: 22px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .remember-wrapper {
                padding: 8px 10px;
            }
            
            .remember-wrapper label {
                font-size: 11px;
            }
            
            .btn-login {
                padding: 12px 16px;
                font-size: 13px;
                letter-spacing: 1px;
            }
            
            .forgot-link {
                font-size: 11px;
            }
        }
        
        @media (max-width: 400px) {
            .login-container {
                padding: 20px 14px;
            }
            
            .login-title {
                font-size: 18px;
            }
            
            .login-subtitle {
                font-size: 12px;
            }
            
            .form-label {
                font-size: 10px;
            }
        }
        
        /* ============================================
        SCROLLBAR
        ============================================ */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #333;
        }
    </style>
</head>
<body>

    <!-- ============================================
    NAVBAR
    ============================================ -->
    <header class="navbar navbar-light">
        <div class="container">
            <a class="navbar-brand mx-auto" href="../../index.php">EMMA+</a>
        </div>
    </header>

    <!-- ============================================
    FORMULAIRE DE CONNEXION
    ============================================ -->
    <div class="login-wrapper">
        <div class="login-container">
            
            <h2 class="login-title">Connexion</h2>
            <p class="login-subtitle">Veuillez vous connecter a votre compte</p>
            <div class="login-divider"></div>

            <!-- Message de succès d'inscription -->
            <?php if (!empty($success)): ?>
                <div class="alert-custom alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Affichage des erreurs -->
            <?php if (!empty($errors)): ?>
                <div class="alert-custom alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE -->
            <form action="" method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Adresse Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="jean.dupont@email.com" 
                           value="<?= htmlspecialchars($email) ?>"
                           required 
                           autofocus>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label mb-0">Mot de passe</label>
                        <a href="mot_de_passe_oublie.php" class="forgot-link">
                            <i class="bi bi-question-circle me-1"></i>Mot de passe oublié ?
                        </a>
                    </div>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Votre mot de passe" 
                           required>
                </div>

                <!-- Checkbox "Se rappeler de moi" -->
                <div class="remember-wrapper">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <label for="remember_me">
                        <i class="bi bi-check-circle me-1"></i> Se rappeler de moi
                    </label>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted" style="font-size: 13px;">
                        Pas encore de compte ? 
                        <a href="inscription.php" class="register-link">Inscrivez-vous</a>
                    </p>
                </div>

                <div class="text-center mt-3">
                    <a href="../../index.php" class="back-link">
                        <i class="bi bi-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Validation côté client en temps réel
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        // Validation email
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() !== '' && emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        emailInput.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() !== '' && emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        // Validation mot de passe
        passwordInput.addEventListener('blur', function() {
            if (this.value.trim() !== '' && this.value.length >= 6) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        passwordInput.addEventListener('input', function() {
            if (this.value.trim() !== '' && this.value.length >= 6) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    });
    </script>
</body>
</html>
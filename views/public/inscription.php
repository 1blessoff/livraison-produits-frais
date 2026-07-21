<?php
// 1. DÉMARRAGE DE LA SESSION & INCLUSION DE LA BASE DE DONNÉES
session_start();
require_once __DIR__ . '/../../config/database.php';

// 2. GÉNÉRATION DU TOKEN CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialisation des variables
$errors = [];
$success = "";
$nom = $prenom = $email = $telephone = $adresse = "";
$accept_cgu = false;

// 3. TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erreur de sécurité : Token CSRF invalide.");
    }

    // Récupération et nettoyage des données
    $nom       = trim(htmlspecialchars($_POST['nom']));
    $prenom    = trim(htmlspecialchars($_POST['prenom']));
    $email     = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
    $telephone = trim(htmlspecialchars($_POST['telephone']));
    $adresse   = trim(htmlspecialchars($_POST['adresse']));
    $password  = $_POST['password'];
    $accept_cgu = isset($_POST['accept_cgu']) ? true : false;

    // 4. VALIDATIONS
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $errors[] = "Veuillez remplir tous les champs obligatoires (*).";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if (!$accept_cgu) {
        $errors[] = "Vous devez accepter les Conditions Générales d'Utilisation.";
    }

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "Cette adresse email est déjà enregistrée.";
        }
    }

    // 5. INSCRIPTION
    if (empty($errors)) {
        try {
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (nom, prenom, email, telephone, adresse, role, password) 
                    VALUES (:nom, :prenom, :email, :telephone, :adresse, 'client', :password)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'nom'       => $nom,
                'prenom'    => $prenom,
                'email'     => $email,
                'telephone' => !empty($telephone) ? $telephone : null,
                'adresse'   => !empty($adresse) ? $adresse : null,
                'password'  => $password_hashed
            ]);

            if ($result) {
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_temp_id'] = $user_id;
                header('Location: verifier_email.php');
                exit();
            }

        } catch (\PDOException $e) {
            $errors[] = "Une erreur est survenue lors de l'enregistrement.";
            error_log("Erreur inscription : " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Créez votre compte EMMA+ et profitez de nos produits frais livrés à domicile">
    <title>Créer un compte | EMMA+</title>
    
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
            background-color: var(--light-bg);
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
        .register-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .register-container {
            max-width: 520px;
            width: 100%;
            background: #ffffff;
            padding: 40px 35px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            transition: var(--transition);
        }
        
        .register-container:hover {
            box-shadow: 0 4px 40px rgba(0,0,0,0.05);
        }
        
        /* ============================================
        TITRE
        ============================================ */
        .register-title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: clamp(20px, 2.5vw, 24px);
            margin-bottom: 8px;
            text-align: center;
        }
        
        .register-subtitle {
            color: var(--secondary);
            font-size: clamp(13px, 1.2vw, 14px);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .register-divider {
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
            border-radius: 0;
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
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        /* ============================================
        CHECKBOX CGU
        ============================================ */
        .cgu-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 20px 0 25px 0;
            padding: 12px 14px;
            background: #f9f9f9;
            border-left: 2px solid var(--primary);
        }
        
        .cgu-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            min-width: 18px;
            margin-top: 1px;
            border: 2px solid #999;
            border-radius: 0;
            cursor: pointer;
            accent-color: var(--primary);
            transition: var(--transition);
        }
        
        .cgu-wrapper input[type="checkbox"]:checked {
            border-color: var(--primary);
        }
        
        .cgu-wrapper label {
            font-size: 13px;
            color: #555;
            cursor: pointer;
            line-height: 1.5;
        }
        
        .cgu-wrapper label a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: underline;
            transition: var(--transition);
        }
        
        .cgu-wrapper label a:hover {
            color: var(--secondary);
        }
        
        .cgu-wrapper .required-star {
            color: #dc3545;
            font-weight: 700;
        }
        
        /* ============================================
        BOUTON
        ============================================ */
        .btn-register {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 0;
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
        
        .btn-register:hover {
            background: #222222;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .btn-register i {
            margin-right: 8px;
        }
        
        /* ============================================
        LIENS
        ============================================ */
        .login-link {
            color: var(--primary);
            text-decoration: underline;
            font-weight: 700;
            transition: var(--transition);
        }
        
        .login-link:hover {
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
            .register-wrapper {
                padding: 20px 12px;
            }
            
            .register-container {
                padding: 28px 18px;
            }
            
            .register-title {
                font-size: 20px;
                letter-spacing: 1.5px;
            }
            
            .register-divider {
                margin-bottom: 22px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .cgu-wrapper {
                padding: 10px 12px;
            }
            
            .cgu-wrapper label {
                font-size: 12px;
            }
            
            .btn-register {
                padding: 12px 16px;
                font-size: 13px;
                letter-spacing: 1px;
            }
            
            .row.g-3 {
                --bs-gutter-x: 0.5rem;
            }
        }
        
        @media (max-width: 400px) {
            .register-container {
                padding: 20px 14px;
            }
            
            .register-title {
                font-size: 18px;
            }
            
            .register-subtitle {
                font-size: 12px;
            }
            
            .form-label {
                font-size: 10px;
            }
        }
        
        /* ============================================
        ANIMATION D'ENTRÉE
        ============================================ */
        .register-container {
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
    <nav class="navbar navbar-light">
        <div class="container">
            <a class="navbar-brand mx-auto" href="../../index.php">EMMA+</a>
        </div>
    </nav>

    <!-- ============================================
    FORMULAIRE D'INSCRIPTION
    ============================================ -->
    <div class="register-wrapper">
        <div class="register-container">
            
            <h2 class="register-title">Créer un compte</h2>
            <p class="register-subtitle">Rejoignez la communauté EMMA+</p>
            <div class="register-divider"></div>

            <!-- AFFICHAGE DES ERREURS -->
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
            <form action="" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?= htmlspecialchars($nom) ?>" 
                               placeholder="Votre" 
                               required 
                               autofocus>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               value="<?= htmlspecialchars($prenom) ?>" 
                               placeholder="Votre prenom" 
                               required>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="email" class="form-label">Email (reel pas fictif) <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($email) ?>" 
                           placeholder="Votre adresse email" 
                           required>
                </div>

                <div class="mt-3">
                    <label for="telephone" class="form-label">Téléphone</label>
                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                           value="<?= htmlspecialchars($telephone) ?>" 
                           placeholder="Ex 055322233">
                </div>

                <div class="mt-3">
                    <label for="adresse" class="form-label">Adresse de résidence</label>
                    <textarea class="form-control" id="adresse" name="adresse" 
                              rows="2" 
                              placeholder="Ex Mpita"><?= htmlspecialchars($adresse) ?></textarea>
                </div>

                <div class="mt-3">
                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="6 caractères minimum" 
                           required>
                    <small class="text-muted" style="font-size: 11px;">
                        <i class="bi bi-info-circle me-1"></i>6 caractères minimum
                    </small>
                </div>

                <!-- CGU -->
                <div class="cgu-wrapper">
                    <input type="checkbox" id="accept_cgu" name="accept_cgu" value="1" 
                           <?= isset($_POST['accept_cgu']) ? 'checked' : '' ?>>
                    <label for="accept_cgu">
                        J'ai lu et j'accepte les 
                        <a href="cgu.php" target="_blank">Conditions Générales d'Utilisation</a>
                        <span class="required-star">*</span>
                    </label>
                </div>

                <button type="submit" class="btn-register">
                    <i class="bi bi-person-plus"></i> Créer mon compte
                </button>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted" style="font-size: 13px;">
                        Déjà inscrit ? 
                        <a href="connexion.php" class="login-link">Connectez-vous</a>
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
        const inputs = form.querySelectorAll('input[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            
            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
        
        // Validation du mot de passe
        const password = document.getElementById('password');
        password.addEventListener('input', function() {
            if (this.value.length >= 6) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        // Validation email
        const email = document.getElementById('email');
        email.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(this.value)) {
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
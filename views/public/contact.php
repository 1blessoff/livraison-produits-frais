<?php
// views/public/contact.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$status = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erreur de sécurité : Token CSRF invalide.");
    }

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet_msg = trim($_POST['sujet'] ?? '');
    $message_txt = trim($_POST['message'] ?? '');

    if (empty($nom) || empty($email) || empty($sujet_msg) || empty($message_txt)) {
        $status = "danger";
        $message = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = "danger";
        $message = "Veuillez entrer une adresse email valide.";
    } else {
        
        $urlGoogleApp = "https://script.google.com/macros/s/AKfycbwGYTojaJeLBJuIhXyNwdXY5bXBUdp_vSeF2N91tpMR2wl4wc7wv_cyDfIH0MxEt0GG/exec"; 
        
        $sujetMail = "Nouveau message de contact [EMMA+] : " . $sujet_msg;
        $contenuMail = "
            <h2>Nouveau message client - EMMA+</h2>
            <p><strong>Nom :</strong> $nom</p>
            <p><strong>Email :</strong> $email</p>
            <p><strong>Sujet :</strong> $sujet_msg</p>
            <p><strong>Message :</strong></p>
            <p>$message_txt</p>
        ";

        $monEmailAdmin = "koukachrist48@gmail.com"; 

        $donnees = [
            'cle' => 'BlessPlus_Gateway_7859!_Secure@', 
            'email' => $monEmailAdmin, 
            'sujet' => $sujetMail,
            'message' => $contenuMail
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlGoogleApp);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($donnees));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);

        $status = "success";
        $message = "Merci ! Votre message a bien été envoyé.";
        
        $nom = $email = $sujet_msg = $message_txt = "";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contactez le service client EMMA+ pour toute question ou demande d'information">
    <title>Contactez-nous | EMMA+</title>
    
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
        .contact-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-color: var(--light-bg);
        }
        
        .contact-container {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            padding: 40px 35px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            transition: var(--transition);
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .contact-container:hover {
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
        .contact-title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: clamp(22px, 2.8vw, 26px);
            margin-bottom: 8px;
            text-align: center;
        }
        
        .contact-subtitle {
            color: var(--secondary);
            font-size: clamp(13px, 1.2vw, 14px);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .contact-divider {
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
        
        .form-label .required-star {
            color: #dc3545;
            font-weight: 700;
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
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        /* ============================================
        BOUTON
        ============================================ */
        .btn-send {
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
        
        .btn-send:hover {
            background: #222222;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .btn-send:active {
            transform: translateY(0);
        }
        
        .btn-send i {
            margin-right: 8px;
        }
        
        /* ============================================
        LIENS
        ============================================ */
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
        
        .alert-custom i {
            margin-right: 8px;
        }
        
        /* ============================================
        INFOS DE CONTACT
        ============================================ */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }
        
        .contact-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--secondary);
            font-size: 13px;
            transition: var(--transition);
        }
        
        .contact-info-item:hover {
            color: var(--primary);
        }
        
        .contact-info-item i {
            font-size: 18px;
            width: 24px;
            text-align: center;
            color: var(--primary);
        }
        
        .contact-info-item a {
            color: var(--secondary);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .contact-info-item a:hover {
            color: var(--primary);
        }
        
        /* ============================================
        RESPONSIVE
        ============================================ */
        @media (max-width: 576px) {
            .contact-wrapper {
                padding: 20px 12px;
            }
            
            .contact-container {
                padding: 28px 18px;
            }
            
            .contact-title {
                font-size: 20px;
                letter-spacing: 1.5px;
            }
            
            .contact-divider {
                margin-bottom: 22px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            textarea.form-control {
                min-height: 100px;
            }
            
            .btn-send {
                padding: 12px 16px;
                font-size: 13px;
                letter-spacing: 1px;
            }
            
            .contact-info-item {
                font-size: 12px;
            }
            
            .contact-info-item i {
                font-size: 16px;
                width: 20px;
            }
        }
        
        @media (max-width: 400px) {
            .contact-container {
                padding: 20px 14px;
            }
            
            .contact-title {
                font-size: 18px;
            }
            
            .contact-subtitle {
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
    FORMULAIRE DE CONTACT
    ============================================ -->
    <div class="contact-wrapper">
        <div class="contact-container">
            
            <h2 class="contact-title">Service Client</h2>
            <p class="contact-subtitle">Une question ? Écrivez-nous, on vous répondra sous 24h</p>
            <div class="contact-divider"></div>

            <!-- Affichage du message -->
            <?php if (!empty($message)): ?>
                <div class="alert-custom alert-<?= $status ?>" role="alert">
                    <i class="bi bi-<?= $status === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE -->
            <form action="" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label for="nom" class="form-label">
                        Votre nom complet <span class="required-star">*</span>
                    </label>
                    <input type="text" class="form-control" id="nom" name="nom" 
                           value="<?= htmlspecialchars($nom ?? '') ?>" 
                           placeholder="Nom" 
                           required 
                           autofocus>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        Votre adresse email <span class="required-star">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($email ?? '') ?>" 
                           placeholder="Email" 
                           required>
                </div>

                <div class="mb-3">
                    <label for="sujet" class="form-label">
                        Sujet du message <span class="required-star">*</span>
                    </label>
                    <input type="text" class="form-control" id="sujet" name="sujet" 
                           value="<?= htmlspecialchars($sujet_msg ?? '') ?>" 
                           placeholder="Ex: Suivi de ma commande" 
                           required>
                </div>

                <div class="mb-4">
                    <label for="message" class="form-label">
                        Votre message <span class="required-star">*</span>
                    </label>
                    <textarea class="form-control" id="message" name="message" 
                              rows="5" 
                              placeholder="Détaillez votre message ici..." 
                              required><?= htmlspecialchars($message_txt ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-send">
                    <i class="bi bi-send"></i> Envoyer le message
                </button>

                <!-- Informations de contact -->
                <div class="contact-info">
                    <div class="contact-info-item">
                        <i class="bi bi-telephone"></i>
                        <a href="tel:+24205532233">+242 05 532 22 33</a>
                    </div>
                  
                    <div class="contact-info-item">
                        <i class="bi bi-clock"></i>
                        <span>Lun-Ven : 8h - 18h | Sam : 9h - 13h</span>
                    </div>
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
        const inputs = form.querySelectorAll('input[required], textarea[required]');
        
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
        
        // Validation email spécifique
        const emailInput = document.getElementById('email');
        if (emailInput) {
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
        }
    });
    </script>
</body>
</html>
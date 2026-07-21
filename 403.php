<?php
// 403.php - Page d'accès interdit - Version moderne
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès interdit | EMMA+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            height: 100vh;
            background: #0d1117;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .container-403 {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card-403 {
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .code-403 {
            font-size: 140px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1;
            letter-spacing: 8px;
            margin-bottom: 10px;
            text-shadow: 0 4px 30px rgba(255, 255, 255, 0.05);
        }

        .icon-lock {
            font-size: 48px;
            color: #8b949e;
            margin-bottom: 20px;
            display: block;
        }

        .title-403 {
            font-size: 18px;
            font-weight: 600;
            color: #f0f6fc;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 12px;
        }

        .message-403 {
            font-size: 15px;
            color: #8b949e;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        /* Barre de progression */
        .progress-wrapper {
            max-width: 220px;
            margin: 0 auto 20px;
        }

        .progress-track {
            width: 100%;
            height: 3px;
            background: #21262d;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: #58a6ff;
            border-radius: 4px;
            transition: width 0.1s linear;
        }

        .progress-text {
            font-size: 12px;
            color: #484f58;
            margin-top: 8px;
            letter-spacing: 1px;
        }

        .footer-403 {
            margin-top: 35px;
            font-size: 12px;
            color: #30363d;
        }

        .footer-403 i {
            margin-right: 4px;
        }

        /* Animation subtile */
        @keyframes pulse {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        .dot-loader {
            display: inline-block;
            animation: pulse 1.2s ease-in-out infinite;
        }
        .dot-loader:nth-child(2) { animation-delay: 0.2s; }
        .dot-loader:nth-child(3) { animation-delay: 0.4s; }
    </style>
</head>
<body>

    <div class="container-403">
        <div class="card-403">

            <!-- 403 en grand blanc -->
            <div class="code-403">403</div>

            <!-- Icône cadenas (moderne) -->
            <span class="icon-lock"><i class="bi bi-shield-lock"></i></span>

            <!-- Titre -->
            <div class="title-403">Impossible d'accéder à cette page</div>

            <!-- Message -->
            <div class="message-403">
                Vous n'avez pas les autorisations nécessaires<br>
                pour consulter cette ressource.
            </div>

            <!-- Barre de progression avec redirection automatique -->
            <div class="progress-wrapper">
                <div class="progress-track">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="progress-text">
                    Retouner en arrière
                    <span class="dot-loader">.</span>
                    <span class="dot-loader">.</span>
                    <span class="dot-loader">.</span>
                </div>
            </div>

            <!-- Footer avec date -->
            <div class="footer-403">
                <i class="bi bi-clock"></i> <?= date('d/m/Y \à H:i') ?>
            </div>

        </div>
    </div>

    <script>
        (function() {
            'use strict';

            const REDIRECT_DELAY = 5000; // 5 secondes
            const INTERVAL_STEP = 50; // ms
            const totalSteps = REDIRECT_DELAY / INTERVAL_STEP;
            let currentStep = 0;

            const progressBar = document.getElementById('progressBar');

            // Met à jour la barre de progression
            function updateProgress() {
                currentStep++;
                const percent = Math.min((currentStep / totalSteps) * 100, 100);
                progressBar.style.width = percent + '%';

                if (currentStep < totalSteps) {
                    requestAnimationFrame(updateProgress);
                }
            }

            // Lance l'animation après un court délai pour un rendu fluide
            setTimeout(() => {
                requestAnimationFrame(updateProgress);
            }, 200);

            // Redirection automatique après REDIRECT_DELAY ms
            setTimeout(function() {
                window.location.href = 'index.php';
            }, REDIRECT_DELAY);

        })();
    </script>

</body>
</html>
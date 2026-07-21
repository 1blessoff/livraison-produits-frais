<?php
// views/public/cgu.php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions Générales d'Utilisation | EMMA+</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #fcfcfc; 
            color: #111111; 
            line-height: 1.6;
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
        }
        .nav-link-custom:hover { 
            text-decoration: underline; 
        }
        
        .page-header { 
            background-color: #ffffff; 
            border-bottom: 1px solid #e5e5e5; 
            padding: 40px 0; 
            margin-bottom: 50px; 
        }
        
        /* Conteneur de texte juridique style éditorial */
        .cgu-container { 
            background: #ffffff; 
            border: 1px solid #e5e5e5; 
            padding: 40px; 
        }
        
        .cgu-section-title { 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-size: 16px; 
            margin-top: 30px; 
            margin-bottom: 15px; 
            border-bottom: 1px solid #111; 
            padding-bottom: 5px; 
        }
        
        p, li { 
            font-size: 14px; 
            color: #444444; 
        }
        
        ul { 
            padding-left: 20px; 
        }
        
        .footer-text {
            font-size: 12px;
            color: #888;
            text-align: center;
            margin-top: 50px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">EMMA+</a>
            <div class="ms-auto d-flex align-items-center gap-4">
                <a href="boutique.php" class="nav-link-custom">Boutique</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/client/dashboard" class="nav-link-custom">Mon Compte</a>
                <?php else: ?>
                    <a href="connexion.php" class="nav-link-custom">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <div class="container text-center">
            <span class="text-muted small text-uppercase" style="letter-spacing: 2px;">Cadre Juridique</span>
            <h1 class="m-0 mt-2" style="font-weight: 900; text-transform: uppercase; font-size: 28px; letter-spacing: 1px;">Conditions Générales d'Utilisation</h1>
            <p class="text-muted small mb-0 mt-2">En vigueur au 1er juin 2026</p>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="cgu-container shadow-sm">
                    <p>
                        Bienvenue sur <strong>EMMA+</strong>. Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») encadrent juridiquement l’accès et l’utilisation des services de notre site web. L'accès au site par tout utilisateur implique l'acceptation pleine et entière des présentes CGU.
                    </p>

                    <h2 class="cgu-section-title">1. Mentions Légales</h2>
                    <p>
                        Le site <strong>EMMA+</strong> est une plateforme e-commerce de distribution de produits maraîchers et frais, éditée et gérée par les équipes d'EMMA+. Pour toute question, vous pouvez contacter notre service support via l'espace client ou à l'adresse email de contact fournie lors de vos confirmations de commande.
                    </p>

                    <h2 class="cgu-section-title">2. Accès au Site et Services</h2>
                    <p>
                        Le site est accessible gratuitement en tout lieu à tout utilisateur ayant un accès à Internet. Tous les frais supportés par l'utilisateur pour accéder au service (matériel informatique, logiciels, connexion Internet) sont à sa charge.
                    </p>
                    <p>
                        Notre boutique en ligne permet à l'utilisateur de :
                    </p>
                    <ul>
                        <li>Consulter le catalogue de produits frais disponibles.</li>
                        <li>Ajouter des articles à un panier d'achat virtuel.</li>
                        <li>Passer commande et suivre l'historique de ses achats via un espace client sécurisé.</li>
                    </ul>

                    <h2 class="cgu-section-title">3. Comptes Utilisateurs et Sécurité</h2>
                    <p>
                        Pour passer une commande, l'utilisateur doit obligatoirement se créer un compte client. Lors de la création, il s'engage à fournir des informations exactes, sincères et à jour (Nom, Prénom, Email, Téléphone).
                    </p>
                    <p>
                        L'utilisateur est seul responsable de la protection de son mot de passe. À titre de rappel, <strong>le client n'est pas autorisé à modifier directement son email de connexion ni son numéro de téléphone</strong> depuis ses paramètres pour des motifs de sécurité. En cas de force majeure, il devra contacter notre support technique.
                    </p>

                    <h2 class="cgu-section-title">4. Propriété Intellectuelle</h2>
                    <p>
                        Les marques, logos, signes ainsi que tous les contenus du site (textes, images, graphismes, design noir et blanc) font l'objet d'une protection par le Code de la propriété intellectuelle.
                    </p>
                    <p>
                        L'utilisateur doit solliciter l'autorisation préalable du site pour toute reproduction, publication ou copie des différents contenus. Toute utilisation à des fins commerciales est strictement interdite.
                    </p>

                    <h2 class="cgu-section-title">5. Responsabilité et Limites</h2>
                    <p>
                        Les sources des informations diffusées sur le site EMMA+ sont réputées fiables. Toutefois, le site se réserve la faculté d'une non-garantie de la fiabilité des sources. Les photographies des produits frais sont fournies à titre illustratif et ne sont pas contractuelles.
                    </p>
                    <p>
                        Tout événement dû à un cas de force majeure (panne de serveur, coupure internet réseau, bug applicatif) ayant pour conséquence un dysfonctionnement du site ou une interruption des services n'engage pas la responsabilité d'EMMA+.
                    </p>

                    <h2 class="cgu-section-title">6. Processus de Commande et Paiement</h2>
                    <p>
                        La validation d'une commande sur le site EMMA+ entraîne une réservation des stocks. Le paiement des produits s'effectue intégralement en espèces ou par Mobile Money <strong>au moment de la livraison effective</strong> des marchandises à l'adresse indiquée, sauf mention contraire.
                    </p>

                    <h2 class="cgu-section-title">7. Évolution des Conditions Générales</h2>
                    <p>
                        Le site EMMA+ se réserve le droit de modifier les clauses de ces CGU à tout moment et sans justification, afin de les adapter aux évolutions du site et des législations en vigueur.
                    </p>
                    
                    <div class="footer-text">
                        © <?= date('Y') ?> EMMA+. Tous droits réservés. Épuré & Engagé.
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="../../index.php" class="text-dark small fw-bold text-uppercase text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Retourner à la page d'accueil 
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
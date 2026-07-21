<?php
// views/client/historique_commande.php
session_start();

// 1. PROTECTION ACCÈS CLIENT
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../public/connexion.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$user_id = $_SESSION['user_id'];

// 2. RÉCUPÉRATION DE L'HISTORIQUE
$commandes = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $commandes = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Erreur lors du chargement de l'historique : " . $e->getMessage());
}

// ============================================
// 3. FILTRE PAR STATUT
// ============================================
$filtre_statut = $_GET['filtre'] ?? 'toutes';
$commandes_filtrees = [];

foreach ($commandes as $cmd) {
    if ($filtre_statut === 'toutes' || $cmd['statut'] === $filtre_statut) {
        $commandes_filtrees[] = $cmd;
    }
}


// ============================================
// 4. FILTRE PAR DATE (mois, jour, semaine, année)
// ============================================
$filtre_date = $_GET['date_filtre'] ?? 'toutes';
$date_valeur = $_GET['date_valeur'] ?? '';
$commandes_filtrees_date = [];

// Définir la période selon le filtre
$date_condition = "";
$current_year = date('Y');
$current_month = date('m');
$current_week = date('W');

switch ($filtre_date) {
    case 'jour':
        if (!empty($date_valeur)) {
            $date_condition = "DATE(date_commande) = '$date_valeur'";
        } else {
            $date_condition = "DATE(date_commande) = CURDATE()";
        }
        break;
    case 'semaine':
        if (!empty($date_valeur)) {
            // Format: YYYY-WXX
            $year = substr($date_valeur, 0, 4);
            $week = substr($date_valeur, 6);
            $date_condition = "YEARWEEK(date_commande, 1) = YEARWEEK('$year-$week-1', 1)";
        } else {
            $date_condition = "YEARWEEK(date_commande, 1) = YEARWEEK(CURDATE(), 1)";
        }
        break;
    case 'mois':
        if (!empty($date_valeur)) {
            $date_condition = "DATE_FORMAT(date_commande, '%Y-%m') = '$date_valeur'";
        } else {
            $date_condition = "DATE_FORMAT(date_commande, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }
        break;
    case 'annee':
        if (!empty($date_valeur)) {
            $date_condition = "YEAR(date_commande) = '$date_valeur'";
        } else {
            $date_condition = "YEAR(date_commande) = YEAR(CURDATE())";
        }
        break;
    default:
        $date_condition = "1=1";
}

// Appliquer le filtre date sur les commandes déjà filtrées par statut
foreach ($commandes_filtrees as $cmd) {
    // Vérifier si la commande correspond au filtre date
    $stmtCheck = $pdo->prepare("SELECT * FROM commandes WHERE id = ? AND $date_condition");
    $stmtCheck->execute([$cmd['id']]);
    if ($stmtCheck->fetch()) {
        $commandes_filtrees_date[] = $cmd;
    }
}

// Remplacer les commandes filtrées par celles avec filtre date
$commandes_filtrees = $commandes_filtrees_date;

// Générer les options pour les selects de date
$mois_options = [];
$annee_options = [];
$semaine_options = [];

for ($i = 1; $i <= 12; $i++) {
    $mois_options[] = sprintf("%02d", $i);
}
for ($i = $current_year - 2; $i <= $current_year; $i++) {
    $annee_options[] = $i;
}
for ($i = 1; $i <= 52; $i++) {
    $semaine_options[] = sprintf("%02d", $i);
}




// Compter les commandes par statut
$nb_en_attente = 0;
$nb_confirmee = 0;
$nb_en_livraison = 0;
$nb_livree = 0;
$nb_annulee = 0;

foreach ($commandes as $cmd) {
    if ($cmd['statut'] === 'en_attente') $nb_en_attente++;
    elseif ($cmd['statut'] === 'confirmee') $nb_confirmee++;
    elseif ($cmd['statut'] === 'en_livraison') $nb_en_livraison++;
    elseif ($cmd['statut'] === 'livree') $nb_livree++;
    elseif ($cmd['statut'] === 'annulee') $nb_annulee++;
}

// Traduction des statuts
$statut_texte = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'en_livraison' => 'En livraison',
    'livree' => 'Livrée',
    'annulee' => 'Annulée'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Historique de Commandes | EMMA+</title>
    

    <!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #fcfcfc; color: #111111; }
        .navbar-brand { font-weight: 900; font-size: 24px; letter-spacing: 3px; color: #111111 !important; text-decoration: none; }
        .nav-link-custom { font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; color: #111; text-decoration: none; }
        .nav-link-custom:hover { text-decoration: underline; }
        
        .page-header { background-color: #ffffff; border-bottom: 1px solid #e5e5e5; padding: 25px 0; margin-bottom: 40px; }
        
        .order-card { background: #ffffff; border: 1px solid #e5e5e5; padding: 20px; margin-bottom: 20px; cursor: pointer; transition: all 0.2s; }
        .order-card:hover { border-color: #111; transform: translateY(-2px); }
        
        .order-header { border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 15px; font-size: 13px; }
        .order-ref { font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; font-size: 15px; }
        
        .status-badge { border-radius: 0; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 12px; display: inline-block; }
        .status-attente { background-color: #111111; color: #ffffff; }
        .status-confirmee { background-color: #17a2b8; color: #ffffff; }
        .status-cours { background-color: #666666; color: #ffffff; }
        .status-livre { background-color: #d1e7dd; color: #0f5132; border: 1px solid #0f5132; }
        .status-annulee { background-color: #f8d7da; color: #842029; border: 1px solid #842029; }
        
        .btn-asos-sm { border: 1px solid #111111; background: transparent; color: #111111; border-radius: 0 !important; padding: 6px 15px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; transition: all 0.2s; display: inline-block; }
        .btn-asos-sm:hover { background: #111111; color: #ffffff; }
        
        /* Boutons de filtre */
        .filter-btn { border: 1px solid #111; background: transparent; color: #111; border-radius: 0; padding: 6px 15px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; display: inline-block; }
        .filter-btn.active { background: #111; color: white; }
        .filter-btn:hover { background: #111; color: white; }
        .filter-badge { background: #e9ecef; color: #111; border-radius: 0; margin-left: 5px; padding: 2px 6px; font-size: 10px; }
        .filter-btn.active .filter-badge { background: white; color: #111; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand" href="historique_commande.php">EMMA+</a>
            <div class="ms-auto d-flex align-items-center gap-4">
                <a href="dashboard.php" class="nav-link-custom">Tableau de bord</a>
                <a href="../public/boutique.php" class="nav-link-custom">Boutique</a>
                <a href="../public/deconnexion.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <!-- ENTÊTE -->
    <div class="page-header">
        <div class="container">
            <span class="text-muted small text-uppercase" style="letter-spacing: 1px;">Mon Espace</span>
            <h1 class="m-0" style="font-weight: 900; text-transform: uppercase; font-size: 24px;">Historique des commandes</h1>
        </div>
    </div>

    <!-- CONTENU -->
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <!-- FILTRES PAR STATUT -->
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filtre=toutes" class="filter-btn <?= $filtre_statut === 'toutes' ? 'active' : '' ?>">
                            Toutes <span class="filter-badge"><?= count($commandes) ?></span>
                        </a>
                        <a href="?filtre=en_attente" class="filter-btn <?= $filtre_statut === 'en_attente' ? 'active' : '' ?>">
                            En attente <span class="filter-badge"><?= $nb_en_attente ?></span>
                        </a>
                        <a href="?filtre=en_livraison" class="filter-btn <?= $filtre_statut === 'en_livraison' ? 'active' : '' ?>">
                            En livraison <span class="filter-badge"><?= $nb_en_livraison ?></span>
                        </a>
                        <a href="?filtre=livree" class="filter-btn <?= $filtre_statut === 'livree' ? 'active' : '' ?>">
                            Livrée <span class="filter-badge"><?= $nb_livree ?></span>
                        </a>
                        <a href="?filtre=annulee" class="filter-btn <?= $filtre_statut === 'annulee' ? 'active' : '' ?>">
                            Annulée <span class="filter-badge"><?= $nb_annulee ?></span>
                        </a>
                    </div>
                </div>


                <!-- FILTRES PAR DATE -->
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted small text-uppercase fw-bold">Période :</span>
                        <a href="?filtre=<?= $filtre_statut ?>&date_filtre=toutes" class="filter-btn <?= $filtre_date === 'toutes' ? 'active' : '' ?>">Toutes</a>
                        <a href="?filtre=<?= $filtre_statut ?>&date_filtre=jour" class="filter-btn <?= $filtre_date === 'jour' ? 'active' : '' ?>">Aujourd'hui</a>
                        <a href="?filtre=<?= $filtre_statut ?>&date_filtre=semaine" class="filter-btn <?= $filtre_date === 'semaine' ? 'active' : '' ?>">Cette semaine</a>
                        <a href="?filtre=<?= $filtre_statut ?>&date_filtre=mois" class="filter-btn <?= $filtre_date === 'mois' ? 'active' : '' ?>">Ce mois</a>
                        <a href="?filtre=<?= $filtre_statut ?>&date_filtre=annee" class="filter-btn <?= $filtre_date === 'annee' ? 'active' : '' ?>">Cette année</a>
                    </div>
                    
                    <!-- Sélecteurs de période personnalisés -->
                    <?php if ($filtre_date === 'jour'): ?>
                    <div class="mt-3">
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="filtre" value="<?= $filtre_statut ?>">
                            <input type="hidden" name="date_filtre" value="jour">
                            <label class="small fw-bold">Date :</label>
                            <input type="date" name="date_valeur" class="form-control form-control-sm rounded-0" style="width: auto;" value="<?= htmlspecialchars($date_valeur) ?>">
                            <button type="submit" class="btn btn-sm btn-dark rounded-0">Filtrer</button>
                        </form>
                    </div>


                    <?php elseif ($filtre_date === 'semaine'): ?>
                    <div class="mt-3">
                        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="filtre" value="<?= $filtre_statut ?>">
                            <input type="hidden" name="date_filtre" value="semaine">
                            <label class="small fw-bold">Semaine :</label>
                            <select name="date_valeur" class="form-select form-select-sm rounded-0" style="width: auto;">
                                <option value="">Semaine en cours</option>
                                <?php for ($w = 1; $w <= 52; $w++): ?>
                                    <option value="<?= $current_year ?>-W<?= sprintf("%02d", $w) ?>" <?= $date_valeur == $current_year . '-W' . sprintf("%02d", $w) ? 'selected' : '' ?>>
                                        Semaine <?= $w ?> (<?= $current_year ?>)
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-dark rounded-0">Filtrer</button>
                        </form>
                    </div>

                    <?php elseif ($filtre_date === 'mois'): ?>
                    <div class="mt-3">
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="filtre" value="<?= $filtre_statut ?>">
                            <input type="hidden" name="date_filtre" value="mois">
                            <label class="small fw-bold">Mois :</label>
                            <select name="date_valeur" class="form-select form-select-sm rounded-0" style="width: auto;">
                                <option value="">Mois en cours</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $current_year ?>-<?= sprintf("%02d", $m) ?>" <?= $date_valeur == $current_year . '-' . sprintf("%02d", $m) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?> <?= $current_year ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-dark rounded-0">Filtrer</button>
                        </form>
                    </div>
                    <?php elseif ($filtre_date === 'annee'): ?>
                    <div class="mt-3">
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="filtre" value="<?= $filtre_statut ?>">
                            <input type="hidden" name="date_filtre" value="annee">
                            <label class="small fw-bold">Année :</label>
                            <select name="date_valeur" class="form-select form-select-sm rounded-0" style="width: auto;">
                                <option value="">Année en cours</option>
                                <?php for ($y = $current_year - 3; $y <= $current_year; $y++): ?>
                                    <option value="<?= $y ?>" <?= $date_valeur == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-dark rounded-0">Filtrer</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>



                

                <!-- AFFICHAGE DES COMMANDES FILTRÉES -->
                <?php if (empty($commandes_filtrees)): ?>
                    <div class="text-center py-5 border bg-white">
                        <i class="bi bi-box-seam fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted small mb-4">
                            <?php if ($filtre_statut !== 'toutes'): ?>
                                Aucune commande avec le statut "<?= $statut_texte[$filtre_statut] ?? $filtre_statut ?>" pour le moment.
                            <?php else: ?>
                                Vous n'avez pas encore passé de commande sur EMMA+.
                            <?php endif; ?>
                        </p>
                        <a href="../public/boutique.php" class="btn-asos-sm px-4 py-2">Faire des achats</a>
                    </div>
                <?php else: ?>
                    
                    <?php foreach ($commandes_filtrees as $cmd): ?>
                        <div class="order-card shadow-sm" data-commande-id="<?= $cmd['id'] ?>">
                            <div class="order-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="text-muted small">Identifiant Commande :</span> 
                                    <span class="order-ref">#<?= $cmd['id'] ?></span>
                                    
                                    <?php if(!empty($cmd['notes'])): ?>
                                        <span class="mx-2 text-muted">|</span>
                                        <span class="text-muted small italic"><i class="bi bi-sticky me-1"></i> Note laissée</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php 
                                        $badgeClass = 'status-attente';
                                        $statut_affichage = '';
                                        if($cmd['statut'] === 'en_attente') { 
                                            $badgeClass = 'status-attente'; 
                                            $statut_affichage = 'En attente';
                                        } elseif($cmd['statut'] === 'en_livraison') { 
                                            $badgeClass = 'status-cours'; 
                                            $statut_affichage = 'En livraison';
                                        } elseif($cmd['statut'] === 'livree') { 
                                            $badgeClass = 'status-livre'; 
                                            $statut_affichage = 'Livrée';
                                        } elseif($cmd['statut'] === 'annulee') { 
                                            $badgeClass = 'status-annulee'; 
                                            $statut_affichage = 'Annulée';
                                        }
                                    ?>
                                    <span class="status-badge <?= $badgeClass ?>"><?= $statut_affichage ?></span>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center pt-2">
                                <div>
                                    <span class="text-muted small text-uppercase d-block" style="letter-spacing: 0.5px;">Total Payé</span>
                                    <span class="fw-bold fs-5"><?= number_format($cmd['total'], 0, ',', ' ') ?> FCFA</span>
                                    <?php if(isset($cmd['date_commande'])): ?>
                                        <p class="text-muted small mb-0 mt-1">
                                            <i class="bi bi-calendar me-1"></i> 
                                            <?= date('d/m/Y', strtotime($cmd['date_commande'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn-asos-sm voir-details-btn" data-commande-id="<?= $cmd['id'] ?>">
                                        <i class="bi bi-eye me-1"></i> Voir les articles
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <div class="mt-4">
                    <a href="dashboard.php" class="text-dark small fw-bold text-uppercase text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Retour au tableau de bord
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL DÉTAILS COMMANDE -->
    <div class="modal fade modal-custom" id="detailsCommandeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-uppercase" style="font-size: 16px; letter-spacing: 1px;">
                        <i class="bi bi-receipt me-2"></i> Détails de la commande <span id="modal_commande_id"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" id="modal_commande_contenu">
                    <div class="text-center py-4">
                        <div class="spinner-border text-dark" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2 text-muted small">Chargement des détails...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-asos-sm" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Attendre que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function() {
            // Sélectionner tous les boutons "Voir les articles"
            const voirDetailsBtns = document.querySelectorAll('.voir-details-btn');
            
            voirDetailsBtns.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const commandeId = this.getAttribute('data-commande-id');
                    ouvrirDetailsCommande(commandeId);
                });
            });
            
            // Permettre aussi le clic sur toute la carte
            const orderCards = document.querySelectorAll('.order-card');
            orderCards.forEach(function(card) {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('.voir-details-btn')) {
                        const commandeId = this.getAttribute('data-commande-id');
                        ouvrirDetailsCommande(commandeId);
                    }
                });
            });
        });
        
        // Fonction pour ouvrir le modal et charger les détails en AJAX
        function ouvrirDetailsCommande(commandeId) {
            if (!commandeId) {
                console.error('ID de commande manquant');
                return;
            }
            
            // Afficher le modal
            const modal = new bootstrap.Modal(document.getElementById('detailsCommandeModal'));
            modal.show();
            
            // Mettre à jour le titre
            document.getElementById('modal_commande_id').innerHTML = '#' + commandeId;
            
            // Afficher le loader
            document.getElementById('modal_commande_contenu').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-dark" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2 text-muted small">Chargement des détails...</p>
                </div>
            `;
            
            // Charger les détails via AJAX
            fetch('ajax_details_commande.php?id=' + commandeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Construire le HTML des détails
                        let html = `
                            <div class="mb-4">
                                <h6 class="fw-bold text-uppercase small mb-3">Récapitulatif</h6>
                                <div class="info-line d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted small">Date de commande</span>
                                    <span class="fw-semibold">${data.date}</span>
                                </div>
                                <div class="info-line d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted small">Statut</span>
                                    <span class="fw-semibold">${data.statut}</span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="fw-bold text-uppercase small mb-3">Adresse de livraison</h6>
                                <p class="small mb-0">${data.adresse}</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold text-uppercase small mb-3">Articles commandés</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Image</th>
                                                <th>Produit</th>
                                                <th class="text-center">Qté</th>
                                                <th class="text-end">Prix unitaire</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;
                        
                        // Ajouter chaque article
                        data.articles.forEach(article => {
                            html += `
                                <tr>
                                    <td><img src="${article.image}" class="product-img-mini" style="width: 50px; height: 60px; object-fit: cover; border: 1px solid #eee;" onerror="this.src='../../uploads/default_product.jpg'"></td>
                                    <td>${article.nom}</td>
                                    <td class="text-center">x${article.quantite}</td>
                                    <td class="text-end">${article.prix} FCFA</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total</td>
                                                <td class="text-end fw-bold">${data.total} FCFA</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('modal_commande_contenu').innerHTML = html;
                    } else {
                        document.getElementById('modal_commande_contenu').innerHTML = `
                            <div class="text-center py-4">
                                <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                                <p class="mt-2 text-danger">${data.message}</p>
                                <p class="text-muted small mt-2">ID commande: ${commandeId}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('modal_commande_contenu').innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                            <p class="mt-2 text-danger">Erreur de connexion au serveur</p>
                        </div>
                    `;
                });
        }
    </script>
    <style>
        .info-line:last-child { border-bottom: none; }
        .product-img-mini { width: 50px; height: 60px; object-fit: cover; border: 1px solid #eee; background: #f9f9f9; }
        .modal-custom .modal-content { border-radius: 0; border: 1px solid #111; }
        .modal-custom .modal-header { border-bottom: 1px solid #e5e5e5; padding: 20px 25px; background: #111; color: white; }
        .modal-custom .modal-header .btn-close { filter: invert(1); }
        .modal-custom .modal-body { padding: 25px; }
        .modal-custom .modal-footer { border-top: 1px solid #e5e5e5; padding: 15px 25px; }
    </style>

    
</body>
</html>
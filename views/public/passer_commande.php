<?php
// views/public/passer_commande.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit();
}
if (empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php'; 
$id_user = $_SESSION['user_id'];

// Récupération des adresses (Ajuste user_id ou id_user selon ta BDD)
try {
    $stmtAdresses = $pdo->prepare("SELECT * FROM adresses WHERE user_id = ?");
    $stmtAdresses->execute([$id_user]);
    $adresses = $stmtAdresses->fetchAll();
} catch (\PDOException $e) {
    $adresses = []; // En cas d'erreur de colonne, on crée un tableau vide pour éviter le crash
}

// Calcul du total pour l'affichage
$total_panier = 0;
foreach ($_SESSION['panier'] as $id_produit => $donnees) {
    $stmtP = $pdo->prepare("SELECT prix FROM produits WHERE id = ?");
    $stmtP->execute([$id_produit]);
    $prix = $stmtP->fetchColumn();
    if ($prix) {
        $qte_reelle = is_array($donnees) ? ($donnees['quantite'] ?? $donnees['qte'] ?? 1) : $donnees;
        $total_panier += (float)$prix * (int)$qte_reelle;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Validation de la commande | EMMA+</title>

<!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">

    <div class="container" style="max-width: 600px;">
        <div class="card p-4 border-0 shadow-sm rounded-0 bg-white">
            <h2 class="text-uppercase fw-bold mb-4 text-center" style="font-size: 20px;">Finaliser ma commande</h2>

            <form action="validation_commande.php" method="POST">
                
                <!-- SECTION 1 : L'ADRESSE -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase small">Adresse de livraison :</label>
                    <input type="text" name="nouvelle_adresse_directe" class="form-control rounded-0 mb-2" placeholder="Votre adresse ex: Mpita" required>
                    
                    <label class="form-label fw-bold text-uppercase small">Ville de livraison :</label>
                    <input type="text" name="nouvelle_ville_directe" class="form-control rounded-0" placeholder="Votre ville ex:pointe noire" required>
                </div>
                
                
                <!-- AJOUT  ZONE DE LIVRAISON ICI  -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase small">Zone de livraison</label>
                    <select name="zone_livraison" class="form-select rounded-0" required>
                        <option value="pointe_noire">Pointe-Noire - 1 500 FCFA</option>
                        <option value="brazzaville">Brazzaville - 2 500 FCFA</option>
                        <option value="autres">Autres villes - 3 500 FCFA</option>
                    </select>
                    <small class="text-muted">Livraison gratuite dès 50 000 FCFA d'achat</small>
                </div>
                
                
                
                
                
                

                <!-- SECTION 2 : LE TOTAL -->
                <div class="d-flex justify-content-between align-items-center border-top border-bottom py-3 mb-4">
                    <span class="fw-bold text-uppercase small">Total à payer :</span>
                    <span class="fw-bold text-danger fs-5"><?= number_format($total_panier, 0, ',', ' ') ?> FCFA</span>
                </div>

                <!-- LE BOUTON : Plus aucune sécurité bloquante, il est cliquable ! -->
                <button type="submit" class="btn btn-dark rounded-0 w-100 py-3 text-uppercase fw-bold">
                    Confirmer et commander
                </button>

            </form>
            
            <div class="text-center mt-3">
                <a href="panier.php" class="text-muted small text-decoration-none">← Retourner au panier</a>
            </div>
        </div>
    </div>

</body>
</html>
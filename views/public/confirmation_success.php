<?php
// views/public/confirmation_success.php
session_start();

if (!isset($_GET['id'])) {
    header('Location: boutique.php');
    exit();
}

$commande_id = htmlspecialchars($_GET['id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commande Réussie | EMMA+</title>

    <!--en local-->
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
</head>
<body class="bg-light text-center py-5">

    <div class="container" style="max-width: 600px;">
        <div class="card p-5 border-0 shadow-sm rounded-0 bg-white">
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
            <h1 class="text-success fw-bold mb-3">Merci pour votre commande !</h1>
            <p class="text-muted">
                Votre commande a bien été prise en compte.
            </p>
            <hr class="my-4">

            <a href="../client/dashboard.php" class="btn btn-dark rounded-0 text-uppercase fw-bold w-100 py-3 mb-2">
                <i class="bi bi-box-seam me-2"></i>Voir mes commandes
            </a>
            
            <a href="../public/boutique.php" class="btn btn-outline-secondary rounded-0 text-uppercase fw-bold w-100 py-2">
                <i class="bi bi-bag me-2"></i>Continuer mes achats
            </a>
        </div>
    </div>

</body>
</html>
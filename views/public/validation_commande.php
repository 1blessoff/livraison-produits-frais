<?php
// views/public/validation_commande.php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$id_user = $_SESSION['user_id'];

// 1. GESTION DE L'ADRESSE
$adresse_id = $_POST['adresse_id'] ?? null;

if (empty($adresse_id)) {
    $txt_adresse = trim($_POST['nouvelle_adresse_directe'] ?? '');
    $txt_ville = trim($_POST['nouvelle_ville_directe'] ?? '');

    if (!empty($txt_adresse) && !empty($txt_ville)) {
        try {
            $stmtIns = $pdo->prepare("INSERT INTO adresses (user_id, adresse, ville) VALUES (?, ?, ?)");
            $stmtIns->execute([$id_user, $txt_adresse, $txt_ville]);
            $adresse_id = $pdo->lastInsertId();
        } catch (\PDOException $e) {
            die("Erreur enregistrement adresse : " . $e->getMessage());
        }
    }
}

if (empty($adresse_id)) {
    die("Erreur : L'adresse de livraison est obligatoire.");
}

// ============================================
// ZONE DE LIVRAISON ET FRAIS
// ============================================
$zone_livraison = $_POST['zone_livraison'] ?? 'pointe_noire';
$_SESSION['zone_livraison'] = $zone_livraison;

$frais_par_zone = [
    'pointe_noire' => 1500,
    'brazzaville' => 2500,
    'autres' => 3500
];

// 2. CALCUL DU TOTAL PANIER
$total_panier = 0;
$items_valides = [];

foreach ($_SESSION['panier'] as $id_produit => $donnees) {
    $stmtProd = $pdo->prepare("SELECT id, nom, prix, stock FROM produits WHERE id = ? AND actif = 1");
    $stmtProd->execute([$id_produit]);
    $prod = $stmtProd->fetch();

    if ($prod) {
        $quantite = is_array($donnees) ? ($donnees['quantite'] ?? 1) : $donnees;
        $quantite = max(1, intval($quantite));

        if ($prod['stock'] < $quantite) {
            die("Erreur : Stock insuffisant pour le produit '" . htmlspecialchars($prod['nom']) . "'.");
        }

        $total_panier += $prod['prix'] * $quantite;
        $items_valides[] = [
            'id' => $prod['id'],
            'nom' => $prod['nom'],
            'prix' => $prod['prix'],
            'quantite' => $quantite
        ];
    }
}

if (empty($items_valides)) {
    die("Erreur : Aucun produit valide dans le panier.");
}

// 3. AJOUT DES FRAIS DE LIVRAISON
$frais_livraison = ($total_panier >= 50000) ? 0 : ($frais_par_zone[$zone_livraison] ?? 3500);
$total_commande = $total_panier + $frais_livraison;

// 4. ENREGISTREMENT DE LA COMMANDE
try {
    $pdo->beginTransaction();

    $stmtCmd = $pdo->prepare("INSERT INTO commandes (user_id, adresse_id, total, statut, date_commande) VALUES (?, ?, ?, 'en_attente', NOW())");
    $stmtCmd->execute([$id_user, $adresse_id, $total_commande]);
    $commande_id = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO commande_items (commande_id, produit_id, quantite, prix) VALUES (?, ?, ?, ?)");
    $stmtUpdateStock = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?");

    foreach ($items_valides as $item) {
        $stmtItem->execute([$commande_id, $item['id'], $item['quantite'], $item['prix']]);
        $stmtUpdateStock->execute([$item['quantite'], $item['id']]);
    }

    $stmtClean = $pdo->prepare("DELETE FROM paniers_sauvegardes WHERE user_id = ?");
    $stmtClean->execute([$id_user]);

    $pdo->commit();

    // ============================================
    // 5. ENVOI DE L'EMAIL STRUCTURÉ
    // ============================================
    
    // Récupérer l'email du client
    $stmtUser = $pdo->prepare("SELECT email, prenom, nom FROM users WHERE id = ?");
    $stmtUser->execute([$id_user]);
    $user = $stmtUser->fetch();

    $user_email = $user['email'] ?? '';
    $user_prenom = $user['prenom'] ?? 'Client';
    $user_nom = $user['nom'] ?? '';

    $email_envoye = false;

    // Envoyer l'email si l'adresse existe
    if (!empty($user_email)) {
        // ============================================
        // CONSTRUCTION DU MESSAGE STRUCTURÉ
        // ============================================
        
        // Récupérer l'adresse de livraison
        $stmtAdresse = $pdo->prepare("SELECT adresse, ville FROM adresses WHERE id = ?");
        $stmtAdresse->execute([$adresse_id]);
        $adresse = $stmtAdresse->fetch();
        $adresse_livraison = $adresse['adresse'] . ', ' . $adresse['ville'];

        // Construction du message
        $sujet = "Confirmation de votre commande N°" . $commande_id . " - EMMA+";
        
        // En-tête
        $message_mail = "═══════════════════════════════════════════════════\n";
        $message_mail .= "                EMMA+ - COMMANDE CONFIRMÉE\n";
        $message_mail .= "═══════════════════════════════════════════════════\n\n";
        
        // Salutation
        $message_mail .= "Bonjour " . $user_prenom . " " . $user_nom . ",\n\n";
        $message_mail .= "Nous avons le plaisir de vous confirmer la réception de votre commande.\n";
        $message_mail .= "Voici les détails de votre commande :\n\n";
        
        // Informations de la commande
        $message_mail .= "┌─────────────────────────────────────────────────┐\n";
        $message_mail .= "│  DÉTAILS DE LA COMMANDE                    │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  N° Commande  : " . str_pad($commande_id, 20) . " │\n";
        $message_mail .= "│  Date         : " . date('d/m/Y H:i') . "            │\n";
        $message_mail .= "│  Statut       : En attente de validation      │\n";
        $message_mail .= "│  Zone         : " . str_pad(ucfirst(str_replace('_', ' ', $zone_livraison)), 18) . " │\n";
        $message_mail .= "└─────────────────────────────────────────────────┘\n\n";
        
        // Adresse de livraison
        $message_mail .= "┌─────────────────────────────────────────────────┐\n";
        $message_mail .= "│   ADRESSE DE LIVRAISON                     │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  " . str_pad($adresse_livraison, 31) . " │\n";
        $message_mail .= "└─────────────────────────────────────────────────┘\n\n";
        
        // Articles commandés
        $message_mail .= "┌─────────────────────────────────────────────────┐\n";
        $message_mail .= "│  ARTICLES COMMANDÉS                       │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        
        $max_nom_length = 20;
        foreach ($items_valides as $index => $item) {
            $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            $nom = substr($item['nom'], 0, $max_nom_length);
            $nom_padded = str_pad($nom, $max_nom_length);
            $qte = str_pad($item['quantite'], 3);
            $prix = number_format($item['prix'], 0, ',', ' ');
            $total_item = number_format($item['prix'] * $item['quantite'], 0, ',', ' ');
            
            $message_mail .= "│  " . $num . ". " . $nom_padded . "  " . $qte . "x " . str_pad($prix, 10) . " │\n";
            $message_mail .= "│     Sous-total : " . str_pad($total_item . ' FCFA', 25) . " │\n";
            $message_mail .= "├─────────────────────────────────────────────────┤\n";
        }
        
        // Récapitulatif financier
        $message_mail .= "│   RÉCAPITULATIF FINANCIER                 │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  Sous-total     : " . str_pad(number_format($total_panier, 0, ',', ' ') . ' FCFA', 19) . " │\n";
        
        if ($frais_livraison > 0) {
            $message_mail .= "│  Frais de liv.  : " . str_pad(number_format($frais_livraison, 0, ',', ' ') . ' FCFA', 19) . " │\n";
        } else {
            $message_mail .= "│  Frais de liv.  : " . str_pad('OFFERTS ', 19) . " │\n";
        }
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  TOTAL À PAYER  : " . str_pad(number_format($total_commande, 0, ',', ' ') . ' FCFA', 19) . " │\n";
        $message_mail .= "└─────────────────────────────────────────────────┘\n\n";
        
        // Informations de paiement
        $message_mail .= "┌─────────────────────────────────────────────────┐\n";
        $message_mail .= "│  MODALITÉS DE PAIEMENT                    │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  ➜ Paiement à la livraison (cash)            │\n";
        $message_mail .= "└─────────────────────────────────────────────────┘\n\n";
        
        // Prochaines étapes
        $message_mail .= "┌─────────────────────────────────────────────────┐\n";
        $message_mail .= "│  PROCHAINES ÉTAPES                        │\n";
        $message_mail .= "├─────────────────────────────────────────────────┤\n";
        $message_mail .= "│  1 Vérification de votre commande          │\n";
        $message_mail .= "│  2 Préparation de vos produits             │\n";
        $message_mail .= "│  3 Livraison à l'adresse indiquée          │\n";
        $message_mail .= "│  4  Paiement à la livraison                 │\n";
        $message_mail .= "└─────────────────────────────────────────────────┘\n\n";
        
        // Pied de page
        $message_mail .= "═══════════════════════════════════════════════════\n";
        $message_mail .= "    Merci pour votre confiance !\n";
        $message_mail .= "═══════════════════════════════════════════════════\n\n";
        $message_mail .= " Service Client : +242 05 532 22 33\n";
  
        $message_mail .= "Ceci est un message automatique, merci de ne pas y répondre.";
        
        // Envoi via Google Apps Script
        $url_google_script = "https://script.google.com/macros/s/AKfycbwGYTojaJeLBJuIhXyNwdXY5bXBUdp_vSeF2N91tpMR2wl4wc7wv_cyDfIH0MxEt0GG/exec";

        $donnees_mail = [
            'cle' => 'BlessPlus_Gateway_7859!_Secure@',
            'email' => $user_email,
            'sujet' => $sujet,
            'message' => $message_mail
        ];

        $ch = curl_init($url_google_script);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($donnees_mail));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code == 200 && !empty($response)) {
            $email_envoye = true;
        } else {
            error_log("Erreur d'envoi d'email pour la commande $commande_id: HTTP $http_code, Erreur: $curl_error");
        }
    }

    // Vider le panier session
    unset($_SESSION['panier']);
    
    // REDIRECTION APRÈS TOUT LE TRAITEMENT
    header('Location: confirmation_success.php?id=' . $commande_id);
    exit();

} catch (\PDOException $e) {
    $pdo->rollBack();
    die("Erreur lors de la validation : " . $e->getMessage());
}
?>
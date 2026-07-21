<?php
// modifier_profil.php
session_start();

// 1. PROTECTION ACCÈS : Le client doit être connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit();
}
require_once __DIR__ . '/../../config/database.php'; 


$message = "";
$error = "";
$id_user = $_SESSION['user_id'];

// =========================================================================
// FONCTION MAGIQUE : COMPRESSION & CONVERSION EN WEBP (Spécial Profil)
// =========================================================================
function optimiserAvatarClient(string $sourcePath, string $destinationPath, int $nouvelleLargeur = 300, int $qualite = 80): bool {
    if (!file_exists($sourcePath) || filesize($sourcePath) === 0) {
        return false;
    }

    $infosImage = getimagesize($sourcePath);
    if ($infosImage === false) {
        return false; 
    }

    list($largeurOrigine, $hauteurOrigine, $typeOrigine) = $infosImage;
    
    // Calcul des proportions pour rester net
    $ratio = $largeurOrigine / $hauteurOrigine;
    if ($largeurOrigine > $nouvelleLargeur) {
        $nouvelleHauteur = (int)($nouvelleLargeur / $ratio);
    } else {
        $nouvelleLargeur = $largeurOrigine;
        $nouvelleHauteur = $hauteurOrigine;
    }

    $imageDest = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);

    // Préserver la transparence si le client envoie un PNG ou WEBP transparent
    if ($typeOrigine == IMAGETYPE_PNG || $typeOrigine == IMAGETYPE_WEBP) {
        imagealphablending($imageDest, false);
        imagesavealpha($imageDest, true);
    }

    switch ($typeOrigine) {
        case IMAGETYPE_JPEG: $imageSource = imagecreatefromjpeg($sourcePath); break;
        case IMAGETYPE_PNG:  $imageSource = imagecreatefrompng($sourcePath); break;
        case IMAGETYPE_WEBP: $imageSource = imagecreatefromwebp($sourcePath); break;
        default: if(isset($imageDest)) imagedestroy($imageDest); return false;
    }

    if (!$imageSource) {
        imagedestroy($imageDest);
        return false;
    }

    // Redimensionnement propre
    imagecopyresampled($imageDest, $imageSource, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeurOrigine, $hauteurOrigine);

    // Sauvegarde en WebP léger
    $resultat = imagewebp($imageDest, $destinationPath, $qualite);

    imagedestroy($imageSource);
    imagedestroy($imageDest);

    return $resultat;
}

// =========================================================================
// TRAITEMENT DU FORMULAIRE (Infos perso & Photo de profil)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($nom) || empty($email)) {
        $error = "Le nom et l'adresse email sont obligatoires.";
    } else {
        try {
            // 1. Mise à jour des informations de texte de base
            $stmt = $pdo->prepare("UPDATE users SET nom = ?, email = ? WHERE id = ?");
            $stmt->execute([$nom, $email, $id_user]);
            $_SESSION['user_nom'] = $nom; // Met à jour le nom dans la navbar
            
            $message = "Vos informations ont été mises à jour.";

            // 2. Gestion de l'avatar si une image est fournie
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['avatar']['tmp_name'];
                $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Forcer le nom final en .webp unique pour ce client
                    $nom_avatar = 'avatar_' . $id_user . '_' . time() . '.webp';
                    $upload_dir = __DIR__ . '/../uploads/avatars/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $chemin_final = $upload_dir . $nom_avatar;
                    
                    // Lancement de la compression
                    if (optimiserAvatarClient($file_tmp, $chemin_final, 300, 80)) {
                        
                        // 🗑️ NETTOYAGE : On récupère l'ancienne photo pour la supprimer du disque
                        $stmtOld = $pdo->prepare("SELECT avatar FROM utilisateurs WHERE id = ?");
                        $stmtOld->execute([$id_user]);
                        $old_avatar = $stmtOld->fetchColumn();
                        
                        if ($old_avatar && $old_avatar !== 'default_avatar.webp' && file_exists($upload_dir . $old_avatar)) {
                            unlink($upload_dir . $old_avatar); // Supprime l'ancien fichier devenu inutile
                        }
                        
                        // Sauvegarde du nouveau nom en BDD
                        $stmtUpdate = $pdo->prepare("UPDATE utilisateurs SET avatar = ? WHERE id = ?");
                        $stmtUpdate->execute([$nom_avatar, $id_user]);
                        
                        // Mise à jour de la session pour l'affichage immédiat
                        $_SESSION['user_avatar'] = $nom_avatar;
                        $message = "Profil et photo mis à jour avec succès !";
                    } else {
                        $error = "Erreur lors du traitement de votre photo de profil.";
                    }
                } else {
                    $error = "Format d'image non supporté (uniquement JPG, PNG, WEBP).";
                }
            }
        } catch (\PDOException $e) {
            $error = "Erreur de mise à jour : " . $e->getMessage();
        }
    }
}

// Récurent : Charger les infos actuelles du client pour remplir les champs
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$id_user]);
$user = $stmtUser->fetch();
$avatar_actuel = !empty($user['avatar']) ? $user['avatar'] : 'default_avatar.webp';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte | EMMA+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #fafafa; color: #111; }
        .card-profile { background: #ffffff; border: 1px solid #e5e5e5; border-radius: 0; padding: 30px; }
        .form-control { border-radius: 0; border: 1px solid #111; padding: 12px; }
        .form-control:focus { border-color: #111; box-shadow: none; }
        .btn-update { background-color: #111; color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-radius: 0; padding: 12px 30px; border: none; }
        .btn-update:hover { background-color: #2d2d2d; color: #fff; }
        .avatar-wrapper { position: relative; display: inline-block; }
        .btn-upload-trigger { font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border: 1px solid #111; background: transparent; padding: 6px 12px; cursor: pointer; display: inline-block; margin-top: 10px; }
        .btn-upload-trigger:hover { background: #111; color: #fff; }
    </style>
</head>
<body>

    <div class="container my-5" style="max-width: 600px;">
        <h1 class="fw-black text-uppercase text-center mb-4" style="font-weight: 900; letter-spacing: 1px;">Modifier mon Profil</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success rounded-0 small py-3" role="alert"><i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-0 small py-3" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-profile shadow-sm">
            <form action="modifier_profil.php" method="POST" enctype="multipart/form-data">
                
                <div class="text-center mb-4">
                    <div class="avatar-wrapper">
                        <img id="avatar-preview" src="../uploads/avatars/<?= htmlspecialchars($avatar_actuel) ?>" 
                             class="rounded-circle border" style="width: 130px; height: 130px; object-fit: cover;">
                    </div>
                    <div>
                        <label for="avatar-input" class="btn-upload-trigger">Changer ma photo</label>
                        <input type="file" id="avatar-input" name="avatar" accept="image/*" class="d-none">
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 11px;">Cliquer pour changer votre photo.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Nom complet</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Adresse Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>

                <div class="text-center pt-3">
                    <button type="submit" class="btn-update w-100">Enregistrer les modifications</button>
                    <a href="../client/dashboard.php" class="d-block mt-3 small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Retour à mon compte</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('avatar-input').addEventListener('change', function(event) {
            const fichier = event.target.files[0];
            if (fichier) {
                const lecteur = new FileReader();
                lecteur.onload = function(e) {
                    // On injecte directement la source lue localement dans la balise img
                    document.getElementById('avatar-preview').src = e.target.result;
                };
                lecteur.readAsDataURL(fichier);
            }
        });
    </script>
</body>
</html>
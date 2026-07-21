<?php
// generate_slugs.php
require_once __DIR__ . '/config/database.php';

// Fonction pour générer un slug SEO-friendly
function generateSlug($text) {
    // Convertir en minuscules
    $slug = strtolower($text);
    
    // Tableau de remplacement pour les caractères spéciaux français
    $replacements = [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'ô' => 'o', 'ö' => 'o',
        'î' => 'i', 'ï' => 'i',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        ' ' => '-',
        "'" => '-',
        '"' => '',
        '.' => '',
        ',' => '',
        ';' => '',
        ':' => '',
        '!' => '',
        '?' => '',
        '/' => '',
        '\\' => '',
        '(' => '',
        ')' => '',
        '[' => '',
        ']' => '',
        '{' => '',
        '}' => '',
        '&' => 'et',
        '+' => 'plus',
        '@' => 'a',
        '#' => ''
    ];
    
    // Remplacer les caractères
    foreach ($replacements as $search => $replace) {
        $slug = str_replace($search, $replace, $slug);
    }
    
    // Remplacer les tirets multiples par un seul
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Supprimer les tirets au début et à la fin
    $slug = trim($slug, '-');
    
    // Si le slug est vide, utiliser "produit"
    if (empty($slug)) {
        $slug = 'produit';
    }
    
    return $slug;
}

// Démarrer la session pour les messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Génération des slugs</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #111; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .log { font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .summary { background: #e9ecef; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #111; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        .btn:hover { background: #333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Génération des slugs</h1>";

// Récupérer tous les produits sans slug
$stmt = $pdo->query("SELECT id, nom, slug FROM produits WHERE slug IS NULL OR slug = '' ORDER BY id");
$produits = $stmt->fetchAll();

$total = count($produits);
echo "<p><strong>Produits sans slug à traiter :</strong> {$total}</p>";

if ($total == 0) {
    echo "<p class='success'>Tous les produits ont déjà un slug !</p>";
    echo "<a href='views/public/boutique.php' class='btn'>Retourner à la boutique</a>";
    echo "</div></body></html>";
    exit;
}

$updated = 0;
$errors = 0;
$logs = [];

foreach ($produits as $prod) {
    $baseSlug = generateSlug($prod['nom']);
    $slug = $baseSlug;
    $counter = 1;
    
    // Vérifier l'unicité du slug
    while (true) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE slug = ? AND id != ?");
        $check->execute([$slug, $prod['id']]);
        if ($check->fetchColumn() == 0) {
            break;
        }
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    // Mettre à jour le produit
    $update = $pdo->prepare("UPDATE produits SET slug = ? WHERE id = ?");
    if ($update->execute([$slug, $prod['id']])) {
        $logs[] = "<div class='log' style='color: #28a745;'>✓ Produit #{$prod['id']} : <strong>{$prod['nom']}</strong> → <code>{$slug}</code></div>";
        $updated++;
    } else {
        $logs[] = "<div class='log' style='color: #dc3545;'>✗ Erreur pour le produit #{$prod['id']} : {$prod['nom']}</div>";
        $errors++;
    }
}

// Afficher les logs
echo "<div style='max-height: 400px; overflow-y: auto; margin: 15px 0;'>";
foreach ($logs as $log) {
    echo $log;
}
echo "</div>";

// Résumé
echo "<div class='summary'>";
echo "<h3>Résumé</h3>";
echo "<ul>";
echo "<li><span class='success'>✓</span> Produits mis à jour : <strong>{$updated}</strong></li>";
if ($errors > 0) {
    echo "<li><span class='error'>✗</span> Erreurs : <strong>{$errors}</strong></li>";
}
echo "<li><span class='info'>ℹ</span> Total traités : <strong>{$total}</strong></li>";
echo "</ul>";

if ($updated > 0 && $errors == 0) {
    echo "<p class='success' style='font-weight: bold; font-size: 1.1em;'>Tous les slugs ont été générés avec succès !</p>";
} elseif ($updated > 0 && $errors > 0) {
    echo "<p class='error' style='font-weight: bold;'> Certains slugs n'ont pas pu être générés.</p>";
}
echo "</div>";

echo "<div style='margin-top: 20px;'>";
echo "<a href='views/public/boutique.php' class='btn'> Retourner à la boutique</a>";
echo " <a href='check_slugs.php' class='btn' style='background: #17a2b8;'> Vérifier les slugs</a>";
echo "</div>";

echo "</div></body></html>";
?>
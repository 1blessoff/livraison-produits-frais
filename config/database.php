<?php
// config/database.php

// 1. Éviter l'accès direct au fichier de configuration
if (count(get_included_files()) === 1) {
    http_response_code(403);
    echo"Desolé(e) mais cette page n'existe pas.";
    exit();
}

//  Définition des identifiants de connexion
define('DB_HOST', 'sql200.infinityfree.com');
define('DB_NAME', 'if0_42158246_panierbdd');
define('DB_USER', 'if0_42158246');    
define('DB_PASS', 'tc5qBiGr87KW');         
define('DB_CHARSET', 'utf8mb4');  

try {
    // Construction de la chaîne de connexion 
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // Options de configuration de PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ];

    // Initialisation de la connexion globale
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

   

} catch (\PDOException $e) {
    error_log("Erreur de connexion : " . $e->getMessage());
    die("Une erreur est survenue lors de la connexion à la base de données : " . $e->getMessage());
}

// 3. LOGIQUE SESSIONS & COOKIES : "Se souvenir de moi"
// Maintenant que $pdo existe et fonctionne, on peut l'utiliser de manière sécurisée !

// 3. LOGIQUE SESSIONS & COOKIES : "Se souvenir de moi"
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification sécurisée du cookie "Se souvenir de moi"
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_me_cookie'])) {
    
    // On vérifie que le cookie contient bien le séparateur ':' pour éviter le crash au découpage
    if (strpos($_COOKIE['remember_me_cookie'], ':') !== false) {
        
        list($user_id, $token) = explode(':', $_COOKIE['remember_me_cookie']);
        $user_id = intval($user_id);
        $token_hashed = hash('sha256', $token);

        if ($user_id > 0 && !empty($token)) {
            try {
                $stmtRemember = $pdo->prepare("SELECT * FROM users WHERE id = ? AND remember_token = ?");
                $stmtRemember->execute([$user_id, $token_hashed]);
                $user = $stmtRemember->fetch();

                if ($user) {
                    // Reconnexion automatique : chargement des variables de session
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['user_role']   = $user['role'];
                    $_SESSION['user_nom']    = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                } else {
                    // Jeton invalide ou expiré -> On nettoie le cookie pour de bon
                    setcookie('remember_me_cookie', '', time() - 3600, '/');
                }
            } catch (\PDOException $e) {
                // En cas de problème BDD, on supprime le cookie suspect pour débloquer l'utilisateur
                setcookie('remember_me_cookie', '', time() - 3600, '/');
            }
        }
    } else {
        // Le cookie est mal formé -> Destruction immédiate
        setcookie('remember_me_cookie', '', time() - 3600, '/');
    }
}


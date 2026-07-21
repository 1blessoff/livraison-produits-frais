<?php
// config/session.php

// 1. Éviter l'accès direct au fichier de configuration
if (count(get_included_files()) === 1) {
    http_response_code(403);
    echo"Desolé(e) mais cette page n'existe pas.";
    exit();
}
?>
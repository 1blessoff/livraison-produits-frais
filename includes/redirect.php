<?php
// includes/redirect.php

function redirigerVersDashboard() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /views/public/connexion.php');
        exit();
    }
    
    $role = $_SESSION['user_role'] ?? 'client';
    
    switch ($role) {
        case 'admin':
            header('Location: /views/admin/dashboard.php');
            break;
        case 'livreur':
            header('Location: /views/livreur/dashboard.php');
            break;
        default:
            header('Location: /views/client/dashboard.php');
            break;
    }
    exit();
}

function estConnecte() {
    return isset($_SESSION['user_id']);
}

function getDashboardLink() {
    if (!estConnecte()) {
        return '/views/public/connexion.php';
    }
    
    $role = $_SESSION['user_role'] ?? 'client';
    
    switch ($role) {
        case 'admin':
            return '/views/admin/dashboard.php';
        case 'livreur':
            return '/views/livreur/dashboard.php';
        default:
            return '/views/client/dashboard.php';
    }
}
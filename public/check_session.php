<?php
require_once 'config.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['customer_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Function to require login (redirect if not logged in)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Function to require admin (redirect if not admin)
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Function to get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['customer_id'],
        'email' => $_SESSION['customer_email'],
        'name' => $_SESSION['customer_name'],
        'is_admin' => $_SESSION['is_admin']
    ];
}

// Function to verify CSRF token
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
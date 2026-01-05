<?php
session_start();

// Función para verificar si el usuario está autenticado
function verificarAutenticacion() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

// Función para cerrar sesión
function cerrarSesion() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Manejar logout
if (isset($_GET['logout'])) {
    cerrarSesion();
}

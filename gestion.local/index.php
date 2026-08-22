<?php
/**
 * Point d'entrée principal du site
 * Redirige vers le dashboard via le routeur
 */
header('Location: router.php?p=dashboard');
exit;
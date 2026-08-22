<?php
/* Version: v1.3.0 (Rev #4) - 2026-07-30 */
/** Script d'exportation de l'historique des fichiers en CSV */
require_once __DIR__ . '/../includes/init.php';  // Pour charger $pdo et la session

// Sécurité admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Accès refusé');
}

// 1. Préparation de la requête avec les mêmes filtres que ta page
$params = [];
$where = [];
if (!empty($_GET['from'])) {
    $where[] = 'date_action >= ?';
    $params[] = $_GET['from'] . ' 00:00:00';
}
if (!empty($_GET['to'])) {
    $where[] = 'date_action <= ?';
    $params[] = $_GET['to'] . ' 23:59:59';
}

$whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT date_action, chemin, ancien_status, nouveau_status, admin FROM historique_fichiers $whereSQL ORDER BY date_action DESC");
$stmt->execute($params);

// 2. Headers pour forcer le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_structure_' . date('Y-m-d') . '.csv"');

// 3. Génération du fichier
$output = fopen('php://output', 'w');
// BOM UTF-8 pour qu'Excel affiche bien les accents (é, à, etc.)
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Entêtes des colonnes
fputcsv($output, ['Date', 'Fichier', 'Ancien État', 'Nouvel État', 'Par'], ';');

// Données
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row, ';');
}

fclose($output);
exit;

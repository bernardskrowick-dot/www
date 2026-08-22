<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

global $pdo;
$user_id = $_SESSION['user_id'];

// -------------------------
// POST
// -------------------------
$titre = trim($_POST['titre'] ?? '');
$organisme = trim($_POST['organisme'] ?? '');
$montant_prevu = (float) ($_POST['montant_prevu'] ?? 0);
$date_depart = $_POST['date_depart'] ?? date('Y-m-d');
$date_fin = $_POST['date_fin'] ?: null;  // null si vide
$recurrence = $_POST['recurrence'] ?? 'mensuelle';
$categorie_id = ($_POST['categorie_id'] ?? null);
$categorie_id = ($categorie_id === '' || $categorie_id === '0') ? null : (int) $categorie_id;

// -------------------------
// INSERT RESSOURCE
// -------------------------
$stmt = $pdo->prepare('
    INSERT INTO ressources (
        user_id, titre, organisme, montant_prevu,
        date_depart, date_fin, recurrence, categorie_id
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
');

$stmt->execute([
    $user_id,
    $titre,
    $organisme,
    $montant_prevu,
    $date_depart,
    $date_fin,
    $recurrence,
    $categorie_id
]);

$ressource_id = $pdo->lastInsertId();

// -------------------------
// GÉNÉRATION DES VERSEMENTS
// -------------------------
$start = new DateTime($date_depart);
$end = $date_fin ? new DateTime($date_fin) : null;

// fonction pour incrémenter selon la récurrence
$stepMap = [
    'unique' => '+0 day',
    'mensuelle' => '+1 month',
    'trimestrielle' => '+3 month',
    'semestrielle' => '+6 month',
    'annuelle' => '+1 year'
];

$step = $stepMap[$recurrence] ?? '+1 month';

// -------------------------
// CAS UNIQUE
// -------------------------
if ($recurrence === 'unique' || !$end) {
    $stmtVersement = $pdo->prepare("
        INSERT INTO versements (
            ressource_id, date_versement_prevue, montant_prevu, statut
        ) VALUES (?, ?, ?, 'attendu')
    ");
    $stmtVersement->execute([
        $ressource_id,
        $start->format('Y-m-d'),
        $montant_prevu
    ]);
} else {
    // -------------------------
    // CAS RECURRANT AVEC DATE_FIN
    // -------------------------
    $current = clone $start;

    while ($current <= $end) {
        $stmtVersement = $pdo->prepare("
            INSERT INTO versements (
                ressource_id, date_versement_prevue, montant_prevu, statut
            ) VALUES (?, ?, ?, 'attendu')
        ");

        $stmtVersement->execute([
            $ressource_id,
            $current->format('Y-m-d'),
            $montant_prevu
        ]);

        $current->modify($step);
    }
}

// -------------------------
// REDIRECTION
// -------------------------
header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php&success=1');
exit;

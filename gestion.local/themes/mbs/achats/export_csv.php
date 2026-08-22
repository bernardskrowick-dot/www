<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */

/**
 * EXPORT_CSV.PHP – VERSION MBS_DARK
 * Génère un export des achats filtrés pour l'utilisateur connecté.
 * RÈGLES : PDO, Filtres dynamiques, BOM UTF-8, Séparateur Excel (;)
 */
/** EXPORT_CSV.PHP – VERSION MBS_DARK */

// On vérifie si les constantes sont déjà définies (cas où le router l'inclut)
// Sinon on les définit manuellement pour un appel direct
if (!defined('DIR_INCLUDES')) {
    require_once __DIR__ . '/../../../includes/config.php';  // Ajuste le nombre de ../ selon ta structure réelle
    require_once __DIR__ . '/../../../includes/connexion.php';
}

// Nettoyage du tampon de sortie pour éviter que du HTML parasite ne s'insère dans le CSV
if (ob_get_length())
    ob_end_clean();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... reste du code (Requête SQL, fputcsv, etc.)

/* --- 1. SÉCURITÉ & ACCÈS --- */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

/* --- 2. RÉCUPÉRATION & NETTOYAGE DES FILTRES --- */
$search = isset($_GET['search'])
    ? mb_substr(trim($_GET['search']), 0, 100)
    : '';

$marchandFilter = isset($_GET['marchand_filter'])
    ? mb_substr(trim($_GET['marchand_filter']), 0, 100)
    : '';

$statutsFilter = isset($_GET['statuts_filter'])
    ? trim($_GET['statuts_filter'])
    : '';
$dateDebut = (
    !empty($_GET['date_debut']) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_debut'])
)
    ? $_GET['date_debut']
    : null;

$dateFin = (
    !empty($_GET['date_fin']) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_fin'])
)
    ? $_GET['date_fin']
    : null;

$filtreCategorie = isset($_GET['categorie'])
    ? (int) $_GET['categorie']
    : 0;

/* --- 3. FILTRE STATUT --- */

$statusWhere = '';

switch ($statutsFilter) {
    case 'solde':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) <= 0
        ";

        break;

    case 'en_retard':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) > 0

            AND

                MIN(
                    CASE
                        WHEN e.statut != 'payee'
                        THEN e.date_echeance
                    END
                ) < CURDATE()
        ";

        break;

    case 'a_venir':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) > 0

            AND

                (
                    MIN(
                        CASE
                            WHEN e.statut != 'payee'
                            THEN e.date_echeance
                        END
                    ) >= CURDATE()

                    OR

                    MIN(
                        CASE
                            WHEN e.statut != 'payee'
                            THEN e.date_echeance
                        END
                    ) IS NULL
                )
        ";

        break;
}
/* --- 3. GÉNÉRATION DU NOM DE FICHIER --- */
$nomFichier = 'historique_achats';
if (!empty($marchandFilter)) {
    // Nettoyage pour éviter les caractères spéciaux dans le nom du fichier
    $marchandSafe = iconv('UTF-8', 'ASCII//TRANSLIT', $marchandFilter);
    $marchandSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $marchandSafe);
    $nomFichier .= '_' . $marchandSafe;
}
$nomFichier .= '_' . date('Y-m-d') . '.csv';

/* --- 4. EN-TÊTES HTTP POUR LE TÉLÉCHARGEMENT --- */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Pragma: no-cache');
header('Expires: 0');

/* --- 5. INITIALISATION DU FLUX --- */
$output = fopen('php://output', 'w');

// Injection du BOM UTF-8 pour forcer Excel à lire l'encodage correctement
fwrite($output, "\u{FEFF}");

/* --- 6. REQUÊTE PDO FILTRÉE --- */
$stmt = $pdo->prepare("
   SELECT
       a.titre,
       a.nom_marchand,
       a.date_depart,
       SUM(e.montant) AS total_echeances,
       SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
       MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance
    FROM echeances e
    JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
      AND (:search = '' OR a.titre LIKE :search)
      AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter)
      AND (
    :categorie = 0

    OR a.categorie_id = :categorie

    OR a.categorie_id IN (

        SELECT id

        FROM categories

        WHERE parent_id = :categorie

        AND user_id = :user_id

    )
)
     AND (
    :date_debut IS NULL

    OR

    (
        a.date_depart BETWEEN :date_debut AND :date_fin

        OR

        EXISTS (

            SELECT 1

            FROM echeances e2

            WHERE e2.achat_id = a.id

            AND e2.date_echeance BETWEEN :date_debut AND :date_fin

        )

    )
)

GROUP BY a.id

" . $statusWhere . '

ORDER BY a.date_depart DESC
');

$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmt->bindValue(':marchand_filter', $marchandFilter, PDO::PARAM_STR);
$stmt->bindValue(':categorie', $filtreCategorie, PDO::PARAM_INT);
$stmt->bindValue(':date_debut', $dateDebut, $dateDebut === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

$stmt->bindValue(
    ':date_fin',
    $dateFin,
    $dateFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR
);
$stmt->execute();

$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --- 7. ÉCRITURE DES DONNÉES --- */

// En-têtes des colonnes (Utilisation du point-virgule pour Excel FR)
fputcsv($output, ['Titre', 'Marchand', "Date d'achat", 'Montant total', 'Total payé', 'Reste à payer', 'État', 'Prochaine échéance'], ';');

foreach ($achats as $achat) {
    $reste = $achat['total_echeances'] - $achat['total_paye'];

    // Logique de statut identique à l'affichage HTML
    if ($reste <= 0) {
        $statut = 'Soldé';
    } elseif (
        $achat['prochaine_echeance'] &&
        $achat['prochaine_echeance'] < $aujourdhui &&
        $reste > 0
    ) {
        $statut = 'En retard';
    } else {
        $statut = 'En cours';
    }

    $dateAchat = $achat['date_depart'] ? date('d/m/Y', strtotime($achat['date_depart'])) : '';
    $prochaineEcheance = $achat['prochaine_echeance'] ? date('d/m/Y', strtotime($achat['prochaine_echeance'])) : '-';

    fputcsv($output, [
        $achat['titre'],
        $achat['nom_marchand'],
        $dateAchat,
        number_format($achat['total_echeances'], 2, ',', ' '),
        number_format($achat['total_paye'], 2, ',', ' '),
        number_format($reste, 2, ',', ' '),
        $statut,
        $prochaineEcheance
    ], ';');
}

/* --- 8. FERMETURE DU FLUX --- */
fclose($output);
exit;

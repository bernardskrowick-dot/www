<?php

/**
 * Page d'administration : Gestion de la structure des fichiers
 * - Synchronisation disque / BDD avec détection création, modification, suppression
 * - Historique des modifications
 * - Calcul automatique de version d'application selon le taux de changement
 * - Injection automatique des en-têtes de version dans le code source
 * - Navigation AJAX pour l'historique (pagination et filtres sans rechargement)
 * - Sécurité globale (rôles admin, CSRF, PDO prepared statements, protection XSS)
 */

// =====================================================
// 1. SÉCURITÉ ET DÉMARRAGE DE SESSION
// =====================================================

/* S'assurer que la session est démarrée */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Génération du token CSRF standard via la fonction globale si nécessaire */
if (function_exists('generate_csrf_token')) {
    generate_csrf_token();
} elseif (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ----------------------------- Constantes ----------------------------- */
define('DIR_PROJECT_ROOT', realpath(__DIR__ . '/../'));  // Racine du projet

/* ----------------------------- Contrôle d'accès ----------------------------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

/* ----------------------------- Fonctions ----------------------------- */

/**
 * Scan récursif des dossiers/fichiers
 */
function scanFolder(string $dir, string $base = '')
{
    $items = [];
    $exclude = ['.', '..', '.git', 'node_modules', 'error_log', '.ftpquota', '.well-known', 'vendor', 'last_sync.txt', 'sync_cron.php', 'composer.phar', 'composer.lock', 'composer.json', 'includes/connexion-cron.php'];
    if (!is_dir($dir)) {
        return [];
    }

    $files = array_diff(scandir($dir), $exclude);

    foreach ($files as $f) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $f;
        $relPath = $base ? $base . '/' . $f : $f;
        if (is_dir($fullPath)) {
            $items[] = ['chemin' => $relPath, 'type' => 'folder'];
            $items = array_merge($items, scanFolder($fullPath, $relPath));
        } else {
            $items[] = ['chemin' => $relPath, 'type' => 'file'];
        }
    }
    return $items;
}

/**
 * Construire un arbre hiérarchique pour affichage HTML
 */
function buildTree(array $files)
{
    $tree = [];
    foreach ($files as $f) {
        $parts = explode('/', $f['chemin']);
        $current = &$tree;
        foreach ($parts as $i => $p) {
            if ($i === count($parts) - 1) {
                $current[$p] = ['item' => $f, 'children' => []];
            } else {
                if (!isset($current[$p])) {
                    $current[$p] = ['item' => ['type' => 'folder', 'chemin' => $p, 'status' => 'existant'], 'children' => []];
                }
                $current = &$current[$p]['children'];
            }
        }
    }
    return $tree;
}

/**
 * Rendu HTML de l'arbre
 */
function renderTree(array $nodes, int $level = 0)
{
    uksort($nodes, function ($a, $b) use ($nodes) {
        $typeA = $nodes[$a]['item']['type'] ?? 'file';
        $typeB = $nodes[$b]['item']['type'] ?? 'file';
        return ($typeA === $typeB) ? strcasecmp($a, $b) : ($typeA === 'folder' ? -1 : 1);
    });

    echo "<ul style='list-style:none; padding-left:" . ($level * 20) . "px'>";
    foreach ($nodes as $name => $node) {
        $item = $node['item'];
        $status = $item['status'] ?? 'existant';

        $cls = match ($status) {
            'créé' => 'badge-cree',
            'supprimé' => 'badge-supprime',
            'modifié' => 'badge-modifie',
            default => 'badge-existant'
        };

        $style = match ($status) {
            'créé' => 'color:blue;',
            'supprimé' => 'color:red;',
            'modifié' => 'color:orange;',
            default => 'color:green;'
        };

        if ($item['type'] === 'folder') {
            $isCollapsed = $level > 0 ? 'collapsed' : '';
            echo "<li class='folder $isCollapsed'>📁 <strong>" . htmlspecialchars($name) . "</strong> - <span class='$cls' style='$style'>" . ucfirst($status) . '</span>';
            renderTree($node['children'], $level + 1);
            echo '</li>';
        } else {
            echo '<li>📄 ' . htmlspecialchars($name) . " - <span class='$cls' style='$style'>" . ucfirst($status) . '</span></li>';
        }
    }
    echo '</ul>';
}

/**
 * Inscription / Mise à jour de l'en-tête de version dans le fichier physique
 */
function injecterEnTeteFichier(string $fullPath, string $versionApp, string $revisionFichier)
{
    if (!file_exists($fullPath) || !is_writable($fullPath)) {
        return;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $dateJour = date('Y-m-d');

    if (in_array($ext, ['php', 'css', 'js'])) {

        $headerLine = '/* Version: ' . $versionApp .
            ' (Rev #' . $revisionFichier .
            ') - ' . $dateJour . ' */';

        $regexPattern = '/\/\* Version: .*? \*\//s';
    } else {
        return;
    }

    $content = file_get_contents($fullPath);

    if (preg_match($regexPattern, $content)) {
        $newContent = preg_replace($regexPattern, $headerLine, $content, 1);
    } else {
        if ($ext === 'php' && strncmp($content, '<?php', 5) === 0) {
            $newContent = "<?php\n" . $headerLine . substr($content, 5);
        } else {
            $newContent = $headerLine . "\n" . $content;
        }
    }

    file_put_contents($fullPath, $newContent);
}

/**
 * Calcul du taux de changement et génération automatique de la version applicative
 */
function calculerTauxEtVersionner(PDO $pdo, array $stats)
{
    $totalImpactes = (int) ($stats['total_cree'] ?? 0) + (int) ($stats['total_modifie'] ?? 0);
    $totalFichiers = (int) ($stats['total_existant'] ?? 0) + $totalImpactes;

    if ($totalFichiers === 0 || $totalImpactes === 0) {
        return 'Aucune évolution détectée (aucun fichier modifié/créé).';
    }

    $tauxChangement = round(($totalImpactes / $totalFichiers) * 100, 2);

    $stmtVer = $pdo->query('SELECT version_number FROM app_versions ORDER BY id DESC LIMIT 1');
    $lastVer = $stmtVer->fetchColumn();

    if (!$lastVer) {
        $vMajor = 1;
        $vMinor = 0;
        $vPatch = 0;
    } else {
        $cleanVer = ltrim($lastVer, 'v');
        $parts = explode('.', $cleanVer);
        $vMajor = (int) ($parts[0] ?? 1);
        $vMinor = (int) ($parts[1] ?? 0);
        $vPatch = (int) ($parts[2] ?? 0);
    }

    if ($tauxChangement >= 5.0) {
        $vMinor++;
        $vPatch = 0;
    } else {
        $vPatch++;
    }

    $newVer = 'v' . $vMajor . '.' . $vMinor . '.' . $vPatch;

    $insVer = $pdo->prepare('INSERT INTO app_versions (version_number, taux_changement, nb_fichiers_impactes, date_release, is_synced, updated_at) VALUES (?, ?, ?, NOW(), 0, NOW())');
    $insVer->execute([$newVer, $tauxChangement, $totalImpactes]);

    return 'Version ' . $newVer . ' générée automatiquement (Taux de changement : ' . $tauxChangement . '%, ' . $totalImpactes . ' fichier(s) impacté(s)).';
}

/**
 * Scan automatique et silencieux exécuté en arrière-plan
 */
function scanSilencieux(PDO $pdo, string $adminUser = 'admin')
{
    $stmtCheck = $pdo->query(
        'SELECT UNIX_TIMESTAMP(updated_at)
         FROM app_versions
         ORDER BY id DESC
         LIMIT 1'
    );

    $lastScanTime = $stmtCheck
        ? (int) $stmtCheck->fetchColumn()
        : 0;

    $currentTime = time();

    if (
        $lastScanTime > 0 &&
        ($currentTime - $lastScanTime) < 43200
    ) {
        return false;
    }

    executerScan(
        $pdo,
        $adminUser,
        'silencieux'
    );

    return true;
}

/* =====================================================
 * GARDE-FOU INCLUSION SILENCIEUSE
 * ===================================================== */
if (isset($onlyLogic) && $onlyLogic === true) {
    return;
}


/**
 * Exécute le scan complet de la structure fichiers.
 *
 * Commun au scan manuel et au scan silencieux.
 *
 * @return string Message généré par le versionnement.
 */
function executerScan(
    PDO $pdo,
    string $adminUser = 'admin',
    string $typeScan = 'manuel'
): string {
    $pdo->beginTransaction();

    try {


        $scanItems = scanFolder(DIR_PROJECT_ROOT);
        $foundPaths = [];

        // Récupération de l'état actuel en BDD
        $stmt = $pdo->query(
            'SELECT chemin, status, date_modification, num_revision FROM fichiers'
        );

        $dbItems = $stmt->fetchAll(
            PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC
        );

        $existingPathsInDb = array_keys($dbItems);

        // Requêtes communes aux deux types de scan
        $updStatusMod = $pdo->prepare("
        UPDATE fichiers
        SET status='modifié',
            date_scan=NOW(),
            date_modification=?,
            num_revision = num_revision + 1,
            is_synced = 0,
            updated_at = NOW()
        WHERE chemin=?
    ");

        $updStatusSame = $pdo->prepare("
        UPDATE fichiers
        SET status='existant',
            date_scan=NOW(),
            date_modification=?
        WHERE chemin=?
    ");

        $insNew = $pdo->prepare("
        INSERT INTO fichiers
        (
            chemin,
            type,
            status,
            date_scan,
            date_modification,
            num_revision,
            is_synced,
            updated_at
        )
        VALUES (?, ?, 'créé', NOW(), ?, 1, 0, NOW())
    ");

        $updSupprime = $pdo->prepare("
        UPDATE fichiers
        SET status='supprimé',
            date_scan=NOW()
        WHERE chemin=?
    ");

        $delFinal = $pdo->prepare(
            'DELETE FROM fichiers WHERE chemin=?'
        );

        $insertHistory = $pdo->prepare("
    INSERT INTO historique_fichiers
    (
        chemin,
        ancien_status,
        nouveau_status,
        admin,
        is_synced,
        type_scan,
        updated_at
    )
    VALUES (?, ?, ?, ?, 0, ?, NOW())
    ");

        $modifiedOrCreatedFiles = [];

        // =====================================================
        // Analyse des fichiers présents physiquement
        // =====================================================

        foreach ($scanItems as $item) {

            $path = $item['chemin'];
            $foundPaths[] = $path;

            $fullDiskPath =
                DIR_PROJECT_ROOT .
                DIRECTORY_SEPARATOR .
                str_replace(
                    ['/', '\\'],
                    DIRECTORY_SEPARATOR,
                    $path
                );

            $currentMtime = file_exists($fullDiskPath)
                ? filemtime($fullDiskPath)
                : 0;

            // Le fichier existe déjà dans la BDD
            if (isset($dbItems[$path])) {

                $oldMtime = (int) (
                    $dbItems[$path]['date_modification'] ?? 0
                );

                $oldStatus = $dbItems[$path]['status'];

                $isModified = ($oldMtime !== $currentMtime);

                if ($isModified) {

                    $newStatus = 'modifié';

                    $updStatusMod->execute([
                        $currentMtime,
                        $path
                    ]);
                } else {

                    $newStatus = 'existant';

                    $updStatusSame->execute([
                        $currentMtime,
                        $path
                    ]);
                }

                // Enregistrement uniquement lorsqu'il y a
                // réellement un changement d'état

                if ($oldStatus !== $newStatus) {

                    $insertHistory->execute([
                        $path,
                        $dbItems[$path]['status'],
                        $newStatus,
                        $adminUser,
                        $typeScan   
                    ]);

                    if ($newStatus === 'modifié') {
                        $modifiedOrCreatedFiles[] = $path;
                    }
                

                    if ($newStatus === 'modifié') {
                        $modifiedOrCreatedFiles[] = $path;
                    }
                }
            } else {

                // Nouveau fichier découvert
                $insNew->execute([
                    $path,
                    $item['type'],
                    $currentMtime
                ]);

                $insertHistory->execute([
                    $path,
                    'inexistant',
                    'créé',
                    $adminUser,
                    $typeScan
                ]);

                $modifiedOrCreatedFiles[] = $path;
            }
        }

        // =====================================================
        // Détection des fichiers absents du disque
        // =====================================================

        $missingOnDisk = array_diff(
            $existingPathsInDb,
            $foundPaths
        );

        foreach ($missingOnDisk as $path) {

            // Deuxième passage :
            // le fichier était déjà marqué supprimé,
            // on supprime définitivement sa ligne de fichiers.
            if ($dbItems[$path]['status'] === 'supprimé') {

                $delFinal->execute([$path]);
            } else {

                // Premier passage :
                // le fichier n'existe plus physiquement,
                // on le marque supprimé.
                $updSupprime->execute([$path]);

                $insertHistory->execute([
                    $path,
                    $dbItems[$path]['status'],
                    'supprimé',
                    $adminUser,
                    $typeScan
                ]);
            }
        }

        // =====================================================
        // Calcul des statistiques et versionnement
        // =====================================================

        $statsCurrent = $pdo->query("
        SELECT
            SUM(CASE WHEN status='créé' THEN 1 ELSE 0 END) AS total_cree,
            SUM(CASE WHEN status='modifié' THEN 1 ELSE 0 END) AS total_modifie,
            SUM(CASE WHEN status='supprimé' THEN 1 ELSE 0 END) AS total_supprime,
            SUM(CASE WHEN status='existant' THEN 1 ELSE 0 END) AS total_existant
        FROM fichiers
    ")->fetch(PDO::FETCH_ASSOC);

        $msgVersion = calculerTauxEtVersionner(
            $pdo,
            $statsCurrent
        );

        // =====================================================
        // Récupération de la version courante
        // =====================================================

        $verCurrentStmt = $pdo->query(
            'SELECT version_number
         FROM app_versions
         ORDER BY id DESC
         LIMIT 1'
        );

        $currentAppVer =
            $verCurrentStmt->fetchColumn() ?: 'v1.0.0';

        // =====================================================
        // Injection des en-têtes de version
        // =====================================================

        $stmtGetRev = $pdo->prepare(
            'SELECT num_revision
         FROM fichiers
         WHERE chemin = ?'
        );

        $updMtimePostInjection = $pdo->prepare(
            'UPDATE fichiers
         SET date_modification = ?
         WHERE chemin = ?'
        );

        foreach ($modifiedOrCreatedFiles as $relPath) {

            $stmtGetRev->execute([$relPath]);

            $nbRevisions = (int) (
                $stmtGetRev->fetchColumn() ?: 1
            );

            $fullDiskPath =
                DIR_PROJECT_ROOT .
                DIRECTORY_SEPARATOR .
                str_replace(
                    ['/', '\\'],
                    DIRECTORY_SEPARATOR,
                    $relPath
                );

            injecterEnTeteFichier(
                $fullDiskPath,
                $currentAppVer,
                $nbRevisions
            );

            if (file_exists($fullDiskPath)) {

                $newMtime = filemtime($fullDiskPath);

                $updMtimePostInjection->execute([
                    $newMtime,
                    $relPath
                ]);
            }
        }

        // Le manuel pourra afficher ce message.
        // Le silencieux pourra simplement l'ignorer.
        $pdo->commit();

        return $msgVersion;
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
/* ----------------------------- TRAITEMENT POST / GET ----------------------------- */
$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecu = $_POST['csrf_token'] ?? '';
    if (function_exists('check_csrf')) {
        if (!check_csrf($tokenRecu)) {
            die('CSRF invalid');
        }
    } else {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenRecu)) {
            die('CSRF invalid');
        }
    }

    if (($_POST['action'] ?? '') === 'purge_history') {
        $pdo->exec('DELETE FROM historique_fichiers');
        $statusMessage = "<p style='color:red; padding:5px;'>✅ Historique purgé avec succès.</p>";
    }

    if (($_POST['action'] ?? '') === 'run_scan') {

        $adminUser = $_SESSION['username'] ?? 'admin';

        $msgVersion = executerScan(
            $pdo,
            $adminUser,
            'manuel'
        );

        $statusMessage =
            "<p style='color:green; padding:10px;'>
            ✅ Scan terminé et BDD mise à jour.
            <br>
            🚀 <strong>" .
            htmlspecialchars($msgVersion) .
            "</strong>
        </p>";
    }
}

/* ----------------------------- CHARGEMENT DES DONNÉES ----------------------------- */
$files = $pdo->query('SELECT * FROM fichiers ORDER BY chemin ASC')->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("
    SELECT 
        SUM(CASE WHEN status='créé' THEN 1 ELSE 0 END) AS total_cree,
        SUM(CASE WHEN status='modifié' THEN 1 ELSE 0 END) AS total_modifie,
        SUM(CASE WHEN status='supprimé' THEN 1 ELSE 0 END) AS total_supprime,
        SUM(CASE WHEN status='existant' THEN 1 ELSE 0 END) AS total_existant
    FROM fichiers
")->fetch(PDO::FETCH_ASSOC);

$lastAppVersion = $pdo->query('SELECT version_number FROM app_versions ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 'v1.0.0';

/* ----------------------- PAGINATION & HISTORIQUE ---------------------- */
$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($_GET['from'])) {
    $where[] = 'date_action >= ?';
    $params[] = $_GET['from'] . ' 00:00:00';
}
if (!empty($_GET['to'])) {
    $where[] = 'date_action <= ?';
    $params[] = $_GET['to'] . ' 23:59:59';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM historique_fichiers $whereSQL");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$historyStmt = $pdo->prepare("SELECT * FROM historique_fichiers $whereSQL ORDER BY date_action DESC LIMIT $limit OFFSET $offset");
$historyStmt->execute($params);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$tree = buildTree($files);

/* ----------------------- INTERCEPTION EXPORT CSV ---------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'export_csv_logs') {
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
    $stmt = $pdo->prepare("SELECT date_action, chemin, ancien_status, nouveau_status, admin, type_scan FROM historique_fichiers $whereSQL ORDER BY date_action DESC");
    $stmt->execute($params);

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv(
        $output,
        ['Date', 'Fichier', 'Ancien Etat', 'Nouvel Etat', 'Admin', 'Type de scan'],
        ';'
    );

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

/* =====================================================
 * RÉPONSE AJAX PARTIELLE POUR L'HISTORIQUE (SI DEMANDÉ)
 * ===================================================== */
if (isset($_GET['ajax_history']) && $_GET['ajax_history'] === '1') {
    ob_start();
?>
    <div id="history-container">
        <div class="table-responsive shadow-lg rounded">
            <table class="table-custom-dark w-100 align-middle">
                <thead>
                    <tr class="bg-dark text-white border-bottom border-secondary">
                        <th class="p-2">Date</th>
                        <th class="p-2">Chemin</th>
                        <th class="p-2">Ancien Status</th>
                        <th class="p-2">Nouveau Status</th>
                        <th class="p-2">Admin</th>
                        <th class="p-2">Scan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history):
                        foreach ($history as $h): ?>
                            <tr class="border-bottom border-secondary-soft">
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['date_action']) ?></td>
                                <td class="p-2 small text-white text-break"><?= htmlspecialchars($h['chemin']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['ancien_status']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['nouveau_status']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['admin']) ?></td>
                                <td class="p-2 small text-white">
                                    <?= $h['type_scan'] === 'silencieux'
                                        ? '🤫 Silencieux'
                                        : '🖱️ Manuel' ?>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:15px;" class="text-white">Aucune modification enregistrée.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Pagination historique" class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">
                    <?php
                    $currentP = $_GET['p'] ?? 'structure_projet.php';
                    $baseUrl = '?p=' . urlencode($currentP);
                    $dateParams = '&from=' . urlencode($_GET['from'] ?? '') . '&to=' . urlencode($_GET['to'] ?? '');
                    ?>

                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $page - 1 ?><?= $dateParams ?>">«</a>
                    </li>

                    <?php
                    $range = 1;
                    for ($i = 1; $i <= $totalPages; $i++):
                        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)):
                    ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $i ?><?= $dateParams ?>"><?= $i ?></a>
                            </li>
                        <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif;
                    endfor; ?>

                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $page + 1 ?><?= $dateParams ?>">»</a>
                    </li>
                </ul>
            </nav>
            <div class="text-center text-muted small mb-3">
                Page <strong><?= $page ?></strong> sur <?= $totalPages ?> (<?= $totalRows ?> entrées)
            </div>
        <?php endif; ?>
    </div>
<?php
    echo ob_get_clean();
    exit;
}
?>
<?php $titrePage = '📁 Gestion Structure Projet'; ?>

<div class="container-xl glass-panel">
    <?php
    $titreSurcharge = 'Gestion Structure Projet';

    if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
        include DIR_LOGIC . 'top-bar-title-page.php';
    }
    ?>

    <div class="mb-3 p-3 border border-secondary rounded bg-glass shadow-sm">
        📊 <strong>Statistiques & Application :</strong><br>
        <div class="mt-2">
            🏷️ <strong>Version Application :</strong> <?= htmlspecialchars($lastAppVersion) ?><br>
            🟢 Existants : <?= (int) $stats['total_existant'] ?><br>
            🔵 Créés : <?= (int) $stats['total_cree'] ?><br>
            🟠 Modifiés : <?= (int) $stats['total_modifie'] ?><br>
            🔴 Supprimés : <?= (int) $stats['total_supprime'] ?>
        </div>
    </div>
    <div class="mt-4 mb-4">
        <a href="<?= BASE_URL ?>router.php?p=dashboard.php" class="btn btn-secondary w-100 w-md-auto">🏠 Retour au tableau de
            bord</a>
    </div>
    <div class="p-4 rounded border-start border-5 border-primary bg-glass mb-4 shadow-sm">
        <h3>Synchronisation</h3>
        <p class="text-muted">L'inventaire suit un cycle de 2 scans pour les suppressions.</p>
        <form method="POST">
            <?php if (function_exists('csrf_input')): ?>
                <?php csrf_input(); ?>
            <?php else: ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="run_scan">
            <button type="submit" class="btn-action-dash w-100 w-md-auto">🔍 Lancer le Scan manuel</button>
        </form>
        <?= $statusMessage ?>
    </div>

    <div id="project-structure" class="mb-4">
        <?php if (empty($tree)): ?>
            <p class="alert alert-info">Aucun fichier indexé. Veuillez lancer un scan.</p>
        <?php else: ?>
            <?php renderTree($tree) ?>
        <?php endif; ?>
    </div>

    <hr class="my-4">

    <!-- ===================================================== -->
    <!-- SECTION HISTORIQUE & FILTRAGE AJAX                   -->
    <!-- ===================================================== -->
    <div class="bg-glass p-3 rounded shadow-sm mb-3 border border-secondary">
        <form id="history-filter-form" method="GET" action="router.php"
            class="d-flex flex-column flex-md-row gap-3 align-items-start align-items-md-center mb-3 pb-3 border-bottom border-secondary-soft">
            <input type="hidden" name="p" value="structure_projet.php">

            <div class="d-flex align-items-center gap-2 w-100 w-md-auto">
                <label for="from" class="mb-0 text-white text-nowrap">De :</label>
                <input type="date" name="from" id="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>"
                    class="form-control bg-dark text-white border-secondary">
            </div>

            <div class="d-flex align-items-center gap-2 w-100 w-md-auto">
                <label for="to" class="mb-0 text-white text-nowrap">À :</label>
                <input type="date" name="to" id="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>"
                    class="form-control bg-dark text-white border-secondary">
            </div>

            <button type="submit" class="btn-action-dash w-100 w-md-auto mt-2 mt-md-0">Filtrer</button>
        </form>

        <div class="d-flex flex-column flex-md-row gap-2">
            <form method="GET" action="router.php?p=structure_projet.php" class="m-0 flex-fill">
                <input type="hidden" name="p"
                    value="<?= htmlspecialchars($_GET['p'] ?? 'admin/structure_projet.php') ?>">
                <input type="hidden" name="action" value="export_csv_logs">
                <input type="hidden" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
                <input type="hidden" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
                <button type="submit" class="btn-action-dash w-100">📦 Export CSV (Filtré)</button>
            </form>

            <form method="POST" class="m-0 flex-fill">
                <?php if (function_exists('csrf_input')): ?>
                    <?php csrf_input(); ?>
                <?php else: ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <?php endif; ?>
                <input type="hidden" name="action" value="purge_history">
                <button type="submit" class="btn-action-dash w-100 btn-outline-danger"
                    onclick="return confirm('Confirmer la purge de l\'historique ?')">🗑 Purger l’historique</button>
            </form>
        </div>
    </div>

    <!-- Conteneur ciblé par AJAX pour l'historique -->
    <div id="history-container">
        <div class="table-responsive shadow-lg rounded">
            <table class="table-custom-dark w-100 align-middle">
                <thead>
                    <tr class="bg-dark text-white border-bottom border-secondary">
                        <th class="p-2">Date</th>
                        <th class="p-2">Chemin</th>
                        <th class="p-2">Ancien Status</th>
                        <th class="p-2">Nouveau Status</th>
                        <th class="p-2">Admin</th>
                        <th class="p-2">Scan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history):
                        foreach ($history as $h): ?>
                            <tr class="border-bottom border-secondary-soft">
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['date_action']) ?></td>
                                <td class="p-2 small text-white text-break"><?= htmlspecialchars($h['chemin']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['ancien_status']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['nouveau_status']) ?></td>
                                <td class="p-2 small text-white"><?= htmlspecialchars($h['admin']) ?></td>
                                <td class="p-2 small text-white">
                                    <?= $h['type_scan'] === 'silencieux'
                                        ? '🤫 Silencieux'
                                        : '🖱️ Manuel' ?>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:15px;" class="text-white">Aucune modification enregistrée.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Pagination historique" class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">
                    <?php
                    $currentP = $_GET['p'] ?? 'structure_projet.php';
                    $baseUrl = '?p=' . urlencode($currentP);
                    $dateParams = '&from=' . urlencode($_GET['from'] ?? '') . '&to=' . urlencode($_GET['to'] ?? '');
                    ?>

                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $page - 1 ?><?= $dateParams ?>">«</a>
                    </li>

                    <?php
                    $range = 1;
                    for ($i = 1; $i <= $totalPages; $i++):
                        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)):
                    ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $i ?><?= $dateParams ?>"><?= $i ?></a>
                            </li>
                        <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif;
                    endfor; ?>

                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link ajax-nav" href="<?= $baseUrl ?>&page=<?= $page + 1 ?><?= $dateParams ?>">»</a>
                    </li>
                </ul>
            </nav>
            <div class="text-center text-muted small mb-3">
                Page <strong><?= $page ?></strong> sur <?= $totalPages ?> (<?= $totalRows ?> entrées)
            </div>
        <?php endif; ?>
    </div>

    <!-- ===================================================== -->
    <!-- SCRIPTS JS & INTERACTIONS                            -->
    <!-- ===================================================== -->
    <script>
        document.querySelectorAll('.folder>strong').forEach(el => {
            el.addEventListener('click', () => {
                el.parentElement.classList.toggle('collapsed');
            });
        });

        // Gestion de la navigation AJAX pour l'historique (pagination et filtres)
        document.addEventListener('DOMContentLoaded', () => {
            const loadHistoryContent = (url) => {
                const separator = url.includes('?') ? '&' : '?';
                const ajaxUrl = url + separator + 'ajax_history=1';

                fetch(ajaxUrl)
                    .then(response => response.text())
                    .then(html => {
                        const container = document.getElementById('history-container');
                        if (container) {
                            container.outerHTML = html;
                        }
                    })
                    .catch(error => console.error('Erreur lors du chargement de l\'historique :', error));
            };

            // Interception des clics sur la pagination de l'historique
            document.addEventListener('click', (e) => {
                const link = e.target.closest('.ajax-nav');
                if (link) {
                    e.preventDefault();
                    loadHistoryContent(link.href);
                }
            });

            // Interception de la soumission du formulaire de filtre de dates
            const filterForm = document.getElementById('history-filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(filterForm);
                    const params = new URLSearchParams(formData).toString();
                    const targetUrl = filterForm.action + '?' + params;
                    loadHistoryContent(targetUrl);
                });
            }
        });
    </script>

    <!-- Bouton sticky -->
    <button id="scrollTopBtn">🏠</button>
</div>
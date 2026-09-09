<?php
/* Version: v1.0.3 - 2026-08-18 */

/**
 * Fichier : includes/sql/tableau_mensuel_queries.php
 * RÈGLES : Requêtes SQL préparées pour le tableau de bord principal
 * SÉCURITÉ : Bindings stricts, PDO, typage des retours
 */

/**
 * Récupère les achats avec leurs totaux, échéances et catégories selon les filtres
 */
function getDashboardAchatsFiltres(
  PDO $pdo,
  int $userId,
  string $filtreMarchand,
  $filtreCategorie
): array {
  $filtreCategorie = ($filtreCategorie !== null && $filtreCategorie !== '') ? (int) $filtreCategorie : null;

  $sql = "
        SELECT 
            a.*,
            c.nom AS nom_categorie,
            c.couleur AS couleur_categorie,
            SUM(e.montant) AS total_echeances,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
            COUNT(e.id) AS nb_total_echeances,
            SUM(CASE WHEN e.statut != 'payee' THEN 1 ELSE 0 END) AS nb_restantes,
            MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance
        FROM achats a
        LEFT JOIN echeances e ON a.id = e.achat_id
        LEFT JOIN categories c ON (a.categorie_id = c.id AND (c.user_id = a.user_id OR c.id = 1))
        WHERE a.user_id = :user_id
    " . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    ' . ($filtreCategorie ? ' AND a.categorie_id = :categorie' : '') . '
        GROUP BY a.id
        ORDER BY prochaine_echeance ASC
    ';

  $params = ['user_id' => $userId];
  if ($filtreMarchand) {
    $params['marchand'] = $filtreMarchand;
  }
  if ($filtreCategorie) {
    $params['categorie'] = $filtreCategorie;
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les totaux globaux des échéances pour l'utilisateur
 */
function getDashboardTotauxGlobaux(PDO $pdo, int $userId): array
{
  $stmtTotaux = $pdo->prepare("
        SELECT 
            SUM(e.montant) AS total_echeances,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
            COUNT(CASE WHEN e.statut != 'payee' THEN 1 END) AS nb_reste
        FROM echeances e
        INNER JOIN achats a ON e.achat_id = a.id
        WHERE a.user_id = :user_id
    ");
  $stmtTotaux->execute(['user_id' => $userId]);
  return $stmtTotaux->fetch(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Récupère la configuration utilisateur (solde initial, première période, jour de début)
 */
function getDashboardUserData(PDO $pdo, int $userId): ?array
{
  $stmtUser = $pdo->prepare('
        SELECT solde_initial, premiere_periode, jour_debut_periode 
        FROM users 
        WHERE id = :id 
        LIMIT 1
    ');
  $stmtUser->execute(['id' => $userId]);
  return $stmtUser->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Récupère les échéances par mois (avec filtre glissant optionnel)
 */
function getDashboardEcheancesParMois(
  PDO $pdo,
  int $userId,
  int $jourDebutPeriode,
  string $filtreMarchand,
  bool $afficherTout,
  string $moisMinGlissant
): array {
  $queryMois = "
        SELECT 
            DATE_FORMAT(
                CASE
                    WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                    THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                    ELSE e.date_echeance
                END,
                '%Y-%m'
            ) AS mois,
            SUM(e.montant) AS total_mois,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
        FROM echeances e
        INNER JOIN achats a ON e.achat_id = a.id
        WHERE a.user_id = :user_id
    " . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
        GROUP BY mois
        HAVING 1=1
    ' . (!$afficherTout ? ' AND mois >= :mois_min' : '') . '
        ORDER BY mois ASC
    ';

  $paramsMois = [
    'user_id' => $userId,
    'jour_debut' => $jourDebutPeriode
  ];

  if (!$afficherTout) {
    $paramsMois['mois_min'] = $moisMinGlissant;
  }
  if ($filtreMarchand) {
    $paramsMois['marchand'] = $filtreMarchand;
  }

  $stmtMois = $pdo->prepare($queryMois);
  $stmtMois->execute($paramsMois);
  return $stmtMois->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'historique complet des échéances par mois (sans filtre glissant)
 */
function getDashboardEcheancesCompletes(
  PDO $pdo,
  int $userId,
  int $jourDebutPeriode,
  string $filtreMarchand
): array {
  $queryMoisComplet = "
        SELECT 
            DATE_FORMAT(
                CASE
                    WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                    THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                    ELSE e.date_echeance
                END,
                '%Y-%m'
            ) AS mois,
            SUM(e.montant) AS total_mois,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
        FROM echeances e
        INNER JOIN achats a ON e.achat_id = a.id
        WHERE a.user_id = :user_id
    " . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
        GROUP BY mois
        ORDER BY mois ASC
    ';

  $paramsComplet = [
    'user_id' => $userId,
    'jour_debut' => $jourDebutPeriode
  ];
  if ($filtreMarchand) {
    $paramsComplet['marchand'] = $filtreMarchand;
  }

  $stmtComplet = $pdo->prepare($queryMoisComplet);
  $stmtComplet->execute($paramsComplet);
  return $stmtComplet->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les revenus groupés par mois
 */
function getDashboardRevenusParMois(PDO $pdo, int $userId, int $jourDebutPeriode): array
{
  $stmtRevenusMois = $pdo->prepare("
        SELECT 
            DATE_FORMAT(
                CASE
                    WHEN :jour_debut > 1 AND DAY(v.date_versement_prevue) >= :jour_debut
                    THEN DATE_ADD(v.date_versement_prevue, INTERVAL 1 MONTH)
                    ELSE v.date_versement_prevue
                END,
                '%Y-%m'
            ) AS mois,
            SUM(
                CASE 
                    WHEN v.statut = 'percu'
                    THEN COALESCE(v.montant_reel, v.montant_prevu)
                    ELSE 0
                END
            ) AS revenu_percu,
            SUM(
                CASE 
                    WHEN v.statut = 'attendu' 
                         OR v.statut IS NULL 
                         OR v.statut = ''
                    THEN v.montant_prevu
                    ELSE 0
                END
            ) AS revenu_attendu,
            SUM(v.montant_prevu) AS revenu_total_prevu
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
        GROUP BY mois
        ORDER BY mois ASC
    ");

  $stmtRevenusMois->execute([
    'user_id' => $userId,
    'jour_debut' => $jourDebutPeriode
  ]);
  return $stmtRevenusMois->fetchAll(PDO::FETCH_ASSOC);
}

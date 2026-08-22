<?php
/* Version: v1.0.1 - 2026-08-18 */

/**
 * Fichier : includes/sql/dashboard_revenu_queries.php
 * RÈGLES : Requêtes SQL préparées pour le module Dashboard Revenus / Ressources
 * SÉCURITÉ : Bindings stricts, PDO, typage des retours
 */

/**
 * Récupère la liste des années disponibles pour les filtres de revenus
 */
function getDashboardRevenusAnneesDisponibles(PDO $pdo, int $userId): array
{
  $sql = "
        SELECT DISTINCT YEAR(v.date_versement_prevue) AS annee
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id AND v.date_versement_prevue IS NOT NULL
        ORDER BY annee DESC
    ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['user_id' => $userId]);
  return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Récupère la liste des ressources avec leurs totaux et versements selon les filtres
 */
function getDashboardRevenusFiltres(
  PDO $pdo,
  int $userId,
  string $filtreOrganisme,
  string $filtreStatut,
  $categorieFilter,
  string $anneeFilter,
  string $aujourdhui
): array {
  $categorieFilter = (int) $categorieFilter;

  $sql = "
        SELECT
            r.*,
            c.nom AS nom_categorie,
            c.parent_id AS categorie_parent_id,
            parent_cat.nom AS nom_categorie_parente,
            c.couleur AS couleur_categorie,
            COUNT(v.id) AS nb_total_versements,
            SUM(
                CASE
                    WHEN v.statut = 'percu'
                    THEN 1
                    ELSE 0
                END
            ) AS nb_percus,
            SUM(
                CASE
                    WHEN v.statut <> 'percu'
                         OR v.statut IS NULL
                    THEN 1
                    ELSE 0
                END
            ) AS nb_restants,
            SUM(v.montant_prevu) AS total_prevu,
            SUM(
                CASE
                    WHEN v.statut = 'percu'
                    THEN COALESCE(
                        v.montant_reel,
                        v.montant_prevu
                    )
                    ELSE 0
                END
            ) AS total_percu,
            MIN(
                CASE
                    WHEN v.statut <> 'percu'
                    THEN v.date_versement_prevue
                END
            ) AS prochain_versement,
            (
                SELECT v2.montant_prevu
                FROM versements v2
                WHERE v2.ressource_id = r.id
                  AND (v2.statut <> 'percu' OR v2.statut IS NULL)
                ORDER BY v2.date_versement_prevue ASC
                LIMIT 1
            ) AS prochain_montant_prevu
        FROM ressources r
        LEFT JOIN versements v ON r.id = v.ressource_id
        LEFT JOIN categories_ressources c ON (
            r.categorie_id = c.id
            AND (
                c.user_id = r.user_id
                OR c.user_id IS NULL
            )
        )
        LEFT JOIN categories_ressources parent_cat ON c.parent_id = parent_cat.id
        WHERE
            r.user_id = :user_id
            AND r.actif = 1
    ";

  $params = ['user_id' => $userId];

  // Application du filtre par catégorie (incluant les sous-catégories)
  if ($categorieFilter > 0) {
    $sqlCats = "SELECT id FROM categories_ressources WHERE user_id = :cat_user_id AND (id = :cat_id OR parent_id = :cat_id)";
    $stmtCats = $pdo->prepare($sqlCats);
    $stmtCats->execute([
      'cat_user_id' => $userId,
      'cat_id' => $categorieFilter
    ]);
    $idsCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsCategories)) {
      $idsCategories = [$categorieFilter];
    }

    $placeholders = [];
    foreach ($idsCategories as $index => $catIdKey) {
      $ph = 'cat_sub_' . $index;
      $placeholders[] = ':' . $ph;
      $params[$ph] = $catIdKey;
    }
    $sql .= ' AND r.categorie_id IN (' . implode(', ', $placeholders) . ') ';
  }

  // Application du filtre Organisme
  if ($filtreOrganisme !== '') {
    $sql .= ' AND r.organisme = :organisme ';
    $params['organisme'] = $filtreOrganisme;
  }

  // Application du filtre Année
  if ($anneeFilter !== '') {
    $sql .= '
            AND EXISTS (
                SELECT 1 FROM versements v_sub 
                WHERE v_sub.ressource_id = r.id 
                AND YEAR(v_sub.date_versement_prevue) = :annee_ressource
            )
        ';
    $params['annee_ressource'] = $anneeFilter;
  }

  // Regroupement et Tri des résultats
  $sql .= '
        GROUP BY
            r.id,
            c.nom,
            c.parent_id,
            parent_cat.nom,
            c.couleur
        ORDER BY
            prochain_versement ASC,
            r.titre ASC
    ';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $ressources = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Filtrage complémentaire par statut en PHP (aligné sur ta logique d'origine)
  if ($filtreStatut !== '') {
    $ressourcesFiltrees = [];
    foreach ($ressources as $ressource) {
      $resteAPercevoir = (float) $ressource['total_prevu'] - (float) $ressource['total_percu'];
      $statutRessource = 'attendu';

      if ($resteAPercevoir <= 0) {
        $statutRessource = 'percu';
      } elseif (!empty($ressource['prochain_versement']) && $ressource['prochain_versement'] < $aujourdhui) {
        $statutRessource = 'en_retard';
      }

      if ($statutRessource === $filtreStatut) {
        $ressourcesFiltrees[] = $ressource;
      }
    }
    $ressources = $ressourcesFiltrees;
  }

  return $ressources;
}

/**
 * Récupère les revenus groupés par mois selon les filtres
 */
function getDashboardRevenusParMois(
  PDO $pdo,
  int $userId,
  string $filtreOrganisme,
  string $filtreStatut,
  $categorieFilter,
  string $anneeFilter,
  string $aujourdhui
): array {
  $categorieFilter = (int) $categorieFilter;

  $sqlRevenusMois = "
        SELECT 
            DATE_FORMAT(v.date_versement_prevue, '%Y-%m') AS mois,
            SUM(
                CASE 
                    WHEN v.statut = 'percu'
                    THEN COALESCE(v.montant_reel, v.montant_prevu)
                    ELSE 0
                END
            ) AS revenu_percu,
            SUM(
                CASE 
                    WHEN (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
                         AND v.date_versement_prevue >= :aujourdhui
                    THEN v.montant_prevu
                    ELSE 0
                END
            ) AS revenu_attendu,
            SUM(v.montant_prevu) AS revenu_total_prevu
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
    ";

  $paramsRevenusMois = [
    'user_id' => $userId,
    'aujourdhui' => $aujourdhui
  ];

  // Application filtre catégorie pour les revenus mensuels
  if ($categorieFilter > 0) {
    $sqlCatsMois = "SELECT id FROM categories_ressources WHERE user_id = :cat_user_id AND (id = :cat_id OR parent_id = :cat_id)";
    $stmtCatsMois = $pdo->prepare($sqlCatsMois);
    $stmtCatsMois->execute([
      'cat_user_id' => $userId,
      'cat_id' => $categorieFilter
    ]);
    $idsCategoriesMois = $stmtCatsMois->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsCategoriesMois)) {
      $idsCategoriesMois = [$categorieFilter];
    }

    $placeholdersMois = [];
    foreach ($idsCategoriesMois as $indexMois => $catIdKeyMois) {
      $phMois = 'cat_mois_sub_' . $indexMois;
      $placeholdersMois[] = ':' . $phMois;
      $paramsRevenusMois[$phMois] = $catIdKeyMois;
    }
    $sqlRevenusMois .= ' AND r.categorie_id IN (' . implode(', ', $placeholdersMois) . ') ';
  }

  // Application filtre organisme pour les revenus mensuels
  if ($filtreOrganisme !== '') {
    $sqlRevenusMois .= ' AND r.organisme = :organisme ';
    $paramsRevenusMois['organisme'] = $filtreOrganisme;
  }

  // Application filtre statut pour les revenus mensuels
  if ($filtreStatut !== '') {
    if ($filtreStatut === 'percu') {
      $sqlRevenusMois .= " AND v.statut = 'percu' ";
    } elseif ($filtreStatut === 'attendu') {
      $sqlRevenusMois .= " AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '') AND v.date_versement_prevue >= :aujourdhui ";
    } elseif ($filtreStatut === 'en_retard') {
      $sqlRevenusMois .= " AND (v.statut != 'percu' OR v.statut IS NULL) AND v.date_versement_prevue < :aujourdhui ";
    }
  }

  // Application filtre année pour les revenus mensuels
  if ($anneeFilter !== '') {
    $sqlRevenusMois .= ' AND YEAR(v.date_versement_prevue) = :annee_revenu ';
    $paramsRevenusMois['annee_revenu'] = $anneeFilter;
  }

  // Regroupement mensuel
  $sqlRevenusMois .= ' GROUP BY mois ORDER BY mois ASC ';

  $stmtRevenusMois = $pdo->prepare($sqlRevenusMois);
  $stmtRevenusMois->execute($paramsRevenusMois);
  return $stmtRevenusMois->fetchAll(PDO::FETCH_ASSOC);
}

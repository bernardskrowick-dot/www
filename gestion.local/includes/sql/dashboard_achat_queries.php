<?php
/* Version: v1.0.1 - 2026-08-17 */

/**
 * Fichier : includes/sql/dashboard_achat_queries.php
 * RÈGLES : Requêtes SQL préparées pour le module Dashboard Achats
 * SÉCURITÉ : Bindings stricts, PDO, typage des retours
 */

/**
 * Récupère la liste des achats avec leurs totaux et échéances selon les filtres
 */
function getDashboardAchatsFiltres(
    PDO $pdo,
    int $userId,
    string $filtreMarchand,
    $filtreCategorie,
    string $dateDebut,
    string $dateFin,
    string $filtreAnnee
): array {
    $filtreCategorie = (int) $filtreCategorie;

    $sql = "
        SELECT 
            a.*,
            c.nom AS nom_categorie,
            c.parent_id AS categorie_parent_id,
            parent_cat.nom AS nom_categorie_parente,
            c.couleur AS couleur_categorie,
            SUM(e.montant) AS total_echeances,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
            COUNT(e.id) AS nb_total_echeances,
            SUM(CASE WHEN e.statut != 'payee' THEN 1 ELSE 0 END) AS nb_restantes,
            MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance,
            MAX(CASE WHEN e.statut = 'payee' THEN e.date_paiement END) AS derniere_date_paiement,
            (
                SELECT e2.montant 
                FROM echeances e2 
                WHERE e2.achat_id = a.id AND e2.statut != 'payee' 
                ORDER BY e2.date_echeance ASC 
                LIMIT 1
            ) AS prochain_montant
        FROM achats a
        LEFT JOIN echeances e ON a.id = e.achat_id
        LEFT JOIN categories c ON (a.categorie_id = c.id AND (c.user_id = a.user_id OR c.id = 1))
        LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
        WHERE a.user_id = :user_id
    " . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    ' . ($filtreCategorie ? ' AND (
            a.categorie_id = :categorie
            OR a.categorie_id IN (
                SELECT id 
                FROM categories 
                WHERE parent_id = :categorie
                AND user_id = :user_id
            )
        )' : '') . '
    ' . ($dateDebut ? ' AND e.date_echeance >= :date_debut' : '') . '
    ' . ($dateFin ? ' AND e.date_echeance <= :date_fin' : '') . '
    ' . ($filtreAnnee && !$dateDebut && !$dateFin ? ' AND YEAR(e.date_echeance) = :annee' : '') . '
        GROUP BY a.id
        ORDER BY prochaine_echeance ASC
    ';

    $params = ['user_id' => $userId];
    if ($filtreMarchand) $params['marchand'] = $filtreMarchand;
    if ($filtreCategorie) $params['categorie'] = $filtreCategorie;
    if ($dateDebut) $params['date_debut'] = $dateDebut;
    if ($dateFin) $params['date_fin'] = $dateFin;
    if ($filtreAnnee && !$dateDebut && !$dateFin) $params['annee'] = $filtreAnnee;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les totaux globaux des achats et échéances selon les filtres
 */
function getDashboardAchatsTotaux(
    PDO $pdo,
    int $userId,
    $filtreCategorie,
    string $dateDebut,
    string $dateFin,
    string $filtreAnnee
): array {
    $filtreCategorie = (int) $filtreCategorie;

    $sql = "
        SELECT 
            SUM(e.montant) AS total_echeances,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
            COUNT(CASE WHEN e.statut != 'payee' THEN 1 END) AS nb_reste
        FROM echeances e
        INNER JOIN achats a ON e.achat_id = a.id
        WHERE a.user_id = :user_id
    " . ($filtreCategorie ? ' AND (
            a.categorie_id = :categorie
            OR a.categorie_id IN (
                SELECT id
                FROM categories
                WHERE parent_id = :categorie
                AND user_id = :user_id
            )
        )' : '') . '
    ' . ($dateDebut ? ' AND e.date_echeance >= :date_debut' : '') . '
    ' . ($dateFin ? ' AND e.date_echeance <= :date_fin' : '') . '
    ' . ($filtreAnnee && !$dateDebut && !$dateFin ? ' AND YEAR(e.date_echeance) = :annee' : '') . '
    ';

    $params = ['user_id' => $userId];
    if ($filtreCategorie) $params['categorie'] = $filtreCategorie;
    if ($dateDebut) $params['date_debut'] = $dateDebut;
    if ($dateFin) $params['date_fin'] = $dateFin;
    if ($filtreAnnee && !$dateDebut && !$dateFin) $params['annee'] = $filtreAnnee;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Récupère les échéances groupées par mois selon les filtres
 */
function getDashboardAchatsParMois(
    PDO $pdo,
    int $userId,
    string $filtreMarchand,
    $filtreCategorie,
    string $dateDebut,
    string $dateFin,
    string $filtreAnnee
): array {
    $filtreCategorie = (int) $filtreCategorie;

    $sql = "
        SELECT 
            DATE_FORMAT(e.date_echeance, '%Y-%m') AS mois,
            SUM(e.montant) AS total_mois,
            SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
        FROM echeances e
        INNER JOIN achats a ON e.achat_id = a.id
        WHERE a.user_id = :user_id
    " . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    ' . ($filtreCategorie ? ' AND (
            a.categorie_id = :categorie
            OR a.categorie_id IN (
                SELECT id 
                FROM categories 
                WHERE parent_id = :categorie
                AND user_id = :user_id
            )
        )' : '') . '
    ' . ($dateDebut ? ' AND e.date_echeance >= :date_debut' : '') . '
    ' . ($dateFin ? ' AND e.date_echeance <= :date_fin' : '') . '
    ' . ($filtreAnnee && !$dateDebut && !$dateFin ? ' AND YEAR(e.date_echeance) = :annee' : '') . '
        GROUP BY mois
        ORDER BY mois
    ';

    $params = ['user_id' => $userId];
    if ($filtreMarchand) $params['marchand'] = $filtreMarchand;
    if ($filtreCategorie) $params['categorie'] = $filtreCategorie;
    if ($dateDebut) $params['date_debut'] = $dateDebut;
    if ($dateFin) $params['date_fin'] = $dateFin;
    if ($filtreAnnee && !$dateDebut && !$dateFin) $params['annee'] = $filtreAnnee;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

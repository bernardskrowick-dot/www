<?php
/* Version: v1.1.0 - 2026-08-17 */

/**
 * Fichier : includes/sql/dashboard_queries.php
 * RÈGLES : Requêtes SQL préparées et calculs pour le Dashboard Général (Vue d'ensemble)
 * SÉCURITÉ : Bindings stricts, PDO, typage des retours
 */

/**
 * 1.1 Récupère les achats et échéances de la période active (Dashboard Général)
 */
function getDashboardAchatsPeriode(PDO $pdo, int $userId, string $mois, int $jourDebut): array
{
  $query = "
        SELECT
            echeances.id AS operation_id,
            echeances.id,
            echeances.achat_id,
            echeances.date_echeance AS date_operation,
            echeances.montant,
            echeances.statut,
            achats.nom_marchand,
            achats.titre,
            'achat' AS type_operation
        FROM echeances
        INNER JOIN achats
            ON echeances.achat_id = achats.id
        WHERE DATE_FORMAT(
                CASE
                    WHEN DAY(echeances.date_echeance) > :jour_debut
                    THEN DATE_ADD(echeances.date_echeance, INTERVAL 1 MONTH)
                    ELSE echeances.date_echeance
                END,
                '%Y-%m'
              ) = :mois
          AND achats.user_id = :user_id
    ";
  $stmt = $pdo->prepare($query);
  $stmt->execute([
    'mois' => $mois,
    'user_id' => $userId,
    'jour_debut' => $jourDebut
  ]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 1.2 Récupère les revenus et versements de la période active (Dashboard Général)
 */
function getDashboardVersementsPeriode(PDO $pdo, int $userId, string $mois, int $jourDebut): array
{
  $query = "
        SELECT
            versements.id AS operation_id,
            versements.id,
            versements.ressource_id,
            versements.date_versement_prevue AS date_operation,
            versements.montant_prevu AS montant,
            versements.statut,
            ressources.organisme AS nom_marchand,
            ressources.titre,
            ressources.categorie_id AS categorie_id,
            'ressource' AS type_operation
        FROM versements
        LEFT JOIN ressources
            ON ressources.id = versements.ressource_id
        WHERE DATE_FORMAT(
                CASE
                    WHEN DAY(versements.date_versement_prevue) > :jour_debut
                    THEN DATE_ADD(versements.date_versement_prevue, INTERVAL 1 MONTH)
                    ELSE versements.date_versement_prevue
                END,
                '%Y-%m'
              ) = :mois
          AND ressources.user_id = :user_id
    ";
  $stmt = $pdo->prepare($query);
  $stmt->execute([
    'mois' => $mois,
    'user_id' => $userId,
    'jour_debut' => $jourDebut
  ]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 2.1 Synthèse globale des achats et échéances (Dashboard Général)
 */
function getDashboardTotauxAchats(PDO $pdo, int $userId): array
{
  $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(e.montant), 0) AS total_echeances,
            COALESCE(SUM(CASE WHEN e.statut = 'payee' THEN e.montant ELSE 0 END), 0) AS total_paye,
            COUNT(CASE WHEN e.statut != 'payee' THEN 1 END) AS nb_reste
        FROM echeances e
        INNER JOIN achats a ON a.id = e.achat_id
        WHERE a.user_id = :user_id
    ");
  $stmt->execute(['user_id' => $userId]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 2.2 Synthèse globale des revenus et versements (Dashboard Général)
 */
function getDashboardRevenusSynthese(PDO $pdo, int $userId): array
{
  $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(v.montant_prevu), 0) AS total_prevu,
            COALESCE(SUM(
                CASE
                    WHEN v.statut = 'percu' THEN COALESCE(v.montant_reel, v.montant_prevu)
                    ELSE 0
                END
            ), 0) AS total_recu,
            COALESCE(SUM(
                CASE
                    WHEN v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '' THEN v.montant_prevu
                    ELSE 0
                END
            ), 0) AS total_restant_a_percevoir,
            COUNT(
                CASE
                    WHEN v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '' THEN 1
                END
            ) AS nb_attendus
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
    ");
  $stmt->execute(['user_id' => $userId]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 3.1 Activité récente unifiée (Prochaines échéances et revenus)
 */
function getDashboardActiviteRecente(PDO $pdo, int $userId): array
{
  $stmt = $pdo->prepare("
        SELECT 
            e.date_echeance AS date_event, 
            a.nom_marchand COLLATE utf8mb4_unicode_ci AS libelle, 
            e.montant, 
            'achat' AS type_flux 
        FROM echeances e
        INNER JOIN achats a ON a.id = e.achat_id
        WHERE a.user_id = :user_id 
          AND e.statut != 'payee'
        
        UNION ALL
        
        SELECT 
            v.date_versement_prevue AS date_event, 
            r.organisme COLLATE utf8mb4_unicode_ci AS libelle, 
            v.montant_prevu AS montant, 
            'ressource' AS type_flux 
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
          AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
        
        ORDER BY date_event ASC
        LIMIT 5
    ");
  $stmt->execute(['user_id' => $userId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 3.2 Revenus réellement encaissés jusqu'aujourd'hui
 */
function getDashboardSoldeRevenusReels(PDO $pdo, int $userId, string $dateJour): float
{
  $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN v.statut = 'percu' THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ), 0) AS revenus_reels
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id AND v.date_versement_prevue <= :dateJour
    ");
  $stmt->execute(['user_id' => $userId, 'dateJour' => $dateJour]);
  return (float) $stmt->fetchColumn();
}

/**
 * 3.3 Alertes financières unifiées (Achats + Revenus à 7 jours)
 */
function getDashboardAlertesFinancieres(PDO $pdo, int $userId, string $dateJour): array
{
  $stmt = $pdo->prepare("
        SELECT 
            e.date_echeance AS date_event, 
            a.nom_marchand COLLATE utf8mb4_unicode_ci AS libelle, 
            e.montant, 
            'achat' AS type_flux 
        FROM echeances e
        INNER JOIN achats a ON a.id = e.achat_id
        WHERE a.user_id = :user_id
          AND e.statut != 'payee'
          AND e.date_echeance <= DATE_ADD(:dateJour, INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            v.date_versement_prevue AS date_event, 
            r.organisme COLLATE utf8mb4_unicode_ci AS libelle, 
            v.montant_prevu AS montant, 
            'ressource' AS type_flux 
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
          AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
          AND v.date_versement_prevue <= DATE_ADD(:dateJour, INTERVAL 7 DAY)
        
        ORDER BY date_event ASC
        LIMIT 10
    ");
  $stmt->execute(['user_id' => $userId, 'dateJour' => $dateJour]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 3.4 Prochain revenu attendu
 */
function getDashboardProchainRevenu(PDO $pdo, int $userId, string $dateJour): ?array
{
  $stmt = $pdo->prepare("
        SELECT r.titre, v.date_versement_prevue, v.montant_prevu
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
          AND v.date_versement_prevue >= :dateJour
          AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
        ORDER BY v.date_versement_prevue ASC
        LIMIT 1
    ");
  $stmt->execute(['user_id' => $userId, 'dateJour' => $dateJour]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  return $result ?: null;
}

/**
 * 3.5 Analyse de la plus grosse dépense de la période
 */
function getDashboardPlusGrosseDepense(PDO $pdo, int $userId, string $mois, int $jourDebut): ?array
{
  $query = "
        SELECT achats.nom_marchand, achats.titre, echeances.montant, echeances.date_echeance
        FROM echeances
        INNER JOIN achats ON echeances.achat_id = achats.id
        WHERE DATE_FORMAT(
                CASE
                    WHEN DAY(echeances.date_echeance) > :jour_debut
                    THEN DATE_ADD(echeances.date_echeance, INTERVAL 1 MONTH)
                    ELSE echeances.date_echeance
                END,
                '%Y-%m'
              ) = :mois
          AND achats.user_id = :user_id
        ORDER BY echeances.montant DESC
        LIMIT 1
    ";
  $stmt = $pdo->prepare($query);
  $stmt->execute([
    'jour_debut' => $jourDebut,
    'mois' => $mois,
    'user_id' => $userId
  ]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  return $result ?: null;
}

/**
 * 4.1 Récupération configuration utilisateur (Trésorerie historique)
 */
function getDashboardUserData(PDO $pdo, int $userId): ?array
{
  $stmt = $pdo->prepare('
        SELECT solde_initial, premiere_periode, jour_debut_periode 
        FROM users 
        WHERE id = :id 
        LIMIT 1
    ');
  $stmt->execute(['id' => $userId]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  return $result ?: null;
}

/**
 * 4.2 Dépenses historiques pour un mois de la boucle de trésorerie
 */
function getDashboardSoldeMoisDepenses(PDO $pdo, int $userId, int $jourDebut, string $cleMois): float
{
  $stmt = $pdo->prepare("
        SELECT SUM(e.montant)
        FROM echeances e
        INNER JOIN achats a ON a.id = e.achat_id
        WHERE a.user_id = :user_id
          AND DATE_FORMAT(
                CASE
                    WHEN DAY(e.date_echeance) > :jour_debut
                    THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                    ELSE e.date_echeance
                END,
                '%Y-%m'
              ) = :mois
    ");
  $stmt->execute([
    'user_id' => $userId,
    'jour_debut' => $jourDebut,
    'mois' => $cleMois
  ]);
  return (float) ($stmt->fetchColumn() ?? 0);
}

/**
 * 4.3 Revenus historiques pour un mois de la boucle de trésorerie
 */
function getDashboardSoldeMoisRevenus(PDO $pdo, int $userId, int $jourDebut, string $cleMois): float
{
  $stmt = $pdo->prepare("
        SELECT SUM(
            CASE
                WHEN v.statut = 'percu' THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE v.montant_prevu
            END
        )
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
          AND DATE_FORMAT(
                CASE
                    WHEN DAY(v.date_versement_prevue) > :jour_debut
                    THEN DATE_ADD(v.date_versement_prevue, INTERVAL 1 MONTH)
                    ELSE v.date_versement_prevue
                END,
                '%Y-%m'
              ) = :mois
    ");
  $stmt->execute([
    'user_id' => $userId,
    'jour_debut' => $jourDebut,
    'mois' => $cleMois
  ]);
  return (float) ($stmt->fetchColumn() ?? 0);
}

/**
 * 7.1 Total des revenus à percevoir jusqu'au 31/12 (Projections annuelles)
 */
function getDashboardResteEncaisserAnnee(PDO $pdo, int $userId, string $dateFinAnnee): float
{
  $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(v.montant_prevu), 0) AS total
        FROM versements v
        INNER JOIN ressources r ON r.id = v.ressource_id
        WHERE r.user_id = :user_id
          AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
          AND v.date_versement_prevue <= :date_fin
    ");
  $stmt->execute([
    'user_id' => $userId,
    'date_fin' => $dateFinAnnee
  ]);
  return (float) $stmt->fetchColumn();
}

/**
 * 7.2 Total des charges à payer jusqu'au 31/12 (Projections annuelles)
 */
function getDashboardResteDepenserAnnee(PDO $pdo, int $userId, string $dateFinAnnee): float
{
  $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(e.montant), 0) AS total
        FROM echeances e
        INNER JOIN achats a ON a.id = e.achat_id
        WHERE a.user_id = :user_id
          AND e.statut != 'payee'
          AND e.date_echeance <= :date_fin
    ");
  $stmt->execute([
    'user_id' => $userId,
    'date_fin' => $dateFinAnnee
  ]);
  return (float) $stmt->fetchColumn();
}

/* -------------------------------------------------------------------------- */
/* INITIALISATION DES VARIABLES DU HTML (Dashboard Général)                   */
/* -------------------------------------------------------------------------- */
if (isset($pdo, $_SESSION['user_id'])) {
  $currentUserId = (int) $_SESSION['user_id'];
  $currentDateJour = date('Y-m-d');

  $syntheseRevenusHtml = getDashboardRevenusSynthese($pdo, $currentUserId);
  $revenusAttendusMois = (float) ($syntheseRevenusHtml['total_restant_a_percevoir'] ?? 0.00);

  $syntheseAchatsHtml = getDashboardTotauxAchats($pdo, $currentUserId);
  $echeancesRestantesMois = (float) ($syntheseAchatsHtml['total_echeances'] ?? 0.00) - (float) ($syntheseAchatsHtml['total_paye'] ?? 0.00);

  // 1. Récupération dynamique du jour de début de période de l'utilisateur
  $jourDebutPeriode = 1; // Valeur par défaut
  $stmtUser = $pdo->prepare("SELECT jour_debut_periode FROM users WHERE id = ?");
  $stmtUser->execute([$currentUserId]);
  $userDataUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
  if ($userDataUser && isset($userDataUser['jour_debut_periode'])) {
    $jourDebutPeriode = (int) $userDataUser['jour_debut_periode'];
  }

  // 2. Votre logique exacte pour calculer le relevé bancaire sans l'écraser
  $dateAujourdhuiClean = new DateTime(date('Y-m-d', strtotime($currentDateJour)));
  $jourCourantNum     = (int) $dateAujourdhuiClean->format('d');
  $jourDebutStr       = str_pad($jourDebutPeriode, 2, '0', STR_PAD_LEFT);

  if ($jourCourantNum >= $jourDebutPeriode) {
    $prochainDebutPeriode = new DateTime(date('Y-m-' . $jourDebutStr, strtotime('+1 month', strtotime($currentDateJour))));
  } else {
    $prochainDebutPeriode = new DateTime(date('Y-m-' . $jourDebutStr, strtotime($currentDateJour)));
  }

  $dateProchainReleve = clone $prochainDebutPeriode;
  $dateProchainReleve->modify('-1 day');

  $intervalleReleve    = $dateAujourdhuiClean->diff($dateProchainReleve);
  $joursRestantsReleve = (int) $intervalleReleve->days;
  if ($joursRestantsReleve < 0) {
    $joursRestantsReleve = 0;
  }
}

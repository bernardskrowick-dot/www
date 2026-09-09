<?php
/* Version: v1.1.1 - 2026-08-18 */

/**
 * Fichier : includes/sql/dashboard_kpi.php
 * RÈGLES : Traitements métiers, calculs historiques et KPIs du Dashboard
 * CORRECTION : Sécurisation de l'accès aux clés du tableau des opérations pour éviter les warnings.
 */

/**
 * Calcule l'intégralité des KPIs et des données de la période pour le Dashboard
 */
function computeDashboardData(PDO $pdo, int $userId, string $mois, string $dateJour, array $achats, array $versements): array
{

  // =======================================================
  // 4. CALCUL DU SOLDE HISTORIQUE (BOUCLE DE TRÉSORERIE)
  // =======================================================
  $userData = getDashboardUserData($pdo, $userId);

  $jourDebutPeriode = (int) ($userData['jour_debut_periode'] ?? 1);
  $premierePeriode  = (string) ($userData['premiere_periode'] ?? date('Y-m'));
  $soldeInitial     = (float) ($userData['solde_initial'] ?? 0);

  $soldeMoisPrecedent = $soldeInitial;

  $moisCourant = new DateTime($premierePeriode . '-01');
  $moisCourant->modify('+1 month');

  $moisFin = new DateTime($mois . '-01');
  $moisFin->modify('-1 month');

  while ($moisCourant <= $moisFin) {
    $cleMois = $moisCourant->format('Y-m');

    $depensesAvant = getDashboardSoldeMoisDepenses($pdo, $userId, $jourDebutPeriode, $cleMois);
    $revenusAvant = getDashboardSoldeMoisRevenus($pdo, $userId, $jourDebutPeriode, $cleMois);

    $soldeMoisPrecedent += $revenusAvant - $depensesAvant;

    $moisCourant->modify('+1 month');
  }

  // =======================================================
  // 5. TRAITEMENT & AGRÉGATION DES OPÉRATIONS DE LA PÉRIODE
  // =======================================================
  $operations = array_merge($achats, $versements);

  $totalRevenusMois = 0;
  $totalDepensesMois = 0;

  $totalAchats = 0;
  $totalAchatsPayes = 0;
  $totalRessources = 0;
  $totalRessourcesPercues = 0;
  $nbAchats = 0;
  $nbRevenus = 0;

  // Variables spécifiques demandées
  $totalRestantFiltre = 0;
  $totalEcheancesAffichees = 0;
  $totalEcheancesRestantesFiltre = 0;
  $totalPayeFiltre = 0;
  $montantTotalFiltre = 0;
  $prochaineEcheanceGlobale = null;
  $prochainMontantGlobal = 0;

  foreach ($operations as &$op) {
    // Sécurisation de la catégorie
    $op['categorie_id'] = isset($op['categorie_id']) ? (int) $op['categorie_id'] : null;

    // Sécurisation du montant (évite le warning undefined array key)
    $montant = (float) ($op['montant'] ?? 0);
    $typeOperation = $op['type_operation'] ?? '';
    $statutOp = $op['statut'] ?? '';

    if ($typeOperation === 'ressource') {
      $totalRevenusMois += $montant;
      $totalRessources += $montant;
      $nbRevenus++;

      if ($statutOp === 'percu') {
        $totalRessourcesPercues += $montant;
      }
    } else {
      $totalDepensesMois += $montant;
      $totalAchats++;
      $nbAchats++;

      if ($statutOp === 'payee') {
        $totalAchatsPayes += $montant;
      }

      // Calculs spécifiques pour les achats
      $totalPayeFiltre += (float) ($op['total_paye'] ?? 0);
      $montantTotalFiltre += (float) ($op['total_echeances'] ?? 0);
      $totalRestantFiltre += (float) (($op['total_echeances'] ?? 0) - ($op['total_paye'] ?? 0));
      $totalEcheancesAffichees += (int) ($op['nb_total_echeances'] ?? 0);
      $totalEcheancesRestantesFiltre += (int) ($op['nb_restantes'] ?? 0);

      // Prochaine échéance globale
      if (!empty($op['prochaine_echeance'])) {
        if ($prochaineEcheanceGlobale === null || $op['prochaine_echeance'] < $prochaineEcheanceGlobale) {
          $prochaineEcheanceGlobale = $op['prochaine_echeance'];
          $prochainMontantGlobal = (float) ($op['prochain_montant'] ?? 0);
        }
      }
    }
  }
  unset($op);

  $montantMoyenAchat = ($totalAchats > 0) ? ($montantTotalFiltre / $totalAchats) : 0;

  $soldeMois = $soldeMoisPrecedent + $totalRevenusMois - $totalDepensesMois;
  $resteAchats = $totalAchats - $totalAchatsPayes;
  $resteRessources = $totalRessources - $totalRessourcesPercues;
  $totalOperations = count($operations);

  $pourcentageAchats = $totalOperations > 0 ? ($nbAchats / $totalOperations) * 100 : 0;
  $ratioVolumeRevenus = $totalOperations > 0 ? ($nbRevenus / $totalOperations) * 100 : 0;
  $nomMois = function_exists('moisFrancais') ? moisFrancais($mois) : $mois;

  // =======================================================
  // 6. CALCULS DES INDICATEURS CLÉS DE PERFORMANCE (KPIs)
  // =======================================================

  $soldeReel = (float) $soldeMoisPrecedent;
  $echeancesRestantesMois = 0.0;
  $revenusAttendusMois = 0.0;
  $fluxFuturs = [];

  foreach ($operations as $op) {
    $montant = (float) ($op['montant'] ?? 0);
    $dateOp = $op['date_operation'] ?? $dateJour;
    $statutOp = $op['statut'] ?? '';
    $typeOperation = $op['type_operation'] ?? '';

    if ($typeOperation === 'ressource') {
      $montantReel = (isset($op['montant_reel']) && $op['montant_reel'] !== null) ? (float) $op['montant_reel'] : $montant;

      if ($statutOp === 'percu') {
        $soldeReel += $montantReel;
      } else {
        $revenusAttendusMois += $montant;
        $fluxFuturs[] = [
          'date' => $dateOp,
          'montant' => $montant,
          'type' => 'revenu'
        ];
      }
    } else {
      if ($statutOp === 'payee') {
        $soldeReel -= $montant;
      } else {
        $echeancesRestantesMois += $montant;
        $fluxFuturs[] = [
          'date' => $dateOp,
          'montant' => $montant,
          'type' => 'depense'
        ];
      }
    }
  }

  $resteAVivre = $soldeReel + $revenusAttendusMois - $echeancesRestantesMois;

  if ($resteAVivre < 0) {
    $statutResteAVivre = 'danger';
    $badgeResteAVivre = '🔴 Déficitaire';
  } elseif ($resteAVivre < 200) {
    $statutResteAVivre = 'warning';
    $badgeResteAVivre = '🟠 Tension';
  } else {
    $statutResteAVivre = 'success';
    $badgeResteAVivre = '🟢 Confortable';
  }

  $ressourcesDisponibles = $soldeReel + $revenusAttendusMois;
  if ($echeancesRestantesMois > 0) {
    $tauxCouverture = ($ressourcesDisponibles / $echeancesRestantesMois) * 100;
  } else {
    $tauxCouverture = ($ressourcesDisponibles >= 0) ? 100.0 : 0.0;
  }

  if ($tauxCouverture >= 120) {
    $statutCouverture = 'success';
    $badgeCouverture = '🟢 Solide';
  } elseif ($tauxCouverture >= 100) {
    $statutCouverture = 'warning';
    $badgeCouverture = '🟠 Juste équilibre';
  } else {
    $statutCouverture = 'danger';
    $badgeCouverture = '🔴 Risque de déficit';
  }

  usort($fluxFuturs, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
  });

  $soldeGlissant = $soldeReel;
  $pointBasTresorerie = $soldeReel;
  $datePointBas = $dateJour;

  foreach ($fluxFuturs as $flux) {
    if ($flux['type'] === 'revenu') {
      $soldeGlissant += $flux['montant'];
    } else {
      $soldeGlissant -= $flux['montant'];
    }

    if ($soldeGlissant < $pointBasTresorerie) {
      $pointBasTresorerie = $soldeGlissant;
      $datePointBas = $flux['date'];
    }
  }

  $soldeProjeteFinMois = $soldeGlissant;

  if ($pointBasTresorerie < 0) {
    $statutPointBas = 'danger';
    $badgePointBas = '🔴 Découvert prévu';
  } elseif ($pointBasTresorerie < 200) {
    $statutPointBas = 'warning';
    $badgePointBas = '🟠 Seuil critique';
  } else {
    $statutPointBas = 'success';
    $badgePointBas = '🟢 Toujours positif';
  }

  $dateAujourdhuiClean = new DateTime(date('Y-m-d', strtotime($dateJour)));
  $jourCourantNum     = (int) $dateAujourdhuiClean->format('d');
  $jourDebutStr       = str_pad($jourDebutPeriode, 2, '0', STR_PAD_LEFT);

  if ($jourCourantNum > $jourDebutPeriode) {
    $dateProchainReleve = new DateTime(date('Y-m-' . $jourDebutStr, strtotime('+1 month', strtotime($dateJour))));
  } else {
    $dateProchainReleve = new DateTime(date('Y-m-' . $jourDebutStr, strtotime($dateJour)));
  }

  $intervalleReleve    = $dateAujourdhuiClean->diff($dateProchainReleve);
  $joursRestantsReleve = (int) $intervalleReleve->days;
  if ($joursRestantsReleve < 0) {
    $joursRestantsReleve = 0;
  }

  if ($joursRestantsReleve <= 3) {
    $statutJoursRestants = 'danger';
    $badgeJoursRestants  = '🔴 Relevé imminent';
  } elseif ($joursRestantsReleve <= 7) {
    $statutJoursRestants = 'warning';
    $badgeJoursRestants  = '🟠 Relevé très proche';
  } else {
    $statutJoursRestants = 'success';
    $badgeJoursRestants  = '✅ Temps restant confortable';
  }

  $tendanceSolde = $totalRevenusMois - $totalDepensesMois;
  if ($tendanceSolde > 0) {
    $statutTendance = 'success';
    $badgeTendance = '📈 Situation favorable';
    $texteTendance = 'Le solde progresse sur cette période';
    $signeTendance = '+';
  } elseif ($tendanceSolde < 0) {
    $statutTendance = 'danger';
    $badgeTendance = '📉 Situation défavorable';
    $texteTendance = 'Le solde régresse sur cette période';
    $signeTendance = '';
  } else {
    $statutTendance = 'info';
    $badgeTendance = '➡️ Solde stable';
    $texteTendance = 'Équilibre parfait sur cette période';
    $signeTendance = '';
  }

  $tauxEpargne = ($totalRevenusMois > 0) ? (($totalRevenusMois - $totalDepensesMois) / $totalRevenusMois) * 100 : 0;
  if ($tauxEpargne >= 15) {
    $statutEpargne = 'success';
    $badgeEpargne = '🎯 Excellente gestion';
  } elseif ($tauxEpargne > 0) {
    $statutEpargne = 'warning';
    $badgeEpargne = '⚠️ Épargne modérée';
  } else {
    $statutEpargne = 'danger';
    $badgeEpargne = '🚨 Solde négatif';
  }

  $soldePourBudget = $resteAVivre > 0 ? $resteAVivre : $soldeProjeteFinMois;
  $budgetQuotidien = ($joursRestantsReleve > 0) ? ($soldePourBudget / $joursRestantsReleve) : $soldePourBudget;

  if ($budgetQuotidien >= 20) {
    $statutBudgetJour = 'success';
    $badgeBudgetJour = '👍 Marge confortable';
  } elseif ($budgetQuotidien > 0) {
    $statutBudgetJour = 'warning';
    $badgeBudgetJour = '⚖️ Attention au quotidien';
  } else {
    $statutBudgetJour = 'danger';
    $badgeBudgetJour = '🛑 Découvert prévu';
  }

  $alerteDecouvertDate = false;
  $dateDecouvertFormat = '';
  $montantDecouvertAlerte = 0;

  if (isset($pointBasTresorerie) && $pointBasTresorerie < 0) {
    $alerteDecouvertDate = true;
    $montantDecouvertAlerte = abs($pointBasTresorerie);
    if (!empty($datePointBas)) {
      $dateDecouvertObj = new DateTime($datePointBas);
      $dateDecouvertFormat = $dateDecouvertObj->format('d/m/Y');
    }
  }

  return compact(
    'jourDebutPeriode',
    'soldeMoisPrecedent',
    'operations',
    'totalRevenusMois',
    'totalDepensesMois',
    'totalAchats',
    'totalAchatsPayes',
    'totalRessources',
    'totalRessourcesPercues',
    'nbAchats',
    'nbRevenus',
    'soldeMois',
    'resteAchats',
    'resteRessources',
    'totalOperations',
    'pourcentageAchats',
    'ratioVolumeRevenus',
    'nomMois',
    'soldeReel',
    'resteAVivre',
    'statutResteAVivre',
    'badgeResteAVivre',
    'tauxCouverture',
    'statutCouverture',
    'badgeCouverture',
    'pointBasTresorerie',
    'datePointBas',
    'soldeProjeteFinMois',
    'statutPointBas',
    'badgePointBas',
    'joursRestantsReleve',
    'statutJoursRestants',
    'badgeJoursRestants',
    'tendanceSolde',
    'statutTendance',
    'badgeTendance',
    'texteTendance',
    'signeTendance',
    'tauxEpargne',
    'statutEpargne',
    'badgeEpargne',
    'budgetQuotidien',
    'statutBudgetJour',
    'badgeBudgetJour',
    'alerteDecouvertDate',
    'dateDecouvertFormat',
    'montantDecouvertAlerte',
    'totalRestantFiltre',
    'totalEcheancesAffichees',
    'totalEcheancesRestantesFiltre',
    'totalPayeFiltre',
    'montantTotalFiltre',
    'montantMoyenAchat',
    'prochaineEcheanceGlobale',
    'prochainMontantGlobal'
  );
}

<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 3. PROCHAIN REVENU 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">💵</span> Prochain revenu

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Affiche le prochain revenu attendu selon les versements planifiés.
          <br><br>
          Calcul : premier versement prévu dont la date est supérieure ou égale à la période
          actuelle.
        </span>
      </span>

    </h5>
    <?php if ($prochainRevenu): ?>
      <span class="badge-neon badge-neon-success fs-5">
        <?= htmlspecialchars(number_format($prochainRevenu['montant_prevu'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?>
        €
      </span>
      <div class="text-muted mt-2 small">
        <strong class="text-white d-block">
          <?= htmlspecialchars($prochainRevenu['titre'], ENT_QUOTES, 'UTF-8') ?>
        </strong>
        Prévu le :
        <?= htmlspecialchars(date('d/m/Y', strtotime($prochainRevenu['date_versement_prevue'])), ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php else: ?>
      <div class="dashboard-empty-state">
        <span class="emoji fs-4">ℹ️</span>
        <p class="text-muted mb-0 small">Aucun revenu prévu</p>
      </div>
    <?php endif; ?>
  </div>
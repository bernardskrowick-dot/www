<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 2. REVENUS À PERCEVOIR
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">⏳</span> Revenus à percevoir

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Montant des revenus attendus mais qui n'ont pas encore été encaissés.
          <br><br>
          Calcul : somme des versements prévus moins les versements déjà reçus.
        </span>
      </span>

    </h5>
    <span class="badge-neon badge-neon-orange fs-5">
      <?= htmlspecialchars(number_format($totalRestantAPercevoir, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?>
      €
    </span>
    <div class="mt-2">
      <small class="text-muted">
        <?= htmlspecialchars((string) $nbVersementsAttendus, ENT_QUOTES, 'UTF-8') ?> versement(s)
        attendu(s)
      </small>
    </div>
  </div>
</div>
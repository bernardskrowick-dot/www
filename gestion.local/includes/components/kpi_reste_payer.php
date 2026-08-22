<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 4. TOTAL DÉPENSES 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">🛒</span> Reste à payer

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Montant restant dû sur les dépenses engagées mais non encore réglées.
          <br><br>
          Calcul : Total des dépenses cumulées moins les montants déjà payés.
        </span>
      </span>

    </h5>
    <span class="badge-neon badge-neon-blue fs-5">
      <?= htmlspecialchars(number_format($resteTotal, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
    </span>
    <small class="text-muted mt-2 d-block">
      <?= htmlspecialchars((string) $nbReste, ENT_QUOTES, 'UTF-8') ?> échéance(s) restante(s)
    </small>
  </div>
</div>
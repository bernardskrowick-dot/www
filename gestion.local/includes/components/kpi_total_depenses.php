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
      <span class="emoji fs-4">🛒</span> Dépenses cumulées

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Montant total des dépenses enregistrées sur la période sélectionnée.
          <br><br>
          Calcul : somme de toutes les échéances liées aux achats pris en compte dans les filtres
          actifs.
        </span>
      </span>

    </h5>
    <span class="badge-neon badge-neon-blue fs-5">
      <?= htmlspecialchars(number_format($totalEcheances, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
    </span>
    <small class="text-muted mt-2 d-block">
      Payé : <?= htmlspecialchars(number_format($totalPaye, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
    </small>
  </div>
</div>
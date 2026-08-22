<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Revenus à percevoir 
 */
?>
<div class="col-md-4">
  <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
    <div>
      <h6 class="form-label">
        <span class="emoji fs-4">⏳</span> Revenus à percevoir

        <!-- Infobulle Revenus à percevoir -->
        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Solde des revenus restant à encaisser pour atteindre le total prévu.
            <br><br>
            Calcul : Montant prévu - Total revenus perçus (avec un minimum à 0 €).
          </span>
        </span>
      </h6>
    </div>
    <div class="my-auto">
      <span class="badge-neon badge-neon-orange fs-5">
        <?= number_format($totalRestant, 2) ?> €
      </span>
    </div>
    <div></div> <!-- Élément vide pour équilibrer l'espace si besoin -->
  </div>
</div>
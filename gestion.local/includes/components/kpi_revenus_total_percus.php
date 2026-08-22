<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Total revenus perçus 
 */
?>
<div class="col-md-4 mb-4 mb-md-0">
  <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
    <div>
      <h6 class="form-label fs-5">
        <span class="emoji fs-4">💰</span> Total revenus perçus

        <!-- Infobulle Total revenus perçus -->
        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Montant total des revenus effectivement encaissés à ce jour.
            <br><br>
            Calcul : somme de tous les versements réellement perçus et validés.
          </span>
        </span>
      </h6>
    </div>

    <div class="my-auto">
      <span class="badge-neon badge-neon-success fs-5">
        <?= number_format($totalRevenusRecu, 2) ?> €
      </span>
    </div>
    <div>
      <small class="text-muted d-block mt-2">
        Revenus prévus : <?= number_format($totalRevenusPrevu, 2) ?> € |
        Versements en attente : <?= $nbVersementsAttendus ?>
      </small>
    </div>
  </div>
</div>
<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 2 : Reste à dépenser (Année)
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav border-danger p-3 card-kpi h-100">
    <small class="fw-bold">
      <span class="text-danger">
        <span class="emoji fs-4">📅</span> ENGAGEMENTS À PAYER (<?= $anneeEnCours ?>)

        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Montant des dépenses et échéances restant à payer jusqu'à la fin de l'année
            sélectionnée.
            <br><br>
            Calcul : somme des échéances non réglées dont la date est comprise entre aujourd'hui
            et le 31/12/<?= $anneeEnCours ?>.
          </span>
        </span>

      </span>
    </small>

    <div class="fs-3 text-white fw-bold mt-2">
      <?= number_format($resteDepenserAnnee, 2, ',', ' ') ?> €
    </div>

    <small class="text-muted mt-1 d-block">
      Échéances prévues d'ici le 31/12/<?= $anneeEnCours ?>
    </small>
  </div>
</div>
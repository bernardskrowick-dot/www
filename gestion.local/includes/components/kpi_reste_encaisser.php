<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 1 : Reste à encaisser (Année)
 */
?>

<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav border-success p-3 card-kpi h-100">
    <small class="fw-bold">
      <span class="text-success">
        <span class="emoji fs-4">📅</span> ENCAISSEMENTS À VENIR (<?= $anneeEnCours ?>)

        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Montant des revenus encore attendus jusqu'à la fin de l'année sélectionnée.
            <br><br>
            Calcul : somme des revenus prévus dont la date d'encaissement est comprise entre
            aujourd'hui et le 31/12/<?= $anneeEnCours ?>.
          </span>
        </span>

      </span>
    </small>

    <div class="fs-3 text-white fw-bold mt-2">
      <?= number_format($resteEncaisserAnnee, 2, ',', ' ') ?> €
    </div>

    <small class="text-muted mt-1 d-block">
      Revenus prévus d'ici le 31/12/<?= $anneeEnCours ?>
    </small>
  </div>
</div>
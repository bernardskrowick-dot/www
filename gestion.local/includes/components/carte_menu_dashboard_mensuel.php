<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 2. CARTE MENU TABLEAU MENSUEL
 */
?>
<div class="col-lg-4 col-md-4">

  <div class="quick-card h-100 d-block">

    <div class="form-label quick-card-body">

      <div class="quick-card-icon">
        📅
      </div>

      <h3 class="quick-card-title">
        Tableau Mensuel

        <span class="info-tooltip ms-1 text-info">
          ⓘ
          <span class="info-tooltip-text">
            Accès à la vue mensuelle basée sur votre période bancaire personnalisée.
            <br><br>
            Contenu : suivi des entrées, sorties, échéances et solde de la période.
          </span>
        </span>

      </h3>

      <p class="quick-card-text">
        Visualiser votre période bancaire et les soldes mensuels.
      </p>

      <a href="<?= htmlspecialchars(BASE_URL) ?>router.php?p=Tableau_mensuel.php"
        class="text-decoration-none">

        <div class="quick-card-footer">
          Accéder →
        </div>

      </a>

    </div>

  </div>

</div>
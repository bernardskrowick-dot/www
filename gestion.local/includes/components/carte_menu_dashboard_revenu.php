<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 1. CARTE TABLEAU DE BORD DES REVENUS 
 */
?>
<div class="col-lg-4 col-md-4">

  <div class="quick-card h-100 d-block">

    <div class="form-label quick-card-body">

      <div class="quick-card-icon">
        💰
      </div>

      <h3 class="quick-card-title">
        Tableau Revenus

        <span class="info-tooltip ms-1 text-info">
          ⓘ
          <span class="info-tooltip-text">
            Accès au tableau de suivi des revenus et des ressources financières.
            <br><br>
            Contenu : ressources, versements, revenus attendus et indicateurs associés.
          </span>
        </span>

      </h3>

      <p class="quick-card-text">
        Consulter les ressources, versements et indicateurs financiers.
      </p>

      <a href="<?= htmlspecialchars(BASE_URL) ?>router.php?p=dashboard_revenus.php&marchand=&statut_filter=a_venir&categorie="
        class="text-decoration-none">

        <div class="quick-card-footer">
          Accéder →
        </div>

      </a>

    </div>

  </div>

</div>
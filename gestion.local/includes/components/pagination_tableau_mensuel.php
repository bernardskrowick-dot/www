<?php

/**
 * Composant pagination_tableau_mensuel.php
 * Variables attendues : 
 * Pagination
 */
if ($totalPagesMois > 1): ?>
  <div class="d-flex justify-content-center pb-3">
    <ul class="pagination pagination-minimal">
      <li class="page-item <?= ($pageCouranteMois <= 1) ? 'disabled' : '' ?>">
        <a class="page-link"
          href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $pageCouranteMois - 1 ?>">
          &lsaquo;
        </a>
      </li>

      <?php for ($i = 1; $i <= $totalPagesMois; $i++): ?>
        <li class="page-item <?= ($i == $pageCouranteMois ? 'active' : '') ?>">
          <a class="page-link"
            href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $i ?>">
            <?= $i ?>
          </a>
        </li>
      <?php endfor; ?>

      <li class="page-item <?= ($pageCouranteMois >= $totalPagesMois) ? 'disabled' : '' ?>">
        <a class="page-link"
          href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $pageCouranteMois + 1 ?>">
          &rsaquo;
        </a>
      </li>
    </ul>
  </div>
<?php endif; ?>
<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 5. RENDU HTML / AFFICHAGE DES CARTES DE RESSOURCES
 */
?>
<?php if (empty($ressources)): ?>
  <div class="col-12 text-center py-5">
    <div class="alert alert-light border shadow-sm">📭 Aucune ressource trouvée.</div>
  </div>
<?php else: ?>
  <?php foreach ($ressourcesAffichees as $ressource): ?>
    <?php
    // Variables d'affichage pour la carte individuelle
    $pourcentageIndividuel = $ressource['pourcentage'];
    $isCompleted = ($pourcentageIndividuel >= 100);
    $resteAPercevoir = $ressource['reste_a_percevoir'];
    $prochainMontant = (float) ($ressource['prochain_montant_prevu'] ?? 0);

    // Détermination du statut et de la classe de bordure
    $statutRessource = 'attendu';
    $classeBordure = 'border-a-venir';
    $classeBadge = 'badge-marchand';

    if ($isCompleted) {
      $statutRessource = 'percu';
      $classeBordure = 'border-solde';
      $classeBadge = 'badge-solde';
    } elseif (!empty($ressource['prochain_versement']) && $ressource['prochain_versement'] < $aujourdhui) {
      $statutRessource = 'en_retard';
      $classeBordure = 'border-en-retard';
      $classeBadge = 'badge-warning-pulse';
    }
    ?>

    <div class="col-md-6 mb-5">
      <div class="glass-card-nav h-100 border-2 <?= $classeBordure ?> card-achat-hover">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div class="text-start">
              <h5 class="text-white mb-3">
                <?= htmlspecialchars($ressource['titre'], ENT_QUOTES, 'UTF-8') ?>
              </h5>

              <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                <!-- Badge Organisme -->
                <span class="badge-dash-pill badge-marchand">
                  🏢 <?= htmlspecialchars($ressource['organisme'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                </span>

                <?php
                // Gestion des badges de catégories (Principale & Sous-catégorie)
                $catColor = !empty($ressource['couleur_categorie']) ? $ressource['couleur_categorie'] : '#ffffff';
                $aUneParente = !empty($ressource['nom_categorie_parente']);
                $nomAffichage = $aUneParente ? $ressource['nom_categorie_parente'] : (!empty($ressource['nom_categorie']) ? $ressource['nom_categorie'] : 'Sans catégorie');
                $ressourceId = (int) $ressource['id'];
                ?>

                <?php
                /** ==========================================================================
                 *  Badge Catégorie Principale
                 *  ========================================================================== */
                ?>
                <span class="badge-dash-pill badge-cat-neon" style="
                                        border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                            background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11;
                                                                            color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                            box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                        ">
                  📁 <?= htmlspecialchars($nomAffichage, ENT_QUOTES, 'UTF-8') ?>
                </span>

                <?php
                /** ==========================================================================
                 *  Badge Sous-Catégorie (si existante)
                 *  ========================================================================== */
                if ($aUneParente): ?>
                  <span class="badge-dash-pill badge-cat-neon" style="
                                                                                border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22;
                                                                                color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                            ">
                    🏷️ <?= htmlspecialchars($ressource['nom_categorie'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>

                <?php
                /** ==========================================================================
                 *  Badge À Classer (si non classé)
                 *  ========================================================================== */
                if (empty($ressource['nom_categorie']) || $nomAffichage === 'Sans catégorie'): ?>
                  <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= $ressourceId ?>" class="text-decoration-none"
                    title="Cliquez pour classer cette ressource">
                    <span class="badge badge-warning-pulse">
                      ⚠️ À CLASSER
                    </span>
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <?php
            /** ==========================================================================
             *  Jauge de progression circulaire
             *  ========================================================================== */
            ?>
            <div class="d-flex flex-column align-items-center" style="min-width:100px;">
              <div class="progress-circle-custom <?= $isCompleted ? 'completed' : '' ?>"
                style="--percentage:<?= $pourcentageIndividuel ?>;">
                <span><?= number_format($pourcentageIndividuel, 0) ?>%</span>
              </div>
              <div class="text-center mt-2">
                <small class="text-muted d-block">Encaissement</small>
                <strong class="text-white"><?= $isCompleted ? '✅ Perçu' : '⏳ En attente' ?></strong>
              </div>
            </div>
          </div>

          <?php
          /** ==========================================================================
           *  Section Montants et Statistiques
           *  ========================================================================== */
          ?>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <span class="badge-marchand">
              💰 Prévu : <?= number_format($ressource['total_prevu'], 2, ',', ' ') ?> €
            </span>
            <span class="badge-solde">
              ✅ Perçu : <?= number_format($ressource['total_percu'], 2, ',', ' ') ?> €
            </span>
            <span class="<?= $classeBadge ?>">
              <?php if ($statutRessource === 'percu'): ?>
                ✅ TOTAL PERÇU
              <?php else: ?>
                ⏳ Reste : <?= number_format($resteAPercevoir, 2, ',', ' ') ?> €
              <?php endif; ?>
            </span>
            <?php if ($statutRessource !== 'percu' && $prochainMontant > 0): ?>
              <span class="badge-marchand">
                💳 Prochaine échéance : <?= number_format($prochainMontant, 2, ',', ' ') ?> €
              </span>
            <?php endif; ?>
          </div>

          <?php
          /** ==========================================================================
           *  Section Versements et Échéances
           *  ========================================================================== */
          ?>
          <div class="mt-3 d-flex flex-wrap gap-2 justify-content-start">
            <span class="badge-marchand">
              📅 Versements : <?= $ressource['nb_restants'] ?> / <?= $ressource['nb_total_versements'] ?>
            </span>
            <?php if (!empty($ressource['prochain_versement'])): ?>
              <span class="badge-warning-pulse">
                ⏳ Prochain : <?= date('d/m/Y', strtotime($ressource['prochain_versement'])) ?>
              </span>
            <?php endif; ?>
          </div>

          <?php
          /** ==========================================================================
           *  Boutons d'actions
           *  ========================================================================== */
          ?>
          <div class="mt-4 d-flex flex-column flex-md-row gap-2 w-100">
            <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= $ressourceId ?>" class="btn-pill-dash w-100"
              style="--btn-color:#ffc107;--btn-glow:rgba(255,193,7,.30);">
              ✏️ Modifier
            </a>

            <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_ressource.php" class="w-100 m-0 p-0">
              <?php csrf_input(); ?>
              <input type="hidden" name="id" value="<?= $ressourceId ?>">
              <button type="submit" class="btn btn-sm btn-danger btn-action-dash w-100"
                onclick="return confirm('Supprimer cette ressource ?');">
                🗑️ Supprimer
              </button>
            </form>

            <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $ressourceId ?>" class="btn-pill-dash w-100"
              style="--btn-color:#00d2ff;--btn-glow:rgba(0,210,255,.30);">
              📅 Versements
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
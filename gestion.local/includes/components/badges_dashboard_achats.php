<?php
/* Version: v1.0.1 - 2026-08-18 */

/**
 * Composant KPI : Revenus cumulés / Badges Achats
 * CORRECTION : Initialisation sécurisée de $messageVide pour éviter le Warning.
 */

// Définition d'un message vide par défaut si la variable n'a pas été transmise
$messageVide = $messageVide ?? "Aucun achat trouvé.";
?>
<?php if (empty($achats)): ?>
  <div class="col-12">
    <div class="card bg-glass p-5 text-center text-muted fs-4"><?= htmlspecialchars($messageVide) ?></div>
  </div>
<?php else: ?>
  <div class="row">
    <?php
    foreach ($achats as $achat):

      /** ==========================================================================
       * // 1. Détermination du statut de l'achat
       * ========================================================================== */

      $resteAPayer = $achat['total_echeances'] - $achat['total_paye'];
      $statutAchat = ($resteAPayer <= 0) ? 'solde' : (($achat['prochaine_echeance'] && $achat['prochaine_echeance'] < $aujourdhui) ? 'en_retard' : 'a_venir');

      $pourcentageIndividuel = ($achat['total_echeances'] > 0) ? ($achat['total_paye'] / $achat['total_echeances']) * 100 : 0;
      $isCompleted = ($pourcentageIndividuel >= 100);
      /** ==========================================================================
       * // 2. Attribution des classes CSS personnalisées
       * ========================================================================== */

      $classeBordure = match ($statutAchat) {
        'solde' => 'border-solde',
        'en_retard' => 'border-en-retard',
        default => 'border-a-venir'
      };

      $classeBadgeReste = match ($statutAchat) {
        'solde' => 'badge-solde',
        'en_retard' => 'badge-en-retard',
        default => 'badge-a-venir'
      };
      /** ==========================================================================
       * // RÈGLE : On saute l'affichage si la card n'est pas sur la page demandée
       * ========================================================================== */

      if (!isset($compteurVisuel)) {
        $compteurVisuel = 0;
      }  // Initialisation si besoin
      $compteurVisuel++;
      if ($compteurVisuel <= (($pageCourante - 1) * $parPage) || $compteurVisuel > ($pageCourante * $parPage))
        continue;

    ?>

      <?php
      /** ==========================================================================
       * // Section Affichage Badges Achats 
       * ========================================================================== */
      ?>

      <div class="col-md-6 mb-4">
        <div class="card bg-glass h-100 border-2 <?= $classeBordure ?> card-achat-hover">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div class="text-start">
                <h5 class="text-white mb-3"><?= htmlspecialchars($achat['titre'], ENT_QUOTES, 'UTF-8') ?></h5>
                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                  <?php
                  /** ==========================================================================
                   * // Badge Marchand
                   * ========================================================================== */
                  ?>

                  <a href="<?= BASE_URL ?>router.php?p=marchands.php&marchand_filter=<?= urlencode($achat['nom_marchand']) ?>"
                    class="badge-dash-pill badge-marchand text-decoration-none"
                    title="Voir les achats chez <?= htmlspecialchars($achat['nom_marchand'], ENT_QUOTES, 'UTF-8') ?>">
                    🏪 <?= htmlspecialchars($achat['nom_marchand'], ENT_QUOTES, 'UTF-8') ?>
                  </a>

                  <?php
                  $catColor = $achat['couleur_categorie'] ?? '#ffffff';
                  $aUneParente = !empty($achat['nom_categorie_parente']);

                  /** ==========================================================================
                   * // Badge Catégorie Principale (ou Sous-catégorie si elle a un parent)
                   * ========================================================================== */
                  ?>

                  <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= (int) $achat['id'] ?>"
                    class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cet achat" style="border-color: <?= $catColor ?>66; 
                                                    background-color: <?= $catColor ?>11; 
                                                    color: <?= $catColor ?>;
                                                    box-shadow: 0 0 8px <?= $catColor ?>33;">
                    📁 <?= htmlspecialchars($aUneParente ? $achat['nom_categorie_parente'] : ($achat['nom_categorie'] ?? 'Sans catégorie'), ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <?php
                  /** ==========================================================================
                   * // Badge Sous-Catégorie (Affiché uniquement si l'achat est rattaché à une sous-catégorie) 
                   * ========================================================================== */
                  if ($aUneParente): ?>
                    <span class="badge-dash-pill text-decoration-none" style="border-color: <?= $catColor ?>66; 
                                                                                background-color: <?= $catColor ?>22; 
                                                                                color: <?= $catColor ?>;
                                                                                box-shadow: 0 0 8px <?= $catColor ?>33;">
                      🏷️ <?= htmlspecialchars($achat['nom_categorie'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif;
                  /** ==========================================================================
                   * // Alerte À Classer si non classé  
                   * ========================================================================== */

                  if ($achat['nom_categorie'] == 'Non classé'): ?>
                    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= (int) $achat['id'] ?>"
                      class="text-decoration-none" title="Cliquez pour classer cet achat">
                      <span class="badge badge-warning-pulse">
                        ⚠️ À CLASSER
                      </span>
                    </a>
                  <?php endif; ?>

                </div>
              </div>

              <div class="d-flex flex-column align-items-center" style="min-width: 100px;">
                <div class="progress-circle-custom <?= $isCompleted ? 'completed' : '' ?>"
                  style="--percentage: <?= $pourcentageIndividuel ?>;">
                  <span><?= number_format($pourcentageIndividuel, 0) ?>%</span>
                </div>
                <div class="text-center mt-2">
                  <small class="text-muted d-block" style="font-size: 0.75rem;">Remboursement</small>
                  <strong class="text-white"
                    style="font-size: 0.85rem;"><?= $isCompleted ? '✅ Soldé' : '⏳ En cours' ?></strong>
                </div>
              </div>
            </div>

            <div class="mt-2 d-flex flex-wrap gap-2">
              <span class="badge-marchand">💰 Total : <?= number_format($achat['total_echeances'], 2) ?>
                €</span>
              <span class="badge-solde">✔️ Payé : <?= number_format($achat['total_paye'], 2) ?> €</span>

              <span class="<?= $classeBadgeReste ?>">
                <?= ($statutAchat === 'solde') ? '✅ SOLDÉ' : '💳 Reste : ' . number_format($resteAPayer, 2) . ' €' ?>
              </span>
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-start">
              <?php
              /** ==========================================================================
               * // Échéances restantes / totales
               * ========================================================================== */
              ?>

              <span class="badge-marchand">📅 Échéances : <?= htmlspecialchars($achat['nb_restantes']) ?>
                / <?= htmlspecialchars($achat['nb_total_echeances']) ?></span>
              <?php
              /** ==========================================================================
               * // Prochaine échéance : Date + Montant unique du mois
               * ========================================================================== */
              ?>

              <?php if (!empty($achat['prochaine_echeance'])): ?>
                <span class="badge-warning-pulse">
                  ⏳ Prochaine :
                  <?= htmlspecialchars(date('d/m/Y', strtotime($achat['prochaine_echeance']))) ?>
                  <?php if (isset($achat['prochain_montant']) && $achat['prochain_montant'] !== null): ?>
                    (<?= htmlspecialchars(number_format((float) $achat['prochain_montant'], 2, ',', ' ')) ?>
                    €)
                  <?php endif; ?>
                </span>
              <?php endif;
              /** ==========================================================================
               * // Date d'achat
               * ========================================================================== */
              if (isset($achat['date_depart']) && $achat['date_depart'] !== '0000-00-00'): ?>
                <span class="badge-marchand">🛒 Date d'achat :
                  <?= htmlspecialchars(date('d/m/Y', strtotime($achat['date_depart']))) ?></span>
              <?php endif;
              /** ==========================================================================
               * // Date de paiement
               * ========================================================================== */
              if (isset($achat['derniere_date_paiement']) && $achat['derniere_date_paiement'] !== null && $achat['derniere_date_paiement'] !== '0000-00-00'): ?>
                <span class="badge-marchand">💳 Date de paiement :
                  <?= htmlspecialchars(date('d/m/Y', strtotime($achat['derniere_date_paiement']))) ?></span>
              <?php endif; ?>
            </div>

            <div class="mt-4 d-flex flex-column flex-md-row gap-2 w-100">
              <a href="<?= BASE_URL ?>router.php?p=modifier_achat.php&id=<?= $achat['id'] ?>"
                class="btn-pill-dash w-100"
                style="--btn-color: #ffc107; --btn-glow: rgba(255, 193, 7, 0.3);">
                <span class="emoji">✏️</span> Modifier
              </a>

              <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_achat.php"
                class="w-100 m-0 p-0">
                <?php csrf_input(); ?>
                <input type="hidden" name="id" value="<?= $achat['id'] ?>">

                <button type="submit" class="btn btn-sm btn-danger btn-action-dash w-100"
                  onclick="return confirm('⚠️ ATTENTION...')">
                  🗑️ Supprimer
                </button>
              </form>

              <a href="<?= BASE_URL ?>router.php?p=echeancier.php&id=<?= $achat['id'] ?>"
                class="btn-pill-dash w-100"
                style="--btn-color: #00d2ff; --btn-glow: rgba(0, 210, 255, 0.3);">
                <span class="emoji">📅</span> Échéancier
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
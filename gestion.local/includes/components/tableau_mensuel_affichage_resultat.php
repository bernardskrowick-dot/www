<?php

/**
 * Fichier : components/tableau_mensuel_affichage_resultat.php
 * RÔLE : Affiche le résultats des periodes mensuelless".
 */

// Variables attendues : $filtreMarchand, $afficherTout, $echeancesParMois
?>

<?php if (count($echeancesParMois) > 0): ?>
    <table class="table table-custom-dark">
        <thead>
            <tr>
                <th class="fw-bold text-center" style="width: 150px; padding: 10px 5px !important;">Mois</th>
                <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Total Prévu</th>
                <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Déjà Payé</th>
                <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Reste à payer
                </th>
                <th class="fw-bold text-center">Revenu du mois</th>
                <th class="fw-bold text-center">Solde</th>
                <th class="fw-bold text-center" style="width: 150px; padding: 10px 5px !important;">Statut actuel
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            $compteurMois = 0;

            foreach ($echeancesParMois as $row):
                $compteurMois++;

                // Pagination
                $debutMois = ($pageCouranteMois - 1) * $parPageMois;
                $finMois = $pageCouranteMois * $parPageMois;
                if ($compteurMois <= $debutMois || $compteurMois > $finMois)
                    continue;

                $restant = $row['total_mois'] - $row['paye_mois'];
                $finDuMois = date('Y-m-t', strtotime($row['mois'] . '-01'));
                $keyMois = trim($row['mois']);

                $revenuMois = (
                    ($revenusParMois[$keyMois]['percu'] ?? 0)
                    + ($revenusParMois[$keyMois]['attendu'] ?? 0)
                );

                // ← Solde pré-calculé sur l'historique complet
                $soldeMois = $soldesCalcules[$keyMois] ?? $soldeInitial;

                if ($restant <= 0) {
                    $statut = '✅ Soldé';
                    $badgeClass = 'badge-solde';
                } elseif ($finDuMois < $aujourdhui && $restant > 0) {
                    $statut = '⚠️ En retard';
                    $badgeClass = 'badge-en-retard';
                } else {
                    $statut = '📅 À venir';
                    $badgeClass = 'badge-a-venir';
                }
                $periode = periodeBancaire($row['mois'], $jourDebutPeriode);
            ?>
                <tr>
                    <td data-label="Mois" class="text-center fw-bold">
                        <a href="<?= BASE_URL ?>router.php?p=echeances_mois.php&mois=<?= $row['mois'] ?><?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
                            class="text-info text-decoration-none">
                            <?= $periode ?>
                        </a>
                    </td>
                    <td data-label="Total Prévu" class="text-center text-white">
                        <?= number_format($row['total_mois'], 2) ?> €
                    </td>
                    <td data-label="Déjà Payé" class="text-center text-success opacity-75">
                        <?= number_format($row['paye_mois'], 2) ?> €
                    </td>
                    <td data-label="Reste" class="text-center <?= $restant > 0 ? 'text-warning' : 'text-white-50' ?>">
                        <?= number_format($restant, 2) ?> €
                    </td>
                    <td data-label="Revenu du mois" class="text-center text-info">
                        <?= number_format($revenuMois, 2) ?> €
                    </td>
                    <td data-label="Solde" class="text-center <?= $soldeMois >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($soldeMois, 2) ?> €
                    </td>
                    <td data-label="Statut" class="text-center">
                        <span class="badge-status <?= $badgeClass ?>">
                            <?= htmlspecialchars($statut) ?>
                            <?php if ($badgeClass === 'badge-en-retard'): ?>
                                <span class="pulse-dot"></span>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="text-center p-5 opacity-50">
        <div class="emoji mb-3" style="font-size: 3rem;">🌙</div>
        <p class="dashboard-subtitle" style="border:0; margin-bottom: 0;">
            Aucune échéance à afficher pour le moment
        </p>
        <small class="text-white-50">Vos futures dépenses apparaîtront ici.</small>
    </div>
<?php endif; ?>
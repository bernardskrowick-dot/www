<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 13. TAUX D'ÉPARGNE
 */
?>
 <div class="col-12 col-md-4">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <h5 class="form-label fs-5">
                    <span class="emoji fs-4">📈</span> Taux d'épargne

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Indique la part des revenus conservée après déduction des dépenses sur la période
                            sélectionnée.
                            <br><br>
                            Calcul : (Revenus - Dépenses) ÷ Revenus × 100.
                        </span>
                    </span>

                </h5>
                <span class="badge-neon badge-neon-<?= htmlspecialchars($statutEpargne, ENT_QUOTES, 'UTF-8'); ?> fs-5">
                    <?= htmlspecialchars(number_format($tauxEpargne, 1, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> %
                </span>
                <div class="text-muted mt-2 small">
                    <strong class="d-block text-white">
                        <?= htmlspecialchars($badgeEpargne, ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                    <span class="d-block text-muted">des revenus conservés sur la période</span>
                </div>
            </div>
        </div>

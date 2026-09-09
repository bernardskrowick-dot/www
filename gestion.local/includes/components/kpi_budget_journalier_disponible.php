<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 14. BUDGET JOURNALIER DISPONIBLE
 */ 
?>
 <div class="col-12 col-md-4">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <h5 class="form-label fs-5">
                    <span class="emoji fs-4">💶</span> Reste à dépenser / jour

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Indique le rythme de dépense quotidien moyen une fois arrivée à la fin de la période (basé
                            sur le solde prévisionnel de fin de période).
                            <br><br>
                            Calcul : Solde prévisionnel ÷ nombre de jours restants.
                        </span>
                    </span>

                </h5>
                <span
                    class="badge-neon badge-neon-<?= htmlspecialchars($statutBudgetJour, ENT_QUOTES, 'UTF-8'); ?> fs-5">
                    <?= htmlspecialchars(number_format($budgetQuotidien, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> € / j
                </span>
                <div class="text-muted mt-2 small">
                    <strong class="d-block text-white">
                        <?= htmlspecialchars($badgeBudgetJour, ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                    <!-- Phrase explicative axée sur l'objectif de fin de période -->
                    <span class="d-block text-muted">rythme cible par jour pour la fin de période (dans
                        <?= htmlspecialchars((string) $joursRestantsReleve, ENT_QUOTES, 'UTF-8'); ?> jours)
                    </span>
                </div>
            </div>
        </div>
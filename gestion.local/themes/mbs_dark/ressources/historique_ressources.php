<?php

/**
 * ============================================================================
 * Page : Historique des Ressources
 * Version : v1.19.1 (Rev #25)
 * Date : 2026-08-06
 * ============================================================================
 *
 * Fonctionnement général :
 * - Vérification de l'authentification utilisateur
 * - Lecture et validation des filtres GET
 * - Calcul des KPI financiers globaux
 * - Calcul des KPI liés aux filtres actifs
 * - Comptage des ressources pour la pagination
 * - Récupération de la liste paginée des ressources
 * - Récupération des années disponibles
 * - Préparation des données pour la vue annuelle
 *
 * Principes de sécurité :
 * - Accès réservé aux utilisateurs authentifiés
 * - Requêtes SQL préparées avec PDO
 * - Validation stricte du filtre de statut
 * - Paramètres utilisateur transmis via des placeholders SQL
 *
 * Principes métier :
 * - Les KPI financiers globaux restent indépendants du filtre de statut.
 * - Les filtres s'appliquent à la liste et aux KPI filtrés.
 * - Le statut "en_retard" correspond à un versement attendu dont
 *   la date prévue est antérieure à la date du jour.
 * ============================================================================
 */


/* ============================================================================
 * 0. SÉCURITÉ — Vérification de la session utilisateur
 * ============================================================================
 *
 * Cette page ne doit être accessible qu'à un utilisateur authentifié.
 *
 * Si aucun identifiant utilisateur n'est présent en session :
 * - redirection vers la page de connexion ;
 * - arrêt immédiat de l'exécution.
 *
 * L'identifiant utilisateur issu de la session est ensuite utilisé dans
 * toutes les requêtes afin d'empêcher l'accès aux ressources d'un autre
 * utilisateur.
 * ========================================================================== */

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * Date courante au format SQL.
 * Utilisée notamment pour déterminer les versements en retard.
 */
$aujourdhui = date('Y-m-d');


/* ============================================================================
 * 1. PAGINATION
 * ============================================================================
 *
 * Nombre de ressources affichées par page.
 *
 * Exemple :
 * - page 1 => offset 0
 * - page 2 => offset 10
 * - page 3 => offset 20
 *
 * Le cast en entier sur $_GET['page'] évite de manipuler directement
 * une valeur arbitraire provenant de l'URL.
 * ========================================================================== */

$ressourcesParPage = 10;

$pageCourante = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

$offset = ($pageCourante - 1) * $ressourcesParPage;


/* ============================================================================
 * 2. RÉCUPÉRATION ET VALIDATION DES FILTRES
 * ============================================================================
 *
 * Les filtres proviennent de la chaîne de requête GET.
 *
 * Ils sont ensuite utilisés uniquement comme paramètres PDO dans les
 * requêtes SQL.
 * ========================================================================== */

$search = $_GET['search'] ?? '';

$categorieFilter = $_GET['categorie_filter'] ?? '';

/**
 * Le filtre de date est accepté uniquement s'il respecte le format :
 * YYYY-MM-DD
 *
 * Toute valeur invalide est transformée en NULL.
 */
$dateFilter = (
    !empty($_GET['date_filter'])
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_filter'])
)
    ? $_GET['date_filter']
    : null;

$anneeFilter = $_GET['annee_filter'] ?? '';


/* ============================================================================
 * 2 BIS. FILTRE DE STATUT — LISTE BLANCHE
 * ============================================================================
 *
 * Seuls les statuts explicitement autorisés peuvent être utilisés :
 *
 * - attendu
 * - percu
 * - en_retard
 * - chaîne vide = aucun filtre de statut
 *
 * Toute autre valeur reçue depuis l'URL est ignorée.
 *
 * Cette validation permet également de contrôler précisément les conditions
 * SQL qui seront ajoutées dynamiquement plus bas.
 * ========================================================================== */

$statutsAutorises = [
    '',
    'attendu',
    'percu',
    'en_retard'
];

$filtreStatut = $_GET['statut_filter'] ?? '';

if (
    !is_string($filtreStatut)
    || !in_array($filtreStatut, $statutsAutorises, true)
) {
    $filtreStatut = '';
}


/* ============================================================================
 * 2 TER. CONSTRUCTION DE LA CONDITION SQL DU STATUT
 * ============================================================================
 *
 * La condition SQL est construite uniquement à partir des valeurs validées
 * par la liste blanche ci-dessus.
 *
 * Aucun morceau de SQL provenant directement de $_GET n'est injecté.
 *
 * Le cas "en_retard" est particulier :
 * il correspond à un versement encore "attendu" dont la date prévue
 * est antérieure à aujourd'hui.
 * ========================================================================== */

$conditionStatut = '';
$paramsStatut = [];

switch ($filtreStatut) {

    case 'attendu':

        $conditionStatut = "
            AND v.statut = :statut_attendu
        ";

        $paramsStatut['statut_attendu'] = 'attendu';

        break;


    case 'percu':

        $conditionStatut = "
            AND v.statut = :statut_percu
        ";

        $paramsStatut['statut_percu'] = 'percu';

        break;


    case 'en_retard':

        $conditionStatut = "
            AND v.statut = :statut_retard
            AND v.date_versement_prevue < :date_aujourdhui
        ";

        $paramsStatut['statut_retard'] = 'attendu';
        $paramsStatut['date_aujourdhui'] = $aujourdhui;

        break;
}


/* ============================================================================
 * 3. KPI FINANCIERS GLOBAUX
 * ============================================================================
 *
 * IMPORTANT :
 *
 * Ces KPI représentent la situation financière globale du périmètre
 * sélectionné par les filtres de recherche, catégorie, date et année.
 *
 * Le filtre de STATUT n'est volontairement PAS appliqué ici.
 *
 * Cela permet de conserver des indicateurs cohérents :
 *
 *   Total prévu  = ensemble des montants prévus
 *   Total perçu  = ensemble des montants réellement encaissés
 *   Total attendu = Total prévu - Total perçu
 *
 * Exemple :
 * si l'utilisateur sélectionne "Perçu", le KPI "Total prévu" ne doit
 * pas devenir uniquement la somme des lignes déjà perçues.
 *
 * Le filtre de statut est donc réservé à la liste et aux KPI filtrés.
 * ========================================================================== */

$stmtTotal = $pdo->prepare("
    SELECT
        SUM(v.montant_prevu) AS total_prevu,

        SUM(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ) AS total_percu

    FROM ressources r

    LEFT JOIN categories_ressources c
        ON c.id = r.categorie_id

    LEFT JOIN categories_ressources parent_cat
        ON c.parent_id = parent_cat.id

    LEFT JOIN versements v
        ON v.ressource_id = r.id

    WHERE r.user_id = :user_id

      AND (
          :search = ''
          OR r.titre LIKE :search
      )

      AND (
          :categorie_filter = ''
          OR r.categorie_id = :categorie_filter
          OR r.categorie_id IN (
              SELECT id
              FROM categories_ressources
              WHERE parent_id = :categorie_filter
          )
      )

      AND (
          :date_filter IS NULL
          OR v.date_versement_prevue >= :date_filter
          OR v.date_perception >= :date_filter
      )

      AND (
          :annee_filter = ''
          OR YEAR(v.date_versement_prevue) = :annee_filter
          OR YEAR(v.date_perception) = :annee_filter
      )
");

$stmtTotal->bindValue(
    ':user_id',
    $user_id,
    PDO::PARAM_INT
);

$stmtTotal->bindValue(
    ':search',
    '%' . $search . '%',
    PDO::PARAM_STR
);

$stmtTotal->bindValue(
    ':categorie_filter',
    $categorieFilter,
    PDO::PARAM_STR
);

$stmtTotal->bindValue(
    ':date_filter',
    $dateFilter,
    $dateFilter === null
        ? PDO::PARAM_NULL
        : PDO::PARAM_STR
);

$stmtTotal->bindValue(
    ':annee_filter',
    $anneeFilter,
    PDO::PARAM_STR
);

$stmtTotal->execute();

$resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);


/**
 * Valeurs globales utilisées par les KPI principaux.
 */
$totalPrevu = $resultTotal['total_prevu'] ?? 0;

$totalPercu = $resultTotal['total_percu'] ?? 0;


/**
 * Montant restant à percevoir.
 *
 * Formule :
 * Total prévu - Total perçu
 */
$resteGlobal = $totalPrevu - $totalPercu;


/**
 * Pourcentage global de perception.
 *
 * Évite une division par zéro lorsqu'aucun montant n'est prévu.
 */
$pourcentageGlobal = ($totalPrevu > 0)
    ? ($totalPercu / $totalPrevu) * 100
    : 0;


/* ============================================================================
 * 3 BIS. KPI LIÉS AUX FILTRES ACTIFS
 * ============================================================================
 *
 * Cette requête est séparée des KPI globaux.
 *
 * Elle permet de mesurer précisément le résultat du périmètre actuellement
 * filtré par l'utilisateur.
 *
 * KPI calculés :
 *
 * - nombre de ressources correspondant aux filtres ;
 * - montant prévu correspondant aux filtres ;
 * - montant perçu correspondant aux filtres ;
 * - montant attendu correspondant aux filtres.
 *
 * Le filtre de statut est appliqué ici.
 * ========================================================================== */

$filtreKpiStatut = $filtreStatut;


/* ============================================================================
 * 3 TER. CONDITION DE STATUT POUR LES KPI FILTRÉS
 * ============================================================================
 *
 * On utilise des paramètres différents de ceux de $stmtTotal, $stmtCount
 * et $stmt afin d'éviter toute ambiguïté dans les requêtes préparées.
 * ========================================================================== */

$conditionKpiStatut = '';
$paramsKpiStatut = [];

switch ($filtreKpiStatut) {

    case 'attendu':

        $conditionKpiStatut = "
            AND v.statut = :kpi_statut_attendu
        ";

        $paramsKpiStatut[':kpi_statut_attendu'] = 'attendu';

        break;


    case 'percu':

        $conditionKpiStatut = "
            AND v.statut = :kpi_statut_percu
        ";

        $paramsKpiStatut[':kpi_statut_percu'] = 'percu';

        break;


    case 'en_retard':

        $conditionKpiStatut = "
            AND v.statut = :kpi_statut_retard
            AND v.date_versement_prevue < :kpi_date_aujourdhui
            AND v.date_versement_prevue IS NOT NULL
        ";

        $paramsKpiStatut[':kpi_statut_retard'] = 'attendu';
        $paramsKpiStatut[':kpi_date_aujourdhui'] = $aujourdhui;

        break;
}


/* ============================================================================
 * 3 QUATER. REQUÊTE DES KPI FILTRÉS
 * ============================================================================
 *
 * COUNT(DISTINCT r.id)
 * --------------------
 * Compte chaque ressource une seule fois même si elle possède plusieurs
 * versements.
 *
 * montant_prevu_filtre
 * --------------------
 * Somme des montants prévus correspondant au périmètre filtré.
 *
 * montant_percu_filtre
 * --------------------
 * Somme des montants effectivement encaissés.
 *
 * montant_attendu_filtre
 * ----------------------
 * Somme des montants encore attendus.
 * ========================================================================== */

$stmtKpiFiltre = $pdo->prepare("
    SELECT

        COUNT(DISTINCT r.id) AS ressources_filtrees,

        COALESCE(
            SUM(v.montant_prevu),
            0
        ) AS montant_prevu_filtre,

        COALESCE(
            SUM(
                CASE
                    WHEN v.statut = 'percu'
                    THEN COALESCE(
                        v.montant_reel,
                        v.montant_prevu
                    )
                    ELSE 0
                END
            ),
            0
        ) AS montant_percu_filtre,

        COALESCE(
            SUM(
                CASE
                    WHEN v.statut = 'attendu'
                    THEN v.montant_prevu
                    ELSE 0
                END
            ),
            0
        ) AS montant_attendu_filtre

    FROM ressources r

    LEFT JOIN versements v
        ON v.ressource_id = r.id

    WHERE r.user_id = :kpi_user_id

      AND (
          :kpi_search = ''
          OR r.titre LIKE :kpi_search_like
      )

      AND (
          :kpi_categorie_filter = ''
          OR r.categorie_id = :kpi_categorie_filter
          OR r.categorie_id IN (
              SELECT id
              FROM categories_ressources
              WHERE parent_id = :kpi_categorie_parent
          )
      )

      AND (
          :kpi_date_filter IS NULL
          OR v.date_versement_prevue >= :kpi_date_filter_date
          OR v.date_perception >= :kpi_date_filter_date
      )

      AND (
          :kpi_annee_filter = ''
          OR YEAR(v.date_versement_prevue) = :kpi_annee_filter_prevue
          OR YEAR(v.date_perception) = :kpi_annee_filter_perception
      )

      $conditionKpiStatut
");


/* ============================================================================
 * 3 QUINQUIES. PARAMÈTRES DE LA REQUÊTE KPI
 * ========================================================================== */

$searchLikeKpi = '%' . $search . '%';

$stmtKpiFiltre->bindValue(
    ':kpi_user_id',
    $user_id,
    PDO::PARAM_INT
);

$stmtKpiFiltre->bindValue(
    ':kpi_search',
    $search,
    PDO::PARAM_STR
);

$stmtKpiFiltre->bindValue(
    ':kpi_search_like',
    $searchLikeKpi,
    PDO::PARAM_STR
);

$stmtKpiFiltre->bindValue(
    ':kpi_categorie_filter',
    $categorieFilter,
    PDO::PARAM_STR
);

$stmtKpiFiltre->bindValue(
    ':kpi_categorie_parent',
    $categorieFilter,
    PDO::PARAM_STR
);


/* ============================================================================
 * 3 SEXIES. PARAMÈTRES DE DATE
 * ============================================================================
 *
 * Deux paramètres distincts sont utilisés afin de rester compatible avec
 * les configurations PDO où la réutilisation d'un même placeholder peut
 * poser problème.
 * ========================================================================== */

if ($dateFilter === null) {

    $stmtKpiFiltre->bindValue(
        ':kpi_date_filter',
        null,
        PDO::PARAM_NULL
    );

    $stmtKpiFiltre->bindValue(
        ':kpi_date_filter_date',
        null,
        PDO::PARAM_NULL
    );
} else {

    $stmtKpiFiltre->bindValue(
        ':kpi_date_filter',
        $dateFilter,
        PDO::PARAM_STR
    );

    $stmtKpiFiltre->bindValue(
        ':kpi_date_filter_date',
        $dateFilter,
        PDO::PARAM_STR
    );
}


/* ============================================================================
 * 3 SEPTIES. PARAMÈTRES DE L'ANNÉE
 * ========================================================================== */

$stmtKpiFiltre->bindValue(
    ':kpi_annee_filter',
    $anneeFilter,
    PDO::PARAM_STR
);

$stmtKpiFiltre->bindValue(
    ':kpi_annee_filter_prevue',
    $anneeFilter,
    PDO::PARAM_STR
);

$stmtKpiFiltre->bindValue(
    ':kpi_annee_filter_perception',
    $anneeFilter,
    PDO::PARAM_STR
);


/* ============================================================================
 * 3 OCTIES. PARAMÈTRES DU STATUT
 * ========================================================================== */

foreach ($paramsKpiStatut as $param => $value) {

    $stmtKpiFiltre->bindValue(
        $param,
        $value,
        PDO::PARAM_STR
    );
}


/* ============================================================================
 * 3 NOVIES. EXÉCUTION DES KPI FILTRÉS
 * ========================================================================== */

$stmtKpiFiltre->execute();

$resultKpiFiltre = $stmtKpiFiltre->fetch(PDO::FETCH_ASSOC);


/* ============================================================================
 * 3 DECIES. NORMALISATION DES RÉSULTATS KPI
 * ============================================================================
 *
 * Les résultats SQL sont convertis explicitement dans les types attendus
 * par PHP avant leur utilisation dans l'affichage.
 * ========================================================================== */

$ressourcesFiltrees = (int) (
    $resultKpiFiltre['ressources_filtrees'] ?? 0
);

$montantPrevuFiltre = (float) (
    $resultKpiFiltre['montant_prevu_filtre'] ?? 0
);

$montantPercuFiltre = (float) (
    $resultKpiFiltre['montant_percu_filtre'] ?? 0
);

$montantAttenduFiltre = (float) (
    $resultKpiFiltre['montant_attendu_filtre'] ?? 0
);


/**
 * Reste correspondant uniquement au périmètre filtré.
 */
$resteFiltre = $montantPrevuFiltre - $montantPercuFiltre;


/* ============================================================================
 * 3 UNDECIES. KPI DYNAMIQUE SELON LE STATUT SÉLECTIONNÉ
 * ============================================================================
 *
 * Le troisième KPI filtré adapte son intitulé et sa valeur au statut choisi.
 *
 * - attendu    => montant attendu
 * - percu      => montant perçu
 * - en_retard  => montant prévu des versements en retard
 * - aucun filtre => montant prévu du périmètre filtré
 * ========================================================================== */

switch ($filtreKpiStatut) {

    case 'attendu':
        $libelleKpiStatut = 'Montant attendu filtré';
        $iconeKpiStatut = '⏳';
        $valeurKpiStatut = $montantAttenduFiltre;
        break;

    case 'percu':
        $libelleKpiStatut = 'Montant perçu filtré';
        $iconeKpiStatut = '✅';
        $valeurKpiStatut = $montantPercuFiltre;
        break;

    case 'en_retard':
        $libelleKpiStatut = 'Montant en retard filtré';
        $iconeKpiStatut = '⚠️';
        $valeurKpiStatut = $montantPrevuFiltre;
        break;

    default:
        $libelleKpiStatut = 'Montant filtré';
        $iconeKpiStatut = '📌';
        $valeurKpiStatut = $montantPrevuFiltre;
        break;
}


/* ============================================================================
 * 4. COMPTAGE DES RESSOURCES POUR LA PAGINATION
 * ============================================================================
 *
 * Cette requête ne récupère pas les ressources.
 * Elle détermine uniquement combien de ressources correspondent aux filtres.
 *
 * Le filtre de statut EST appliqué ici afin que le nombre de pages corresponde
 * exactement à la liste affichée.
 * ========================================================================== */

$stmtCount = $pdo->prepare("
    SELECT COUNT(DISTINCT r.id) AS total

    FROM ressources r

    LEFT JOIN versements v
        ON v.ressource_id = r.id

    WHERE r.user_id = :user_id

      AND (
          :search = ''
          OR r.titre LIKE :search
      )

      AND (
          :categorie_filter = ''
          OR r.categorie_id = :categorie_filter
          OR r.categorie_id IN (
              SELECT id
              FROM categories_ressources
              WHERE parent_id = :categorie_filter
          )
      )

      AND (
          :date_filter IS NULL
          OR v.date_versement_prevue >= :date_filter
          OR v.date_perception >= :date_filter
      )

      AND (
          :annee_filter = ''
          OR YEAR(v.date_versement_prevue) = :annee_filter
          OR YEAR(v.date_perception) = :annee_filter
      )

      $conditionStatut
");


/* ============================================================================
 * 4 BIS. PARAMÈTRES DU COMPTAGE
 * ========================================================================== */

$stmtCount->bindValue(
    ':user_id',
    $user_id,
    PDO::PARAM_INT
);

$stmtCount->bindValue(
    ':search',
    '%' . $search . '%',
    PDO::PARAM_STR
);

$stmtCount->bindValue(
    ':categorie_filter',
    $categorieFilter,
    PDO::PARAM_STR
);

$stmtCount->bindValue(
    ':date_filter',
    $dateFilter,
    $dateFilter === null
        ? PDO::PARAM_NULL
        : PDO::PARAM_STR
);

$stmtCount->bindValue(
    ':annee_filter',
    $anneeFilter,
    PDO::PARAM_STR
);

foreach ($paramsStatut as $param => $valeur) {

    $stmtCount->bindValue(
        ':' . $param,
        $valeur,
        PDO::PARAM_STR
    );
}

$stmtCount->execute();

$totalRessources = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;


/**
 * Nombre total de pages nécessaires.
 */
$totalPages = ceil(
    $totalRessources / $ressourcesParPage
);


/* ============================================================================
 * 5. RÉCUPÉRATION DE LA LISTE DES RESSOURCES
 * ============================================================================
 *
 * Cette requête récupère uniquement les ressources correspondant aux filtres
 * et à la page courante.
 *
 * Elle récupère également :
 *
 * - catégorie ;
 * - catégorie parente ;
 * - couleur de catégorie ;
 * - total prévu ;
 * - total perçu ;
 * - dernière date de perception ;
 * - prochaine date de versement attendu.
 *
 * Le filtre de statut EST appliqué ici.
 * ========================================================================== */

$stmt = $pdo->prepare("
    SELECT
        r.*,

        cr.nom AS categorie_nom,
        cr.parent_id AS categorie_parent_id,
        parent_cat.nom AS categorie_parente_nom,
        cr.couleur AS categorie_couleur,

        SUM(v.montant_prevu) AS total_prevu,

        SUM(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(
                    v.montant_reel,
                    v.montant_prevu
                )
                ELSE 0
            END
        ) AS total_percu,

        MAX(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(
                    v.date_perception,
                    v.date_versement_prevue
                )
            END
        ) AS derniere_date_percue,

        MIN(
            CASE
                WHEN v.statut = 'attendu'
                THEN v.date_versement_prevue
            END
        ) AS prochaine_versement

    FROM ressources r

    LEFT JOIN versements v
        ON v.ressource_id = r.id

    LEFT JOIN categories_ressources cr
        ON cr.id = r.categorie_id

    LEFT JOIN categories_ressources parent_cat
        ON cr.parent_id = parent_cat.id

    WHERE r.user_id = :user_id

      AND (
          :search = ''
          OR r.titre LIKE :search
      )

      AND (
          :categorie_filter = ''
          OR r.categorie_id = :categorie_filter
          OR r.categorie_id IN (
              SELECT id
              FROM categories_ressources
              WHERE parent_id = :categorie_filter
          )
      )

      AND (
          :date_filter IS NULL
          OR v.date_versement_prevue >= :date_filter
          OR v.date_perception >= :date_filter
      )

      AND (
          :annee_filter = ''
          OR YEAR(v.date_versement_prevue) = :annee_filter
          OR YEAR(v.date_perception) = :annee_filter
      )

      $conditionStatut

    GROUP BY
        r.id,
        cr.nom,
        cr.parent_id,
        parent_cat.nom,
        cr.couleur

    ORDER BY
        COALESCE(
            MAX(
                CASE
                    WHEN v.statut = 'percu'
                    THEN COALESCE(
                        v.date_perception,
                        v.date_versement_prevue
                    )
                END
            ),
            MIN(
                CASE
                    WHEN v.statut = 'attendu'
                    THEN v.date_versement_prevue
                END
            ),
            r.date_depart
        ) DESC

    LIMIT :limit
    OFFSET :offset
");


/* ============================================================================
 * 5 BIS. PARAMÈTRES DE LA LISTE
 * ========================================================================== */

$stmt->bindValue(
    ':user_id',
    $user_id,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':search',
    '%' . $search . '%',
    PDO::PARAM_STR
);

$stmt->bindValue(
    ':categorie_filter',
    $categorieFilter,
    PDO::PARAM_STR
);

$stmt->bindValue(
    ':date_filter',
    $dateFilter,
    $dateFilter === null
        ? PDO::PARAM_NULL
        : PDO::PARAM_STR
);

$stmt->bindValue(
    ':annee_filter',
    $anneeFilter,
    PDO::PARAM_STR
);


/**
 * LIMIT et OFFSET sont transmis comme entiers.
 */
$stmt->bindValue(
    ':limit',
    $ressourcesParPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);


/**
 * Ajout des paramètres liés au statut sélectionné.
 */
foreach ($paramsStatut as $param => $valeur) {

    $stmt->bindValue(
        ':' . $param,
        $valeur,
        PDO::PARAM_STR
    );
}

$stmt->execute();

$ressourcesPage = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================================
 * 6. ANNÉES DISPONIBLES POUR LE FILTRE
 * ============================================================================
 *
 * On récupère toutes les années dans lesquelles l'utilisateur possède
 * au moins un versement avec une date exploitable.
 *
 * Les années sont triées de la plus récente à la plus ancienne.
 * ========================================================================== */

$stmtAnnees = $pdo->prepare("
    SELECT DISTINCT
        YEAR(
            COALESCE(
                v.date_perception,
                v.date_versement_prevue
            )
        ) AS annee

    FROM versements v

    INNER JOIN ressources r
        ON r.id = v.ressource_id

    WHERE r.user_id = :uid

      AND (
          v.date_perception IS NOT NULL
          OR v.date_versement_prevue IS NOT NULL
      )

    ORDER BY annee DESC
");

$stmtAnnees->execute([
    'uid' => $user_id
]);

$anneesDisponibles = $stmtAnnees->fetchAll(
    PDO::FETCH_COLUMN
);


/* ============================================================================
 * 7. VUE ANNUELLE DES RESSOURCES
 * ============================================================================
 *
 * Cette partie prépare les données nécessaires au graphique ou tableau
 * annuel.
 *
 * L'année affichée par défaut est l'année courante.
 *
 * Les données sont regroupées par mois :
 *
 * - montant prévu ;
 * - montant effectivement perçu.
 * ========================================================================== */

$anneeVue = date('Y');

$stmtAnnee = $pdo->prepare("
    SELECT

        MONTH(v.date_versement_prevue) AS mois,

        SUM(v.montant_prevu) AS total_prevu,

        SUM(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(
                    v.montant_reel,
                    v.montant_prevu
                )
                ELSE 0
            END
        ) AS total_percu

    FROM versements v

    INNER JOIN ressources r
        ON r.id = v.ressource_id

    WHERE r.user_id = :user_id

      AND YEAR(v.date_versement_prevue) = :annee

    GROUP BY MONTH(v.date_versement_prevue)
");

$stmtAnnee->execute([
    ':user_id' => $user_id,
    ':annee'   => $anneeVue
]);


/* ============================================================================
 * 7 BIS. TRANSFORMATION DES DONNÉES ANNUELLES
 * ============================================================================
 *
 * Les résultats SQL sont indexés directement par numéro de mois.
 *
 * Exemple :
 *
 * $donneesAnnuelles[1]  => janvier
 * $donneesAnnuelles[2]  => février
 * ...
 * $donneesAnnuelles[12] => décembre
 *
 * Cela facilite ensuite la construction du graphique ou du tableau annuel.
 * ========================================================================== */

$donneesAnnuelles = [];

foreach (
    $stmtAnnee->fetchAll(PDO::FETCH_ASSOC)
    as $ligne
) {
    $donneesAnnuelles[(int) $ligne['mois']] = $ligne;
}

?>

<div class="container mb-5 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-4">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>

        <hr class="hr-glass" style="opacity: 0.6;">

        <div class="text-left mt-0 mb-0">
            <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm mt-0">
                <span class="emoji fs-4">💰</span> Retour au Tableau de bord Revenus
            </a>
        </div>

        <hr class="hr-glass" style="opacity: 0.6;">

        <div class="row g-3 mb-4 mt-2">

            <!-- KPI : Total prévu -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav  border-accent-blue p-3 text-center card-kpi">

                    <small class="text-accent-blue fw-bold uppercase-tracking">
                        <span class="emoji fs-4">💰</span> Total prévu

                        <!-- Infobulle -->
                        <span class="info-tooltip ms-1 text-info fs-5">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant total des revenus prévus sur la période sélectionnée.
                                <br><br>
                                Il s'agit de la somme de tous les revenus attendus,
                                qu'ils aient été perçus ou non.
                            </span>
                        </span>
                    </small>

                    <div class="fs-3 fw-800 text-white mt-2">
                        <?= number_format($totalPrevu, 2, ',', ' ') ?> €
                    </div>

                </div>
            </div>


            <!-- KPI : Total perçu -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav  border-success p-3 text-center card-kpi">

                    <small class="text-success fw-bold uppercase-tracking">
                        <span class="emoji fs-4">✅</span> Total perçu

                        <!-- Infobulle -->
                        <span class="info-tooltip ms-1 text-info fs-5">
                            ⓘ
                            <span class="info-tooltip-text">
                                Somme de tous les revenus effectivement encaissés
                                sur la période sélectionnée.
                            </span>
                        </span>
                    </small>

                    <div class="fs-3 fw-800 text-white mt-2">
                        <?= number_format($totalPercu, 2, ',', ' ') ?> €
                    </div>

                </div>
            </div>


            <!-- KPI : Reste attendu -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav  <?= ($resteGlobal > 0) ? 'border-danger' : 'border-secondary' ?> p-3 text-center card-kpi">

                    <small class="<?= ($resteGlobal > 0) ? 'text-danger' : 'text-muted' ?> fw-bold uppercase-tracking">
                        <span class="emoji fs-4">💵</span> Reste attendu

                        <!-- Infobulle -->
                        <span class="info-tooltip ms-1 text-info fs-5">
                            ⓘ
                            <span class="info-tooltip-text">
                                Différence entre le montant total prévu et le montant déjà perçu.
                                <br><br>
                                Calcul :
                                <strong>Total prévu − Total perçu</strong>.
                            </span>
                        </span>
                    </small>

                    <div class="fs-3 fw-800 text-white mt-2">
                        <?= number_format($resteGlobal, 2, ',', ' ') ?> €
                    </div>

                </div>
            </div>
            <!-- KPI : nombre de ressources correspondant aux filtres -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-accent-purple p-3 text-center card-kpi">

                    <small class="text-accent-purple fw-bold uppercase-tracking">

                        <span class="emoji fs-4">🔎</span> Ressources filtrées

                        <!-- Infobulle -->
                        <span class="info-tooltip ms-1 text-info fs-5">
                            ⓘ

                            <span class="info-tooltip-text">
                                Nombre de ressources correspondant aux critères de filtrage sélectionnés.
                                <br><br>
                                Le compteur évolue automatiquement selon la recherche,
                                la catégorie, l'année, la date et le statut.
                            </span>
                        </span>

                    </small>

                    <div class="fs-3 fw-800 text-white mt-2">
                        <?= number_format($ressourcesFiltrees, 0, ',', ' ') ?>
                    </div>

                </div>
            </div>


            <!-- KPI : montant correspondant au statut filtré -->
           <div class="col-md-4">
    <div class="form-label glass-card-nav border-accent-orange p-3 text-center card-kpi">

        <small class="text-accent-orange fw-bold uppercase-tracking">

            <span class="emoji fs-4"><?= $iconeKpiStatut ?></span>
            <?= htmlspecialchars($libelleKpiStatut, ENT_QUOTES, 'UTF-8') ?>

            <!-- Infobulle -->
            <span class="info-tooltip ms-1 text-info fs-5">
                ⓘ

                <span class="info-tooltip-text">

                    <?php if ($filtreKpiStatut === 'attendu'): ?>

                        Montant total des revenus attendus correspondant
                        aux critères de filtrage sélectionnés.

                    <?php elseif ($filtreKpiStatut === 'percu'): ?>

                        Montant total des revenus effectivement perçus
                        correspondant aux critères de filtrage sélectionnés.

                    <?php elseif ($filtreKpiStatut === 'en_retard'): ?>

                        Montant total des revenus attendus dont la date
                        de versement est dépassée, selon les critères
                        de filtrage sélectionnés.

                    <?php else: ?>

                        Montant total correspondant aux critères de
                        filtrage sélectionnés.

                    <?php endif; ?>

                </span>
            </span>

        </small>

        <div class="fs-3 fw-800 text-white mt-2">
            <?= number_format($valeurKpiStatut, 2, ',', ' ') ?> €
        </div>

    </div>
</div>

        </div>

        <!-- 📊 VUE ANNUELLE DES RESSOURCES -->
        <div class="glass-card-nav  p-4 mb-4 border-0">
            <h5 class="text-white mb-4">
                📅 Vue annuelle <?= $anneeVue ?>
            </h5>

            <div class="row g-3">
                <?php
                $moisNoms = [
                    1 => 'Janvier',
                    2 => 'Février',
                    3 => 'Mars',
                    4 => 'Avril',
                    5 => 'Mai',
                    6 => 'Juin',
                    7 => 'Juillet',
                    8 => 'Août',
                    9 => 'Septembre',
                    10 => 'Octobre',
                    11 => 'Novembre',
                    12 => 'Décembre'
                ];

                foreach ($moisNoms as $num => $nom):
                    $prevu = $donneesAnnuelles[$num]['total_prevu'] ?? 0;
                    $percu = $donneesAnnuelles[$num]['total_percu'] ?? 0;
                    $pourcentageMois = ($prevu > 0) ? round(($percu / $prevu) * 100) : 0;
                ?>

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-3"><?= $nom ?></h6>
                                <small class="text-muted d-block">Prévu</small>
                                <div class="text-white fw-bold mb-2"><?= number_format($prevu, 2, ',', ' ') ?> €</div>
                                <small class="text-muted d-block">Perçu</small>
                                <div class="text-success fw-bold mb-3"><?= number_format($percu, 2, ',', ' ') ?> €</div>
                                <div class="progress progress-custom-dark">
                                    <div class="progress-bar progress-bar-neon bg-neon-amber"
                                        style="width: <?= $pourcentageMois ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $pourcentageMois ?>%</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtres -->
        <form method="GET" action="router.php" class="glass-card-nav  p-3 mb-4 border-0">
            <input type="hidden" name="p" value="historique_ressources.php">
            <div class="row g-3 align-items-center justify-content-center ">
                <div class="col-md-3">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">🔍</span> Rechercher</label>
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary shadow-sm"
                        value="<?= htmlspecialchars($search) ?>" placeholder="Titre...">
                </div>

                <!-- EXEMPLE APPLIQUÉ : Filtre de catégorie hiérarchique -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span> CATÉGORIE</label>
                    <select name="categorie_filter" class="form-select bg-dark text-white border-secondary w-100">
                        <option value="">Toutes les catégories</option>
                        <?php
                        $catStmt = $pdo->prepare('
                        SELECT id, nom, parent_id
                        FROM categories_ressources
                        WHERE user_id = ?
                        ORDER BY 
                            COALESCE(parent_id, id),
                            parent_id IS NOT NULL,
                            nom ASC
                    ');
                        $catStmt->execute([$user_id]);

                        foreach ($catStmt->fetchAll() as $c):
                            $nomCategorie = !empty($c['parent_id'])
                                ? '└── 🏷️ ' . $c['nom']
                                : '📁 ' . $c['nom'];
                        ?>
                            <option value="<?= $c['id'] ?>" <?= ($categorieFilter == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomCategorie) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre par année -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📅</span> Année</label>
                    <select name="annee_filter" class="form-select bg-dark text-white border-secondary shadow-sm">
                        <option value="">Toutes</option>
                        <?php foreach ($anneesDisponibles as $annee): ?>
                            <?php if ($annee): ?>
                                <option value="<?= htmlspecialchars($annee) ?>" <?= ($anneeFilter == $annee) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($annee) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📅</span> Depuis le</label>
                    <input type="date" name="date_filter" class="form-control bg-dark text-white border-secondary shadow-sm"
                        value="<?= htmlspecialchars($dateFilter ?? '') ?>">
                </div>
                <?php
                /** ==========================================================================
                 *  Statut
                 *  ========================================================================== */
                ?>
                <div class="col-md">
                    <label class="form-label text-white-50 mb-1 small">
                        <span class="emoji fs-4">📌</span> Statut
                    </label>

                    <select name="statut_filter"
                        class="form-select bg-dark text-white border-secondary w-100">

                        <option value=""
                            <?= ($filtreStatut === '') ? 'selected' : '' ?>>
                            Tous les statuts
                        </option>

                        <option value="attendu"
                            <?= ($filtreStatut === 'attendu') ? 'selected' : '' ?>>
                            ⏳ Attendu
                        </option>

                        <option value="percu"
                            <?= ($filtreStatut === 'percu') ? 'selected' : '' ?>>
                            ✅ Perçu
                        </option>

                        <option value="en_retard"
                            <?= ($filtreStatut === 'en_retard') ? 'selected' : '' ?>>
                            ⚠️ En retard
                        </option>

                    </select>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col d-flex align-items-end gap-2">

                        <!-- BOUTON FILTRER -->
                        <button type="submit"
                            class="btn-create-dash shadow-sm px-3 d-inline-flex align-items-center justify-content-center w-100"
                            style="height: 38px;">
                            <span class="emoji fs-4">🔍</span> Filtrer
                        </button>

                        <!-- BOUTON Effacer -->
                        <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php"
                            class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center w-100"
                            style="height: 38px;" title="Réinitialiser tous les filtres">
                            <span class="emoji fs-4">🔄 </span> Effacer les filtres
                        </a>
                    </div>
                </div>
            </div>

        </form>

        <!-- Tableau des ressources -->
        <div class="table-responsive shadow-lg">
            <table class="table table-dark table-hover mb-0 bg-glass table-custom-dark">
                <thead>
                    <tr>
                        <th style="width:20%">Titre</th>
                        <th style="width:16%">Organisme</th>
                        <th style="width:10%" class="text-end">Total prévu</th>
                        <th style="width:10%" class="text-end">Perçu</th>
                        <th style="width:12%" class="text-center">Date perçu</th>
                        <th style="width:10%" class="text-end">Reste attendu</th>
                        <th style="width:12%" class="text-center">Prochain versement</th>
                        <th style="width:10%" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ressourcesPage)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucune ressource trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        foreach ($ressourcesPage as $r):
                            $totalPrevuRes = $r['total_prevu'] ?? 0;
                            $totalPercuRes = $r['total_percu'] ?? 0;
                            $reste = $totalPrevuRes - $totalPercuRes;
                            $estEnRetard = ($r['prochaine_versement'] && $r['prochaine_versement'] < $aujourdhui && $reste > 0);
                            $classeBordure = ($reste <= 0 && $totalPrevuRes > 0) ? 'border-success' : ($estEnRetard ? 'border-danger' : 'border-secondary');

                            // Gestion des couleurs et de la hiérarchie des catégories
                            $catColor = $r['categorie_couleur'] ?? '#ff8000';
                            $aUneParente = !empty($r['categorie_parente_nom']);
                            $ressourceId = (int) $r['id'];
                        ?>
                            <tr class="align-middle <?= $classeBordure ?>">
                                <td data-label="Titre">
                                    <!-- Titre principal -->
                                    <div class="fw-bold text-white mb-1 me-4">
                                        <?= htmlspecialchars($r['titre'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                    <!-- Conteneur des badges corrigé (Hiérarchie inversée : Catégorie directe d'abord, puis Parente) -->
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">

                                        <!-- Badge Catégorie Directe (ex: Salaires) -->
                                        <?php if (!empty($r['categorie_nom'])): ?>
                                            <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                                class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cette ressource" style="
                                                                                    border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                    background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11;
                                                                                    color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                    box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                                ">
                                                📁 <?= htmlspecialchars($r['categorie_nom'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Badge Catégorie Parente (si existante, ex: MBS Gestion) -->
                                        <?php if ($aUneParente): ?>
                                            <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                                class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cette ressource" style="
                                                                                    border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                    background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22;
                                                                                    color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                    box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                                ">
                                                🏷️ <?= htmlspecialchars($r['categorie_parente_nom'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Badge À Classer (si non classé) -->
                                        <?php if (empty($r['categorie_nom'])): ?>
                                            <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                                class="text-decoration-none" title="Cliquez pour classer cette ressource">
                                                <span class="badge badge-warning-pulse">
                                                    ⚠️ À CLASSER
                                                </span>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Badge Versements -->
                                        <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $ressourceId ?>"
                                            class="badge-neon badge-echeancier text-decoration-none">
                                            📅 Versements
                                        </a>
                                    </div>
                                </td>
                                <td data-label="Organisme"><?= htmlspecialchars($r['organisme'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Total prévu" class="text-end"><?= number_format($totalPrevuRes, 2, ',', ' ') ?> €</td>
                                <td data-label="Perçu" class="text-end"><?= number_format($totalPercuRes, 2, ',', ' ') ?> €</td>
                                <td data-label="Date perçu" class="text-center">
                                    <?= $r['derniere_date_percue'] ? date('d/m/Y', strtotime($r['derniere_date_percue'])) : '-' ?>
                                </td>
                                <td data-label="Reste attendu" class="text-end"><?= number_format($reste, 2, ',', ' ') ?> €</td>
                                <td data-label="Prochain versement" class="text-center">
                                    <?= $r['prochaine_versement'] ? date('d/m/Y', strtotime($r['prochaine_versement'])) : '-' ?>
                                </td>
                                <td class="td-action text-center" data-label="Actions">
                                    <!-- MODIFIER -->
                                    <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= (int) $r['id'] ?>"
                                        class="btn-modifier-neon mb-2 d-inline-block">
                                        ✏️ Modifier
                                    </a>
                                    <!-- SUPPRIMER -->
                                    <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_ressource.php"
                                        onsubmit="return confirm('⚠️ Confirmer la suppression de cette ressource ?');">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <? csrf_input() ?>
                                        <button type="submit" class="btn btn-sm btn-danger btn-action-dash w-100">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


        <!-- Pagination -->
        <?php
        if ($totalPages > 1):

            $filtresPagination = [
                'p' => 'historique_ressources.php',
                'search' => $search,
                'categorie_filter' => $categorieFilter,
                'date_filter' => $dateFilter ?? '',
                'annee_filter' => $anneeFilter,
                'statut_filter' => $filtreStatut
            ];

            $baseUrl = BASE_URL . 'router.php?' .
                http_build_query($filtresPagination);

            // Génération d'une URL pour chaque page
            $urlPage = static function (int $page) use ($baseUrl): string {
                return htmlspecialchars(
                    $baseUrl . '&page=' . $page,
                    ENT_QUOTES,
                    'UTF-8'
                );
            };

            // Fenêtre de pages à afficher
            $debut = max(1, $pageCourante - 2);
            $fin = min($totalPages, $pageCourante + 2);
        ?>

            <nav class="d-flex justify-content-center mt-4"
                aria-label="Pagination des ressources">

                <ul class="pagination pagination-sm flex-wrap">

                    <!-- Première page -->
                    <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= $urlPage(1) ?>"
                            title="Aller à la première page"
                            aria-label="Première page"
                            <?= ($pageCourante <= 1) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            «
                        </a>
                    </li>

                    <!-- Page précédente -->
                    <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= $urlPage(max(1, $pageCourante - 1)) ?>"
                            title="Aller à la page précédente"
                            aria-label="Page précédente"
                            <?= ($pageCourante <= 1) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            ‹
                        </a>
                    </li>


                    <!-- Pages numérotées -->
                    <?php if ($debut > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $urlPage(1) ?>">1</a>
                        </li>

                        <?php if ($debut > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">…</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $debut; $i <= $fin; $i++): ?>
                        <li class="page-item <?= ($i === $pageCourante) ? 'active' : '' ?>">
                            <a class="page-link"
                                href="<?= $urlPage($i) ?>"
                                <?= ($i === $pageCourante) ? 'aria-current="page"' : '' ?>>
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($fin < $totalPages): ?>
                        <?php if ($fin < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">…</span>
                            </li>
                        <?php endif; ?>

                        <li class="page-item">
                            <a class="page-link"
                                href="<?= $urlPage($totalPages) ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Page suivante -->
                    <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= $urlPage(min($totalPages, $pageCourante + 1)) ?>"
                            title="Aller à la page suivante"
                            aria-label="Page suivante"
                            <?= ($pageCourante >= $totalPages) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            ›
                        </a>
                    </li>

                    <!-- Dernière page -->
                    <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= $urlPage($totalPages) ?>"
                            title="Aller à la dernière page"
                            aria-label="Dernière page"
                            <?= ($pageCourante >= $totalPages) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            »
                        </a>
                    </li>

                </ul>
            </nav>

        <?php endif; ?>
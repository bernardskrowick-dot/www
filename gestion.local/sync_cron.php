<?php

/**
 * Script de synchronisation cron bidirectionnel (Local <-> Distant)
 * Ce script est destiné à être exécuté par une tâche planifiée (Cron)
 */

// 1. Inclusion des fichiers de configuration et de connexion locaux
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connexion-cron.php';
require_once __DIR__ . '/includes/sync_functions.php';

// Sécurité : Ce script ne doit être exécuté que depuis le CLI (Terminal/Cron) ou avec une protection stricte
if (php_sapi_name() !== 'cli' && !isset($_GET['force_run'])) {
  http_response_code(403);
  die("Accès refusé : Ce script doit être exécuté en ligne de commande (Cron).");
}

// Paramètres de configuration
$remoteApiUrl = 'https://gestion.hugueslenoir.fr/sync_api.php';
$syncToken = defined('SYNC_SECRET_TOKEN') ? SYNC_SECRET_TOKEN : '';
$stateFile = __DIR__ . '/last_sync.txt';

// Définition centralisée de toutes les tables gérées et de leur clé primaire si différente de 'id'
$tablesConfig = [
  // 1. Tables sans dépendances (Niveau 0)
  'users' => [
    'pk' => 'id',
    'columns' => ['username', 'email', 'password_hash', 'role', 'is_super_admin', 'statut', 'theme', 'solde_initial', 'premiere_periode', 'jour_debut_periode', 'sticky_pos', 'updated_at', 'deleted_at']
  ],
  'categories' => [
    'pk' => 'id',
    'columns' => ['user_id', 'nom', 'parent_id', 'couleur', 'updated_at', 'deleted_at']
  ],
  'categories_ressources' => [
    'pk' => 'id',
    'columns' => ['user_id', 'nom', 'couleur', 'parent_id', 'updated_at', 'deleted_at']
  ],
  'fichiers' => [
    'pk' => 'id',
    'columns' => ['chemin', 'type', 'status', 'date_scan', 'date_modification', 'num_revision', 'updated_at', 'deleted_at']
  ],
  'app_versions' => [
    'pk' => 'id',
    'columns' => ['version_number', 'taux_changement', 'nb_fichiers_impactes', 'date_release', 'updated_at', 'deleted_at']
  ],

  // 2. Tables parents ayant besoin de 'users' ou de catégories
  'achats' => [
    'pk' => 'id',
    'columns' => ['user_id', 'titre', 'nom_marchand', 'montant_total', 'date_depart', 'date_creation', 'categorie_id', 'recurrence', 'updated_at', 'deleted_at']
  ],
  'ressources' => [
    'pk' => 'id',
    'columns' => ['user_id', 'titre', 'organisme', 'montant_prevu', 'date_depart', 'recurrence', 'categorie_id', 'date_creation', 'date_fin', 'updated_at', 'deleted_at']
  ],
  'historique_fichiers' => [
    'pk' => 'id',
    'columns' => ['chemin', 'ancien_status', 'nouveau_status', 'admin', 'date_action', 'updated_at', 'deleted_at']
  ],

  // 3. Tables enfants dépendantes (Niveau final)
  'echeances' => [
    'pk' => 'id',
    'columns' => ['achat_id', 'date_echeance', 'montant', 'statut', 'date_paiement', 'modifie_manuellement', 'modifie_montant', 'modifie_date', 'updated_at', 'deleted_at']
  ],
  'versements' => [
    'pk' => 'id',
    'columns' => ['ressource_id', 'date_versement_prevue', 'montant_prevu', 'statut', 'montant_reel', 'date_perception', 'modifie_manuellement', 'date_creation', 'modifie_date', 'modifie_montant', 'updated_at', 'deleted_at']
  ]
];

$tables = array_keys($tablesConfig);

// 2. Gestion de la date de dernière synchronisation
$lastSync = '1970-01-01 00:00:00';
if (file_exists($stateFile)) {
  $lastSync = trim(file_get_contents($stateFile));
}

$dateTime = new DateTime('now', new DateTimeZone('Europe/Paris'));
$currentSyncTime = $dateTime->format('Y-m-d H:i:s');

try {
  if (!isset($pdo)) {
    throw new Exception("Connexion à la base de données locale introuvable.");
  }

  // =========================================================================
  // ÉTAPE 1 : ENVOI DES CHANGEMENTS LOCAUX VERS LE DISTANT (PUSH)
  // =========================================================================
  $itemsPayload = [];
  $deletionsPayload = [];

  foreach ($tables as $table) {
    $itemsPayload[$table] = getLocalUnsyncedItems($pdo, $table);
    $deletionsPayload[$table] = getLocalDeletions($pdo, $table, $lastSync);
  }

  $postDataPush = [
    'token' => $syncToken,
    'action' => 'push_changes',
    'items' => json_encode($itemsPayload),
    'deletions' => json_encode($deletionsPayload)
  ];

  $pushResponse = callRemoteApi($remoteApiUrl, $postDataPush);

  if (!$pushResponse['success']) {
    throw new Exception("Erreur API distante (push_changes) : " . ($pushResponse['error'] ?? 'Inconnue'));
  }

  // Nettoyage local de la synchronisation des items
  foreach ($tables as $table) {
    $pdo->exec("UPDATE {$table} SET is_synced = 1 WHERE is_synced = 0");
  }

  // Nettoyage local des tables sync_deletions après succès du push
  foreach ($tables as $table) {
    clearLocalDeletions($pdo, $table, $deletionsPayload[$table]);
  }

  // =========================================================================
  // ÉTAPE 2 : RÉCUPÉRATION DES CHANGEMENTS DISTANTS (GET)
  // =========================================================================
  $postDataGet = [
    'token' => $syncToken,
    'action' => 'get_changes',
    'last_sync' => $lastSync
  ];

  $remoteData = callRemoteApi($remoteApiUrl, $postDataGet);

  if (!$remoteData['success']) {
    throw new Exception("Erreur API distante (get_changes) : " . ($remoteData['error'] ?? 'Inconnue'));
  }

  $remotePayload = $remoteData['data'] ?? [];
  $remoteDeletions = $remotePayload['deletions'] ?? [];


  // =========================================================================
  // ÉTAPE 3 : APPLICATION DES CHANGEMENTS EN LOCAL (TRANSACTION)
  // =========================================================================
  $pdo->beginTransaction();

  // 1. Appliquer les suppressions distantes
  foreach ($tables as $table) {
    $idsToDelete = $remoteDeletions[$table] ?? [];
    $pk = $tablesConfig[$table]['pk'];
    syncTableDeletions($pdo, $table, $idsToDelete, $pk);
  }

  // 2. Appliquer les insertions / mises à jour (Upsert)
  foreach ($tables as $table) {
    $items = $remotePayload[$table] ?? [];
    $pk = $tablesConfig[$table]['pk'];
    $columns = $tablesConfig[$table]['columns'];

    syncTableUpsert($pdo, $table, $items, $pk, $columns);
  }

  $pdo->commit();

  // ACQUITTEMENT DES MODIFICATIONS DISTANTES (Passer is_synced à 1 sur le distant)
  $changesAckData = [];
  foreach ($tables as $table) {
    $items = $remotePayload[$table] ?? [];
    $pk = ($table === 'fichiers') ? 'chemin' : $tablesConfig[$table]['pk'];
    $keys = [];
    foreach ($items as $item) {
      if (isset($item[$pk])) {
        $keys[] = $item[$pk];
      }
    }
    if (!empty($keys)) {
      $changesAckData[$table] = $keys;
    }
  }

  if (!empty($changesAckData)) {
    $postDataChangesAck = [
      'token' => $syncToken,
      'action' => 'ack_changes',
      'changes' => json_encode($changesAckData)
    ];
    $ackChangesResponse = callRemoteApi($remoteApiUrl, $postDataChangesAck);
    if (!$ackChangesResponse['success']) {
      echo "Avertissement : L'acquittement des modifications distantes a échoué.\n";
    }
  }

  // 4. ACQUITTEMENT : Informer le serveur distant de nettoyer ses sync_deletions consommées
  if (!empty($remoteDeletions)) {
    $hasDeletionsToAck = false;
    foreach ($remoteDeletions as $tableIds) {
      if (!empty($tableIds)) {
        $hasDeletionsToAck = true;
        break;
      }
    }

    if ($hasDeletionsToAck) {
      $postDataAck = [
        'token' => $syncToken,
        'action' => 'ack_deletions',
        'deletions' => json_encode($remoteDeletions)
      ];
      $ackResponse = callRemoteApi($remoteApiUrl, $postDataAck);
      if (!$ackResponse['success']) {
        echo "Avertissement : L'acquittement des suppressions distantes a échoué.\n";
      }
    }
  }

  // 3. Mettre à jour le fichier d'état
  file_put_contents($stateFile, $currentSyncTime);

  echo "Synchronisation bidirectionnelle réussie à " . $currentSyncTime . "\n";
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "Erreur lors de la synchronisation : " . $e->getMessage() . "\n";
}

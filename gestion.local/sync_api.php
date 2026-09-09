<?php
/* Version: v1.21.0 (Rev #26) - 2026-08-08 */

/**
 * API de synchronisation distante sécurisée (Factorisée)
 * Emplacement : Racine du site distant
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connexion.php'; // Fournit l'objet $pdo

header('Content-Type: application/json; charset=utf-8');

// 1. Vérification de la méthode HTTP (POST obligatoire)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
  exit;
}

// 2. Vérification du Token Secret
$inputToken = $_POST['token'] ?? '';
if (!defined('SYNC_SECRET_TOKEN') || !hash_equals(SYNC_SECRET_TOKEN, $inputToken)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Accès refusé : Token invalide.']);
  exit;
}

$action = $_POST['action'] ?? '';
$response = ['success' => false];

// Définition centralisée des tables et de leurs structures (identique au client)
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

try {
  if (!isset($pdo)) {
    throw new Exception("Connexion à la base de données introuvable.");
  }

  // -------------------------------------------------------------------------
  // ACTION 1 : Le local demande les modifications distantes non synchronisées
  // -------------------------------------------------------------------------
  if ($action === 'get_changes') {
    $lastSync = $_POST['last_sync'] ?? '1970-01-01 00:00:00';

    $dataPayload = [];
    $deletionsPayload = [];

    foreach ($tables as $table) {
      // Récupération des éléments non synchronisés
      $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE is_synced = 0");
      $stmt->execute();
      $dataPayload[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Récupération des suppressions
      $stmtDel = $pdo->prepare("SELECT record_id FROM sync_deletions WHERE table_name = ? AND deleted_at > ?");
      $stmtDel->execute([$table, $lastSync]);
      $deletionsPayload[$table] = $stmtDel->fetchAll(PDO::FETCH_COLUMN);
    }

    $response['success'] = true;
    $response['data'] = $dataPayload;
    $response['data']['deletions'] = $deletionsPayload;
  }

  // -------------------------------------------------------------------------
  // ACTION 2 : Le local pousse ses modifications vers le distant
  // -------------------------------------------------------------------------
  elseif ($action === 'push_changes') {
    $items = json_decode($_POST['items'] ?? '[]', true);
    $deletions = json_decode($_POST['deletions'] ?? '[]', true);

    if (!is_array($items)) {
      throw new Exception("Format de données invalide pour les items.");
    }

    $pdo->beginTransaction();

    // A. Traitement des suppressions transmises par le local (Suppression physique distante)
    if (!empty($deletions) && is_array($deletions)) {
      foreach ($tables as $table) {
        $idsToDelete = $deletions[$table] ?? [];
        if (!empty($idsToDelete)) {
          $pk = ($table === 'fichiers') ? 'chemin' : $tablesConfig[$table]['pk'];
          $stmtDel = $pdo->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
          foreach ($idsToDelete as $id) {
            $stmtDel->execute([$id]);
          }
        }
      }
    }

    // B. Traitement des inserts / updates (Upsert générique côté serveur)
    foreach ($tables as $table) {
      $tableItems = $items[$table] ?? [];
      if (empty($tableItems)) {
        continue;
      }

      $columns = $tablesConfig[$table]['columns'];

      // Cas particulier pour la table 'fichiers' (unicité sur 'chemin', ID auto-incrémenté)
      if ($table === 'fichiers') {
        $stmtCheck = $pdo->prepare("SELECT updated_at, deleted_at, num_revision FROM fichiers WHERE chemin = ?");

        $updateFields = [];
        foreach ($columns as $col) {
          if ($col === 'chemin') continue;
          $updateFields[] = "{$col} = ?";
        }
        $updateFieldsStr = implode(', ', $updateFields) . ", is_synced = 1";
        $stmtUpdate = $pdo->prepare("UPDATE fichiers SET {$updateFieldsStr} WHERE chemin = ?");

        $insertCols = array_diff($columns, ['id']);
        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $columnsStr = implode(', ', $insertCols);
        $stmtInsert = $pdo->prepare("INSERT INTO fichiers ({$columnsStr}, is_synced) VALUES ({$placeholders}, 1)");

        foreach ($tableItems as $item) {
          $cheminVal = trim($item['chemin'] ?? '');
          if ($cheminVal === '') continue;

          $stmtCheck->execute([$cheminVal]);
          $remoteItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

          if ($remoteItem && !empty($remoteItem['deleted_at'])) {
            continue;
          }

          $values = [];
          foreach ($insertCols as $col) {
            $values[] = $item[$col] ?? null;
          }

          if ($remoteItem) {
            $incomingRevision = intval($item['num_revision'] ?? 0);
            $remoteRevision = intval($remoteItem['num_revision'] ?? 0);

            // Priorité au numéro de révision le plus élevé, puis à updated_at
            if ($incomingRevision > $remoteRevision || ($incomingRevision === $remoteRevision && isset($item['updated_at']) && isset($remoteItem['updated_at']) && strtotime($item['updated_at']) > strtotime($remoteItem['updated_at']))) {
              $updateValues = [];
              foreach ($columns as $col) {
                if ($col === 'chemin') continue;
                $updateValues[] = $item[$col] ?? null;
              }
              $updateValues[] = $cheminVal;
              $stmtUpdate->execute($updateValues);
            }
          } else {
            $stmtInsert->execute($values);
          }
        }
      } else {
        // Traitement standard pour les autres tables
        $pk = $tablesConfig[$table]['pk'];
        $stmtCheck = $pdo->prepare("SELECT updated_at, deleted_at FROM {$table} WHERE {$pk} = ?");

        $updateFields = [];
        foreach ($columns as $col) {
          $updateFields[] = "{$col} = ?";
        }
        $updateFieldsStr = implode(', ', $updateFields) . ", is_synced = 1";
        $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$updateFieldsStr} WHERE {$pk} = ?");

        $allColumns = array_merge([$pk], $columns);
        $placeholders = implode(', ', array_fill(0, count($allColumns), '?'));
        $columnsStr = implode(', ', $allColumns);
        $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$columnsStr}, is_synced) VALUES ({$placeholders}, 1)");

        foreach ($tableItems as $item) {
          $pkVal = $item[$pk] ?? null;
          if ($pkVal === null) continue;

          $stmtCheck->execute([$pkVal]);
          $remoteItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

          if ($remoteItem && !empty($remoteItem['deleted_at'])) {
            continue;
          }

          $values = [];
          foreach ($columns as $col) {
            $values[] = $item[$col] ?? null;
          }

          if ($remoteItem) {
            if (isset($item['updated_at']) && isset($remoteItem['updated_at']) && strtotime($item['updated_at']) > strtotime($remoteItem['updated_at'])) {
              $updateValues = array_merge($values, [$pkVal]);
              $stmtUpdate->execute($updateValues);
            }
          } else {
            $insertValues = array_merge([$pkVal], $values);
            $stmtInsert->execute($insertValues);
          }
        }
      }
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Synchronisation distante réussie.';
  }

  // -------------------------------------------------------------------------
  // ACTION 3 : Acquittement des suppressions distantes consommées par le local
  // -------------------------------------------------------------------------
  elseif ($action === 'ack_deletions') {
    $deletionsAck = json_decode($_POST['deletions'] ?? '[]', true);

    if (!is_array($deletionsAck)) {
      throw new Exception("Format de données invalide pour les acquittements de suppression.");
    }

    $pdo->beginTransaction();

    foreach ($tables as $table) {
      $idsToAck = $deletionsAck[$table] ?? [];
      if (!empty($idsToAck)) {
        $placeholders = implode(',', array_fill(0, count($idsToAck), '?'));
        $stmtDel = $pdo->prepare("DELETE FROM sync_deletions WHERE table_name = ? AND record_id IN ($placeholders)");
        $stmtDel->execute(array_merge([$table], $idsToAck));
      }
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Acquittement des suppressions enregistré.';
  }

  // -------------------------------------------------------------------------
  // ACTION 4 : Acquittement des modifications distantes consommées par le local
  // -------------------------------------------------------------------------
  elseif ($action === 'ack_changes') {
    $changesAck = json_decode($_POST['changes'] ?? '[]', true);

    if (!is_array($changesAck)) {
      throw new Exception("Format de données invalide pour les acquittements de modifications.");
    }

    $pdo->beginTransaction();

    foreach ($tables as $table) {
      $keysToAck = $changesAck[$table] ?? [];
      if (!empty($keysToAck)) {
        $pk = ($table === 'fichiers') ? 'chemin' : $tablesConfig[$table]['pk'];
        $placeholders = implode(',', array_fill(0, count($keysToAck), '?'));
        $stmtAck = $pdo->prepare("UPDATE {$table} SET is_synced = 1 WHERE {$pk} IN ($placeholders)");
        $stmtAck->execute($keysToAck);
      }
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Acquittement des modifications enregistré.';
  } else {
    throw new Exception("Action inconnue.");
  }
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  $response['error'] = $e->getMessage();
}

echo json_encode($response);

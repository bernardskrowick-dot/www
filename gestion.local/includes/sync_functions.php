<?php
/* Version: v1.21.0 (Rev #26) - 2026-08-08 */

/**
 * Fichier des fonctions utilitaires pour la synchronisation
 */

function callRemoteApi(string $url, array $data): array
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    return ['success' => false, 'error' => 'Erreur cURL : ' . $curlError];
  }

  if ($httpCode !== 200) {
    return ['success' => false, 'error' => 'HTTP Code ' . $httpCode . ' - Réponse : ' . $response];
  }

  $decoded = json_decode($response, true);
  if (!is_array($decoded)) {
    return ['success' => false, 'error' => 'Réponse JSON invalide reçue du serveur distant : ' . $response];
  }

  return $decoded;
}

function clearLocalDeletions(PDO $pdo, string $tableName, array $ids): void
{
  if (empty($ids)) return;
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("DELETE FROM sync_deletions WHERE table_name = ? AND record_id IN ($placeholders)");
  $stmt->execute(array_merge([$tableName], $ids));
}

/**
 * Fonction générique pour appliquer les insertions / mises à jour (Upsert) sur une table
 */
function syncTableUpsert(PDO $pdo, string $tableName, array $items, string $primaryKey, array $columns): void
{
  if (empty($items)) {
    return;
  }

  // Cas particulier pour la table 'fichiers' : l'unicité métier se fait sur 'chemin' 
  // et non sur l'ID auto-incrémenté pour éviter les conflits d'IDs entre serveurs.
  if ($tableName === 'fichiers') {
    $stmtCheck = $pdo->prepare("SELECT updated_at, num_revision FROM fichiers WHERE chemin = ?");

    $updateFields = [];
    foreach ($columns as $col) {
      if ($col === 'chemin') continue; // Le chemin est la condition WHERE
      $updateFields[] = "{$col} = ?";
    }
    $updateFieldsStr = implode(', ', $updateFields) . ", is_synced = 1";
    $stmtUpdate = $pdo->prepare("UPDATE fichiers SET {$updateFieldsStr} WHERE chemin = ?");

    // Construction de l'INSERT sans l'id (géré en auto-incrément par MySQL)
    $insertCols = array_diff($columns, ['id']);
    $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
    $columnsStr = implode(', ', $insertCols);
    $stmtInsert = $pdo->prepare("INSERT INTO fichiers ({$columnsStr}, is_synced) VALUES ({$placeholders}, 1)");

    foreach ($items as $item) {
      $cheminVal = trim($item['chemin'] ?? '');
      if ($cheminVal === '') continue;

      $stmtCheck->execute([$cheminVal]);
      $localItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

      $values = [];
      foreach ($insertCols as $col) {
        $values[] = $item[$col] ?? null;
      }

      if ($localItem) {
        $remoteRevision = intval($item['num_revision'] ?? 0);
        $localRevision = intval($localItem['num_revision'] ?? 0);

        // Règle infaillible : le plus grand numéro l'emporte, ou à défaut le plus récent updated_at
        if ($remoteRevision > $localRevision || ($remoteRevision === $localRevision && strtotime($item['updated_at']) > strtotime($localItem['updated_at']))) {
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
    return;
  }

  // Traitement standard pour les autres tables
  $stmtCheck = $pdo->prepare("SELECT updated_at FROM {$tableName} WHERE {$primaryKey} = ?");

  $updateFields = [];
  foreach ($columns as $col) {
    $updateFields[] = "{$col} = ?";
  }
  $updateFieldsStr = implode(', ', $updateFields) . ", is_synced = 1";
  $stmtUpdate = $pdo->prepare("UPDATE {$tableName} SET {$updateFieldsStr} WHERE {$primaryKey} = ?");

  $allColumns = array_merge([$primaryKey], $columns);
  $placeholders = implode(', ', array_fill(0, count($allColumns), '?'));
  $columnsStr = implode(', ', $allColumns);
  $stmtInsert = $pdo->prepare("INSERT INTO {$tableName} ({$columnsStr}, is_synced) VALUES ({$placeholders}, 1)");

  foreach ($items as $item) {
    $pkVal = $item[$primaryKey] ?? null;
    if ($pkVal === null) continue;

    $stmtCheck->execute([$pkVal]);
    $localItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $values = [];
    foreach ($columns as $col) {
      $values[] = $item[$col] ?? null;
    }

    if ($localItem) {
      if (isset($item['updated_at']) && isset($localItem['updated_at']) && strtotime($item['updated_at']) > strtotime($localItem['updated_at'])) {
        $updateValues = array_merge($values, [$pkVal]);
        $stmtUpdate->execute($updateValues);
      }
    } else {
      $insertValues = array_merge([$pkVal], $values);
      $stmtInsert->execute($insertValues);
    }
  }
}

/**
 * Récupère tous les éléments locaux non synchronisés pour une table donnée
 */
function getLocalUnsyncedItems(PDO $pdo, string $tableName): array
{
  $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE is_synced = 0");
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les IDs des éléments supprimés localement depuis la dernière synchro
 */
function getLocalDeletions(PDO $pdo, string $tableName, string $lastSync): array
{
  $stmt = $pdo->prepare("SELECT record_id FROM sync_deletions WHERE table_name = ?");
  $stmt->execute([$tableName]);
  return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Fonction générique pour appliquer les suppressions distantes en local
 */
function syncTableDeletions(PDO $pdo, string $tableName, array $idsToDelete, string $primaryKey = 'id'): void
{
  if (empty($idsToDelete)) {
    return;
  }

  // Pour la table fichiers, si les IDs transmis correspondent au chemin ou à l'id technique
  $pk = ($tableName === 'fichiers') ? 'chemin' : $primaryKey;

  $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE {$pk} = ?");
  foreach ($idsToDelete as $id) {
    $stmt->execute([$id]);
  }
}

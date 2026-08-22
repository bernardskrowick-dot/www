/* Version: v1.3.0 (Rev #4) - 2026-07-30 */
<form method="POST" action="traiter_theme.php">
    <?php csrf_input(); // Ta fonction de fonctions.php ?>
    <select name="theme">
        <option value="mbs">MBS (Défaut)</option>
        <option value="dark">Mode Sombre</option>
    </select>
    <button type="submit">Enregistrer</button>
</form>
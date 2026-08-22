<?php
 $pdo = new PDO(
    "mysql:host=localhost;dbname=ii3jbzwv_gestion_echeances;charset=utf8mb4",
    "Cron_serv",
    "Mk8@dkot#8/71"
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
?>
<?php
/* Version: v1.0.1 - 2026-08-11 */

// --- 1. CLASSE POUR L'AUTOMATISATION DE LA RÉPLICATION ---
/* class PDOReplique extends PDO
{
    private $pdo_dist;
    public function setDist($pdo_dist)
    {
        $this->pdo_dist = $pdo_dist;
    }

    // Signature exacte compatible avec PDO::prepare()
    public function prepare(string $query, array $options = []): PDOStatement|bool
    {
        $stmt = parent::prepare($query, $options);
        if ($stmt === false) {
            return false;
        }
        return new StatementReplique($stmt, $this->pdo_dist, $query);
    }
}

class StatementReplique
{
    private $stmt, $pdo_dist, $query;
    public function __construct($stmt, $pdo_dist, $query)
    {
        $this->stmt = $stmt;
        $this->pdo_dist = $pdo_dist;
        $this->query = $query;
    }
    public function execute(?array $params = null): bool
    {
        $res = $this->stmt->execute($params);
        $q = strtoupper(trim($this->query));
        if (str_starts_with($q, 'INSERT') || str_starts_with($q, 'UPDATE') || str_starts_with($q, 'DELETE')) {
            if ($this->pdo_dist) {
                try {
                    $s = $this->pdo_dist->prepare($this->query);
                    $s->execute($params);
                } catch (Exception $e) {
                    error_log("Synchro distante échouée : " . $e->getMessage());
                }
            }
        }
        return $res;
    }
    public function __call($name, $args)
    {
        return call_user_func_array([$this->stmt, $name], $args);
    }
    public function fetch($mode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($mode, $cursorOrientation, $cursorOffset);
    }
}

// --- 2. CONFIGURATION (Vos identifiants REDACTED) ---
$host_local = 'localhost';
$db_local   = 'ii3jbzwv_gestion_echeances';
$user_local = 'Cron_serv';
$pass_local = 'Mk8@dkot#8/71';

$host_distant = '81.88.53.128';
$port_distant = '3306';
$db_distant   = 'ii3jbzwv_gestion_echeances';
$user_distant = 'ii3jbzwv_EricB';
$pass_distant = 'Mk7@dkot#7/71';

// --- 3. INITIALISATION DES CONNEXIONS ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo_distant = new PDO("mysql:host={$host_distant};port={$port_distant};dbname={$db_distant};charset=utf8mb4", $user_distant, $pass_distant, $options);
} catch (PDOException $e) {
    $pdo_distant = null;
    error_log("Erreur de connexion distante (synchro) : " . $e->getMessage());
}

try {
    $pdo = new PDOReplique("mysql:host=$host_local;dbname=$db_local;charset=utf8mb4", $user_local, $pass_local, $options);
    $pdo->setDist($pdo_distant);
} catch (PDOException $e) {
    die("Erreur de connexion locale : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
*/
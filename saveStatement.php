<?php 
require_once('config.php');
require_once('utilities.php');
require_once('mlnew.php');
$fu = $_POST["fu"];
try {
    refreshML($fu);
    $db = new PDO(PDO_CONNECT, USER, PSWD, $PDO_OPTIONS);
    $stmt = $db->query('Select MAX(ref) as mref from Journal');
    $newRef = intval($stmt->fetchColumn()) + 1; 
    $stmt = $db->query('SELECT dt, am, comm, duplicate, bal, DATE_FORMAT(dt,"%d-%b-%Y") as fdate FROM Statement ORDER BY id');
    $insert_sql = 'INSERT INTO Journal (ref, dt, fu, fu2, fu_amount, ac, ac_amount, comm, bal, rec, guess) ';
    $insert_sql .= 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $insert = $db->prepare( $insert_sql);
    $count = 0;
    $tables = $db->query('SHOW TABLES LIKE "Tokens' . strval($fu) . '";')->fetchAll(PDO::FETCH_COLUMN);
    $ml = count($tables) == 1;
    if ($ml) {
        $tokens = $db->query('Select * from Tokens' . $fu. ' ORDER BY freq DESC;')->fetchAll(PDO::FETCH_ASSOC);
    }
    while($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $guess = 0;
        $ac = AC_UNALLOCATED;
        if ($result["duplicate"] == "Y") {
            $ac = AC_DUPLICATE;
        } elseif ($ml) {
            // Search for token in comments string
            $co = " " . strtoupper($result["comm"]) . " ";
            for ($i = 0; $i < count($tokens); $i++){
                if (strpos($co, $tokens[$i]['token']) !== false) {
                    $ac = $tokens[$i]['ac'];
                    $guess = 1;
                    break;
                }
            }
        }
        $temp = array($newRef, $result["dt"], $fu, 11, $result["am"], $ac, -$result["am"], $result["comm"], 0.00, "S", $guess);
        $insert->execute($temp);
        ++$newRef;
        ++$count;
    } 
    $db->exec("DELETE FROM Statement;");
}
catch(PDOException $ex) {
    echo $ex->getMessage();
}
echo $count;
$db = null; 
?>

<?php 
require_once('config.php');
require_once('utilities.php');
function refreshML($fu){
    try {
        $db = new PDO(PDO_CONNECT, USER, PSWD, array(PDO::ATTR_EMULATE_PREPARES => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        //Fetch learning data
        $stmt = $db->prepare('Select ac, comm from Journal where Journal.fu = ?');
        $stmt->execute(array($fu));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //
        //Clear model
        //
        $tables = $db->query('SHOW TABLES LIKE "Tokens' . strval($fu) . '";')->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) != 1 ) {
            $db->exec('CREATE TABLE Tokens' . $fu . ' (
                `id` int NOT NULL AUTO_INCREMENT,
                `ac` int NOT NULL,
                `token` varchar(50) NOT NULL,
                `freq` int NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;');
        } else {
            $stmt = $db->exec('TRUNCATE TABLE Tokens' . $fu . ';');
        }
        $regex = $db->query('Select regular from Regex;')->fetchAll(PDO::FETCH_COLUMN);
        //
        //Clean comm strings
        //
        for ($i=0;$i<count($result);$i++) {
            $c = $result[$i]['comm'];
            foreach($regex as $regular) {
                $c = preg_replace($regular,'',$c);
            }
            $c = preg_replace('/\s{2,}/',' ',$c);
            $c = strtoupper(trim($c));
            $result[$i]['comm'] = $c;
        }
        //
        //Tokenize each statement comment, and build table of direct mapping tokens
        //
        $tokens = array();
        $acs = array();
        $freqs = array();
        $remove = array();
        foreach ($result as $row) {
            if (trim($row['comm'])=='') continue;
            $tlist = explode(' ',$row['comm']);
            for ($i=0;$i < count($tlist); $i++) {
                $p = array_search($tlist[$i],$tokens,TRUE);
                if ($p === FALSE) {
                    //Token not previously encountered
                    array_push($tokens,$tlist[$i]);
                    array_push($acs,$row['ac']);
                    array_push($freqs,1);
                    array_push($remove,FALSE);
                } elseif (!$remove[$p]) {
                    //Token not already marked for removal
                    if ($acs[$p] == $row['ac']) {
                        //If it matches allocation, add 1 to frequency count
                        $freqs[$p]++;
                    } else {
                        //Mark token for removal
                        $remove[$p] = TRUE;
                    }
                    //Not captured case - already marked for removal - no action
                }
            }
        }
        //
        //Remove ambiguous tokens
        //
        for ($i=0;$i < count($tokens); $i++) {
            if ($remove[$i]) {
                unset($tokens[$i]);
                unset($acs[$i]);
                unset($freqs[$i]);
            }
        }
        $tokens = array_values($tokens);
        $acs = array_values($acs);
        $freqs = array_values($freqs);
        //
        //Write table
        //
        $stmt = $db->prepare('INSERT INTO Tokens' . $fu . ' (ac, token, freq) VALUES (?,?,?);');
        for ($i=0;$i < count($tokens); $i++) {
            $stmt->execute(array($acs[$i], " " . $tokens[$i] . " ",$freqs[$i]));
        }
    }
    catch(PDOException $ex) {
        echo $ex->getMessage();
    }
    $db = null; 
}
?>

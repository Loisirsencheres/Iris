<?php

namespace Iris\Service;

class UpdateService {

    public function __construct() {

    }

    public function createMaterializedTable($mysqli, $conversion, $date, $model, $key) {
        $value = "1";
        switch ($conversion) {
            case 'NouveauxMembres' :
                $tableName = "t_Registers";
                $tableMatName = "Registers";
                break;
            case 'Bids' :
                $tableName = "t_Bids";
                $tableMatName = "Bids";
                break;
            case 'Paiements' :
                $tableName = "t_Payments";
                $tableMatName = "Payments";
                break;
            case 'CA' :
                $tableName = "t_Payments";
                $tableMatName = "CA";
                $value = "amount";
                break;
            case 'Wins' :
                $tableName = "t_Wins";
                $tableMatName = "Wins";
                break;
            case 'VA' :
                $tableName = "t_Wins";
                $tableMatName = "VA";
                $value = "amount";
                break;
            case 'Connexions' :
                $tableName = "t_Logins";
                $tableMatName = "Logins";
                break;
            default:
                $tableName = "t_Registers";
                $tableMatName = "Registers";
                break;
        }

        $noDirect = (false) ? "and s.campaign_id !=0 " : "";

        $createTable = ("
            CREATE TABLE IF NOT EXISTS mat_Self$tableMatName$key(
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created DATE NOT NULL,
                Campaign_id VARCHAR(11) NOT NULL,
                Campaign_name VARCHAR(255) NOT NULL,
                utm_source VARCHAR(255) NOT NULL,
                utm_campaign VARCHAR(255) NOT NULL,
                utm_medium VARCHAR(255) NOT NULL,
                conversion INT(15) NOT NULL,
                firstConversion DATETIME NOT NULL,
                lastConversion DATETIME NOT NULL,
                updated DATETIME NOT NULL
            )"
                );

        if (!$mysqli->query($createTable)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }

        $delete = ("
            DELETE
            FROM mat_Self$tableMatName$key
            WHERE created='$date'
        ");

        if (!$mysqli->query($delete)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }

        $query = ("    
            CREATE TEMPORARY TABLE tempo$conversion (INDEX(Conversion_id)) ENGINE=MEMORY
            SELECT
                conversion.id AS Conversion_id
                ,SUM(s.uRang) AS total
                ,COUNT(s.uRang) AS nb_campaign
            FROM $tableName conversion
            JOIN t_Sessions s ON s.t_User_id=conversion.t_User_id AND s.rang<conversion.rang AND s.uRang!=0 $noDirect
            WHERE DATE(conversion.created)='$date'
            GROUP BY conversion.id"
                );

        if (!$mysqli->query($query)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }
        $now = new \DateTime();
        $now = $now->format('Y-m-d H:i:s');

        $mainQuery = ("
            INSERT INTO mat_Self$tableMatName$key (created, Campaign_id, Campaign_name, utm_source, utm_campaign, utm_medium, conversion, firstConversion, lastConversion, updated)
            SELECT
                '$date'
                ,c.id AS Campaign_id
                ,if(c.id OR c.id=0,c.name,'Notracking') AS Campaign_name
                ,c.utm_source
                ,c.utm_campaign
                ,c.utm_medium
                ,$model AS conversion
                ,MIN(q2.created) AS firstConversion
                ,MAX(q2.created) AS lastConversion
                ,'$now'
            FROM(
                SELECT
                    conversion.id AS conv
                    ,s.uRang
                    ,q.total
                    ,q.nb_campaign
                    ,s.campaign_id
                    ,conversion.created
                    ,$value AS CA
                FROM $tableName conversion
                LEFT JOIN t_Sessions s ON s.t_User_id=conversion.t_User_id AND s.rang<conversion.rang AND s.uRang!=0 $noDirect
                LEFT JOIN tempo$conversion q ON q.Conversion_id=conversion.id
                WHERE DATE(conversion.created)='$date'
            )q2
            LEFT JOIN campaign c ON c.id=q2.campaign_id
            GROUP BY Campaign_id
            UNION SELECT '$date'
                ,'Total' as Campaign_id
                ,'Total' AS Campaign_name
                ,'Total' AS utm_source
                ,'Total' AS utm_campaign
                ,'Total' AS utm_medium
                ,SUM($value) AS conversion
                ,MIN(created) AS firstConversion
                ,MAX(created) AS lastConversion
                ,'$now'
            FROM $tableName
            WHERE DATE(created) = '$date'"
                );
        if (!$mysqli->query($mainQuery)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }

        $drop = ("
            DROP TABLE tempo$conversion
        ");

        if (!$mysqli->query($drop)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }
    }

    public function addUrang($mysqli) {
        $now = new \DateTime();
        $getuRangUpdated = "
            SELECT `value`
            FROM toolBox
            WHERE _key = 'uRangUpdated'
        ";

        $sqlGetuRangUpdated = $mysqli->prepare($getuRangUpdated);
        $sqlGetuRangUpdated->execute();
        $uRangUpdated = null;
        $sqlGetuRangUpdated->bind_result($uRangUpdated);
        $sqlGetuRangUpdated->fetch();
        $sqlGetuRangUpdated->close();
        $drop = "
            UPDATE t_Sessions s
            LEFT JOIN t_Users u ON u.id=s.t_User_id
            SET s.uRang=0
            WHERE u.user_id !=0 AND u.updated > '$uRangUpdated' AND u.updated <= '" . $now->format('Y-m-d H:i:s') . "'
        ";
        if (!$mysqli->query($drop)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }

        $query = "
            SELECT
                s.id
                ,u.id
            FROM t_Users u
            JOIN t_Sessions s ON s.t_User_id=u.id
            WHERE u.user_id !=0 AND u.updated>='$uRangUpdated' AND u.updated <= '" . $now->format('Y-m-d H:i:s') . "'
            GROUP by u.id, s.campaign_id
            ORDER BY u.id, s.rang
        ";
        $sqlGet = $mysqli->prepare($query);
        $sqlGet->execute();
        $res = [];
        $sessions = [];
        $sqlGet->bind_result($res['id'], $res['user_id']);
        $oldUser = 0;
        $rang = 1;
        while ($sqlGet->fetch()) {
            if ($oldUser != $res['user_id']) {
                $rang = 1;
            }
            $sessions[$res['id']] = array(
                't_User_id' => $res['user_id'],
                'uRang' => $rang,
            );
            $oldUser = $res['user_id'];
            $rang++;
        }
        $sqlGet->close();
        foreach ($sessions as $key => $session) {
            $update = "UPDATE t_Sessions SET uRang=" . $session['uRang'] . " WHERE id=$key";
            if (!$mysqli->query($update)) {
                printf("Message d'erreur : %s\n", $mysqli->error);
            }
        }

        $insert = "
            UPDATE toolBox SET `value`='" . $now->format('Y-m-d H:i:s') . "'
            WHERE _key = 'uRangUpdated'
        ";
        if (!$mysqli->query($insert)) {
            printf("Message d'erreur : %s\n", $mysqli->error);
        }
    }

}

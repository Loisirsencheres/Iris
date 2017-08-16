<?php

namespace Iris\Service;

class CleanerService {

    public function __construct() {

    }

    // Get IP with more than 50 touchpoints session
    // detect robot
    public function getIp($mysqli, $nbTouchpoint, $minimum, $touchpointId) {
        $ipList = null;
        $sqlGet = $mysqli->prepare("SELECT ip, COUNT(1) AS nb FROM (SELECT * FROM touchpoints ORDER BY id DESC LIMIT $nbTouchpoint)q WHERE type='session' AND id<=$touchpointId GROUP BY ip HAVING nb >=$minimum");
        $sqlGet->execute();
        $ip = null;
        $sqlGet->bind_result($ip, $nb);
        $inc = 0;
        while ($sqlGet->fetch()) {
            $ipList[$inc] = $ip;
            $inc++;
        }
        $sqlGet->close();
        return $ipList;
    }

    // Get the IP which have at least one touchpoint different to session among a list of ip 
    // Detect human among the robots
    public function getIpNot($mysqli, $ipList) {
        $ipListNot = null;
        $listIp = "";
        foreach ($ipList AS $ip) {
            $listIp.="'" . $ip . "',";
        }
        $listIp = substr($listIp, 0, -1);
        $sqlGet = $mysqli->prepare("SELECT ip FROM touchpoints WHERE ip IN($listIp) AND type!='session' GROUP BY ip");
        $sqlGet->execute();
        $ip = null;
        $sqlGet->bind_result($ip);
        $inc = 0;
        while ($sqlGet->fetch()) {
            $ipListNot[$inc] = $ip;
            $inc++;
        }
        $sqlGet->close();
        return $ipListNot;
    }

    //Unset the human in the robotList
    public function purge($ipList, $ipListNot) {
        foreach ($ipList as $key => $ip) {
            foreach ($ipListNot as $keyBis => $ipNot) {
                if ($ip == $ipNot) {
                    unset($ipList[$key]);
                }
            }
        }
        return $ipList;
    }

    //Delete RobotIP in base
    public function delete($mysqli, $ipList) {
        $listIp = "";
        foreach ($ipList AS $ip) {
            $listIp.="'" . $ip . "',";
        }
        if ($listIp != "") {
            $listIp = substr($listIp, 0, -1);
            $sqlGet = $mysqli->prepare("DELETE FROM touchpoints WHERE ip IN($listIp)");
            $sqlGet->execute();
            $sqlGet->close();
        } else {
            echo "No touchpoints to delete";
        }
    }

    public function getNewCleanerTouchpoint($mysqli, $cleanerTouchpoint, $limit) {
        $sqlGet = $mysqli->prepare("SELECT MAX(id) FROM (SELECT id FROM touchpoints WHERE  id>= '$cleanerTouchpoint' LIMIT $limit)q");
        $sqlGet->execute();
        $newCleanerTouchpoint = null;
        $sqlGet->bind_result($newCleanerTouchpoint);
        $sqlGet->fetch();
        $sqlGet->close();
        return $newCleanerTouchpoint;
    }

    public function checkIfExist($mysqli, &$touchpointId){
        $sqlGet = $mysqli->prepare("SELECT id FROM touchpoints WHERE id>=$touchpointId LIMIT 1");
        $sqlGet->execute();
        $sqlGet->bind_result($touchpointId);
        $sqlGet->fetch();
        $sqlGet->close();
    }

}

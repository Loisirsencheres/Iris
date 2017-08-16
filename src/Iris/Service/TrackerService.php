<?php

namespace Iris\Service;

class TrackerService {

    public function __construct() {

    }

    public function startSession($mysqli, $post) {
        $campaign_id = 0;
        if (!empty($post['landingPage'])) {
            $post['value'] = ($post['landingPage'] == "/user/logout") ? "out" : "in";
            $lPage = $this->getLandingPage($post['landingPage']);
        } else {
            $lPage = "";
        }
        if (!empty($post['t_utm_source']) || !empty($post['t_utm_campaign']) || !empty($post['t_utm_medium']) || !empty($post['t_godfatherId']) || !empty($post['t_gclid']) || !empty($lPage)) {
            $campaign_id = $this->insertCampaign($mysqli, $post, $campaign_id, $lPage);
        }

        $touchpoint_id = $this->insertTouchPoint($mysqli, $post, $campaign_id);
        return $touchpoint_id;
    }

    public function event($mysqli, $post) {
        $touchpoint_id = $this->insertTouchPoint($mysqli, $post, 0);
        return $touchpoint_id;
    }

    /**
     * 
     * @param string $allLandingPage
     * @return string
     */
    private function getLandingPage($allLandingPage) {
        $lPage = null;
        if (strpos($allLandingPage, "hunkemoller") != false) {
            $lPage = "hunkemoller";
        }
        if (strpos($allLandingPage, "bonjour") != false) {
            $lPage = "bonjour";
        }
        if (strpos($allLandingPage, "sudouest") != false) {
            $lPage = "sudouest";
        }
        if (strpos($allLandingPage, "semaine-digitale") != false) {
            $lPage = "semaine-digitale";
        }
        return $lPage;
    }

    /**
     * 
     * @param mysqli $mysqli
     * @param array[] $post
     * @param int $campaign_id
     * @param string $lPage
     * @return int
     */
    private function insertCampaign($mysqli, $post, $campaign_id, $lPage) {
        if (!empty($post['t_utm_source']) || !empty($post['t_utm_campaign']) || !empty($post['t_utm_medium'])) {
            $source = "utm";
        }
        if (!empty($post['t_godfatherId'])) {
            $source = "gfid";
        }
        if (!empty($post['t_gclid'])) {
            $source = "gclid";
        }
        if (!empty($lPage) && empty($source)) {
            $source = "landingPage";
        }
        $params = [];
        switch ($source) {
            case "utm":
                $params['t_utm_source'] = (!empty($post['t_utm_source'])) ? $post['t_utm_source'] : "null";
                $params['t_utm_campaign'] = (!empty($post['t_utm_campaign'])) ? $post['t_utm_campaign'] : "null";
                $params['t_utm_medium'] = (!empty($post['t_utm_medium'])) ? $post['t_utm_medium'] : "null";
                $this->isNewsLetter($params);
                $sqlGet = $mysqli->prepare("SELECT id FROM `campaign` WHERE utm_source = ? AND utm_campaign = ? AND utm_medium = ?");
                $sqlGet->bind_param('sss', $params['t_utm_source'], $params['t_utm_campaign'], $params['t_utm_medium']);
                break;

            case "gfid":
                $params['t_godfatherId'] = $post['t_godfatherId'];
                $sqlGet = $mysqli->prepare("SELECT id FROM `campaign` WHERE gfid = ?");
                $sqlGet->bind_param('d', $params['t_godfatherId']);
                break;

            case "gclid":
                $arr = [];
                if (!empty($post['landingPage'])) {
                    $arr = explode("?gclid=", $post['landingPage'], 2);
                }
                $sqlGet = $mysqli->prepare("SELECT id FROM `campaign` WHERE gclid = ?");
                $sqlGet->bind_param('s', $arr[0]);
                break;

            case "landingPage":
                $sqlGet = $mysqli->prepare("SELECT id FROM `campaign` WHERE landingPage = ?");
                $sqlGet->bind_param('s', $lPage);
                break;

            default;
        }
        $sqlGet->execute();
        $sqlGet->bind_result($campaign_id);
        if (!$sqlGet->fetch()) {
            switch ($source) {
                case "utm":
                    if (!isset($params['name'])) {
                        $params['name'] = $post['t_utm_source'] . " : " . $post['t_utm_campaign'];
                    }
                    $sqlSet = $mysqli->prepare("INSERT INTO `campaign` (`name`,`utm_source`,`utm_campaign`,`utm_medium`) VALUES (?,?,?,?)");
                    $sqlSet->bind_param('ssss', $params['name'], $params['t_utm_source'], $params['t_utm_campaign'], $params['t_utm_medium']);
                    break;

                case "gfid":
                    $params['name'] = "GodFather";
                    $sqlSet = $mysqli->prepare("INSERT INTO `campaign` (`name`,`gfid`) VALUES (?,?)");
                    $sqlSet->bind_param('sd', $params['name'], $params['t_godfatherId']);
                    break;

                case "gclid":
                    $arr = [];
                    $params['name'] = "Google Adwords";
                    if (!empty($post['landingPage'])) {
                        $arr = explode("?gclid=", $post['landingPage'], 2);
                    } else {
                        $arr[0] = "";
                    }
                    $sqlSet = $mysqli->prepare("INSERT INTO `campaign` (`name`,`gclid`) VALUES (?,?)");
                    $sqlSet->bind_param('ss', $params['name'], $arr[0]);
                    break;

                case "landingPage":
                    $params['name'] = "Landing Page";
                    $sqlSet = $mysqli->prepare("INSERT INTO `campaign` (`name`,`landingPage`) VALUES (?,?)");
                    $sqlSet->bind_param('ss', $params['name'], $lPage);
                    break;

                default;
            }
            $sqlSet->execute();
            $campaign_id = $mysqli->insert_id;
            $sqlSet->close();
        }
        $sqlGet->close();
        return $campaign_id;
    }

    /**
     * 
     * @param mysqli $mysqli
     * @param array[] $post
     * @param int $campaign_id
     * @return int
     */
    private function insertTouchPoint($mysqli, $post, $campaign_id) {
        $touchpoint = [
            'created' => "",
            'track_id' => "",
            'device_id' => "",
            'user_id' => "",
            'type' => "",
            'value' => "",
            'campaign_id' => "",
            'landingReferer' => "",
            'landingPage' => "",
            'clientId' => "",
            'agent' => "",
            'ip' => "",
            'transaction_id' => "",
        ];
        foreach ($touchpoint AS $key => $value) {
            $touchpoint[$key] = (!empty($post[$key])) ? $post[$key] : "";
        }
        $touchpoint['value'] = (!empty($post['value'])) ? $post['value'] : "Web";
        $touchpoint['campaign_id'] = $campaign_id;
        if (!empty($post['touchpoint'])) {
            $touchpoint['value'] = ($touchpoint['value'] == "Web" && $post['touchpoint'] == "payment") ? 0 : $touchpoint['value'];
            if ($post['touchpoint'] != "startSession") {
                $touchpoint['type'] = $post['touchpoint'];
            } else {
                $touchpoint['type'] = "session";
            }
        } else {
            $touchpoint['type'] = "unknow";
        }
        $stringSQL = "";
        foreach ($touchpoint AS $key => $value) {
            $stringSQL.='`' . $key . '`,';
        }
        $stringSQL = substr($stringSQL, 0, -1);
        $sql = $mysqli->prepare("INSERT INTO `touchpoints` ($stringSQL) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sql->bind_param('sssssssssssss', $touchpoint['created'], $touchpoint['track_id'], $touchpoint['device_id'], $touchpoint['user_id'], $touchpoint['type'], $touchpoint['value'], $touchpoint['campaign_id'], $touchpoint['landingReferer'], $touchpoint['landingPage'], $touchpoint['clientId'], $touchpoint['agent'], $touchpoint['ip'], $touchpoint['transaction_id']);
        $sql->execute();
        $touchpoint_id = $mysqli->insert_id;
        $sql->close();
        return $touchpoint_id;
    }

    private function isNewsLetter(&$params) {
        if (strpos($params['t_utm_campaign'], "news") !== false && $params['t_utm_medium'] == "e-mail") {
            $params['t_utm_source'] = "";
            $params['name'] = "Newsletter " . substr($params['t_utm_campaign'], 5);
        }
    }

    public function isRobot($landingPage, $userAgent, $ip) {
        $robot = $this->onIP($ip);
        if (!$robot) {
            $robot = $this->onUserAgent($userAgent);
        }
        if (!$robot && (strpos($ip, "89") == 0 || strpos($ip, "185") == 0 || strpos($ip, "82") == 0 || strpos($ip, "94") == 0 || strpos($ip, "66") == 0)) {
            $robot = $this->isCriteoBot($ip, $landingPage);
        }
        if ($robot) {
            return true;
        } else {
            return false;
        }
    }

    private function onIP($ip) {
        $robotIP = array(
            "94.23.217.48"
        );
        foreach ($robotIP AS $r) {
            if ($ip == $r) {
                return true;
            }
        }
        return false;
    }

    private function onUserAgent($userAgent) {
        $robotUserAgent = array(
            "Pingdom"
            , "NewRelicPinger"
            , "Baiduspider"
            , "MegaIndex"
            , "Yahoo! Slurp"
            , "facebookexternalhit"
            , "Plukkie"
            , "DeuSu"
            , "WinHTTP"
            , "Python-urllib"
            , "bot"
            , "Go-http-client"
        );
        foreach ($robotUserAgent AS $r) {
            if (strpos($userAgent, $r) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isCriteoBot($ip, $landingPage) {
        $crietoIP = array(
            "89.36."
            , "89.38."
            , "89.40."
            , "185.35."
            , "82.241."
            , "94.177.240."
            , "66.249.91."
        );
        foreach ($crietoIP AS $c) {
            if (strpos($ip, $c) !== false) {
                if (strpos($landingPage, "criteo") !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getLastCallBack(\mysqli $mysqli) {
        $sqlGet = $mysqli->prepare("SELECT value FROM toolBox t WHERE t._key = 'lastCallBack'");
        $sqlGet->execute();
        $lastCallBack=null;
        $sqlGet->bind_result($lastCallBack);
        $sqlGet->fetch();
        $sqlGet->close();
        return $lastCallBack;
    }

    public function setLastCallBack(\mysqli $mysqli, $lastCallBack) {
        $sqlSet = $mysqli->prepare("UPDATE tracking.toolBox t SET value='$lastCallBack' WHERE t._key = 'lastCallBack'");
        $sqlSet->execute();
        $sqlSet->close();
    }

    public function getCallBacks(\mysqli $mysqliSSL, $lastCallBack) {
        $query = "USE stuff;";
        $query .= "SET max_heap_table_size = 1024 * 1024 * 1024 * 1;";
        $query .= "SET sort_buffer_size = 1024 * 1024 * 1024 * 1;";
        $query .= "SET read_rnd_buffer_size = 1024 * 1024 * 1024 * 1;";
        $query .= "tmp_table_size = 1024 * 1024 * 1024 * 1";

        if ($mysqliSSL->multi_query($query)) {
            do {
                /* Stockage du premier résultat */
                if ($result = $mysqliSSL->store_result()) {
                    while ($row = $result->fetch_row()) {
                    }
                    $result->free();
                }
                if ($mysqliSSL->more_results()) {
                }
            } while ($mysqliSSL->next_result());
        }
        $tempoTable = "
            CREATE TEMPORARY TABLE device_tracker (INDEX(did)) ENGINE=MEMORY
            SELECT 
                    IF(gpsid IS NOT NULL,gpsid, deviceid) AS did
                    ,d.id
            FROM ass_api.devices d;
        ";
        if (!$mysqliSSL->query($tempoTable)) {
            printf("Message d'erreur : %s\n", $mysqliSSL->error);
        }

        $mainQuery = "
            SELECT
            'startSession' AS type
            ,q1.*
            ,ud.user_id AS user_id
            ,'mobile' AS clientId
            FROM(
                SELECT 
                    aj.id
                    ,aj.created
                    ,IF(gps_adid != '', gps_adid, idfv) AS device_id
                    ,aj.`tracker_name` AS t_utm_source
                    ,aj.`network_name` AS t_utm_medium
                    ,aj.`campaign_name` AS t_utm_campaign
                    ,aj.event AS value
                    FROM webservice.adjust_callbacks aj
                WHERE aj.id>$lastCallBack
                )q1
            JOIN stuff.device_tracker d ON d.did=q1.device_id
            LEFT JOIN ass_api.user_devices ud ON ud.device_id=d.id
            ORDER BY created ASC
            LIMIT 100
        ";
        if (!$mysqliSSL->query($mainQuery)) {
            printf("Message d'erreur : %s\n", $mysqliSSL->error);
        }
        $sqlGet = $mysqliSSL->prepare($mainQuery);
        $sqlGet->execute();
        $callBacks = [];
        $touchpoint = $id = $created = $device_id = $t_utm_source = $t_utm_medium = $t_utm_campaign = $value = $user_id = $clientId = null;
        $sqlGet->bind_result($touchpoint, $id, $created, $device_id, $t_utm_source, $t_utm_medium, $t_utm_campaign, $value, $user_id, $clientId);
        while ($sqlGet->fetch()) {
            $callBacks[$id] = array(
                "created" => $created,
                "user_id" => $user_id,
                "device_id" => $device_id,
                "touchpoint" => $touchpoint,
                "t_utm_source" => $t_utm_source,
                "t_utm_medium" => $t_utm_medium,
                "t_utm_campaign" => $t_utm_campaign,
                "value" => $value,
                "clientId" => $clientId,
                "transaction_id" => $id,
            );
        }
        $sqlGet->close();
        return $callBacks;
    }

    public function getCountOnly(\mysqli $mysqliSSL, $lastCallBack) {

        $sqlGet = $mysqliSSL->prepare("
                SELECT COUNT(aj.id)
                FROM webservice.adjust_callbacks aj
                WHERE aj.id>$lastCallBack
        ");
        $sqlGet->execute();
        $coutnOnly = null;
        $sqlGet->bind_result($coutnOnly);
        $sqlGet->fetch();
        $sqlGet->close();
        return $coutnOnly;
    }

}

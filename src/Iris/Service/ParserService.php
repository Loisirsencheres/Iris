<?php

namespace Iris\Service;

class ParserService {

    private $usersToDelete;
    private $Users;
    private $touchpoints;

    public function __construct() {
        $this->usersToDelete = "";
        $this->Users = [];
        $this->touchpoints = [];
    }

    public function getParserTouchpoint(\mysqli $mysqli) {
        $this->usersToDelete = "";
        $this->Users = [];
        $this->touchpoints = [];
        $sqlGet = $mysqli->prepare("SELECT value FROM toolBox t WHERE t._key = 'parserTouchpoint'");
        $sqlGet->execute();
        $parserTouchpoint = null;
        $sqlGet->bind_result($parserTouchpoint);
        $sqlGet->fetch();
        $sqlGet->close();
        return $parserTouchpoint;
    }

    public function getCleanerTouchpoint(\mysqli $mysqli) {
        $sqlGet = $mysqli->prepare("SELECT value FROM toolBox t WHERE t._key = 'cleanerTouchpoint'");
        $sqlGet->execute();
        $cleanerTouchpoint = null;
        $sqlGet->bind_result($cleanerTouchpoint);
        $sqlGet->fetch();
        $sqlGet->close();
        return $cleanerTouchpoint;
    }

    public function getLastTouchpoint(\mysqli $mysqli) {
        $sqlGet = $mysqli->prepare("SELECT id FROM touchpoints ORDER BY id DESC LIMIT 1");
        $sqlGet->execute();
        $lastTouchpoint = null;
        $sqlGet->bind_result($lastTouchpoint);
        $sqlGet->fetch();
        $sqlGet->close();
        return $lastTouchpoint;
    }

    public function setParserTouchpoint(\mysqli $mysqli, $last, $time) {
        $sqlSet = $mysqli->prepare("UPDATE toolBox t SET value = ? WHERE t._key='parserTouchpoint'");
        $sqlSet->bind_param('s', $last['id']);
        $sqlSet->execute();
        $sqlSet->close();
        $sqlSet2 = $mysqli->prepare("INSERT INTO toolBox (_key,value) VALUES ('clock', ?)");
        $sqlSet2->bind_param('s', $time);
        $sqlSet2->execute();
        $sqlSet2->close();
    }

    public function setCleanerTouchpoint(\mysqli $mysqli, $cleanerTouchpoint) {
        $sqlSet = $mysqli->prepare("UPDATE toolBox t SET value = ? WHERE t._key='cleanerTouchpoint'");
        $sqlSet->bind_param('s', $cleanerTouchpoint);
        $sqlSet->execute();
        $sqlSet->close();
    }

    function getListTouchpoint(\mysqli $mysqli, $start, $stop, $limit) {
        $params['start'] = $start;
        $params['stop'] = $stop;
        $sqlGet = $mysqli->prepare("SELECT id, track_id, device_id, user_id, type, value, campaign_id, created, clientId, agent, ip, transaction_id FROM touchpoints t WHERE t.id > ? AND t.id <= ? ORDER BY id ASC LIMIT ?");
        $sqlGet->bind_param('sss', $params['start'], $params['stop'], $limit);
        $sqlGet->execute();
        $id = $track_id = $device_id = $user_id = $type = $value = $campaign_id = $created = $clientId = $agent = $ip = $transaction_id = null;
        $sqlGet->bind_result($id, $track_id, $device_id, $user_id, $type, $value, $campaign_id, $created, $clientId, $agent, $ip, $transaction_id);

        while ($sqlGet->fetch()) {
            $this->touchpoints[$id] = array(
                "id" => $id
                , "track_id" => $track_id
                , "device_id" => $device_id
                , "user_id" => $user_id
                , "type" => $type
                , "value" => $value
                , "campaign_id" => $campaign_id
                , "created" => $created
                , "clientId" => $clientId
                , "agent" => $agent
                , "ip" => $ip
                , "transaction_id" => $transaction_id
            );
        }
        $sqlGet->close();
        if (count($this->touchpoints) != 0) {
            return max($this->touchpoints);
        } else {
            return false;
        }
    }

    function purgeListTouchpoint() {
        foreach (array_reverse($this->touchpoints, true) as $key => $touchpoint) {
            if (isset($this->touchpoints[$key - 1])) {
                if (
                        $touchpoint['ip'] == $this->touchpoints[$key - 1]['ip'] && (strtotime($touchpoint['created']) - strtotime($this->touchpoints[$key - 1]['created']) <= 10) && $touchpoint['type'] == "session" && $touchpoint['agent'] == $this->touchpoints[$key - 1]['agent']
                ) {
                    unset($this->touchpoints[$key]);
                }
            }
        }
        return $this->touchpoints;
    }

    public function deleteUsers(\mysqli $mysqli) {
        if ($this->usersToDelete != "") {
            $this->usersToDelete = substr($this->usersToDelete, 0, -1);
            $sqlDelete = $mysqli->prepare("DELETE FROM t_Users WHERE id IN(" . $this->usersToDelete . ")");
            $sqlDelete->execute();
            $sqlDelete->close();
        }
    }

    /**
     * Get the list of the users still in base thanks the list of touchpoint
     * 
     * @param mysqli $mysqli
     * @param type $touchpointList
     * @return type
     */
    public function getExistingT_User(\mysqli $mysqli) {
        $listDevice_id = $listTrack_id = $listUser_id = "";
        foreach ($this->touchpoints AS $t) {
            if ($t["track_id"] != "") {
                $listTrack_id.="'" . $t["track_id"] . "',";
            }
            if ($t["user_id"] != 0) {
                $listUser_id.="'" . $t["user_id"] . "',";
            }
            if ($t["device_id"] != "") {
                $listDevice_id.="'" . $t["device_id"] . "',";
            }
        }
        $listTrack_id = substr($listTrack_id, 0, -1);
        $listUser_id = substr($listUser_id, 0, -1);
        $listDevice_id = substr($listDevice_id, 0, -1);
        if ($listTrack_id == "") {
            $listTrack_id = -1;
        }
        if ($listUser_id == "") {
            $listUser_id = -1;
        }
        if ($listDevice_id == "") {
            $listDevice_id = -1;
        }
        $sqlGet = $mysqli->prepare("
        SELECT 
            id
            ,user_id
            , first_track_id
            , last_track_id
            , first_device_id
            , last_device_id
            , nb_campaign
            , nb_touchpoint
            , first_update
            , last_update 
        FROM t_Users 
        WHERE t_Users.last_track_id IN(" . $listTrack_id . ") OR t_Users.user_id IN(" . $listUser_id . ") OR t_Users.last_device_id IN(" . $listDevice_id . ")
        GROUP BY id
    ");
        $sqlGet->execute();
        $id = $user_id = $first_track_id = $last_track_id = $first_device_id = $last_device_id = $nb_campaign = $nb_touchpoint = $last_update = $first_update = null;
        $sqlGet->bind_result($id, $user_id, $first_track_id, $last_track_id, $first_device_id, $last_device_id, $nb_campaign, $nb_touchpoint, $first_update, $last_update);
        $inc = 0;
        while ($sqlGet->fetch()) {
            $this->Users[$inc] = array(
                "id" => $id
                , "user_id" => $user_id
                , "first_track_id" => $first_track_id
                , "last_track_id" => $last_track_id
                , "first_device_id" => $first_device_id
                , "last_device_id" => $last_device_id
                , "nb_campaign" => $nb_campaign
                , "nb_touchpoint" => $nb_touchpoint
                , "first_update" => $first_update
                , "last_update" => $last_update
                , "events" => null
            );
            $inc++;
        }
        $sqlGet->close();
        if (count($this->Users) != 0) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * Get the list of events for the existing users
     */

    public function getOldEvents(\mysqli $mysqli) {
        $list = "";
        foreach ($this->Users AS $user) {
            if ($user["id"] != "") {
                $list.="'" . $user["id"] . "',";
            }
        }
        $listT_User_id = substr($list, 0, -1);
        $sqlGet = $mysqli->prepare(
                "        SELECT id, 'session',  t_User_id, created, rang, `value`,  campaign_id , clientId, transaction_id FROM t_Sessions  WHERE t_User_id IN($listT_User_id)"
                . "UNION SELECT id, 'register', t_User_id, created, rang, type,   '0'         , clientId, transaction_id FROM t_Registers WHERE t_User_id IN($listT_User_id)"
                . "UNION SELECT id, 'login',    t_User_id, created, rang, type, '0'         , clientId, transaction_id FROM t_Logins    WHERE t_User_id IN($listT_User_id)"
                . "UNION SELECT id, 'bid',      t_User_id, created, rang, amount, '0'         , clientId, transaction_id FROM t_Bids      WHERE t_User_id IN($listT_User_id)"
                . "UNION SELECT id, 'win',      t_User_id, created, rang, amount, '0'         , clientId, transaction_id FROM t_Wins      WHERE t_User_id IN($listT_User_id)"
                . "UNION SELECT id, 'payment',  t_User_id, created, rang, amount, '0'         , clientId, transaction_id FROM t_Payments  WHERE t_User_id IN($listT_User_id)"
        );
        $sqlGet->execute();
        $id = $type = $t_User_id = $created = $rang = $value = $campaign_id = $clientId = $transaction_id = null;
        $sqlGet->bind_result($id, $type, $t_User_id, $created, $rang, $value, $campaign_id, $clientId, $transaction_id);

        while ($sqlGet->fetch()) {
            $listOldEvents[$t_User_id . "/" . $id . $type] = array(
                "id" => $id
                , "t_User_id" => $t_User_id
                , "created" => $created
                , "rang" => $rang
                , "type" => $type
                , "value" => $value
                , "campaign_id" => $campaign_id
                , "clientId" => $clientId
                , "transaction_id" => $transaction_id
                , "toUpdate" => false
            );
        }
        $sqlGet->close();
        if (isset($listOldEvents)) {
            foreach ($listOldEvents as $event) {
                foreach ($this->Users as $key => $user) {
                    if ($user['id'] == $event['t_User_id']) {
                        $this->Users[$key]["events"][$event["rang"]] = $event;
                    }
                }
            }
        }
//    echo"\n\n----------------------------------------------------------------------------------------------\n\n";
    }

    /*
     * Work on touchpoint
     * Dispatch new informations 
     */

    public function parseTouchpoint() {
//    echo "------------------------- Parsing touchpoints-----------------------------------</br>";
        foreach ($this->touchpoints AS $touchpoint) {
            $selectedOldUser = null;
//        echo "\n-------Processing of ".$touchpoint['id']." : ".$touchpoint['track_id']."-------\n";
            // look if we have at less one user in base
            if (isset($this->Users)) {

                //case track_id and user_id are defined
                if ($touchpoint['track_id'] != "" && $touchpoint['user_id'] != 0) {
                    foreach (array_reverse($this->Users, true) AS $key => $oldUser) {
                        //if the user has a track_id
                        if ($oldUser['last_track_id'] != "") {
                            //And if is equal to the track_id of the touchpoint
                            if ($oldUser['last_track_id'] == $touchpoint['track_id']) {
                                //And if user_id is not defined
                                if ($oldUser['user_id'] == 0) {
                                    //So the touchppoint go to THIS User
                                    $selectedOldUser = $key;
                                    break;
                                }
                                //Else if it's the same user_id
                                elseif ($oldUser['user_id'] == $touchpoint['user_id']) {
                                    //So the touchppoint go to THIS User
                                    $selectedOldUser = $key;
                                    break;
                                }
                            }
                        }
                    }
                }

                //case device_id and user_id are defined
                if ($touchpoint['device_id'] != "" && $touchpoint['user_id'] != 0) {
                    foreach (array_reverse($this->Users, true) AS $key => $oldUser) {
                        if ($oldUser['last_device_id'] != "") {
                            if ($oldUser['last_device_id'] == $touchpoint['device_id']) {
                                if ($oldUser['user_id'] == 0) {
                                    $selectedOldUser = $key;
                                    break;
                                } elseif ($oldUser['user_id'] == $touchpoint['user_id']) {
                                    $selectedOldUser = $key;
                                    break;
                                }
                            }
                        }
                    }
                }

                //case track_id is defined but user_id isn't
                if ($touchpoint['track_id'] != "" && $touchpoint['user_id'] == 0) {
                    foreach (array_reverse($this->Users, true) AS $key => $oldUser) {
                        if ($oldUser['last_track_id'] != "") {
                            if ($oldUser['last_track_id'] == $touchpoint['track_id']) {
                                if ($oldUser['user_id'] == 0) {
                                    $selectedOldUser = $key;
                                    break;
                                }
                            }
                        }
                    }
                }

                //case device_id is defined but user_id isn't
                if ($touchpoint['device_id'] != "" && $touchpoint['user_id'] == 0) {
                    foreach (array_reverse($this->Users, true) AS $key => $oldUser) {
                        if ($oldUser['last_device_id'] != "") {
                            if ($oldUser['last_device_id'] == $touchpoint['device_id']) {
                                if ($oldUser['user_id'] == 0) {
                                    $selectedOldUser = $key;
                                    break;
                                }
                            }
                        }
                    }
                }

                //case user_id is defined but track_id and device_id aren't
                if ($touchpoint['track_id'] == "" && $touchpoint['device_id'] == "" && $touchpoint['user_id'] != 0) {
                    foreach (array_reverse($this->Users, true) AS $key => $oldUser) {
                        if ($oldUser['user_id'] != 0) {
                            if ($oldUser['user_id'] == $touchpoint['user_id']) {
                                $selectedOldUser = $key;
                                break;
                            }
                        }
                    }
                }

                if (isset($selectedOldUser)) {
                    $this->updateOldUser($selectedOldUser, $touchpoint);
                } else {
                    $this->createNewUser($touchpoint);
                }
            } else {
                $this->createNewUser($touchpoint);
            }
        }
    }

    public function createNewUser($touchpoint) {
        $inc = count($this->Users);
        $this->Users[$inc]['user_id'] = $touchpoint['user_id'];
        $this->Users[$inc]['last_track_id'] = $this->Users[$inc]['first_track_id'] = $touchpoint['track_id'];
        $this->Users[$inc]['last_device_id'] = $this->Users[$inc]['first_device_id'] = $touchpoint['device_id'];
        $this->Users[$inc]['nb_campaign'] = ($touchpoint['type'] == "session") ? 1 : 0;
        $this->Users[$inc]['nb_touchpoint'] = 1;
        $this->Users[$inc]['last_update'] = $this->Users[$inc]['first_update'] = $touchpoint['created'];

        $this->Users[$inc]['events'][1] = array(
            "t_User_id" => $inc
            , "created" => $touchpoint['created']
            , "rang" => 1
            , "type" => $touchpoint['type']
            , "value" => $touchpoint['value']
            , "clientId" => $touchpoint['clientId']
            , "transaction_id" => $touchpoint['transaction_id']
            , "campaign_id" => $touchpoint['campaign_id']
            , "toUpdate" => true
        );
    }

    public function updateOldUser($inc, $touchpoint) {
        if (isset($this->Users[$inc]['events'])) {
            $arrayRang = $this->findRang($this->Users[$inc], $touchpoint);
        } else {
            $arrayRang["rang"] = 1;
        }

        if ($arrayRang["rang"] > 1) {
            $duplica = $this->detectDuplica($this->Users[$inc], $touchpoint, $arrayRang);
            if ($duplica) {
                return;
            }
        }
        if ($arrayRang["rang"] <= $this->Users[$inc]['nb_touchpoint']) {
            $this->Users[$inc] = $this->updateGreaterRang($arrayRang['rang'], $this->Users[$inc]);
        }

        if ($touchpoint['user_id'] != 0) {
            $this->Users[$inc]['user_id'] = $touchpoint['user_id'];
        }

        if ($this->Users[$inc]['last_track_id'] == "") {
            $this->Users[$inc]['last_track_id'] = $touchpoint['track_id'];
        }

        if ($this->Users[$inc]['last_device_id'] == "") {
            $this->Users[$inc]['last_device_id'] = $touchpoint['device_id'];
        }

        if ($touchpoint['type'] == "session") {
            $this->Users[$inc]['nb_campaign'] ++;
        }

        $this->Users[$inc]['nb_touchpoint'] ++;

        if (strtotime($touchpoint['created']) > strtotime($this->Users[$inc]['last_update'])) {
            $this->Users[$inc]['last_update'] = $touchpoint['created'];
            if ($touchpoint['track_id'] != "") {
                $this->Users[$inc]['last_track_id'] = $touchpoint['track_id'];
            }
            if ($touchpoint['device_id'] != "") {
                $this->Users[$inc]['last_device_id'] = $touchpoint['device_id'];
            }
        }

        if (strtotime($touchpoint['created']) < strtotime($this->Users[$inc]['first_update'])) {
            $this->Users[$inc]['first_update'] = $touchpoint['created'];
            if ($touchpoint['track_id'] != "") {
                $this->Users[$inc]['first_track_id'] = $touchpoint['track_id'];
            }
            if ($touchpoint['device_id'] != "") {
                $this->Users[$inc]['first_device_id'] = $touchpoint['device_id'];
            }
        }

        $this->Users[$inc]['events'][$arrayRang['rang']] = array(
            "t_User_id" => $inc
            , "created" => $touchpoint['created']
            , "rang" => $arrayRang['rang']
            , "type" => $touchpoint['type']
            , "value" => $touchpoint['value']
            , "clientId" => $touchpoint['clientId']
            , "transaction_id" => $touchpoint['transaction_id']
            , "campaign_id" => $touchpoint['campaign_id']
            , "toUpdate" => true
        );
    }

    public function updateGreaterRang($rang, $user) {
        for ($i = $user['nb_touchpoint']; $i >= $rang; $i--) {
            $user['events'][$i + 1] = $user['events'][$i];
            $user['events'][$i + 1]['rang'] = $i + 1;
            $user['events'][$i + 1]['toUpdate'] = true;
        }
        return $user;
    }

    public function detectDuplica($user, $touchpoint, $arrayRang) {
        $rang = $arrayRang['rang'];
        $gapDuration = $arrayRang['gapDuration'];
        if ($touchpoint['type'] != 'session' && $touchpoint['type'] != 'login') {
            return false;
        } elseif ($touchpoint['type'] == 'session') {
            if ($touchpoint['campaign_id'] != $user['events'][$rang - 1]["campaign_id"]) {
                if ($touchpoint['campaign_id'] != 0 && $user['events'][$rang - 1]["campaign_id"] != 0) {
                    return false;
                }
                if ($touchpoint['campaign_id'] == 0 && $gapDuration < 60 * 60) {
                    return true;
                } else {
                    return false;
                }
            } elseif ($gapDuration < 60 * 60) {
                return true;
            } else {
                return false;
            }
            return false;
        } else {
            if ($user['events'][$rang - 1]["type"] == "login") {
                return true;
            }
        }
    }

    public function findRang($user, $touchpoint) {
        $created = strtotime($touchpoint['created']);
        for ($i = max(array_keys($user['events'])); $i > 0; $i--) {
            $dateRang = strtotime($user['events'][$i]['created']);
            $gapDuration = $created - $dateRang;
            if ($gapDuration >= 0) {
                $return['rang'] = $i + 1;
                $return['gapDuration'] = $gapDuration;
                return $return;
            }
        }
        $return['rang'] = $i + 1;
        $return['gapDuration'] = 0;
        return $return;
    }

    public function sessionUnification() {
        $break = false;
        $tab2 = $tab1 = array_reverse($this->Users, true);
        foreach ($tab1 AS $key => $T_user) {
            foreach ($tab2 AS $keyBis => $T_userBis) {
                if ($keyBis != $key) {
                    if (($T_user['first_track_id'] == $T_userBis['last_track_id'] && $T_user['user_id'] == $T_userBis['user_id']) && $T_user['first_track_id'] != "" || ($T_user['user_id'] == $T_userBis['user_id'] && $T_user['user_id'] != 0)) {
                        $this->Users[$keyBis] = $this->fusionUser($T_user, $T_userBis);
                        if (isset($this->Users[$key]['id'])) {
                            $this->usersToDelete.=$this->Users[$key]['id'] . ",";
                        }
                        unset($this->Users[$key]);
                        $break = true;
                        break(2);
                    }
                    if (($T_user['first_device_id'] == $T_userBis['last_device_id'] && $T_user['user_id'] == $T_userBis['user_id']) && $T_user['first_device_id'] != "" || ($T_user['user_id'] == $T_userBis['user_id'] && $T_user['user_id'] != 0)) {
                        $this->Users[$keyBis] = $this->fusionUser($T_user, $T_userBis);
                        if (isset($this->Users[$key]['id'])) {
                            $this->usersToDelete.=$this->Users[$key]['id'] . ",";
                        }
                        unset($this->Users[$key]);
                        $break = true;
                        break(2);
                    }
                }
            }
        }
        if ($break) {
            $this->sessionUnification();
        }
    }

    public function fusionUser($add, $root) {
        foreach ($add['events'] AS $key => $event) {
            $arrayRang = $this->findRang($root, $event);
            if ($arrayRang["rang"] > 1) {
                if ($this->detectDuplica($root, $event, $arrayRang)) {
                    continue;
                }
            }
            $root = $this->updateGreaterRang($arrayRang['rang'], $root);
            $root['events'][$arrayRang['rang']] = $event;
            $root['events'][$arrayRang['rang']]['rang'] = $arrayRang['rang'];
            $root['events'][$arrayRang['rang']]['toUpdate'] = true;
            $root["nb_touchpoint"] ++;
            if ($event['type'] == "session") {
                $root["nb_campaign"] ++;
            }
            if (strtotime($event['created']) > strtotime($root['last_update'])) {
                $root['last_update'] = $event['created'];
                $root['last_track_id'] = ($add['last_track_id'] != "") ? $add['last_track_id'] : $root['last_track_id'];
                $root['first_device_id'] = ($add['last_device_id'] != "" && $root['first_device_id'] == "") ? $root['last_device_id'] : $root['first_device_id'];
                $root['last_device_id'] = ($add['last_device_id'] != "") ? $add['last_device_id'] : $root['last_device_id'];
            }
            if (strtotime($event['created']) < strtotime($root['first_update'])) {
                $root['first_update'] = $event['created'];
                $root['first_track_id'] = ($add['first_track_id'] != "") ? $add['first_track_id'] : $root['first_track_id'];
                $root['first_device_id'] = ($add['first_device_id'] != "") ? $add['first_device_id'] : $root['first_device_id'];
            }
            if (strtotime($event['created']) < strtotime($root['last_update']) && strtotime($event['created']) > strtotime($root['first_update'])) {
                $root['first_device_id'] = ($add['first_device_id'] != "" && $root['first_device_id'] == "") ? $add['first_device_id'] : $root['first_device_id'];
                $root['last_device_id'] = ($add['last_device_id'] != "" && $root['last_device_id'] == "") ? $add['last_device_id'] : $root['last_device_id'];
            }
        }
        $root['first_device_id'] = ($root['first_device_id'] == "" && $root['last_device_id'] != "") ? $root['last_device_id'] : $root['first_device_id'];
        $root['last_device_id'] = ($root['last_device_id'] == "" && $root['first_device_id'] != "") ? $root['first_device_id'] : $root['last_device_id'];
        $root['first_track_id'] = ($root['first_track_id'] == "" && $root['last_track_id'] != "") ? $root['last_track_id'] : $root['first_track_id'];
        $root['last_track_id'] = ($root['last_track_id'] == "" && $root['first_track_id'] != "") ? $root['first_track_id'] : $root['last_track_id'];
        return $root;
    }

    public function insertInBase(\mysqli $mysqli) {
        $this->insertUser($mysqli);
        $this->insertEvents($mysqli);
    }

    public function insertUser(\mysqli $mysqli) {
        $now = new \DateTime();
        foreach ($this->Users as $key => $user) {
            if (isset($user['id'])) {
                $sqlUpdate = $mysqli->prepare("UPDATE t_Users SET user_id = ?, first_track_id = ?, last_track_id = ?, first_device_id = ?, last_device_id = ?, nb_campaign = ?, nb_touchpoint = ?, first_update = ?, last_update = ?, updated = ? WHERE id = ? ");
                $sqlUpdate->bind_param('sssssssssss', $user['user_id'], $user['first_track_id'], $user['last_track_id'], $user['first_device_id'], $user['last_device_id'], $user['nb_campaign'], $user['nb_touchpoint'], $user['first_update'], $user['last_update'], $now->format('Y-m-d H:i:s'), $user['id']);
                $sqlUpdate->execute();
                $sqlUpdate->close();
            } else {
                $sqlUpdate = $mysqli->prepare("INSERT INTO t_Users (user_id, first_track_id, last_track_id, first_device_id, last_device_id, nb_campaign, nb_touchpoint, first_update, last_update, updated) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $sqlUpdate->bind_param('ssssssssss', $user['user_id'], $user['first_track_id'], $user['last_track_id'], $user['first_device_id'], $user['last_device_id'], $user['nb_campaign'], $user['nb_touchpoint'], $user['first_update'], $user['last_update'], $now->format('Y-m-d H:i:s'));
                $sqlUpdate->execute();
                $this->Users[$key]['id'] = $sqlUpdate->insert_id;
                $sqlUpdate->close();
            }
        }
    }

    public function insertEvents(\mysqli $mysqli) {
        foreach ($this->Users as $key => $user) {
            if (isset($user['events'])) {
                foreach ($user['events'] as $keyEvent => $event) {
                    if ($event['toUpdate']) {
                        $event['t_User_id'] = $user['id'];
                        switch ($event['type']) {
                            case "session" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Sessions SET t_User_id = ?, created = ?, rang = ?, campaign_id = ?, `value` = ?, clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('ssssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['campaign_id'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Sessions (t_User_id, created, rang, campaign_id, `value`, clientId, transaction_id) VALUES (?,?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['campaign_id'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            case "register" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Registers SET t_User_id = ?, created = ?, rang = ?, type = ?, clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Registers (t_User_id, created, rang, type, clientId, transaction_id) VALUES (?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('ssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            case "login" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Logins SET t_User_id = ?, created = ?, rang = ?, type = ? , clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Logins (t_User_id, created, rang, type, clientId, transaction_id) VALUES (?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('ssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            case "bid" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Bids SET t_User_id = ?, created = ?, rang = ?, type = ?, amount = ?, clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('ssssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['type'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Bids (t_User_id, created, rang, type, amount, clientId, transaction_id) VALUES (?,?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['type'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            case "win" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Wins SET t_User_id = ?, created = ?, rang = ?, amount = ?, clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Wins (t_User_id, created, rang, amount, clientId, transaction_id) VALUES (?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('ssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            case "payment" :
                                if (isset($event['id'])) {
                                    $sqlUpdate = $mysqli->prepare("UPDATE t_Payments SET t_User_id = ?, created = ?, rang = ?, amount = ?, clientId = ?, transaction_id = ? WHERE id = ? ");
                                    $sqlUpdate->bind_param('sssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id'], $event['id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                } else {
                                    $sqlUpdate = $mysqli->prepare("INSERT INTO t_Payments (t_User_id, created, rang, amount, clientId, transaction_id) VALUES (?,?,?,?,?,?)");
                                    $sqlUpdate->bind_param('ssssss', $event['t_User_id'], $event['created'], $event['rang'], $event['value'], $event['clientId'], $event['transaction_id']);
                                    $sqlUpdate->execute();
                                    $sqlUpdate->close();
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }
    }

    public function getLock(\mysqli $mysqli) {
        $sqlGet = $mysqli->prepare("SELECT value FROM toolBox t WHERE t._key = 'lock'");
        $sqlGet->execute();
        $lock = null;
        $sqlGet->bind_result($lock);
        $sqlGet->fetch();
        $sqlGet->close();
        return $lock;
    }

    public function setLock(\mysqli $mysqli, $value) {
        $sqlSet = $mysqli->prepare("UPDATE toolBox t SET value='$value' WHERE t._key = 'lock'");
        $sqlSet->execute();
        $sqlSet->close();
    }

}

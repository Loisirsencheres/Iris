<?php

namespace Iris\Service;

class StatusService {

    private $usersToDelete;
    private $Users;
    private $touchpoints;

    public function __construct() {
        $this->usersToDelete = "";
        $this->Users = [];
        $this->touchpoints = [];
    }

    public function getLast_t_Update($mysql) {
        $query = "
            SELECT
                _key
                ,value
            FROM
            toolBox
            WHERE _key LIKE 'last_t_%'
                ";
        $sqlGet = $mysql->prepare($query);
        $sqlGet->execute();
        $status = [];
        $key = $value = null;
        $sqlGet->bind_result($key, $value);
        while ($sqlGet->fetch()) {
            $status[$key] = $value;
        }
        $mysql->close();
        return $status;
    }

}

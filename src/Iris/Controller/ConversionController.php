<?php

namespace Iris\Controller;

use Iris\Service\StatusService;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class ConversionController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/self', array($this, 'selfConversion'))->bind('conversion.self');
        return $controllers;
    }

    public function selfConversion(Request $request) {
        $result = $params = [];
        $params['groupby'] = "campaign_name";
        if ($request->get('dateRange')) {
            if ($request->get('dateRange') == "AllDate") {
                $params['AllDate'] = 1;
            } else {
                $explodeDate = explode(' - ', $request->get('dateRange'));
                $params['date']['start'] = $explodeDate[0];
                $params['date']['end'] = $explodeDate[1];
            }
        }
        if ($request->get('groupby')) {
            $params['groupby'] = $request->get('groupby');
        }

        $params['conversions'] = $request->query->get('conversion');
        $params['models'] = $request->query->get('model');
        $clock['init'] = microtime(true);

        if (isset($params['models']) && isset($params['conversions'])) {
            //Campaign list initialization
            $result = $this->initCampaign($params['groupby']);
            //Get the conversions
            foreach ($params['conversions'] as $conversion) {
                foreach ($params['models'] as $model) {
                    $this->addConversion($result, $conversion, $params, $model);
                }
            }
            //Deleting campaigns without conversions
            $this->cleanCampaign($result, $params['conversions'], $params['models']);
        }

        //Viewing the table
        return $this->generateTable($result, $params);
    }

    function initCampaign($groupBy) {
        $result = [];
        $query = ("
        SELECT
            c.id AS campaign_id
            ,c.name AS campaign_name
            ,c.utm_source
            ,c.utm_campaign
            ,c.utm_medium
        FROM campaign c
        GROUP BY $groupBy
        UNION SELECT 
            'mobile' AS campaign_id
            ,'mobile' AS campaign_name
            ,'mobile' AS utm_source
            ,'mobile' AS utm_campaign
            ,'mobile' AS utm_medium
        UNION SELECT 
            'Total' AS campaign_id
            ,'Total' AS campaign_name
            ,'Total' AS utm_source
            ,'Total' AS utm_campaign
            ,'Total' AS utm_medium
    ");

        $sqlGet = $this->app['mysqli']->prepare($query);
        $sqlGet->execute();
        $campaignId = $campaignName = $utmSource = $utmCampaign = $utmMedium = null;
        $sqlGet->bind_result($campaignId, $campaignName, $utmSource, $utmCampaign, $utmMedium);

        while ($sqlGet->fetch()) {
            $res = array(
                "campaign_id" => $campaignId,
                "campaign_name" => $campaignName,
                "utm_source" => $utmSource,
                "utm_campaign" => $utmCampaign,
                "utm_medium" => $utmMedium,
            );

            $result[strtolower($res[$groupBy])] = array(
                $groupBy => $res[$groupBy],
//            "Exemple campagne" => $campaignName,
            );
        }
        $sqlGet->close();
        return $result;
    }

    function addConversion(&$result, $conversion, $params, $model) {
        switch ($conversion) {
            case 'registers' :
                $tableName = "t_Registers";
                $tableMatName = "Registers";
                break;
            case 'bids' :
                $tableName = "t_Bids";
                $tableMatName = "Bids";
                break;
            case 'payments' :
                $tableName = "t_Payments";
                $tableMatName = "Payments";
                break;
            case 'ca' :
                $tableName = "t_Payments";
                $tableMatName = "CA";
                break;
            case 'wins' :
                $tableName = "t_Wins";
                $tableMatName = "Wins";
                break;
            case 'va' :
                $tableName = "t_Wins";
                $tableMatName = "VA";
                break;
            case 'logins' :
                $tableName = "t_Logins";
                $tableMatName = "Logins";
                break;
            default:
                $tableName = "t_Registers";
                $tableMatName = "Registers";
                break;
        }
        $where = "WHERE 1=1";
        if (isset($params['date'])) {
            $where.=" AND DATE(created) >= '" . $params['date']['start'] . "' AND DATE(created) <= '" . $params['date']['end'] . "'";
        }
        $query = ("
            SELECT
                " . $params['groupby'] . "
                ,sum(conversion) AS conv
            FROM mat_Self$tableMatName$model
            $where
            GROUP BY " . $params['groupby'] . "
            ORDER BY conv  DESC
        ");
//        if (!$this->app['mysqli']->query($query)) {
//            printf("Message d'erreur : %s\n", $this->app['mysqli']->error);
//        }
        $sqlGet = $this->app['mysqli']->prepare($query);
        $sqlGet->execute();
        $res = [];
        $sqlGet->bind_result($res[$params['groupby']], $res['conversion']);
        $conversion.=$model;
        while ($sqlGet->fetch()) {
            if ($conversion == "CA$model" || $conversion == "VA$model") {
                $result[strtolower($res[$params['groupby']])][$conversion] = round($res['conversion'] / 100);
            } else {
                $result[strtolower($res[$params['groupby']])][$conversion] = $res['conversion'];
            }
        }
        $sqlGet->close();
    }

    function cleanCampaign(&$result, $conv, $models) {
        foreach ($result as $key => &$campaign) {
            $unset = true;
            foreach ($models as $model) {
                foreach ($conv as $conversion) {
                    if (!isset($campaign[$conversion . $model])) {
                        $campaign[$conversion . $model] = 0;
                    }
                    if ($campaign[$conversion . $model] != 0) {
                        $unset = false;
                    }
                }
            }
            if ($unset) {
                unset($result[$key]);
            }
        }
        return $result;
    }

    /**
     * 
     * Array's format : 
     * key = name of the columns
     * Value = Value
     * 
     */
    function generateTable($result, $filter) {
        /** @var StatusService $ss */
        $ss = $this->app['service.status'];
        $status = $ss->getLast_t_Update($this->app['mysqli']);
        if (count($result) != 0) {
            reset($result);
            $first_key = key($result);
            $column = [];
            foreach ($result[$first_key] as $key => $value) {
                $column[$key] = $key;
            }
            $params = array(
                'results' => $result
                , 'columns' => $column
                , 'filters' => $filter
                , 'status' => $status
            );
            return $this->app['twig']->render('conversions/self.twig', $params);
        } else {
            $params = array(
                'filters' => $filter
                , 'status' => $status
            );
            return $this->app['twig']->render('conversions/self.twig', $params);
        }
    }

}

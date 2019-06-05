<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 18/03/2019
 * Time: 8:30
 */


/**
http://yourdomain.net/adminYOURadmindirectory/index.php?controller=AdminCronJobs&token=
 */

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');

/* Check security token */

if (!Module::isInstalled('saleslayerimport') || substr(Tools::encrypt('saleslayerimport'), 0, 10) != Tools::getValue('token')  )
    die('Bad token');
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR .'saleslayerimport.php';

$SLimport = new SalesLayerImport();

$is_internal =  Tools::getValue('internal');
if(!$is_internal == 1){
    // call from cron
    $SLimport->saveCronExecutionTime();
}

if ($SLimport->checkRegistersForProccess()){

    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $sync_libs = new SalesLayerPimUpdate();
    try{
        $response =  $sync_libs->sync_data_connectors();
        if(count($response)){
            $createMessege = implode('<br>',$response);
            $SLimport->debbug(' After executed sync_data_connectors return ->'.print_r($createMessege,1),'autosync');
        }

    }catch(Exception $e){
        $this->debbug('## Error. Sync data connectors  in Cron start : '.$e->getMessage(),'error');
    }
}else{
    try{
        $SLimport->auto_sync_connectors();
    }catch(Exception $e){
        $this->debbug('## Error. In autosync_ conectors   in cron start : '.$e->getMessage(),'error');
    }
}
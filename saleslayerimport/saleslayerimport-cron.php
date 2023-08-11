<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * Sales-layer PIM Plugin for Prestashop
 *
 * @author    Sales Layer
 * @copyright 2019 Sales Layer
 * @license   License: GPLv3  License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

/* Check security token */

if (!Module::isInstalled('saleslayerimport')
    || Tools::substr(
        Tools::encrypt('saleslayerimport'),
        0,
        10
    ) != Tools::getValue('token')
) {
    die('Bad token');
}
echo ' ';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'saleslayerimport.php';

$SLimport = new SalesLayerImport();
$SLimport->errorSetup();
$is_internal = Tools::getValue('internal');
if (!$is_internal == 1) {
    // call from cron
    $SLimport->saveCronExecutionTime();
}
$force_sync = Tools::getValue('force_sync');

if ($force_sync != '') {
    /*
    Sync if ajax error in template
    */
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $sync_libs = new SalesLayerPimUpdate();
    try {
        $returnUpdate = $sync_libs->storeSyncData($force_sync);
        $SLimport->debbug('Result of comand sync from post of conector ->' . print_r($returnUpdate, 1));
    } catch (Exception $e) {
        $SLimport->debbug('## Error. Sync data connectors in Cron start from post : ' . $e->getMessage()
                          . ' line->' . $e->getLine() . ' trace' . print_r($e->getTrace(), 1), 'error');
    }
}

if ($SLimport->checkRegistersForProccess(false, 'syncdata', true)) {
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $sync_libs = new SalesLayerPimUpdate();
    try {
        $response = $sync_libs->runBalancer();
        if ($response) {
            // $createMessage = implode('</br>', $response);
            $SLimport->debbug(
                ' After executed runBalancer return ->' . print_r($response, 1),
                'autosync'
            );
        }
    } catch (Exception $e) {
        $SLimport->debbug('## Error. Sync data connectors in Cron start : ' . $e->getMessage() .
                          'Line->' . $e->getLine(), 'error');
    }
} else {
    try {
        $SLimport->autoSyncConnectors();
        $SLimport->callProcess('cleaner');
    } catch (Exception $e) {
        $SLimport->debbug('## Error. Auto-sync connectors in cron start : ' . $e->getMessage(), 'error');
    }
}

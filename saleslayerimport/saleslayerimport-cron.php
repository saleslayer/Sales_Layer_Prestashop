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
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'saleslayerimport.php';

$SLimport = new SalesLayerImport();

$is_internal = Tools::getValue('internal');
if (!$is_internal == 1) {
    // call from cron
    $SLimport->saveCronExecutionTime();
}

if ($SLimport->checkRegistersForProccess()) {
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $sync_libs = new SalesLayerPimUpdate();
    try {
        $response = $sync_libs->syncDataConnectors();
        if (count($response)) {
            $createMessage = implode('</br>', $response);
            $SLimport->debbug(
                ' After executed sync_data_connectors return ->' . print_r($createMessage, 1),
                'autosync'
            );
        }
    } catch (Exception $e) {
        $SLimport->debbug('## Error. Sync data connectors  in Cron start : ' . $e->getMessage(), 'error');
    }
} else {
    try {
        $SLimport->autoSyncConnectors();
    } catch (Exception $e) {
        $SLimport->debbug('## Error. In autosync_ conectors   in cron start : ' . $e->getMessage(), 'error');
    }
}

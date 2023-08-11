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
$process_name = 'cleaner';
$start_time = time();
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

try {
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'saleslayerimport.php';
    $SLimport = new SalesLayerImport();
    $SLimport->errorSetup();
} catch (Exception $e) {
    die('Exception in load plugin file->' . $e->getMessage());
}

    ini_set('max_execution_time', 144000);
    $SLimport->registerWorkProcess($process_name);
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $pimUpdate = new SalesLayerPimUpdate();
if (!$pimUpdate->testDownloadingBlock('CLEANER')) {
    /*  $SLimport->debbug(
          "A CLEANER is already in progress. Try to run after 15 minutes.",
          'cleaner'
      );*/
    return false;
}
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SlProductIndexer.php';
try {
    $SLimport->clearDeletedSlyrRegs();
    $SLimport->registerWorkProcess($process_name);
    $SLimport->checkTheRuntime($start_time);

    $SLimport->clearDataHash();
    $SLimport->registerWorkProcess($process_name);
    $SLimport->checkTheRuntime($start_time);

    $SLimport->clearWorkProcess();
    $SLimport->registerWorkProcess($process_name);
    $SLimport->checkTheRuntime($start_time);

    $SLimport->clearPreloadCache();
    $SLimport->registerWorkProcess($process_name);
    $SLimport->checkTheRuntime($start_time);

    $SLimport->clearTempImages();
    $SLimport->clearDebugContent();
} catch (Exception $e) {
    $SLimport->debbug(
        '## Error. in load Indexer_file ->' . print_r($e->getMessage(), 1),
        'cleaner'
    );
}
    $SLimport->clearWorkProcess($process_name);
    $pimUpdate->removeDownloadingBlock('CLEANER');

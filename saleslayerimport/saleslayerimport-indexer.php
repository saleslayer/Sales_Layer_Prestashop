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
$process_name = 'indexer';
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

if ($SLimport->checkRegistersForProccess(false, 'indexer')) {
    ini_set('max_execution_time', 144000);
    $start_time = time();
    $SLimport->registerWorkProcess($process_name);
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $pimUpdate = new SalesLayerPimUpdate();
    if (!$pimUpdate->testDownloadingBlock('INDEXER')) {
        /*  $SLimport->debbug(
              "A indexer is already in progress. Try to run after 15 minutes.",
              'syncdata'
          );*/
        return false;
    }
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SlProductIndexer.php';
    try {
        $indexer  = new SlProductIndexer();
        $all_shops = Shop::getShops(true, null, true);
        $registers = [];
        do {
            try {
                $query = "SELECT * FROM " . _DB_PREFIX_ . 'slyr_indexer LIMIT 250 ';
                $registers = Db::getInstance()->executeS($query);
                if (count($registers) > 0) {
                    $response = $indexer->indexProducts($registers);
                    if (count($response)) {
                        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ .
                                                   'slyr_indexer WHERE id IN(' .
                                                   implode(',', $response) .
                                                   ')');
                        $SLimport->debbug(
                            'After executed indexer return ->' . print_r($response, 1),
                            'indexer'
                        );
                    }
                    $SLimport->registerWorkProcess($process_name);
                }
            } catch (Exception $e) {
                $SLimport->debbug('## Error. Indexer error : ' . $e->getMessage() .
                                  ' line->' . $e->getLine(), 'indexer');
            }

            $SLimport->checkTheRuntime($start_time);
            $SLimport->clearDebugContent();
        } while (count($registers) > 0);
    } catch (Exception $e) {
        $SLimport->debbug(
            '## Error. in load Indexer_file ->' . print_r($e->getMessage(), 1),
            'indexer'
        );
    }
    $SLimport->clearWorkProcess($process_name);
    $pimUpdate->removeDownloadingBlock('INDEXER');
}

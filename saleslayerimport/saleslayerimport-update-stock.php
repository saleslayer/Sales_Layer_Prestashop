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
try {
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'saleslayerimport.php';
    $SLimport = new SalesLayerImport();
} catch (Exception $e) {
    die('Exception in load plugin file->' . $e->getMessage());
}

if ($SLimport->checkRegistersForProccess(false, 'stock_update')) {
    ini_set('max_execution_time', 144000);

    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SalesLayerPimUpdate.php';
    $pimUpdate = new SalesLayerPimUpdate();
    if (!$pimUpdate->testDownloadingBlock('STOCK_UPDATER')) {
        $SLimport->debbug(
            "A Stock updater is already in progress. Try to run after 15 minutes.",
            'update-stock'
        );
        return false;
    }
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SlStockUpdater.php';
    try {
        $stock_updater = new SlStockUpdater();

        $ps_type   = ['product','product_format'];
        $shops  = Db::getInstance()->executeS("SELECT id_shop FROM " .
                                              _DB_PREFIX_ .
                                              "slyr_stock_update group by id_shop ");

        foreach ($ps_type as $item_type) {
            foreach ($shops as $shop) {
                $registers = [];
                do {
                    try {
                        $query = "SELECT * FROM " . _DB_PREFIX_ .
                                 "slyr_stock_update st ,
                                 (SELECT MAX(stock) as max_stock FROM " .
                                 _DB_PREFIX_ . "slyr_stock_update  
                                 WHERE " .
                                 " ps_type = '" . $item_type . "'
                                  AND id_shop = " . $shop['id_shop'] .
                                 "   LIMIT 1) mx_stck WHERE  " .
                                 " ps_type = '" . $item_type . "' " .
                                 "AND id_shop = " . $shop['id_shop'] . " " .
                                 "AND st.stock = mx_stck.max_stock " .
                                 "GROUP BY ps_id, id_shop, stock LIMIT 1000";

                        $registers = Db::getInstance()->executeS($query);
                        if (count($registers) > 0) {
                            $SLimport->debbug(
                                'Petition to update stock ->' . print_r(reset($registers), 1) .
                                ' query ->' . print_r($query, 1),
                                'stock_update'
                            );
                            $response = $stock_updater->updateStock($registers, $shop['id_shop'], $item_type);
                            if (count($response)) {
                                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ .
                                                             'slyr_stock_update WHERE id IN(' .
                                                             implode(',', $response) .
                                                             ')');
                                $SLimport->debbug(
                                    'Petition to update stock ->' . print_r(reset($registers), 1) .
                                    ' After executed stock update return ->' . print_r($response, 1) .
                                    ' query ->' . print_r($query, 1),
                                    'stock_update'
                                );
                            }
                        } else {
                            break;
                        }
                    } catch (Exception $e) {
                        $SLimport->debbug('## Error. Indexer error : ' . $e->getMessage() .
                                                  ' line->' . $e->getLine(), 'update-stock');
                        break;
                    }
                    $SLimport->clearDebugContent();
                } while (count($registers) > 0);
            }
        }
    } catch (Exception $e) {
        $SLimport->debbug(
            '## Error. in load stock_updater file ->' . print_r($e->getMessage(), 1),
            'update-stock'
        );
    }
    $pimUpdate->removeDownloadingBlock('STOCK_UPDATER');
}

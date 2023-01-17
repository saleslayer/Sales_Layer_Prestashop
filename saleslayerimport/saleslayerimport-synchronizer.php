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
$process_name = 'synchronizer';
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
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR .
                 'admin' . DIRECTORY_SEPARATOR . 'SalesLayerPimUpdate.php';
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR .
                 'admin' . DIRECTORY_SEPARATOR . 'SlCatalogues.php';
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR .
                 'admin' . DIRECTORY_SEPARATOR . 'SlProducts.php';
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR .
                 'admin' . DIRECTORY_SEPARATOR . 'SlVariants.php';
    $SLimport = new SalesLayerImport();
    $SLimport->errorSetup();
} catch (Exception $e) {
    die('Exception in load plugin file->' . $e->getMessage());
}
$pid = getmypid();

ini_set('max_execution_time', 144000);
try {
    $type  = Tools::getValue('type');
    $limit = Tools::getValue('limit');
    $SLimport->debbug(
        'start process -> ' .
        print_r($type, 1).' limit -> '.print_r($limit, 1),
        'syncdata'
    );
    if ($type != '' && !empty($limit) && in_array($type, ['category','product','product_format','accessories'])) {
        $SLimport->registerWorkProcess($process_name, $pid);
        $performance_limit = $SLimport->getConfiguration('PERFORMANCE_LIMIT');
        if (!$performance_limit) {
            $performance_limit = 4.00;
        }
        echo 'entry process_item';
        if ($type == 'category') {
            $SLimport->sl_catalogues = new SlCatalogues();
            $skip_duration = date("Y-m-d H:i:s", strtotime("-2 minutes"));
        } elseif ($type == 'product') {
            $SLimport->sl_products = new SlProducts();
            $SLimport->sl_variants = new SlVariants();
            $skip_duration = date("Y-m-d H:i:s", strtotime("-" .
                                                           $SLimport->product_time_limit_reload_minutes." minutes"));
        } elseif ($type == 'product_format') {
            $SLimport->sl_variants = new SlVariants();
            $skip_duration = date("Y-m-d H:i:s", strtotime("-5 minutes"));
        } else {
            $skip_duration = date("Y-m-d H:i:s", strtotime("-5 minutes"));
        }

        $SLimport->allocateMemory();
        for ($for = 0; $for < $limit; $for++) {
            $SLimport->debbug(
                'entry bucle $for -> ' .
                print_r($for, 1).' limit -> '.print_r($limit, 1),
                'syncdata'
            );
            $sqlpre  = ' SET @id = null,@sync_type = null,@item_type = null,' .
                      '@sync_tries = null,@item_data = null,@sync_params = null ';


            $sqlpre2 = "UPDATE " . _DB_PREFIX_ .
                       "slyr_syncdata dest, (SELECT A.id, A.sync_tries, @id := A.id, " .
                       "@sync_type := A.sync_type, @item_type := A.item_type, " .
                       "@sync_tries := A.sync_tries, " .
                       "@item_data := A.item_data, @sync_params := A.sync_params FROM " . _DB_PREFIX_ .
                       "slyr_syncdata A WHERE A.id IN (" .
                       "SELECT MIN(id) FROM " . _DB_PREFIX_ .
                       "slyr_syncdata WHERE sync_type = 'update' AND item_type = '" . $type .
                       "' AND (date_start <= '" . $skip_duration . "' OR date_start IS NULL)) " .
                       "LIMIT 1) src " .
                       "SET dest.status = 'pr', dest.sync_tries = src.sync_tries + 1, " .
                       "dest.date_start = '" . date("Y-m-d H:i:s") . "'  WHERE  dest.id = src.id ";


            $sqlpre3 = ' SELECT @id AS id,@sync_type AS sync_type,@item_type AS item_type , ' .
                      ' @sync_tries AS sync_tries,@item_data AS item_data,@sync_params AS sync_params  ';

            $SLimport->slConnectionQuery('-', $sqlpre);
            $SLimport->slConnectionQuery('-', $sqlpre2);

            $items_to_update = $SLimport->slConnectionQuery(
                'read',
                $sqlpre3
            );

            if (!empty($items_to_update)
                && isset($items_to_update[0]['id'])
                && $items_to_update[0]['id'] != null
            ) {
                try {
                    $SLimport->updateItems($items_to_update, $items_to_update[0]['id'], $pid);
                } catch (Exception $e) {
                    $SLimport->debbug(' ##Error. in update items: ->' .
                                      print_r($e->getMessage(), 1), 'syncdata');
                }
                if ($for !=0 && $for < $limit) {
                    $SLimport->registerWorkProcess($process_name, $pid);
                }
            } else {
                $SLimport->debbug(
                    'break but is without register to work -> ' .
                    print_r($items_to_update[0], 1),
                    'syncdata'
                );
                break;
            }

            $load = sys_getloadavg();
            if ($load[0] >= ($performance_limit - 1)) {
                $count_proceses    = $SLimport->getCountProcess($process_name);
                if ($count_proceses > 1) {
                    $SLimport->debbug(
                        'break but is overloaded -> ' .
                        print_r($load[0], 1) . ' limit->' . print_r($performance_limit, 1)
                        . 'num of proceses->'.print_r($count_proceses, 1),
                        'syncdata'
                    );
                    break;
                }
            }
        }

        $SLimport->checkSqlItemsDelete(true);
        $SLimport->clearWorkProcess($process_name, $pid);
    }
} catch (Exception $e) {
    $SLimport->debbug(
        '## Error. in process command for sincronize-> ' .
        print_r($e->getMessage(), 1) . ' line->' . print_r($e->getLine(), 1),
        'syncdata'
    );
}
die();

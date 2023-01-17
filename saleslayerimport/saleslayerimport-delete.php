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
$process_name = 'delete';
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
try {
    $SLimport->registerWorkProcess($process_name);
    $type = Tools::getValue('type');
    $ids = Tools::getValue('ids');
    if ($type != '' && !empty($ids)) {
        $SLimport->debbug(
            'entry to process deletes with this petition: $type->' . print_r($type, 1) .
            ' ids->' . print_r($ids, 1),
            'delete'
        );
        if ($type == 'category') {
            $SLimport->sl_catalogues = new SlCatalogues();
        } elseif ($type == 'product') {
            $SLimport->sl_products_dl = new SlProductDelete();
            $SLimport->sl_variants = new SlVariants();
        } elseif ($type == 'product_format') {
            $SLimport->sl_variants = new SlVariants();
        }

        $ids = explode(',', $ids);
        try {
            $sql = " SELECT * FROM " . _DB_PREFIX_ . "slyr_syncdata
                     WHERE sync_type = 'delete' AND id IN('" .
                   implode("','", $ids) .
                   "') ORDER BY item_type ASC, sync_tries ASC, id ASC ";

            $items_to_delete = $SLimport->slConnectionQuery(
                'read',
                $sql
            );
            $SLimport->allocateMemory();
            if (!empty($items_to_delete)) {
                $SLimport->debbug(
                    'items founded for delete ->' . print_r($type, 1) .
                    ' ids->' . print_r($ids, 1) . 'items count()->' . count($items_to_delete),
                    'delete'
                );
                foreach ($items_to_delete as $item_to_delete) {
                    $SLimport->checkSqlItemsDelete();
                    $sync_tries = $item_to_delete['sync_tries'];
                    $sync_params = json_decode(
                        Tools::stripslashes($item_to_delete['sync_params']),
                        1
                    );
                    $SLimport->processing_connector_id = $sync_params['conn_params']['connector_id'];
                    $SLimport->comp_id                 = $sync_params['conn_params']['comp_id'];
                    $SLimport->conector_shops_ids      = $sync_params['conn_params']['shops'];


                    $item_data = json_decode(Tools::stripslashes($item_to_delete['item_data']), 1);
                    $sl_id = $item_data['sl_id'];

                    switch ($item_to_delete['item_type']) {
                        case 'category':
                            try {
                                $result_delete = $SLimport->sl_catalogues->deleteCategory(
                                    $sl_id,
                                    $SLimport->comp_id,
                                    $SLimport->conector_shops_ids,
                                    $SLimport->processing_connector_id
                                );
                            } catch (Exception $e) {
                                $SLimport->debbug(
                                    '## Error. in delete category : ' . print_r($item_to_delete, 1),
                                    'delete'
                                );
                            }
                            break;

                        case 'product':
                            try {
                                $result_delete = $SLimport->sl_products_dl->deleteProduct(
                                    $sl_id,
                                    $SLimport->comp_id,
                                    $SLimport->conector_shops_ids,
                                    $SLimport->processing_connector_id
                                );
                            } catch (Exception $e) {
                                $SLimport->debbug(
                                    '## Error. In delete product: ' . print_r($item_to_delete, 1),
                                    'delete'
                                );
                            }
                            break;

                        case 'product_format':
                            try {
                                $result_delete = $SLimport->sl_variants->deleteVariant(
                                    $sl_id,
                                    $SLimport->comp_id,
                                    $SLimport->conector_shops_ids
                                );
                            } catch (Exception $e) {
                                $SLimport->debbug(
                                    '## Error. In delete Variant: ' . print_r($item_to_delete, 1),
                                    'delete'
                                );
                            }
                            break;

                        default:
                            $result_delete = 'Undefined ithem';
                            $SLimport->debbug(
                                '## Error. Incorrect item: ' . print_r($item_to_delete, 1),
                                'delete'
                            );
                            break;
                    }

                    switch ($result_delete) {
                        case 'item_not_deleted':
                            $SLimport->debbug(
                                '## Error. Problem in deleting Item: ' . print_r($item_to_delete, 1),
                                'delete'
                            );
                            $sync_tries++;

                            $sql_update = " UPDATE " . _DB_PREFIX_ . "slyr_syncdata" .
                                      " SET sync_tries = " . $sync_tries .
                                      " WHERE id = " . $item_to_delete['id'];

                            $SLimport->slConnectionQuery('-', $sql_update);
                            $SLimport->clearDebugContent();
                            break;

                        default:
                            $SLimport->sql_items_delete[] = $item_to_delete['id'];
                            $SLimport->clearDebugContent();
                            break;
                    }
                }
                $SLimport->checkSqlItemsDelete(true);
                if ($type == 'category') {
                    $SLimport->debbug('Run regenerateEntireNtree after delete', 'delete');
                    $SLimport->sl_catalogues->reorganizeCategories($SLimport->conector_shops_ids);
                }
            }
        } catch (Exception $e) {
            $SLimport->debbug('## Error. Deleting syncdata process: ' .
                          $e->getMessage(), 'delete');
        }
    }
    $SLimport->clearWorkProcess($process_name);
} catch (Exception $e) {
    $SLimport->debbug(
        '## Error. in process command for delete->' . print_r($e->getMessage(), 1),
        'delete'
    );
}

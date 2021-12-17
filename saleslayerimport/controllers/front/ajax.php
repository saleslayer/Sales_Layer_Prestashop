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

class SaleslayerimportajaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
    }


    public function displayAjax()
    {
        if (Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10) != Tools::getValue('token')) {
            $return = array();
            $return['message_type'] = 'error';
            $return['message'] = 'Invalid Token.';
            die(Tools::jsonEncode($return));
        }
        /**
         * Commands for syncronize the conector
         */

        $command = Tools::getValue('command');

        /**
         * Check status
         */

        if ($command == 'check_status') {
            $return = array();
            $return['server_time'] = 'Server time: ' . date('H:i');
            $SLimport = new SalesLayerImport();

            $sql_processing = ' SELECT count(*) as sl_cuenta_registros  FROM '
                              . _DB_PREFIX_ . 'slyr_syncdata ';
            $items_processing = $SLimport->slConnectionQuery('read', $sql_processing);

            if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0) {
                $return['status'] = 'processing';
                $return['actual_stat'] = $items_processing['sl_cuenta_registros'];
                $return['total_stat']  = null;
                //work_stat

                $balancer_runned = $SLimport->getConfiguration('BALANCER');
                $working_stat =  $SLimport->getConfiguration('SYNC_STATUS');
                //work_stat
                $parse_work = '';
                $process    = '';
                $item_type  = '';
                if ($working_stat) {
                    $parse_work = explode('_', $working_stat);
                    $process = $parse_work[0];
                    $item_type = $parse_work[1];
                }

                $Work_in_message = '';
                if ($process == 'update') {
                    $Work_in_message .= 'Updating ';
                } else {
                    if ($item_type == 'product_format') {
                        $Work_in_message .= 'Removing ';
                    } else {
                        $Work_in_message .= 'Deactivating ';
                    }
                }
                if ($item_type == 'category') {
                    $Work_in_message .= 'categories';
                } elseif ($item_type == 'product') {
                    $Work_in_message .= 'products';
                } elseif ($item_type == 'product_format') {
                    $Work_in_message .= 'variants';
                } elseif ($item_type == 'accessories') {
                    $Work_in_message .= 'accessories';
                } elseif ($item_type == 'index') {
                    $Work_in_message = 'Indexing';
                }
                if (!$balancer_runned) {
                    $Work_in_message = 'Waiting for cron';
                }
                $Work_in_message .= '&nbsp;';
                $return['work_stat'] = $Work_in_message ;
                $return['speed']     = $SLimport->getCountProcess('synchronizer');

                $result = $SLimport->testSlcronExist();
                $register_forProcess = $SLimport->checkRegistersForProccess();

                if (count($result) && $register_forProcess) {
                    $execution_frecuency_cron = $SLimport->getConfiguration('CRON_MINUTES_FREQUENCY');
                    $updated_time = strtotime($result[0]['updated_at']);
                    $now_is_bd = strtotime($result[0]['timeBD']);

                    $next_sync_bd_time = $updated_time + $execution_frecuency_cron;
                    $start_in_bd_seconds = $next_sync_bd_time - $now_is_bd;

                    $return['next_cron_expected'] = $this->timeOccurence($start_in_bd_seconds);
                } else {
                    $return['next_cron_expected'] = 0;
                }
            } else {
                $Work_in_message = '';
                $downloading_status = $SLimport->getConfiguration('DOWNLOADING');
                if ($downloading_status != false) {
                    $Work_in_message = 'Download in progress';
                }
                $return['work_stat'] = $Work_in_message ;
                $return['status'] = 'complete';
            }
            /**
             * system health
             */
            $return['health'] = $SLimport->checkServerUse();

            die(Tools::jsonEncode($return));
        }

        /**
         * Command for force update connector
         */

        if ($command == 'update_command' || $command == 'store_data_now') {
            $connector_id = Tools::getValue('connector_id');

            $returnUpdate = $this->updateConector($connector_id, false);
            if ($returnUpdate['stat']) {
                $return['message_type'] = 'success';
                $return['message'] = $returnUpdate['message'];
            } else {
                $return['message_type'] = 'error';
                $return['message'] = 'Error in update this connector.';
            }


            $return['server_time'] = 'Server time: ' . date('H:i');
            die(Tools::jsonEncode($return));
        }

        /**
         * Command for store data now  from this connector
         */

        if ($command == 'store_data_now') {
            $connector_id = Tools::getValue('connector_id');

            $returnUpdate = $this->updateConector($connector_id);
            if ($returnUpdate['stat']) {
                $return['message_type'] = 'success';
                $return['message'] = $returnUpdate['message'];
            } else {
                $return['message_type'] = 'error';
                $return['message'] = 'Error in store data of this connector.';
            }


            $return['server_time'] = 'Server time: ' . date('H:i');
            die(Tools::jsonEncode($return));
        }


        /**
         * Delete connector
         */

        if ($command == 'delete_now') {
            $connector_id = Tools::getValue('connector_id');

            $SLimport = new SalesLayerImport();

            $resultDelete = $SLimport->sl_updater->deleteConnector($connector_id, true);

            if (empty($resultDelete)) { // error
                $return['message_type'] = 'error';
                $return['message'] = 'Error in update this connector.';
            } else {
                $elements_for_unset_conn = Db::getInstance()->executeS(
                    'SELECT sl.id,sl.ps_id,sl.shops_info FROM ' . _DB_PREFIX_ . 'slyr_category_product sl '
                );
                if (count($elements_for_unset_conn)) {
                    foreach ($elements_for_unset_conn as $row) {
                        if (!empty($row['shops_info'])) {
                            $shops_active = json_decode(Tools::stripslashes($row['shops_info']), 1);
                            if (isset($shops_active[$connector_id])) {
                                unset($shops_active[$connector_id]);
                            }
                            Db::getInstance()->execute(
                                "UPDATE " . _DB_PREFIX_ . "slyr_category_product sl " .
                                " SET sl.shops_info ='" .
                                addslashes(json_encode($shops_active)) . "'  WHERE sl.id = '" . $row['id'] . "' "
                            );
                        }
                    }
                }

                $return['message_type'] = 'success';
                $return['message'] = 'Connector removed correctly';
            }

            $return['server_time'] = 'Server time: ' . date('H:i');
            die(Tools::jsonEncode($return));
        }

        /**
         * Delete all products categories and images for testing from zero
         */

        if ($command == 'purge_all') {
            $SLimport = new SalesLayerImport();
            if ($SLimport->i_am_a_developer) {
                ini_set('max_execution_time', 0);

                $this->purgeAllElementsFromPresta();

                $return['message_type'] = 'success';
                $return['message'] = 'Purged all from Prestashop';
            } else {
                $return['message_type'] = 'error';
                $return['message'] = 'you are not a developer';
            }

            $return['server_time'] = 'Server time: ' . date('H:i');
            die(Tools::jsonEncode($return));
        }

        if ($command == 'clear_syncronization') {
            $SLimport = new SalesLayerImport();
            $sql_processing = "DELETE FROM " . _DB_PREFIX_ . 'slyr_syncdata';
            $SLimport->slConnectionQuery('-', $sql_processing);

            $SLimport->clearPreloadCache();
            $SLimport->clearTempImages();
            $return['message_type'] = 'success';
            $return['message'] = 'Deleted all from synchronization';

            $return['server_time'] = 'Server time: ' . date('H:i');
            die(Tools::jsonEncode($return));
        }


        /**
         *
         * Auto save data in conector
         */
        $return = array();
        $permited_fields = array('auto_sync', 'auto_sync_hour', 'avoid_stock_update');
        $connector_id = Tools::getValue('connector_id');
        $field_name = Tools::getValue('field_name');
        $field_value = Tools::getValue('field_value');
        $permited_fields = array(
                'auto_sync[' . $connector_id . ']',
                'auto_sync_hour[' . $connector_id . ']',
                'avoid_stock_update[' . $connector_id . ']'
        );
        if ($connector_id != null) {
            $SLimport = new SalesLayerImport();
            if (in_array($field_name, $permited_fields, false)) {
                try {
                    if (($field_name == 'auto_sync[' . $connector_id . ']'
                         && ($field_value >= 0 && $field_value <= 72)) ||
                        ($field_name == 'auto_sync_hour[' . $connector_id . ']'
                         && ($field_value >= 0 && $field_value <= 24)) ||
                        ($field_name == 'avoid_stock_update[' . $connector_id . ']'
                         && ($field_value == 1 || $field_value == 0))) {
                        $field_arr = explode('[', $field_name);
                        $field_name = reset($field_arr);
                        $shops_info = $SLimport->setConnectorData($connector_id, $field_name, $field_value);

                        if ($shops_info) {
                            $return['message_type'] = 'success';
                            $return['message']      = 'Changes saved successfully';
                        } else {
                            $return['message_type'] = 'error';
                            $return['message']      = 'An error occurred in saving changes in the connector.';
                        }
                    } else {
                        $SLimport->debbug('cut connection  ' . print_r($field_name, 1));
                        $return['message_type'] = 'error';
                        $return['message'] = 'Not valid value for save.';

                        die(Tools::jsonEncode($return));
                    }
                } catch (Exception $e) {
                    $return['message_type'] = 'error';
                    $return['message'] = 'An error occurred in saving changes in the connector. Check error log.';
                    $SLimport->debbug(
                        '## Error. An error occurred in saving changes in the connector ' . print_r($e->getMessage(), 1)
                    );
                }
            } else {
                if ($field_name != null) {
                    $sting_toarray = explode('_', $field_name);
                    if (0 === strpos($sting_toarray[0], "shop")) {
                        /**
                         * Update stores
                         */

                        try {
                            $conn_extra_info = $SLimport->sl_updater->getConnectorExtraInfo($connector_id);
                            $need_save = false;
                            if (isset($conn_extra_info['shops']) && !empty($sting_toarray[1])) {
                                foreach ($conn_extra_info['shops'] as $shop_key => $connector_shop) {
                                    if ('[' . $connector_shop['id_shop'] . ']' == $sting_toarray[1]) {
                                        if ($field_value == true) {
                                            $conn_extra_info['shops'][$shop_key]['checked'] = true;
                                        } else {
                                            $conn_extra_info['shops'][$shop_key]['checked'] = false;
                                        }
                                        $need_save = true;
                                        break;
                                    }
                                }
                            }

                            if ($need_save) {
                                $SLimport->sl_updater->setConnectorExtraInfo($connector_id, $conn_extra_info);
                                $return['message_type'] = 'success';
                                $return['message'] = 'Changes saved successfully.';
                            } else {
                                $return['message_type'] = 'warning';
                                $return['message'] = 'No store was found to save changes.';
                            }
                        } catch (Exception $e) {
                            $return['message_type'] = 'error';
                            $return['message'] =
                                'An error occurred in saving shops changes in the connector. Check error log.';
                        }
                    } else {
                        $return['message_type'] = 'error';
                        $return['message'] = 'Command not allowed.';
                    }
                } else {
                    $return['message_type'] = 'error';
                    $return['message'] = 'Field name == null';
                }
            }
        } else {
            $return['message_type'] = 'error';
            $return['message'] = 'Could not save data in the connector equal to null.';
        }
        $return['server_time'] = 'Server time: ' . date('H:i');
        die(Tools::jsonEncode($return));
    }

    public function updateConector(
        $conn_code,
        $onlystore = false
    ) {
        $return = [];
        $SLimport = new SalesLayerImport();
        $SLimport->debbug('Start update from AJAX command to process connector: ' . print_r($conn_code, 1));
        $createMessege = '';

        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/../../controllers/admin/SalesLayerPimUpdate.php';
        $sync_libs = new SalesLayerPimUpdate();


        if ($SLimport->checkRegistersForProccess()) {
            try {
                $SLimport->callProcess('cron', ['internal' => 1]);
                $return = 'Synchronization executed';
                $update_success = true;
            } catch (Exception $e) {
                $SLimport->debbug(
                    '## Error. Sync data connectors ' . $conn_code . ' : ' .
                    $e->getMessage() . ' line->' . $e->getLine(),
                    'error'
                );
                $return = 'Error: Sync data connectors  ' . $conn_code . ' : ' . $e->getMessage();
                $update_success = false;
            }
        } else {
            try {
                $returnUpdate = $sync_libs->storeSyncData($conn_code, null, $onlystore);

                if (is_array($returnUpdate)) {
                    if (count($returnUpdate)) {
                        $createMessege .= 'Stored for proccess: <br>';
                        if (isset($returnUpdate['categories_to_delete']) && $returnUpdate['categories_to_delete'] > 0) {
                            $createMessege .= $returnUpdate['categories_to_delete'] . ' Categories to delete <br>';
                        }
                        if (isset($returnUpdate['products_to_delete']) && $returnUpdate['products_to_delete'] > 0) {
                            $createMessege .= $returnUpdate['products_to_delete'] . ' Products to hide <br>';
                        }
                        if (isset($returnUpdate['product_formats_to_delete']) &&
                            $returnUpdate['product_formats_to_delete'] > 0) {
                            $createMessege .= $returnUpdate['product_formats_to_delete'] . ' Variants to delete <br>';
                        }
                        if (isset($returnUpdate['categories_to_sync']) && $returnUpdate['categories_to_sync'] > 0) {
                            $createMessege .= $returnUpdate['categories_to_sync'] . ' Categories to process <br>';
                        }
                        if (isset($returnUpdate['products_to_sync']) && $returnUpdate['products_to_sync'] > 0) {
                            $createMessege .= $returnUpdate['products_to_sync'] . ' Products to process <br>';
                        }
                        if (isset($returnUpdate['product_formats_to_sync']) &&
                            $returnUpdate['product_formats_to_sync'] > 0) {
                            $createMessege .= $returnUpdate['product_formats_to_sync'] . ' Variants to process <br>';
                        }
                    } else {
                        $createMessege .= 'No changes have been received to process.';
                    }
                } else {
                    $createMessege = $returnUpdate;
                }


                if (trim($createMessege) == '') {
                    $createMessege = 'No changes have been received to process.';
                }

                $return = 'Test update this conector executed:<br>' . $createMessege;
                $update_success = true;
            } catch (Exception $e) {
                $SLimport->debbug(
                    '## Error. In store data ' . $conn_code . ' : ' . $e->getMessage() . ' trace->' . print_r(
                        $e->getTrace(),
                        1
                    ) . ' line->' . $e->getLine()
                );
                $return = 'Error: In storing data ' . $conn_code . ' : ' . $e->getMessage();
                $update_success = false;
            }
        }

        return array('stat' => $update_success, 'message' => $return);
    }

    /**
     * Send time as string
     * @param $time
     * @return string
     */


    private function timeOccurence(
        $time
    ) {
        // $time = time() - $time; // to get the time since that moment

        $tokens = array(
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second',
        );

        foreach ($tokens as $unit => $text) {
            if ($time < $unit) {
                continue;
            }
            if ($text != 'year') {
                $numberOfUnits = floor($time / $unit);

                return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
            } else {
                return ' never because your cron does not work ';
            }
        }
    }

    /**
     * Delete ALL CATEGORIES, Products, Variants only FOR DEVELOPERS !!!!!
     *
     */

    private function purgeAllElementsFromPresta()
    {
        $SLimport = new SalesLayerImport();

        $SLimport->allocateMemory();
        ini_set('max_execution_time', 7000);
        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);
        //products
        $res = Db::getInstance()->executeS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ');
        $SLimport->allocateMemory();
        if ($res) {
            foreach ($res as $row) {
                $p = new Product($row['id_product']);

                $p->deleteImage(true);
                $p->delete();
            }
        }

        $res = Db::getInstance()->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category`
             WHERE id_category != "0" AND is_root_category = "0" '
        );
        $SLimport->allocateMemory();
        if ($res) {
            foreach ($res as $row) {
                $p = new Category($row['id_category']);
                $p->deleteImage(true);
                $p->delete();
            }
        }

        $res = Db::getInstance()->executeS('SELECT `id_image` FROM `' . _DB_PREFIX_ . 'image` ');
        $SLimport->allocateMemory();
        if ($res) {
            foreach ($res as $row) {
                $p = new Image($row['id_image']);
                $p->deleteImage(true);
                $p->delete();

                Db::getInstance()->execute(
                    sprintf("DELETE FROM " . $SLimport->image_table . " WHERE id_image = '%s' ", $row['id_image'])
                );
                Db::getInstance()->execute(
                    sprintf(
                        "DELETE FROM " . $SLimport->image_lang_table . " WHERE id_image = '%s' ",
                        $row['id_image']
                    )
                );
                Db::getInstance()->execute(
                    sprintf(
                        "DELETE FROM " . $SLimport->image_shop_table . " WHERE id_image = '%s' ",
                        $row['id_image']
                    )
                );
                Db::getInstance()->execute(
                    sprintf("DELETE FROM " . _DB_PREFIX_ . "slyr_image WHERE id_image = '%s' ", $row['id_image'])
                );
            }
        }

        $res = Db::getInstance()->executeS('SELECT `id_attribute_group` FROM `' . _DB_PREFIX_ . 'attribute_group` ');
        $SLimport->allocateMemory();
        if ($res) {
            foreach ($res as $row) {
                $attr = new AttributeGroup($row['id_attribute_group']);
                $attr->delete();
            }
        }

        $SLimport->clearDeletedSlyrRegs();
        $SLimport->clearDataHash(false);
        $SLimport->clearPreloadCache();
        $SLimport->clearWorkProcess();
        $SLimport->clearTempImages();

        Db::getInstance()->execute("DELETE FROM " . _DB_PREFIX_ . 'slyr_category_product');

        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
    }
}

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

class AllConnectorsController extends ModuleAdminController
{
    private $SLimport;

    public function __construct()
    {
        $this->display = 'All conectors';
        $this->show_toolbar = true;
        $this->meta_title = 'Sales layer All conectors';
        parent::__construct();
        $this->SLimport = new SalesLayerImport();
        $this->bootstrap = true;
    }

    public function init()
    {
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();
    }

    public function generateTable()
    {
        $connectors = $this->SLimport->getAllConnectors();
        $autosync_options = array(
            '0' => 'Autosync Off',
            '1' => '1H',
            '3' => '3H',
            '6' => '6H',
            '8' => '8H',
            '12' => '12H',
            '15' => '15H',
            '24' => '24H',
            '48' => '48H',
            '72' => '72H',
        );

        $hours_range = range(0, 23);

        /**
         * Generate table with conectors
         */
        $table = '';
        if (count($connectors) > 0) {
            foreach ($connectors as $connector) {
                $table .= '<tr id="connector_register_' . $connector['conn_code'] . '">';
                $table .= '<th class="' . ($connector['auto_sync'] == 0 ? 'grey_sl' : 'green_sl') .
                          ' headname" scope="row" id="head_' . $connector['conn_code'] .
                          '"><b class="headname mar-10" id="head_b_'
                          . $connector['conn_code'] . '">' .
                          ($connector['auto_sync'] == 0 ? 'OFF' : 'ON') . '</b></th>';
                $table .= '<td class="mar-top-btt-10">';
                $table .= '<table class="table mar-10 table-hide-bor">';
                $table .= '<tr>';
                $table .= '<input type="hidden" name="connector[]" value="' . $connector['conn_code'] . '"/>';
                $table .= '<td><strong>Identification Code: </strong></td><td>' . $connector['conn_code'] . '</td>';
                $table .= '</tr>';
                $table .= '<tr>';
                $table .= '<td><strong>Last update: </strong></td><td>' . $connector['last_update'] . '</td>';
                $table .= '</tr>';
                $table .= '</table>';
                $table .= '</td>';

                /**
                 * Shops to synchronize
                 */
                $table .= '<td>';
                $table .= '<table class="table table-hide-bor">';

                if (count($connector['shops']) > 0) {
                    foreach ($connector['shops'] as $shop) {
                        $table .= '<tr class="mar-10">';
                        $table .= '<td>';
                        $table .= '<input type="checkbox" id="shops[' . $connector['conn_code'] . ']['
                                  . $shop['id_shop'] . ']_[' . $shop['id_shop'] .
                                  ']_'
                                  . $connector['conn_code'] . '"  title="' . $shop['name'] .
                                  '" name="shops[' . $connector['conn_code'] . ']['
                                  . $shop['id_shop'] . ']_['
                                  . $shop['id_shop'] . ']" ' . ($shop['checked'] == true ? 'checked="checked"' : '')
                                  . '  value="1" onchange="update_conn_field(this);">' ;

                        $table .= '</td>';
                        $table .= '<td>';
                        $table .= '<label for="shops[' . $connector['conn_code'] . ']['
                                  . $shop['id_shop'] . ']_[' . $shop['id_shop'] .
                                  ']_'
                                  . $connector['conn_code'] . '" class="text-center">' . $shop['name'];
                        $table .= '</label>';
                        $table .= '</td>';
                        $table .= '</tr>';
                    }
                }

                $table .= '</table>';
                $table .= '</td>';

                /**
                 * Autosync select
                 */
                if (strtotime($connector['last_sync']) > time()) {
                    $last_text = 'Next ';
                } else {
                    $last_text = 'Last ';
                }
                $table .= '<td>';
                $table .= '<table class="table table-hide-bor">';
                $table .= '<tr>';
                $table .= '<td>';
                $table .= '<span id="last_sync_' . $connector['conn_code'] . '">' . $last_text
                          . ' sync: ' . ($connector['last_sync'] != 0 ? $connector['last_sync'] : 'Never') . '</span>';
                $table .= '</td>';
                $table .= '</tr>';
                $table .= '<tr>';
                $table .= '<td>';
                $table .= '<select id="auto_sync[' . $connector['conn_code'] . ']_' . $connector['conn_code']
                          . '" name="auto_sync[' . $connector['conn_code'] .
                          ']" onchange="update_conn_field(this); validAutosync(this);">';
                foreach ($autosync_options as $value => $hour) {
                    $table .= '<option value="' . $value . '" ' . ($value == $connector['auto_sync']
                            ? 'selected' : '') . '>' . $hour . '</option>';
                }
                $table .= '</select>';
                $table .= '</td>';
                $table .= '</tr>';
                $table .= '</table>';
                $table .= '</td>';

                /**
                 * Create autosync time prefered
                 */
                $table .= '<td>';
                $table .= '<table class="table table-hide-bor">';
                $table .= '<tr>';
                $table .= '<td>';
                $table .= '<span class="server_time">Server time: ' . date('H:i') . '</span>';
                $table .= '</td>';
                $table .= '</tr>';
                $table .= '<tr>';
                $table .= '<td>';
                $table .= '<select id="auto_sync_hour[' . $connector['conn_code'] . ']_' . $connector['conn_code']
                          . '" name="auto_sync_hour[' . $connector['conn_code'] .
                          ']" onchange="update_conn_field(this);" '
                          . ($connector['auto_sync'] < 24 ? 'disabled' : '') . ' >';
                foreach ($hours_range as $hour) {
                    $table .= '<option value="' . $hour . '" ' . ($hour == $connector['auto_sync_hour'] ?
                            'selected' : '') . '>' . (Tools::strlen(
                                $hour
                            ) == 1 ? '0' . $hour : $hour) . ':00' . '</option>';
                }
                $table .= '</select>';
                $table .= '</td>';
                $table .= '</tr>';
                $table .= '</table>';
                $table .= '</td>';

                /**
                 * Create avoid_stock_update
                 */
                $table .= '<td class="text-center">';
                // $table .= '<label class="text-center">';
                $table .= '<input class="mar-10" type="checkbox" id="avoid_stock_update[' .
                          $connector['conn_code'] . ']_' . $connector['conn_code']
                          . '"  title=" I want that with each update it is overwritten state of stock of products
                    that will be received as modified." name="avoid_stock_update[' .
                          $connector['conn_code'] . ']" '
                          . ($connector['avoid_stock_update'] == true ? 'checked="checked"' : '')
                          . '  value="1" onchange="update_conn_field(this);">';
                // $table .= '</label>';
                $table .= '</td>';

                /**
                 * Delete form options
                 */
                $table .= '<td class="text-center">';
                $table .= '<input type="hidden" name="saleslayerimport[action]" value="logout"/>';
                $table .= '<div class="mar-top-btt-10 slyr-form-field-block">';
                $table .= '<span  id="delete_now_' .
                          $connector['conn_code'] .
                          '" class="btn btn-danger update_btt"
                           onclick=update_command("' . $connector['conn_code'] . '","delete_now");>' .
                          '<i class="fa fa-trash text-left" aria-hidden="true"></i> Remove</span>';
                $table .= '</div>';
                $table .= '</td>';

                /**
                 * Store data now button
                 */
                $table .= '<td class="form-group text-center">';
                $table .= '<div class="mar-top-btt-10 slyr-form-field-block">';
                $table .= '<span id="store_data_now_' . $connector['conn_code'] .
                          '" name="store_data_now"
                          onclick=update_command("' . $connector['conn_code'] . '","store_data_now");
 title="Download and store data now and wait for cron sync."
class="btn btn-success update_btt">' .
                          '<i class="fa fa-cloud-download text-left" ' .
                          'aria-hidden="true"/> Download data</span>';
                $table .= '</div>';
                $table .= '</td>';

                $table .= '</tr>';
            }
        }
        return $table;
    }

    public function purgeAllButton()
    {
        $purge_all_button = '';
        if ($this->SLimport->i_am_a_developer) {
            $purge_all_button .= '<div class="mar-top-btt-10 slyr-form-field-block">';
            $purge_all_button .= '<span id="purge_all_" name="purge_all" ' .
            'title="Warning! This will eliminate all the products, ' .
             'categories and images of your store. Use only for development." ' .
            ' type="button" class="btn btn-danger" onclick=update_command("-","purge_all"); >
            <i class="fa fa-warning text-left" aria-hidden="true"></i> Delete all from Prestashop</span>';
            $purge_all_button .= '</div>';
        }
        return $purge_all_button;
    }

    public function stopSyncronizacionButton()
    {
        $stop_syncronization = '';
        $stop_syncronization .= '<div class="mar-top-btt-10 slyr-form-field-block">';
        $stop_syncronization .= '<span id="clear_syncronization_" name="clear_syncronization"
title="Warning! This will remove all the records that are saved to synchronize to the store. To re-sync,
you will have to run the refresh in the connector.
Use this button in case you think that the synchronization has got stuck and can not continue,
to stop all synchronization stored." class="btn btn-danger" onclick=update_command("-","clear_syncronization");>
<i class="fa fa-warning text-left" aria-hidden="true"></i> Stop syncronization</span>';
        $stop_syncronization .= '</div>';
        return $stop_syncronization;
    }


    public function renderList()
    {
        $messages = '';

        $downloading_status = $this->SLimport->getConfiguration('DOWNLOADING');
        if ($downloading_status != false) {
            $messages .= '<ul class="messages"><li class="success-msg"><ul><li>' .
                         'Download in progress </li></ul></li></ul>';
        }

        $status_rows =  $this->SLimport->checkRegistersForProccess(true);
        if ($status_rows > 0) {
            /*show process*/
            $messages = '<ul class="messages"><li class="success-msg"><ul><li>' .
                         $status_rows . ' items left to process </li></ul></li></ul>';
        }


        $developmentButton = '';


        $return = $this->SLimport->verifySLcronRegister();

        if (!$return['stat']) {
            $messages .= '<h4 class="sy-alert sy-danger">' . $return['message'] . '</h4>';
        }

        $this->context->smarty->assign(
            array(
                'ajax_link' =>
                    $this->context->link->getModuleLink(
                        'saleslayerimport',
                        'ajax',
                        [],
                        null,
                        null,
                        $this->SLimport->shop_loaded_id
                    ),
                'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                'diag_link' =>
                    $this->context->link->getModuleLink(
                        'saleslayerimport',
                        'diagtools',
                        [],
                        null,
                        null,
                        $this->SLimport->shop_loaded_id
                    )
                ,
                'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                'SLY_TABLE' => $this->generateTable(),
                'SLY_DEVELOPMENT' => $developmentButton,
                'messages' => $messages,
                'link_all_connectors' => $this->context->link->getAdminLink('AddConnectors'),
                'purge_button' => $this->purgeAllButton(),
                'stop_syncronization' => $this->stopSyncronizacionButton(),
            )
        );

        //  $this->context->controller->addJS($this->SLimport->module_path.'views/js/authenticated.js');
        return $this->module->display(
            _PS_MODULE_DIR_ . 'saleslayerimport',
            'views/templates/admin/authenticated.tpl'
        );
    }

    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'Sales layer Connectors';
    }

    public function postProcess()
    {
        if (Tools::isSubmit('savechanges')) {
            $connectors = Tools::getValue('connector');
            if (is_array($connectors) && count($connectors)) {
                $shops              = Tools::getValue('shops');
                $auto_sync          = Tools::getValue('auto_sync');
                $auto_sync_hour     = Tools::getValue('auto_sync_hour');
                $avoid_stock_update = Tools::getValue('avoid_stock_update');
                $order_content = array();
                foreach ($connectors as $conector_id) {
                    $order_content[$conector_id]['auto_sync']      =
                    $order_content[$conector_id]['auto_sync_hour'] =
                    $order_content[$conector_id]['avoid_stock_update'] = 0;

                    if (isset($auto_sync[$conector_id])) {
                        $order_content[$conector_id]['auto_sync']              =  (int)      $auto_sync[$conector_id];
                        if ($auto_sync[$conector_id] >= 24) {
                            if (isset($auto_sync_hour[$conector_id])) {
                                $order_content[$conector_id]['auto_sync_hour'] =  (int) $auto_sync_hour[$conector_id];
                            }
                        }
                    }
                    if (isset($avoid_stock_update[$conector_id])) {
                        $order_content[$conector_id]['avoid_stock_update'] = (int) $avoid_stock_update[$conector_id];
                    }

                    /* shops */
                    try {
                        $conn_extra_info = $this->SLimport->sl_updater->getConnectorExtraInfo($conector_id);
                        foreach ($conn_extra_info['shops'] as $shop_key => $connector_shop) {
                            if (isset($shops[$conector_id][$connector_shop['id_shop']])) {
                                if ($shops[$conector_id][$connector_shop['id_shop']] == true) {
                                    $conn_extra_info['shops'][$shop_key]['checked'] = 1;
                                } else {
                                    $conn_extra_info['shops'][$shop_key]['checked'] = 0;
                                }
                            } else {
                                $conn_extra_info['shops'][$shop_key]['checked'] = 0;
                            }
                        }
                        $this->SLimport->sl_updater->setConnectorExtraInfo($conector_id, $conn_extra_info);
                    } catch (Exception $e) {
                        $this->SLimport->debbug('##Error. In save nech store info -> ' . $conn_extra_info .
                                                ' msg -> ' . $e->getMessage() . ' line->' . $e->getLine(), '');
                    }
                    $sql_FUPD = "UPDATE " . _DB_PREFIX_ . "slyr_" . $this->SLimport->sl_updater->table_config .
                                " SET " ;
                    $counter = 1;
                    $total = count($order_content[$conector_id]);
                    foreach ($order_content[$conector_id] as $key => $value_for_save) {
                        $sql_FUPD .=      $key . " = '$value_for_save.'  ";
                        if ($counter == $total) {
                        } else {
                            $sql_FUPD .= ',';
                        }
                        $counter++;
                    }

                    $sql_FUPD .= " WHERE conn_code = '" . addslashes(
                        $conector_id
                    ) . "' limit 1 ";
                    try {
                        Db::getInstance()->execute($sql_FUPD);
                    } catch (Exception $e) {
                        $this->SLimport->debbug('##Error. In sve chages -> ' . $sql_FUPD, '');
                    }
                }
            }
        } else {
            $delete_conector = Tools::getValue('del_conn');
            if ($delete_conector != '') {
                $this->SLimport->sl_updater->deleteConnector($delete_conector, true);
                return true;
            }
            $sync_conector = Tools::getValue('sync_conn');
            if ($sync_conector != '') {
                try {
                    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR .
                             '/../../controllers/admin/SalesLayerPimUpdate.php';
                    $sync_libs = new SalesLayerPimUpdate();
                } catch (Exception $e) {
                    $this->SLimport->debbug(
                        '## Error. in luad  ' . $sync_conector . ' : ' .
                        $e->getMessage() . ' line->' . $$e->getLine(),
                        'error'
                    );
                }

                $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
                $url = 'http://' . $default_shop->domain . $default_shop->getBaseURI() . 'modules/' .
                       'saleslayerimport/saleslayerimport-cron.php?token=' . Tools::substr(
                           Tools::encrypt('saleslayerimport'),
                           0,
                           10
                       ) .
                       '&internal=1&force_sync=' . $sync_conector;
                $this->SLimport->debbug(
                    'Calling execution of synchronization from post.->' . print_r($url, 1)
                );

                $sync_libs->urlSendCustomJson('GET', $url, null, false);
                return true;
            }
            $clear_syncronization = Tools::getValue('clear_syncronization');
            if ($clear_syncronization == 1) {
                $sql_processing = 'DELETE FROM ' . _DB_PREFIX_ . 'slyr_syncdata';
                $this->SLimport->slConnectionQuery('-', $sql_processing);
                return true;
            }
        }
    }
}

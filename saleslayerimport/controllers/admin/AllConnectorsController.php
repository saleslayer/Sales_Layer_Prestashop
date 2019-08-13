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

    public function renderList()
    {
        $messages = '';
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
        $developmentButton = '';
        if ($this->SLimport->i_am_a_developer) {
            /**
             * Create test button
             */
            $developmentButton = '<th class="text-center"><h4>Development start update</h4></th>';
        }

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
                    . $connector['conn_code'] . '">' . ($connector['auto_sync'] == 0 ? 'OFF' : 'ON') . '</b></th>';
                $table .= '<td class="mar-top-btt-10">';
                $table .= '<ul class="list-unstyled mar-10">';
                $table .= '<li><strong>Identification Code: </strong>' . $connector['conn_code'] . '</li>';
                $table .= '<li><strong>Last update: </strong>' . $connector['last_update'] . '</li>';
                $table .= '</ul>';
                $table .= '</td>';

                /**
                 * Shops to synchronize
                 */
                $table .= '<td>';
                $table .= '<ul class="list-unstyled">';
                if (count($connector['shops']) > 0) {
                    foreach ($connector['shops'] as $shop) {
                        $table .= '<li class="mar-10">';
                        $table .= '<label class="text-center">';
                        $table .= '<input class="mar-10" type="checkbox" id="shops_[' . $shop['id_shop'] . ']_'
                            . $connector['conn_code'] . '"  title="' . $shop['name'] . '" name="shops_['
                            . $shop['id_shop'] . ']" ' . ($shop['checked'] == true ? 'checked="checked"' : '')
                            . '  value="1" onchange="update_conn_field(this);">' . $shop['name'];
                        $table .= '</label>';
                        $table .= '</li>';
                    }
                }
                $table .= '</ul>';
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
                $table .= '<span id="last_sync_' . $connector['conn_code'] . '">' . $last_text
                    . ' sync: ' . ($connector['last_sync'] != 0 ? $connector['last_sync'] : 'Never') . '</span>';
                $table .= '<select id="auto_sync_' . $connector['conn_code']
                    . '" name="auto_sync" onchange="update_conn_field(this); validAutosync(this);">';
                foreach ($autosync_options as $value => $hour) {
                    $table .= '<option value="' . $value . '" ' . ($value == $connector['auto_sync']
                            ? 'selected' : '') . '>' . $hour . '</option>';
                }
                $table .= '</select>';
                $table .= '</td>';

                /**
                 * Create autosync time prefered
                 */
                $table .= '<td>';
                $table .= '<span class="server_time">Server time: ' . date('H:i') . '</span>';
                $table .= '<select id="auto_sync_hour_' . $connector['conn_code']
                    . '" name="auto_sync_hour" onchange="update_conn_field(this);" '
                    . ($connector['auto_sync'] < 24 ? 'disabled' : '') . ' >';
                foreach ($hours_range as $hour) {
                    $table .= '<option value="' . $hour . '" ' . ($hour == $connector['auto_sync_hour'] ?
                            'selected' : '') . '>' . (Tools::strlen(
                                $hour
                            ) == 1 ? '0' . $hour : $hour) . ':00' . '</option>';
                }
                $table .= '</select>';
                $table .= '</td>';

                /**
                 * Create avoid_stock_update
                 */
                $table .= '<td class="text-center">';
                // $table .= '<label class="text-center">';
                $table .= '<input class="mar-10" type="checkbox" id="avoid_stock_update_' . $connector['conn_code']
                    . '"  title=" I want that with each update it is overwritten state of stock of products
                    that will be received as modified." name="avoid_stock_update" '
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
                $table .= '<button onclick="update_command(this);" name="delete_now" id="delete_now_' .
                    $connector['conn_code'] .
                    '" class="btn btn-danger"><i class="fa fa-trash text-left" aria-hidden="true"></i> Remove</button>';
                $table .= '</div>';
                $table .= '</td>';

                /**
                 * Store data now button
                 */
                $table .= '<td class="form-group text-center">';
                $table .= '<div class="mar-top-btt-10 slyr-form-field-block">';
                $table .= '<button id="store_data_now_' . $connector['conn_code'] .

                    '" name="store_data_now" onclick="update_command(this);"
 title="Download and store data now and wait for cron sync."
type="button" class="btn btn-success"><i class="fa fa-cloud-download text-left" 
aria-hidden="true"></i> Download data</button>';
                $table .= '</div>';
                $table .= '</td>';


                if ($this->SLimport->i_am_a_developer) {
                    /**
                     * Creating test button
                     */
                    $table .= '<td class="form-group text-center">';
                    $table .= '<div class="mar-top-btt-10 slyr-form-field-block">';
                    $table .= '<button id="update_command_' . $connector['conn_code'] .
                        '" name="update_command" onclick="update_command(this);" 
title="simulate cron execution with immediate synchronization."
type="button" class="btn btn-success"><i class="fa fa-refresh text-left"
 aria-hidden="true"></i> Force Start </button>';
                    $table .= '</div>';
                    $table .= '</td>';
                }
                $table .= '</tr>';
            }
        }

        $return = $this->SLimport->verifySLcronRegister();

        if (!$return['stat']) {
            $messages .= '<h4 class="sy-alert sy-danger">' . $return['message'] . '</h4>';
        }
        $purge_all_button = '';

        if ($this->SLimport->i_am_a_developer) {
            $purge_all_button .= '<div class="mar-top-btt-10 slyr-form-field-block">';
            $purge_all_button .= '<button id="purge_all_" name="purge_all" onclick="update_command(this);"
title="Warning! This will eliminate all the products, categories and images of your store. Use only for development."
type="button" class="btn btn-danger">
<i class="fa fa-warning text-left" aria-hidden="true"></i> Delete all from Prestashop</button>';
            $purge_all_button .= '</div>';
        }
        $stop_syncronization = '';
        $stop_syncronization .= '<div class="mar-top-btt-10 slyr-form-field-block">';
        $stop_syncronization .= '<button id="clear_syncronization_" name="clear_syncronization"
title="Warning! This will remove all the records that are saved to synchronize to the store. To re-sync,
you will have to run the refresh in the connector.
Use this button in case you think that the synchronization has got stuck and can not continue,
to stop all synchronization stored." onclick="update_command(this);" type="button" class="btn btn-danger">
<i class="fa fa-warning text-left" aria-hidden="true"></i> Stop syncronization</button>';
        $stop_syncronization .= '</div>';


        $this->context->smarty->assign(
            array(
                'ajax_link' => $this->context->link->getModuleLink('saleslayerimport', 'ajax'),
                'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                'diag_link' => $this->context->link->getModuleLink('saleslayerimport', 'diagtools'),
                'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                'SLY_TABLE' => $table,
                'SLY_DEVELOPMENT' => $developmentButton,
                'messages' => $messages,
                'link_all_connectors' => $this->context->link->getAdminLink('AddConnectors'),
                'purge_button' => $purge_all_button,
                'stop_syncronization' => $stop_syncronization,
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
}

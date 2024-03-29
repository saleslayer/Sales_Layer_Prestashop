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

use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

class HowToUseController extends ModuleAdminController
{
    public function __construct()
    {
        $this->show_toolbar = true;
        $this->display = 'How to use Sales Layer';
        $this->meta_title = 'How to use';
        $this->toolbar_title = 'How to use Sales Layer';
        parent::__construct();
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
        $SLimport = new SalesLayerImport();
        $message = '';
        $return_table = array();
        $extension_needed = array(
            'curl_version' => array('test' => 'function_exists', 'public_name' => 'PHP cURL Installed'),
            'testSlCronLatestRun' => array(
                'test' => 'setfunction',
                'public_name' => 'Cron has been executed in the last 10 minutes',
            )
        );
        
        if (count($extension_needed)) {
            foreach ($extension_needed as $extension_name => $extension_value) {
                if ($extension_value['test'] == 'function_exists') {
                    if (function_exists($extension_name)) {
                        $result_test = 'fa-check text-success';
                    } else {
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '
                        . $result_test . '" aria-hidden="true"></i>';
                } elseif ($extension_value['test'] == 'module') {
                    $file_extension = _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . $extension_name
                        . DIRECTORY_SEPARATOR . $extension_name . '.php';
                    if (file_exists($file_extension)) {
                        $result_test = 'fa-check text-success';
                    } else {
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '
                        . $result_test . '" aria-hidden="true"></i>';
                } elseif ($extension_value['test'] == 'setfunction') {
                    $return_stat = $SLimport->{$extension_name}();
                    if (isset($extension_value['return'])) {
                        $return_response = $return_stat[$extension_value['return']];
                    } else {
                        $return_response = $return_stat;
                    }
                    if ($return_response) {
                        $result_test = 'fa-check text-success';
                    } else {
                        if (isset($extension_value['additional_message'])) {
                            $message .= $return_stat[$extension_value['additional_message']];
                        }
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '
                        . $result_test . '" aria-hidden="true"></i>';
                }
            }
        }
        if (count($return_table)) {
            $create_validation_table = '<table class="table">';
            foreach ($return_table as $table_name => $table_value) {
                $create_validation_table .= '<tr>';
                $create_validation_table .= '<td>';
                $create_validation_table .= $table_name;
                $create_validation_table .= '<td>';
                $create_validation_table .= '<td>';
                $create_validation_table .= $table_value;
                $create_validation_table .= '<td>';
                $create_validation_table .= '</tr>';
            }
            $create_validation_table .= '</table>';
        }


        $shop_id = (Shop::getContextShopID() ? Shop::getContextShopID() : Configuration::get('PS_SHOP_DEFAULT'));
        Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);
        if (Tools::version_compare(_PS_VERSION_, '1.7.7.5', '>=') == true) {
            ShopConstraint::shop($shop_id);
        }

        $this->context->smarty->assign(
            array(
                'SLY_LOGOS_PATH' => $SLimport->module_path . 'views/img/',
                'SLY_ASSETS_PATH' => $SLimport->module_path,
                'link_all_connectors' => $this->context->link->getAdminLink(
                    'AllConnectors',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'add_connectors' => $this->context->link->getAdminLink(
                    'AddConnectors',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'link_how_to_use' => $this->context->link->getAdminLink(
                    'HowToUse',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'link_diagnostics' => $this->context->link->getAdminLink(
                    'AdminDiagtools',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'plugin_name' => Tools::ucfirst($SLimport->name),
                'admin_attributes' => $this->context->link->getAdminLink(
                    'AdminAttributesGroups',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'message' => $message,
                'validation_table' => $create_validation_table,
                'directly_sl_cronLink' => $SLimport->createSLPluginCronUrl(false, false)
            )
        );


        return $this->module->display(_PS_MODULE_DIR_ . 'saleslayerimport', 'views/templates/admin/howtouse.tpl');
    }


    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'How to use Sales Layer';
    }
}

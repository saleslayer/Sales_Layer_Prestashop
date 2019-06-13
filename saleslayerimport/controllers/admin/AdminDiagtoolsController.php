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

class AdminDiagtoolsController extends ModuleAdminController
{
    public $SLimport;

    public function __construct()
    {
        $this->show_toolbar = true;
        $this->display = 'Sales layer diagnostics tools';
        $this->meta_title = 'Sales layer diagnostics tools';
        parent::__construct();
        $this->bootstrap = true;
        $this->SLimport = new SalesLayerImport();
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
        $option_debug = array(
            'Off' => 0,
            'On level 1' => 1,
            'On Level 2' => 2,
            'On Level 3' => 3,
        );
        $option_debug_output = '';
        foreach ($option_debug as $option_key => $option_value) {
            $option_debug_output .= '<option value="' . $option_value . '" ' .
                ($option_value == $this->SLimport->debugmode ? 'selected' : '') .
                ' >' . $option_key . '</option>';
        }

        $this->context->smarty->assign(
            array(
                'ajax_link' => $this->context->link->getModuleLink('saleslayerimport', 'ajax'),
                'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                'diag_link' => $this->context->link->getModuleLink('saleslayerimport', 'diagtools'),
                'delete_link' => $this->context->link->getModuleLink('saleslayerimport', 'deletelogs'),
                'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                'SLY_DEBUGMODE_SELECT' => $option_debug_output,
            )
        );

        return $this->module->display(_PS_MODULE_DIR_ . 'saleslayerimport', 'views/templates/admin/showdebug.tpl');
    }

    public function setMedia(
        $isNewTheme = false
    ) {
        return parent::setMedia($isNewTheme);
    }

    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'Sales layer Diagnostics';
    }
}

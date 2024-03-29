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

class AddConnectorsController extends ModuleAdminController
{
    public $SLimport;

    public function __construct()
    {
        $this->show_toolbar = true;
        $this->display = 'Add New Conector Sales Layer';
        $this->meta_title = 'Add New Conector';
        $this->toolbar_title = 'How to use Sales Layer';
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
        $conn_code = Tools::getValue('api_client');
        $conn_secret = Tools::getValue('api_key');
        if (Tools::version_compare(_PS_VERSION_, '1.7.7.5', '>=') == true) {
            ShopConstraint::shop($this->SLimport->shop_loaded_id);
        }
        $this->context->smarty->assign(
            array(
                'ajax_link' => $this->context->link->getModuleLink(
                    'saleslayerimport',
                    'ajax',
                    [],
                    Configuration::get('PS_SSL_ENABLED'),
                    null,
                    $this->SLimport->shop_loaded_id
                ),
                'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                'diag_link' => $this->context->link->getModuleLink(
                    'saleslayerimport',
                    'diagtools',
                    [],
                    Configuration::get('PS_SSL_ENABLED'),
                    null,
                    $this->SLimport->shop_loaded_id
                ),
                'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                'link_all_conectors' => $this->context->link->getAdminLink(
                    'AllConnectors',
                    true,
                    array(),
                    array( 'id_shop' => $this->context->shop->id)
                ),
                'api_client' => $conn_code,
                'api_key' => $conn_secret,
            )
        );

        return $this->module->display(_PS_MODULE_DIR_ . 'saleslayerimport', 'views/templates/admin/index.tpl');
    }

    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'How to use Sales Layer';
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws PrestaShopExceptionCore
     */
    public function postProcess()
    {
        if (Tools::isSubmit('addnewconector')) {
            $conn_code = Tools::getValue('api_client');
            $conn_secret = Tools::getValue('api_key');

            $api = new SalesLayerConn($conn_code, $conn_secret);
            // $api->set_API_version('1.16');
            ini_set('max_execution_time', 14400);
            ini_set('memory_limit', '-1');
            $api->setGroupMulticategory(true);
            $api->getInfo(time() - 5);
            if ($api->hasResponseError()) {
                $error_MESSAGE = $api->getResponseErrorMessage();

                $this->SLimport->debbug('## Error. Conexion error num->' . print_r(
                    $api->getResponseErrorMessage(),
                    1
                ) . ' ->' . print_r($error_MESSAGE, 1), 'syncdata', true);

                $this->context->smarty->assign(
                    array(
                        'SLY_HAS_ERRORS' => true,
                        'sly_conex_error' => Tools::ucfirst($error_MESSAGE),
                        'api_client' => $conn_code,
                        'api_key' => $conn_secret,
                        'ajax_link' => $this->context->link->getModuleLink(
                            'saleslayerimport',
                            'ajax',
                            [],
                            Configuration::get('PS_SSL_ENABLED'),
                            null,
                            $this->SLimport->shop_loaded_id
                        ),
                        'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                        'diag_link' => $this->context->link->getModuleLink(
                            'saleslayerimport',
                            'diagtools',
                            [],
                            Configuration::get('PS_SSL_ENABLED'),
                            null,
                            $this->SLimport->shop_loaded_id
                        ),
                        'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                        'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/logos/',
                        'link_all_conectors' => $this->context->link->getAdminLink(
                            'AllConnectors',
                            true,
                            array(),
                            array( 'id_shop' => $this->context->shop->id)
                        )
                    )
                );

                $this->SLimport->debbug('## Error. Conexion error num->' . print_r(
                    $api->getResponseErrorMessage(),
                    1
                ) . ' ->' . print_r($error_MESSAGE, 1), 'syncdata', true);

                return $this->module->display(
                    _PS_MODULE_DIR_ . 'saleslayerimport',
                    'views/templates/admin/index.tpl'
                );
            } else {
                $response_connector_schema = $api->getResponseConnectorSchema();
                $response_connector_type = $response_connector_schema['connector_type'];

                if ($response_connector_type != $this->SLimport->connector_type) {
                    $error_MESSAGE = 'The version you are trying to connect is not the same';

                    $this->SLimport->debbug(
                        '## Error. Conexion error num->' . print_r(
                            $api->getResponseErrorMessage(),
                            1
                        ) . ' ->' . print_r($error_MESSAGE, 1) . ' version of connector from response ->' .
                        $response_connector_type . '  expected version -> ' .
                        $this->SLimport->connector_type,
                        'syncdata',
                        true
                    );

                    $this->context->smarty->assign(
                        array(
                            'SLY_HAS_ERRORS' => true,
                            'sly_conex_error' => $error_MESSAGE,
                            'api_client' => $conn_code,
                            'api_key' => $conn_secret,
                            'ajax_link' => $this->context->link->getModuleLink(
                                'saleslayerimport',
                                'ajax',
                                [],
                                Configuration::get('PS_SSL_ENABLED'),
                                null,
                                $this->SLimport->shop_loaded_id
                            ),
                            'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                            'diag_link' => $this->context->link->getModuleLink(
                                'saleslayerimport',
                                'diagtools',
                                [],
                                Configuration::get('PS_SSL_ENABLED'),
                                null,
                                $this->SLimport->shop_loaded_id
                            ),
                            'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                            'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                            'link_all_conectors' => $this->context->link->getAdminLink(
                                'AllConnectors',
                                true,
                                array(),
                                array( 'id_shop' => $this->context->shop->id)
                            )
                        )
                    );

                    return $this->module->display(
                        _PS_MODULE_DIR_ . 'saleslayerimport',
                        'views/templates/admin/index.tpl'
                    );
                } else {
                    try {
                        $this->SLimport->checkFreeSpaceMemory();
                        $conector_data = [
                            'conn_code' => $conn_code,
                            'conn_secret' => $conn_secret
                        ];
                        try {
	                        $this->SLimport->addConnectors($conector_data);
                        } catch (Exception $e) {
                            $this->SLimport->debbug('Error in update new connector ->' . print_r($e->getMessage(), 1) .
                                                    ' in line ->' . $e->getLine(), 'syncdata', true);
                        }
                      /*  $this->SLimport->setConnectorData($conn_code, 'last_update', 0);
                        $this->SLimport->setConnectorData($conn_code, 'comp_id', $api->getResponseCompanyId());*/
						$data = [
							'last_update'=> 0,
							'comp_id' => $api->getResponseCompanyId()
						];

	                    $this->SLimport->setConnectors($conn_code,$data);


                    } catch (Exception $e) {
                        $this->SLimport->debbug('## Error. Adding new conector problem found->' . $e->getMessage());
                        $this->SLimport->debbug('## Error. track->' . print_r($e->getTrace(), 1));
                    }

                    Tools::redirectAdmin($this->context->link->getAdminLink(
                        'AllConnectors',
                        true,
                        array(),
                        array( 'id_shop' => $this->context->shop->id)
                    ));
                }
            }
        }
    }
}

<?php


//echo 'entro aqui';
class AddConnectorsController extends ModuleAdminController
{//  /// extends ModuleFrontController
        public $SLimport;
    public function __construct()
    {
        $this->show_toolbar = true;
        $this->display = 'Add New Conector Sales Layer';
        $this->meta_title = 'How to use';
        $this->toolbar_title ='How to use Sales Layer';
        parent::__construct();
        $this->bootstrap = true;
        $this->SLimport = new SalesLayerImport();
    }


    public function init(){
        parent::init();
    }
    public function initContent(){
        parent::initContent();
    }
    public function renderList()
    {
        $conn_code = Tools::getValue('api_client');
        $conn_secret      = Tools::getValue('api_key');
        $this->context->smarty->assign(array(
            'ajax_link'=>$this->context->link->getModuleLink('saleslayerimport','ajax'),
            'token'    =>substr(Tools::encrypt('saleslayerimport'), 0, 10),
            'diag_link'=>$this->context->link->getModuleLink('saleslayerimport', 'diagtools'),
            'SLY_ASSETS_PATH' => $this->SLimport->module_path,
            'SLY_LOGOS_PATH' => $this->SLimport->module_path.'config/logos/',
            'link_all_conectors'=>$this->context->link->getAdminLink('AllConnectors'),
            'api_client'     => $conn_code,
            'api_key'        => $conn_secret
        ));

        return $this->module->display(_PS_MODULE_DIR_.'saleslayerimport', 'views/templates/admin/index.tpl');

    }
    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'How to use Sales Layer';
    }


    public function postProcess()
    {
        if (Tools::isSubmit('addnewconector'))
        {

            $conn_code = Tools::getValue('api_client');
            $conn_secret      = Tools::getValue('api_key');

            $api = new SalesLayer_Conn($conn_code, $conn_secret);
            // $api->set_API_version('1.16');
            $api->set_group_multicategory(true);
            $api->get_info();

            if ($api->has_response_error()) {
                $this->context->smarty->assign(array(
                    'SLY_HAS_ERRORS' => true,
                    'api_client'     => $conn_code,
                    'api_key'        => $conn_secret,
                    'ajax_link'=>$this->context->link->getModuleLink('saleslayerimport','ajax'),
                    'token'    =>substr(Tools::encrypt('saleslayerimport'), 0, 10),
                    'diag_link'=>$this->context->link->getModuleLink('saleslayerimport', 'diagtools'),
                    'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                    'SLY_LOGOS_PATH' => $this->SLimport->module_path.'config/logos/',
                    'link_all_conectors'=>$this->context->link->getAdminLink('AllConnectors'),
                ));
                return $this->module->display(_PS_MODULE_DIR_.'saleslayerimport', 'views/templates/admin/index.tpl');

            }else{
                $response_connector_schema = $api->get_response_connector_schema();
                $response_connector_type = $response_connector_schema['connector_type'];

                if ($response_connector_type != $this->SLimport->connector_type) {

                    $this->context->smarty->assign(array(
                        'SLY_HAS_ERRORS' => true,
                        'api_client'     => $conn_code,
                        'api_key'        => $conn_secret,
                        'ajax_link'=>$this->context->link->getModuleLink('saleslayerimport','ajax'),
                        'token'    =>substr(Tools::encrypt('saleslayerimport'), 0, 10),
                        'diag_link'=>$this->context->link->getModuleLink('saleslayerimport', 'diagtools'),
                        'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                        'SLY_LOGOS_PATH' => $this->SLimport->module_path.'config/logos/',
                        'link_all_conectors'=>$this->context->link->getAdminLink('AllConnectors'),
                    ));
                    return $this->module->display(_PS_MODULE_DIR_.'saleslayerimport', 'views/templates/admin/index.tpl');

                }else{

                    try{

                        $this->SLimport->reasignMemoryNeccesary();
                        $this->SLimport->sl_updater->set_identification($conn_code, $conn_secret);
                        $this->SLimport->sl_updater->get_connectors_info($conn_code);
                        $this->SLimport->sl_updater->update(true,null,true);
                        $this->SLimport->set_connector_Data($conn_code,'last_update',0);

                    }catch(Exception $e){

                        $this->SLimport->debbug('## Error. Adding new conector problem found->'.$e->getMessage());
                        $this->SLimport->debbug('## Error. track->'.print_r($e->getTrace(),1));
                    }

                    Tools::redirectAdmin($this->context->link->getAdminLink('AllConnectors'));

                }

            }




        }
    }




}
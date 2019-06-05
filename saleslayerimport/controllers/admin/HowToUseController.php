<?php



class HowToUseController extends ModuleAdminController
{

    public function __construct()
    {
        $this->show_toolbar = true;
        $this->display = 'How to use Sales Layer';
        $this->meta_title = 'How to use';
        $this->toolbar_title ='How to use Sales Layer';
        parent::__construct();
        $this->bootstrap = true;
    }


    public function init(){
        parent::init();
    }
    public function initContent(){
        parent::initContent();
    }
    public function renderList()
    {
        $SLimport = new SalesLayerImport();
        $message = '';
        $return_table = array();
        $extension_needed = array(
            'curl_version'        =>array('test'=>'function_exists','public_name'=>'PHP cURL Installed'),
            'cronjobs'            =>array('test'=>'module','public_name' =>'Prestashop module Cronjobs installed'),
            'testSlcronExist'     =>array('test'=>'setfunction','public_name' =>'There is a task to execute Sales Layer cron job in Prestashop'),
            'verifySLcronRegister'=>array('test'=>'setfunction','public_name' =>'Registered prestashop cronjob activity','return'=>'stat','additional_message'=>'message'),
        );

        if(count($extension_needed)){
            foreach($extension_needed as $extension_name => $extension_value){
                if($extension_value['test']== 'function_exists'){
                    if(function_exists($extension_name)){
                        $result_test = 'fa-check text-success';
                    }else{
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '.$result_test.'" aria-hidden="true"></i>';
                }elseif($extension_value['test']== 'module'){
                    $file_extension = _PS_MODULE_DIR_.DIRECTORY_SEPARATOR.$extension_name.DIRECTORY_SEPARATOR.$extension_name.'.php';
                    if(file_exists($file_extension)){
                        $result_test = 'fa-check text-success';
                    }else{
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '.$result_test.'" aria-hidden="true"></i>';
                }elseif($extension_value['test'] == 'setfunction'){
                    $return_stat = $SLimport->{$extension_name}();
                    if(isset($extension_value['return'])){
                        $return_response = $return_stat[$extension_value['return']];
                    }else{
                        $return_response = $return_stat;
                    }
                    if($return_response){
                        $result_test = 'fa-check text-success';
                    }else{
                        if(isset($extension_value['additional_message'])){
                            $message .= $return_stat[$extension_value['additional_message']];
                        }
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa '.$result_test.'" aria-hidden="true"></i>';
                }
            }
        }
        if(count($return_table)){
            $create_validation_table = '<table class="table">';
            foreach($return_table as $table_name => $table_value){
                $create_validation_table .='<tr>';
                $create_validation_table .='<td>';
                $create_validation_table .= $table_name;
                $create_validation_table .='<td>';
                $create_validation_table .='<td>';
                $create_validation_table .= $table_value;
                $create_validation_table .='<td>';
                $create_validation_table .='</tr>';
            }
            $create_validation_table .= '</table>';
        }



        $this->context->smarty->assign(array(
            'SLY_LOGOS_PATH'     => $SLimport->module_path.'config/logos/',
            'SLY_ASSETS_PATH'    =>$SLimport->module_path,
            'link_all_connectors'=>$this->context->link->getAdminLink('AllConnectors'),
            'add_connectors'     =>$this->context->link->getAdminLink('AddConnectors'),
            'link_how_to_use'    =>$this->context->link->getAdminLink('HowToUse'),
            'link_diagnostics'   =>$this->context->link->getAdminLink('AdminDiagtools'),
            'plugin_name'        =>ucfirst($SLimport->name),
            'admin_attributes'   =>$this->context->link->getAdminLink('AdminAttributesGroups'),
            'culr_link'          => $this->context->link->getAdminLink('AdminCronJobs',false).'&token='.Configuration::getGlobalValue('CRONJOBS_EXECUTION_TOKEN'),
            'message'            =>$message,
            'validation_table'   =>$create_validation_table
        ));


        return $this->module->display(_PS_MODULE_DIR_.'saleslayerimport', 'views/templates/admin/howtouse.tpl');

    }
    public function initToolBarTitle()
    {
        $this->toolbar_title[] = 'Administration';
        $this->toolbar_title[] = 'How to use Sales Layer';
    }


}
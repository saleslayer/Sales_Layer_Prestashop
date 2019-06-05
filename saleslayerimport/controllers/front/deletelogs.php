<?php
class saleslayerimportdeletelogsModuleFrontController extends ModuleFrontController
{
    private $SLimport;
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
        $this->SLimport = new SalesLayerImport();
    }
    public function displayAjax()
    {
        if(substr(Tools::encrypt('saleslayerimport'), 0, 10) !=  Tools::getValue('token')){
            $return = array();
            $return['message_type'] = 'error';
            $return['message']      = 'Invalid Token.';
            die(Tools::jsonEncode($return));
        }


        $files_to_delete =    Tools::getValue('logfilesfordelete');

        $result = $this->deleteSLLogFile($files_to_delete);

        $array_return = array();

        if ($result){

            $array_return['message_type'] = 'success';

        }else{

            $array_return['message_type'] = 'error';

        }

        die(Tools::jsonEncode($array_return));
    }

    /**
     * Function to delete Sales Layer log file.
     * @return boolean
     */
    public function deleteSLLogFile($files_to_delete){

        $log_dir_path =  $this->SLimport->log_module_path;

        if (!is_array($files_to_delete)){ $files_to_delete = array($files_to_delete); }

        if (empty($files_to_delete)){ return false; }

        foreach ($files_to_delete as $file_to_delete) {

            $file_path = $log_dir_path.'/'.$file_to_delete;

            if (file_exists($file_path)){

                unlink($file_path);

            }

        }

        return true;

    }

}
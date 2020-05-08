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
    private $showtable = '';

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

    private function formatTable($array)
    {
        $tr = '<tr>';
        foreach ($array as $tr_value) {
            $tr .= '<td>';
            $tr .= $tr_value;
            $tr .= '</td>';
        }

        $tr .= '</tr>';

        $this->showtable .= $tr;
    }

    public function generateServerInfo()
    {
        $return = '';
        $return .= '<h4>Server Info</h4>';
        $return .= '<table class="table">';
        $this->showtable = $return;

        if ($this->SLimport->i_am_a_developer) {
            /**
             * Refresh integrity of plugin
             */
            $this->SLimport->createIntegrity();
        }


        $array_toshow = ['Php version',phpversion()];
        $this->formatTable($array_toshow);

        $array_toshow = ['Plugin Version',$this->SLimport->version];
        $this->formatTable($array_toshow);

        $array_toshow = ['max_execution_time',ini_get('max_execution_time')];
        $this->formatTable($array_toshow);

        $array_toshow = ['Server Software',$_SERVER['SERVER_SOFTWARE']];
        $this->formatTable($array_toshow);

        $array_toshow = ['intl.error_level',ini_get('intl.error_level')];
        $this->formatTable($array_toshow);

        $array_toshow = ['Server name',$_SERVER['SERVER_NAME']];
        $this->formatTable($array_toshow);

        $array_toshow = ['Server http_host',$_SERVER['HTTP_HOST']];
        $this->formatTable($array_toshow);

        $array_toshow = ['Prestashop base url',_PS_BASE_URL_ . __PS_BASE_URI__];
        $this->formatTable($array_toshow);

        $array_toshow = ['Prestashop getModuleLink',
            $this->context->link->getModuleLink(
                'saleslayerimport',
                'ajax',
                [],
                null,
                null,
                $this->SLimport->shop_loaded_id
            )];
        $this->formatTable($array_toshow);

        /* $array_toshow = ['Prestashop overwriteOrigin Url',$this->SLimport->overwriteOriginDomain(
             $this->context->link->getModuleLink('saleslayerimport', 'ajax',
                        [],
                        null,
                        null,
                        $this->SLimport->shop_loaded_id
         )];
         $this->formatTable($array_toshow);*/

        $array_toshow = ['Prestashop getAdminLink',$this->context->link->getAdminLink('AllConnectors')];
        $this->formatTable($array_toshow);

        if (!is_writable(DEBBUG_PATH_LOG)) {
            $class = "text-danger";
            $text = 'You cannot create log files to this directory';
        } else {
            $class = "text-success";
            $text = 'Great! Is writable!';
        }
        $array_toshow = ['Log directory','<span class="' . $class . '" title="' . $text .
                                         '">' . DEBBUG_PATH_LOG . '</span>'];
        $this->formatTable($array_toshow);

        if (!is_writable(_PS_TMP_IMG_DIR_)) {
            $class = "text-danger";
            $text = 'You cannot create temporary images to this directory';
        } else {
            $class = "text-success";
            $text = 'Great! Is writable!';
        }
        $array_toshow = ['Temp image directory','<span class="' . $class . '" title="' . $text . '">' .
                                                _PS_TMP_IMG_DIR_ . '</span>'];
        $this->formatTable($array_toshow);

        $array_toshow = ['Cron Execution Frequency',gmdate(
            "H:i:s",
            $this->SLimport->getConfiguration('CRON_MINUTES_FREQUENCY')
        )];
        $this->formatTable($array_toshow);

        $array_toshow = ['Latest Cron Execution','<span title="Server time">' .
                                                 date(
                                                     'd-m-Y H:i:s',
                                                     $this->SLimport->getConfiguration('LATEST_CRON_EXECUTION')
                                                 ) . '</span>'];
        $this->formatTable($array_toshow);

        if (!$this->SLimport->compareIntegrity()) {
            $class = "text-danger";
            $text = 'The files are not properly installed, install the plugin to correct this bug.';
        } else {
            $class = "text-success";
            $text = 'All files are well installed.';
        }

        $array_toshow = ['Module Integrity','<span class="' . $class . '" title="' . $text .
                                             '">' . $text .
                                            ' &nbsp;&nbsp;&nbsp;&nbsp;Control token: ' .
                                            hash('crc32', json_encode($this->SLimport->loadIntegrity())) .
                                            ' &nbsp;&nbsp;&nbsp;&nbsp;Generated: ' . date(
                                                'd/m/Y H:i:s',
                                                filemtime($this->SLimport->integrityPathDirectory .
                                                $this->SLimport->integrityFile)
                                            ) . ' </span>'];
        $this->formatTable($array_toshow);

        $array_toshow = ['Ajax connections','<span id="ajaxtest" class="text-danger" ' .
                                            ' title="Ajax connections">It does not work</span>'];
        $this->formatTable($array_toshow);

        $class = "text-success";
        $text  = 'It has not yet been possible to analyze if there is enough memory to receive data.';
        $max_memory_usage =  $this->SLimport->getConfiguration('MAX_MEMORY_USAGE');
        $free_memory = $this->SLimport->checkServerUse();
        if ($max_memory_usage) {
            $array_toshow = ['Max used memory',
                '<span title="Memory used in its largest synchronization for download data.">' .
                                                                 $max_memory_usage . ' Mb</span>'];
            $this->formatTable($array_toshow);



            if ($max_memory_usage > $free_memory) {
                $class = "text-danger";
                $text = 'For synchronization you needed more memory than is available now.';
            } else {
                $class = "text-success";
                $text = 'There is supposedly enough memory to receive data.';
            }
        }

        $array_toshow = ['Free memory','<span class="' . $class . '" title="' . $text . '">' .
                                       $free_memory['frmem'] . ' Mb</span>'];
        $this->formatTable($array_toshow);




        $this->showtable .= '</table>';

        $loaded_extensions = get_loaded_extensions();
        $this->showtable .= '<h4>Php Extensions loaded</h4>';
        $optimal = ['Core','date','libxml','openssl','pcre','zlib','filter','hash','Reflection','SPL','session',
            'standard','apache2handler','mysqlnd','PDO','xml','calendar','ctype','curl','dom','mbstring','fileinfo',
            'ftp','gd','gettext','iconv','imagick','intl','json','exif','mcrypt','mysqli','pdo_mysql','Phar','posix',
            'readline','shmop','SimpleXML','soap','sockets','sysvmsg','sysvsem','sysvshm','tokenizer','wddx',
            'xmlreader','xmlrpc','xmlwriter','xsl','zip','Zend OPcache'];

        foreach ($optimal as $key => $extensions) {
            if (in_array($extensions, $loaded_extensions, false)) {
                $keysearch = array_search($extensions, $loaded_extensions);
                unset($loaded_extensions[$keysearch]);
                $color = 'badge-success';
                $text = 'We use the same module';
            } else {
                $color = 'badge-secondary';
                $text = 'We use this module';
            }
            unset($optimal[$key]);
            $this->showtable .= '<span class="badge ' . $color . ' mar-5" title="' . $text . '">';
            $this->showtable .= $extensions;
            $this->showtable .= '</span>';
        }

        foreach ($loaded_extensions as $extension_loaded) {
            $this->showtable .= '<span class="badge mar-5" title="You have this php module installed">';
            $this->showtable .= $extension_loaded;
            $this->showtable .= '</span>';
        }
        return $this->showtable;
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
        $option_performance = array(
                'max' => 10,
                '9' => 9,
                '8' => 8,
                '7' => 7,
                '6' => 6,
                '5' => 5,
                '4 default' => 4,
                '3' => 3,
                'low' => 2
        );

        $option_performance_output = '';
        $first_arr = explode('.', (string) $this->SLimport->cpu_max_limit_for_retry_call);
        foreach ($option_performance as $option_key => $option_value) {
            $option_performance_output .= '<option value="' . $option_value . '" ' .
                                    ($option_value == $first_arr[0] ? 'selected' : '') .
                                    ' >' . $option_key . '</option>';
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
                    )
                ,
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
                'delete_link' =>
                    $this->context->link->getModuleLink(
                        'saleslayerimport',
                        'deletelogs',
                        [],
                        null,
                        null,
                        $this->SLimport->shop_loaded_id
                    ),
                'SLY_ASSETS_PATH' => $this->SLimport->module_path,
                'SLY_LOGOS_PATH' => $this->SLimport->module_path . 'views/img/',
                'SLY_DEBUGMODE_SELECT' => $option_debug_output,
                'SLY_PERFORMANCE_SELECT' => $option_performance_output,
                'log_files' => $this->showlogfiles(),
                'server_aditionalInfo' => ($this->SLimport->debugmode ? $this->generateServerInfo() : '')
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
    private function showlogfiles()
    {
        $ignored_files = array('index.php','.','..');
        $files = array();
        $log_dir_path = $this->SLimport->log_module_path;

        if (!file_exists($log_dir_path)) {
            if (! mkdir($log_dir_path, 0777, true) && ! is_dir($log_dir_path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $log_dir_path));
            }
        }

        $log_folder_files = array_slice(scandir($log_dir_path), 2);

        if (!empty($log_folder_files)) {
            foreach ($log_folder_files as $log_folder_file) {
                if (in_array($log_folder_file, $ignored_files, false)) {
                    continue;
                }
                $files[] = $log_folder_file;
            }
        }
        /*titles*/
        $table = '';
        if (count($files)) {
            foreach ($files as $file) {
                $table .= '<tr class="filesnamestr table-active">';
                /*checkbox*/
                $table .= '<td>';
                $table .= '<input type="checkbox" name="file[]" value="' . $file . '">';
                $table .= '</td>';
                /*filename*/
                $table .= '<td>';
                $table .= $file;
                $table .= '</td>';
                /*file info*/
                $table .= '<td>';
                $table .= preg_replace(
                    '/(\d)(?=(\d{3})+(?!\d))/',
                    ' ',
                    $this->countLines($this->SLimport->log_module_path . $file)
                );
                $table .= '</td>';
                /*warnings*/
                $table .= '<td>';
                $table .= '<span title="Your server configuration does not' .
                          ' allow ajax queries to show number of errors.">?</span>';
                $table .= '</td>';
                /* errors */
                $table .= '<td>';
                $table .= '<span title="For show more information download the file">?</span>';
                $table .= '</td>';
                /*downloads*/
                $table .= '<td>';
                $table .= '<span class="btn btn-xs" onclick=downloadlogfile("' . $file . '");>';
                $table .= '<i class="fa fa-download" aria-hidden="true"></i>';
                $table .= '</span>';
                $table .= '</td>';

                $table .= '</tr>';
            }
        }
        return $table;
    }
    private function countLines(
        $file
    ) {
        $linecount = 0;
        $handle = fopen($file, 'rb');
        while (!feof($handle)) {
            fgets($handle);
            $linecount++;
        }
        fclose($handle);
        return $linecount;
    }

    public function postProcess()
    {
        $debug_mode = Tools::getValue('debugmode');
        if ($debug_mode != '') {
            if (is_numeric($debug_mode)) {
                $this->SLimport->setDebugMode($debug_mode);
            }
        }
        $performance_limit = Tools::getValue('performance');
        if ($performance_limit != '') {
            if (is_numeric($performance_limit)) {
                $performance_limit = (int) $performance_limit . '.00';
                $this->SLimport->setPerformanceLimit($performance_limit);
            }
        }
        $download_log_filename = Tools::getValue('download');
        if ($download_log_filename != '') {
            $this->downloadLogFile($download_log_filename);
            return true;
        }
        $deletefiles = Tools::getValue('fordelete');
        if (!empty($deletefiles)) {
            foreach ($deletefiles as $filefordelete) {
                $log_dir_path = $this->SLimport->log_module_path;
                $file_path = $log_dir_path . $filefordelete;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }
    private function downloadLogFile($namefile)
    {
        $log_dir_path = $this->SLimport->log_module_path;
        $file_path = $log_dir_path . $namefile;
        if (file_exists($file_path)) {
            header('Content-type: application/octet-stream');
            header('Content-Length: ' . filesize($file_path));
            header('Content-Disposition: attachment; filename=' . $namefile . '');
            while (ob_get_level()) {
                ob_end_clean();
            }
            readfile($file_path);
            exit();
        } else {
            header("HTTP/1.0 404 Not Found");
        }
    }
}

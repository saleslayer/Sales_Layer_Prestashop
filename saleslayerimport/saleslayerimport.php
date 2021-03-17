<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * Sales-layer PIM Plugin for import categories, products and variants to Prestashop
 *
 * @author    Sales Layer
 * @copyright 2019 Sales Layer
 * @license   License: GPLv3  License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_PS_VERSION_') or exit;

require dirname(__FILE__).DIRECTORY_SEPARATOR.'config'
    .DIRECTORY_SEPARATOR.'config.php';
require dirname(__FILE__).DIRECTORY_SEPARATOR.'controllers'
    .DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'SalesLayer-Conn.php';
require dirname(__FILE__).DIRECTORY_SEPARATOR.'controllers'
    .DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'SalesLayer-Updater.php';
if (extension_loaded('PDO')) {
    if (!class_exists('slyrSQL')) {
        include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'controllers'
            .DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'class.DBPDO.php';
    }
} elseif (!class_exists('slyrSQL')) {
    include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'controllers'
        .DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'class.MySQL.php';
}

class SalesLayerImport extends Module
{

    ###############################################################

    #  Configuration of tables

    ###############################################################

    public $slyr_table = _DB_PREFIX_ . 'slyr_category_product';
    public $slyr_images_table = _DB_PREFIX_ . 'slyr_images';
    public $saleslayer_syncdata_table = _DB_PREFIX_ . 'slyr_syncdata';
    public $saleslayer_syncdata_flag_table = _DB_PREFIX_ . 'slyr_syncdata_flag';
    public $saleslayer_aditional_config = _DB_PREFIX_ . 'slyr_additional_config';
    public $category_table = _DB_PREFIX_ . 'category';
    public $category_product_table = _DB_PREFIX_ . 'category_product';
    public $category_lang_table = _DB_PREFIX_ . 'category_lang';
    public $product_table = _DB_PREFIX_ . 'product';
    public $product_shop_table = _DB_PREFIX_ . 'product_shop';
    public $attribute_group_table = _DB_PREFIX_ . 'attribute_group';
    public $attribute_group_lang_table = _DB_PREFIX_ . 'attribute_group_lang';
    public $attribute_group_shop_table = _DB_PREFIX_ . 'attribute_group_shop';
    public $attribute_table = _DB_PREFIX_ . 'attribute';
    public $attribute_lang_table = _DB_PREFIX_ . 'attribute_lang';
    public $attribute_shop_table = _DB_PREFIX_ . 'attribute_shop';
    public $product_attribute_table = _DB_PREFIX_ . 'product_attribute';
    public $product_attribute_shop_table = _DB_PREFIX_ . 'product_attribute_shop';
    public $product_attribute_image_table = _DB_PREFIX_ . 'product_attribute_image';
    public $product_attribute_combination_table = _DB_PREFIX_ . 'product_attribute_combination';
    public $product_tax_rule_table = _DB_PREFIX_ . 'tax_rule';
    public $product_tax_rules_group_table = _DB_PREFIX_ . 'tax_rules_group';
    public $product_tax_rules_group_shop_table = _DB_PREFIX_ . 'tax_rules_group_shop';
    public $product_tax_table = _DB_PREFIX_ . 'tax';
    public $prestashop_cron_table = _DB_PREFIX_ . 'cronjobs';
    public $feature_product_table = _DB_PREFIX_ . 'feature_product';
    public $feature_shop_table = _DB_PREFIX_ . 'feature_shop';
    public $shop_table = _DB_PREFIX_ . 'shop';
    public $image_table = _DB_PREFIX_ . 'image';
    public $image_lang_table = _DB_PREFIX_ . 'image_lang';
    public $image_shop_table = _DB_PREFIX_ . 'image_shop';
    public $attachment_table = _DB_PREFIX_ . 'attachment';
    public $pack_table = _DB_PREFIX_ . 'pack';
    public $seosa_product_labels_table = _DB_PREFIX_ . 'seosaproductlabels';
    public $seosa_product_labels_location_table = _DB_PREFIX_ . 'seosaproductlabels_location';

    ###############################################################

    #  Show Developer buttons

    ###############################################################

    public $i_am_a_developer = false;

    ###############################################################

    #   Additional default configurations

    ###############################################################

    public $ignore_hex_color_code               = false;
    public $create_new_attributes               = true;
    public $create_new_features_as_custom       = false;
    public $deleteProductOnHide                 = false;
    // only for debug, false == deactivate product, true == delete product
    public $rewrite_execution_frequency         = true;
    public $log_module_path                     = _PS_MODULE_DIR_ . 'saleslayerimport/logs/';
    public $plugin_dir                          = _PS_MODULE_DIR_  . 'saleslayerimport/';
    public $integrityFile                       = 'integrity.json';
    public $integrityPathDirectory              = '';
    public $cpu_max_limit_for_retry_call        = 4.00;
    public $timeout_for_run_process_connections = 5000;
    private $max_execution_time                 = 290;
    private $memory_min_limit                   = 300;
    private $sql_insert_limit                   = 5;
    private $logfile_delete_days                = 15;// After so many days the debug files will be deleted


    ##############################################################

    #   Default declarations

    ##############################################################

    public $conector_shops_ids               = [];
    public $connector_type                   = '';
    public $sql_to_insert                    = array();
    public $default_category_id              = '';
    public $sl_data_schema                   = array();
    public $product_accessories              = false;
    public $listAttrGroupCased               = array();
    public $format_value_arrays              = array();
    public $format_value_fields              = array();
    public $final_format_array               = array();
    public $category_images_sizes            = array();
    public $product_images_sizes             = array();
    public $format_images_sizes              = array();
    public $connector_shops                  = array();
    public $product_format_has_frmt_image    = false;
    public $product_format_has_format_images = false;
    public $first_sync_shop                  = true;
    public $defaultLanguage                  = 0;
    public $currentLanguage                  = '';
    public $comp_id                          = '';
    public $sl_catalogues;
    public $sl_products;
    public $sl_variants;
    public $shop_languages;
    public $sl_time_ini_process;
    public $sl_updater;
    public $processing_connector_id;
    public $module_path;
    public $debugmode;
    public $cron_frequency;
    public $sync_categories                 = true;
    private $sql_items_delete               = array();
    private $debug_occurence                = array();
    private $updated_elements               = array();
    private $syncdata_pid;
    private $end_process;
    private $block_retry_call               = false;
    private $sl_time_ini_auto_sync_process;
    private $sl_time_ini_sync_data_process;
    private $load_cron_time_status;
    public $shop_loaded_id;

    ############################################################

    # Declaration of known predefined fields, all others will be converted into features and attributes

    ############################################################

    public $product_format_base_fields
        = array(
            'ID',
            'ID_products',
            'frmt_image',
            'location',
            'ean13',
            'upc',
            'quantity',
            'reference',
            'wholesale_price',
            'price',
            'price_tax_incl',
            'price_tax_excl',
            'ecotax',
            'weight',
            'unit_price_impact',
            'minimal_quantity',
            'default_on',
            'available_date',
            'mostrar',
            'format_images',
            'format_supplier',
            'format_supplier_reference',
            'format_alt',
            'low_stock_threshold'
        );
    public $predefined_product_fields
        = array(
            'product_type',
            'product_name',
            'product_reference',
            'product_ean13',
            'product_upc',
            'product_active',
            'product_visibility',
            'product_available_for_order',
            'product_show_price',
            'product_available_online_only',
            'product_condition',
            'product_description_short',
            'product_description',
            'product_price',
            'product_id_tax_rules_group',
            'product_price_tax_incl',
            'product_discount_1',
            'product_discount_1_type',
            'product_discount_1_quantity',
            'product_discount_1_from',
            'product_discount_1_to',
            'product_discount_2',
            'product_discount_2_type',
            'product_discount_2_quantity',
            'product_discount_2_from',
            'product_discount_2_to',
            'wholesale_price',
            'meta_title',
            'meta_description',
            'friendly_url',
            'category_sl_default',
            'product_accessories',
            'product_manufacturer',
            'product_width',
            'product_height',
            'product_depth',
            'product_weight',
            'additional_shipping_cost',
            'product_carrier',
            'product_quantity',
            'product_minimal_quantity',
            'product_out_of_stock',
            'available_now',
            'available_later',
            'product_image',
            'product_available_date',
            'product_creation_date',
            'product_customizable',
            'pack_product_1',
            'pack_format_1',
            'pack_quantity_1',
            'product_supplier_1',
            'product_supplier_reference_1',
            'product_attachment',
            'product_tag',
            'estimacion',
            'unit_price_ratio',
            'unity',
            'product_alt',
            'low_stock_threshold',
            'redirect_type',
            'id_type_redirected',
            'location'
        );
    public $predefined_partial_cut_fields
        = array(
            'pack_product_',
            'pack_format_',
            'pack_quantity_',
            'product_supplier_',
            'product_supplier_reference_'
        );


    /**
     * SalesLayerImport constructor.
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */

    public function __construct()
    {
        require_once 'controllers/admin/SalesLayerPimUpdate.php';
        require_once 'controllers/admin/SlCatalogues.php';
        require_once 'controllers/admin/SlProducts.php';
        require_once 'controllers/admin/SlProductDelete.php';
        require_once 'controllers/admin/SlVariants.php';

        $this->name = 'saleslayerimport';
        $this->tab = 'administration';
        $this->version = '1.4.23';
        $this->author = 'Sales Layer';
        $this->connector_type = 'CN_PRSHP2';
        $this->need_instance = 0;
        $this->dependencies = array('cronjobs');  // force users to install cron jobs module
        $this->ps_versions_compliancy = array('min' => '1.6.1.6', 'max' => '1.8.0.0');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Sales Layer Import');
        $this->description = $this->l('Import products from Sales Layer API.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->sl_time_ini_process = microtime(1);
        $this->sl_updater = new SalesLayerUpdater(_DB_NAME_, _DB_USER_, _DB_PASSWD_, _DB_SERVER_);
        $this->sl_updater->setTablePrefix(_DB_PREFIX_ . 'slyr_');
        $this->sl_updater->connect(_DB_NAME_, _DB_USER_, _DB_PASSWD_, _DB_SERVER_);
        $this->module_path = $this->_path;
        $this->loadLanguages();
        $this->loadActualShopId();
        $this->smarty->assign(
            array(
                'ajax_link' =>
                    $this->context->link->getModuleLink(
                        'saleslayerimport',
                        'ajax',
                        [],
                        null,
                        null,
                        $this->shop_loaded_id
                    )
                ,
                'token' => Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10),
                'COMPANY_NAME' => COMPANY_NAME,
                'COMPANY_TYPE' => COMPANY_TYPE,
            )
        );
        $this->loadDebugMode();
        $this->loadPerformanceLimit();
        $this->integrityPathDirectory = $this->plugin_dir . '/integrity/';
    }

    /**
     * Load debugmode
     */
    public function loadPerformanceLimit()
    {
        $Performance_limit = $this->getConfiguration('PERFORMANCE_LIMIT');
        if ($Performance_limit === false) {
            $Performance_limit = $this->cpu_max_limit_for_retry_call;
            $this->saveConfiguration(['PERFORMANCE_LIMIT' => $Performance_limit]);
        }
        $this->cpu_max_limit_for_retry_call = $Performance_limit;
    }

    /**
     * Load debugmode
     */
    public function loadDebugMode()
    {
        $schemaSQL_PS_SL_configdata = 'CREATE TABLE IF NOT EXISTS ' . $this->saleslayer_aditional_config . " (
                                     `id_config` INT NOT NULL AUTO_INCREMENT,
                                      `configname` VARCHAR(100) NOT NULL,
                                      `save_value` VARCHAR(500) NULL,
                                    PRIMARY KEY (`id_config`),
                                    UNIQUE INDEX `configname_UNIQUE` (`configname` ASC))
                                    COMMENT = 'Sales Layer additional configuration' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_configdata);

        $debugmode = $this->getConfiguration('DEBUGMODE');
        if ($debugmode === false) {
            $debugmode = 0;
            $this->saveConfiguration(['DEBUGMODE' => $debugmode]);
        }
        $this->debugmode = $debugmode;
    }

    /**
     * Get custom configuration
     * @param $configuration_name
     * @return bool|mixed
     */

    public function getConfiguration(
        $configuration_name
    ) {
        $sql_sel = "SELECT * FROM " . $this->saleslayer_aditional_config .
            " WHERE configname = '$configuration_name'";
        $res = $this->slConnectionQuery('read', $sql_sel);
        if (!empty($res)) {
            return $res[0]['save_value'];
        } else {
            return false;
        }
    }

    /**
     * Function to execute a sql and commit it.
     * @param string $sql_to_execute sql to execute
     * @return void|array|string
     */

    public function slConnectionQuery(
        $type,
        $query,
        $params = array()
    ) {
        $resultado = false;
        try {
            if ($type == 'read') {
                if (!empty($params)) {
                    $resultado = Db::getInstance()->executeS($this->writeSqlData($query, $params));
                } else {
                    $resultado = Db::getInstance()->executeS($query);
                }

                if ($resultado && strpos($query, 'sl_cuenta_registros') !== false) {
                    if (isset($resultado[0])) {
                        $resultado = $resultado[0];
                    }
                }
            } else {
                if (!empty($params)) {
                    $resultado = Db::getInstance()->execute($this->writeSqlData($query, $params));
                } else {
                    $resultado = Db::getInstance()->execute($query);
                }
            }
        } catch (Exception $e) {
            if (!empty($params)) {
                $this->debbug(
                    '## Error. SL SQL type: ' . $type . ' - query: ' . $query . ' - params: ' . print_r(
                        $params,
                        1
                    )
                );
            } else {
                $this->debbug('## Error. SL SQL type: ' . $type . ' - query: ' . $query);
            }

            $this->debbug('## Error. SL SQLin error message: ' . $e->getMessage());
        }

        if (!$resultado) {
            return false;
        }

        return $resultado;
    }
    public function loadLanguages()
    {
        $all_languages           = Language::getLanguages(false);
        $this->defaultLanguage   = (int)Configuration::get('PS_LANG_DEFAULT');
        $new_order = [];
        foreach ($all_languages as $lang_key => $language) {
            if ($language['id_lang'] == $this->defaultLanguage) {
                $new_order[] = $language;
                unset($all_languages[$lang_key]);
            }
        }
        if (count($all_languages)) {
            foreach ($all_languages as $language) {
                $new_order[] = $language;
            }
        }
        $this->shop_languages = $new_order;
    }

    /**
     * Fix ajax url in different domains
     * @deprecated
     */

    /* public function overwriteOriginDomain($url)
     {
         if (strpos($url, __PS_BASE_URI__) !== 0) {
             $parsed_url = parse_url($url);
             $exploded = explode('/', $parsed_url['path']);

             $element_for_delete = array('',__PS_BASE_URI__);
             foreach ($exploded as $key => $element) {
                 if (in_array($element, $element_for_delete, false)) {
                     unset($exploded[$key]);
                 }
             }
             $urlcorrect = _PS_BASE_URL_ . __PS_BASE_URI__ . implode('/', $exploded);
         } else {
             $urlcorrect = $url;
         }
         if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
             $urlcorrect = str_replace('http://', 'https://', $urlcorrect);
         }
         return $urlcorrect;
     }*/

    /**
     * Rewrite sql string convert ? to %s  and execute sprint con all values  to write secure sql
     * @param $sql
     * @param $params
     * @return mixed|void
     */

    public function writeSqlData(
        $sql,
        $params
    ) {
        $Replace_string = preg_replace('?', '%s', $sql);

        $output = array($Replace_string);
        if (count($params)) {
            foreach ($params as $parameter) {
                $output[] = $parameter;
            }
        }

        $select_prepare = call_user_func_array('sprintf', $output);

        return $select_prepare;
    }

    /**
     * Print a message in a log with datetime in logs/
     *
     * @param                $mensaje     string message to print
     * @param                $type_file   string|bool  print
     * @param                $force_print string message to print
     * @param string|boolean $type_file error to print in a normal or error log
     * @return void | bool
     */

    public function debbug(
        $mensaje = '',
        $type_file = false,
        $force_print = false
    ) {
        $error_content = false;

        if (strpos($mensaje, '## Error.') !== false || strpos($mensaje, '## Warning.') !== false) {
            $error_content = true;
        }

        if (!$force_print) {
            //$loc_funcs = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $GLOBALS['FCONF_local_server']);
            $func = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $methods = ['debbug', 'debbug_error', 'include', 'require', 'query_db', 'halt'];
            if (isset($func[1]['function']) and !in_array($func[1]['function'], $methods, false)
            ) {
                // alineacion de linias
                $line_chars = 5;
                $separator = '';
                $discount_chart = Tools::strlen($func[0]['line']);
                if ($discount_chart < $line_chars) {
                    for (; $discount_chart < $line_chars; $discount_chart++) {
                        $separator .= ' ';
                    }
                }

                // alineacion de debbug
                $line_chars_functions = 30;
                $separator_function = '';
                $discount_chart_function = Tools::strlen($func[1]['function']);
                if ($discount_chart_function < $line_chars_functions) {
                    for (; $discount_chart_function < $line_chars_functions; $discount_chart_function++) {
                        $separator_function .= ' ';
                    }
                }

                $mensaje = 'Line:' . $func[0]['line'] . $separator . '> ' . $func[1]['function']
                    . '()' . $separator_function . '> ' . trim(
                        $mensaje
                    );
            }
            if ($this->debugmode > 2 || $error_content) {
                // alineacion de debbug
                if (isset($func[1]['file'])) {
                    $filename_array = explode('/', $func[1]['file']);
                    $file_last = end($filename_array);
                    $linechars = 30;
                    $separator_function_file = '';
                    $discount_chart = Tools::strlen('[' . $file_last . ']');
                    if ($discount_chart < $linechars) {
                        for (; $discount_chart < $linechars; $discount_chart++) {
                            $separator_function_file .= ' ';
                        }
                    }

                    $mensaje = 'from:[' . $file_last . '] ' . $separator_function_file . $mensaje;
                }
            }
            if (!$this->debugmode && $error_content && !$force_print) {
                $this->debug_occurence[] = array($mensaje, $type_file);
                $this->printDebugContent();

                return false;
            } elseif (!$this->debugmode && !$force_print) {
                $this->debug_occurence[] = array($mensaje, $type_file);

                return false;
            }
        }


        switch (DEBBUG_TYPE) {
            case 'print_r':
                $msg = print_r($mensaje, 1);
                break;
            case 'var_dump':
                ob_start();
                var_dump($mensaje);
                $msg = ob_get_clean();
                break;
            default:
                $msg = $mensaje;
                break;
        }

        //  $msg = $msg.PHP_EOL;

        if (DEBBUG_PATH_LOG) {
            if ($this->debugmode || $error_content || $force_print) {
                $error_file = '';
                $error_write = false;
                if ($error_content) {
                    $error_write = true;
                    $error_file = DEBBUG_PATH_LOG . '_error_debbug_log_' . date('Y-m-d') . '.txt';
                }


                switch ($type_file) {
                    case 'timer':
                        $file = DEBBUG_PATH_LOG . '_debbug_log_timers_' . date('Y-m-d') . '.txt';
                        break;

                    case 'autosync':
                        $file = DEBBUG_PATH_LOG . '_debbug_log_auto_sync_' . date('Y-m-d') . '.txt';
                        break;

                    case 'syncdata':
                        $file = DEBBUG_PATH_LOG . '_debbug_log_sync_data_' . date('Y-m-d') . '.txt';
                        break;

                    default:
                        $file = DEBBUG_PATH_LOG . '_debbug_log_' . date('Y-m-d') . '.txt';
                        break;
                }


                $new_file = false;

                if (!file_exists($file)) {
                    $new_file = true;
                }
            }
            if ($this->debugmode > 2) {
                $mem = sprintf("%05.2f", (memory_get_usage(true) / 1024) / 1024);

                $pid = getmypid();

                $time_end_process = round(microtime(true) - $this->sl_time_ini_process);
                $load_cpu = sys_getloadavg();
                $load_cpu = $load_cpu[0];
                $msg = "pid:{$pid}-mem:{$mem}-cpu:{$load_cpu}-time:{$time_end_process}-$msg";
            }

            if ($this->debugmode || $error_content || $force_print) {
                if (!file_exists(DEBBUG_PATH_LOG)) {
                    mkdir(DEBBUG_PATH_LOG, 0777, true);
                }
                try {
                    $result = file_put_contents($file, "$msg\r\n", FILE_APPEND);
                } catch (Exception $e) {
                    $result = error_log("$msg\r\n", 3, $file);
                }
            } else {
                $this->debug_occurence[] = array("$msg\r\n", $type_file);

                return false;
            }


            if ($new_file || !$result || !is_writable($file)) {
                chmod($file, 0777);
                if (!$result) {
                    error_log("$msg\r\n", 3, $file);
                }
            }

            if ($error_write) {
                $new_error_file = false;

                if (!file_exists($error_file)) {
                    $new_error_file = true;
                }

                file_put_contents($error_file, "$msg\r\n", FILE_APPEND);

                if ($new_error_file) {
                    chmod($error_file, 0777);
                }
            }
        } else {
            echo $msg;
            ob_flush();
            flush();
        }
    }

    /**
     * Print debug
     */

    public function printDebugContent()
    {
        foreach ($this->debug_occurence as $debugline) {
            $this->debbug($debugline[0], $debugline[1], true);
        }
        $this->clearDebugContent();
    }

    /**
     * Clear debug content
     */

    public function clearDebugContent()
    {
        $this->debug_occurence = array();
    }

    /**
     * SAVE CUSTOM CONFIGURATION in SL Config TABLE
     * @param null $data
     */

    public function saveConfiguration(
        $data = null
    ) {
        if ($data != null && is_array($data)) {
            foreach ($data as $configname => $save_value) {
                // $this->debbug('New configuration to save '.$configname.'   value ->'.$save_value,'syncdata');
                $sql_sel = 'SELECT * FROM ' . $this->saleslayer_aditional_config . " WHERE configname = '$configname'";
                $res = $this->slConnectionQuery('read', $sql_sel);
                if (!empty($res)) {
                    $sql_sel = 'UPDATE ' . $this->saleslayer_aditional_config .
                        " SET  save_value = '" . $save_value . "' " .
                        " WHERE id_config = '" . $res[0]['id_config'] . "' Limit 1";
                    $this->slConnectionQuery('-', $sql_sel);
                } else {
                    $sql_sel = "INSERT INTO  " . $this->saleslayer_aditional_config . " ( configname , save_value )
                     VALUES ('" . $configname . "','" . $save_value . "')";
                    $this->slConnectionQuery('insert', $sql_sel);
                }
            }
        }
    }

    /**
     * Save the last date that is stored in the prestashop cron, when it was the last time you called this cronjob.
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    public function saveCronExecutionTime()
    {
        $result = $this->testSlcronExist();
        if (count($result)) {
            $old_execution_time = $this->checkCronProcessExecutionTime(); //check old time of execution from slyr table

            $this->debbug('Saving newest cron execution time form BD ->' . $result[0]['updated_at'], 'autosync');
            $timetosave = strtotime($result[0]['updated_at']);

            $this->saveConfiguration(['LATEST_CRON_EXECUTION' => $timetosave]); // save newest time of execution

            if (!$old_execution_time) {
                $old_execution_time = $this->max_execution_time - 10;
                $this->debbug(
                    'Compare executions of cron ' . date('H:i:s', $timetosave) . ' Old->' . date(
                        'H:i;s',
                        $old_execution_time
                    ),
                    'autosync'
                );
            }

            $execution_time_cron = $timetosave - $old_execution_time - 10;
            $this->debbug('Frequency of execution of cron is ' . $execution_time_cron, 'autosync');

            if ($execution_time_cron > 0) {
                $this->cron_frequency = $execution_time_cron;
                $this->saveConfiguration(['CRON_MINUTES_FREQUENCY' => $execution_time_cron]);
            }
        }
    }

    /**
     *
     */
    public function globalUrlToRunPrestashopCronJobs()
    {
        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);
        $url = $this->context->link->getAdminLink(
            'AdminCronJobs',
            false
        ) .
               '&token=' . Configuration::getGlobalValue('CRONJOBS_EXECUTION_TOKEN');
        if (Tools::substr($url, 0, 4) !== "http") {
            $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            $url = 'http://' . $default_shop->domain . $default_shop->getBaseURI() .
                   $this->getConfiguration('ADMIN_DIR') . '/' . $url;
        }
        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
        return $url;
    }
    /**
     * Getactual shop id from loaded domain
     */
    public function loadActualShopId()
    {
        $idShop = null;
        if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            $host = Tools::getHttpHost();
            $request_uri = rawurldecode($_SERVER['REQUEST_URI']);

            $sql = 'SELECT s.id_shop, CONCAT(su.physical_uri, su.virtual_uri) AS uri, su.domain, su.main
                    FROM ' . _DB_PREFIX_ . 'shop_url su
                    LEFT JOIN ' . _DB_PREFIX_ . 'shop s ON (s.id_shop = su.id_shop)
                    WHERE (su.domain = \'' . pSQL($host) . '\' OR su.domain_ssl = \'' . pSQL($host) . '\')
                        AND s.active = 1
                        AND s.deleted = 0
                    ORDER BY LENGTH(CONCAT(su.physical_uri, su.virtual_uri)) DESC';

            try {
                $result = Db::getInstance()->executeS($sql);
            } catch (PrestaShopDatabaseException $e) {
                return null;
            }

            foreach ($result as $row) {
                // A shop matching current URL was found
                if (preg_match('#^' . preg_quote($row['uri'], '#') . '#i', $request_uri)) {
                    $idShop = $row['id_shop'];
                    break;
                }
            }

            if (null !== $idShop) {
                $shop = new Shop($idShop);
            } else {
                $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            }
        } else {
            $shop = Context::getContext()->shop;
        }
        $this->shop_loaded_id = $shop->id;
    }


    /**
     * Function that prepares sql to verify if a cron already exists in prestashop database with that url
     * @return array|false|mysqli_result|PDOStatement|resource|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function testSlcronExist()
    {
        $task_url = '%' . urlencode(
            'saleslayerimport-cron.php?token=' . Tools::substr(
                Tools::encrypt('saleslayerimport'),
                0,
                10
            )
        );
        $task = array(
            'task' => $task_url,
        );

        $where = ' WHERE ';
        $counter = 0;

        foreach ($task as $where_keys => $where_values) {
            $counter++;
            $where .= ' ' . $where_keys . ' LIKE \'' . $where_values . '\' ' .
                      ($counter != count($task) ? ' AND ' : ' ');
        }

        $query_full = 'SELECT id_cronjob,updated_at,NOW() as timeBD
FROM ' . $this->prestashop_cron_table . $where . ' LIMIT 1';
        return Db::getInstance()->executeS($query_full);
    }

    /**
     * Read from file the last execution of cron stored in the last call
     * @return bool|false|int|string
     */


    public function checkCronProcessExecutionTime()
    {
        $latest_cron_execution = $this->getConfiguration('LATEST_CRON_EXECUTION');
        $this->debbug(' LATEST_CRON_EXECUTION ->' . $latest_cron_execution, 'autosync');

        return $latest_cron_execution;
    }

    /**
     * Insatalation of plugin
     * @return bool
     */

    public function install()
    {
        try {
            if (!$this->registerHook('actionCronJob')
                  && !parent::install()
            ) {
                if (!empty($this->_errors)) {
                    if (!file_exists(DEBBUG_PATH_LOG)) {
                        mkdir(DEBBUG_PATH_LOG, 0777, true);
                    }
                    $this->debbug('Install test result error-> :' . print_r($this->_errors, 1), '', true);
                }
                return false;
            }

            $this->checkDB();
            $this->createTabLink();
            $this->registerHook('displayBackOfficeHeader');
            if (!empty($this->_errors)) {
                $this->debbug('Install test result-> :' . print_r($this->_errors, 1), '', true);
            }
            if (defined('_PS_ADMIN_DIR_')) { //save adminpanel directory for create cronjobs url
                $admin = explode('/', _PS_ADMIN_DIR_);
                $admin_folder_arr = array_slice($admin, -1);
                $admin_folder = reset($admin_folder_arr);
                $configuration = array('ADMIN_DIR' => $admin_folder);
                $this->saveConfiguration($configuration);
            }
            if (!$this->compareIntegrity()) {
                $this->debbug('It is possible that the installation was not successful and some of the files' .
                              ' have not been successfully copied. Some of the files have not been copied ' .
                              'well or have been manipulated. Please reinstall the ' .
                              'module to correct this error or unzip and move files manually.', '', true);
            }

            return true;
        } catch (Exception $e) {
            $this->debbug('Install error ' . $e->getMessage() . ' line->' . $e->getLine() .
                          ' Trace->' . print_r($e->getTrace(), 1));
            $this->_errors[] = 'Install error  :' . $e->getMessage();
            return false;
        }
    }

    /**
     * checks if Sales Layer table exists, if not, creates it
     *
     * @return void
     */

    public function checkDB()
    {
        $schemaSQL_PS_Config = 'CREATE TABLE IF NOT EXISTS ' .
                               "`" . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config . "` (" .
                               '`cnf_id` int(11) NOT NULL AUTO_INCREMENT, ' .
                               '`conn_code` varchar(32) NOT NULL, ' .
                               '`conn_secret` varchar(32) NOT NULL, ' .
                               '`comp_id` int(11) NOT NULL, ' .
                               '`last_update` int, ' .
                               '`default_language` varchar(6) NOT NULL, ' .
                               '`languages` varchar(512) NOT NULL, ' .
                               '`conn_schema` mediumtext NOT NULL, ' .
                               '`data_schema` mediumtext NOT NULL, ' .
                               '`conn_extra` mediumtext ,' .
                               '`updater_version` varchar(10) NOT NULL, ' .
                               'PRIMARY KEY (`cnf_id`)' .
                               ') ENGINE=' . $this->sl_updater->table_engine . '
            ROW_FORMAT=' . $this->sl_updater->table_row_format . ' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ';

        Db::getInstance()->execute($schemaSQL_PS_Config);

        $schemaSQL_PS_SL_C_P = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ .
                               "slyr_category_product`  (
								`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
								`ps_id` int(11) NOT NULL
								COMMENT '(prestashop_id| attribute_id if ps_type = product_format_value)',
								`slyr_id` bigint(20) NOT NULL COMMENT '(sales_layer_id)',
								`ps_type` varchar(32) NOT NULL
								COMMENT '(slCatalogue|product|product_format_field|product_format_value|combination)',
								`ps_attribute_group_id` int(11) DEFAULT NULL,
								`date_add` datetime NOT NULL,
								`date_upd` datetime DEFAULT NULL,
								`comp_id` int(11) NOT NULL COMMENT '(company_id)',
								`id_lang` int(11) DEFAULT NULL COMMENT 'Prestashop id_language',
								`shops_info` text COMMENT 'Sales Layer connectors shops',
								PRIMARY KEY (`id`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        Db::getInstance()->execute($schemaSQL_PS_SL_C_P);

        $query_read = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_category_products' ";

        $rename = Db::getInstance()->executeS($query_read);

        if (!empty($rename)) {
            //change to singular from older versions
            $query_alter = 'ALTER TABLE ' . _DB_PREFIX_ . 'slyr_category_products
              RENAME TO `' .  _DB_PREFIX_  . 'slyr_category_product` ';
            Db::getInstance()->execute(sprintf($query_alter));
        }

        $query_read = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_category_product' " .
            " AND column_name = 'shops_info'";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = "ALTER TABLE " . _DB_PREFIX_ . "slyr_category_product ADD COLUMN
             `shops_info` text COMMENT 'Sales Layer connectors shops'";

            Db::getInstance()->execute(sprintf($query_alter));
        }

        $query_read = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_images' ";

        $rename = Db::getInstance()->executeS($query_read);

        if (!empty($rename)) {
            //change to singular from older versions
            $query_alter = 'ALTER TABLE ' . _DB_PREFIX_ . 'slyr_images
              RENAME TO `' .  _DB_PREFIX_  . 'slyr_image` ';
            Db::getInstance()->execute(sprintf($query_alter));
        }

        $schemaSQL_PS_SL_C_I = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . "slyr_image (
								`image_reference` varchar(255) NOT NULL
								 COMMENT '(Sales Layer original image reference)',
								`id_image` int(10) NOT NULL COMMENT '(prestashop image id)',
								`md5_image` varchar(128) NOT NULL COMMENT '(prestashop image md5)',
								PRIMARY KEY (`image_reference`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8; ";

        Db::getInstance()->execute($schemaSQL_PS_SL_C_I);

        $query_read = " SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_  . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_image' " .
            " AND column_name = 'md5_image'";

        $md5_image = Db::getInstance()->executeS($query_read);

        if (empty($md5_image)) {
            $query_alter = 'ALTER TABLE ' . _DB_PREFIX_ . "slyr_image ADD COLUMN
            `md5_image` varchar(128) NOT NULL COMMENT '(prestashop image md5)'";
            Db::getInstance()->execute(sprintf($query_alter));
        }

        /* from Sales layer version 1.4 */

        $schemaSQL_PS_SL_SETDATA = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . "slyr_syncdata (
                                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                                    `sync_type` VARCHAR(10) NOT NULL,
                                    `item_type` VARCHAR(30) NOT NULL,
                                    `sync_tries` INT NOT NULL DEFAULT 0,
                                    `item_data` TEXT(2000000) NULL,
                                    `sync_params` TEXT(2000000) NULL,
                                    PRIMARY KEY (`id`),
                                    INDEX `sync_type` (`sync_type` ASC) ,
                                    INDEX `item_type` (`item_type` ASC) ,
                                    INDEX `sync_tries` (`sync_tries` ASC))
                                    COMMENT = 'Sales Layer Sync Data Table' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_SETDATA);


        $schemaSQL_PS_SL_SETDATA_flag = "CREATE TABLE IF NOT EXISTS " . $this->saleslayer_syncdata_flag_table .
                                        " (
                                      `id` BIGINT NOT NULL AUTO_INCREMENT,
                                      `syncdata_pid` BIGINT NOT NULL,
                                      `syncdata_last_date` INT(11) NOT NULL,
                                      PRIMARY KEY (`id`),
                                      INDEX `syncdata_pid` (`syncdata_pid` ASC),
                                      INDEX `syncdata_last_date` (`syncdata_last_date` ASC))
                                      COMMENT = 'Sales Layer Sync Data Flag Table' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_SETDATA_flag);


        $query_read = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config . "' " .
            " AND column_name = 'auto_sync'";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = 'ALTER TABLE ' . _DB_PREFIX_ . 'slyr_' . $this->sl_updater->table_config .
                ' ADD COLUMN `auto_sync` INT(11) NOT NULL DEFAULT 0 AFTER `updater_version` ';
            Db::getInstance()->execute($query_alter);
        }

        $query_read = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config . "' " .
            " AND column_name = 'last_sync' ";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = "ALTER TABLE " . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config .
                ' ADD COLUMN `last_sync` DATETIME NULL AFTER `auto_sync` ';
            Db::getInstance()->execute($query_alter);
        }


        $query_read = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config . "' " .
            " AND column_name = 'auto_sync_hour' ";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = "ALTER TABLE " . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config .
                " ADD COLUMN `auto_sync_hour` INT(2) NOT NULL DEFAULT 0 AFTER `last_sync` ";
            Db::getInstance()->execute($query_alter);
        }

        $query_read = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config . "' " .
            " AND column_name = 'avoid_stock_update' ";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = "ALTER TABLE " . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config .
                " ADD COLUMN `avoid_stock_update` INT(2) NOT NULL DEFAULT 0 AFTER `auto_sync_hour` ";
            Db::getInstance()->execute($query_alter);
        }


        $query_read = " SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_image' " .
            " AND column_name = 'ps_product_id'";

        $ps_variant_id = Db::getInstance()->executeS($query_read);

        if (empty($ps_variant_id)) {
            $query_alter = " ALTER TABLE " . _DB_PREFIX_ . "slyr_image
            ADD COLUMN `ps_product_id` BIGINT COMMENT '(prestashop ps_product_id)'";
            Db::getInstance()->execute(sprintf($query_alter));
        }

        $query_read = " SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_image' " .
            " AND column_name = 'ps_variant_id'";

        $ps_variant_id = Db::getInstance()->executeS($query_read);

        if (empty($ps_variant_id)) {
            $query_alter = " ALTER TABLE " . _DB_PREFIX_ . "slyr_image
            ADD COLUMN `ps_variant_id` varchar(1000) COMMENT '(prestashop ps_variant_id)'";
            Db::getInstance()->execute(sprintf($query_alter));
        }

        $query_read = " SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_image' " .
            " AND column_name = 'origin'";

        $ps_variant_id = Db::getInstance()->executeS($query_read);

        if (empty($ps_variant_id)) {
            $query_alter = " ALTER TABLE " . _DB_PREFIX_ . "slyr_image
            ADD COLUMN `origin` varchar(4) COMMENT '(photo import from origin)'";
            Db::getInstance()->execute(sprintf($query_alter));
        }

        $schemaSQL_PS_SL_configdata = "CREATE TABLE IF NOT EXISTS " . $this->saleslayer_aditional_config . " (
                                     `id_config` INT NOT NULL AUTO_INCREMENT,
                                      `configname` VARCHAR(100) NOT NULL,
                                      `save_value` VARCHAR(500) NULL,
                                    PRIMARY KEY (`id_config`),
                                    UNIQUE INDEX `configname_UNIQUE` (`configname` ASC))
                                    COMMENT = 'Sales Layer additional configuration' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_configdata);
        /*From 1.4.1*/
        $query_read = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            " WHERE table_schema = '" . _DB_NAME_ . "' " .
            " AND table_name = '" . _DB_PREFIX_ . "slyr_syncdata' " .
            " AND column_name = 'status'";

        $shops_info = Db::getInstance()->executeS($query_read);

        if (empty($shops_info)) {
            $query_alter = 'ALTER TABLE ' . _DB_PREFIX_ . 'slyr_syncdata ' .
            "ADD COLUMN `status` VARCHAR(2) NOT NULL DEFAULT 'no' AFTER `sync_params`, " .
                'ADD INDEX `status` (`status` ASC, `sync_tries` ASC, `item_type` ASC, `sync_type` ASC) ';

            Db::getInstance()->execute(sprintf($query_alter));
        }

        /*From version 1.4.7*/

        $schemaSQL_PS_SL_C_I = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . "slyr_attachment (
								`file_reference` varchar(255) NOT NULL
								 COMMENT '(Sales Layer original file attachment reference)',
								`id_attachment` int(10) NOT NULL COMMENT '(prestashop file attachment id)',
								`md5_attachment` varchar(128) NOT NULL COMMENT '(prestashop file md5)',
								`ps_product_id` BIGINT COMMENT '(prestashop ps_product_id)',
								PRIMARY KEY (`file_reference`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8; ";
        Db::getInstance()->execute($schemaSQL_PS_SL_C_I);

        /* From version 1.4.20 */

        $schemaSQL_PS_SL_SETDATA = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . "slyr_indexer (
                                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                                    `id_product` BIGINT NOT NULL,
                                    PRIMARY KEY (`id`),
                                    INDEX `id_product` (`id_product` ASC))
                                    COMMENT = 'Sales Layer poducts ids for reindex' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_SETDATA);

        $schemaSQL_PS_SL_SETDATA = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . "slyr_accessories (
                                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                                    `id_product` BIGINT NOT NULL,
                                    `accessories` varchar(20000) NOT NULL,
                                    PRIMARY KEY (`id`),
                                    INDEX `id_product` (`id_product` ASC))
                                    COMMENT = 'Sales Layer table for accessories' ";
        Db::getInstance()->execute($schemaSQL_PS_SL_SETDATA);
    }

    /**
     * Creating Sales layer Menu in admin panel
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function createTabLink()
    {
        $this->installTab('SalesLayerImport', 'Sales Layer', 'SELL');
        $this->installTab('AllConnectors', 'Connectors', 'SalesLayerImport');
        $this->installTab('AddConnectors', 'Add New Connector', 'SalesLayerImport');
        $this->installTab('HowToUse', 'How To Use', 'SalesLayerImport');
        $this->installTab('AdminDiagtools', 'Diagnostics', 'SalesLayerImport');
        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCss($this->_path . 'views/css/tab.css', 'all');
    }

    /**
     * Insert tab for menu in admin
     * @param      $className
     * @param      $tabName
     * @param bool $tabParentName
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function installTab(
        $className,
        $tabName,
        $tabParentName = false
    ) {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $className;
        $tab->name = array();

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $tabName;
        }
        if ($tabParentName) {
            $tab->id_parent = (int) Tab::getIdFromClassName($tabParentName);
        } else {
            $tab->id_parent = 0;
        }
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * Uninstal plugin from prestashop
     * @return bool
     */
    public function uninstall()
    {
        try {
            /**
             * Delete images that are not assigned to any product and have been imported from sales layer
             */
            $connectors = $this->sl_updater->getConnectorsInfo(null, true);
            if (!empty($connectors)) {
                foreach ($connectors as $connector) {
                    $this->setConnectorData($connector['conn_code'], 'last_update', 0);
                }
                $this->sl_updater->deleteAll(true);
            }

            $this->deleteTabLink();
            $this->deleteSlcronRegister();
            $this->deleteSlyrTables();
            $this->unregisterHook('displayBackOfficeHeader');
            if (!parent::uninstall()
            ) {
                $this->_errors[] = 'Error uninstall module';
                $this->debbug('## Error. Ocurred errors on uninstalling ' . print_r($this->_errors, 1));
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->debbug('Uninstall error ' . $e->getMessage() . ' line->' . $e->getLine()
                          . ' track->' . print_r($e->getTrace(), 1));
            return false;
        }
    }

    /**
     * Save data to connector table
     * @param $connector_id
     * @param $field_name
     * @param $field_value
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    public function setConnectorData(
        $connector_id,
        $field_name,
        $field_value
    ) {
        if ($connector_id != null && $field_name != null) {
            $sql_FUPD = "UPDATE " . _DB_PREFIX_ . "slyr_" . $this->sl_updater->table_config .
                " SET " . $field_name . " = '$field_value' WHERE conn_code = '" . addslashes(
                    $connector_id
                ) . "' limit 1 ";
            $shops_info = Db::getInstance()->execute($sql_FUPD);

            if ($shops_info) {
                return $shops_info;
            }
        }

        return false;
    }

    /**
     * Delete Sales layer Menu from admin panel
     */

    public function deleteTabLink()
    {
        try {
            $tab = new Tab((int)Tab::getIdFromClassName('SalesLayerImport'));
            $tab->delete();
            $tab = new Tab((int)Tab::getIdFromClassName('AddConnectors'));
            $tab->delete();
            $tab = new Tab((int)Tab::getIdFromClassName('AllConnectors'));
            $tab->delete();
            $tab = new Tab((int)Tab::getIdFromClassName('AdminDiagtools'));
            $tab->delete();
            $tab = new Tab((int)Tab::getIdFromClassName('HowToUse'));
            $tab->delete();
        } catch (Exception $e) {
            $this->debbug(
                '## Error. There was an error in the removal of the sales layer menu.' . print_r(
                    $e->getMessage(),
                    1
                ),
                'error'
            );
        }
    }

    /**
     * Remove the task to run sales layer plugin
     */

    public function deleteSlcronRegister()
    {
        $sql_delete_query = 'DELETE FROM ' . $this->prestashop_cron_table .
                            " WHERE task LIKE '%saleslayerimport-cron%' ";
        $this->slConnectionQuery('-', $sql_delete_query);
    }

    /**
     * Main function for Prestashop for show content in template after configuration panel in module manager
     * ->config
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws CoreException
     */

    public function getContent()
    {
        $create_validation_table = '';
        $message = '';
        $return_table = array();
        $extension_needed = array(
            'curl_version' => array('test' => 'function_exists', 'public_name' => 'PHP cURL Installed'),
            'cronjobs' => array(
                'test' => 'module',
                'public_name' => 'Prestashop module Cronjobs Installed',
            ),
            'testSlcronExist' => array(
                'test' => 'setfunction',
                'public_name' => 'There is a task to execute Sales Layer cron job in prestashop',
            ),
            'verifySLcronRegister' => array(
                'test' => 'setfunction',
                'public_name' => 'Registered prestashop cronjob activity',
                'return' => 'stat',
                'additional_message' => 'message',
            ),
        );

        if (count($extension_needed)) {
            foreach ($extension_needed as $extension_name => $extension_value) {
                if ($extension_value['test'] == 'function_exists') {
                    if (function_exists($extension_name)) {
                        $result_test = 'fa-check text-success';
                    } else {
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa ' . $result_test .
                        '" aria-hidden="true"></i>';
                } elseif ($extension_value['test'] == 'module') {
                    $file_extension = _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . $extension_name .
                        DIRECTORY_SEPARATOR . $extension_name . '.php';
                    if (file_exists($file_extension)) {
                        $result_test = 'fa-check text-success';
                    } else {
                        $result_test = 'fa-exclamation text-danger';
                    }
                    $return_table[$extension_value['public_name']] = '<i class="fa ' . $result_test .
                        '" aria-hidden="true"></i>';
                } elseif ($extension_value['test'] == 'setfunction') {
                    $return_stat = $this->{$extension_name}();
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
                    $return_table[$extension_value['public_name']] = '<i class="fa ' . $result_test .
                        '" aria-hidden="true"></i>';
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
        $culr_link = $this->globalUrlToRunPrestashopCronJobs();



        $this->context->smarty->assign(
            array(
                'SLY_LOGOS_PATH' => $this->module_path . 'views/img/',
                'SLY_ASSETS_PATH' => $this->_path,
                'link_all_connectors' => $this->context->link->getAdminLink('AllConnectors'),
                'add_connectors' => $this->context->link->getAdminLink('AddConnectors'),
                'link_how_to_use' => $this->context->link->getAdminLink('HowToUse'),
                'link_diagnostics' => $this->context->link->getAdminLink('AdminDiagtools'),
                'plugin_name' => Tools::ucfirst($this->name),
                'admin_attributes' => $this->context->link->getAdminLink('AdminAttributesGroups'),
                'message' => $message,
                'validation_table' => $create_validation_table,
                'culr_link' => $culr_link,
            )
        );

        return $this->display(__FILE__, 'views/templates/admin/howtouse.tpl');
    }

    /**
     * Check if in the table of crons of prestashop there is the record that executes the sales layer plugin and if
     * it does not exist it will be created and it verifies if in the last hour it has been executed at least once.
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function verifySLcronRegister()
    {
        $return = array();
        $return['stat'] = true;
        $result = $this->testSlcronExist();

        if (count($result)) {
            $updated_time = strtotime($result[0]['updated_at']);
            $limit_time = strtotime($result[0]['timeBD']) - (60 * 60);

            if ($updated_time > $limit_time) {
                $this->debbug('Cron jobs of Sl are working correctly.');
            } else {
                $construct_prestashop_cron_url = '*/5 * * * *  curl "' .
                    $this->globalUrlToRunPrestashopCronJobs() . '"';
                $this->debbug(
                    '## Error. The activity of Prestashop cron has not been detected.' .
                    ' Last time the SL cron needed for synchronization was executed ->' . print_r(
                        date('d/m/Y H:i:s', $updated_time),
                        1
                    ) .
                    'It is necessary that you activate on your server the cron job ' .
                    'that performs the prestashop tasks in order to execute ' .
                    'the automatic synchronizations of prestashop.' .
                    'Add the following command to the cronjobs on your server: ' . $construct_prestashop_cron_url
                );
                $return['stat'] = false;
                $return['message'] = 'The activity of Prestashop cron has not been detected.<br>';
                $return['message'] .= 'It is necessary that you activate on your server the cron job ' .
                                        'that performs the prestashop tasks ' .
                                            'in order to execute the automatic synchronizations Sales ' .
                    'layer plugin.<br>';
                $return['message'] .= 'Add the following command to your cronjobs on your server: <br> ' .
                    $construct_prestashop_cron_url;
            }
        } else {
            $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            $task_url = urlencode(
                'http://' . $default_shop->domain . $default_shop->getBaseURI() . 'modules/' .
                'saleslayerimport/saleslayerimport-cron.php?token=' . Tools::substr(
                    Tools::encrypt('saleslayerimport'),
                    0,
                    10
                )
            );
            $task = array(
                'task' => $task_url,
                'hour' => -1,
                'day' => -1,
                'month' => -1,
                'day_of_week' => -1,
            );
            $this->createSlcronRegister($task);
        }

        return $return;
    }

    /**
     * Create a task in prestashop to execute the tasks of the sales layer plugin
     * @param $config_task
     * @return bool
     */

    public function createSlcronRegister(
        $config_task
    ) {
        $description = 'Registration required for Sales layer catalog synchronization.';
        $task = $config_task['task'];
        $hour = $config_task['hour'];
        $day = $config_task['day'];
        $month = $config_task['month'];
        $day_of_week = $config_task['day_of_week'];

        if ($task != null && $hour != null && $day != null && $month != null && $day_of_week != null) {
            $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            $id_shop = $default_shop->id;
            $id_shop_group = $default_shop->id_shop_group;

            $query = 'INSERT INTO ' . $this->prestashop_cron_table .
                '(`description`, `task`, `hour`, `day`, `month`, `day_of_week`,
                 `updated_at`, `active`, `id_shop`, `id_shop_group`)' .
                'VALUES (\'' . $description . '\', \'' . $task . '\', \'' . $hour . '\', \''
                     . $day . '\', \'' . $month . '\',
                \'' . $day_of_week . '\', NULL, TRUE, ' . $id_shop . ', ' . $id_shop_group . ')';

            try {
                $result = Db::getInstance()->execute($query);
            } catch (Exception $e) {
                $this->debbug('## Error. query ->' . print_r($query, 1) . ' exception->  ' . $e->getMessage());
            }

            if (!$result) {
                $this->debbug('## Error. Could not create record to create cron jobs');
            }
        }

        return true;
    }

    /**
     * clear all registers in Sales Layer table which have been deleted in Prestashop
     * @param $company_id
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function clearDeletedSlyrRegs()
    {
        $this->debbug(
            'Reasync deleted content and remove all from sl table if not exist in prestashop',
            'syncdata'
        );


        $categoriesDelete = Db::getInstance()->executeS(
            "SELECT sl.id FROM `" . _DB_PREFIX_ . 'slyr_category_product`  sl
            LEFT JOIN `' . _DB_PREFIX_ . "category`  ca ON (ca.id_category = sl.ps_id )
            WHERE  sl.ps_type = 'slCatalogue' AND ca.id_category is null"
        );

        if (!empty($categoriesDelete)) {
            //  $this->debbug(' delete this slCatalogue register but already deleted ' .
            // print_r($categoriesDelete,1).' sql->'.$schemaCats);
            foreach ($categoriesDelete as $categoryDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product  WHERE id = "%s"',
                        $categoryDelete['id']
                    )
                );
            }
        }

        $schemaProds = " SELECT sl.id FROM `" . _DB_PREFIX_ . "slyr_category_product`  sl " .
            " LEFT JOIN `" . $this->product_table . "` p ON (p.id_product = sl.ps_id ) " .
            " WHERE  sl.ps_type = 'product' AND p.id_product is null ";
        $productsDelete = Db::getInstance()->executeS($schemaProds);

        if (!empty($productsDelete)) {
            //  $this->debbug(' delete this product register but already deleted ' .
            // print_r($productsDelete,1).' $sql->'.$schemaProds);
            foreach ($productsDelete as $productDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product  WHERE id = "%s"',
                        $productDelete['id']
                    )
                );
            }
        }

        $schemaAttrs = " SELECT sl.id FROM `" . _DB_PREFIX_ . "slyr_category_product`  sl " .
            " LEFT JOIN " . $this->attribute_group_table . " ag ON (ag.id_attribute_group = sl.ps_id ) " .
            " WHERE  sl.ps_type = 'product_format_field' AND ag.id_attribute_group is null ";
        $attributesDelete = Db::getInstance()->executeS($schemaAttrs);

        if (!empty($attributesDelete)) {
            //  $this->debbug(' delete this product_format_field register but already deleted ' .
            // print_r($attributesDelete,1).' $sql->'.$schemaAttrs);
            foreach ($attributesDelete as $attributeDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product  WHERE id = "%s"',
                        $attributeDelete['id']
                    )
                );
            }
        }

        // Removes attribute values which group no longer exists
        $schemaAttributesGroup = 'SELECT at.id_attribute FROM ' . $this->attribute_table . ' AS at ' .
            ' LEFT JOIN ' . $this->attribute_group_table . ' AS pa
            ON (pa.id_attribute_group = at.id_attribute_group ) WHERE pa.id_attribute_group is null ';

        $deleteAttributeGroup = Db::getInstance()->executeS($schemaAttributesGroup);

        if (!empty($deleteAttributeGroup)) {
            $this->debbug(
                '## Warning. Attribute values are sent to delete because their group no longer exists ->'.print_r(
                    $deleteAttributeGroup,
                    1
                )
            );
            foreach ($deleteAttributeGroup as $Attribute) {
                $attributeValue = new AttributeCore($Attribute['id_attribute']);
                $attributeValue->delete();
            }
        }


        $schemaAttrVals = 'SELECT id FROM '._DB_PREFIX_.'slyr_category_product  AS sl ' .
            ' LEFT JOIN ' . $this->attribute_table . ' AS a ON (a.id_attribute = sl.ps_id ) ' .
            " WHERE  sl.ps_type = 'product_format_value' AND a.id_attribute is null ";
        $attributeValuesDelete = Db::getInstance()->executeS($schemaAttrVals);

        if (!empty($attributeValuesDelete)) {
            //  $this->debbug(' delete this product_format_value register but already deleted ' .
            //print_r($attributeValuesDelete,1).' sql->'.$schemaAttrVals);
            foreach ($attributeValuesDelete as $attributeValueDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product  WHERE id = "%s"',
                        $attributeValueDelete['id']
                    )
                );
            }
        }

        $schemaFeatures = " SELECT id FROM " . _DB_PREFIX_ . "slyr_category_product  AS sl " .
            " LEFT JOIN " . $this->product_attribute_table . " AS pa ON (pa.id_product_attribute = sl.ps_id ) " .
            " WHERE  sl.ps_type = 'combination' AND ( pa.id_product_attribute is null OR pa.id_product = 0) ";
        $featuresDelete = Db::getInstance()->executeS($schemaFeatures);

        if (!empty($featuresDelete)) {
            //  $this->debbug(' delete this combination register but already deleted ' .
            // print_r($featuresDelete,1).' sql->'.$schemaFeatures);
            foreach ($featuresDelete as $featureDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product  WHERE id = "%s"',
                        $featureDelete['id']
                    )
                );
            }
        }


        $schemaImages = " SELECT sl.id_image FROM " . _DB_PREFIX_ . "slyr_image AS sl " .
            " LEFT JOIN " . $this->image_shop_table . ' AS pa
            ON (pa.id_image = sl.id_image ) WHERE pa.id_image is null ';

        $deleteImages = Db::getInstance()->executeS($schemaImages);

        if (!empty($deleteImages)) {
            $this->debbug('Enviando imagenes para eliminar de tabla SLYR que ya no existen en la
             tabla de imagenes de prestashop $deleteImages->' . print_r($deleteImages, 1) .
             ' query->' . print_r($schemaImages, 1));
            foreach ($deleteImages as $ImageforDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_image WHERE id_image = "%s"',
                        $ImageforDelete['id_image']
                    )
                );
            }
        }

        $schemaAttachment = " SELECT sl.id_attachment FROM " . _DB_PREFIX_ . "slyr_attachment AS sl " .
                        " LEFT JOIN " . $this->attachment_table . ' AS pa
            ON (pa.id_attachment = sl.id_attachment ) WHERE pa.id_attachment is null ';

        $deleteAttachment = Db::getInstance()->executeS($schemaAttachment);

        if (!empty($deleteAttachment)) {
            // $this->debbug('Eliminando archivos  SLYR que ya no existen en la tabla
            // ' query->'.print_r($deleteAttachment,1));
            foreach ($deleteAttachment as $AttachmentforDelete) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . 'slyr_attachment WHERE id_attachment = "%s"',
                        $AttachmentforDelete['id_attachment']
                    )
                );
            }
        }
    }

    /**
     * Function to insert sync data into the database.
     * @param boolean $force_insert forces sql to be inserted
     * @return void
     */

    public function insertSyncdataSql(
        $force_insert = false
    ) {
        if (!empty($this->sql_to_insert)
            && (count($this->sql_to_insert)
                >= $this->sql_insert_limit
                || $force_insert)) {
            $sql_to_insert = implode(',', $this->sql_to_insert);
            try {
                $sql_query_to_insert = 'INSERT INTO ' . _DB_PREFIX_ . 'slyr_syncdata' .
                    ' ( sync_type, item_type, item_data, sync_params ) VALUES ' .
                    $sql_to_insert;
                $this->slConnectionQuery('insert', $sql_query_to_insert);
            } catch (Exception $e) {
                $this->debbug('## Error. Insert syncdata SQL query: ' . $sql_query_to_insert);
                $this->debbug('## Error. Insert syncdata SQL message: ' . $e->getMessage());
            }

            $this->sql_to_insert = array();
        }
    }

    /**
     * Function to get a connector's field value.
     * @param string $connector_id Sales Layer connector id
     * @param string|array $field_name connector field name
     * @return string|array   $field_value                    field value
     */

    public function getConnField(
        $connector_id,
        $field_name
    ) {
        $return = array();
        if ($connector_id != null) {
            if (is_array($field_name)) {
                $field_name = implode(',', $field_name);
            }

            $sql = "SELECT " . $field_name . " FROM " . _DB_PREFIX_ . "slyr_" .
                $this->sl_updater->table_config . " WHERE `conn_code`='" . addslashes(
                    $connector_id
                ) . "' limit 1 ;";
            $return = $this->slConnectionQuery('read', $sql);
        }

        return $return;
    }

    /**
     * Repair structure of image. Remove images that are not linked with other tables so that prestashop can assign
     * new images.
     * @param $id_product
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function repairImageStructureOfProduct(
        $id_product
    ) {
        $id_product = (string)$id_product;
        $ids_images_all = array();
        $counter = 0;
        $this->debbug('Entry to repair images of product with ps_id -> ' .
            print_r($id_product, 1), 'syncdata');

        $ids_images = Db::getInstance()->executeS(
            sprintf(
                'SELECT id_image FROM ' . $this->image_shop_table .
                ' WHERE id_product = "%s" GROUP BY id_image ',
                $id_product
            )
        );
        $this->debbug('id_images from ' . $this->image_shop_table . ' -> ' .
                      print_r($ids_images, 1), 'syncdata');

        $ids_images_image = Db::getInstance()->executeS(
            sprintf(
                'SELECT id_image  FROM ' . $this->image_table .
                ' WHERE id_product = "%s" GROUP BY id_image ',
                $id_product
            )
        );
        $this->debbug('id_images from ' . $this->image_table . ' -> ' .
            print_r($ids_images_image, 1), 'syncdata');


        $this->debbug(
            'before merge  ' . print_r($ids_images, 1) .
            ' with ->' . print_r($ids_images_image, 1),
            'syncdata'
        );
        try {
            if (count($ids_images)) {
                foreach ($ids_images as $id_image) {
                    $ids_images_all[] = $id_image['id_image'];
                }
            }
            if (count($ids_images_image)) {
                foreach ($ids_images_image as $id_image) {
                    $ids_images_all[] = $id_image['id_image'];
                }
            }
            $ids_images_all = array_unique($ids_images_all);
        } catch (Exception $e) {
            $this->debbug('Error in merge values ' . $e->getMessage(), 'syncdata');

            return false;
        }

        $this->debbug('idImages after merge  ' . print_r($ids_images_all, 1), 'syncdata');
        if (count($ids_images_all)) {
            foreach ($ids_images_all as $id_image) {
                $this->debbug('Test image ->' . print_r($id_image, 1), 'syncdata');
                $test_ok_ps_image = true;
                $test_ok_image_lang_table = true;
                $test_ok_image_shop = true;

                /*test if exist this image in ps_image table*/
                try {
                    $ps_images = Db::getInstance()->executeS(
                        sprintf('SELECT id_image FROM ' . $this->image_table . ' WHERE id_image = "%s" ', $id_image)
                    );
                    if (empty($ps_images)) {
                        $test_ok_ps_image = false;
                    }
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. Selecting images of id_image -> ' . print_r(
                            $id_image,
                            1
                        ) . ' occured->' . print_r(
                            $e->getMessage(),
                            1
                        ),
                        'syncdata'
                    );
                }
                /*test if exist ps_image_lang registers*/
                try {
                    $ps_images_lang = Db::getInstance()->executeS(
                        sprintf(
                            'SELECT id_image FROM ' . $this->image_lang_table . ' WHERE id_image = "%s" ',
                            $id_image
                        )
                    );
                    if (empty($ps_images_lang)) {
                        $test_ok_image_lang_table = false;
                    }
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. Selecting images of id_image lang table -> ' . print_r(
                            $id_image,
                            1
                        ) . ' occured->' . print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }
                try {
                    /*test if exist ps_image_shop registers*/
                    $ps_images_shop = Db::getInstance()->executeS(
                        sprintf(
                            'SELECT id_image FROM ' . $this->image_shop_table . ' WHERE id_image = "%s" ',
                            $id_image
                        )
                    );
                    if (empty($ps_images_shop)) {
                        $test_ok_image_shop = false;
                    }
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. Selecting images of id_image lang table -> ' . print_r(
                            $id_image,
                            1
                        ) . ' occured->' . print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }
                if ($test_ok_ps_image && $test_ok_image_lang_table && $test_ok_image_shop) {
                    $this->debbug('Image ' . print_r($id_image, 1) . ' is ok ', 'syncdata');
                } else {
                    $counter++;
                    // delete image for delete this image the structure
                    $this->debbug(
                        'Image ' . print_r(
                            $id_image,
                            1
                        ) . ' is  corrupt how to a delete it for posibility to create it: test_ok_ps_image->' . print_r(
                            $test_ok_ps_image,
                            1
                        ) . ',  test_ok_image_lang_table->' . print_r(
                            $test_ok_image_lang_table,
                            1
                        ) . ', test_ok_image_shop->' . print_r($test_ok_image_shop, 1),
                        'syncdata'
                    );
                    $image_for_delete = new Image($id_image);
                    $delete_return = $image_for_delete->delete();
                    if (!$delete_return) {
                        try {
                            $image_for_delete->deleteProductAttributeImage();
                            $image_for_delete->deleteImage(true);
                        } catch (Exception $e) {
                            $this->debbug('', 'syncdata');
                        }
                    }

                    if ($delete_return) {
                        $this->debbug(
                            'Image deleting ' . print_r($id_image, 1) . ' is ok from new Image() function',
                            'syncdata'
                        );
                        Db::getInstance()->execute(
                            sprintf("DELETE FROM " . _DB_PREFIX_ . "slyr_image WHERE id_image = '%s'", $id_image)
                        );
                    } else {
                        $this->debbug(
                            'Image force deleting image id:' . print_r($id_image, 1) . ', how to a delete from BD',
                            'syncdata'
                        );
                        Db::getInstance()->execute(
                            sprintf(
                                "DELETE FROM " . $this->image_table . " WHERE id_image = '%s'",
                                $id_image
                            )
                        );
                        Db::getInstance()->execute(
                            sprintf(
                                "DELETE FROM " . $this->image_lang_table . " WHERE id_image = '%s'",
                                $id_image
                            )
                        );
                        Db::getInstance()->execute(
                            sprintf(
                                "DELETE FROM " . $this->image_shop_table . " WHERE id_image = '%s'",
                                $id_image
                            )
                        );
                        Db::getInstance()->execute(
                            sprintf(
                                "DELETE FROM " . _DB_PREFIX_ . "slyr_image WHERE id_image = '%s'",
                                $id_image
                            )
                        );
                    }
                }
            }
        }
        $this->debbug('Deleted Corrupted images :' . print_r($counter, 1), 'syncdata');
        $this->debbug(
            '## Info. Structure Repaired Complete! ' .
            ($counter > 0 ? 'It is likely that the problem was solved.' :
                ' No image was found that prevents insertion.'),
            'syncdata'
        );
    }

    /**
     * Function to check and synchronize Sales Layer connectors with auto-synchronization enabled.
     * @return void
     */

    public function autoSyncConnectors()
    {
        $this->debbug("==== AUTOSync INIT " . date('Y-m-d H:i:s') . " ====", 'autosync');

        $this->sl_time_ini_auto_sync_process = microtime(1);

        try {
            require_once 'controllers/admin/SalesLayerPimUpdate.php';

            $sync_libs = new SalesLayerPimUpdate();
            $all_connectors = $this->getAllConnectors();
            $now = time();

            if (!empty($all_connectors)) {
                $connectors_to_check = array();

                foreach ($all_connectors as $connector) {
                    if ($connector['auto_sync'] > 0) {
                        $connector_last_sync_unix = strtotime($connector['last_sync']);

                        if ($connector_last_sync_unix == '') {
                            $unix_to_update = time();
                            $connector['unix_to_update'] = $unix_to_update;
                            $connectors_to_check[$unix_to_update] = $connector;
                        } else {
                            if ($connector['auto_sync'] >= 24) {
                                if (!$connector_last_sync_unix) {
                                    $connector_last_sync_unix = 0;
                                }

                                $unix_to_update = $connector_last_sync_unix + ($connector['auto_sync'] * 3600);
                                if ($connector['auto_sync_hour'] > 0) {
                                    $unix_to_update = mktime(
                                        $connector['auto_sync_hour'],
                                        0,
                                        0,
                                        date('m', $unix_to_update),
                                        date('d', $unix_to_update),
                                        date('Y', $unix_to_update)
                                    );
                                }
                                if ($now >= $unix_to_update) {
                                    $connector['unix_to_update'] = $unix_to_update;
                                    $connectors_to_check[$unix_to_update] = $connector;
                                }
                            } else {
                                $unix_to_update = $connector_last_sync_unix + ($connector['auto_sync'] * 3600);
                                if ($now >= $unix_to_update) {
                                    $connector['unix_to_update'] = $unix_to_update;
                                    $connectors_to_check[$unix_to_update] = $connector;
                                }
                            }
                        }
                    }
                }

                if (!empty($connectors_to_check)) {
                    ksort($connectors_to_check, SORT_NATURAL);
                    foreach ($connectors_to_check as $connector) {
                        if ($connector['auto_sync'] >= 24) {
                            $last_sync_time = mktime(
                                $connector['auto_sync_hour'],
                                0,
                                0,
                                date('m', $now),
                                date('d', $now),
                                date('Y', $now)
                            );
                            $last_sync = date('Y-m-d H:i:s', $last_sync_time);
                        } else {
                            $last_sync = date('Y-m-d H:i:s');
                        }

                        $connector_id = $connector['conn_code'];

                        $this->debbug('Connector to auto-synchronize: ' .
                                       $connector_id, 'autosync');

                        $time_ini_cron_sync = microtime(1);

                        $time_random = rand(10, 20);
                        sleep($time_random);
                        $this->debbug("#### time_random: " . $time_random . ' seconds.', 'autosync');

                        $data_return = $sync_libs->storeSyncData($connector_id, $last_sync);

                        $this->debbug(
                            "#### time_cron_sync: " . (microtime(1) - $time_ini_cron_sync - $time_random) . ' seconds.',
                            'autosync'
                        );

                        if (is_array($data_return)) {
                            break;
                        }
                    }
                } else {
                    $this->debbug("Currently there aren't connectors to synchronize.", 'autosync');
                }
            } else {
                $this->debbug("There aren't any configured connectors.", 'autosync');
            }
        } catch (Exception $e) {
            $this->debbug('## Error. Autosync process: ' . $e->getMessage(), 'autosync');
        }

        $this->debbug(
            '##### time_all_autosync_process: ' . (microtime(1) - $this->sl_time_ini_auto_sync_process) . ' seconds.',
            'autosync'
        );

        $this->debbug("==== AUTOSync END ====", 'autosync');
    }

    /**
     * Get all the connectors from Sales Layer
     * @return array  with all their info and shops
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function getAllConnectors()
    {
        $connectors = $this->sl_updater->getConnectorsInfo(null, true);

        if (!empty($connectors)) {
            $shops = $this->getAllShops();

            foreach ($connectors as $conn_key => $connector) {
                unset(
                    $connectors[$conn_key]['cnf_id'],
                    $connectors[$conn_key]['conn_schema'],
                    $connectors[$conn_key]['data_schema'],
                    $connectors[$conn_key]['conn_extra']
                );

                $conn_extra_info = $this->sl_updater->getConnectorExtraInfo($connector['conn_code']);

                $updated_shops = array();

                if (is_array($shops)) {
                    $updated_shops = $shops;

                    if (isset($conn_extra_info['shops'])) {
                        foreach ($updated_shops as $keyUS => $updated_shop) {
                            $updated_shops[$keyUS]['checked'] = false;

                            foreach ($conn_extra_info['shops'] as $connector_shop) {
                                if ($connector_shop['checked'] == true
                                    && $connector_shop['id_shop'] == $updated_shop['id_shop']) {
                                    $updated_shops[$keyUS]['checked'] = true;
                                }
                            }
                        }
                    }
                }

                $connectors[$conn_key]['shops'] = $updated_shops;

                if (!empty($connectors[$conn_key]['shops']) || (isset($conn_extra_info['shops'])
                        && !empty($connectors[$conn_key]['shops'])
                        && $connectors[$conn_key]['shops'] != $conn_extra_info['shops'])) {
                    $this->sl_updater->setConnectorExtraInfo(
                        $connector['conn_code'],
                        array('shops' => $connectors[$conn_key]['shops'])
                    );
                }

                if ($connectors[$conn_key]['last_update'] != null && $connectors[$conn_key]['last_update'] != 0) {
                    $connectors[$conn_key]['last_update'] = date(
                        'Y-m-d H:i:s',
                        $connectors[$conn_key]['last_update']
                    );
                }
            }
        } else {
            return array();
        }

        return $connectors;
    }

    /**
     * Get all actived shops saved in Prestashop
     * @return array|bool|false|mysqli_result|PDOStatement|resource|null array with all the shops info and the
     *                                                                   first checked
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function getAllShops()
    {
        $schemaShops = ' SELECT * FROM ' . $this->shop_table . ' WHERE active = 1 and deleted = 0 ';
        $shops = Db::getInstance()->executeS($schemaShops);

        if (!empty($shops)) {
            foreach (array_keys($shops) as $key) {
                if ($key == 0) {
                    $shops[$key]['checked'] = true;
                } else {
                    $shops[$key]['checked'] = false;
                }
            }

            return $shops;
        }

        return false;
    }

    /**
     * Function to synchronize Sales Layer connectors data stored in sync data table.
     * @return array|bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    public function syncDataConnectors()
    {
        $processed_delete = array();
        $this->sl_catalogues = new SlCatalogues();
        $this->sl_products = new SlProducts();
        $this->sl_products_dl = new SlProductDelete();
        $this->sl_variants = new SlVariants();
        $processed = array();
        $this->allocateMemory();
        $this->debbug('memory limit status ->' . ini_get('memory_limit'));
        $this->stopIndexer();
        $result = $this->testSlcronExist();
        $this->load_cron_time_status = $result;
        $this->recalculateDurationOfSyncronizationProcess($result);

        $this->sl_time_ini_sync_data_process = microtime(1);

        $this->debbug("==== Sync Data INIT " . date('Y-m-d H:i:s') . " ====", 'syncdata');

        try {
            $sql_test = "SELECT * FROM " . _DB_PREFIX_ . "slyr_syncdata WHERE sync_tries >= 3 AND status = 'pr' ";
            $result_test = $this->slConnectionQuery('read', $sql_test);

            if (!empty($result_test)) {
                //Print errors that have occurred due to php processes
                $this->debbug('## Error. Processes have been detected that could not be completed' .
                    ' and it is possible that this is due to a PHP error, please check the' .
                    ' php / apache / nginx error log to find a solution to this problem.' .
                    ' Stored information is:', 'syncdata');
                foreach ($result_test as $item_err) {
                    $item_data = json_decode($item_err['item_data'], 1);
                    $this->debbug('## Error. item_type:' . $item_err['item_type'] .
                        ' ID:' . print_r($item_data['sync_data']['ID'], 1) .
                        ' Item_data_pack->' . print_r($item_err, 1), 'syncdata', true);
                }
                $this->debbug('Server information ================================================ ', 'syncdata', true);
                $this->debbug('PS VERSION: ' . print_r(_PS_VERSION_, 1), 'syncdata', true);
                $this->debbug('php Version: ' . print_r(phpversion(), 1), 'syncdata', true);
                $this->debbug('Plugin Version: ' . print_r($this->version, 1), 'syncdata', true);
                $this->debbug('max execution time: ' . print_r(ini_get('max_execution_time'), 1), 'syncdata', true);
                $this->debbug('SERVER_SOFTWARE: ' . print_r($_SERVER["SERVER_SOFTWARE"], 1), 'syncdata', true);
                $this->debbug('display_errors: ' . ini_get('display_errors'), 'syncdata', true);
                $this->debbug(
                    'ignore_repeated_errors: ' . print_r(ini_get('ignore_repeated_errors'), 1),
                    'syncdata',
                    true
                );
                $this->debbug(
                    'intl.error_level: ' . print_r(ini_get('intl.error_level'), 1),
                    'syncdata',
                    true
                );
                $this->debbug(
                    'cron frequency: ' . print_r($this->getConfiguration('CRON_MINUTES_FREQUENCY'), 1),
                    'syncdata',
                    true
                );
                $this->debbug(
                    'loaded extensions: ' . print_r(get_loaded_extensions(), 1),
                    'syncdata',
                    true
                );
            }

            //Clear exceeded attemps
            $sql_delete = " DELETE FROM " . _DB_PREFIX_ . "slyr_syncdata WHERE sync_tries >= 3";
            $this->slConnectionQuery('-', $sql_delete);
        } catch (Exception $e) {
            $this->debbug('## Error. Data cleaning has exceeded the maximum number of attempts: '
                . $e->getMessage(), 'syncdata');
        }

        $load = sys_getloadavg();

        if (($this->cpu_max_limit_for_retry_call * 2) < $load[0]) {
            $this->debbug(
                '## Warning. at:' . date(
                    'H:i:s'
                ) . ' We have detected that the cpu is overloaded, The saturation limit of cpu doubled.' .
                 ' Cpu load->' .
                print_r(
                    $load[0],
                    1
                ) . ' limit ->' .
                $this->cpu_max_limit_for_retry_call .
                '. It\'s possible that that\'s why the synchronization will go much slower. ' .
                ' We have reduced the number of insertions per process to 1.',
                'syncdata'
            );
            $this->sql_insert_limit = 1;
        //return false;
        } elseif ($load[0] > $this->cpu_max_limit_for_retry_call) {
            $this->debbug(
                '## Warning. at:' . date(
                    'H:i:s'
                ) . ' We have detected that the cpu is overloaded,' .
                 'we will try to postpone this synchronization for a ' .
                  '10 seconds so as not to saturate your server any more. ' .
                  'Cpu load->' .
                print_r(
                    $load[0],
                    1
                ) . ' limit ->' . $this->cpu_max_limit_for_retry_call . ' send sleep.' .
                ' We have reduced the number of insertions per process to 3.',
                'syncdata'
            );
            $this->sql_insert_limit = 3;
            sleep(10);
            $this->recalculateDurationOfSyncronizationProcess();
        }

        $this->syncdata_pid = getmypid();
        $this->end_process = false;
        $this->checkSyncDataFlag();

        if (!$this->end_process) {
            try {
                $sql = " SELECT * FROM " . _DB_PREFIX_ . "slyr_syncdata
                     WHERE sync_type = 'delete' ORDER BY item_type ASC, sync_tries ASC, id ASC ";

                $items_to_delete = $this->slConnectionQuery(
                    'read',
                    $sql
                );
                $this->allocateMemory();
                if (!empty($items_to_delete)) {
                    foreach ($items_to_delete as $item_to_delete) {
                        $this->checkProcessTime();
                        $this->checkSqlItemsDelete();

                        if ($this->end_process) {
                            $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                            break;
                        } else {
                            $sync_tries = $item_to_delete['sync_tries'];
                            $sync_params = json_decode(
                                Tools::stripslashes($item_to_delete['sync_params']),
                                1
                            );
                            $this->processing_connector_id = $sync_params['conn_params']['connector_id'];
                            $this->comp_id                 = $sync_params['conn_params']['comp_id'];
                            $this->conector_shops_ids      = $sync_params['conn_params']['shops'];

                            //   $this->store_view_ids = $sync_params['conn_params']['store_view_ids'];

                            $item_data = json_decode(Tools::stripslashes($item_to_delete['item_data']), 1);
                            $sl_id = $item_data['sl_id'];

                            switch ($item_to_delete['item_type']) {
                                case 'category':
                                    try {
                                        $result_delete = $this->sl_catalogues->deleteCategory(
                                            $sl_id,
                                            $this->comp_id,
                                            $this->conector_shops_ids,
                                            $this->processing_connector_id
                                        );
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. in delete category : ' . print_r($item_to_delete, 1),
                                            'syncdata'
                                        );
                                    }
                                    break;

                                case 'product':
                                    try {
                                        $result_delete = $this->sl_products_dl->deleteProduct(
                                            $sl_id,
                                            $this->comp_id,
                                            $this->conector_shops_ids,
                                            $this->processing_connector_id
                                        );
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. In delete product: ' . print_r($item_to_delete, 1),
                                            'syncdata'
                                        );
                                    }
                                    break;

                                case 'product_format':
                                    try {
                                        $result_delete = $this->sl_variants->deleteVariant(
                                            $sl_id,
                                            $this->comp_id,
                                            $this->conector_shops_ids
                                        );
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. In delete Variant: ' . print_r($item_to_delete, 1),
                                            'syncdata'
                                        );
                                    }
                                    break;

                                default:
                                    $result_delete = 'Undefined ithem';
                                    $this->debbug(
                                        '## Error. Incorrect item: ' . print_r($item_to_delete, 1),
                                        'syncdata'
                                    );
                                    break;
                            }

                            switch ($result_delete) {
                                case 'item_not_deleted':
                                    $this->debbug(
                                        '## Error. Problem in deleting Item: ' . print_r($item_to_delete, 1),
                                        'syncdata'
                                    );
                                    $sync_tries++;

                                    $sql_update = " UPDATE " . _DB_PREFIX_ . "slyr_syncdata" .
                                        " SET sync_tries = " . $sync_tries .
                                        " WHERE id = " . $item_to_delete['id'];

                                    $this->slConnectionQuery('-', $sql_update);
                                    $this->clearDebugContent();
                                    break;

                                default:
                                    $processed_delete[] = str_replace('_', ' ', $item_to_delete['item_type']);
                                    $this->sql_items_delete[] = $item_to_delete['id'];
                                    $this->clearDebugContent();
                                    break;
                            }
                        }
                    }
                    $this->debbug('Run regenerateEntireNtree after delete', 'syncdata');

                    $this->sl_catalogues->reorganizeCategories($this->conector_shops_ids);
                }
            } catch (Exception $e) {
                $this->debbug('## Error. Deleting syncdata process: ' .
                    $e->getMessage(), 'syncdata');
            }

            $indexes = array('category', 'product', 'accessories', 'product_format');
            $categories_clear = true;
            $product_clear = true;
            $product_format_clear = true;


            foreach ($indexes as $index) {
                if ($index == 'product' && $categories_clear) {
                    $this->allocateMemory();
                    /**
                     * After category synchronization
                     */

                    $categories_clear = false;
                    try {
                        $this->sl_catalogues->reorganizeCategories($this->conector_shops_ids);
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. In reorganizing Categories after Update ' .
                            $e->getMessage() . ' line->' . $e->getLine(),
                            'syncdata'
                        );
                    }

                    unset($this->category_images_sizes, $this->sl_catalogues, $this->categories_collection);
                } elseif ($index == 'accessories' && $product_clear) {
                    /**
                     * After Product synchronization
                     */
                    $product_clear = false;
                    unset($this->product_images_sizes, $this->sl_products);
                } elseif ($index == 'product_format' && $product_format_clear) {
                    /**
                     * After Product acesories synchronization
                     */
                    $product_format_clear = false;
                }

                $sql_check_try = 0;

                do {
                    $sqlpre = ' SET @id = null,@sync_type = null,@item_type = null,' .
                        '@sync_tries = null,@item_data = null,@sync_params = null ';
                    $sqlpre2 =  'UPDATE ' . _DB_PREFIX_ .
                                'slyr_syncdata dest, (SELECT MIN(A.id) ,A.id,A.sync_tries,@id := A.id,' .
                        '@sync_type := A.sync_type,@item_type := A.item_type,@sync_tries := A.sync_tries , ' .
                        '@item_data := A.item_data,@sync_params := A.sync_params FROM ' . _DB_PREFIX_ .
                                'slyr_syncdata A ' .
                        " WHERE sync_type = 'update' AND item_type = '" . $index . "' LIMIT 1 ) src " .
                        " SET dest.status = 'pr', dest.sync_tries = src.sync_tries + 1   WHERE   dest.id = src.id  ";
                    $sqlpre3 = ' SELECT @id AS id,@sync_type AS sync_type,@item_type AS item_type , ' .
                        ' @sync_tries AS sync_tries,@item_data AS item_data,@sync_params AS sync_params  ';

                    $this->slConnectionQuery('-', $sqlpre);
                    $this->slConnectionQuery('-', $sqlpre2);

                    $items_to_update = $this->slConnectionQuery(
                        'read',
                        $sqlpre3
                    );

                    if (!empty($items_to_update)
                        && isset($items_to_update[0]['id'])
                        && $items_to_update[0]['id'] != null) {
                        $processed_ithems = $this->updateItems($items_to_update);
                        unset($items_to_update);

                        if (count($processed_ithems)) {
                            foreach ($processed_ithems as $result) {
                                $this->updated_elements[] = $result;
                            }
                        }
                        unset($processed_ithems);
                    } else {
                        // $sql_check_try++;
                        $this->debbug('Stop because there are no more elements of ' . $index, 'syncdata');
                        unset($items_to_update);
                        break;
                    }

                    if ($this->end_process) {
                        $this->debbug('Break 2 from but is end the process', 'syncdata');
                        unset($items_to_update);
                        break 2;
                    }
                } while ($sql_check_try < 4);
            }
        }

        $this->checkSqlItemsDelete(true);

        if (!$this->checkRegistersForProccess()) {
            $this->startIndexer();
        }

        if (count($processed_delete)) {
            $order_deleted = array_count_values($processed_delete);
            foreach ($order_deleted as $deleted_key => $deleted) {
                if ($deleted_key == 'category' && $deleted > 1) {
                    $deleted_key = 'categorie';
                }
                $processed[] = 'Deleted ' . $deleted_key . ($deleted > 1 ? 's' : '') . ': ' . $deleted;
            }
        }


        if (count($this->updated_elements)) {
            $order_updated = array_count_values($this->updated_elements);
            foreach ($order_updated as $updated_key => $updated) {
                if ($updated_key == 'category' && $updated > 1) {
                    $updated_key = 'categorie';
                }
                $processed[] = 'Modified ' . $updated_key . ($updated > 1 ? 's' : '') . ': ' . $updated;
            }
        }

        $this->debbug('Processed :-> ' . print_r($processed, 1));
        try {
            $this->disableSyncDataFlag();
            $this->verifyRetryCall();
        } catch (Exception $e) {
            $this->debbug('## Error. Deleting sync_data_flag: ' . $e->getMessage(), 'syncdata');
        }

        $this->debbug(
            '### time_all_syncdata_process: ' . (microtime(1) - $this->sl_time_ini_sync_data_process) . ' seconds.',
            'syncdata'
        );

        $this->debbug("==== Sync Data END " . date('Y-m-d H:i:s') . " ====", 'syncdata');

        return $processed;
    }

    /**
     * Replace "/" to  "-" for strtotime recognition
     * @param $date
     *
     * @return string|string[]
     */

    public function fomatDate($date)
    {
        $date = str_ireplace(['/','\\'], ['-','-'], $date);
        return $date;
    }

    /**
     * Before sensitive processes to use a lot of memory, we calculate current usage and multiply it to the amount
     * needed to finish the job.
     */

    public function allocateMemory()
    {
        $min_assigned = $this->memory_min_limit;
        $mem = sprintf("%05.2f", (memory_get_usage(true) / 1024) / 1024);
        $memory_multiply = round($mem * 3);
        $actual_limit = ini_get('memory_limit');

        if ($memory_multiply < $min_assigned) {
            $memory_multiply = $min_assigned;
        }

        $memory_remaining = $memory_multiply - $mem;

        if ($memory_remaining < 100) {
            $this->debbug(
                'The synchronization does not have any mb left wih which to work, it will be assigned more ->'
                . $memory_multiply,
                'syncdata'
            );
            $memory_multiply = $memory_multiply * 3;
        }

        $this->debbug(
            'Actual Memory limit ->' . $actual_limit . ' In Use->' . $mem . ' Memory recommended ->' . $memory_multiply,
            'syncdata'
        );
        if ($actual_limit < $memory_multiply) {
            $this->debbug('Reassigning memory max_limit to ->' . $memory_multiply);
            @ini_set('memory_limit', $memory_multiply . 'M');
        }
    }

    /**
     * Check if there are elements to be processed
     *
     * @param bool $return_num return as number actutual stat of table
     * @param string $table
     *
     * @return bool|mixed
     */


    public function checkRegistersForProccess(
        $return_num = false,
        $table = 'syncdata'
    ) {
        $sql_processing = "SELECT count(*) as sl_cuenta_registros FROM " .
                          _DB_PREFIX_ . "slyr_" . $table;
        $items_processing = $this->slConnectionQuery('read', $sql_processing);

        if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0) {
            if ($return_num) {
                return $items_processing['sl_cuenta_registros'];
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Search the pid and return if it's still running or not
     * @param integer $pid pid to search
     * @return boolean        status of pid running
     */

    public function hasPidAlive(
        $pid
    ) {
        if ($pid) {
            try {
                if (Tools::strtolower(Tools::substr(PHP_OS, 0, 3)) == 'win') {
                    $wmi = new COM('winmgmts://');
                    $prc = $wmi->ExecQuery("SELECT ProcessId FROM Win32_Process WHERE ProcessId='$pid'");

                    $i = count($prc);

                    $this->debbug(
                        "Searching active process pid '$pid' by Windows. Is active? " . ($i > 0 ? 'Yes' : 'No')
                    );


                    return ($i > 0 ? true : false);
                } else {
                    if (function_exists('posix_getpgid')) {
                        $this->debbug(
                            "Searching active process pid '$pid' by posix_getpgid. Is active? " . (posix_getpgid(
                                $pid
                            ) ? 'Yes' : 'No')
                        );


                        return (posix_getpgid($pid) ? true : false);
                    } else {
                        $this->debbug(
                            "Searching active process pid '$pid' by ps -p. Is active? " . (shell_exec(
                                "ps -p $pid | wc -l"
                            ) > 1 ? 'Yes' : 'No')
                        );


                        if (shell_exec("ps -p $pid | wc -l") > 1) {
                            return true;
                        }
                    }
                }
            } catch (Exception $e) {
                $this->debbug(
                    '## Error. The plugin has tried to release a stuck process but without ' .
                    ' success ->' . $e->getMessage() . ' line->' . print_r(
                        $e->getLine(),
                        1
                    ),
                    'syncdata'
                );
            }
        }

        return false;
    }

    /**
     * Save product for async index
     */

    public function saveProductIdForIndex($product_id)
    {
        $this->debbug(
            'before test to save ? id_product->' .
            print_r($product_id, 1),
            'syncdata'
        );
        $sql_query = "SELECT * FROM  " . _DB_PREFIX_ .
                     "slyr_indexer WHERE id_product = '$product_id'  LIMIT 1";
        $res = $this->slConnectionQuery('read', $sql_query);
        $this->debbug(
            'Exist in bd?->' . print_r($res, 1),
            'syncdata'
        );
        if (!$res) {
            $this->debbug(
                'Saving id_product for indexing after sync',
                'syncdata'
            );
            $sl_query_flag_to_insert = " INSERT INTO " . _DB_PREFIX_ . "slyr_indexer " .
                                       " (id_product) VALUES " .
                                       "('" . $product_id . "')";
            $this->slConnectionQuery('-', $sl_query_flag_to_insert);
        }
    }

    /**
     * Save product for async index
     *
     * @param $product_id
     * @param $accessories
     */
    public function saveProductAccessories($product_id, $accessories)
    {
        try {
            $this->debbug(
                'entry to function ' .
                'product_id-> ' . $product_id . ' accessories ->' . print_r(
                    $accessories,
                    1
                ),
                'syncdata'
            );
            $sql_query = "SELECT * FROM  " . _DB_PREFIX_ .
                     "slyr_accessories WHERE id_product = '$product_id' ";
            $res = $this->slConnectionQuery('read', $sql_query);

            if (!$res) {
                $sl_query_flag_to_insert = " INSERT INTO " . _DB_PREFIX_ . "slyr_accessories " .
                                       " (id_product, accessories) VALUES " .
                                       "('" . $product_id . "','" . addslashes(json_encode($accessories)) . "')";
                $this->slConnectionQuery('-', $sl_query_flag_to_insert);
                $this->debbug(
                    'Saving new accessory register ' .
                    'product_id-> ' . $product_id . ' accessories ->' . print_r(
                        $accessories,
                        1
                    ) . ' query->' . print_r($sl_query_flag_to_insert, 1),
                    'syncdata'
                );
            } else {
                $this->debbug(
                    'updating existing accessory register ' .
                    'product_id-> ' . $product_id . ' accessories ->' . print_r(
                        $accessories,
                        1
                    ) . ' bd response ->' . print_r($res, 1),
                    'syncdata'
                );
                $old_json = reset($res);
                $decode_json = json_decode(Tools::stripslashes($old_json), 1);
                if ($decode_json) {
                    foreach ($decode_json as $sku) {
                        if (in_array($sku, $accessories)) {
                            $accessories[] = $sku;
                        }
                    }
                }
                $sl_query_flag_to_insert = " UPDATE " . _DB_PREFIX_ . "slyr_accessories " .
                                       " SET accessories = '" . addslashes(
                                           json_encode($accessories)
                                       ) . "' WHERE id ='" . $decode_json['id'] . "' ";
                $this->slConnectionQuery('-', $sl_query_flag_to_insert);
            }

            if (!$this->product_accessories) {
                //set process to process in end
                $item_type = 'accessories';
                $sync_type = 'update';
                $sql_sel = "SELECT * FROM " . _DB_PREFIX_ . "slyr_syncdata
            WHERE sync_type = '$sync_type' AND item_type = '$item_type' Limit 1  ";
                $result = $this->slConnectionQuery('read', $sql_sel);

                if (!$result) {
                    $this->product_accessories = true;
                    $sql_query_to_insert = "INSERT INTO " . _DB_PREFIX_ . "slyr_syncdata" .
                                       " ( sync_type, item_type, item_data ) VALUES " .
                                       "('" . $sync_type . "', '" . $item_type .
                                           "', '" . json_encode(['virtual work for sync accessories']) . "')";
                    $this->slConnectionQuery('-', $sql_query_to_insert);
                } else {
                    $this->product_accessories = true;
                }
            }
        } catch (Exception $e) {
            $this->debbug(
                '##Error. Saving new accessory register ' .
                'product_id-> ' . $product_id . ' accessories ->' . print_r(
                    $accessories,
                    1
                ) . ' message->' . $e->getMessage() . ' track->' .
                print_r($e->getTrace(), 1),
                'syncdata'
            );
        }
    }

    /**
     * Synchronize Acessories 'related_ithems'->accesories skus
     *
     * @param $item_type
     * @param $array_name
     */

    /*  public function saveStatAccessories($item_type, $array_name)
      {
          if (isset($this->{$array_name}) && is_array($this->{$array_name}) &&
               count($this->{$array_name})) {
              // $item_type = 'accessories';
              $sync_type = 'update';
              try {
                  $item_data_to_insert = html_entity_decode(json_encode($this->{$array_name}));

                  $sql_sel = "SELECT * FROM " . _DB_PREFIX_ . "slyr_syncdata
              WHERE sync_type = '$sync_type' AND item_type = '$item_type' Limit 1  ";
                  $res = $this->slConnectionQuery('read', $sql_sel);

                  if (!$res) {
                      $sql_query_to_insert = "INSERT INTO " . _DB_PREFIX_ . "slyr_syncdata" .
                          " ( sync_type, item_type, item_data ) VALUES " .
                          "('" . $sync_type . "', '" . $item_type . "', '" . addslashes($item_data_to_insert) . "')";
                      $this->slConnectionQuery('-', $sql_query_to_insert);
                  } else {
                      $sql_query_to_insert = " UPDATE " . _DB_PREFIX_ . "slyr_syncdata" .
                          " SET item_data =  '" . addslashes(
                              $item_data_to_insert
                          ) . "' WHERE sync_type = '$sync_type' AND item_type = '$item_type' ";
                      $this->slConnectionQuery('-', $sql_query_to_insert);
                  }
              } catch (Exception $e) {
                  $this->debbug('## Error. An error has occurred keeping changes of ' .
                                $item_type . ' of a product.->' .
                      $e->getMessage() . ' line->' . $e->getLine(), 'syncdata');
              }
          }
      }*/


    /**
     * Ejecutar despues de la synchronización de productos
     */

    public function syncAccesories()
    {
        $this->debbug(' Entry to sync accessories', 'syncdata');
        ;
        ini_set('max_execution_time', 144000);
        do {
            //Process to update accessories once all products have been generated.
            $sql_sel = "SELECT * FROM " . _DB_PREFIX_ . "slyr_accessories
	              Limit 250  ";
            $res = $this->slConnectionQuery('read', $sql_sel);

            if (count($res)) {
                $ids_for_delete = [];
                $this->debbug(' Entry to sync accessories->' .
                              print_r($res, 1), 'syncdata');
                $saleslayerpimupdate = new SalesLayerPimUpdate();

                foreach ($res as $register) {
                    $ids_for_delete[] = $register['id'];
                    $id_product       = $register['id_product'];
                    $product_accessories = json_decode(Tools::stripslashes($register['accessories']), 1);
                    $product_accessories_ids = array();

                    if (!empty($product_accessories)) {
                        foreach ($product_accessories as $product_accessory_reference) {
                            //Eliminamos carácteres especiales de la referencia
                            $product_accessory_reference =
                                $saleslayerpimupdate->slValidateReference($product_accessory_reference);

                            //find product with the same reference
                            $schemaRef = "SELECT id_product FROM " . $this->product_table .
                                " WHERE reference = '" . $product_accessory_reference . "' LIMIT 1 ";
                            $regsRef = Db::getInstance()->executeS($schemaRef);

                            if (count($regsRef) > 0) {
                                $product_accessories_ids[] = $regsRef[0]['id_product'];
                            }
                        }
                    }

                    if (!empty($product_accessories_ids)) {
                        $this->debbug(' Entry to sync accessories->' .
                                      print_r($product_accessories_ids, 1), 'syncdata');
                        $productObject = new Product($id_product);
                        $productObject->deleteAccessories();
                        $productObject->changeAccessories($product_accessories_ids);
                    }
                }
                if ($ids_for_delete && count($ids_for_delete)) {
                    $delete_query = 'DELETE FROM ' . _DB_PREFIX_ . 'slyr_accessories' .
                                    ' WHERE id IN(' . implode(',', $ids_for_delete) . ')';
                    Db::getInstance()->execute($delete_query);
                    $this->debbug(' ids for delete form accessories ->' .
                                  print_r($ids_for_delete, 1) .
                                  ' query->' . print_r($delete_query, 1), 'syncdata');
                }
            }
        } while (count($res) > 1);
    }
    /**
     * Ejecutar despues de la synchronización de productos
     */

    /* public function syncIndexes()
     {
         //Process to update accessories once all products have been generated.
         if (!empty($this->for_index)) {
             $this->debbug(' Entry to sync Indexes->' .
                           print_r($this->for_index, 1), 'syncdata');
             $all_shops_image = Shop::getShops(true, null, true);
             foreach ($this->for_index as $id_product => $product_id_val) {
                 try {
                     foreach ($all_shops_image as $shop_id_in) {
                         Shop::setContext(shop::CONTEXT_SHOP, $shop_id_in);
                         $prod_index = new Product($product_id_val, false, null, $shop_id_in);
                         $prod_index->indexed = 0;
                         if ($prod_index->price === null || $prod_index->price === '') {
                             $prod_index->price = 0;
                         }
                         $this->debbug('Status active for stores in indexing-> ps_ product_id' .
                                       $product_id_val  .
                                       ' for store ' . $shop_id_in . ' before save ' .
                                       print_r(
                                           $prod_index->active,
                                           1
                                       ), 'syncdata');
                         $prod_index->save();
                     }
                 } catch (Exception $e) {
                     $this->debbug('## Error. ' . $product_id_val .
                                   ' Set indexer to 0: ' .
                                   $e->getMessage(), 'syncdata');
                 }
                 try {
                     Shop::setContext(shop::CONTEXT_ALL);
                     Search::indexation(false, $product_id_val);
                     unset($this->for_index[$id_product]);
                 } catch (Exception $e) {
                     $this->debbug('## Error. ' . $product_id_val .
                                   ' indexer error: ' .
                                   $e->getMessage(), 'syncdata');
                 }
             }
         }
     }*/

    /**
     * Function that will verify if there is need to call the synchronization again in case the frequency of cron
     * calls is not enough
     * $force bool  for force call to cron reproduce all content
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function verifyRetryCall(
        $force = false
    ) {
        if ($this->block_retry_call) {
            return false;
        }

        $this->debbug('Entry to retry call ', 'syncdata');
        $result = $this->testSlcronExist();

        $register_forProcess = $this->checkRegistersForProccess();

        if (isset($this->load_cron_time_status[0]['updated_at']) &&
            ((count($result) && $register_forProcess) || $force)) {
            $updated_time = strtotime($this->load_cron_time_status[0]['updated_at']);
            $now_is = strtotime($result[0]['timeBD']);

            $execution_frequency_cron = $this->getConfiguration('CRON_MINUTES_FREQUENCY');

            $this->debbug(
                'Execution time of cron is  ' . $updated_time . ' and time limit-> ' . $execution_frequency_cron . ' ',
                'syncdata'
            );
            $next_sync = round($updated_time + $execution_frequency_cron);
            $duration_of_this_process = microtime(1) - $this->sl_time_ini_sync_data_process; //53
            $if_start_now = round($now_is + $duration_of_this_process);
            $restant_seconds_for_next_sync = round($next_sync - $now_is - 10);
            if ($restant_seconds_for_next_sync < - 10) {
                $this->debbug(
                    'Execution cron has been lost,' .
                    ' to advance the wait by launching a call to cron. ',
                    'syncdata'
                );
                $force = true;
            }

            if (($execution_frequency_cron > 0 && $next_sync > $if_start_now) || $force) {
                $this->debbug(
                    'Execution of frequency execution of disponible_for_synchronization is ' .
                    $restant_seconds_for_next_sync .
                    ' and time limit-> ' . $this->max_execution_time . ' next synchronization from
                     cron espected at ' . date(
                        'd-m-Y H:i:s',
                        $next_sync
                    ) . ' now with duration of this porocess if start terminate at ->' . date(
                        'd-m-Y H:i:s',
                        $next_sync
                    ),
                    'syncdata'
                );
                //verify load of cpu
                $load = sys_getloadavg();
                if ($load[0] > $this->cpu_max_limit_for_retry_call) {
                    $this->debbug(
                        '## Warning. The call will not be executed to start the process because the ' .
                         'cpu of the server is heavily loaded,' .
                         'it will try to synchronize later when the cron resumes the synchronization. ' .
                          'cpu loaded->' .
                        print_r(
                            $load[0],
                            1
                        ) .
                        ' limit is ->' . $this->cpu_max_limit_for_retry_call,
                        'syncdata'
                    );
                }

                if ((($this->max_execution_time < $restant_seconds_for_next_sync ||
                      $restant_seconds_for_next_sync < -10) || $force)
                    && $load[0] < $this->cpu_max_limit_for_retry_call) {
                    $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
                    $s = '';
                    if (Tools::usingSecureMode()) {
                        $s = 's';
                    }
                    $url =  'http' . $s . '://' . $default_shop->domain . $default_shop->getBaseURI() . 'modules/' .
                        'saleslayerimport/saleslayerimport-cron.php?token=' . Tools::substr(
                            Tools::encrypt('saleslayerimport'),
                            0,
                            10
                        ) .
                        '&internal=1';

                    $this->debbug(
                        'Calling execution of this syncronization $restant_seconds_for_next_sync->' .
                        $restant_seconds_for_next_sync . ' and time limit-> ' . $this->max_execution_time .
                        ' force ->' . print_r(
                            $force,
                            1
                        ) . 'Call RETRY to ' . $url,
                        'syncdata'
                    );

                    $this->urlSendCustomJson('GET', $url, null, false);
                } else {
                    $this->debbug(
                        'Calling this process is not necessary if the prestashop calls to cron appear in
                         sufficient frequency for this process Or cpu is overloaded $restant_seconds_for_next_sync-> ' .
                        $restant_seconds_for_next_sync . ', time limit-> ' .
                        $this->max_execution_time . ' load_cpu->' . $load[0] .
                        ' cpu limit stop config ->' . $this->cpu_max_limit_for_retry_call,
                        'syncdata'
                    );
                }
            } else {
                $this->debbug(
                    ' If another process were to be executed, it would not be complete before the other.
                     $execution_FREQUENCY_cron->' . print_r(
                        $execution_frequency_cron,
                        1
                    ) . 'seconds, $next_sync ->' . print_r(
                        date('d-m-Y H:i:s', $next_sync),
                        1
                    ) . ' $if_start_now terminate at->' . print_r(date('d-m-Y H:i:s', $if_start_now), 1),
                    'syncdata'
                );
            }
        } else {
            if ($force) {
                $this->debbug('Is a force retry call', 'syncdata');
                $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
                $s = '';
                if (Tools::usingSecureMode()) {
                    $s = 's';
                }
                $url =  'http' . $s . '://' . $default_shop->domain . $default_shop->getBaseURI() . 'modules/' .
                       'saleslayerimport/saleslayerimport-cron.php?token=' . Tools::substr(
                           Tools::encrypt('saleslayerimport'),
                           0,
                           10
                       ) .
                       '&internal=1';
                $this->debbug(
                    'Calling execution of this synchronization  and time limit-> ' .
                    $this->max_execution_time . ' force ->' . print_r(
                        $force,
                        1
                    ) . 'Call RETRY to ' . $url,
                    'syncdata'
                );
                try {
                    $this->urlSendCustomJson('GET', $url, null, false);
                } catch (Exception $e) {
                    $this->debbug(
                        'Connection error-> ' . $e->getMessage() .
                        'Call RETRY to ' . $url,
                        'syncdata'
                    );
                }

                return true;
            } else {
                $this->debbug(
                    'Else The call will not be made -> ' . print_r(
                        $result,
                        1
                    ) . ' registers for process ->' . print_r(
                        $register_forProcess,
                        1
                    ),
                    'syncdata'
                );
            }
        }
    }

    /**
     * Delete custom configuration
     * @param $configuration_name
     * @return bool
     */

    public function deleteConfiguration(
        $configuration_name
    ) {
        $sql_sel = 'DELETE FROM ' . $this->saleslayer_aditional_config .
            " WHERE configname = '" . $configuration_name . "'  Limit 1  ";
        $res = $this->slConnectionQuery('-', $sql_sel);
        if ($res) {
            return true;
        }

        return false;
    }
    /**
     * Update performance limit
     * @param $debugmode
     */

    public function setPerformanceLimit(
        $performance
    ) {
        $this->saveConfiguration(['PERFORMANCE_LIMIT' => $performance]);
        $this->cpu_max_limit_for_retry_call = $performance;
    }
    /**
     * Update debugmode
     * @param $debugmode
     */

    public function setDebugMode(
        $debugmode
    ) {
        $this->saveConfiguration(['DEBUGMODE' => $debugmode]);
        $this->debugmode = $debugmode;
    }

    /**
     * Delete any files older than  num defined in $this->logfile_delete_days days
     */

    public function deleteOldDebugFiles()
    {
        $files = glob(DEBBUG_PATH_LOG . '*');
        $now = time();

        foreach ($files as $file) {
            if ($file == 'index.php') {
                continue;
            }
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $this->logfile_delete_days) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get all the shops from a connector of Sales Layer
     * @param $connector_id string id id of connector
     * @return array with the shops of the connector
     * @throws PrestaShopDatabaseException
     */

    protected function getConnectorShops(
        $connector_id
    ) {
        $extra_info = $this->sl_updater->getConnectorExtraInfo($connector_id);

        $shops = array();
        if (isset($extra_info['shops']) && count($extra_info['shops']) > 0) {
            foreach ($extra_info['shops'] as $shop) {
                if ($shop['checked']) {
                    $shops[] = (int)$shop['id_shop'];
                }
            }
        }
        if (empty($shops)) {
            $shops[] = (int)Configuration::get('PS_SHOP_DEFAULT');
        }

        return $shops;
    }

    /**
     * Delete all tables created with plugin
     */

    private function deleteSlyrTables()
    {
        /* from version 1.3 */
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_images');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_category_products');
        /* from version 1.4.0 */
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_' .
                                   $this->sl_updater->table_config);
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_catalogue');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_product_formats');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_products');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_category_product');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_category_products');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_syncdata');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . $this->saleslayer_syncdata_flag_table);
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_image');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . $this->saleslayer_aditional_config);
        /*from version 1.4.7*/
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_attachment');
        /*from version 1.4.20*/
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_indexer');
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_accessories');
    }

    /**
     * Stop indexer if is executed and save stat before stop it
     * @throws PrestaShopDatabaseException
     */


    private function stopIndexer()
    {
        $stat_indexer = Configuration::get('PS_SEARCH_INDEXATION');
        $this->debbug('Indexer stat before stop ' . $stat_indexer, 'syncdata');

        if ($stat_indexer == 1) {
            $this->saveConfiguration(['STAT_INDEXER' => $stat_indexer]);
            Configuration::set('PS_SEARCH_INDEXATION', 0);
            $this->debbug('Indexer Stoped', 'syncdata');
        } else {
            $this->debbug('indexer is already stopped', 'syncdata');
        }
    }

    /**
     * Recalculate the maximum time allowed for this synchronization by checking the frequency of cron calls and
     * the maximum allowed time of execution of cron of php
     * $result array|bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    private function recalculateDurationOfSyncronizationProcess($result = false)
    {
        if ($this->rewrite_execution_frequency) {
            $actual_execution_limit = 0;
            $actual_max_execution_limit = ini_get('max_execution_time');
            $this->debbug('Reed max execution time before discount ->' . $actual_max_execution_limit, 'syncdata');
            if ($actual_max_execution_limit > 0) { // no tiene limite
                $actual_execution_limit = ($actual_max_execution_limit - 10);
                // -10 seconds for the process to end before the other was executed and you have
                // 10 seconds of reservation to finish any process you are doing.
                $this->debbug('Rewrite max execution time to set  ' . $actual_execution_limit . ' -10', 'syncdata');
            }

            $execution_time_cron = $this->getConfiguration('CRON_MINUTES_FREQUENCY');
            if (!$execution_time_cron) {
                $execution_time_cron = 0;
            }

            if ($actual_execution_limit > 0 && $actual_execution_limit <= $execution_time_cron) {
                $transcured = round($actual_execution_limit - ($this->sl_time_ini_process - microtime(1)) - 5);
                $this->debbug(
                    'Set Max execution time from register of ini_get max_execution_time ' . $transcured,
                    'syncdata'
                );
                $this->max_execution_time = $transcured;

                return true;
            }

            if ($execution_time_cron > 0 && $actual_execution_limit >= $execution_time_cron) {
                if (!$result) {
                    $result = $this->testSlcronExist();
                }


                if (count($result)) {
                    $updated_time = strtotime($result[0]['updated_at']);
                    $now_is_from_BD = strtotime($result[0]['timeBD']);
                    $next_run_at = $updated_time + $execution_time_cron;

                    $restand_seconds_for = $next_run_at - $now_is_from_BD;
                    $this->debbug(
                        'It remains seconds until executing another cron ' . print_r($restand_seconds_for, 1),
                        'syncdata'
                    );
                } else {
                    $restand_seconds_for = $actual_execution_limit - ($this->sl_time_ini_process - microtime(1));
                    $this->debbug(
                        'Limit from actual frequency  frequency  ' . print_r($restand_seconds_for, 1),
                        'syncdata'
                    );
                }

                $this->debbug(
                    'Set Max execution time from cron register limit ' . $restand_seconds_for,
                    'syncdata'
                );
                if ($restand_seconds_for > 0) {
                    $this->max_execution_time = round($restand_seconds_for - 5);
                } else {
                    $this->debbug(
                        'Set Max execution time from default-> ' .
                        "because chron's frequency does not seem to be correct ->" . $restand_seconds_for,
                        'syncdata'
                    );
                }
            }
        }
    }

    /**
     * Function to check sync data pid flag in database and delete kill it if the process is stuck.
     * @return void
     */

    private function checkSyncDataFlag()
    {
        if ($this->checkRegistersForProccess()) {
            $current_flag = $this->slConnectionQuery( // slConnectionQuery
                'read',
                'SELECT * FROM ' . $this->saleslayer_syncdata_flag_table . ' ORDER BY id DESC LIMIT 1'
            );

            $now = time();
            $date_now = $now;

            if (!empty($current_flag)) {
                $current_flag = $current_flag[0];
                if ($current_flag['syncdata_pid'] == 0) {
                    $sl_query_flag_to_update = " UPDATE " . $this->saleslayer_syncdata_flag_table .
                        " SET syncdata_pid = " . $this->syncdata_pid . ", syncdata_last_date = '" . $date_now . "'" .
                        " WHERE id = " . $current_flag['id'];

                    $this->slConnectionQuery('-', $sl_query_flag_to_update);
                } else {
                    //$interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
                    $interval = abs($now - $current_flag['syncdata_last_date']);
                    $minutes = round($interval / 60);

                    if ($minutes < 10) {
                        $this->debbug('Data is already being processed.' .
                                      ' Go to terminate this process and wait ' .
                                      'to run retry call if is needed.', 'syncdata');
                        $this->end_process = true;
                        $this->block_retry_call = true;
                    } else {
                        if ($this->syncdata_pid === $current_flag['syncdata_pid']) {
                            $this->debbug('Pid is the same as current.', 'syncdata');
                        }

                        $flag_pid_is_alive = $this->hasPidAlive($current_flag['syncdata_pid']);

                        if ($flag_pid_is_alive > 1) {
                            try {
                                $this->debbug('Killing pid: ' . $current_flag['syncdata_pid'], 'syncdata');

                                $result_kill = posix_kill($current_flag['syncdata_pid'], 0);

                                if (!$result_kill) {
                                    $this->debbug(
                                        '## Error. Could not kill pid ' . $current_flag['syncdata_pid'],
                                        'syncdata'
                                    );
                                }
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. Exception killing pid ' . $current_flag['syncdata_pid'] . ': ' . print_r(
                                        $e->getMessage(),
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                        }

                        $sl_query_flag_to_update = " UPDATE " . $this->saleslayer_syncdata_flag_table .
                            " SET syncdata_pid = " . $this->syncdata_pid .
                            ", syncdata_last_date = '" . $date_now . "'" .
                            " WHERE id = " . $current_flag['id'];

                        $this->slConnectionQuery('-', $sl_query_flag_to_update);
                    }
                }
            } else {
                // $this->debbug('Insert data flag: '.$this->syncdata_pid, 'syncdata');
                $sl_query_flag_to_insert = " INSERT INTO " . $this->saleslayer_syncdata_flag_table .
                    " ( syncdata_pid, syncdata_last_date) VALUES " .
                    "('" . $this->syncdata_pid . "', '" . $date_now . "')";

                $this->slConnectionQuery('-', $sl_query_flag_to_insert);
            }
        }
    }

    /**
     * Function to check current process time to avoid exceding the limit.
     * @return void
     */

    private function checkProcessTime()
    {
        $current_process_time = microtime(1) - $this->sl_time_ini_sync_data_process;

        if ($current_process_time >= $this->max_execution_time) {
            $this->debbug(
                'The predefined time for the synchronization has been exceeded in the next opportunity,
                the synchronization will be calibrated and the synchronization will continue in its next process.->'
                . $current_process_time . ' seconds max_execution_time:' . $this->max_execution_time
            );
            $this->end_process = true;
        }
    }

    /**
     * Function to check sql rows to delete from sync data table.
     * @return void
     */

    private function checkSqlItemsDelete(
        $force_delete = false
    ) {
        if (count($this->sql_items_delete) >= $this->sql_insert_limit
            || ($force_delete
                && count(
                    $this->sql_items_delete
                ) > 0)
        ) {
            $sql_items_to_delete = implode(',', $this->sql_items_delete);

            $sql_delete = " DELETE FROM " . _DB_PREFIX_ . "slyr_syncdata" .
                " WHERE id IN (" . $sql_items_to_delete . ")";

            $this->slConnectionQuery('-', $sql_delete);

            $this->sql_items_delete = array();
        }
    }

    /**
     * Function to update items depending on type.
     * @return array
     */

    private function updateItems(
        $items_to_update
    ) {
        $processed = array();
        foreach ($items_to_update as $item_to_update) {
            $this->checkProcessTime();
            $this->checkSqlItemsDelete();

            if ($this->end_process) {
                $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                break;
            } else {
                $sync_tries = $item_to_update['sync_tries'];

                if (isset($item_to_update['sync_params']) &&
                    !empty($item_to_update['sync_params']) &&
                    $item_to_update['sync_params'] != '') {
                    $sync_params = json_decode($item_to_update['sync_params'], 1);
                    if (isset($sync_params['conn_params']) && !empty($sync_params['conn_params'])) {
                        $this->processing_connector_id = $sync_params['conn_params']['connector_id'];
                        $this->comp_id = $sync_params['conn_params']['comp_id'];
                        $this->conector_shops_ids = $sync_params['conn_params']['shops'];
                    }
                }

                $item_data = json_decode($item_to_update['item_data'], 1);
                $result_update = 'item_not_updated';
                if ($item_data == '') {
                    $this->debbug(
                        "## Error. Decoding item's data: " . print_r($item_to_update['item_data'], 1),
                        'syncdata'
                    );
                } else {
                    switch ($item_to_update['item_type']) {
                        case 'category':
                            $this->sl_catalogues->loadCategoryImageSchema(
                                $sync_params['conn_params']['data_schema']
                            );
                            $this->default_category_id = $item_data['defaultCategory'];

                            $time_ini_sync_stored_category = microtime(1);
                            $this->debbug(' >> Category synchronization initialized << ', 'syncdata');
                            try {
                                $result_update = $this->sl_catalogues->syncOneCategory(
                                    $item_data['sync_data'],
                                    $sync_params['conn_params']['data_schema_info'],
                                    $this->processing_connector_id,
                                    $this->comp_id,
                                    $sync_params['conn_params']['currentLanguage'],
                                    $this->conector_shops_ids,
                                    $item_data['defaultCategory']
                                );
                            } catch (Exception $e) {
                                $result_update = 'item_not_updated';
                                $this->debbug(
                                    '## Error. Synchronizing category ' . print_r($e->getMessage(), 1),
                                    'syncdata'
                                );
                            }

                            // $result_update = $this->sync_stored_category($item_data);
                            $this->debbug(' >> Category synchronization finished << ', 'syncdata');
                            $this->debbug(
                                '#### time_sync_stored_category: ' . $item_data['sync_data']['ID'] . '->' .
                                (
                                    microtime(
                                        1
                                    ) - $time_ini_sync_stored_category
                                ) .
                                ' seconds.',
                                'timer'
                            );
                            break;

                        case 'product':
                            $this->debbug(' >> Product start << ', 'syncdata');
                            $this->sl_products->loadProductImageSchema($sync_params['conn_params']['data_schema']);
                           /* $this->loadConnectorAccesories('accessories', 'product_accessories');
                            $this->loadConnectorAccesories('index', 'for_index');*/

                            $time_ini_sync_stored_product = microtime(1);
                            $this->debbug(' >> Product synchronization initialized << ', 'syncdata');

                            try {
                                $this->debbug(' sync categories -> ' .
                                              print_r($this->sync_categories, 1), 'syncdata');
                                $result_update_array = $this->sl_products->syncOneProduct(
                                    $item_data['sync_data'],
                                    $sync_params['conn_params']['data_schema_info'],
                                    $this->comp_id,
                                    $this->conector_shops_ids,
                                    $sync_params['conn_params']['avoid_stock_update'],
                                    $sync_params['conn_params']['sync_categories'],
                                    $this->processing_connector_id
                                );
                                $result_update = $result_update_array['stat'];




                                /* $this->saveStatAccessories('accessories', 'product_accessories');
                                 $this->saveStatAccessories('index', 'for_index');*/
                            } catch (Exception $e) {
                                $result_update = 'item_not_updated';
                                $this->debbug(
                                    '## Error. Synchronizing product ' . print_r(
                                        $e->getMessage(),
                                        1
                                    ) . ' line->' . $e->getLine()
                                    . ' trace->' . print_r(
                                        $e->getTrace(),
                                        1
                                    ),
                                    'syncdata'
                                );
                            }

                            // $result_update = $this->sync_stored_product($item_data);
                            $this->debbug(' >> Product synchronization finished << ', 'syncdata');
                            $this->debbug(
                                '#### time_sync_stored_product: ' . $item_data['sync_data']['ID'] . '->' . (
                                    microtime(
                                        1
                                    ) - $time_ini_sync_stored_product
                                ) .
                                ' seconds.',
                                'timer'
                            );
                            break;

                        case 'product_format':
                            $this->sl_variants->loadVariantImageSchema($sync_params['conn_params']['data_schema']);
                           // $this->loadConnectorAccesories('index', 'for_index');
                            $time_ini_sync_stored_product_format = microtime(1);
                            $this->debbug(' >> Format synchronization initialized << ', 'syncdata');
                            try {
                                $result_update = $this->sl_variants->syncOneVariant(
                                    $item_data['sync_data'],
                                    $sync_params['conn_params']['data_schema_info'],
                                    $this->processing_connector_id,
                                    $this->comp_id,
                                    $this->conector_shops_ids,
                                    $sync_params['conn_params']['currentLanguage'],
                                    $sync_params['conn_params']['avoid_stock_update']
                                );

                                $result_update = $result_update['stat'];
                                //  $this->saveStatAccessories('index', 'for_index');
                            } catch (Exception $e) {
                                $result_update = 'item_not_updated';
                                $this->debbug(
                                    '## Error. Synchronizing Variant ' . print_r(
                                        $e->getMessage(),
                                        1
                                    ) . ' trace->' . print_r(
                                        $e->getTrace(),
                                        1
                                    ) . ' line->' . $e->getLine(),
                                    'syncdata'
                                );
                            }
                            //$result_update = $this->sync_stored_product_format($item_data);
                            $this->debbug(' >> Format synchronization finished << ', 'syncdata');
                            $this->debbug(
                                '#### time_sync_stored_product_format: ' . $item_data['sync_data']['ID'] . '-> ' .
                                (
                                    microtime(
                                        1
                                    ) - $time_ini_sync_stored_product_format
                                ) .
                                ' seconds.',
                                'timer'
                            );
                            break;

                        case 'accessories':
                            $time_ini_sync_stored_product_accessories = microtime(1);
                            $this->debbug(' >> Product accessories << ');
                          //  $this->loadConnectorAccesories('accessories', 'product_accessories');
                            try {
                                $this->syncAccesories();
                                $result_update = 'item_updated';
                            } catch (Exception $e) {
                                $result_update = 'item_not_updated';
                                $this->debbug(
                                    '## Error. Synchronizing accessories ' . print_r(
                                        $e->getMessage(),
                                        1
                                    )
                                );
                            }

                            $this->debbug(' >> Product Accessories END << ');
                            $this->debbug(
                                '#### time_sync_stored_product_accessories: ' .
                                (
                                    microtime(
                                        1
                                    ) - $time_ini_sync_stored_product_accessories
                                ) .
                                ' seconds.',
                                'timer'
                            );
                            break;
                        default:
                            $this->debbug('## Error. Incorrect item: : ' .
                                print_r($item_to_update, 1), 'syncdata');
                            break;
                    }
                }

                switch ($result_update) {
                    case 'item_not_updated':
                        $this->debbug(
                            '## Error. item could not be synchronized: ' .
                            $item_to_update['item_type'] . ': ' . print_r(
                                $item_to_update,
                                1
                            ),
                            'syncdata'
                        );
                        $sync_tries++;

                        if ($sync_tries > 2) {
                            if ($item_to_update['item_type'] == 'category') {
                                $this->sl_catalogues->reorganizeCategories($this->conector_shops_ids);
                            }

                            $this->sql_items_delete[] = $item_to_update['id'];
                        } else {
                            $this->debbug(
                                '## Error. item_not_updated: : ' .
                                print_r($item_to_update, 1),
                                'syncdata'
                            );
                            $sql_update = ' UPDATE ' . _DB_PREFIX_ . 'slyr_syncdata ' .
                                          " SET status = 'no' " .
                                          ' WHERE id = ' . $item_to_update['id'];

                            $this->slConnectionQuery('-', $sql_update);
                        }
                        $this->clearDebugContent();
                        break;

                    default:
                        $this->allocateMemory();
                        $this->clearDebugContent();
                        $processed[] = str_replace('_', ' ', $item_to_update['item_type']);
                        $this->sql_items_delete[] = $item_to_update['id'];
                        break;
                }
            }
        }

        $this->checkSqlItemsDelete(true);


        return $processed;
    }

    /**
     * Start indexer if stat is
     * @throws PrestaShopException
     */

    private function startIndexer()
    {
        $before_start_status_indexer = $this->getConfiguration('STAT_INDEXER');
        if ($before_start_status_indexer) {
            Configuration::set('PS_SEARCH_INDEXATION', $before_start_status_indexer);
            $this->debbug('Indexer Update ->' .
                          print_r($before_start_status_indexer, 1), 'syncdata');
        }
        $this->callIndexer();
    }

    /**
     * Call to indexers reindex all
     * @throws PrestaShopException
     *
     */

    private function callIndexer()
    {
        /*
          Deprecated! now indexes immediately with synchronization
        */

        // This is code for reindex all.
        // But it makes too much use for the cpu in verision 1.7.6.0 version is deprecated.
        /*  $admin_folder = $this->getConfiguration('ADMIN_DIR');
          $contextShopID = Shop::getContextShopID();
          Shop::setContext(Shop::CONTEXT_ALL);
          $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
          $adminurl = 'http://' . $default_shop->domain . $default_shop->getBaseURI() .
                          $admin_folder . '/searchcron.php?full=1&token=' .
                  Tools::substr(
                      _COOKIE_KEY_,
                      34,
                      8
                  );
          Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
          $this->debbug('Calling indexer to start reindex all ', 'syncdata');
          $this->urlSendCustomJson('GET', $adminurl, null, false);*/
        $default_shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
        $s = '';
        if (Tools::usingSecureMode()) {
            $s = 's';
        }
        $url =  'http' . $s . '://' . $default_shop->domain . $default_shop->getBaseURI() . 'modules/' .
                'saleslayerimport/saleslayerimport-indexer.php?token=' .
                Tools::substr(
                    Tools::encrypt('saleslayerimport'),
                    0,
                    10
                );
        $this->debbug(
            'Calling execution of indexer and time limit-> '
            . 'Call sales layer indexer to ' . $url,
            'syncdata'
        );
        try {
            $this->urlSendCustomJson('GET', $url, null, false);
        } catch (Exception $e) {
            $this->debbug(
                'Connection error-> ' . $e->getMessage() .
                'Call RETRY to ' . $url,
                'syncdata'
            );
        }


        /**
         * If you need to execute something after synchronization add the url here and uncomment the script
         */
/*
        $url_for_run = 'Place hare one url for run after sync';
        $this->urlSendCustomJson('GET', $url_for_run, null, false);
*/
    }

    /**
     * Function to disable sync data pid flag in database.
     * @return void
     */
    private function disableSyncDataFlag()
    {
        $current_flag = $this->slConnectionQuery(
            'read',
            "SELECT * FROM " . $this->saleslayer_syncdata_flag_table . " ORDER BY id DESC LIMIT 1"
        );

        if (!empty($current_flag)) {
            $sl_query_flag_to_update = "UPDATE " . $this->saleslayer_syncdata_flag_table .
                " SET syncdata_pid = 0" .
                " WHERE id = " . $current_flag[0]['id'];
            $this->slConnectionQuery('-', $sl_query_flag_to_update);
        }
    }

    /**
     * Returns differences between two arrays
     * @param $array1
     * @param $array2
     *
     * @return array
     */
    public function slArrayDiff($array1, $array2)
    {
        $diff = array();
        foreach ($array1 as $value) {
            if (!in_array($value, $array2, false)) {
                $diff[] = $value;
            }
        }
        foreach ($array2 as $value) {
            if (!in_array($value, $array1, false)) {
                $diff[] = $value;
            }
        }
        return $diff;
    }

    /**
     *
     * @return array|bool
     * @throws Exception
     */
    public function checkServerUse()
    {
        $memInfo = $this->getSystemMemInfo();
        if ($memInfo) {
            $recurses = array();
            $totalMemory     = $memInfo['MemTotal'];
            $cachedMemory    = $memInfo['Cached'];
            $swapTotalMemory = $memInfo['SwapTotal'];
            $swapFreeMemory  = $memInfo['SwapFree'];
            $realFreeMemory = $totalMemory - $cachedMemory;
            /**
             * mem
             */
            $showtotalMemory = round($totalMemory / 1000);
            $showFreeMemory  = round($realFreeMemory / 1000);
            $onePercent      = round($showtotalMemory / 100);
            $percentualyuse  = round(100 - ($showFreeMemory / $onePercent), 2);
            $recurses['mem'] = $percentualyuse;
            $recurses['frmem'] = $showFreeMemory;


            /**
             * cpu
             */
            $loads = sys_getloadavg();
            /*  $core_nums = (int) trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
              $load = round(($loads[0] / $core_nums) * 100, 2);*/
            $load = round(($loads[0] / 12) * 100, 2);
            $recurses['cpu']   =  $load;

            /**
             * swap
             */
            $percentualyuseswp = 0;
            if ($swapTotalMemory > 0) {
                $showtotalMemoryswp = round($swapTotalMemory / 1000);
                $showFreeMemoryswp  = round($swapFreeMemory / 1000);
                $onePercentswp      = round($showtotalMemoryswp / 100);
                $percentualyuseswp  = round(100 - ($showFreeMemoryswp / $onePercentswp), 2);
            }
            $recurses['swp'] = $percentualyuseswp;

            return $recurses;
        }
        return false;
    }

    /**
     * Get System memory usage
     * @return array|null
     * @throws Exception
     */
    private function getSystemMemInfo()
    {
        $meminfo = Tools::file_get_contents('/proc/meminfo');
        if ($meminfo) {
            $data = explode("\n", $meminfo);
            $meminfo = [];
            foreach ($data as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $val) = explode(':', $line);
                    $val = trim($val);
                    $val = preg_replace('/ kB$/', '', $val);
                    if (is_numeric($val)) {
                        $val = (int) $val;
                    }
                    $meminfo[$key] = $val;
                }
            }
            return $meminfo;
        }
        return  null;
    }

    /**
     * Function to create low memory warning
     * @return bool
     * @throws Exception
     */

    public function checkFreeSpaceMemory()
    {
        $memInfo = $this->getSystemMemInfo();
        if ($memInfo) {
            $totalMemory = $memInfo['MemTotal'];
            $cachedMemory = $memInfo['Cached'];
            $realFreeMemory = $totalMemory - $cachedMemory;
            $swapTotalMemory = $memInfo['SwapTotal'];
            $swapFreeMemory = $memInfo['SwapFree'];
            $showtotalMemory = round($totalMemory / 1000);
            $showcachedMemory = round($cachedMemory / 1000);
            $showreal_usedMemory = $showtotalMemory - $showcachedMemory;
            $onePercent = round($showtotalMemory / 100);
            $percentualyuse =  round($showcachedMemory / $onePercent);
            $this->debbug(
                'Total memory of this server->' .
                print_r($showtotalMemory, 1) . ' Mb ' .
                           'Free Memory for usage->' .
                print_r($showreal_usedMemory, 1) . ' Mb ' .
                ' Free memory ->' . print_r($percentualyuse, 1) . '%'
            );
            $max_memory = $this->getConfiguration('MAX_MEMORY_USAGE');
            if ($max_memory) {
                if ($max_memory > $realFreeMemory) {
                    $this->debbug('## Warning. The previous time has taken up more memory than is available now.' .
                                  ' If much more data is received, it is possible that the server' .
                                  ' does not support this amount of data. Free memory ->' .
                                  print_r($realFreeMemory, 1) . ' Mb ' .
                                  'Last time you needed memory ->' .
                                  print_r($max_memory, 1) . ' Mb ');
                }
            }

            if (($totalMemory / 100.0) * 30.0 > $realFreeMemory) {
                if (($swapTotalMemory / 100.0) * 50.0 > $swapFreeMemory) {
                    $this->debbug('## Warning. Less than 30% free memory' .
                                  ' and less than 50% free swap space.');
                }
                $this->debbug('## Warning. Less than 30% free memory.' .
                              ' Your server may not be able to finish the download if it runs out of memory.' .
                              ' Review your server settings to optimize your needs. ' .
                              ' Through the SSH console you can execute "top" command to see what ' .
                              ' processes your server resources are consuming.');
                return true;
            }
        }
        $this->debbug('Server has more than 30% memory left. test ok');
    }

    /**
     * Analyze content y load it to array
     * Verifica si contenido es un json y reestructura los datos a un array si es posible
     * @param $v mixed
     * @return array|mixed
     */
    public function clearStructureData($v)
    {
        $array_decoded = json_decode($v, true);
        if (is_array($array_decoded) && count($array_decoded)) {
            $v = $array_decoded;
            $this->debbug('Contenido  convertido en array de un json ->' .
                          print_r($array_decoded, 1), 'syncdata');
        }
        return  $v;
    }
    /**
     * Verifica si ha sido usada alguna tabla para rellenar este contenido y elimina primer nivel de array
     * @param $v
     * @return array
     */
    public function clearAndOrderArray($v)
    {
        $cleared = array();
        foreach ($v as $values_for_array) {
            if (is_array($values_for_array)) {
                $cleared[] = reset($values_for_array);
            } else {
                $cleared[] = $values_for_array;
            }
        }
        $v = $cleared;
        return $v;
    }
    public function createIntegrity()
    {
        $files = $this->checkIntegrity();
        $this->saveIntegrity($files);
        return $files;
    }
    private function saveIntegrity($files)
    {
        if (!file_exists($this->integrityPathDirectory)) {
            if (!mkdir($this->integrityPathDirectory, 0775, true) && ! is_dir($this->integrityPathDirectory)) {
                $this->debbug("## Error. Directory was not created -> $this->integrityPathDirectory");
            }
        }
        if (file_exists($this->integrityPathDirectory . $this->integrityFile)) {
            unlink($this->integrityPathDirectory . $this->integrityFile);
        }
        file_put_contents($this->integrityPathDirectory . $this->integrityFile, json_encode($files), FILE_APPEND);
        chmod($this->integrityPathDirectory . $this->integrityFile, 0775);
    }

    private function checkIntegrity()
    {
        try {
            $ignoredirectories = array('logs','integrity','saleslayerimport.php');
            $files      = array();
            $log_folder_files = array_slice(scandir($this->plugin_dir), 2);
            foreach ($log_folder_files as $file) {
                if (in_array($file, $ignoredirectories, false)) {
                    continue;
                }
                $real_link =  $this->plugin_dir . $file;
                if (is_dir($real_link)) {
                    $this->mapDirectory($real_link, $files);
                } else {
                    if (file_exists($real_link)) {
                        $content = Tools::file_get_contents($real_link);
                        $checkmd5 = hash('md5', $content);
                        $files[$checkmd5] = $file;
                    }
                }
            }
        } catch (Exception $e) {
            $this->debbug('## Error. There was a problem verifying the' .
                          ' integrity of the plugin ->' . print_r($e->getMessage(), 1));
        }
        return $files;
    }

    /**
     * Compare predefined integrity with installed
     * @return bool
     */
    public function compareIntegrity()
    {
        $load_predefined_integrity = $this->loadIntegrity();
        $check_actual_integrity = $this->checkIntegrity();
        $integrity_ok = true;
        if (count($check_actual_integrity)) {
            foreach ($load_predefined_integrity as $md5_gen => $file_gen) {
                foreach ($check_actual_integrity as $md5_test => $file_test) {
                    if ($file_test == $file_gen) {
                        if ($md5_gen != $md5_test) {
                            $integrity_ok = false;
                            $this->debbug('## Warning. file ->' . print_r($file_test, 1)
                                          . ' An anomaly was found in the file, please reinstall' .
                                          ' the plugin or replace file.');
                        }
                        break;
                    }
                }
            }
        }

        return $integrity_ok;
    }

    /**
     * Load integrity map for files
     * @return bool|mixed
     */
    public function loadIntegrity()
    {
        if (file_exists($this->integrityPathDirectory . $this->integrityFile)) {
            $content =  Tools::file_get_contents($this->integrityPathDirectory . $this->integrityFile);
            $decode = json_decode($content, 1);
            if ($decode) {
                return $decode;
            }
        }
        return false;
    }

    /**
     * @param $link
     * @param $files
     *
     * @return mixed
     */
    private function mapDirectory($link, &$files)
    {
        $separate = explode('/', $link);
        foreach ($separate as $key_dir => $directories) {
            unset($separate[$key_dir]);
            if ($directories == $this->name) {
                break;
            }
        }
        $log_folder_files = array_slice(scandir($link), 2);
        foreach ($log_folder_files as $file) {
            $check_link = implode('/', $separate) . '/' . $file;
            $real_link = $this->plugin_dir . $check_link;
            $real_link = str_replace('//', '/', $real_link);
            if (is_dir($real_link)) {
                $this->mapDirectory($real_link, $files);
            } else {
                if (file_exists($real_link)) {
                    $content = Tools::file_get_contents($real_link);
                    $checkmd5 = hash('md5', $content);
                    $files[$checkmd5] = $check_link;
                }
            }
        }
    }

    /**
     * Function to sort connectors by unix_to_update or auto_sync values.
     * @param array $conn_a first connector to sort
     * @param array $conn_b second connector to sort
     * @return integer                      comparative of connectors
     * @deprecated
     */

    private function sortByUnixToUpdate(
        $conn_a,
        $conn_b
    ) {
        $unix_a = $conn_a['unix_to_update'];
        $unix_b = $conn_b['unix_to_update'];

        if ($unix_a == $unix_b) {
            $auto_a = $conn_a['auto_sync'];
            $auto_b = $conn_b['auto_sync'];

            if ($auto_a == $auto_b) {
                return 0;
            }

            return ($auto_a > $auto_b) ? -1 : 1;
        }

        return ($unix_a < $unix_b) ? -1 : 1;
    }
    public function strtotime($string)
    {
        $valuetm =  $this->fomatDate($string);



        $date_val =  strtotime(trim($valuetm));
        if ($date_val != '') {
            $this->debbug('value converted to timestamp if generic function ->' .
                          print_r($valuetm, 1) .
                          ' and return value ->' . print_r($date_val, 1), 'syncdata');
            return $date_val;
        } else {
            $valuetm = trim($valuetm);
            if ($valuetm == 'ahora' || $valuetm == 'now') {
                return time();
            }
            if ($valuetm == 'mañana' || $valuetm == 'tomorrow') {
                $suma = 86400;
                return mktime(
                    0,
                    0,
                    0,
                    date('m', time() + $suma),
                    date('d', time() + $suma),
                    date('Y', time() + $suma)
                );
            }

            $parse_content = explode(' ', $valuetm);
            $hour    = 0;
            $minutes = 0;
            $seconds = 0;
            $year    = date('Y');
            $already_year  = false;
            $month   = date('m');
            $already_month = false;
            $day     = date('d');
            $already_day   = false;


            foreach ($parse_content as $date_content) {
                if (preg_match('/:/', $date_content)) {
                    $parse_hour = explode(':', $date_content);
                    if (count($parse_hour) == 3) {
                        $hour    = (int) trim($parse_hour[0]);
                        $minutes = (int) trim($parse_hour[1]);
                        $seconds = (int) trim($parse_hour[2]);
                    } elseif (count($parse_hour) == 2) {
                        $hour    = (int) trim($parse_hour[0]);
                        $minutes = (int) trim($parse_hour[1]);
                    }
                } elseif (preg_match('/-/', $date_content)) {
                    $parse_date = explode('-', $date_content);
                    if (Tools::strlen($parse_date[0]) == 4) { //yyyy-mm-dd
                        if ((int) $parse_date[0] > 0 && (int) $parse_date[0] < 2080) {
                            $year = (int) $parse_date[0];
                            $already_year = true;
                        }
                        if (Tools::strlen($parse_date[1]) == 2 && (int) $parse_date[1] >= 1 && $parse_date[1] <= 12) {
                            $month   = (int)$parse_date[1];
                            $already_month = true;
                        }
                        if (Tools::strlen($parse_date[2]) == 2 && (int) $parse_date[2] >= 1 && $parse_date[2] <= 31) {
                            $day   = (int)$parse_date[2];
                            $already_day = true;
                        }
                    } elseif (Tools::strlen($parse_date[2]) == 4) { //dd-mm-yyyy
                        if ((int) $parse_date[2] > 0 && (int) $parse_date[2] < 2080) {
                            $year = (int) $parse_date[0];
                            $already_year = true;
                        }
                        if (Tools::strlen($parse_date[1]) == 2 && (int) $parse_date[1] >= 1 && $parse_date[1] <= 12) {
                            $month   = (int)$parse_date[1];
                            $already_month = true;
                        }
                        if (Tools::strlen($parse_date[0]) == 2 && (int) $parse_date[0] >= 1 && $parse_date[0] <= 31) {
                            $day   = (int)$parse_date[2];
                            $already_day = true;
                        }
                    } elseif (count($parse_date) == 2) { //dd-mm
                        if (Tools::strlen($parse_date[1]) == 2 && (int) $parse_date[1] >= 1 && $parse_date[1] <= 12) {
                            $month   = (int)$parse_date[1];
                            $already_month = true;
                        }
                        if (Tools::strlen($parse_date[0]) == 2 && (int) $parse_date[0] >= 1 && $parse_date[0] <= 31) {
                            $day   = (int)$parse_date[2];
                            $already_day = true;
                        }
                    }
                }
            }

            if (($already_month && $already_day && $already_year) ||
                ($already_month && $already_day && !$already_year)) {
                $timestamp = mktime($hour, $minutes, $seconds, $month, $day, $year);
                if (!$timestamp) {
                    return (int) $timestamp;
                }
            }

            $this->debbug('Problem! ' .
                          'In available_date convert this time to timestamp.' .
                          ' Please try another format of date used by strtotime().' .
                          ' Set the original time  ->' .
                          print_r($valuetm, 1), 'syncdata');
        }
        return false;
    }
}

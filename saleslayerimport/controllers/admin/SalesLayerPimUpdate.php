<?php
/**
 * Class for sync all data in Prestashop from Sales-layer PIM
 * By Sales layer developer team
 * Date: 04/03/2019
 * Time: 11:26
 */




class SalesLayerPimUpdate extends SalesLayerImport{

    public $prestashop_all_shops;

    public function __construct(){
        parent::__construct();
        Shop::setContext(Shop::CONTEXT_ALL);
        $this->defaultLanguage = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->prestashop_all_shops = Shop::getShops();

    }



    /**
     * Gets all the info from a connector and send it to update
     * @param  $connector_id string|null id from connector
     * @return bool|int|string $last_update_save last update date
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function store_sync_data($connector_id,$last_sync = null){


        $sql_processing = "SELECT count(*) as sl_cuenta_registros FROM ".$this->saleslayer_syncdata_table;
        $items_processing = $this->sl_connection_query('read', $sql_processing);

        $this->debbug(" reading from table  ".print_r($items_processing,1));
        if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0){

            $this->debbug("There are still ".$items_processing['sl_cuenta_registros']." items processing, wait until is finished and synchronize again.");
            return "There are still ".$items_processing['sl_cuenta_registros']." items processing, wait until is finished and synchronize again.";

        }

        $this->sl_time_ini_process = microtime(1);

        $this->debbug(" ==== Store Sync Data INIT ==== ");
        $this->debbug('conector_id :'.print_r($connector_id,1).' last_sync->'.print_r($last_sync,1));

        if ($last_sync == null){ $last_sync = date('Y-m-d H:i:s'); }

        $this->debbug('before update conector_id :'.print_r($connector_id,1).' last_sync->'.print_r($last_sync,1));
        $this->set_connector_Data($connector_id,'last_sync',$last_sync);

        $update_all = microtime(1);
        $this->debbug(' Empezando la syncronización');
        //$this->checkDB();

        $conn_info   = $this->sl_updater->get_connectors_info($connector_id);
        $secret_key  = $conn_info['conn_secret'];
        $last_update = $conn_info['last_update'];
        $comp_id     = $conn_info['comp_id'];
        $avoid_stock_update      = $conn_info['avoid_stock_update'];




        //Clear registers in Sales Layer table deleted in Prestashop
        $this->clearDeletedSlyrRegs($conn_info['comp_id']);


        $api = new SalesLayer_Conn($connector_id, $secret_key);

        // $api->set_API_version('1.16');
        $api->set_parents_category_tree(true);
        $api->set_group_multicategory(true);

        $this->debbug('last_update: '.$last_update.' date: '.date('Y-m-d', $last_update));

        $this->reasignMemoryNeccesary();

        if ($last_update!= null && $last_update != 0){
            $api->get_info($last_update, null, null, true);
            //$api->get_info(null, null, null, true);
        }else{
            $api->get_info(null, null, null, true);
        }

        $data_returned = $api->get_data_returned();

        $this->reasignMemoryNeccesary();

        if ($api->has_response_error()) {
            $this->debbug( '## Error. : '.$api->get_response_error().' Msg: '.$api->get_response_error_message() );
            return false;
        }


        $this->debbug('Datos de languages '.print_r($data_returned['schema']['languages'],1));


        $this->debbug('Language api_data iso codes  ->'.print_r($data_returned['schema']['languages'],1) );
        $langIso = null;
        try {
            if (isset($data_returned['schema']['languages']) && !empty($data_returned['schema']['languages'])) {
                foreach ($data_returned['schema']['languages'] as $lang) {
                    $ps_id_lang = Language::getIdByIso($lang);
                    if (in_array($ps_id_lang, $this->shop_languages, false)) {
                        $langIso = $lang;
                        break;
                    }
                }
            } elseif (isset($data_returned['schema']['default_language']) && $data_returned['schema']['default_language']) {
                $id_lang = Language::getIdByIso($data_returned['schema']['default_language']);
                if ($id_lang != null) {
                    $langIso = $id_lang;
                }
            }
        }catch(Exception $e){
            $this->debbug('## Error. Schema generation error->'.$e->getMessage().' trace->'.print_r($e->getTrace(),1),'syncdata');
        }
        if ($langIso!= null){
            $this->currentLanguage = Language::getIdByIso($langIso);
            ($this->currentLanguage== null || $this->currentLanguage == 0) ? $this->currentLanguage = $this->defaultLanguage : false;
        }else{
            $this->currentLanguage = $this->defaultLanguage;
        }


        $this->debbug('currentLanguage->'.$this->currentLanguage.'  defaultLanguage ->'.$this->defaultLanguage );
         Configuration::updateValue('CURRENT_LANGUAGE',$this->currentLanguage);


        $this->clear_debug_content();

        $last_update_save = $api->get_response_time('unix');
        $data_schema = $this->get_data_schema($api);
        $table_data = $api->get_response_table_data();



        $catalogue_items           = $this->organizarIndicesTablas($table_data['catalogue']['modified']      , $data_returned['data_schema_info']['catalogue']);
        $product_items             = $this->organizarIndicesTablas($table_data['products']['modified']       , $data_returned['data_schema_info']['products']);
        $product_formats_items     = $this->organizarIndicesTablas($table_data['product_formats']['modified'], $data_returned['data_schema_info']['product_formats']);


        $catalogue_items_del       = $table_data['catalogue']['deleted'];
        $product_items_del         = $table_data['products']['deleted'];
        $product_formats_items_del = $table_data['product_formats']['deleted'];

          $catalogue_items_del       = $this->organizarIndicesTablas($catalogue_items_del      , $data_returned['data_schema_info']['catalogue']);
          $product_items_del         = $this->organizarIndicesTablas($product_items_del        , $data_returned['data_schema_info']['products']);
          $product_formats_items_del = $this->organizarIndicesTablas($product_formats_items_del, $data_returned['data_schema_info']['product_formats']);


        $sync_params = $arrayReturn = array();

        $shops = $this->getConnectorShops($connector_id);

        $this->connector_shops = $shops;

        if ( !empty($product_formats_items) && isset($data_returned['data_schema_info']['product_formats']) && !empty($data_returned['data_schema_info']['product_formats']) ){
            try {
                $this->synchronizeAttributeGroup($data_returned['data_schema_info']['product_formats'], $shops, $connector_id, $comp_id);
            }catch(Exception $e){
                $this->debbug('## Error.  synchronizeAttributeGroup->'.$e->getMessage());
                $this->debbug('## Error.  trace->'.print_r($e->getTrace(),1));
            }
        }

        $sync_params['conn_params']['comp_id']          = $comp_id;
        $sync_params['conn_params']['connector_id']     = $connector_id;
        $sync_params['conn_params']['shops']            = $this->getConnectorShops($connector_id);
        $sync_params['conn_params']['currentLanguage']  = $this->currentLanguage;
        $sync_params['conn_params']['defaultLanguage']  = $this->defaultLanguage;


        $contextShopID = Shop::getContextShopID();

        if (!empty($shops)){
            if ($this->debugmode > 1) $this->debbug('Total count of delete elements to store: '.count($catalogue_items_del));
            $timer_delete_data = microtime(1);

            $sync_type = 'delete';
            if (count($catalogue_items_del) > 0 || count($product_items_del) > 0 || count($product_formats_items_del) > 0 ) {

                $time_ini_store_items_delete = microtime(1);
                if (!empty($catalogue_items_del)) {
                    $item_type = 'category';
                    if ($this->debugmode > 1) $this->debbug('Total count of delete categories to store: '.count($catalogue_items_del));
                    if ($this->debugmode> 1) $this->debbug('Delete categories data to store: '.print_r($catalogue_items_del,1));
                    $arrayReturn['categories_to_delete'] = count($catalogue_items_del);
                    foreach ($catalogue_items_del as $catalog) {

                        $item_data['sl_id'] = $catalog;
                        $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                        $this->insert_syncdata_sql();


                    }
                }

                if (!empty($product_items_del)) {
                    $item_type = 'product';

                    if ($this->debugmode> 1) $this->debbug('Total count of delete products to store: '.count($product_items_del));
                    if ($this->debugmode > 1) $this->debbug('Delete products data to store: '.print_r($product_items_del,1));
                    $arrayReturn['products_to_delete'] = count($product_items_del);

                    foreach ($product_items_del as $product) {

                        $item_data['sl_id'] = $product;
                        $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                        $this->insert_syncdata_sql();

                    }
                }

                if (!empty($product_formats_items_del)) {
                    $item_type = 'product_format';

                    if ($this->debugmode > 1) $this->debbug('Total count of delete product formats to store: '.count($product_formats_items_del));
                    if ($this->debugmode > 1) $this->debbug('Delete product formats data to store: '.print_r($product_formats_items_del,1));
                    $arrayReturn['product_formats_to_delete'] = count($product_formats_items_del);
                    foreach ($product_formats_items_del as $product_format) {

                        $item_data['sl_id'] = $product_format;
                        $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
                        $this->insert_syncdata_sql();


                    }
                }

                $this->debbug('#### time_store_items_delete - '.$item_type.': '.(microtime(1) - $time_ini_store_items_delete).' seconds.');
            }

            $this->insert_syncdata_sql(true);

            $this->debbug('Despues de delete apì data  ->'.(  microtime(1) - $timer_delete_data ).'s');
            $this->clear_debug_content();

            $timer_sync_apidata = microtime(1);

          if(count($catalogue_items)> 0 || count($product_items)> 0 || count($product_formats_items)> 0){
              if ($this->debugmode > 2) $this->debbug('Total count of modified elements to store: '.count($catalogue_items_del));
              $sync_type = 'update';
              $time_ini_store_items_update = microtime(1);



                $this->debbug(' Empezando con la syncronizacion y enviando la info a la tienda con el shops_ids ->'.print_r($shops,1));
                /**


                Sync Catalogues


                 */

                if (!empty($catalogue_items)) {


                    $item_type = 'category';
                    $sync_params['conn_params']['data_schema_info'] = $data_returned['data_schema_info']['catalogue'];
                    $sync_params['conn_params']['data_schema']      = $data_schema['catalogue'];
                    $defaultCategory = (int) Configuration::get('PS_HOME_CATEGORY');
                    $defaultCategory = $this->checkDefaultCategory($defaultCategory);
                    $catalogue_items = $this->reorganizeCategories($catalogue_items);

                    if ($this->debugmode > 1) $this->debbug('Total count of sync categories to store: '.count($catalogue_items));
                    if ($this->debugmode > 2) $this->debbug('Sync categories data to store: '.print_r($catalogue_items,1));


                    $arrayReturn['categories_to_sync'] = count($catalogue_items);
                    foreach($catalogue_items as $catalog) {
                        $data_insert = array();
                        $data_insert['sync_data']        = $catalog;
                        $data_insert['defaultCategory']  = $defaultCategory;

                        $item_data_to_insert = html_entity_decode(json_encode($data_insert));
                        $sync_params_to_insert = json_encode($sync_params);

                        $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                        $this->insert_syncdata_sql();

                    }

                }

                /**


                Sync all Products


                 */

                if(!empty($product_items)){

                    $item_type = 'product';

                    if ($this->debugmode > 1) $this->debbug('Total count of sync products to store: '.count($product_items));
                    if ($this->debugmode > 2) $this->debbug('Sync products data to store: '.print_r($product_items,1));

                    $this->product_accessories = array();
                    $sync_params['conn_params']['data_schema_info'] = $data_returned['data_schema_info']['products'];
                    $sync_params['conn_params']['data_schema']      = $data_schema['products'];
                    $sync_params['conn_params']['avoid_stock_update']  = $avoid_stock_update;
                    $arrayReturn['products_to_sync'] = count($product_items);
                    foreach($product_items as $product) {

                        $data_insert = array();
                        $data_insert['sync_data']        = $product;

                        $item_data_to_insert   = html_entity_decode(json_encode($data_insert));
                        $sync_params_to_insert = json_encode($sync_params);

                        $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                        $this->insert_syncdata_sql();

                    }
                }

                /**


                 Sync Variants


                 */

                if(!empty($product_formats_items)){


                    $item_type = 'product_format';

                    if ($this->debugmode > 1) $this->debbug('Total count of sync product formats to store: '.count($product_formats_items));
                    if ($this->debugmode > 2) $this->debbug('Product formats data: '.print_r($product_formats_items,1));

                    $sync_params['conn_params']['data_schema_info'] = $data_returned['data_schema_info']['product_formats'];
                    $sync_params['conn_params']['data_schema']      = $data_schema['product_formats'];
                    $sync_params['conn_params']['avoid_stock_update']  = $avoid_stock_update;
                    $product_formats_items = $this->reorganizeProductFormats($product_formats_items);
                    $arrayReturn['product_formats_to_sync'] = count($product_formats_items);


                     foreach($product_formats_items as $product_format) {
                         $data_insert = array();
                         $data_insert['sync_data']        = $product_format;

                         $item_data_to_insert = html_entity_decode(json_encode($data_insert));
                         $sync_params_to_insert = json_encode($sync_params);

                         $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
                         $this->insert_syncdata_sql();

                     }

                }

              $this->insert_syncdata_sql(true);
                Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);


            $this->debbug('After synchronizeApiData duration: ->'.(  microtime(1) - $timer_sync_apidata).'s');
            unset($this->sl_catalogues,$this->sl_products,$this->sl_variants);

        }
        }

        if (!$api->has_response_error()) {
            $this->debbug('Actualizando last update ->'. $last_update_save.' ');
            $this->sl_updater->set_connector_last_update($connector_id,$last_update_save);

            //call to cron for sincronize all cached data
            if($this->checkRegistersForProccess()){
                $this->verifyRetryCall(true);
            }

        }

        $this->clear_debug_content();
        $this->debbug('==== Store Sync Data END  duration of process  ->'.(  microtime(1) - $update_all).'s  at '.date('d-m-Y H:i:s' ));
        $this->delete_old_debug_files();


        if ( count($catalogue_items) > 0 || count($catalogue_items_del) > 0  || count($product_items) > 0 || count($product_items_del) > 0 || count($product_formats_items) > 0 || count($product_formats_items_del) > 0 ) {
            return $arrayReturn;
        }else{
            return false;
        }

    }
    /**
     * combinations given multiple arrays generates all the possible combinations
     *
     * @param array $arr values from multiple arrays
     * @param int $n (default null) actual index to go through.
     * @param array $res (default empty) actual combinations at the moment.
     * @param string entity 'products' or 'categories'
     * @return void
     */
    protected function combinations($arr, $n, $res = array()){

        foreach ($arr[$this->format_value_fields[$n]] as $item){
            $res[$this->format_value_fields[$n]] = $item;

            if ($n==count($arr)-1){
                $this->final_format_array[] = $res;
            }else{
                $this->combinations($arr,$n+1, $res);
            }
        }

    }

    /**
     * copyImg copy an image located in $url and save it in a path
     * according to $entity->$id_entity .
     * @param $id_entity int id of product or category (set in entity)
     * @param $id_image string|void|array is used if we need to add a watermark
     * @param $url string path or url to use
     * @param string $entity string entity 'products' or 'categories'
     * @param bool $regenerate
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function copyImg($id_entity, $id_image = null, $url, $entity = 'products', $regenerate = true, $isfile = false){
        $this->debbug('Process image entry.'.print_r($url,1).' of entity '.$entity ,'syncdata');
        $timing_process_image = microtime(1);

        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity){

            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_.(int)$id_entity;
                break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_.(int)$id_entity;
                break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_.(int)$id_entity;
                break;
        }

        if(!$isfile){
            $url = str_replace(' ', '%20', trim($url));

        }

        if (!ImageManager::checkImageMemoryLimit($url)) {
            if($isfile){
                unlink($url);
            }

            $this->debbug('## Error. There is not enough memory space to process the image' ,'syncdata');
            return false;
        }



        if(!$isfile) {
            $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_sl_import');
            $this->debbug('no es file .'.print_r($url,1).' of entity '.$entity.'ha generado'.$tmpfile  ,'syncdata');
            if (ini_get('allow_url_fopen')) {
                $resultado = copy($url, $tmpfile);
            } else {
                $resultado = file_get_contents($url);
                if ($resultado) $resultado = file_put_contents($tmpfile, $resultado);
            }
        }else{
            $resultado = $url;
        }


        if ($resultado){

            ImageManager::resize($resultado, $path.'.jpg');

            $images_types = ImageType::getImagesTypes($entity);

            if ($regenerate){
                foreach ($images_types as $image_type)
                {

                    ImageManager::resize($resultado, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'], $image_type['height']);

                    if (in_array($image_type['id_image_type'], $watermark_types,false))
                        Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));

                }
            }else{
                $this->debbug('## Error. The image could not be copied.' ,'syncdata');
            }

        }else{
            unlink($resultado);
            $this->debbug('## Error. Process image error result false.  time to process:'.(microtime(1) - $timing_process_image),'syncdata');
            return false;
        }

        unlink($resultado);
        $this->debbug('Process image complete. time to process:'.(microtime(1) - $timing_process_image),'syncdata');
        return true;
    }

    public function downloadImageToTemp($url){
      //  $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_sl_import');
        if(file_exists($tmpfile)){unlink($tmpfile);}
      $response =  $this->urlSendCustomJson('GET',$url);
       // $extension =   $this->getExensionFile($url);
        if($response[0]){
            $this->debbug('file temp generado para imagen :'.($tmpfile),'syncdata');
            $response =  file_put_contents($tmpfile, $response[1]);
            if($response){
                unset($response);
                $this->debbug('sending file filled with the file :'.$tmpfile,'syncdata');
                return $tmpfile;
            }else{
                $this->debbug('Image error could not be saved on downloaded :'.$tmpfile,'syncdata');
                unlink($tmpfile);
                unset($response);
                return false;
            }
        }else{
            $this->debbug('Error imaen no ha podido ser descargado :'.$tmpfile,'syncdata');
            unset($tmpfile);
            return false;
        }

    }
    public function getExensionFile($url){
        $url_array = explode('/',$url);
        $namefile = end($url_array);
        $extension_array = explode('.',$namefile);
            unset($url_array,$namefile);
        return end($extension_array);
    }
   /* public function fast_request($url)
    {
        $parts=parse_url($url);
        $fp = fsockopen($parts['host'],isset($parts['port'])?$parts['port']:80,$errno, $errstr, 30);
        $out = "GET ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "Content-Length: 0"."\r\n";
        $out.= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        fclose($fp);
    }*/
    public function urlSendCustomJson($type,$url,$json = null,$wait_for_response = true){
        $time_ini_urlsendcustomjson = microtime(1);
        $ch = curl_init($url);
        $agent = 'SALES-LAYER PIM, Connector Prestashop->'.$this->name.', Sync-Data';
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        if($json !== NULL){
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json))
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        if(!$wait_for_response){
            $this->debbug('conection with timeout','syncdata');
            curl_setopt($ch, CONNECTION_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,5);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS,5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        }else{
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($ch,CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, FALSE);

        $time_ini_curl_exec = microtime(1);
        $result   = curl_exec($ch);

        $time_ini_curl_getinfo = microtime(1);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if($httpcode>=200 && $httpcode<300){
            $http_stat = true;
        }else{
            if($wait_for_response) {
                if ($this->debugmode && $httpcode == 0) {
                    $this->debbug(' curl error result  ' . curl_error($ch),'syncdata');
                }
                if ($this->debugmode) {
                    $this->debbug(' error result connection http:' . $httpcode . ' -> ' . print_r($result, 1) . ' type :' . $type . '   json decoded-> ' . print_r(json_decode($json, 1), 1) . ' URL -> ' . $url,'syncdata');
                }
                $http_stat = false;
            }else{
                $http_stat = true;
            }
        }
        curl_close($ch);
        unset($ch,$url,$json);
        return array($http_stat,$result,$httpcode);
    }

    /**
     * synchronize an attribute group
     *
     * @param array $tablaStructure structure with attribute groups to synchronize
     * @param array $conn_shops shops from the connector
     * @param string $connector_id id of connector
     * @param string $comp_id id of company in SL
     * @param string $currentLanguage id of Default language selected
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function synchronizeAttributeGroup($tablaStructure, $conn_shops, $connector_id,$comp_id){

        $this->debbug(' entra con shops ->'.print_r($conn_shops,1));
        // $defaultLanguage = (int) Configuration::get('PS_LANG_DEFAULT');
        // $currentLanguage = (int) Configuration::get('CURRENT_LANGUAGE');
        // $comp_id = Configuration::get('SLY_SCHEMA_COMPANY_ID');
       // $comp_id = $this->comp_id;

        $fieldName = '';

        $formats_update_shops = array();

        foreach ($tablaStructure as $keyStruct => $campoStruct) {



            $this->debbug(' $keyStruct->'.$keyStruct.' $campoStruct ->'.print_r($campoStruct,1));
            if (isset($campoStruct['basename'])){
                $fieldName = $campoStruct['basename'];
            }else{
                $fieldName = $keyStruct;
            }

            if (in_array($fieldName, $this->product_format_base_fields,false) || preg_match('/format_supplier_\+?\d+$/', $fieldName) || preg_match('/format_supplier_reference_\+?\d+$/', $fieldName)){
                continue;
            }

            $fieldNamePublic = ucwords(str_replace('_', ' ', $fieldName));

            $format_exists = (int) Db::getInstance()->getValue(sprintf('SELECT gl.`id_attribute_group` FROM '.$this->attribute_group_lang_table.' AS gl JOIN '.$this->slyr_table.' AS sl ON ( gl.`id_attribute_group` = sl.`ps_id` and sl.`ps_type` = "product_format_field") WHERE comp_id = "%s" AND  name LIKE "%s" GROUP BY gl.`id_attribute_group`',  $comp_id ,$fieldName));

            if ($format_exists) {
                //Almacenamos id de formato para actualizar las tiendas
                //array_push($formats_update_shops, $format_exists);
                $formats_update_shops[]= $format_exists;

                Db::getInstance()->execute(
                    sprintf('UPDATE '.$this->slyr_table.' SET date_upd = CURRENT_TIMESTAMP() WHERE ps_id = "%s" AND comp_id = "%s" AND ps_type = "product_format_field"',
                        $format_exists,
                        $comp_id
                    ));

                /**
                 *
                    Complete if is needed names in another languages
                 *
                 */

                $contextShopID = Shop::getContextShopID();
                Shop::setContext(Shop::CONTEXT_ALL);

                $attGroup = new AttributeGroup($format_exists);

                $this->debbug('Name of group attribute exist ->'.print_r($keyStruct,1));

                if(isset($tablaStructure[$keyStruct]['language_code'])){
                    foreach ($this->shop_languages as $lang_sub) {

                        $attribute_search_index = $fieldName.'_'.$lang_sub['iso_code'];
                        if(isset($tablaStructure[$attribute_search_index]['language_code'])){

                            $fieldName = $tablaStructure[$attribute_search_index]['basename'];
                            $fieldNamePublic = $tablaStructure[$attribute_search_index]['basename'];

                                if(!isset($attGroup->name[$lang_sub['id_lang']])|| $attGroup->name[$lang_sub['id_lang']] == null || $attGroup->name[$lang_sub['id_lang']] == ''){
                                    $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                }
                                if(!isset($attGroup->public_name[$lang_sub['id_lang']])|| $attGroup->public_name[$lang_sub['id_lang']] == null || $attGroup->public_name[$lang_sub['id_lang']] == ''){
                                    $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);
                                }




                            if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                if (!isset($attGroup->name[$this->defaultLanguage]) || $attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                    $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                }
                                if (!isset($attGroup->public_name[$this->defaultLanguage]) || $attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                    $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                }
                            }

                        }

                    }
                }else{
                    if(isset($tablaStructure[$keyStruct]['titles'])){

                        foreach ($this->shop_languages as $lang_sub) {

                            if(isset($tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']])) {
                                $fieldName = $tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']];
                                $fieldNamePublic = $tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']];
                                $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);
                            }else{
                                if (!isset($attGroup->name[$lang_sub['id_lang']]) || $attGroup->name[$lang_sub['id_lang']] == null || $attGroup->name[$lang_sub['id_lang']] == '') {
                                    $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                }
                                if (!isset($attGroup->public_name[$lang_sub['id_lang']]) || $attGroup->public_name[$lang_sub['id_lang']] == null || $attGroup->public_name[$lang_sub['id_lang']] == '') {
                                    $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);
                                }
                            }

                            if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                if (!isset($attGroup->name[$this->defaultLanguage]) || $attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                    $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                }
                                if (!isset($attGroup->public_name[$this->defaultLanguage]) ||$attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                    $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                }
                            }

                        }

                    }else{

                        foreach ($this->shop_languages as $lang_sub) {
                            if(!isset($attGroup->name[$lang_sub['id_lang']])|| $attGroup->name[$lang_sub['id_lang']] == null || $attGroup->name[$lang_sub['id_lang']] == '') {
                                $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                            }
                            if (!isset($attGroup->public_name[$lang_sub['id_lang']]) || $attGroup->public_name[$lang_sub['id_lang']] == null || $attGroup->public_name[$lang_sub['id_lang']] == '') {
                                $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);
                            }
                            if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                if (!isset($attGroup->name[$this->defaultLanguage]) || $attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                    $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                }
                                if (!isset($attGroup->public_name[$this->defaultLanguage]) ||$attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                    $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                }
                            }

                        }
                    }
                }



                try{
                    $attGroup->save();
                } catch (Exception $e) {
                    $this->debbug('Error update AttributeGroup->'.print_r($e->getMessage(),1));
                }


                Shop::setContext(Shop::CONTEXT_SHOP,$contextShopID);


            }else{

                $this->debbug('Name of group attribute newest ->'.print_r($keyStruct,1));

                $schemaAttrGroup = " SELECT agl.`id_attribute_group`, name, public_name ".
                    " FROM ".$this->attribute_group_lang_table." AS agl ".
                    " GROUP BY agl.`id_attribute_group` ";
                $regsAttrGroup = Db::getInstance()->executeS($schemaAttrGroup);
                $format_exists = false;
                if (count($regsAttrGroup) > 0){
                    if (!isset($this->listAttrGroupCased[$fieldName])){
                        foreach ($regsAttrGroup as $regAttrGroup){

                          /*  $regAttrGroupName       = $this->removeAccents(strtolower(str_replace(' ', '_', $regAttrGroup['name'])));
                            $regAttrGroupPublicName = $this->removeAccents(strtolower(str_replace(' ', '_', $regAttrGroup['public_name'])));*/

                     /*     $regAttrGroupName       =  $regAttrGroup['name'];
                            $regAttrGroupPublicName =  $regAttrGroup['public_name'];


                            if ($regAttrGroupName == $fieldName || $regAttrGroupPublicName == $fieldName){
                     */


                            $replaced                  = str_replace(' ','_', $fieldName);
                            $replaced_strtolower       = strtolower( $replaced);
                            $replaced_w_accents        = $this->removeAccents( $replaced);
                            $replaced_ucfirst          = ucfirst($replaced_w_accents);
                            $replaced_original_ucfirst = ucfirst($fieldName);

                            $alternatives = array($replaced,$replaced_strtolower,$replaced_w_accents,$replaced_ucfirst,$replaced_original_ucfirst);

                            if (  in_array($regAttrGroup['name'],$alternatives,false) || in_array($regAttrGroup['public_name'],$alternatives,false) ){

                                $format_exists                        = $regAttrGroup['id_attribute_group'];
                                $this->listAttrGroupCased[$fieldName] = $format_exists;
                                break;
                            }
                        }
                    }
                }

                $lastFormatId = 0;

                if ($format_exists){
                    //Almacenamos id de formato para actualizar las tiendas
                    //array_push($formats_update_shops, $format_exists);
                    $formats_update_shops[]= $format_exists;

                    //Format found by name or public name, we insert the register.
                    Db::getInstance()->execute(
                        sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, comp_id, date_add) VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                            $format_exists,
                            $lastFormatId,
                            'product_format_field',
                            $comp_id
                        ));

                }else{
                    $this->debbug('Name of group attribute newest ->'.print_r($keyStruct,1));
                    $in_search = array('color','texture');
                    $is_color_attribute = false;

                    $contextShopID = Shop::getContextShopID();
                    Shop::setContext(Shop::CONTEXT_ALL);

                    $attGroup = new AttributeGroup();
                    $attGroup->name = array();
                    $attGroup->public_name = array();


                        if(isset($tablaStructure[$keyStruct]['language_code'])){

                            foreach ($this->shop_languages as $lang_sub) {

                                $attribute_search_index = $fieldName.'_'.$lang_sub['iso_code'];
                                if(isset($tablaStructure[$attribute_search_index]['language_code'])){
                                    if(isset($tablaStructure[$attribute_search_index]['title'])&& !empty($tablaStructure[$attribute_search_index]['title'])){
                                        $fieldName = $tablaStructure[$attribute_search_index]['title'];
                                        $fieldNamePublic = $tablaStructure[$attribute_search_index]['title'];
                                    }


                                    if(in_array(strtolower($fieldName),$in_search,false)){
                                        $is_color_attribute = true;
                                    }


                                    $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                    $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);

                                    if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                        if ($attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                            $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                        }
                                        if ($attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                            $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                        }
                                    }

                                }

                            }

                        }else{

                            if(isset($tablaStructure[$keyStruct]['titles'])){

                                foreach ($this->shop_languages as $lang_sub) {

                                    if(isset($tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']])) {
                                        $fieldName = $tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']];
                                        $fieldNamePublic = $tablaStructure[$keyStruct]['titles'][$lang_sub['iso_code']];
                                    }

                                    $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                    $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);

                                    if(in_array(strtolower($fieldName),$in_search,false)){
                                        $is_color_attribute = true;
                                    }

                                    if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                        if ($attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                            $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                        }
                                        if ($attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                            $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                        }
                                    }

                                }

                            }else{

                                foreach ($this->shop_languages as $lang_sub) {

                                    $attGroup->name[$lang_sub['id_lang']] = ucfirst($fieldName);
                                    $attGroup->public_name[$lang_sub['id_lang']] = ucfirst($fieldNamePublic);

                                    if(in_array(strtolower($fieldName),$in_search,false)){
                                        $is_color_attribute = true;
                                    }

                                    if ($lang_sub['id_lang'] != $this->defaultLanguage) {
                                        if ($attGroup->name[$this->defaultLanguage] == null || $attGroup->name[$this->defaultLanguage] == '') {
                                            $attGroup->name[$this->defaultLanguage] = ucfirst($fieldName);
                                        }
                                        if ($attGroup->public_name[$this->defaultLanguage] == null || $attGroup->public_name[$this->defaultLanguage] == '') {
                                            $attGroup->public_name[$this->defaultLanguage] = ucfirst($fieldNamePublic);
                                        }
                                    }

                                }
                            }

                        }

                        if($is_color_attribute){

                            $this->debbug('Creating a color attribute '.$fieldName,'syncdata');
                            $attGroup->is_color_group = true;
                            $attGroup->group_type = 'color';

                        }else{

                            $this->debbug('Creating a select attribute '.$fieldName,'syncdata');
                            $attGroup->is_color_group = false;
                            $attGroup->group_type = 'select';

                        }



                    $attGroup->position = AttributeGroupCore::getHigherPosition() + 1;

                    try{
                        $attGroup->add();
                        //Actualizamos las tiendas después de insertar
                        $formats_update_shops[]= $attGroup->id;

                        Db::getInstance()->execute(
                            sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, comp_id, date_add) VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                $attGroup->id,
                                $lastFormatId,
                                'product_format_field',
                                $comp_id
                            ));

                    } catch (Exception $e) {
                        $this->debbug('Error save AttributeGroup->'.print_r($e->getMessage(),1));
                    }


                    Shop::setContext(Shop::CONTEXT_SHOP,$contextShopID);

                    unset($attGroup);
                }


            }
            $this->clear_debug_content();
        }

        if ((count($formats_update_shops) > 0) && (count($conn_shops) > 0)){

            foreach ($formats_update_shops as $format_id) {

                //Actualizamos tiendas
                //Revisar en otros comp_id
                $schemaFormsExtra =	" SELECT id, shops_info FROM ".$this->slyr_table.
                    " WHERE ps_id = ".$format_id." AND comp_id = ".$comp_id." AND ps_type = 'product_format_field'";


                $formatInfo = Db::getInstance()->executeS($schemaFormsExtra);

                $schemaFormsShops =	" SELECT id_shop FROM ".$this->attribute_group_shop_table.
                    " WHERE id_attribute_group = ".$format_id;


                $format_shops = Db::getInstance()->executeS($schemaFormsShops);

                foreach ($conn_shops as $shop_id) {
                    $found = false;
                    //Primero buscamos en las existentes
                    if (count($format_shops) > 0){
                        foreach ($format_shops as $key => $format_shop) {
                            if ($shop_id == $format_shop['id_shop']){
                                $found = true;
                                //Eliminamos para obtener sobrantes
                                unset($format_shops[$key]);
                                break;
                            }
                        }
                    }

                    if (!$found){
                        Db::getInstance()->execute(
                            sprintf('INSERT INTO '.$this->attribute_group_shop_table.'(id_attribute_group, id_shop) VALUES("%s", "%s")',
                                $format_id,
                                $shop_id
                            ));
                    }

                }

                if (!empty($formatInfo)){

                    $sl_format_info_conns = json_decode($formatInfo[0]['shops_info'], 1);

                    $schemaFormsExtra =	" SELECT shops_info FROM ".$this->slyr_table.
                        " WHERE ps_id = ".$format_id." AND comp_id != ".$comp_id." AND ps_type = 'product_format_field'";


                    $infoOtherComps = Db::getInstance()->executeS($schemaFormsExtra);
                    $shopsOtherComps = array();
                    if (is_array($infoOtherComps) && count($infoOtherComps)){
                        foreach ($infoOtherComps as $shopsConn) {
                            $shops_info = json_decode($shopsConn['shops_info'], 1);
                            if (is_array($shops_info) && count($shops_info) > 0){
                                foreach ($shops_info as $conn_id => $shops) {
                                    if (!isset($shopsOtherComps[$conn_id])){ $shopsOtherComps[$conn_id] = array(); }
                                    foreach ($shops as $shop) {
                                        if (!in_array($shop, $shopsOtherComps[$conn_id],false)){
                                            //array_push($shopsOtherComps[$conn_id], $shop);
                                            $shopsOtherComps[$conn_id][]= $shop;
                                        }
                                    }
                                }
                            }
                        }
                    }

                }else{

                    $this->debbug('Registro slyr inválido al actualizar tiendas para el formato con ID: '.$format_id, 'error');

                }


                //Revisamos las sobrantes
                if (count($format_shops) > 0){
                    //Buscamos en conectores
                    foreach ($format_shops as $key => $format_shop) {
                        $found = false;
                        if (is_array($sl_format_info_conns) && count($sl_format_info_conns) > 0){
                            foreach ($sl_format_info_conns as $sl_format_info_conn => $sl_format_info_conn_shops) {
                                if ($sl_format_info_conn != $connector_id){
                                    if (in_array($format_shop['id_shop'],$sl_format_info_conn_shops,false)){
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if (count($shopsOtherComps) > 0){
                            foreach ($shopsOtherComps as $conn_id => $shopsOtherComp) {
                                if ($connector_id != $conn_id && in_array($format_shop['id_shop'], $shopsOtherComp,false)){
                                    $found = true;
                                }
                            }
                        }

                        if (!$found){
                            // Db::getInstance()->execute(
                            //     sprintf('DELETE FROM '.$this->attribute_group_shop_table.' WHERE id_attribute_group = "%s" AND id_shop = "%s"',
                            //     $format_id,
                            //     $format_shop['id_shop']
                            // ));
                        }

                    }
                }


                //Actualizamos el registro
                $sl_format_info_conns[$connector_id] = $conn_shops;
                $shopsInfo = json_encode($sl_format_info_conns);

                $schemaUpdateShops = " UPDATE ".$this->slyr_table." SET shops_info = '".$shopsInfo."' WHERE id = ".$formatInfo[0]['id'];

                $retorno = Db::getInstance()->execute($schemaUpdateShops);
                $this->clear_debug_content();
            }

        }

    }

    /**
     * Get attribute group id by a field name
     * @param string $fieldName name of the field
     * @param string $comp_id id of company
     * @return int|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getAttributeGroupId($fieldName, $comp_id){
        $format_exists = (int) Db::getInstance()->getValue(sprintf('SELECT gl.`id_attribute_group` FROM '.$this->attribute_group_lang_table.' AS gl JOIN '.$this->slyr_table.' AS sl ON ( gl.`id_attribute_group` = sl.`ps_id` and sl.`ps_type` = "product_format_field")  WHERE   comp_id = "%s" AND name LIKE "%s" GROUP BY gl.`id_attribute_group`', $comp_id, $fieldName));

        if (!$format_exists){
                $this->debbug('After search id atribute group from field name ->'.print_r($fieldName,1),'syncdata');
            if (isset($this->listAttrGroupCased[$fieldName])){
                return $this->listAttrGroupCased[$fieldName];
            }

            $schemaAttrGroup = " SELECT agl.`id_attribute_group`, name, public_name ".
                " FROM ".$this->attribute_group_lang_table." AS agl ".
                " GROUP BY agl.`id_attribute_group` ";
            $regsAttrGroup = Db::getInstance()->executeS($schemaAttrGroup);

            if (count($regsAttrGroup) > 0){
                $this->debbug('After select id atribute group '.$this->attribute_group_lang_table.' field name  ->'.print_r($fieldName,1),'syncdata');
                if (!isset($this->listAttrGroupCased[$fieldName])){
                    foreach ($regsAttrGroup as $regAttrGroup){

                      /*  $regAttrGroupName = $regAttrGroup['name'];
                        $regAttrGroupPublicName =  $regAttrGroup['public_name'];*/
                       $replaced                  = str_replace(' ','_', $fieldName);
                       $replaced_strtolower       = strtolower( $replaced);
                       $replaced_w_accents        = $this->removeAccents( $replaced);
                       $replaced_ucfirst          = ucfirst($replaced_w_accents);
                       $replaced_original_ucfirst = ucfirst($fieldName);

                        $alternatives = array($replaced,$replaced_strtolower,$replaced_w_accents,$replaced_ucfirst,$replaced_original_ucfirst);

                        if (  in_array($regAttrGroup['name'],$alternatives,false) || in_array($regAttrGroup['public_name'],$alternatives,false) ){

                            $format_exists = $regAttrGroup['id_attribute_group'];
                            $this->listAttrGroupCased[$fieldName] = $format_exists;
                            return $format_exists;

                        }
                    }
                }
            }

        }

        return $format_exists;
    }






    /**
     * sort tables with a structure
     * Organiza los indices cambiando el nombre de un campo con lenguaje por el nombre base.
     * @param array $tablas with tables to sort
     * @param array $tablaStructure with order to sort by
     * @return array $tablas with tables sorted
     */

    protected function organizarIndicesTablas($tablas, $tablaStructure){

            foreach ($tablaStructure as $keyStruct => $campoStruct) {

                if (isset($campoStruct['basename'])){
                    foreach ($tablas as $keyTab => $campoTabla) {
                        if (isset($campoTabla['data']) && !empty($campoTabla['data'])){
                            if (array_key_exists($keyStruct, $campoTabla['data'])){

                                if(isset($campoStruct['language_code']) && !empty($campoStruct['language_code'])) {  //es multiidioma

                                    $index_name = $campoStruct['basename'].'_'.$campoStruct['language_code'];
                                    $tablas[$keyTab]['data'][$index_name] = $tablas[$keyTab]['data'][$keyStruct];

                                }else{
                                    $this->debbug('No es multilanguage '.print_r($campoStruct['language_code'],1).' $keyStruct[data]-> '.print_r($keyStruct,1) );
                                    $tablas[$keyTab]['data'][$campoStruct['basename']] = $tablas[$keyTab]['data'][$keyStruct];

                                }
                            }
                        }
                    }
                }

            }

        return $tablas;
    }



    /**
     * remove accents from string
     *
     * @param string $str to remove accent from
     * @return string $str with removed accent
     */
    public function removeAccents($str) {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η');
        return str_replace($a, $b, $str);
    }



    /**
     * Function to get data schema from the connector images.
     * @param  $slconn array Sales Layer connector
     * @return array 				connector's schema
     */
    private function get_data_schema ($slconn) {

        $info = $slconn->get_response_table_information();

        $schema = array();

        foreach ($info as $table => $data) {

            if (isset($data['table_joins'])) {

                $schema[$table]['table_joins']=$data['table_joins'];
            }

            if (isset($data['fields'])) {

                foreach ($data['fields'] as $field => $struc) {

                    if (isset($struc['has_multilingual']) and $struc['has_multilingual']) {

                        if (!isset($schema[$table][$field])) {

                            $schema[$table]['fields'][$struc['basename']] = array(

                                'type'            =>$struc['type'],
                                'has_multilingual'=>1,
                                'multilingual_name' => $field
                            );

                            if ($struc['type']=='image') {

                                $schema[$table]['fields']['image_sizes']=$struc['image_sizes'];
                            }
                        }

                    } else {

                        $schema[$table]['fields'][$field]=$struc;
                    }
                }
            }
        }
        return $schema;
    }

    /**
     * Function to sort images by dimension.
     * @param array $img_a 		first image to sort
     * @param array $img_b 		second image to sort
     * @return array 			comparative of the images
     */
    function sort_by_dimension($img_a, $img_b){

        $area_a = $img_a['width'] * $img_a['height'];
        $area_b = $img_b['width'] * $img_b['height'];

        return strnatcmp($area_b, $area_a);

    }

    /**
     * Function to order an array of images.
     * @param array $array_img 		images to order
     * @return array 			array of ordered images
     */
    function order_array_img ($array_img) {

        $has_ORG = false;

        if (isset($array_img['ORG'])){

            if (count($array_img) == 1){

                return $array_img;

            }

            $has_ORG = true;
            unset($array_img['ORG']);

        }

        if (!empty($array_img) && count($array_img) > 1){

            uasort($array_img, array($this, 'sort_by_dimension'));

        }

        if ($has_ORG){

            $array_img = array('ORG' => array()) + $array_img;

        }

        return $array_img;

    }

    /**
     * Function to validate value and return yes/no.
     * @param  $value  mixed value to check
     * @return string 				boolean value as string
     */
    function sl_validate_boolean($value){

        if ( is_bool( $value ) ) {

            return $value;

        }

        if ( ( is_numeric( $value ) && $value === 0 )|| ( is_string( $value ) && in_array( strtolower( $value ), array('false', '0', 'no')      ,false) ) ) {

            return false;

        }

        if ( ( is_numeric( $value ) && $value === 1 )|| ( is_string( $value ) && in_array( strtolower( $value ), array('true', '1', 'yes', 'si'),false ) ) ) {

            return true;

        }

        return false;

    }

    /**
     * @param $name string			name to validate
     * @param $type string			type of field to validate
     * @return $string 			validated catalog name
     */
    public function sl_validate_catalog_name($name, $type = ''){

        $return_name = '';

        if ($name != '' && Validate::isCatalogName($name)){

            $return_name = $name;

        }else{

            $return_name = trim(preg_replace('/[^\p{L}0-9\-]/u','', $name));

            if (trim($return_name) == '' && $type != ''){

                $return_name = 'Untitled '.$type;

            }

        }

        return $return_name;

    }

    /**
     * @param $reference string reference to validate
     * @return $string 			validated reference
     */

    public function sl_validate_reference($reference){

        if (Validate::isReference($reference)){

            return $reference;

        }else{

            return trim(preg_replace('/[^\p{L}0-9\-]/u','', $reference));

        }

    }

    /**
     * get id from category
     * @param $defaultCategory string id from default category
     * @return int|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function checkDefaultCategory($defaultCategory){

        $categoryExist = (int) Db::getInstance()->getValue(sprintf('SELECT id_category FROM '.$this->category_table.' where id_category = "%s"',$defaultCategory));

        if ($categoryExist == 0){
            $PS_ROOT_CATEGORY = Configuration::get('PS_ROOT_CATEGORY');
            if ($PS_ROOT_CATEGORY){
                return $PS_ROOT_CATEGORY;
            }
            return 1;
        }else{
            return $defaultCategory;
        }
    }

    /**
     * reorganize categories by its parents
     *
     * @param array $categories data
     * @return array $new_categories reorganized data
     */
    protected function reorganizeCategories($categories){
        $microtime = microtime(1);
       // $this->debbug('Reorganize categories: '.print_r($categories,1));
        $new_categories = array();

        if (count($categories) > 0){

            $counter = 0;
            $first_level = $first_clean = true;
            $categories_loaded = array();

            do{

                $level_categories = $this->get_level_categories($categories, $categories_loaded, $first_level);

                if (!empty($level_categories)){

                    $counter = 0;
                    $first_level = false;

                    foreach ($categories as $keyCat => $category) {

                        if (isset($level_categories[$category['ID']])){

                            $new_categories[]= $category;
                            $categories_loaded[$category['ID']] = 0;
                            unset($categories[$keyCat]);

                        }

                    }

                }else{

                    $counter++;

                }

                if ($counter == 3){

                    if ($first_clean && !empty($categories)){

                        $categories_not_loaded_ids = array_flip(array_column($categories, 'ID'));

                        foreach ($categories as $keyCat => $category) {

                            if (!is_array($category['ID_PARENT'])){

                                $category_parent_ids = array($category['ID_PARENT']);

                            }else{

                                $category_parent_ids = array($category['ID_PARENT']);

                            }

                            $has_any_parent = false;

                            foreach ($category_parent_ids as $category_parent_id) {

                                if (isset($categories_not_loaded_ids[$category_parent_id])){

                                    $has_any_parent = true;
                                    break;

                                }

                            }

                            if (!$has_any_parent){

                                $category['ID_PARENT'] = 0;

                                //array_push($new_categories, $category);
                                $new_categories[]=  $category;
                                $categories_loaded[$category['ID']] = 0;
                                unset($categories[$keyCat]);

                                $counter = 0;
                                $first_level = $first_clean = false;

                            }

                        }

                    }else{

                        break;

                    }

                }

            }while (count($categories) > 0);

        }
        $this->debbug('END Reorganize categories: '.(microtime(1)-$microtime));
        return $new_categories;

    }
    /**
     * reorganize product_formats checking possible combinations
     *
     * @param array $products_formats values from products formats
     * @return array $products_formats values from products formats reorganized
     */
    protected function reorganizeProductFormats($products_formats){
        $microtime = microtime(1);
        if (!empty($products_formats)){
            foreach ($products_formats as $keyPF => $product_format){
                $this->format_value_arrays = array();
                $this->format_value_fields = array();
                $this->final_format_array = array();

                $product_format_data = $product_format['data'];
                unset($product_format['data']);

                $hasArrays = false;
                foreach ($product_format_data as $field_name => $field_value) {
                    if (is_array($field_value) && !empty($field_value) && $field_name != 'frmt_image'){
                        //array_push($this->format_value_fields, $field_name);
                        $this->format_value_fields[]= $field_name;
                        $this->format_value_arrays[$field_name] = $field_value;
                        unset($product_format_data[$field_name]);
                        $hasArrays = true;
                    }
                }

                if (!$hasArrays){ continue; }
                unset($products_formats[$keyPF]);

                $this->combinations($this->format_value_arrays , 0);

                foreach ($this->final_format_array as $format_array) {
                    $new_product_format = $product_format;
                    $new_product_format['data'] = array_merge($product_format_data, $format_array);
                    //array_push($products_formats, $new_product_format);
                    $products_formats[]= $new_product_format;
                }
            }
        }
        $this->debbug('END reorganizing Variants '.(microtime(1) - $microtime));
        return $products_formats;

    }

    public function priceForamat($campo){
        $campo = str_replace(',', '.', $campo);
        return  round((float) $campo, 6);
    }

    public function discauntFormat($campo){
        $campo = str_replace(array('%',','), array('','.'), $campo);
        return  round((float) $campo, 6);
    }

    /**
     * get categories by its root level
     * @param $categories
     * @param $categories_loaded
     * @param bool $first
     * @return array categories that own to that level
     */
    protected function get_level_categories($categories, $categories_loaded, $first = false){

        $level_categories = array();

        if ($first){

            foreach ($categories as $category) {

                if (!is_array($category['ID_PARENT']) && $category['ID_PARENT'] == 0){

                    $level_categories[$category['ID']] = 0;

                }

            }

        }else{

            foreach ($categories as $category) {

                if (!is_array($category['ID_PARENT'])){
                    $category_parent_ids = array($category['ID_PARENT']);
                }else{
                    $category_parent_ids = array($category['ID_PARENT']);
                }

                $parents_loaded = true;
                foreach ($category_parent_ids as $category_parent_id) {

                    if (!isset($categories_loaded[$category_parent_id])){

                        $parents_loaded = false;
                        break;
                    }
                }

                if ($parents_loaded){

                    $level_categories[$category['ID']] = 0;

                }

            }

        }

        return $level_categories;

    }






}

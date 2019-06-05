<?php

class sl_variants extends SalesLayerPimUpdate{



    public function __construct ()
    {
        parent::__construct();
    }

    public function loadVariantImageSchema($product_formats_schema){

       if(empty($this->format_images_sizes)){
           if (!empty($product_formats_schema)){

               if (isset($product_formats_schema['fields']['frmt_image']) && $product_formats_schema['fields']['frmt_image']['type'] == 'image'){
                   $this->product_format_has_frmt_image = true;
               }
               if (!empty($product_formats_schema['fields']['frmt_image']['image_sizes'])) {
                   $product_format_field_images_sizes = $product_formats_schema['fields']['frmt_image']['image_sizes'];
                   $ordered_image_sizes = $this->order_array_img($product_format_field_images_sizes);
                   foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                       $this->format_images_sizes[] = $img_size;
                   }
                   unset($category_field_images_sizes,$ordered_image_sizes);
               } else if (!empty($product_formats_schema['fields']['image_sizes'])) {

                   $product_format_field_images_sizes = $product_formats_schema['fields']['image_sizes'];
                   $ordered_image_sizes = $this->order_array_img($product_format_field_images_sizes);
                   foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                       $this->format_images_sizes[] = $img_size;
                   }
                   unset($category_field_images_sizes,$ordered_image_sizes);
               } else {
                   $this->format_images_sizes[] = array('ORG', 'IMD', 'THM', 'TH');
               }
               $this->debbug(' load: Format_images_sizes '.print_r($this->format_images_sizes,1),'syncdata');
           }
       }

        $this->debbug(' load: Variant_image_schema' ,'syncdata');
    }



    public function syncOneVariant($product_format,$schema,$connector_id,$comp_id,$conn_shops,$currentLanguage,$avoid_stock_update){


        $syncCat = true;


        $this->debbug('The information that comes to synchrionize variant  ->'.print_r($product_format,1).'  $shops ->'.print_r($conn_shops,1).'   comp_id ->'.print_r($comp_id,1),'syncdata');

        if(empty($connector_id)|| empty($comp_id)|| empty($currentLanguage)|| empty($conn_shops)  ){

            $this->debbug('## Error. alguno de los datos no esta rellenado correctamente','syncdata');
            return 'item_not_updated';
        }

        if(isset($product_format['data']['reference'])&& !empty($product_format['data']['reference'])){
            $Alt_atribute_for_image = $product_format['data']['reference'];
        }else{
            $Alt_atribute_for_image = 'variant_'.$product_format['ID'];
        }

        $occurence_found = false;
        if(isset($product_format['data']['reference']) && !empty($product_format['data']['reference'])){
            $occurence_found = true;
            $occurence = ' variant reference :"'.$product_format['data']['reference'].'" ' ;
        }else{
            $occurence = ' variant ID :'.$product_format['ID'].' of Product ID:'.$product_format['ID_products'] ;
        }



        $product_format_id = $product_format['ID'];
        $slyr_product_id = $product_format['ID_products'];

        $product_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE comp_id = "%s" AND slyr_id = "%s" AND ps_type = "product"', $comp_id, $slyr_product_id));

        if ($product_id == null || $product_id == ''){
            $this->debbug('## Error. '.$occurence.' It has not been possible to find id Product of the variant, it is necessary to make the parent product of this variant visible and visible again so that its synchronization is possible. Variant ID:'.$product_format_id.',  ID product: '.$slyr_product_id.',  company ID: '.$comp_id,'syncdata');

            return 'item_not_updated';
        }else{

            $product_count_pack = Db::getInstance()->getValue(sprintf('SELECT COUNT(*) AS count FROM '.$this->pack_table.' WHERE id_product_pack = "%s"', $product_id));

            $product_type_data = Db::getInstance()->executeS(sprintf('SELECT is_virtual,cache_is_pack FROM '.$this->product_table.' WHERE id_product = "%s"', $product_id));
            $product_type_data = $product_type_data[0];

            if ($product_count_pack > 0 || $product_type_data['cache_is_pack'] == 1 || $product_type_data['is_virtual'] == 1){

                $this->debbug('## Error.'. $occurence.'.  Product is a type pack or virtual and can not have variants ','syncdata');
                //continue;
                return 'item_not_updated';

            }

        }

        $fieldsBase = array_fill_keys($this->product_format_base_fields, '');

        if (isset($product_format['data'])){
            foreach ($fieldsBase as $key => $value) {
                if ($key == 'format_supplier' || $key == 'format_supplier_reference'){

                    $array_supplier = preg_grep('/'.$key.'_\+?\d+$/', array_keys($product_format['data']));

                    if (!empty($array_supplier)){
                        foreach ($array_supplier as $supplier_field) {

                            if ($product_format['data'][$supplier_field] != '' && $product_format['data'][$supplier_field]!= null){
                                $fieldsBase[$supplier_field] = $product_format['data'][$supplier_field];
                                unset($product_format['data'][$supplier_field]);
                            }
                        }
                    }
                    unset($fieldsBase[$key]);
                }else{

                    if (array_key_exists($key, $product_format['data']) && $product_format['data'][$key] != '' && $product_format['data'][$key] != null){
                        $fieldsBase[$key] = $product_format['data'][$key];
                        unset($product_format['data'][$key]);
                    }else{
                        unset($fieldsBase[$key]);
                    }

                }
            }


            $attributes = array();
            $processed_Keys = array();
            do {

                $attributeGroupName = '';
                $attributeValue = '';

                foreach($product_format['data'] as $first_index_name_elm => $first_index_value){

                    $attributeGroupName = $first_index_name_elm;
                    $attributeValue = $first_index_value;
                    if(!empty($attributeValue)){
                        break;
                    }else{
                        unset($product_format['data'][$first_index_name_elm]);
                    }
                }

                if(empty($attributeValue)){
                    break;
                }

              // $this->debbug('Elemento encontrado para procesar como attributo '.$attributeGroupName.' con value ->'.print_r($attributeValue,1) ,'syncdata');
                //($product_format['data'] as $attributeGroupName => $attributeValue) {
                $currentLanguage_for_set   = $currentLanguage;
                $mulilanguage = array();

                if($attributeGroupName != '' && !empty($attributeValue)  && !in_array( $attributeGroupName , $processed_Keys,false) ){

                    if(isset( $schema[$attributeGroupName]['language_code'] ) && !empty( $schema[$attributeGroupName]['language_code'] ) ){
                        /**

                         Attribute is mutli-Language

                         */
                        try{
                            $currentLanguage_for_set   = Language::getIdByIso($schema[$attributeGroupName]['language_code']);
                            foreach($this->shop_languages as $leng){

                                $attribute_index =  $schema[$attributeGroupName]['basename'].'_'.$leng['iso_code'];
                                if(isset($product_format['data'][$attribute_index]) ){
                                    $this->debbug('Is the same attribute but in other languages '.print_r($attribute_index,1).' Etiqueta language ->'.print_r($leng['iso_code'],1),'syncdata');
                                    $mulilanguage[$leng['id_lang']] = $product_format['data'][$attribute_index];
                                    $processed_Keys[] = $attribute_index;
                                    unset($product_format['data'][$attribute_index]);
                                }

                            }

                             /**
                             * Delete the same attribute but in language that is not installed in prestashop but comes from sales layer
                             */
                             foreach ($schema as $nameOfAttribute => $valuesOfAttribute ){

                                 if( isset($schema[$attributeGroupName]['basename'],$valuesOfAttribute['basename']) && $schema[$attributeGroupName]['basename'] == $valuesOfAttribute['basename']){
                                     unset($product_format['data'][$nameOfAttribute]);
                                 }

                             }


                        } catch (Exception $e) {
                            $this->debbug('## Error. '.$occurence.' Language::getIdByIso'. print_r($e->getMessage(), 1),'syncdata');
                        }

                        unset($product_format['data'][$attributeGroupName]);
                        $attributeGroupName = $schema[$attributeGroupName]['basename'];

                    }

                    try{
                             $attribute_group_id = $this->getAttributeGroupId($attributeGroupName, $comp_id);
                    } catch (Exception $e) {
                        unset($product_format['data'][$attributeGroupName]);
                        $this->debbug('## Error. '.$occurence.' getAttributeGroupId'. print_r($e->getMessage(), 1),'syncdata');
                    }
                    if ($attribute_group_id == null || $attribute_group_id == ''){
                        $this->debbug('## Error. '.$occurence.' When you get the group ID of attribute '.$attributeGroupName.' para la empresa con ID: '.$comp_id,'syncdata');
                        unset($product_format['data'][$attributeGroupName]);
                        continue;
                    }

                    if ( is_array($attributeValue) ){
                        /**

                             Value is a array value

                         */
                        $this->debbug('attribute is array');
                        foreach ($attributeValue as $attributeVal) {
                            try{
                                $attribute_id = $this->synchronizeAttribute($attribute_group_id, $attributeVal, $product_format_id, $connector_id,$comp_id,$conn_shops, $currentLanguage_for_set,$mulilanguage);

                                if ($attribute_id == null || $attribute_id == ''){
                                    $this->debbug('## Error. '.$occurence.' When synchronizing the attribute '.$attributeGroupName.' para el formato con ID: '.$product_format_id,'syncdata');
                                    continue;
                                }else{

                                    if(!in_array( $attribute_id,$attributes,false)) {
                                        $attributes[] = $attribute_id;
                                    }

                                }
                            } catch (Exception $e) {
                                unset($product_format['data'][$attributeGroupName]);
                                $this->debbug('## Error. '.$occurence.' synchronizeAttribute'. print_r($e->getMessage(), 1),'syncdata');
                            }
                        }
                    }elseif ( !is_array($attributeValue) && $attributeValue != ''){
                        /**

                        Value is string

                         */
                        $this->debbug('attribute is string','syncdata');
                        try{

                            $attribute_id = $this->synchronizeAttribute($attribute_group_id,(string) $attributeValue, $product_format_id, $connector_id,$comp_id,$conn_shops, $currentLanguage_for_set,$mulilanguage);

                            if ($attribute_id == null || $attribute_id == ''){
                                unset($product_format['data'][$attributeGroupName]);
                                $this->debbug('## Error. '.$occurence.' When synchronizing the attribute '.$attributeGroupName.' para el formato con ID: '.$product_format_id,'syncdata');
                                continue;
                            }else{

                                if(!in_array($attribute_id,$attributes,false)){
                                    $this->debbug('Attribute id guardado con el '.$attributeGroupName.' valor -> '.print_r($attribute_id,1),'syncdata');
                                    $attributes[]= $attribute_id;
                                }

                            }
                        } catch (Exception $e) {
                            unset($product_format['data'][$attributeGroupName]);
                            $this->debbug('## Error. '.$occurence.' synchronizeAttribute as string: '. print_r($e->getMessage(), 1).' line->'.print_r($e->getLine(),1),'syncdata');
                        }
                    }
                }
                unset($product_format['data'][$attributeGroupName]);

            }while( count($product_format['data']) > 0 );



            if (empty($attributes)){
                $this->debbug('## Error. '.$occurence.' There are no configurable attributes. Please continue to the cloud of Sales Layer >> Channels >> Edit Prestashop Connector >> Output data >> Variants >> Include new field  and insert field type (color,size,..) ','syncdata');
                //continue;
                return 'item_updated';
            }




            $schemaProdAttrs = "SELECT `pa`.`id_product_attribute` ".
                " FROM ".$this->product_attribute_table." pa ".
                " WHERE id_product = ".$product_id.
                " GROUP BY `pa`.`id_product_attribute` ".
                " ORDER BY `pa`.`id_product_attribute` ";
            $productAttributes = Db::getInstance()->executeS($schemaProdAttrs);

            $sl_product_format_id = '';

            if (count($productAttributes) > 0 ){

                foreach ($productAttributes as $key => $value) {

                    foreach ($this->shop_languages as $lang){

                        $this->debbug('Product attributes en language search in BD iso_code ->'.$lang['iso_code'].'  id_product_attribute key->' . print_r($key, 1) . '  value->' . print_r($value, 1),'syncdata' );

                        // $existing_sl_combination_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE ps_id = "%s" AND comp_id = "%s" AND ps_type = "combination"', $value['id_product_attribute'], $comp_id));

                        // if ($existing_sl_combination_id == 0){

                        // 	$delete_combination = new CombinationCore($value['id_product_attribute']);
                        // 	$delete_combination->delete();
                        // 	continue;

                        // }

                        $schemaAttrs = "SELECT `a`.`id_attribute` " .
                            " FROM " . $this->product_attribute_table . " pa " .
                            " LEFT JOIN " . $this->product_attribute_combination_table . " pac ON pac.id_product_attribute = pa.id_product_attribute " .
                            " LEFT JOIN " . $this->attribute_table . " a ON a.id_attribute = pac.id_attribute " .
                            " LEFT JOIN " . $this->attribute_group_table . " ag ON ag.id_attribute_group = a.id_attribute_group " .
                            " LEFT JOIN " . $this->attribute_lang_table . " al ON (a.id_attribute = al.id_attribute AND al.id_lang = '" . $lang['id_lang'] . "') " .
                            " LEFT JOIN " . $this->attribute_group_lang_table . " agl ON (ag.id_attribute_group = agl.id_attribute_group AND agl.id_lang = '" . $lang['id_lang'] . "') " .
                            " WHERE `pa`.`id_product` = " . $product_id .
                            " and `pa`.`id_product_attribute` = " . $value['id_product_attribute'] .
                            " GROUP BY `pa`.`id_product_attribute`, `ag`.`id_attribute_group` " .
                            " ORDER BY `pa`.`id_product_attribute`; ";


                        $attributesValues = Db::getInstance()->executeS($schemaAttrs);

                        if (count($attributesValues) > 0) {

                            $attributes_val = array();
                            foreach ($attributesValues as $attrVal) {

                                //array_push($attributes_val, $attrVal['id_attribute']);
                                if (isset($attrVal['id_attribute']) && !empty($attrVal['id_attribute'])) {
                                    $attributes_val[] = $attrVal['id_attribute'];
                                }


                            }
                           // $this->debbug('Diferenciar estos 2 arrays de los guardados  en la sync  ->' . print_r($attributes, 1) . '    el segundo que tiene el producto  ->' . print_r($attributes_val, 1).'  array de diff que debe esttar vacio '.print_r(array_diff($attributes, $attributes_val),1));
                            if (empty(array_diff($attributes, $attributes_val))) {

                              //  $this->debbug('Id_product es igual  variante aceptado para su guardado recibiendo un break de segundo nivel  para salir del buckle ->' . print_r($value['id_product_attribute'], 1),'syncdata' );
                                $sl_product_format_id = $value['id_product_attribute'];
                                break 2;
                            }

                        }

                    }// lang
                }

            }
            $this->first_sync_shop = true;
            $is_new_variant = false;
            $processedShop = 0;
            foreach ($conn_shops as $shop_id){

                Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);


            $check_sl_product_format_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"', $product_format_id, $comp_id));
            $combination_changed = false;
            if ($check_sl_product_format_id != 0){

                $existing_combination = new CombinationCore($check_sl_product_format_id, null, $shop_id);
                try{
                     $combination_option_values = $existing_combination->getWsProductOptionValues();
                } catch (Exception $e) {
                    $this->debbug('## Error. getWsProductOptionValues'. print_r($e->getMessage(), 1),'syncdata');
                }
                unset($existing_combination);
                $combination_attributes_values = array();


                if (!empty($combination_option_values)){

                    foreach ($combination_option_values as $combination_option_value) {
                        //array_push($combination_attributes_values, $combination_option_value['id']);
                        $combination_attributes_values[]= $combination_option_value['id'];
                    }
                    $this->debbug('Check synchronize data attributes attr->'.print_r($attributes,1).' diff ->'.print_r($combination_attributes_values,1),'syncdata');
                    if (array_diff($attributes, $combination_attributes_values)){
                        $this->debbug('Sending is needed combination generate','syncdata');
                        $combination_changed = true;
                        // $existing_combination->delete();

                        // if ($sl_product_format_id == ''){

                        // 	Db::getInstance()->execute(
                        // 	    sprintf('DELETE FROM '.$this->slyr_table.' WHERE ps_id = "%s" AND slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"',
                        // 	    $check_sl_product_format_id,
                        // 	    $product_format_id,
                        // 	    $comp_id
                        // 	));

                        // }
                    }else{
                        $this->debbug('The combinations have not changed.','syncdata');
                    }

                }


                if ($check_sl_product_format_id != $sl_product_format_id && $sl_product_format_id != ''){
                    try {
                        Db::getInstance()->execute(
                            sprintf('UPDATE '.$this->slyr_table.' SET ps_id = "%s" WHERE ps_id = "%s" AND slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"',
                                $sl_product_format_id,
                                $check_sl_product_format_id,
                                $product_format_id,
                                $comp_id
                            ));
                    }catch(Exception $e){
                        $this->debbug('## Error. '.$occurence.' In update combination table','syncdata');
                    }



                }



            }else{

                if ($sl_product_format_id != ''){

                    $old_sl_product_format_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE ps_id = "%s" AND slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"', $sl_product_format_id, $product_format_id, $comp_id));

                    if ($old_sl_product_format_id != 0){

                        Db::getInstance()->execute(
                            sprintf('UPDATE '.$this->slyr_table.' SET slyr_id = "%s" WHERE ps_id = "%s" AND slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"',
                                $product_format_id,
                                $sl_product_format_id,
                                $old_sl_product_format_id,
                                $comp_id
                            ));

                    }else{

                        Db::getInstance()->execute(
                            sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, comp_id, date_add) VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                $sl_product_format_id,
                                $product_format_id,
                                'combination',
                                $comp_id
                            ));

                    }

                }

            }

            $id_product_attribute = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"', $product_format_id, $comp_id));

            $stock = 0;

            $combination_generated = false;
            if ($id_product_attribute){
                $comb = new CombinationCore($id_product_attribute, null, $shop_id);

                if($comb->id_product != $product_id){
                    /**
                        If parent product is diferent remove old images from parent product
                     */
                    try{
                        $this->syncVariantImageToProduct(array(),$this->defaultLanguage,$comb->id_product,'',$id_product_attribute);
                    }catch(Exception $e){
                        $this->debbug('## Error. '.$occurence.'. In parent product is diferent remove old images from parent product and set to newest  error->'.print_r($e->getMessage(),1),'syncdata');
                    }
                }

                $comb->id_product = $product_id;
            }else{
                $combination_generated = true;
                if ($sl_product_format_id){
                    $comb = new CombinationCore($sl_product_format_id, null, $shop_id);
                }else{
                    $comb = new CombinationCore(null, null, $shop_id);
                    $is_new_variant = true;
                }

                $comb->id_product = $product_id;
                try {
                    if ($sl_product_format_id){
                        $comb->save();
                    }else{
                        $comb->add();
                    }
                } catch (Exception $e) {
                    $syncCat = false;
                    $this->debbug('## Error.  In save Variant->'.print_r($e->getMessage(),1),'syncdata');
                }
            }

                if (!empty($attributes) && (!$sl_product_format_id || $combination_changed) && $processedShop == 0 ){
                    /**
                      Overwrite combinations info if is changed
                     */
                    $this->debbug('Synchronize attributes before set atributes array ->'.print_r($attributes,1),'syncdata');
                    try{
                         $comb->setAttributes($attributes);
                    } catch (Exception $e) {
                        $syncCat = false;
                        $this->debbug('## Error. '.$occurence.' In setAttributes->'.print_r($e->getMessage(),1),'syncdata');
                    }
                }

            $format_img_ids = array();
            $update_mostrar = $mostrar = false;

            if (!empty($fieldsBase)){
                try{
                    $current_supplier_collection = ProductSupplier::getSupplierCollection($product_id, false);
                } catch (Exception $e) {
                    $syncCat = false;
                    $this->debbug('## Error. '.$occurence.' In ProductSupplier::getSupplierCollection->'.print_r($e->getMessage(),1),'syncdata');
                }
                $processed_suppliers = array();
                $supplier_processed = false;

                foreach ($fieldsBase as $key => $value) {

                    switch ($key) {
                        case 'quantity':
                            if ($value != 0){

                                $stock = $value;

                            }
                            break;

                        case (preg_match('/format_supplier_\+?\d+$/', $key) ? true : false):

                            $number_field = str_replace('format_supplier_', '', $key);

                            if ($number_field){
                                $format_suplier_reference_index = 'format_supplier_reference_'.$number_field;
                                if (isset($fieldsBase[$format_suplier_reference_index]) && $fieldsBase[$format_suplier_reference_index] != ''){

                                    $supplier_reference = $fieldsBase['format_supplier_reference_'.$number_field];

                                    $supplier = new Supplier();

                                    $id_supplier = 0;

                                    if (is_numeric($value)){

                                        $supplier_exists = $supplier->supplierExists($value);
                                        if ($supplier_exists){
                                            $id_supplier = $value;
                                        }

                                    }else{

                                        $supplier_exists = $supplier->getIdByName(strtolower($value));
                                        if ($supplier_exists){
                                            $id_supplier = $supplier_exists;

                                        }else{
                                            $supplier_exists = $supplier->getIdByName(strtolower(str_replace('_', ' ', $value)));
                                            if ($supplier_exists){
                                                $id_supplier = $supplier_exists;
                                            }else{
                                                $supplier_exists = $supplier->getIdByName(strtolower(str_replace(' ', '_', $value)));
                                                if ($supplier_exists){
                                                    $id_supplier = $supplier_exists;
                                                }
                                            }
                                        }
                                    }

                                    if ($id_supplier != 0){

                                        $productObject = new Product($product_id, null, null, $shop_id);

                                        try{
                                            $productObject->addSupplierReference($id_supplier, $comb->id, $supplier_reference);
                                        } catch (Exception $e) {
                                            $syncCat = false;
                                            $this->debbug('## Error. '.$occurence.'  In addSupplierReference->'.print_r($e->getMessage(),1),'syncdata');
                                        }
                                        $processed_suppliers[$id_supplier] = 0;
                                        $supplier_processed = true;


                                    }

                                }

                            }

                            break;

                        case (preg_match('/supplier_reference_\+?\d+$/', $key) ? true : false):

                            break;

                        case 'wholesale_price':

                            $price = (float) abs($value);

                            if (Validate::isPrice($price)){

                                $comb->wholesale_price = $price;

                            }

                            break;
                        case 'price_tax_excl':

                            $comb->price = (float)$value;

                            break;
                        case 'price_tax_incl':

                            $productObjectTaxes = new Product($product_id, null, null, $shop_id);
                            //$price = round((floatval(str_replace(',', '.', $value)) / (1 + ($productObjectTaxes->getTaxesRate() / 100))), 6);
                            //$price = round((float) str_replace(',', '.', $value) / (1 + ($productObjectTaxes->getTaxesRate() / 100)), 6);
                            $price = $this->priceForamat( str_replace(',', '.', $value) / (1 + ($productObjectTaxes->getTaxesRate() / 100)));

                            $comb->price = $price;

                            break;
                        case 'price':

                            //continue;
                            continue 2;

                            break;
                        case 'default_on':

                            if ($this->sl_validate_boolean($value)){

                                $productObject = new Product($product_id, null, null, $shop_id);
                                $productObject->deleteDefaultAttributes();
                                $comb->default_on = 1;

                            }

                            break;
                        case 'mostrar':

                            $mostrar = $this->sl_validate_boolean($value);

                            $check_column = Db::getInstance()->executeS(sprintf('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = "'._DB_NAME_.'" AND TABLE_NAME = "'.$this->product_attribute_table.'" AND COLUMN_NAME = "mostrar"'));

                            if (!empty($check_column)){

                                $update_mostrar = true;

                            }

                            break;
                        case 'frmt_image':

                           // $this->debbug('Processing frmt_image of shop '.$shop_id.' content -> '.print_r($value,1),'syncdata');

                            if ($this->product_format_has_frmt_image && $this->first_sync_shop){ // entry only in first shop



                                if (!empty($value)){
                                   if($this->debugmode > 2){ $this->debbug('Entry to process images','syncdata');}
                                    try{
                                         $format_img_ids =  $this->syncVariantImageToProduct($value,$currentLanguage,$product_id,$Alt_atribute_for_image,$comb->id);
                                    } catch (Exception $e) {
                                        $syncCat = false;
                                        $this->debbug('## Error. '.$occurence.' In syncVariantImageToProduct->'.print_r($e->getMessage(),1),'syncdata');
                                    }

                                }
                                $this->first_sync_shop = false;
                            }

                            break;
                      /*  case 'format_images':
                            $this->debbug('processing format_images '.print_r($value,1),'syncdata');
                            if ($this->product_format_has_frmt_image && !empty($fieldsBase['frmt_image'])){
                                $this->debbug('sending continue 2  / continue 1 ?'.print_r($value,1),'syncdata');
                                //continue;
                               // continue 2;
                                break;
                            }
                            $format_img_ids = array();
                            $images_references = array();
                            $values_json = json_decode($value,1);

                            if ($values_json){

                                if (!empty($values_json)){

                                    foreach ($values_json as $value_json) {

                                        $images_references[] = trim($value_json);

                                    }

                                }

                            }else if (is_string($value) && $value != ''){

                                $values_string = explode(',', $value);
                                if (!empty($values_string)){

                                    foreach ($values_string as $value_string) {

                                        $images_references[] = trim($value_string);

                                    }

                                }

                            }else if (is_array($value) && !empty($value)){

                                $images_references = $value;

                            }

                            if (!empty($images_references)){

                                $images_references_string = '';
                                foreach ($images_references as $image_reference) {
                                    if ($image_reference != end($images_references)){

                                        $images_references_string .= "'".$image_reference."',";

                                    }else{

                                        $images_references_string .= "'".$image_reference."'";
                                    }

                                }

                                $check_img_ids = Db::getInstance()->executeS(sprintf('SELECT si.id_image FROM '.$this->slyr_images_table.' AS si RIGHT JOIN '.$this->image_table.' AS i on (i.id_image = si.id_image) WHERE si.image_reference in ('.$images_references_string.') ORDER BY field(si.image_reference, '.$images_references_string.');'));

                                if (!empty($check_img_ids)){

                                    foreach ($check_img_ids as $check_img_id) {

                                        $format_img_ids[] = $check_img_id['id_image'];

                                    }

                                }

                            }

                            break;*/
                        default:

                            $comb->key = $value;
                            break;

                    }

                }

                if ($supplier_processed){

                    foreach ($current_supplier_collection as $current_supplier_item){

                        if ( $current_supplier_item->id_product_attribute == $comb->id && !isset($processed_suppliers[$current_supplier_item->id_supplier])  ){

                            $current_supplier_item->delete();

                        }
                    }

                }

            }

            if (isset($fieldsBase['minimal_quantity']) && !empty($fieldsBase['minimal_quantity'])){
                $comb->minimal_quantity = (int) $fieldsBase['minimal_quantity'];
            }else{
                $comb->minimal_quantity = 1;
            }


            try{

                if($comb->low_stock_alert == null){
                    $comb->low_stock_alert = false;
                }

                $comb->save();
            } catch (Exception $e) {
                $syncCat = false;
                $this->debbug('## Error. '.$occurence.' Update Variant->'.print_r($e->getMessage(),1),'syncdata');
            }


            if ($update_mostrar){

                Db::getInstance()->execute(
                    sprintf('UPDATE '.$this->product_attribute_table.' SET mostrar = "%s" WHERE id_product_attribute = "%s"',
                        ($mostrar) ? 1 : 0,
                        $comb->id
                    ));

            }

            if ( $avoid_stock_update || $is_new_variant ){
                /**
                    Set Stock of Variant
                 */

                StockAvailableCore::setQuantity($product_id, $comb->id, $stock,$shop_id);
            }



            if (!empty($format_img_ids)){

                /**
                     UPDATE IMAGES IDS
                 */
                $this->debbug('Asignacion de imagenes a variante con ps id variante ->'.$comb->id.' llamando a set images ids de images-> '.print_r($format_img_ids,1),'syncdata' );
                $all_shops_image = Shop::getShops(true, null, true);

                $comb->setImages($format_img_ids);
                $comb->associateTo($all_shops_image);

//                $check_column = Db::getInstance()->executeS(sprintf('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = "'._DB_NAME_.'" AND TABLE_NAME = "'.$this->product_attribute_image_table.'" AND COLUMN_NAME = "img_portada"'));
//
//                if (!empty($check_column)){
//
//                    $portada_asignada = '';
//                    foreach($format_img_ids as $format_img_id) {
//                        $this->debbug('Asignacion de imagenes como portada de formato  '.print_r($format_img_id,1),'syncdata' );
//                      /*  $portada_asignada = Db::getInstance()->execute(
//                            sprintf('UPDATE '.$this->product_attribute_image_table.' SET img_portada = "1" WHERE id_product_attribute = "%s" AND id_image = "%s"',
//                                $comb->id,
//                                $format_img_id
//                            ));*/
//
//                        if ($portada_asignada){ break; }
//
//                    }
//
//                }


            }else{
                $this->debbug('This Variant dont have any images  ->'.$comb->id.' status of ids images-> '.print_r($format_img_ids,1),'syncdata' );
            }

            if (!$combination_generated){

                $this->debbug('Working update in slyr table of combination  '.print_r(array($id_product_attribute,$product_format_id,$comp_id),1),'syncdata' );
                Db::getInstance()->execute(
                    sprintf('UPDATE '.$this->slyr_table.' SET date_upd = CURRENT_TIMESTAMP() WHERE ps_id = "%s" AND slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"',
                        $id_product_attribute,
                        $product_format_id,
                        $comp_id
                    ));

            }else{

                $this->debbug('Working in insert in slyr table of combination  '.print_r(array($comb->id,$product_format_id,'combination',$comp_id),1),'syncdata' );
                Db::getInstance()->execute(
                    sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, comp_id, date_add) VALUES ("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                        $comb->id,
                        $product_format_id,
                        'combination',
                        $comp_id
                    ));

            }
                $processedShop++;
            }

        }
        $this->clear_debug_content();
        unset($comb);
        if ($syncCat){

            return 'item_updated';

        }else{

            return 'item_not_updated';
        }



    }

    private function syncVariantImageToProduct($images,$id_lang,$product_id,$product_name,$variant_id){


        if(isset($product_name) && !empty($product_name)){
            $occurence = ' Variant name :'.reset($product_name).'' ;
        }else{
            $occurence = ' ID :'.$product_id ;
        }



            $image_ids = array();
            $this->debbug(' Entry to a synchronize images from formato a paent product image array for this $images->'.print_r($images,1).'  $id_lang->'.print_r($id_lang,1).' $product_name-> '.print_r($product_name,1).' $variant_id->'.print_r($variant_id,1),'syncdata');
            /**

            First store only sync images

             */
            $contextShopID = Shop::getContextShopID();
            Shop::setContext(Shop::CONTEXT_ALL);

            $catch_images = array();

            if (isset($images)&& count($images)) {
                /**

                     Process images from this connection
                     Imagenes de formato que han entrado

                 */

                foreach($images as $image_reference => $image_list) {
                    if (is_array($image_list)) {
                        /**
                            Check correct sizes and filter images
                            Revisar correctos  y filtrar los
                         */
                        $this->debbug(' check correct sizes of images reference ->'.$image_reference.' value ->'.print_r($image_list,1),'syncdata');
                        foreach ($this->format_images_sizes as $imgFormat) {
                            if (isset($image_list[$imgFormat]) && !empty($image_list[$imgFormat])) {
                                $catch_images[$image_reference] = $image_list[$imgFormat];
                                break;
                            }
                        }
                    }

                }

                $catch_images_references = '';
                $slyr_images = $slyr_images_to_delete = array();

                if (!empty($catch_images)){
                   // imagenes elegidos para subir a este formato buscar en producto padre si en ya hay este imagen

                    /**
                        How to a search images cached in SL table for MD5 hash
                     */
                    $this->debbug(' How to a search images cached in SL table for MD5 hash ','syncdata');

                        $catch_images_references =  "'".implode("','", array_keys($catch_images))."'";
                        $slyr_images = Db::getInstance()->executeS("SELECT * FROM ".$this->slyr_images_table." WHERE image_reference IN (".$catch_images_references.") AND ps_product_id = '".$product_id."' ");

                }


                /**
                Process images from this connection
                 */
                $this->debbug('before process prepared images for update stat of array  ->'.print_r($catch_images,1),'syncdata');
                $ps_images   = Image::getImages($id_lang, $product_id);
                if(count($ps_images)== 0){
                    $cover = true;
                }else{
                    $cover = false;
                }

                foreach($catch_images as $image_reference => $image_url) {
                    $this->debbug('Process images of variant and set it to product from this connection ->'.print_r($image_reference,1).'  '.print_r($image_url,1),'syncdata');
                    $time_ini_image = microtime(1);
                    $url = trim($image_url);

                    if (!empty($url)) {
                        $temp_image = $this->downloadImageToTemp($url);

                        if ($temp_image) {

                            $md5_image = md5_file($temp_image);

                            if (!empty($slyr_images)) {

                                foreach ($slyr_images as $keySLImg => $slyr_image) {

                                    $variant_ids = array();
                                    if ($slyr_image['ps_variant_id'] != null) {
                                        $variant_ids = json_decode($slyr_image['ps_variant_id'], 1);
                                    } else {

                                    }

                                    if ($slyr_image['image_reference'] == $image_reference && $slyr_image['md5_image'] !== '') {
                                        /**
                                         * Image is the same
                                         */

                                        unset($slyr_images[$keySLImg]);

                                        if ($slyr_image['md5_image'] !== $md5_image) {

                                            /**
                                             * Image with same name but different md5
                                             */

                                            if (in_array($variant_id, $variant_ids, false)) {
                                                /**
                                                 * Verify if is needed delete this file
                                                 */

                                                $new_array = array();
                                                foreach ($variant_ids as $variant_id_key => $variant_id_in_search) {
                                                    if ($variant_id_in_search != $variant_id) {
                                                        $new_array[] = $variant_id_in_search;
                                                    }
                                                }

                                                $variant_ids = $new_array;
                                                if (empty($variant_ids)) {// this variant  is unique variant in use this file

                                                    if ($slyr_image['origin'] == 'frmt' || empty($slyr_image['origin'])) { // if origin if the image is this variant delete it  from product
                                                        $image_delete = new Image($slyr_image['id_image']);
                                                        $image_delete->delete();
                                                        break;
                                                    }

                                                }
                                            }

                                        } else {
                                            /**
                                             * Image found / Update this image if is needed
                                             */
                                            if (in_array($variant_id, $variant_ids, false)) { // image is the same and this variant is inthe array


                                            } else {
                                                // set this variant it to the foto
                                                /**
                                                 *
                                                 * aqui continuar con editcion de imagen
                                                 */

                                                $variant_ids[] = $variant_id;
                                                $need_update = false;

                                                $image_cover = new Image($slyr_image['id_image']);
                                                foreach ($this->shop_languages as $shop_language){
                                                    if (!isset($image_cover->legend[$shop_language['id_lang']])) {   // if is empty
                                                        if ($product_name != '' && ( !isset($image_cover->legend[$shop_language['id_lang']]) && ( empty($image_cover->legend[$shop_language['id_lang']]) || trim($image_cover->legend[$shop_language['id_lang']]) != trim($product_name)))) {
                                                                $need_update = true;
                                                                $image_cover->legend[$shop_language['id_lang']] = $product_name;
                                                               // $this->debbug('Set image alt atribute  need update this image info ->' . print_r($image_cover->legend[$shop_language['id_lang']], 1) . '  !=  ' . print_r($product_name, 1), 'syncdata');
                                                            } else {
                                                                $this->debbug('Image is the same, image alt attribute not is needed update this image info ->' . print_r($image_cover->legend[$shop_language['id_lang']], 1) . '  ==  ' . print_r($product_name, 1), 'syncdata');
                                                            }
                                                    }
                                                }
                                                if ($cover && !$image_cover->cover && count($ps_images) == 1) { //  is first image  set to cover && Image is already like cover
                                                    try {
                                                        Image::deleteCover($product_id); // delete cover image from this product
                                                    } catch (Exception $e) {
                                                        $this->debbug('## Error. '.$occurence.' Delete cover ->' . print_r($e->getMessage(), 1), 'syncdata');
                                                    }
                                                    $need_update = true;
                                                    $image_cover->cover = $cover; // set this image as cover
                                                    $cover = false;
                                                    $this->debbug('Image es unique foto of this product set it as cover ', 'syncdata');
                                                }else{
                                                    $image_cover->cover = null;
                                                }

                                                if ($need_update) {
                                                    $this->debbug('Image need to update ', 'syncdata');

                                                    try {
                                                        $image_cover->save();

                                                    } catch (Exception $e) {
                                                        $this->debbug('## Error. '.$occurence.' Update Image info product->' . print_r($e->getMessage(), 1), 'syncdata');
                                                    }
                                                }

                                                $variant_ids = json_encode($variant_ids);
                                                $this->debbug('Update variant ids of image', 'syncdata');
                                                Db::getInstance()->execute("UPDATE " . $this->slyr_images_table . " SET ps_variant_id ='" . $variant_ids . "' WHERE id_image = '" . $image_cover->id . "' ");
                                                $image_ids[] = $image_cover->id;
                                            }
                                            unlink($temp_image);
                                            unset($image_cover);

                                            // exit from  second loop
                                            continue 2;

                                        }

                                    }

                                }

                            }

                            /**
                             * Process images that do not exist in the sl cache and have arrived in this connection
                             */
                            try {
                                $image = new Image();
                                $variant_ids = array($variant_id);
                                $image->id_product = (int)$product_id;
                                $image->position = Image::getHighestPosition($product_id) + 1;

                                foreach ($this->shop_languages as $shop_language) {
                                    if (!isset($image->legend[$shop_language['id_lang']])) {   // if is empty
                                        if ($product_name != '' && (!isset($image->legend[$shop_language['id_lang']]) && (empty($image->legend[$shop_language['id_lang']]) || trim($image->legend[$shop_language['id_lang']]) != trim($product_name)))) {
                                            $image->legend[$shop_language['id_lang']] = $product_name;
                                            //$this->debbug('Set image alt atribute  need update this image info ->' . print_r($image->legend[$shop_language['id_lang']], 1) . '  !=  ' . print_r($product_name, 1), 'syncdata');
                                        } else {
                                            $this->debbug('The image is the same and the alt attribute of the image has not changed is not necessary, update the information of this image. ->' . print_r($image->legend[$shop_language['id_lang']], 1) . '  ==  ' . print_r($product_name, 1), 'syncdata');
                                        }
                                    }
                                }

                                if (count($ps_images) == 0 && $cover) {// is first image of this product
                                    try {
                                        Image::deleteCover($product_id); // delete cover image from this product
                                    } catch (Exception $e) {
                                        $this->debbug('## Error. '.$occurence.' Delete cover ->' . print_r($e->getMessage(), 1), 'syncdata');
                                    }
                                    $image->cover = $cover;
                                    $cover = false;
                                } else {
                                    $image->cover = null;
                                }

                                try{
                                    $validate_fields = $image->validateFields(false, true);
                                } catch (Exception $e) {
                                    $validate_fields = false;
                                    $this->debbug('## Error. '.$occurence.' Validate fields of image in Variant ->' . print_r($e->getMessage(), 1).' url->'.$url, 'syncdata');
                                }
                                try{
                                    $validate_language = $image->validateFieldsLang(false, true);
                                } catch (Exception $e) {
                                    $validate_language = false;
                                    $this->debbug('## Error. '.$occurence.' Validate language fields of image in Variant ->' . print_r($e->getMessage(), 1).' url->'.$url, 'syncdata');
                                }
                                try{
                                    $result_save_image = $image->add();
                                } catch (Exception $e) {
                                    $result_save_image = false;
                                    $this->debbug('## Error. '.$occurence.' Problem save image variant ->' . print_r($e->getMessage(), 1).' url->'.$url, 'syncdata');
                                }


                                if($result_save_image != true){

                                    $this->debbug('## Warning. Problem to create image template for image. Prestashop may have broken the table of images We will try to repair it.','syncdata');
                                    try{
                                        $this->repairImageStructureOfProduct($product_id);
                                    }catch(Exception $e){
                                        $this->debbug('## Error. '.$occurence.' In repairing structure of images '.$e->getMessage(),'syncdata');
                                    }

                                    $result_save_image = $image->add();

                                }


                                // file_exists doesn't work with HTTP protocol
                                if ($validate_fields === true && $validate_language === true && $result_save_image) {

                                    if (!$this->copyImg($product_id, $image->id, $temp_image, 'products', true, true)) {

                                        $image->delete();

                                    } else {
                                        $all_shops_image = Shop::getShops(true, null, true);
                                        $image->associateTo($all_shops_image);


                                        $variant_ids = json_encode($variant_ids);

                                        /**
                                         * INSERT INTO SL CACHE TABLE IMAGE WITH MD5, NAME OF FILE , ID
                                         */

                                        Db::getInstance()->execute("INSERT INTO " . $this->slyr_images_table . " (image_reference, id_image, md5_image, ps_product_id, ps_variant_id) VALUES ('" . $image_reference . "', " . $image->id . ", '" . $md5_image . "','" . $product_id . "','" . $variant_ids . "') ON DUPLICATE KEY UPDATE id_image = '" . $image->id . "', md5_image = '" . $md5_image . "'");

                                        $image_ids[] = $image->id;

                                    }

                                } else {
                                    unlink($temp_image);
                                    $this->debbug('Image of Variant no aceptaed as Valid ', 'syncdata');
                                }

                            }catch(Exception $e){
                                $this->debbug('## Error. '.$occurence.' Error in create new format image problem found->'.print_r($e->getMessage(),1), 'syncdata');
                            }



                            unset($image);
                        }
                    }
                    $this->debbug('END to process this image Timing ->'.($time_ini_image - microtime(1)),'syncdata');

                }
                unset($image);

            }else{
                 // el formato ya no tiene imagenes debemos eliminar si tiene en prestashop asignada alguna imagen
                $this->debbug('We will check if any of the images has been imported in the past with this variant','syncdata');
                $slyr_images = Db::getInstance()->executeS("SELECT * FROM ".$this->slyr_images_table." WHERE  ps_product_id = '".$product_id."' ");

                if(!empty($slyr_images)){
                    foreach ($slyr_images as $keySLImg => $slyr_image ){
                        $this->debbug('Test if is needed to delete this image '.print_r($slyr_image,1),'syncdata');

                        $variant_ids = array();
                        if($slyr_image['ps_variant_id'] != null){
                            $variant_ids = json_decode($slyr_image['ps_variant_id'],1);
                        }

                        if(in_array($variant_id,$variant_ids,false)){
                            $this->debbug('Id of this variant '.$variant_id.' is in variants array-> '.print_r($variant_ids,1),'syncdata');
                            /**
                            Verify if is needed delete this file
                             */

                            $new_array = array();
                            foreach ($variant_ids as $variant_id_key => $variant_id_in_search){
                                if($variant_id_in_search != $variant_id){
                                    $new_array[] = $variant_id_in_search;
                                }
                            }


                            $variant_ids = $new_array;
                            if(empty($variant_ids)){// this variant  is unique variant in use this file
                              //  $this->debbug('Array is empty how to a send this image, but before test if is  upload from product or  -> '.print_r($variant_ids,1),'syncdata');
                                if($slyr_image['origin'] == 'frmt'|| empty($slyr_image['origin'])){ // if origin if the image is this variant delete it  from product
                                  //  $this->debbug('Deleting image because the image has been sent from this format and now no longer has this format no photo','syncdata');
                                    $image_delete = new Image($slyr_image['id_image']);
                                    $image_delete->delete();
                                    Db::getInstance()->execute("DELETE FROM ".$this->slyr_images_table."  WHERE id_image = '".$slyr_image['id_image']."' " );
                                    unset($image_delete);
                                }

                            }

                            $variant_ids = json_encode($variant_ids);

                            Db::getInstance()->execute("UPDATE ".$this->slyr_images_table." SET ps_variant_id ='".$variant_ids."' WHERE id_image = '".$slyr_image['id_image']."' " );

                        }

                    }

                }
            }

            Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
            return $image_ids;
        }

    /**
     * Synchronize an attribute
     * @param $attributeGroupName string name of attribute group
     * @param $attributeValue string value of attribute
     * @param $attribute_id string if of attribute
     * @param string $connector_id
     * @param $comp_id
     * @param $conn_shops
     * @param $currentLanguage
     * @param $multilanguage array of multi-language values
     * @return array|bool|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function synchronizeAttribute($attribute_group_id, $attributeValue, $attribute_id, $connector_id, $comp_id, $conn_shops, $currentLanguage, $multilanguage){

        $this->debbug('Entry to  $attribute_group_id->'.print_r($attribute_group_id,1).'  $attributeValue ->'.print_r($attributeValue,1).' $attribute_id->'.print_r($attribute_id,1).'  $this->comp_id ->'.print_r($comp_id,1).'  $conn_shops->'.print_r($conn_shops,1).'  $currentLanguage ->'.print_r($currentLanguage,1).'  $this->defaultLanguage ->'.print_r($this->defaultLanguage,1).' $multilanguage->'.print_r($multilanguage,1),'syncdata');


        //Buscamos registro en tabla Slyr con cualquier idioma
        $schema = 'SELECT ps_id FROM '.$this->slyr_table.' WHERE slyr_id = "'.$attribute_id.'" AND comp_id = "'.$comp_id.'" AND ps_attribute_group_id = "'.$attribute_group_id.'" AND ps_type = "product_format_value"';
        $attribute_exists = Db::getInstance()->executeS($schema);

        try{

            if(empty($multilanguage)){
                $left_group_lang  = " = '".$currentLanguage."'";
                $left_group_value = " al.`name` LIKE '".$attributeValue."'";
            }else{

                $left_group_lang  = " IN('".implode("','",array_keys(  $multilanguage))."') ";

                if(count($multilanguage) > 1){
                    $left_group_value =' ( ';
                    $counter = 1;
                //  $atr_values =   array_values($multilenguage);
                  $count_end = count($multilanguage);
                    foreach($multilanguage as $col_like){
                        if($count_end == $counter ){
                            $left_group_value .= " al.`name` LIKE '". $col_like."' ";
                        }else{
                            $left_group_value .= " al.`name` LIKE '". $col_like."'  OR ";
                        }
                        $counter++;
                    }
                    $left_group_value .=' ) ';

                }else{

                    $left_group_value = " al.`name` LIKE '".reset($multilanguage)."' ";
                }

            }

            //Buscamos id de atributo con padre,nombre e idioma ya existente
            $schemaAttribute = "SELECT al.`id_attribute` ".
                "FROM ".$this->attribute_group_table." ag ".
                "LEFT JOIN ".$this->attribute_group_lang_table." agl ".
                "	ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` ".$left_group_lang." ) ".
                "LEFT JOIN ".$this->attribute_table." a ".
                "	ON a.`id_attribute_group` = ag.`id_attribute_group` ".
                "LEFT JOIN ".$this->attribute_lang_table." al ".
                "	ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` ".$left_group_lang.") ".
                "WHERE  ag.`id_attribute_group` = '".$attribute_group_id."'  AND ".$left_group_value."   ".
                " GROUP BY al.`id_attribute` ".
                "ORDER BY agl.`name` ASC, a.`position` ASC";

            $isAttribute = Db::getInstance()->executeS($schemaAttribute);

         //   $this->debbug('## Busca el attributo query->'.print_r($schemaAttribute,1),'syncdata');


        }catch(Exception $e){
            $this->debbug('## Error. '.print_r($multilanguage,1).'  Error in sql ->'.print_r($schemaAttribute,1).' line->'.$e->getLine(),'syncdata');
        }

       // $this->debbug('Attribute after search ->'.print_r($isAttribute,1),'syncdata');

        if ( count($isAttribute) == 0 || !$isAttribute ){

            $this->debbug('Attribute does not exist ->'.print_r($isAttribute,1),'syncdata');
            /**
                Not exist this attribute
             */

            $schemaGroupAttributeColor = "SELECT `is_color_group` ".
                " FROM ".$this->attribute_group_table.
                " WHERE `id_attribute_group` = '".$attribute_group_id."' ";

            $isColorGroupAttribute = Db::getInstance()->executeS($schemaGroupAttributeColor);

            $is_color = false;
            if (count($isColorGroupAttribute) > 0){

                if ($isColorGroupAttribute[0]['is_color_group'] == 1){

                    $is_color = true;

                }

            }
            $show_color = '#ffffff';
            //Creamos nuevo atributo
            $attribute = new AttributeCore();
            $attribute->name = array();
            try{
                if(!empty($multilanguage)){
                    /**
                    Is multilanguage create attribute with all Values with more languages
                     */

                    foreach ($multilanguage as $id_lang => $att_value){

                        $attribute->name[$id_lang] = ucfirst($att_value);
                        if($is_color && $show_color == '#ffffff'){
                          $picked =  $this->stringToColorCode(strtolower($att_value));
                          if($picked != null ){
                              $show_color =  $picked;
                          }
                        }

                        if ($id_lang != $this->defaultLanguage){
                            if ( $attribute->name[$this->defaultLanguage]== null || $attribute->name[$this->defaultLanguage] == '' ){ $attribute->name[$this->defaultLanguage] = ucfirst($att_value); }
                        }
                    }

                }else{
                    /**
                    Only one language
                     */

                    $attribute->name[$currentLanguage] = ucfirst($attributeValue);
                    if($is_color && $show_color == '#ffffff'){ // is color? how to pick color from word
                        $picked =  $this->stringToColorCode($attributeValue);
                        if($picked != null ){
                            $show_color =  $picked;
                        }
                    }

                    if ($currentLanguage != $this->defaultLanguage){
                        if (!isset($attribute->name[$this->defaultLanguage]) || $attribute->name[$this->defaultLanguage]== null || $attribute->name[$this->defaultLanguage] == '' ){ $attribute->name[$this->defaultLanguage] = ucfirst($attributeValue); }
                    }
                }
            } catch (Exception $e) {
                $this->debbug('## Error. '.print_r($multilanguage,1).' Creating attribute->'.print_r($e->getMessage(),1),'syncdata');
            }

            $attribute->id_attribute_group = $attribute_group_id;
            $position = AttributeCore::getHigherPosition($attribute_group_id);
            $attribute->position = $position == null ? 0 : $position + 1;

            if ($is_color){
                $attribute->color = $show_color;

            }

            try{
                $attribute->add();
            } catch (Exception $e) {
                $this->debbug('## Error. '.print_r($multilanguage,1).'  Creating New attribute ->'.print_r($e->getMessage(),1),'syncdata');
            }
            $attribute_value_id = $attribute->id;

        }else{

            $this->debbug('Attribute found send only id'.print_r($isAttribute,1),'syncdata');
            //Obtenemos id de atributo ya existente
            $attribute_value_id = $isAttribute[0]['id_attribute'];

            if(!empty($multilanguage)){

                $this->debbug('field is a multi-language'.print_r($isAttribute,1).', values of multi-language ->'.print_r($multilanguage,1),'syncdata');
                /**
                Update if is an Language more posible to set or overwrite
                 */
                $update_needed = false;
                $attribute = new AttributeCore($attribute_value_id);
                foreach($multilanguage as $id_lang => $Value ){

                   if(($attribute->name[$id_lang] == null || $attribute->name[$id_lang] == '' )&&  $Value != '' && $Value != null ){
                       $this->debbug('set name of $Value->'.print_r($Value,1),'syncdata');
                       /**
                       Any translate is diferent?
                        */
                       $attribute->name[$id_lang] = ucfirst($Value);
                       $update_needed = true;
                       $this->debbug('Set name for attribute in another language need update...'.print_r($Value,1).' array->'.print_r($attribute->name,1),'syncdata');
                   }

                }
                if($update_needed){

                    try{
                        $attribute->save();
                    } catch (Exception $e) {
                        $this->debbug('## Error. '.print_r($multilanguage,1).' Save changes to Attribute->'.print_r($e->getMessage(),1),'syncdata');
                    }

                }

            }
        }


        if(empty($multilanguage)){  // if is simple Value  set lenguage to all values
            /**
                 If is empty set language current selected
             */

            $multilanguage[$currentLanguage] = $attributeValue;

        }


       // foreach ($multilanguage as $id_lang => $value){

                if (count($attribute_exists) > 0 ) {

                    $attribute_exists_id = $attribute_exists[0]['ps_id'];

                    //Buscamos registro de lenguaje en tabla Slyr
                    $attribute_lang_exists = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE slyr_id = "%s" AND comp_id = "%s" AND ps_attribute_group_id = "%s" AND ps_type = "product_format_value" ', $attribute_id, $comp_id, $attribute_group_id)); //  AND id_lang = "%s"  $id_lang
                    if (($attribute_lang_exists == 0 || !$attribute_lang_exists)  ){ //|| $attribute_exists_id != $attribute_value_id
                        $this->debbug('  register not founded $attribute_lang_exists->'.print_r($attribute_lang_exists,1),'syncdata');
                        //Inserta registro de lenguaje en tabla Slyr
                        Db::getInstance()->execute(
                            sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, ps_attribute_group_id, comp_id,  date_add) VALUES("%s", "%s", "%s", "%s", "%s",  CURRENT_TIMESTAMP())',
                                $attribute_value_id,
                                $attribute_id,
                                'product_format_value',
                                $attribute_group_id,
                                $comp_id

                            ));  // id_lang     $id_lang

                    }else{

                        $this->debbug(' register  founded  update it $attribute_lang_exists->'.print_r($attribute_lang_exists,1),'syncdata');
                        //Actualiza registro de lenguaje en tabla Slyr
                        Db::getInstance()->execute(
                            sprintf('UPDATE '.$this->slyr_table.' SET date_upd = CURRENT_TIMESTAMP() WHERE ps_id = "%s" AND comp_id = "%s" AND ps_type = "product_format_value" AND ps_attribute_group_id = "%s" ',
                                $attribute_value_id,
                                $comp_id,
                                $attribute_group_id
                            ));  // AND id_lang = "%s" , $id_lang

                    }

                   if ($attribute_exists_id != $attribute_value_id){
                       $this->debbug(' register  founded  update it $attribute_exists_id != $attribute_value_id, $attribute_exists_id ->'.print_r($attribute_exists_id,1).'   $attribute_value_id->'.print_r($attribute_value_id,1),'syncdata');                      //Cambia registro de lenguaje en tabla Slyr por nuevo
                        Db::getInstance()->execute(
                            sprintf('UPDATE '.$this->slyr_table.' SET date_upd = CURRENT_TIMESTAMP(), ps_id = "%s" WHERE ps_id = "%s" AND comp_id = "%s" AND ps_type = "product_format_value" AND ps_attribute_group_id = "%s"',
                                $attribute_value_id,
                                $attribute_id,
                                $comp_id,
                                $attribute_group_id
                            ));
                    }


                }else{
                    $this->debbug(' Inserta registro de lenguaje en tabla Slyr  $attribute_exists == 0','syncdata');
                    //Inserta registro de lenguaje en tabla Slyr
                    Db::getInstance()->execute(
                        sprintf('INSERT INTO '.$this->slyr_table.'(ps_id, slyr_id, ps_type, ps_attribute_group_id, comp_id, date_add) VALUES("%s", "%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                            $attribute_value_id,
                            $attribute_id,
                            'product_format_value',
                            $attribute_group_id,
                            $comp_id
                        )); // $id_lang

                }

        //}

        if (count($conn_shops) > 0){
            $this->debbug(' actualizar los registros de tiendas ->  count($conn_shops) > 0 ->'.print_r($conn_shops,1).', $attribute_group_id->'.$attribute_group_id,'syncdata');
            //Actualizamos tiendas
            $schemaAttrExtra =	" SELECT id, shops_info FROM ".$this->slyr_table.
                " WHERE ps_id = ".$attribute_value_id." AND ps_attribute_group_id = ".$attribute_group_id." AND comp_id = ".$comp_id." AND ps_type = 'product_format_value'";

            $attrsInfo = Db::getInstance()->executeS($schemaAttrExtra);

            $schemaAttrShops =	" SELECT id_shop FROM ".$this->attribute_shop_table.
                " WHERE id_attribute = ".$attribute_value_id;

            $attr_shops = Db::getInstance()->executeS($schemaAttrShops);

            foreach ($conn_shops as $shop_id) {
                $found = false;
                //Primero buscamos en las existentes
                if (count($attr_shops) > 0){
                    foreach ($attr_shops as $key => $attr_shop) {
                        if ($shop_id == $attr_shop['id_shop']){
                            $found = true;
                            //Eliminamos para obtener sobrantes
                            unset($attr_shops[$key]);
                            break;
                        }
                    }
                }

                if (!$found){
                    Db::getInstance()->execute(
                        sprintf('INSERT INTO '.$this->attribute_shop_table.'(id_attribute, id_shop) VALUES("%s", "%s")',
                            $attribute_value_id,
                            $shop_id
                        ));
                }

            }

            $shopsConnectors = array();
            foreach ($attrsInfo as $attrInfo) {
                $shops_info = json_decode($attrInfo['shops_info'], 1);
                if (is_array($shops_info) && count($shops_info) > 0){
                    foreach ($shops_info as $conn_id => $shops) {
                        if (!isset($shopsConnectors[$conn_id])){ $shopsConnectors[$conn_id] = array(); }
                        foreach ($shops as $shop) {
                            if (!in_array($shop, $shopsConnectors[$conn_id],false)){
                                //array_push($shopsConnectors[$conn_id], $shop);
                                $shopsConnectors[$conn_id][] = $shop;
                            }
                        }
                    }
                }
            }

            foreach ($attrsInfo as $key => $attrInfo) {

                $sl_attr_info_conns = json_decode($attrInfo['shops_info'], 1);

                //Revisamos las sobrantes
                if (count($attr_shops) > 0){
                    //Buscamos en conectores
                    foreach ($attr_shops as $attr_shop_key => $attr_shop) {
                        $found = false;
                        if (is_array($sl_attr_info_conns) && count($sl_attr_info_conns) > 0){
                            foreach ($sl_attr_info_conns as $sl_attr_info_conn => $sl_attr_info_conn_shops) {
                                if ($sl_attr_info_conn != $connector_id){
                                    if (in_array($attr_shop['id_shop'],$sl_attr_info_conn_shops,false)){
                                        $found = true;
                                        break;
                                    }
                                }
                            }

                            if (count($shopsConnectors) > 0){
                                foreach ($shopsConnectors as $conn_id => $shopsConnector) {
                                    if ($connector_id != $conn_id && in_array($attr_shop['id_shop'], $shopsConnector,false)){
                                        $found = true;
                                    }
                                }
                            }
                        }

                        //         	if (!$found){
                        //         		Db::getInstance()->execute(
                        //         		    sprintf('DELETE FROM '.$this->attribute_shop_table.' WHERE id_attribute = "%s" AND id_shop = "%s"',
                        //         		    $attribute_value_id,
                        //         		    $attr_shop['id_shop']
                        //         		));
                        // }
                    }
                }


            }

           // foreach ($multilanguage as $id_lang => $value){
                //Actualizamos unicamente el registro de este atributo
                $schemaAttrExtra =	" SELECT id, shops_info FROM ".$this->slyr_table.
                    " WHERE ps_id = ".$attribute_value_id." AND ps_attribute_group_id = ".$attribute_group_id." AND slyr_id = ".$attribute_id." AND comp_id = ".$comp_id." AND ps_type = 'product_format_value'  "; //   AND id_lang = '".$id_lang ."'

                $attrInfo = Db::getInstance()->executeS($schemaAttrExtra);

                if(isset($attrInfo[0]['id'])){

                    if(isset($attrInfo[0]['shops_info'])){
                        //Actualizamos el registro
                        $sl_attr_info_conns = json_decode($attrInfo[0]['shops_info'], 1);
                        $sl_attr_info_conns[$connector_id] = $conn_shops;
                        $shopsInfo = json_encode($sl_attr_info_conns);
                    }else{
                        //Creamos el registro
                        $sl_attr_info_conns[$connector_id] = $conn_shops;
                        $shopsInfo = json_encode($sl_attr_info_conns);
                    }

                    $schemaUpdateShops = " UPDATE ".$this->slyr_table." SET shops_info = '".$shopsInfo."' WHERE id = ".$attrInfo[0]['id'];
                    $retorno = Db::getInstance()->execute($schemaUpdateShops);

                }else{
                    $this->debbug('## warning. '.print_r($multilanguage,1).' Register of attribute in this language not exist $attribute_group_id->'.$attribute_group_id.' $attribute_id = slyr_id->'.$attribute_id.' comp_id->'.$comp_id.'. It is not a serious problem but working with several connectors and stores at the same time could lose track.' ,'syncdata');

                }

            //}
        }
        unset($attribute);
        return $attribute_value_id;

    }

      private function stringToColorCode($str) {

        $colors_arr = array('black'=>'#000000','silver'=>'#c0c0c0','gray'=>'#808080','white'=>'#ffffff','maroon'=>'#800000','red'=>'#ff0000','purple'=>'#800080','fuchsia'=>'#ff00ff','green'=>'#008000','lime'=>'#00ff00','olive'=>'#808000','yellow'=>'#ffff00',
            'navy'=>'#000080','blue'=>'#0000ff','teal'=>'#008080','aqua'=>'#00ffff','orange'=>'#ffa500','aliceblue'=>'#f0f8ff','antiquewhite'=>'#faebd7','aquamarine'=>'#7fffd4','azure'=>'#f0ffff','beige'=>'#f5f5dc','bisque'=>'#ffe4c4','blanchedalmond'=>'#ffe4c4',
            'blueviolet'=>'#8a2be2','brown'=>'#a52a2a','burlywood'=>'#deb887','cadetblue'=>'#5f9ea0','chartreuse'=>'#7fff00','chocolate'=>'#d2691e','coral'=>'#ff7f50','cornflowerblue'=>'#6495ed','cornsilk'=>'#fff8dc','crimson'=>'#dc143c','darkblue'=>'#00008b','darkcyan'=>'#008b8b',
            'darkgoldenrod'=>'#b8860b','darkgray'=>'#a9a9a9','darkgreen'=>'#006400','darkgrey'=>'#a9a9a9','darkkhaki'=>'#bdb76b','darkmagenta'=>'#8b008b','darkolivegreen'=>'#556b2f','darkorange'=>'#ff8c00','darkorchid'=>'#9932cc','darkred'=>'#8b0000','darksalmon'=>'#e9967a',
            'darkseagreen'=>'#8fbc8f','darkslateblue'=>'#483d8b','darkslategray'=>'#2f4f4f','darkslategrey'=>'#2f4f4f','darkturquoise'=>'#00ced1','darkviolet'=>'#9400d3','deeppink'=>'#ff1493','deepskyblue'=>'#00bfff','dimgray'=>'#696969','dimgrey'=>'#696969','dodgerblue'=>'#1e90ff',
            'firebrick'=>'#b22222','floralwhite'=>'#fffaf0','forestgreen'=>'#228b22','gainsboro'=>'#dcdcdc','ghostwhite'=>'#f8f8ff','gold'=>'#ffd700','goldenrod'=>'#daa520','greenyellow'=>'#adff2f','grey'=>'#808080','honeydew'=>'#f0fff0','hotpink'=>'#ff69b4','indianred'=>'#cd5c5c',
            'indigo'=>'#4b0082','ivory'=>'#fffff0','khaki'=>'#f0e68c','lavender'=>'#e6e6fa','lavenderblush'=>'#fff0f5','lawngreen'=>'#7cfc00','lemonchiffon'=>'#fffacd','lightblue'=>'#add8e6','lightcoral'=>'#f08080','lightcyan'=>'#e0ffff','lightgoldenrodyellow'=>'#fafad2',
            'lightgray'=>'#d3d3d3','lightgreen'=>'#90ee90','lightgrey'=>'#d3d3d3','lightpink'=>'#ffb6c1','lightsalmon'=>'#ffa07a','lightseagreen'=>'#20b2aa','lightskyblue'=>'#87cefa','lightslategray'=>'#778899','lightslategrey'=>'#778899','lightsteelblue'=>'#b0c4de',
            'lightyellow'=>'#ffffe0','limegreen'=>'#32cd32','linen'=>'#faf0e6','mediumaquamarine'=>'#66cdaa','mediumblue'=>'#0000cd','mediumorchid'=>'#ba55d3','mediumpurple'=>'#9370db','mediumseagreen'=>'#3cb371','mediumslateblue'=>'#7b68ee','mediumspringgreen'=>'#00fa9a',
            'mediumturquoise'=>'#48d1cc','mediumvioletred'=>'#c71585','midnightblue'=>'#191970','mintcream'=>'#f5fffa','mistyrose'=>'#ffe4e1','moccasin'=>'#ffe4b5','navajowhite'=>'#ffdead','oldlace'=>'#fdf5e6','olivedrab'=>'#6b8e23','orangered'=>'#ff4500','orchid'=>'#da70d6',
            'palegoldenrod'=>'#eee8aa','palegreen'=>'#98fb98','paleturquoise'=>'#afeeee','palevioletred'=>'#db7093','papayawhip'=>'#ffefd5','peachpuff'=>'#ffdab9','peru'=>'#cd853f','pink'=>'#ffc0cb','plum'=>'#dda0dd','powderblue'=>'#b0e0e6','rosybrown'=>'#bc8f8f','royalblue'=>'#4169e1',
            'saddlebrown'=>'#8b4513','salmon'=>'#fa8072','sandybrown'=>'#f4a460','seagreen'=>'#2e8b57','seashell'=>'#fff5ee','sienna'=>'#a0522d','skyblue'=>'#87ceeb','slateblue'=>'#6a5acd','slategray'=>'#708090','slategrey'=>'#708090','snow'=>'#fffafa','springgreen'=>'#00ff7f',
            'steelblue'=>'#4682b4','tan'=>'#d2b48c','thistle'=>'#d8bfd8','tomato'=>'#ff6347','turquoise'=>'#40e0d0','violet'=>'#ee82ee','wheat'=>'#f5deb3','whitesmoke'=>'#f5f5f5','yellowgreen'=>'#9acd32','rebeccapurple'=>'#663399');

        return (isset($colors_arr[strtolower($str)])? $colors_arr[strtolower($str)]:null);

    }

    public  function deleteVariant($product_format,$comp_id,$shops){

        $format_ps_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.$this->slyr_table.' WHERE slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"', $product_format, $comp_id));

        if ($format_ps_id) {
            $ps_product_id = '';
            foreach ($shops as $shop) {
                try{

                    Shop::setContext(Shop::CONTEXT_SHOP, $shop);
                    $form = new CombinationCore($format_ps_id, null, $shop);
                    if($ps_product_id == ''){
                        $ps_product_id = $form->id_product;
                    }
                    $form->delete();

                }catch(Exception $e){
                    $this->debbug('## Error. Deleting variant ID : '.$product_format.'  error->'.print_r($e->getMessage(),1),'syncdata');
                }

            }
            $this->debbug('Deleting variant : '.$format_ps_id.'  ','syncdata');
            if($ps_product_id != ''){
                try{
                    $this->syncVariantImageToProduct(array(),$this->defaultLanguage,$ps_product_id,'',$format_ps_id);
                }catch(Exception $e){
                    $this->debbug('## Error. In removing variant image from product : '.$format_ps_id.'  error->'.print_r($e->getMessage(),1),'syncdata');
                }
            }



                Db::getInstance()->execute(
                    sprintf('DELETE FROM '.$this->slyr_table.' WHERE slyr_id = "%s" AND comp_id = "%s" AND ps_type = "combination"',
                        $product_format,
                        $comp_id
                ));


        }
        $this->clear_debug_content();
    }



}
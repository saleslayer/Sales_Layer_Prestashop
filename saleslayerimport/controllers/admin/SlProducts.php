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

class SlProducts extends SalesLayerPimUpdate
{
    public function __construct()
    {
        parent::__construct();
    }

    public function loadProductImageSchema(
        $products_schema
    ) {
        if (empty($this->product_images_sizes)) {
            if (!empty($products_schema['fields']['product_image']['image_sizes'])) {
                $product_field_images_sizes = $products_schema['fields']['product_image']['image_sizes'];
                $ordered_image_sizes = $this->orderArrayImg($product_field_images_sizes);
                foreach (array_keys($ordered_image_sizes) as $img_size) {
                    $this->product_images_sizes[] = $img_size;
                }
                unset($product_field_images_sizes, $ordered_image_sizes);
            } else {
                if (!empty($products_schema['fields']['image_sizes'])) {
                    $product_field_images_sizes = $products_schema['fields']['image_sizes'];
                    $ordered_image_sizes = $this->orderArrayImg($product_field_images_sizes);
                    foreach (array_keys($ordered_image_sizes) as $img_size) {
                        $this->product_images_sizes[] = $img_size;
                    }
                    unset($product_field_images_sizes, $ordered_image_sizes);
                } else {
                    $this->product_images_sizes[] = array('ORG', 'IMD', 'THM', 'TH');
                }
            }
        }
        $this->debbug(' load: product_images_sizes ' . print_r($this->product_images_sizes, 1), 'syncdata');
    }

    public function syncOneProduct(
        $product,
        $schema,
        $comp_id,
        $shops,
        $avoid_stock_update,
        $sync_categories
    ) {
        $test_after_update = array();
        $mulilanguage = array();
        $occurence_found = false;
        if (isset($product['data']['product_reference']) && !empty($product['data']['product_reference'])) {
            $occurence_found = true;
            $occurence = ' product reference : "' . $product['data']['product_reference'] . '"';
        } elseif (isset($product['data']['product_name']) && !empty($product['data']['product_name'])) {
            $occurence_found = true;
            $occurence = ' product name :"' . $product['data']['product_name'] . '"';
        } else {
            $occurence = ' ID :' . $product['ID'];
        }
        $this->sync_categories =  $sync_categories;
        $syncCat = true;
        $additional_output = array();
        $is_new_product = false;

        $this->debbug(
            ' Entering to process: ' . $occurence . '  Product Information: ' . print_r(
                $product,
                1
            ) . '  $shops ->' . print_r(
                $shops,
                1
            ) . '   comp_id ->' . print_r($comp_id, 1),
            'syncdata'
        );

        if (empty($comp_id) || empty($shops)) {
            $this->debbug('## Error. ' . $occurence . '. Some of the data is not filled in correctly ', 'syncdata');

            return 'item_not_updated';
        }

        $product_exists = (int)Db::getInstance()->getValue(
            sprintf(
                'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                 WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product" ',
                $product['ID'],
                $comp_id
            )
        );


        //Force verify if exist already
        /*  if( $product_exists ) {
        $productObject_exist = Db::getInstance()->getValue('SELECT * FROM '._DB_PREFIX_.'product
        WHERE id_product="'.$product_exists.'"');
              $this->debbug('Verify if product '.$product_exists.' cached exist in prestashop ->'.
        print_r($productObject_exist,1),'syncdata');

              if(!$productObject_exist){
                  $this->debbug('Deleting product form slyr table but not exist in prestashop','syncdata');
                  Db::getInstance()->execute( 'DELETE FROM '._DB_PREFIX_.'slyr_category_product
        WHERE ps_type = "product" AND ps_id = "'.$product_exists.'" ');
                  $product_exists = false;
              }
          }*/


        if (!$product_exists) {
            $this->debbug('Synchronize product ID: ' . $product['ID'], 'syncdata');
            try {
                $product_exists = $this->syncProduct($product, $comp_id, $schema);
                $is_new_product = true;
            } catch (Exception $e) {
                $this->debbug(
                    '## Error. Synchronizing product syncProduct-> ' . print_r($e->getMessage(), 1),
                    'syncdata'
                );
            }
        }


        if (!$product_exists) {
            $this->debbug('## Error. When creating the product with ' . $occurence, 'syncdata');

            //continue;
            return false;
        } else {
            /**
             * SAVED PRODUCT SUCCESS
             */

            $arrayIdCategories = array();
            $exist_id_categories = isset($product['ID_catalogue']);
            if ($exist_id_categories && $this->sync_categories) {
                $sl_product_parent_ids = $product['ID_catalogue'];

                if (!is_array($sl_product_parent_ids)) {
                    $sl_product_parent_ids = array($sl_product_parent_ids);
                }
            }



            if ($exist_id_categories && $this->sync_categories && !empty($sl_product_parent_ids)) {
                foreach ($sl_product_parent_ids as $sl_product_parent_id) {
                    $product_category_id = (int)Db::getInstance()->getValue(
                        sprintf(
                            'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                            WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                            $sl_product_parent_id,
                            $comp_id
                        )
                    );

                    if (!$product_category_id) {
                        $this->debbug(
                            '## Error. ' . $occurence . ' The catalog with ID :' .
                            $sl_product_parent_id . ' for the company with ID: '
                            . $comp_id . ' does not exist in the table. ' .
                             'It is possible that it has been deactivated or deleted.' .
                            'Change the state of the category and of the product to invisible,' .
                            'and then back to visible again so that the issue can be resolved.',
                            'syncdata'
                        );
                        continue;
                    } else {
                        $sl_product_parent_id = $product_category_id;

                        if ($product_category_id != 0) {
                            if ($sl_product_parent_id) {
                                do {
                                    $schemaCats = 'SELECT id_category, id_parent, active FROM '
                                        . $this->category_table .
                                        " WHERE id_category = '" . $sl_product_parent_id . "' 
                                        ORDER BY id_category ASC LIMIT 1";
                                    $category = Db::getInstance()->executeS($schemaCats);

                                    if (isset($category[0]['id_category']) && $category[0]['id_category'] != 0
                                        && !in_array(
                                            $category[0]['id_category'],
                                            $arrayIdCategories,
                                            false
                                        )
                                    ) {
                                        /*   $schemaCatslangg = 'SELECT id_category,name FROM '
                                                         . $this->category_lang_table .
                                                         " WHERE id_category = '" . $category[0]['id_category'] . "'
                                                          AND id_lang = '1'
                                           ORDER BY id_category ASC LIMIT 1";
                                           $categorylang = Db::getInstance()->executeS($schemaCatslangg);

                                           $this->debbug('Get name of category ->' .
                                                         print_r($categorylang[0]['name'], 1) . ' return -> ' .
                                                         print_r($categorylang, 1), 'syncdata');*/
                                        $sl_product_parent_id =  $category[0]['id_parent'];
                                        $arrayIdCategories[] = $category[0]['id_category'];
                                    } else {
                                        $sl_product_parent_id = 0;
                                    }


                                    if ($sl_product_parent_id === 0) {
                                        break;
                                    }
                                } while ($sl_product_parent_id !== 0);
                            } else {
                                $arrayIdCategories[] = 0;
                            }
                        }
                    }
                }
            }


            /**
             *
             * Update Product
             */
            $this->first_sync_shop = true;

            foreach ($shops as $shop_id) {
                Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);

                $productObject = new Product($product_exists, false, null, $shop_id);



                // $update_needed = false;
                if (isset($product['data']['product_type'])) {
                    $product_type = '';

                    if (is_array($product['data']['product_type']) && !empty($product['data']['product_type'])) {
                        $product_type = reset($product['data']['product_type']);
                    } else {
                        if (!is_array($product['data']['product_type']) && $product['data']['product_type'] != '') {
                            $product_type = $product['data']['product_type'];
                        }
                    }

                    if (is_string($product_type)) {
                        if (preg_match('/(simple)/i', $product_type)) {
                            $product_type = Product::PTYPE_SIMPLE;
                        } else {
                            if (preg_match('/(pack)/i', $product_type)) {
                                $product_type = Product::PTYPE_PACK;
                            } else {
                                $product_type = '';
                            }
                        }
                    } else {
                        if (is_numeric($product_type)) {
                            if ($product_type != Product::PTYPE_SIMPLE && $product_type != Product::PTYPE_PACK) {
                                $product_type = '';
                            }
                        }
                    }

                    if ($product_type == Product::PTYPE_SIMPLE || $product_type == Product::PTYPE_PACK) {
                        $current_product_type = $productObject->getType();

                        /**
                         *
                         * PRODUC TYPE SIMPLE
                         */


                        if ($product_type == Product::PTYPE_SIMPLE) {
                            if ($current_product_type != Product::PTYPE_SIMPLE) {
                                try {
                                    $productObject->setWsType('simple');
                                } catch (Exception $e) {
                                    $this->debbug(
                                        '## Error. ' . $occurence . ' In changing type of product->' .
                                        print_r($e->getMessage(), 1) .
                                        'line->' . $e->getLine() .
                                        'trace->' . print_r($e->getTrace(), 1),
                                        'syncdata'
                                    );
                                }
                            }
                        }

                        /**
                         *
                         * PRODUCT TYPE PACK
                         */

                        if ($product_type == Product::PTYPE_PACK) {
                            $product_packs_data = $processed_pack_ids = array();

                            $array_format_pack = preg_grep('/pack_format_\+?\d+$/i', array_keys($product['data']));

                            foreach ($array_format_pack as $pack_format) {
                                $pack_id = str_replace('pack_format_', '', $pack_format);
                                $pack_product_id = $pack_quantity = 0;
                                $pack_format_ref = '';

                                if (isset($product['data'][$pack_format]) &&
                                    is_array(
                                        $product['data'][$pack_format]
                                    )
                                    && !empty($product['data'][$pack_format])
                                ) {
                                    $pack_format_ref = reset($product['data'][$pack_format]);
                                } else {
                                    if (!is_array(
                                        $product['data'][$pack_format]
                                    )
                                        && $product['data'][$pack_format] != ''
                                    ) {
                                        if (strpos($product['data'][$pack_format], ',')) {
                                            $pack_format_field_data = explode(',', $product['data'][$pack_format]);
                                            $pack_format_ref = reset($pack_format_field_data);
                                        } else {
                                            $pack_format_ref = $product['data'][$pack_format];
                                        }
                                    }
                                }

                                if ($pack_format_ref == '') {
                                    continue;
                                }

                                // $pack_format_ref = trim(preg_replace('/[^A-Za-z0-9\-]/', ' ', $pack_format_ref));
                                $pack_format_ref = $this->slValidateReference($pack_format_ref);


                                $schemaRef = 'SELECT id_product_attribute,id_product FROM ' .
                                    $this->product_attribute_table . "
                                             WHERE reference = '" . $pack_format_ref . "'";
                                $regsRef = Db::getInstance()->executeS($schemaRef);



                                if (count($regsRef) > 0) {
                                    $pack_format_id = $regsRef[0]['id_product_attribute'];
                                    $pack_product_id = $regsRef[0]['id_product'];
                                    $processed_pack_ids[$pack_id] = 0;
                                    $pack_quantity_pack_id_index = 'pack_quantity_' . $pack_id;


                                    if (isset($product['data'][$pack_quantity_pack_id_index])
                                        && is_numeric(
                                            $product['data'][$pack_quantity_pack_id_index]
                                        )
                                        && $product['data'][$pack_quantity_pack_id_index] > 0
                                    ) {
                                        $pack_quantity = $product['data'][$pack_quantity_pack_id_index];
                                    } else {
                                        $pack_quantity = 1;
                                    }

                                    $product_packs_data[] = array(
                                        'pack_product_id' => $pack_product_id,
                                        'pack_format_id' => $pack_format_id,
                                        'pack_quantity' => $pack_quantity,
                                    );
                                }
                            }

                            $array_product_pack = preg_grep('/pack_product_\+?\d+$/i', array_keys($product['data']));

                            foreach ($array_product_pack as $pack_product) {
                                $pack_id = str_replace('pack_product_', '', $pack_product);
                                $pack_format_id = $pack_product_id = $pack_quantity = 0;
                                $pack_product_ref = '';

                                if (isset($product['data'][$pack_product]) && is_array(
                                    $product['data'][$pack_product]
                                )
                                    && !empty($product['data'][$pack_product])
                                ) {
                                    $pack_product_ref = reset($product['data'][$pack_product]);
                                } else {
                                    if (!is_array(
                                        $product['data'][$pack_product]
                                    )
                                        && $product['data'][$pack_product] != ''
                                    ) {
                                        if (strpos($product['data'][$pack_product], ',')) {
                                            $pack_format_field_data = explode(',', $product['data'][$pack_product]);
                                            $pack_product_ref = reset($pack_format_field_data);
                                        } else {
                                            $pack_product_ref = $product['data'][$pack_product];
                                        }
                                    }
                                }

                                if ($pack_product_ref == '' || isset($processed_pack_ids[$pack_id])) {
                                    continue;
                                }


                                $pack_product_ref = $this->slValidateReference($pack_product_ref);

                                $schemaRef = 'SELECT id_product FROM ' . $this->product_table . "
                                WHERE reference = '" . $pack_product_ref . "'";
                                $regsRef = Db::getInstance()->executeS($schemaRef);

                                if (count($regsRef) > 0) {
                                    $pack_product_id = $regsRef[0]['id_product'];
                                    $pack_quantity_pack_id_index = 'pack_quantity_' . $pack_id;
                                    if (isset($product['data'][$pack_quantity_pack_id_index])
                                        && is_numeric(
                                            $product['data'][$pack_quantity_pack_id_index]
                                        )
                                        && $product['data'][$pack_quantity_pack_id_index] > 0
                                    ) {
                                        $pack_quantity = $product['data'][$pack_quantity_pack_id_index];
                                    } else {
                                        $pack_quantity = 1;
                                    }

                                    $product_packs_data[] = array(
                                        'pack_product_id' => $pack_product_id,
                                        'pack_format_id' => $pack_format_id,
                                        'pack_quantity' => $pack_quantity,
                                    );
                                }
                            }

                            if (!empty($product_packs_data)) {
                                try {
                                    if ($current_product_type != Product::PTYPE_PACK) {
                                        $productObject->setWsType('pack');
                                    }

                                    Pack::deleteItems($productObject->id);

                                    foreach ($product_packs_data as $product_pack_data) {
                                        if ($product_pack_data['pack_format_id'] != 0) {
                                            Pack::addItem(
                                                $productObject->id,
                                                $product_pack_data['pack_product_id'],
                                                $product_pack_data['pack_quantity'],
                                                $product_pack_data['pack_format_id']
                                            );
                                        } else {
                                            Pack::addItem(
                                                $productObject->id,
                                                $product_pack_data['pack_product_id'],
                                                $product_pack_data['pack_quantity'],
                                                0
                                            );
                                        }
                                    }
                                } catch (Exception $e) {
                                    $this->debbug(
                                        '## Error. ' . $occurence . ' product type pack->' . $e->getMessage() .
                                        'line->' . print_r($e->getLine(), 1) .
                                        'trace->' . print_r($e->getTrace(), 1),
                                        'syncdata'
                                    );
                                }
                            }
                        }
                    }
                }
                /**
                 * Update tax rule
                 */
                $this->debbug(
                    'before tax rules updating ->' . print_r(
                        (isset($product['data']['product_id_tax_rules_group']) ?
                            $product['data']['product_id_tax_rules_group']:'empty'),
                        1
                    ),
                    'syncdata'
                );

                $tax_updated = false;
                if (isset($product['data']['product_id_tax_rules_group']) &&
                    $product['data']['product_id_tax_rules_group'] != null &&
                    $product['data']['product_id_tax_rules_group'] != '') {
                    $is_percentage = false;
                    $id_tax_rules_group_default_from_cloud = array();


                    if (preg_match('/%/', $product['data']['product_id_tax_rules_group'])) {
                        $is_percentage = true;
                        $product['data']['product_id_tax_rules_group'] =
                            trim(str_replace('%', '', $product['data']['product_id_tax_rules_group']));
                    }
                    if ($product['data']['product_id_tax_rules_group'] != '') {
                        if (is_numeric($product['data']['product_id_tax_rules_group'])) {
                            if ($is_percentage) {
                                $format_rate = number_format(
                                    $product['data']['product_id_tax_rules_group'],
                                    3,
                                    '.',
                                    ' '
                                );
                                $query_for_search_tax =  '';
                                try {
                                    $query_for_search_tax = sprintf(
                                        'SELECT trg.id_tax_rules_group FROM ' .
                                            $this->product_tax_rules_group_table . ' trg ' .
                                            ' INNER JOIN ' . $this->product_tax_rules_group_shop_table . ' trgs ' .
                                            ' ON  ( trg.id_tax_rules_group = trgs.id_tax_rules_group AND ' .
                                            ' trgs.id_shop = "%s" ) ' .
                                            ' INNER JOIN ' . $this->product_tax_rule_table . ' tr  ' .
                                            ' ON  trg.id_tax_rules_group = tr.id_tax_rules_group   ' .
                                            ' INNER JOIN ' . $this->product_tax_table . ' t ' .
                                            ' ON  tr.id_tax = t.id_tax  ' .
                                            ' WHERE t.rate = "%s" AND t.active ="1" AND t.deleted = "0" ' .
                                            ' GROUP BY trg.id_tax_rules_group LIMIT 2 ',
                                        $shop_id,
                                        $format_rate
                                    );
                                    $id_tax_rules_group_default_from_cloud =
                                    Db::getInstance()->executeS($query_for_search_tax);

                                    if (count($id_tax_rules_group_default_from_cloud)) {
                                        /**
                                         * Founded tax rule
                                         */
                                        $first = (int) $id_tax_rules_group_default_from_cloud[0]['id_tax_rules_group'];
                                        $product['data']['product_id_tax_rules_group'] = $first;
                                        if (count($id_tax_rules_group_default_from_cloud) > 1) {
                                            $this->debbug(
                                                '## Warning. ' . $occurence . ' There are more rules with this tax.' .
                                                          ' The first one has been chosen but it may be that a ' .
                                                          'rule with the same percentage is chosen for a different ' .
                                                          'type of product. For more precision use id_tax_rules_group.',
                                                'syncdata'
                                            );
                                        }
                                    } else {
                                        /**
                                         *  id_tax_rules_group not founded
                                         */
                                        $this->debbug('## Error. ' . $occurence . ' There is no tax rule for' .
                                                      ' this store and this rate.' .
                                                  'Please first create a rule create a rule for that tax.' .
                                                  print_r($product['data']['product_id_tax_rules_group'], 1) .
                                                  '% store_id-> ' .
                                                  print_r($shop_id, 1), 'syncdata');
                                    }
                                } catch (Exception $e) {
                                    $this->debbug('## Error. ' . $occurence .
                                                  ' in seach tax rule group in query ' . $e->getMessage() .
                                              ' ->' . print_r($query_for_search_tax, 1), 'syncdata');
                                }
                            } else {
                                $id_tax_rules_group_default_from_cloud =  Db::getInstance()->executeS(
                                    sprintf(
                                        'SELECT id_tax_rules_group FROM ' .
                                        $this->product_tax_rules_group_table .
                                        ' WHERE id_tax_rules_group = "%s" AND active ="1"  LIMIT 1',
                                        $product['data']['product_id_tax_rules_group']
                                    )
                                );
                            }

                            if (count($id_tax_rules_group_default_from_cloud)) {
                                $this->debbug(
                                    'Tax_rules  updating ->' . print_r(
                                        $id_tax_rules_group_default_from_cloud,
                                        1
                                    ),
                                    'syncdata'
                                );

                                $productObject->id_tax_rules_group =
                                    (int) $product['data']['product_id_tax_rules_group'];
                                $tax_updated = true;
                            } else {
                                if (!$is_percentage) {
                                    $this->debbug(
                                        '## Error. ' . $occurence . ' id of tax rule not founded ->' . print_r(
                                            $product['data']['product_id_tax_rules_group'],
                                            1
                                        ),
                                        'syncdata'
                                    );
                                }
                            }
                        } else {
                            $this->debbug(
                                '## Error. ' . $occurence . ' tax rules is not a numeric value ->' . print_r(
                                    $product['data']['product_id_tax_rules_group'],
                                    1
                                ),
                                'syncdata'
                            );
                        }
                    }
                }

                if ($shop_id != $productObject->id_shop_default) {
                    $id_tax_rules_group_default = (int)Db::getInstance()->getValue(
                        sprintf(
                            'SELECT id_tax_rules_group FROM ' .
                            $this->product_shop_table .
                            ' WHERE id_product = "%s" AND id_shop = "%s"',
                            $product_exists,
                            $productObject->id_shop_default
                        )
                    );

                    if ($id_tax_rules_group_default != $productObject->id_tax_rules_group) {
                        if (!$tax_updated) {
                            $productObject->id_tax_rules_group = $id_tax_rules_group_default;
                        }
                        if ($productObject->price == null) {
                            $productObject->price = 0;
                        }
                        if ($productObject->low_stock_alert == null) {
                            $productObject->low_stock_alert = false;
                        }
                        try {
                            $this->debbug(' Updating all shops languages ' . print_r(
                                $this->first_sync_shop,
                                1
                            ), 'syncdata');
                            $productObject->save();
                        } catch (Exception $e) {
                            $syncCat = false;
                            $this->debbug(
                                '## Error. ' . $occurence . ' When saving changes to product ->' . print_r(
                                    $e->getMessage(),
                                    1
                                ),
                                'syncdata'
                            );
                        }
                    }
                }
                /**
                 * Multi language  set values
                 */
                $this->debbug(' Updating all shops languages ' . print_r($this->first_sync_shop, 1), 'syncdata');

                $customization_multi_language = array();
                $carriers_not_founded = array();
                $update_carriers = false;
                $product_carriers_ids = array();
                $alt_images = array();


                foreach ($this->shop_languages as $lang) {
                    $defaultLenguage = (int) $this->defaultLanguage;

                    $friendly_url = '';
                    $available_now = '';
                    $available_later = '';
                    $product_description = '';
                    $product_name = '';

                    /**
                     * Set Name of product
                     */

                    $product_name_index = '';
                    $product_name_index_search = 'product_name_' . $lang['iso_code'];

                    if (isset(
                        $product['data'][$product_name_index_search],
                        $schema[$product_name_index_search]['language_code']
                    ) &&
                         !empty($product['data'][$product_name_index_search])
                        && $schema[$product_name_index_search]['language_code'] == $lang['iso_code']) {
                        $product_name_index = 'product_name_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_name']) && !empty($product['data']['product_name'])
                        && !isset($schema['product_name']['language_code'])) {
                        $product_name_index = 'product_name';
                    }

                    if ($product_name_index != '' && isset($product['data'][$product_name_index])
                        && !empty($product['data'][$product_name_index])) {
                        if (!$occurence_found) {
                            $occurence = $product['data'][$product_name_index];
                            $occurence_found = true;
                        }


                        $this->debbug(
                            'Name found ->' . print_r(
                                $product['data'][$product_name_index],
                                1
                            ) . ' language code  ' . print_r($lang['iso_code'], 1) . ' index used ' . print_r(
                                $product_name_index,
                                1
                            ),
                            'syncdata'
                        );

                        // $product_name = preg_replace('/[^A-Za-z0-9\-]/', ' ', $product['data']['product_name']);
                        // if ($product_name == ''){ $product_name = 'Untitled Product'; }
                        $product_name = $product['data'][$product_name_index];
                        if (trim($product_name) != '') {
                            $productObject->name[$lang['id_lang']] = $product_name;
                        }
                        $mulilanguage[$lang['id_lang']] = $product['data'][$product_name_index];

                        $friendly_url_index = '';
                        $friendly_url_index_search = 'friendly_url_' . $lang['iso_code'];
                        if (isset(
                            $product['data'][$friendly_url_index_search],
                            $schema[$friendly_url_index_search]['language_code']
                        ) &&
                             !empty($product['data'][$friendly_url_index_search])
                            && $schema[$friendly_url_index_search]['language_code'] == $lang['iso_code']) {
                            $friendly_url_index = 'friendly_url_' . $lang['iso_code'];
                        } elseif (isset($product['data']['friendly_url'])
                            && !empty($product['data']['friendly_url'])
                            && !isset($schema['friendly_url']['language_code'])) {
                            $friendly_url_index = 'friendly_url';
                        }

                        if (isset($product['data'][$friendly_url_index])) {
                            if ($product['data'][ $friendly_url_index ] != '') {
                                $friendly_url = $product['data'][ $friendly_url_index ];
                            } else {
                                $friendly_url = $product_name;
                            }

                            if ($friendly_url != '') {
                                $friendly_url = $this->slValidateCatalogName(
                                    $friendly_url,
                                    'Product'
                                );
                                $productObject->link_rewrite[ $lang['id_lang'] ] = Tools::link_rewrite($friendly_url);
                            }
                        }
                    }

                    /**
                     *
                     * Set description
                     */
                    $product_description_index = '';
                    $product_description_index_search = 'product_description_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_description_index_search],
                        $schema[$product_description_index_search]['language_code']
                    ) &&
                         !empty($product['data'][$product_description_index_search])
                        && $schema[$product_description_index_search]['language_code'] == $lang['iso_code']) {
                        $product_description_index = 'product_description_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_description'])
                        && !empty($product['data']['product_description'])
                        && !isset($schema['product_description']['language_code'])) {
                        $product_description_index = 'product_description';
                    }

                    if ($product_description_index != '' && isset($product['data'][$product_description_index])
                        && !empty($product['data'][$product_description_index])) {
                        $product_description =
                            html_entity_decode(
                                $product['data'][$product_description_index]
                            );
                        $productObject->description[$lang['id_lang']] = $product_description;
                    }
                    /**
                     * Product description short
                     */

                    $product_desc_short_index = '';
                    $product_desc_short_index_search = 'product_description_short_' . $lang['iso_code'];
                    if (isset($product['data']['product_description_short'])
                        && !empty($product['data']['product_description_short'])
                        && !isset($schema['product_description_short']['language_code'])) {
                        $product_desc_short_index = 'product_description_short';
                    } elseif (isset(
                        $product['data'][$product_desc_short_index_search],
                        $schema[$product_desc_short_index_search]['language_code']
                    )
                        && !empty($product['data'][$product_desc_short_index_search])
                        && $schema[$product_desc_short_index_search]['language_code'] == $lang['iso_code']) {
                        $product_desc_short_index = 'product_description_short_' . $lang['iso_code'];
                    }

                    if (isset($product['data'][$product_desc_short_index])
                        && $product['data'][$product_desc_short_index] != '') {
                        $product_description_short = html_entity_decode($product['data'][$product_desc_short_index]);
                    } else {
                        $product_description_short = html_entity_decode($product_description);
                    }

                    if (Tools::strlen($product_description_short) > 800) {
                        $product_description_short =
                            Tools::substr($product_description_short, 0, 800);
                    }

                    if ($product_description_short != ''
                        && (!isset($productObject->description_short[$lang['id_lang']]) ||
                            (isset($productObject->description_short[$lang['id_lang']]) &&
                             $productObject->description_short[$lang['id_lang']] != $product_description_short))) {
                        $productObject->description_short[$lang['id_lang']] = $product_description_short;
                    }

                    /**
                     * Meta title
                     */
                    $meta_title = '';
                    $meta_title_index = '';
                    $meta_title_index_search = 'meta_title_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$meta_title_index_search],
                        $schema[$meta_title_index_search]['language_code']
                    ) &&
                         !empty($product['data'][$meta_title_index_search])
                        && $schema[$meta_title_index_search]['language_code'] == $lang['iso_code']) {
                        $meta_title_index = 'meta_title_' . $lang['iso_code'];
                    } elseif (isset($product['data']['meta_title']) && !empty($product['data']['meta_title'])
                        && !isset($schema['meta_title']['language_code'])) {
                        $meta_title_index = 'meta_title';
                    }

                    if (isset($product['data'][$meta_title_index]) && $product['data'][$meta_title_index] != '') {
                        $meta_title = $product['data'][$meta_title_index];
                    } else {
                        if (isset($product['data'][$product_name_index])
                            && !empty($product['data'][$product_name_index]) &&
                            $productObject->meta_title[$lang['id_lang']] == '') {
                            $meta_title =  $this->clearForMetaData($product['data'][$product_name_index]);
                            if (Tools::strlen($meta_title) > 80) {
                                $meta_title = Tools::substr($meta_title, 0, 80);
                            }
                        }
                    }

                    if ($meta_title != '') {
                        if (isset($productObject->meta_title[$lang['id_lang']])) {
                            if ($productObject->meta_title[$lang['id_lang']] != $meta_title) {
                                $productObject->meta_title[$lang['id_lang']] = $meta_title;
                            }
                        } else {
                            $productObject->meta_title[$lang['id_lang']] = $meta_title;
                        }
                    }

                    /**
                     * Meta description
                     */
                    $meta_description = '';
                    $meta_description_index = '';
                    $meta_description_index_search = 'meta_description_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$meta_description_index_search],
                        $schema[$meta_description_index_search]['language_code']
                    ) &&
                         !empty($product['data'][$meta_description_index_search])
                        && $schema[$meta_description_index_search]['language_code'] == $lang['iso_code']) {
                        $meta_description_index = 'meta_description_' . $lang['iso_code'];
                    } elseif (isset($product['data']['meta_description'])
                        && !empty($product['data']['meta_description'])
                        && !isset($schema['meta_description']['language_code'])) {
                        $meta_description_index = 'meta_description';
                    }


                    if (isset($product['data'][$meta_description_index])
                        && $product['data'][$meta_description_index] != '') {
                        $meta_description = $product['data'][$meta_description_index];
                    } else {
                        if ($productObject->meta_description[$lang['id_lang']] == '') {
                            $meta_description =  $this->clearForMetaData($product_description_short);
                        }
                    }
                    if (Tools::strlen($meta_description) > 180) {
                        $meta_description = Tools::substr($meta_description, 0, 180);
                    }

                    if ($meta_description != ''
                        && $productObject->meta_description[$lang['id_lang']] != $meta_description) {
                        $this->debbug(
                            'Meta_description ->' .
                                      print_r($meta_description, 1),
                            'syncdata'
                        );

                        $productObject->meta_description[$lang['id_lang']] = $meta_description;
                    }
                    /**
                     * Load customization variable with all languages
                     */

                    $customization_index = '';
                    $customization_index_search = 'product_customizable_' . $lang['iso_code'];

                    if (isset(
                        $product['data'][$customization_index_search],
                        $schema[$customization_index_search]['language_code']
                    ) &&
                        !empty($product['data'][$customization_index_search])
                        && $schema[$customization_index_search]['language_code'] == $lang['iso_code']) {
                        $customization_index = 'product_customizable_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_customizable'])
                              && !empty($product['data']['product_customizable'])
                              && !isset($schema['product_customizable']['language_code'])) {
                        $customization_index = 'product_customizable';
                    }

                    if ($customization_index != '' && isset($product['data'][$customization_index])
                        && $product['data'][$customization_index] != '') {
                        if (!is_array($product['data'][$customization_index])) {
                            if (preg_match('/,/', $product['data'][$customization_index])) {
                                $number_of_fields_arr = explode(',', $product['data'][$customization_index]);
                                $product['data'][$customization_index] = $number_of_fields_arr;
                            }
                            $testtrue = $this->slValidateBoolean($product['data'][$customization_index]);
                            $this->debbug(
                                'After test bool  $testtrue->' . print_r($testtrue, 1),
                                'syncdata'
                            );
                            if ($product['data'][$customization_index] === false || $testtrue) {
                                $this->debbug(
                                    'convert to boolean  $testtrue->' . print_r($testtrue, 1),
                                    'syncdata'
                                );
                                $product['data'][$customization_index] = $testtrue;
                            }
                        }

                        $customization_multi_language[$lang['id_lang']] = $product['data'][$customization_index];
                    }

                    /**
                     * Available now
                     */
                    $product_available_now_index = '';
                    $product_available_now_index_search = 'available_now_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_available_now_index_search],
                        $schema[$product_available_now_index_search]['language_code']
                    ) &&
                        !empty($product['data'][$product_available_now_index_search])
                        && $schema[$product_available_now_index_search]['language_code'] == $lang['iso_code']) {
                        $product_available_now_index = 'available_now_' . $lang['iso_code'];
                    } elseif (isset($product['data']['available_now']) && !empty($product['data']['available_now'])
                        && !isset($schema['available_now']['language_code'])) {
                        $product_available_now_index = 'available_now';
                    }

                    if ($product_available_now_index != '' && isset($product['data'][$product_available_now_index])
                        && !empty($product['data'][$product_available_now_index])) {
                        $available_now = $product['data'][$product_available_now_index];
                        $productObject->available_now[$lang['id_lang']] = $available_now;
                    }

                    /**
                     *
                     * Available_later
                     */

                    $product_available_later_index = '';
                    $product_available_later_index_search = 'available_later_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_available_later_index_search],
                        $schema[$product_available_later_index_search]['language_code']
                    ) &&
                        !empty($product['data'][$product_available_later_index_search])
                        && $schema[$product_available_later_index_search]['language_code'] == $lang['iso_code']) {
                        $product_available_later_index = 'available_later_' . $lang['iso_code'];
                    } elseif (isset($product['data']['available_later']) && !empty($product['data']['available_later'])
                        && !isset($schema['available_later']['language_code'])) {
                        $product_available_later_index = 'available_later';
                    }


                    if ($product_available_later_index != ''
                        && isset($product['data'][$product_available_later_index])
                        && !empty($product['data'][$product_available_later_index])) {
                        $available_later = $product['data'][$product_available_later_index];
                        $productObject->available_later[$lang['id_lang']] = $available_later;
                    }

                    /**
                     *
                     * Default category
                     */
                    $category_default_found = false;

                    $product_category_default_index = '';
                    $product_category_default_index_search = 'category_sl_default_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_category_default_index_search],
                        $schema[$product_category_default_index_search]['language_code']
                    ) &&
                         !empty($product['data'][$product_category_default_index_search])
                        && $schema[$product_category_default_index_search]['language_code'] == $lang['iso_code']) {
                        $product_category_default_index = 'category_sl_default_' . $lang['iso_code'];
                    } elseif (isset($product['data']['category_sl_default'])
                        && !empty($product['data']['category_sl_default'])
                        && !isset($schema['category_sl_default']['language_code'])) {
                        $product_category_default_index = 'category_sl_default';
                    }

                    if ($product_category_default_index != ''
                        && isset($product['data'][$product_category_default_index])) {
                        $category_default_value = '';

                        if (is_array(
                            $product['data'][$product_category_default_index]
                        )
                            && !empty($product['data'][$product_category_default_index])
                        ) {
                            $category_default_value = reset($product['data'][$product_category_default_index]);
                        } else {
                            if (!is_array(
                                $product['data'][$product_category_default_index]
                            )
                                && $product['data'][$product_category_default_index] != ''
                            ) {
                                $category_default_value = $product['data'][$product_category_default_index];
                            }
                        }

                        if ($category_default_value != '') {
                            $schema_db = 'SELECT id_category,meta_keywords FROM ' . $this->category_lang_table .
                                " WHERE meta_keywords like '%" . $category_default_value . "%' 
                                AND id_lang = " . $lang['id_lang']
                                . " AND id_shop = " . $shop_id . " GROUP BY id_category";

                            $schemaCats = Db::getInstance()->executeS($schema_db);

                            if (!empty($schemaCats)) {
                                $ps_category_default_id = 0;

                                foreach ($schemaCats as $cat) {
                                    $cat_meta_keywords = $cat['meta_keywords'];

                                    if (strpos($cat_meta_keywords, ',')) {
                                        $cmk = explode(',', $cat_meta_keywords);

                                        if (in_array($category_default_value, $cmk, false)) {
                                            $ps_category_default_id = $cat['id_category'];
                                            break;
                                        }
                                    } else {
                                        if ($cat_meta_keywords == $category_default_value) {
                                            $ps_category_default_id = $cat['id_category'];
                                            break;
                                        }
                                    }
                                }

                                if ($ps_category_default_id != 0) {
                                    $this->debbug(
                                        'Id category selected->  ' . print_r($ps_category_default_id, 1),
                                        'syncdata'
                                    );

                                    $productObject->id_category_default = $ps_category_default_id;

                                    if (!in_array($ps_category_default_id, $arrayIdCategories, false)) {
                                        $arrayIdCategories[] = $ps_category_default_id;
                                    }

                                    $category_default_found = true;
                                }
                            }
                        }
                    }

                    if (!$category_default_found && count($arrayIdCategories)) {
                        $newdefault_category = reset($arrayIdCategories);
                        $this->debbug(
                            'Default category selected from last value->' . $newdefault_category,
                            'syncdata'
                        );
                        $productObject->id_category_default = $newdefault_category;
                    }

                    /**
                     * Product Carrier
                     */

                    $product_product_carrier_index = '';
                    $product_product_carrier_index_search = 'product_carrier_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_product_carrier_index_search],
                        $schema[$product_product_carrier_index_search]['language_code']
                    )
                        && $schema[$product_product_carrier_index_search]['language_code'] == $lang['iso_code']) {
                        $product_product_carrier_index = 'product_carrier_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_carrier'])
                         && !isset($schema['product_carrier']['language_code'])) {
                        $product_product_carrier_index = 'product_carrier';
                    }

                    if ($product_product_carrier_index != ''
                        && isset($product['data'][$product_product_carrier_index])) {
                        $product_carriers = array();
                        $update_carriers = true;
                        if (is_array(
                            $product['data'][$product_product_carrier_index]
                        )
                            && !empty($product['data'][$product_product_carrier_index])
                        ) {
                            $product_carriers = $product['data'][$product_product_carrier_index];
                        } else {
                            if (!is_array(
                                $product['data'][$product_product_carrier_index]
                            )
                                && $product['data'][$product_product_carrier_index] != ''
                            ) {
                                $product_carriers = explode(',', $product['data'][$product_product_carrier_index]);
                            }
                        }

                        if (!empty($product_carriers)) {
                            $carrier = new Carrier();
                            $existing_carriers = $carrier->getCarriers($lang['id_lang']);

                            foreach ($product_carriers as $position => $product_carrier) {
                                $match = false;
                                if (is_numeric($product_carrier)) {
                                    foreach ($existing_carriers as $existing_carrier) {
                                        if ($existing_carrier['id_reference'] == $product_carrier) {
                                            $product_carriers_ids[] = $existing_carrier['id_reference'];
                                            $match = true;
                                        }
                                    }
                                } else {
                                    foreach ($existing_carriers as $existing_carrier) {
                                        $this->debbug(' Check carrier with this data-> ' .
                                                      print_r(
                                                          $existing_carrier,
                                                          1
                                                      ), 'syncdata');
                                        if (Tools::strtolower(trim($this->removeAccents($existing_carrier['name'])))
                                            == Tools::strtolower(
                                                trim($this->removeAccents($product_carrier))
                                            )
                                        ) {
                                            $this->debbug('Carrier found from name -> ' .
                                                          print_r(
                                                              $existing_carrier['name'],
                                                              1
                                                          ), 'syncdata');
                                            $product_carriers_ids[] = $existing_carrier['id_reference'];
                                            $match = true;
                                        }
                                    }
                                }
                                if (!$match) {
                                    if (!in_array($product_carrier, $carriers_not_founded, false)) {
                                        $carriers_not_founded[$position] =  $product_carrier;
                                    }
                                }
                            }
                        }
                    }

                    /**
                     * Tags
                     */

                    $product_tag_index = '';
                    $product_tag_index_index_search = 'product_tag_' . $lang['iso_code'];
                    if (isset(
                        $product['data'][$product_tag_index_index_search],
                        $schema[$product_tag_index_index_search]['language_code']
                    )
                        && $schema[$product_tag_index_index_search]['language_code'] == $lang['iso_code']) {
                        $product_tag_index = 'product_tag_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_tag'])
                              && !isset($schema['product_tag']['language_code'])) {
                        $product_tag_index = 'product_tag';
                    }

                    if ($product_tag_index != '' && isset($product['data'][$product_tag_index])) {
                        if (is_array($product['data'][$product_tag_index])) {
                            $my_tags = $product['data'][$product_tag_index];
                        } else {
                            $my_tags = array();
                            $explode = explode(',', $product['data'][$product_tag_index]);
                            foreach ($explode as $values) {
                                if (!empty($values)) {
                                    $my_tags[] = $values;
                                }
                            }
                        }
                        $this->syncTagsSL($productObject->id, $my_tags, $lang, $shop_id);
                    }

                    /**
                     * Image alt atributes
                     */
                    $product_alt_index = '';
                    $product_alt_images_search = 'product_alt_' . $lang['iso_code'];

                    if (isset(
                        $product['data'][$product_alt_images_search],
                        $schema[$product_alt_images_search]['language_code']
                    ) &&
                        !empty($product['data'][$product_alt_images_search])
                        && $schema[$product_alt_images_search]['language_code'] == $lang['iso_code']) {
                        $product_alt_index = 'product_alt_' . $lang['iso_code'];
                    } elseif (isset($product['data']['product_alt']) &&
                              !empty($product['data']['product_alt'])) {
                        $product_alt_index = 'product_alt';
                    }
                    $this->debbug('index selected for alt ->  ' . print_r(
                        $product_alt_index,
                        1
                    )
                    . ' id_lang -> ' .
                    print_r($lang['id_lang'], 1), 'syncdata');

                    if ($product_alt_index != '' && isset($product['data'][$product_alt_index]) &&
                        !empty($product['data'][$product_alt_index])) {
                        $alt_images_arr = array();
                        $product['data'][$product_alt_index] =
                            $this->clearStructureData($product['data'][$product_alt_index]);
                        if (is_array($product['data'][$product_alt_index])) {
                            $alt_images_arr = $this->clearAndOrderArray($product['data'][$product_alt_index]);
                        } else {
                            $alt_images_arr = explode(',', $product['data'][$product_alt_index]);
                        }
                        foreach ($alt_images_arr as $key => $value) {
                            $alt_images_arr[$key] = trim($value);
                        }
                        $this->debbug('Save data from alt attribute product  ' . print_r(
                            $alt_images_arr,
                            1
                        )
                        . ' id_lang -> ' .
                        print_r($lang['id_lang'], 1), 'syncdata');
                        $alt_images[$lang['id_lang']] =  $alt_images_arr;
                        $this->debbug(' after save  ' . print_r(
                            $alt_images,
                            1
                        ), 'syncdata');
                    } else {
                        $this->debbug(' after save from else  ' . print_r(
                            $alt_images,
                            1
                        ), 'syncdata');
                    }

                    /**
                     * Autogenerate attribute alt for images if is empty
                     * Rellenar faltantes atributos alt
                     */
                    if (isset($product['data']['product_image'])) {
                        $images_in_array = sizeof($product['data']['product_image']);
                        for ($counter = 0; $counter < $images_in_array; $counter++) {
                            $show_number = '';
                            if ($counter != 0) {
                                $show_number = ' (' . $counter . ')';
                            }
                            if (empty($alt_images[ $lang['id_lang'] ][ $counter ]) &&
                                !empty($mulilanguage[ $lang['id_lang'] ])) {
                                $alt_images[ $lang['id_lang'] ][ $counter ] =
                                        $this->clearForMetaData($mulilanguage[ $lang['id_lang'] ]) . $show_number;
                                $this->debbug('complete array with name ->  ' . print_r(
                                    $alt_images[ $lang['id_lang'] ][ $counter ],
                                    1
                                ), 'syncdata');
                            }
                        }
                    }

                    /**
                     * Place your custom multi-language code here
                     */







                    /**
                     *
                     * Default language if it is null set it from any Language
                     */
                    if ($lang['id_lang'] != $this->defaultLanguage) {
                        if ($product_name != '' && (!isset($productObject->name[$defaultLenguage])
                                || ($productObject->name[$defaultLenguage] == null
                                    || $productObject->name[$defaultLenguage] == ''))) {
                            $productObject->name[$defaultLenguage] = $product_name;
                        }
                        if ($product_description != '' && (!isset($productObject->description[$defaultLenguage])
                                || ($productObject->description[$defaultLenguage] == null
                                    || $productObject->description[$defaultLenguage] == ''))) {
                            $productObject->description[$defaultLenguage] = $product_description;
                        }
                        if ($product_description_short != ''
                            && (!isset($productObject->description_short[$defaultLenguage])
                                || ($productObject->description_short[$defaultLenguage] == null
                                    || $productObject->description_short[$defaultLenguage] == ''))) {
                            $productObject->description_short[$defaultLenguage] = $product_description_short;
                        }
                        if ($meta_title != '' && (!isset($productObject->meta_title[$defaultLenguage])
                                || ($productObject->meta_title[$defaultLenguage] == null
                                    || $productObject->meta_title[$defaultLenguage] == ''))) {
                            $productObject->meta_title[$defaultLenguage] = $meta_title;
                        }
                        if ($meta_description != '' && (!isset($productObject->meta_description[$defaultLenguage])
                                || ($productObject->meta_description[$defaultLenguage] == null
                                    || $productObject->meta_description[$defaultLenguage] == ''))) {
                            $productObject->meta_description[$defaultLenguage] = $meta_description;
                        }
                        if ($friendly_url != null && $friendly_url != '' &&
                            (!isset($productObject->link_rewrite[$defaultLenguage])
                                || ($productObject->link_rewrite[$defaultLenguage] == null
                                    || $productObject->link_rewrite[$defaultLenguage] == ''))) {
                            $productObject->link_rewrite[$defaultLenguage] = Tools::link_rewrite($friendly_url);
                        }
                        if ($available_now != '' && (!isset($productObject->available_now[$defaultLenguage])
                                || ($productObject->available_now[$defaultLenguage] == null
                                    || $productObject->available_now[$defaultLenguage] == ''))) {
                            $productObject->available_now[$defaultLenguage] = $available_now;
                        }
                        if ($available_later != '' && (!isset($productObject->available_later[$defaultLenguage])
                                || ($productObject->available_later[$defaultLenguage] == null
                                    || $productObject->available_later[$defaultLenguage] == ''))) {
                            $productObject->available_later[$defaultLenguage] = $available_later;
                        }
                    }
                }



                /**
                 * Place your custom non multi-language code here
                 */







                /**
                 * Clear all remove leftover fields of alt attribute (languages that do not match those of prestashop)
                 */

                $array_alt_attributes = preg_grep('/format_alt?/', array_keys($product['data']));
                if (!empty($array_alt_attributes)) {
                    foreach ($array_alt_attributes as $alt_field) {
                        if (isset($product['data'][$alt_field])) {
                            unset($product['data'][$alt_field]);
                        }
                    }
                }

                /**
                 * Sync images
                 */

                if ($this->first_sync_shop) {
                    try {
                        if (isset($product['data']['product_image'])) {
                            $this->syncProductImages(
                                $product['data']['product_image'],
                                $lang['id_lang'],
                                $productObject->id,
                                $alt_images
                            );
                        }

                        unset($product['data']['product_image']);
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. ' . $occurence . ' In Synchronization of images :' . print_r(
                                $e->getMessage(),
                                1
                            ),
                            'syncdata'
                        );
                    }

                    $this->first_sync_shop = false;
                }
                if ($exist_id_categories || count($arrayIdCategories)) {
                    // $productObject->addToCategories($arrayIdCategories);
                    // $productObject->updateCategories($arrayIdCategories);
                    $this->debbug('Before updating categories ->' . print_r($arrayIdCategories, 1), 'syncdata');
                    try {
                        $categories = Product::getProductCategories($productObject->id);
                        $this->debbug(
                            'before testing old categories -> ' . print_r(
                                $categories,
                                1
                            ) . '  new set Categories ->' . print_r(
                                $arrayIdCategories,
                                1
                            ),
                            'syncdata'
                        );

                        $arrayIdCategories = array_unique($arrayIdCategories);

                        if (!empty($categories) && count($categories)) {
                            $this->debbug(
                                'Updating categories but product has old category values -> ' . print_r(
                                    $categories,
                                    1
                                ) . ' newest after unique array -> ' . print_r($arrayIdCategories, 1),
                                'syncdata'
                            );
                            $array_diff = $this->slArrayDiff($categories, $arrayIdCategories);

                            if ($exist_id_categories && count($array_diff)) {
                                $this->debbug(
                                    'Differences in categories  $diferences-> ' . print_r(
                                        $categories,
                                        1
                                    ) . ' deleting old and setting new categories ->' .
                                    print_r($arrayIdCategories, 1) .
                                    ' diff->' . print_r($array_diff, 1),
                                    'syncdata'
                                );
                                if ($this->sync_categories) {
                                    $productObject->deleteCategories();
                                }
                                // $productObject->updateCategories($arrayIdCategories);
                                $productObject->addToCategories($arrayIdCategories);
                            } else {
                                $this->debbug(
                                    'There is no difference in categories  -> ' . print_r($categories, 1),
                                    'syncdata'
                                );
                            }
                        } else {
                            $this->debbug(
                                'Updating categories but product has no categories. Old categories ->' . print_r(
                                    $categories,
                                    1
                                ) . ' new categories ->' . print_r($arrayIdCategories, 1),
                                'syncdata'
                            );
                            if (count($arrayIdCategories)) {
                                $productObject->addToCategories($arrayIdCategories);
                            // $productObject->updateCategories($arrayIdCategories);
                            } else {
                                if ($this->sync_categories) {
                                    $productObject->deleteCategories();
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. In updating Category tree ' . $occurence . ' ->' . print_r(
                                $e->getMessage(),
                                1
                            ) . ' line->' . print_r($e->getLine(), 1),
                            'syncdata'
                        );
                    }
                }

                $active = true;
                if (isset($product['data']['product_price_tax_incl'])
                    && $product['data']['product_price_tax_incl'] != 0
                    && $product['data']['product_price_tax_incl'] != '') {
                    $productObject->price = $this->priceForamat(
                        (float) str_replace(
                            ',',
                            '.',
                            $product['data']['product_price_tax_incl']
                        ) / (1 + ($productObject->getTaxesRate() / 100))
                    );
                    $this->debbug(
                        ' Product price from product_price_tax_incl ->' .
                        print_r(
                            $product['data']['product_price_tax_incl'],
                            1
                        ),
                        'syncdata'
                    );
                } else {
                    if (isset($product['data']['product_price']) && $product['data']['product_price'] != 0
                        && $product['data']['product_price'] != '') {
                        $productObject->price = $this->priceForamat($product['data']['product_price']);
                        $this->debbug(
                            ' Product_price ->' .
                            print_r(
                                $product['data']['product_price'],
                                1
                            ),
                            'syncdata'
                        );
                    }
                }

                if (isset($product['data']['unity'])) {
                    $productObject->unity       =  $product['data']['unity'];
                }

                if (isset($product['data']['location'])) {
                    $productObject->location    =  $product['data']['location'];
                }


                if (isset($product['data']['product_active']) && $product['data']['product_active'] != '') {
                    $toactivate = $this->slValidateBoolean($product['data']['product_active']);
                    if (Validate::isBool($toactivate)) {
                        $productObject->active =  $toactivate;
                    } else {
                        $this->debbug('## Warning. ' . $occurence .
                                      ' Field Active. Value is not a boolean -> ' .
                                      print_r(
                                          $toactivate,
                                          1
                                      ), 'syncdata');
                    }
                } else {
                    $productObject->active = $active;
                }

                if (isset($product['data']['product_creation_date'])
                    && Validate::isDate(
                        $product['data']['product_creation_date']
                    )
                ) {
                    if ($product['data']['product_creation_date'] != null &&
                        $product['data']['product_creation_date'] != '') {
                        if (is_array($product['data']['product_creation_date'])) {
                            $product['data']['product_creation_date'] =
                                reset($product['data']['product_creation_date']);
                        }
                        if (is_string($product['data']['product_creation_date'])) {
                            $creation_date = strtotime($this->fomatDate($product['data']['product_creation_date']));
                            if (!$creation_date) {
                                $product['data']['product_creation_date'] = $creation_date;
                            }
                        }
                        if (is_numeric($product['data']['product_creation_date']) &&
                            (int)$product['data']['product_creation_date'] ==
                            $product['data']['product_creation_date']) {
                            $date_add = date('Y-m-d H:i:s', $product['data']['product_creation_date']);
                        } else {
                            $date_add = null;
                        }
                        if ($date_add != null) {
                            $productObject->date_add = $date_add;
                        }
                    }
                }

                if (isset($product['data']['product_available_date'])
                ) {
                    if ($product['data']['product_available_date'] != null &&
                       $product['data']['product_available_date'] != '') {
                        if (is_array($product['data']['product_available_date'])) {
                            $product['data']['product_available_date'] =
                                reset($product['data']['product_available_date']);
                        }
                        if (is_string($product['data']['product_available_date'])) {
                            $date_result =
                                strtotime($this->fomatDate($product['data']['product_available_date']));
                            if (!$date_result) {
                                $product['data']['product_available_date'] = $date_result;
                            }
                        }
                        if (is_numeric($product['data']['product_available_date']) &&
                           (int) $product['data']['product_available_date'] ==
                           $product['data']['product_available_date']) {
                            $available_date = date('Y-m-d', $product['data']['product_available_date']);
                        } else {
                            $available_date = '0000-00-00';
                        }
                        if (Validate::isDate($available_date)) {
                            $productObject->available_date = $available_date;
                        }
                    }
                }

                /**
                 * Update carriers
                 */

                if ($update_carriers) {
                    $product_carriers_ids = array_unique($product_carriers_ids);
                    $this->debbug(' Updating carriers -> ' .
                                          print_r(
                                              $product_carriers_ids,
                                              1
                                          ), 'syncdata');
                    $productObject->setCarriers($product_carriers_ids);
                }

                /**
                 * Carrier create warning of carriers
                 */

                if (!empty($carriers_not_founded)) {
                    $this->debbug('## Warning. ' . $occurence .
                                      ' Could not find the transport for the product. Carrier values -> ' .
                                      print_r(
                                          implode(', ', $carriers_not_founded),
                                          1
                                      ), 'syncdata');
                }

                /**
                 * Customizable text field
                 */

                if (!empty($customization_multi_language)) {
                    try {
                        $this->debbug('Entry to process customizable fields. with value -> ' .
                                           print_r(
                                               $customization_multi_language,
                                               1
                                           ), 'syncdata');

                        $max_values = 0;
                        $one_element = '';
                        foreach (array_keys($customization_multi_language) as $id_lang) {
                            $this->debbug('Element is array  set all line as array-> ' .
                                               print_r(
                                                   $customization_multi_language[$id_lang],
                                                   1
                                               ), 'syncdata');
                            if (is_array($customization_multi_language[$id_lang])) {
                                $count_values = count($customization_multi_language[$id_lang]);
                            } else {
                                $count_values = 1;
                            }

                            if ($count_values > $max_values) {
                                $max_values = $count_values;
                                $one_element = $customization_multi_language[$id_lang];
                            }
                        }
                        $this->debbug('one element selected -> ' . print_r(
                            $one_element,
                            1
                        ), 'syncdata');
                        $is_bool_test = $this->slValidateBoolean($one_element);

                        if (!is_array($one_element) && Validate::isBool($is_bool_test)) {
                            $this->debbug('element is a boolean  -> ' . print_r(
                                $is_bool_test,
                                1
                            ), 'syncdata');
                            if ($is_bool_test) {
                                $productObject->customizable     = 1;
                                $productObject->text_fields      = 1;
                                $productObject->uploadable_files = 0;
                            } else {
                                $productObject->customizable     = 0;
                                $productObject->text_fields      = 0;
                                $productObject->uploadable_files = 0;
                            }
                        } else {
                            $productObject->customizable = 1;
                            if (is_array($one_element)) {
                                $this->debbug('element is array one element  -> ' . print_r(
                                    $one_element,
                                    1
                                ), 'syncdata');
                                $number_of_text_fields = 0;
                                $number_of_file_fields = 0;
                                if (count($one_element)) {
                                    foreach ($one_element as $field) {
                                        $this->debbug('procesing field -> ' . print_r(
                                            $field,
                                            1
                                        ), 'syncdata');
                                        if (preg_match('/:/', $field)) {
                                            $field_arr = explode(':', $field);
                                            $field_arr = array_map('trim', $field_arr);
                                        } else {
                                            $field_arr = array($field);
                                        }
                                        $field_arr = array_map('strtolower', $field_arr);
                                        if (in_array('file', $field_arr, false) ||
                                                 in_array('files', $field_arr, false) ||
                                                 in_array('archivo', $field_arr, false)) {
                                            $number_of_file_fields++;
                                        } else {
                                            $number_of_text_fields++;
                                        }
                                    }
                                }
                                $this->debbug('creating filefields -> ' . print_r(
                                    $number_of_file_fields,
                                    1
                                ) . ' Text fields -> ' . print_r($number_of_text_fields, 1), 'syncdata');
                                $productObject->text_fields      = $number_of_text_fields;
                                $productObject->uploadable_files = $number_of_file_fields;
                            } else {
                                $number_of_text_fields = 0;
                                $number_of_file_fields = 0;
                                if (preg_match('/:/', $one_element)) {
                                    $field_arr = explode(':', $one_element);
                                    $field_arr = array_map('trim', $field_arr);
                                } else {
                                    $field_arr = array($one_element);
                                }
                                $field_arr = array_map('strtolower', $field_arr);
                                if (in_array('file', $field_arr, false) ||
                                         in_array('files', $field_arr, false) ||
                                         in_array('archivo', $field_arr, false)) {
                                    $number_of_file_fields++;
                                } else {
                                    $number_of_text_fields++;
                                }
                                $productObject->text_fields      = $number_of_text_fields;
                                $productObject->uploadable_files = $number_of_file_fields;
                            }
                        }
                        if (!$productObject->createLabels(
                            (int) $productObject->uploadable_files,
                            (int) $productObject->text_fields
                        )) {
                            $this->debbug(
                                '## Error. An error occurred while creating customization fields.',
                                'syncdata'
                            );
                        }

                        $productObject->updateLabels();

                        if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                            $deleted_string =  ' AND cf.`is_deleted` = "0"  ';
                        } else {
                            $deleted_string = ' ';
                        }


                        $customization_fields = Db::getInstance()->executeS('SELECT cf.`id_customization_field`,
                                                        cf.`type`, cf.`required`, cfl.`name`, cfl.`id_lang`
                                                        FROM `' . _DB_PREFIX_ . 'customization_field` cf
                                                        NATURAL JOIN `' . _DB_PREFIX_ . 'customization_field_lang` cfl 
                                                        WHERE cf.`id_product` = ' . $productObject->id .
                                                        ' AND cfl.`id_shop` = ' .  $shop_id .
                                                                            $deleted_string .
                                                        ' ORDER BY cf.`id_customization_field`');

                        $this->debbug(
                            'Values of custom fields from BD ->' . print_r($customization_fields, 1),
                            'syncdata'
                        );
                        if (count($customization_fields)) {
                            $count_fields = null;
                            $last_field = 0;

                            foreach ($customization_fields as $key_number => $value_field) {
                                $this->debbug(
                                    'before check  if is different id as is saved $last_field->' .
                                                   print_r($last_field, 1) . '  id in variable ->  ' .
                                                   print_r($value_field['id_customization_field'], 1),
                                    'syncdata'
                                );
                                if ($last_field != $value_field['id_customization_field']) {
                                    $required = 0;
                                    $type = 1;
                                    $one_line = '';
                                    if ($count_fields === null) {
                                        $count_fields = 0;
                                    } else {
                                        $count_fields++;
                                    }
                                    $this->debbug(
                                        'Up number of key $count_fields->' .
                                                       print_r($count_fields, 1),
                                        'syncdata'
                                    );
                                    $last_field = $value_field['id_customization_field'];
                                }
                                $this->debbug(
                                    'fields for process from bd $key_number>' .
                                                   print_r($key_number, 1) . '  $value_field-> ' .
                                                   print_r($value_field, 1) .
                                                   ' values in this position ->' .
                                                   print_r((isset($customization_multi_language[$value_field['id_lang']]
                                                       [$count_fields]) ?
                                                       $customization_multi_language[$value_field['id_lang']]
                                                       [$count_fields] : 'empty'), 1),
                                    'syncdata'
                                );

                                $name_for_save = '';
                                $boleantest = reset($customization_multi_language);

                                if ($boleantest === true) {
                                    $this->debbug(
                                        'Set empty name but value is boolean in true->' .
                                                       print_r($id_lang, 1),
                                        'syncdata'
                                    );
                                    $one_line = '';
                                } elseif ($boleantest === false) {
                                    $this->debbug(
                                        'Set empty name but value is boolean to false,' .
                                                       ' delete all fields ->' . print_r($id_lang, 1),
                                        'syncdata'
                                    );
                                    $one_line = '';

                                    $productObject->customizable = 0;
                                    $productObject->text_fields = 0;
                                    $productObject->uploadable_files = 0;
                                    $productObject->updateLabels();
                                    break;
                                } else {
                                    if (isset($customization_multi_language[$value_field['id_lang']]) &&
                                           is_array($customization_multi_language[$value_field['id_lang']])) {
                                        if (isset($customization_multi_language[$value_field['id_lang']][$count_fields])
                                               &&
                                            !empty($customization_multi_language[$value_field['id_lang']]
                                            [$count_fields])) {
                                            $one_line = $customization_multi_language[$value_field['id_lang']]
                                            [$count_fields];
                                            $this->debbug(
                                                'Set custom name from multi-language->' .
                                                               print_r($one_line, 1) . '  id_lang-> ' .
                                                               print_r($value_field['id_lang'], 1),
                                                'syncdata'
                                            );
                                        } elseif (isset($customization_multi_language[$defaultLenguage][$count_fields])
                                                    && !empty($customization_multi_language[$defaultLenguage]
                                            [$count_fields])) {
                                            $one_line = $customization_multi_language[$defaultLenguage][$count_fields];
                                            $this->debbug(
                                                'Set custom name from default-language->' .
                                                               print_r($one_line, 1) . '  id_lang-> ' .
                                                               print_r($value_field['id_lang'], 1),
                                                'syncdata'
                                            );
                                        } else {
                                            foreach ($customization_multi_language as $id_lang => $line) {
                                                $this->debbug(
                                                    'Search any value for set-language->' .
                                                                   print_r($id_lang, 1) . ' id_lang-> ' .
                                                                   print_r($line, 1),
                                                    'syncdata'
                                                );
                                                if (isset($customization_multi_language[$id_lang][$count_fields]) &&
                                                       !empty($customization_multi_language[$id_lang][$count_fields])) {
                                                    $one_line = $customization_multi_language[$id_lang][$count_fields];
                                                }
                                            }
                                        }
                                    } else {
                                        if (isset($customization_multi_language[$value_field['id_lang']])) {
                                            $one_line = $customization_multi_language[$value_field['id_lang']];
                                            $this->debbug(
                                                'set entire line ->' . print_r($one_line, 1) .
                                                               '  id_lang-> ' . print_r($value_field['id_lang'], 1),
                                                'syncdata'
                                            );
                                        } elseif (isset($customization_multi_language[$defaultLenguage][$count_fields])
                                                  &&
                                                 !empty($customization_multi_language[$defaultLenguage]
                                                 [$count_fields])) {
                                            $one_line = $customization_multi_language[$defaultLenguage][$count_fields];
                                            $this->debbug(
                                                'Set custom name from default-language->' .
                                                               print_r($one_line, 1) . '  id_lang-> ' .
                                                               print_r($value_field['id_lang'], 1),
                                                'syncdata'
                                            );
                                        } else {
                                            foreach ($customization_multi_language as $id_lang => $line) {
                                                $this->debbug(
                                                    'Search any value for set-language->' .
                                                                   print_r($id_lang, 1) . '  id_lang-> ' .
                                                                   print_r($line, 1),
                                                    'syncdata'
                                                );
                                                if (isset($customization_multi_language[$id_lang][$count_fields]) &&
                                                       !empty($customization_multi_language[$id_lang][$count_fields])) {
                                                    $one_line = $customization_multi_language[$id_lang][$count_fields];
                                                }
                                            }
                                        }
                                    }
                                }

                                if (is_array($one_line) &&
                                        count($one_line)) {
                                    if (isset($one_line[$key_number]) &&
                                            !empty($one_line[$key_number])) {
                                        $name_for_save = $one_line[$key_number];
                                        if (preg_match('/:/', $name_for_save)) {
                                            $field_arr     = explode(':', $name_for_save);
                                            $field_arr = array_map('trim', $field_arr);
                                            $this->debbug(
                                                'test before test array $field_arr->' .
                                                print_r($field_arr, 1),
                                                'syncdata'
                                            );
                                            $name_for_save = $field_arr[0];
                                            $field_arr = array_map('strtolower', $field_arr);
                                            if (in_array('file', $field_arr, false) ||
                                                         in_array('files', $field_arr, false) ||
                                                         in_array('archivo', $field_arr, false)) {
                                                $this->debbug(
                                                    'command file in array->' .
                                                                       print_r($field_arr, 1),
                                                    'syncdata'
                                                );
                                                $type = 0;
                                            }
                                            if (in_array('required', $field_arr, false) ||
                                                         in_array('require', $field_arr, false)||
                                                         in_array('requerido', $field_arr, false)) {
                                                $this->debbug(
                                                    'command required in array->' .
                                                                       print_r($field_arr, 1),
                                                    'syncdata'
                                                );
                                                $required = 1;
                                            }
                                        }
                                    }
                                } else {
                                    $this->debbug(
                                        'Entry as string for save->' . print_r($one_line, 1),
                                        'syncdata'
                                    );
                                    if (preg_match('/,/', $one_line)) {
                                        $name_for_save_arr = explode(
                                            ',',
                                            $one_line
                                        );
                                        if (!empty($name_for_save_arr[$key_number])) {
                                            $name_for_save = $name_for_save_arr[$key_number];
                                            if (preg_match('/:/', $name_for_save)) {
                                                $field_arr     = explode(':', $name_for_save);
                                                $field_arr = array_map('trim', $field_arr);
                                                $this->debbug(
                                                    'test before test array $field_arr->' .
                                                                   print_r($field_arr, 1),
                                                    'syncdata'
                                                );
                                                $name_for_save = $field_arr[0];
                                                $field_arr = array_map('strtolower', $field_arr);
                                                if (in_array('file', $field_arr, false) ||
                                                         in_array('files', $field_arr, false) ||
                                                         in_array('archivo', $field_arr, false)) {
                                                    $this->debbug(
                                                        'command file in array->' .
                                                                       print_r($field_arr, 1),
                                                        'syncdata'
                                                    );
                                                    $type = 0;
                                                }
                                                if (in_array('required', $field_arr, false) ||
                                                         in_array('require', $field_arr, false) ||
                                                         in_array('requerido', $field_arr, false)) {
                                                    $this->debbug(
                                                        'command required in array->' .
                                                                       print_r($field_arr, 1),
                                                        'syncdata'
                                                    );
                                                    $required = 1;
                                                }
                                            }
                                        }
                                    } else {
                                        if (preg_match('/:/', $one_line)) {
                                            $field_arr     = explode(':', $one_line);
                                            $field_arr = array_map('trim', $field_arr);
                                            $this->debbug(
                                                'test before test array $field_arr->' .
                                                               print_r($field_arr, 1),
                                                'syncdata'
                                            );
                                            $name_for_save = $field_arr[0];
                                            $field_arr = array_map('strtolower', $field_arr);
                                            if (in_array('file', $field_arr, false) ||
                                                     in_array('files', $field_arr, false) ||
                                                     in_array('archivo', $field_arr, false)) {
                                                $this->debbug(
                                                    'command file in array->' .
                                                                   print_r($field_arr, 1),
                                                    'syncdata'
                                                );
                                                $type = 0;
                                            }
                                            if (in_array('required', $field_arr, false) ||
                                                     in_array('require', $field_arr, false) ||
                                                     in_array('requerido', $field_arr, false)) {
                                                $this->debbug(
                                                    'command required in array->' .
                                                                   print_r($field_arr, 1),
                                                    'syncdata'
                                                );
                                                $required = 1;
                                            }
                                        } else {
                                            $name_for_save = $one_line;
                                        }
                                    }
                                }

                                if ($value_field['type'] != $type || $required != $value_field['required']) {
                                    $this->debbug(
                                        'update type of field to  $type->' .
                                                       print_r($type, 1)
                                                       . ' required ->' . print_r(
                                                           $required,
                                                           1
                                                       ),
                                        'syncdata'
                                    );
                                    $query = 'UPDATE `' . _DB_PREFIX_ . 'customization_field`
                                                SET ' .
                                                 ($value_field['type'] != $type ? '`type` = "' . $type . '" ':'') .
                                                 ($value_field['type'] != $type &&
                                                  $required != $value_field['required']? ',':'') .
                                                 ($required != $value_field['required']?
                                                     ' `required` = "' . $required . '" ':'') .
                                                'WHERE `id_customization_field` = ' .
                                             (int) $value_field['id_customization_field'];
                                    if (!Db::getInstance()->execute($query)) {
                                        $this->debbug(
                                            '## Warning. it was not possible to change the ' .
                                                           'type of custom field. $customization_fields->' .
                                                           print_r(
                                                               $customization_fields,
                                                               1
                                                           ) . ' in $type ->' . print_r(
                                                               $type,
                                                               1
                                                           ) . ' required ->' . print_r(
                                                               $required,
                                                               1
                                                           ) . ' $query ->' . print_r(
                                                               $query,
                                                               1
                                                           ),
                                            'syncdata'
                                        );
                                    }
                                }
                                $this->debbug('Proccesing with value -> ' . print_r(
                                    $value_field['name'],
                                    1
                                ) . ' and id -> ' . $value_field['id_customization_field'] .
                                                   ' and save this-> ' . print_r(
                                                       $name_for_save,
                                                       1
                                                   ), 'syncdata');
                                $query = 'UPDATE `' . _DB_PREFIX_ . 'customization_field_lang`
                                            SET `name` = "' . pSQL(trim($name_for_save)) . '" ' .
                                            'WHERE `id_customization_field` = "' .
                                         (int) $value_field['id_customization_field'] . '"' .
                                            ' AND `id_shop` = "' . (int) $shop_id . '" ' .
                                            ' AND `id_lang` = "' . (int) $value_field['id_lang'] . '" ';

                                if (!Db::getInstance()->execute($query)) {
                                    $this->debbug(
                                        '## Warning. actual creating customization fields. ' .
                                                       '$customization_fields->' . print_r(
                                                           $customization_fields,
                                                           1
                                                       ) . ' in language ->' . print_r(
                                                           $value_field['id_lang'],
                                                           1
                                                       ) . ' $query ->' . print_r($query, 1),
                                        'syncdata'
                                    );
                                }
                            }
                        }

                        if (!ObjectModel::updateMultishopTable(
                            'product',
                            array( 'customizable' => 2 ),
                            'a.id_product = ' . (int) $productObject->id
                        )) {
                            $this->debbug(
                                '## Warning. the multi-shop table could not be corrected ',
                                'syncdata'
                            );
                        }
                    } catch (Exception $e) {
                        $this->debbug('## Error. ' . $occurence . ' Some problems have been detected in ' .
                                           'custom field creation ->' . $e->getMessage() . ' and line->' .
                                           print_r($e->getLine(), 1) .
                                           ' and track ->' . print_r($e->getTrace(), 1), 'syncdata');
                    }
                } else {
                    // corregir cuando se agrega campo file
                    if ($productObject->customizable == 1) {
                        $this->debbug('Deleting custom fields', 'syncdata');
                        $productObject->customizable = 0;
                        $productObject->text_fields = 0;
                        $productObject->uploadable_files = 0;
                        $productObject->updateLabels();
                    }
                }

                $this->debbug(
                    ' Price before save ->' . print_r($productObject->price, 1),
                    'syncdata'
                );
                $test_after_update['price'] = $productObject->price;
                // $productObject->price = $price;
                // $price = abs($price);




                if (isset($product['data']['wholesale_price']) && $product['data']['wholesale_price'] != null) {
                    $price = abs($product['data']['wholesale_price']);
                    if (Validate::isPrice($price)) {
                        $productObject->wholesale_price = $price;
                    } else {
                        $this->debbug(
                            '## Warning. ' . $occurence . ' wholesale_price not is a valid price ->' .
                            print_r($price, 1),
                            'syncdata'
                        );
                    }
                }

                if (isset($product['data']['unit_price_ratio'])) {
                    $unit_price_ratio =  $this->priceForamat($product['data']['unit_price_ratio']);
                    $unit_price_ratio = abs($unit_price_ratio);
                    if (Validate::isPrice($unit_price_ratio)) {
                        $this->debbug(
                            ' unit_price_ratio -> ' . print_r(
                                $unit_price_ratio,
                                1
                            ),
                            'syncdata'
                        );
                        $productObject->unit_price_ratio = $unit_price_ratio;

                        $productObject->unit_price =
                            ($productObject->unit_price_ratio != 0 ?
                                $productObject->price / $productObject->unit_price_ratio : 0);
                    } else {
                        $this->debbug(
                            '## Warning. ' . $occurence . ' unit_price_ratio not is a valid price ->' .
                            print_r($unit_price_ratio, 1),
                            'syncdata'
                        );
                    }
                }

                if (isset($product['data']['product_upc'])) {
                    $productObject->upc = (Tools::strlen(
                        $product['data']['product_upc']
                    ) < 13 && Validate::isUpc(
                        $product['data']['product_upc']
                    )) ? $product['data']['product_upc'] : '';
                }

                if (isset($product['data']['product_ean13'])) {
                    $productObject->ean13 = (Tools::strlen(
                        $product['data']['product_ean13']
                    ) < 14 && Validate::isEan13(
                        $product['data']['product_ean13']
                    )) ? $product['data']['product_ean13'] : '';
                }

                isset($product['data']['product_reference']) ? $product_reference = $this->slValidateReference(
                    $product['data']['product_reference']
                ) : $product_reference = '';

                if (Tools::strlen($product_reference) > 32) {
                    $product_reference = Tools::substr($product_reference, 0, 31);
                }
                $productObject->reference = $product_reference;

                if ($avoid_stock_update || $is_new_product) {
                    if (isset($product['data']['product_quantity']) && $product['data']['product_quantity'] != 0) {
                        $this->debbug(
                            'quantity for save->' . $product['data']['product_quantity'] . ' to shop ->' .
                            $shop_id,
                            'syncdata'
                        );
                        try {
                            StockAvailable::setQuantity(
                                $productObject->id,
                                0,
                                $product['data']['product_quantity'],
                                $shop_id
                            );
                        } catch (Exception $e) {
                            $this->debbug(
                                '## Error. ' . $occurence . ' Set new stock for product ID:'
                                . $product['ID'] . ' ->' . print_r(
                                    $e->getMessage(),
                                    1
                                ),
                                'syncdata'
                            );
                        }
                    }
                } else {
                    $this->debbug(
                        'Overwrite stock status is set to deny->' . $avoid_stock_update .
                        '. The stock will not be updated',
                        'syncdata'
                    );
                }

                if (isset($product['data']['product_out_of_stock'])) {
                    $out_of_stock = '';
                    if (is_array(
                        $product['data']['product_out_of_stock']
                    )
                        && !empty($product['data']['product_out_of_stock'])
                    ) {
                        $out_of_stock = reset($product['data']['product_out_of_stock']);
                    } else {
                        if (!is_array(
                            $product['data']['product_out_of_stock']
                        )
                            && $product['data']['product_out_of_stock'] != ''
                        ) {
                            $out_of_stock = $product['data']['product_out_of_stock'];
                        }
                    }

                    if ($out_of_stock != '') {
                        $out_of_stock_val = -1;
                        if (!is_numeric($out_of_stock)) {
                            if (preg_match('/(denegar|deny|false)/i', $out_of_stock)) {
                                $out_of_stock_val = 0;
                            }

                            if (preg_match('/(permitir|allow|true)/i', $out_of_stock)) {
                                $out_of_stock_val = 1;
                            }

                            if (preg_match('/(defecto|default)/i', $out_of_stock)) {
                                $out_of_stock_val = 2;
                            }
                        } else {
                            $out_of_stock_val = $out_of_stock;
                        }

                        if (in_array($out_of_stock_val, array(0, 1, 2), false)) {
                            try {
                                $existing_out_of_stock = StockAvailable::outOfStock($productObject->id, $shop_id);
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. ' . $occurence . ' get product_out_of_stock for product ID:'
                                    . $product['ID'] . ' ->' . print_r(
                                        $e->getMessage(),
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                            if ($out_of_stock_val != $existing_out_of_stock) {
                                try {
                                    StockAvailable::setProductOutOfStock(
                                        $productObject->id,
                                        $out_of_stock_val,
                                        $shop_id
                                    );
                                } catch (Exception $e) {
                                    $this->debbug(
                                        '## Error. ' . $occurence .
                                        ' Set product_out_of_stock for product ID:' .
                                        $product['ID'] . ' ->' . print_r(
                                            $e->getMessage(),
                                            1
                                        ),
                                        'syncdata'
                                    );
                                }
                            }
                        } else {
                            $this->debbug(
                                '## Warning. ' . $occurence . ' product_out_of_stock ->' .
                                print_r($out_of_stock, 1) .
                                'Value has not been recognized. Please use:' .
                                ' denegar|deny|false|permitir|allow|true|defecto|default|0|1|2 ',
                                'syncdata'
                            );
                        }
                    }
                }

                if (isset($product['data']['low_stock_threshold'])) {
                    if (!empty($product['data']['low_stock_threshold'])) {
                        $productObject->low_stock_threshold = (int) $product['data']['low_stock_threshold'];
                    } else {
                        $productObject->low_stock_threshold = null;
                    }
                }


                if (isset($product['data']['product_accessories'])) {
                    if (is_array(
                        $product['data']['product_accessories']
                    )
                        && !empty($product['data']['product_accessories'])
                    ) {
                        $additional_output['product_psid'] = $productObject->id;
                        $additional_output['product_accessories'] = $product['data']['product_accessories'];
                        $this->debbug(
                            'is accessories as array->' .
                            print_r($product['data']['product_accessories'], 1),
                            'syncdata'
                        );
                        unset($product['data']['product_accessories']);
                    } else {
                        if (!is_array(
                            $product['data']['product_accessories']
                        )
                            && $product['data']['product_accessories'] != ''
                        ) {
                            $additional_output['product_psid'] = $productObject->id;
                            $additional_output['product_accessories'] = explode(
                                ',',
                                $product['data']['product_accessories']
                            );
                            $this->debbug(
                                'is accessories as string ->' .
                                print_r($product['data']['product_accessories'], 1),
                                'syncdata'
                            );
                        }
                    }
                }


                $productObject->date_upd = date('Y-m-d H:i:s');
                $fieldsTransport = array(
                    'product_width' => 'width',
                    'product_height' => 'height',
                    'product_depth' => 'depth',
                    'product_weight' => 'weight',
                    'additional_shipping_cost' => 'additional_shipping_cost',
                );

                foreach ($fieldsTransport as $fieldSales => $fieldPresta) {
                    if (isset($product['data'][$fieldSales])) {
                        $value = (float) str_replace(',', '.', $product['data'][$fieldSales]);
                        $productObject->{"$fieldPresta"} = $value;
                        unset($product['data'][$fieldSales]);
                    }
                }


                $fieldsOptions = array(
                    'product_available_for_order' => 'available_for_order',
                    'product_show_price' => 'show_price',
                    'product_available_online_only' => 'online_only',
                );
                foreach ($fieldsOptions as $fieldSales => $fieldPresta) {
                    if (isset($product['data'][$fieldSales]) && $product['data'][$fieldSales] !== '') {
                        $validatebool =  $this->slValidateBoolean(
                            $product['data'][$fieldSales]
                        );
                        if (Validate::isBool($validatebool)) {
                            $productObject->{"$fieldPresta"} = $validatebool;
                        } else {
                            $this->debbug(
                                '## Warning. ' . $occurence . ' field-> ' . $fieldSales .
                                ' Invalid boolean value -> ' .
                                print_r(
                                    $product['data'][$fieldSales],
                                    1
                                ),
                                'syncdata'
                            );
                        }
                        unset($product['data'][$fieldSales]);
                    }
                }

                if (isset($product['data']['product_manufacturer'])) {
                    $product_manufacturer = '';

                    if (is_array(
                        $product['data']['product_manufacturer']
                    )
                        && !empty($product['data']['product_manufacturer'])
                    ) {
                        $product_manufacturer = reset($product['data']['product_manufacturer']);
                    } else {
                        if (!is_array(
                            $product['data']['product_manufacturer']
                        )
                            && $product['data']['product_manufacturer'] != ''
                        ) {
                            $product_manufacturer = $product['data']['product_manufacturer'];
                        }
                    }

                    if ($product_manufacturer != '') {
                        $id_manufacturer = 0;

                        if (is_numeric($product_manufacturer)) {
                            $this->debbug(
                                ' is numeric test if name of manufacturer is a id real-> ' .
                                print_r(
                                    $product_manufacturer,
                                    1
                                ),
                                'syncdata'
                            );

                            if (Manufacturer::manufacturerExists($product_manufacturer)) {
                                $id_manufacturer = $product_manufacturer;
                                $this->debbug(
                                    $occurence . ' selecting id as id manufacturer-> ' .
                                    print_r(
                                        $product_manufacturer,
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                        }

                        if ($id_manufacturer == 0) {
                            try {
                                $id_manufacturer = Manufacturer::getIdByName($product_manufacturer);
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. ' . $occurence . ' get id by name-> ' .
                                    print_r(
                                        $product_manufacturer,
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                        }


                        if ($id_manufacturer != 0) {
                            /**
                             * Manufacturer Found
                             */

                            $manufacturer = new Manufacturer($id_manufacturer);

                            $productObject->id_manufacturer = $manufacturer->id;
                            unset($manufacturer);
                        } else {
                            /**
                             * Create Manufacturer if not exist
                             */
                            $manufacturer = new Manufacturer();

                            $manufacturer->name           = $product_manufacturer;

                            if (!isset($manufacturer->meta_title) || empty($manufacturer->meta_title)) {
                                $manufacturer->meta_title = $product_manufacturer;
                            }

                            $manufacturer->active = 1;
                            $manufacturer->link_rewrite = Tools::link_rewrite($product_manufacturer);

                            try {
                                $manufacturer->add();
                                $productObject->id_manufacturer = $manufacturer->id;

                                $this->debbug(
                                    'Created new Manufacturer correctly -> ' .
                                    $product_manufacturer . ' with id -> ' . print_r(
                                        $manufacturer->id,
                                        1
                                    ),
                                    'syncdata'
                                );
                                unset($manufacturer);
                            } catch (Exception $e) {
                                $syncCat = false;
                                $this->debbug(
                                    '## Error. ' . $occurence . ' In saving new Manufacturer ' . print_r(
                                        $e->getMessage(),
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                        }
                    }
                }

                $array_supplier = preg_grep('/product_supplier_\+?\d+$/i', array_keys($product['data']));

                if (!empty($array_supplier)) {
                    $default_founded = array();
                    $current_supplier_collection = ProductSupplier::getSupplierCollection(
                        $productObject->id,
                        false
                    );

                    $processed_suppliers = array();

                    foreach ($array_supplier as $supplier_field) {
                        $supplier_name = '';
                        $default_supplier = false;
                        if (is_array(
                            $product['data'][$supplier_field]
                        ) && !empty($product['data'][$supplier_field])) {
                            $supplier_name = reset($product['data'][$supplier_field]);
                        } else {
                            if (!is_array(
                                $product['data'][$supplier_field]
                            )
                                && $product['data'][$supplier_field] != ''
                            ) {
                                $supplier_name = $product['data'][$supplier_field];
                            }
                        }
                        if (preg_match('/:default/', $supplier_name)) {
                            $supplier_name = trim(str_replace(':default', '', $supplier_name));
                            $default_supplier = true;
                        }

                        if ($supplier_name != '' && $supplier_name != null) {
                            $number_field = str_replace('product_supplier_', '', $supplier_field);

                            if ($number_field) {
                                $supplier = new Supplier();

                                $id_supplier = 0;
                                try {
                                    if (is_numeric($supplier_name)) {
                                        $supplier_exists = $supplier->supplierExists($supplier_name);
                                        if ($supplier_exists) {
                                            $id_supplier = $supplier_name;
                                        } else {
                                            $supplier_exists = $supplier->getIdByName($supplier_name);
                                        }
                                        if ($supplier_exists) {
                                            $id_supplier = $supplier_exists;
                                        }
                                    } else {
                                        $supplier_exists = $supplier->getIdByName(
                                            $supplier_name
                                        );
                                        if ($supplier_exists) {
                                            $id_supplier = $supplier_exists;
                                        } else {
                                            $supplier_exists = $supplier->getIdByName(
                                                Tools::strtolower(str_replace('_', ' ', $supplier_name))
                                            );
                                            if ($supplier_exists) {
                                                $id_supplier = $supplier_exists;
                                            } else {
                                                $supplier_exists = $supplier->getIdByName(
                                                    Tools::strtolower(str_replace(' ', '_', $supplier_name))
                                                );
                                                if ($supplier_exists) {
                                                    $id_supplier = $supplier_exists;
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    $this->debbug(
                                        '## Error. ' . $occurence . ': supplier management ->' .
                                            print_r($e->getMessage(), 1) .
                                            'line->' . $e->getLine(),
                                        'syncdata'
                                    );
                                }
                                if ($id_supplier == 0) {
                                    try {
                                        $supplier             = new Supplier();
                                        $supplier->name       = $supplier_name;
                                        $supplier->active     = 1;
                                        $supplier->add();
                                        $id_supplier =   $supplier->id;
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. ' . $occurence . ': in create new  supplier ->' .
                                                print_r($e->getMessage(), 1) .
                                                'line->' . $e->getLine(),
                                            'syncdata'
                                        );
                                    }
                                }

                                if ($id_supplier != 0) {
                                    if ($default_supplier) {
                                        $default_founded[] = $id_supplier;
                                    }
                                    try {
                                        $product_supplier_reference_index = 'product_supplier_reference_'
                                                                            . $number_field;
                                        if (isset($product['data'][$product_supplier_reference_index])) {
                                            if ($product['data'][$product_supplier_reference_index] != '') {
                                                $supplier_reference =
                                                    $product['data'][$product_supplier_reference_index];
                                                $productObject->addSupplierReference(
                                                    $id_supplier,
                                                    0,
                                                    $supplier_reference
                                                );
                                            }
                                        } else {
                                            /* $this->debbug(
                                                 ' ## Warning. Supplier reference is empty management ->' .
                                                     print_r($supplier_name, 1),
                                                 'syncdata'
                                             );*/
                                        }
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. ' . $occurence . ': in add supplier Reference ->' .
                                                print_r($e->getMessage(), 1) .
                                                'line->' . $e->getLine(),
                                            'syncdata'
                                        );
                                    }
                                    $processed_suppliers[$id_supplier] = 0;
                                } else {
                                    $this->debbug(
                                        ' ## Warning. An problem detected in supplier management ->' .
                                            print_r($id_supplier, 1),
                                        'syncdata'
                                    );
                                }
                            }
                        }
                    }

                    foreach ($current_supplier_collection as $current_supplier_item) {
                        if ($current_supplier_item->id_product_attribute == 0
                            && !isset($processed_suppliers[$current_supplier_item->id_supplier])) {
                            $this->debbug(
                                ' Delete old suppliers ->' .
                                print_r($current_supplier_item->id_supplier, 1),
                                'syncdata'
                            );
                            $current_supplier_item->delete();
                        }
                    }
                    //set default supplier
                    if (count($default_founded)) {
                        $this->debbug(
                            'Set default suppliers ->' .
                            print_r(reset($default_founded), 1),
                            'syncdata'
                        );
                        $productObject->id_supplier = reset($default_founded);
                    } else {
                        if (count($processed_suppliers)) {
                            $this->debbug(
                                'Set default from first supplier ->' .
                                print_r(key($processed_suppliers), 1),
                                'syncdata'
                            );
                            $productObject->id_supplier = key($processed_suppliers);
                        } else {
                            $this->debbug(
                                'Set 0 as default supplier ->' .
                                print_r(key($processed_suppliers), 1),
                                'syncdata'
                            );
                            $productObject->id_supplier = 0;
                        }
                    }
                }
                $product_id = null;

                if (isset($productObject->low_stock_alert) && $productObject->low_stock_alert == null) {
                    $productObject->low_stock_alert = false;
                }


                try {
                    $productObject->save();
                    $product_id = $productObject->id;


                    if (isset($product['data']['estimacion']) && is_numeric($product['data']['estimacion'])) {
                        $check_column = Db::getInstance()->executeS(
                            sprintf(
                                'SELECT * FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = "' . _DB_NAME_ . '" AND
TABLE_NAME = "' . $this->product_table . '" AND COLUMN_NAME = "estimacion"'
                            )
                        );

                        if (!empty($check_column)) {
                            Db::getInstance()->execute(
                                sprintf(
                                    'UPDATE ' . $this->product_table . ' SET estimacion = "%s" WHERE id_product = "%s"',
                                    $product['data']['estimacion'],
                                    $product_id
                                )
                            );
                        } else {
                            $this->debbug('## Error. ' . $occurence . ' Estimacion column does not exist! ');
                        }
                        unset($product['data']['estimacion']);
                    }
                } catch (Exception $e) {
                    $syncCat = true;
                    $this->debbug(
                        '## Error. ' . $occurence .
                        ' Saving changes to product problem ->' . print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }
                /**
                 * Test after update
                 */
                if (count($test_after_update) && Validate::isLoadedObject($productObject) && $productObject->id) {
                    $after_test_success = true;
                    foreach ($test_after_update as $key_of_data => $test_value) {
                        if (is_array($test_value)) {
                            foreach (array_keys($test_value) as $key_for_test_array) {
                                if ($productObject->{"$key_of_data"}[$key_for_test_array] !=
                                    $test_value[$key_for_test_array]) {
                                    $after_test_success = false;
                                    $this->debbug('## Warning. ' . $occurence .
                                                  ' It has been detected that the value ' .
                                                  'you tried to save is not saved correctly.' .
                                                  ' data saved as ' . $key_of_data . ' key ->' .
                                                  $key_for_test_array . ' ->' .
                                                  print_r($productObject->{"$key_of_data"}[$key_for_test_array], 1) .
                                                  'data for save ->' .
                                                  print_r($test_value[$key_for_test_array], 1), 'syncdata');
                                }
                            }
                        } else {
                            if ($productObject->{"$key_of_data"} != $test_value) {
                                $after_test_success = false;
                                $this->debbug('## Warning. ' . $occurence .
                                              ' It has been detected that the value ' .
                                              'you tried to save is not saved correctly.' .
                                              ' data saved as ' . $key_of_data . ' ->' .
                                              print_r($productObject->{"$key_of_data"}, 1) .
                                              'data for save ->' . print_r($test_value, 1), 'syncdata');
                            }
                        }
                    }
                    if ($after_test_success) {
                        $this->debbug('The imported fields have been tested and if your information matches:'
                            . implode(', ', array_keys($test_after_update)) .
                            ' result: success', 'syncdata');
                    }
                }
                $this->debbug('Before run indexation', 'syncdata');
                $microtime = microtime(1);
                Search::indexation(false, $product_id);
                $this->debbug('After run indexation time->' . print_r(
                    (microtime(1) - $microtime),
                    1
                ), 'syncdata');

                unset($productObject);


                $isset_Product_discount_1 = isset($product['data']['product_discount_1']);
                $isset_Product_discount_2 = isset($product['data']['product_discount_2']);


                if ($isset_Product_discount_1 || $isset_Product_discount_2) {
                    $product_discount_1_data = array();
                    $context_store =  $shop_id;

                    /**
                     * Check special price type for check Context store
                     */


                    if (isset($product['data']['product_discount_1_type'])) {
                        $product_discount_1_type = '';

                        if (is_array(
                            $product['data']['product_discount_1_type']
                        )
                            && !empty($product['data']['product_discount_1_type'])
                        ) {
                            $product_discount_1_type = Tools::strtolower(
                                trim(reset($product['data']['product_discount_1_type']))
                            );
                        } else {
                            if (!is_array(
                                $product['data']['product_discount_1_type']
                            )
                                && $product['data']['product_discount_1_type'] != ''
                            ) {
                                $product_discount_1_type = Tools::strtolower(
                                    trim($product['data']['product_discount_1_type'])
                                );
                            }
                        }
                        unset($product['data']['product_discount_1_type']);
                        if ($product_discount_1_type != '') {
                            $this->debbug('Data type discount 1->' .
                                          print_r($product_discount_1_type, 1), 'syncdata');
                            if (preg_match('/:(all|global)/i', $product_discount_1_type)) {
                                $clear_data  = str_replace(
                                    array( ':all', ':global' , ':GLOBAL' , ':ALL' ),
                                    '',
                                    $product_discount_1_type
                                );
                                $this->debbug('cleared value fom global shops parameter->' .
                                              print_r($clear_data, 1), 'syncdata');
                                $product_discount_1_type = trim($clear_data);
                                $context_store =  0;
                            }

                            if (in_array(
                                $product_discount_1_type,
                                array('%', 'porcentaje', 'percentage')
                            )) {
                                $this->debbug('Data type discount 1 is a percentage->' .
                                              print_r($product_discount_1_type, 1), 'syncdata');
                                $product_discount_1_data['type_reduction'] = 'percentage';
                            }

                            if (in_array(
                                $product_discount_1_type,
                                array('$', 'euro', '€', 'dollar', 'amount', 'importe')
                            )
                            ) {
                                $this->debbug('Data type discount 1 is a amount->' .
                                              print_r($product_discount_1_type, 1), 'syncdata');
                                $product_discount_1_data['type_reduction'] = 'amount';
                            }
                        }
                    }

                    /**
                     * Check parameters for first special price
                     */

                    if ($isset_Product_discount_1 && $product['data']['product_discount_1'] != '') {
                        $product_discount_1_value = $this->discauntFormat($product['data']['product_discount_1']);
                        $product_discount_1_data = array(
                            'reduction' => $product_discount_1_value,
                            'type_reduction' => (isset($product_discount_1_data['type_reduction']) ?
                                $product_discount_1_data['type_reduction'] : 'amount'),
                            'from_quantity' => 1,
                            'from_time' => '0000-00-00 00:00:00',
                            'to_time' => '0000-00-00 00:00:00'
                        );
                        if (is_numeric($product_discount_1_value) && $product_discount_1_value > 0) {
                            if (isset($product['data']['product_discount_1_quantity'])
                                && $product['data']['product_discount_1_quantity'] != ''
                                && is_numeric(
                                    $product['data']['product_discount_1_quantity']
                                )
                            ) {
                                $product_discount_1_data['from_quantity'] =
                                    $product['data']['product_discount_1_quantity'];

                                if ($product_discount_1_data['from_quantity'] == 0) {
                                    $product_discount_1_data['from_quantity'] = 1;
                                }
                                unset($product['data']['product_discount_1_quantity']);
                            } else {
                                $product_discount_1_data['from_quantity'] = 1;
                            }
                            $this->debbug('Data prepared for discount 1->' .
                                          print_r($product_discount_1_data, 1)
                                          . ' and id of shop for edit->' . print_r($shop_id, 1), 'syncdata');
                        }
                        /**
                         * check time from discount
                         */
                        if (isset($product['data']['product_discount_1_from'])) {
                            if (!empty($product['data']['product_discount_1_from'])) {
                                if (is_array($product['data']['product_discount_1_from'])) {
                                    $product['data']['product_discount_1_from'] =
                                        reset($product['data']['product_discount_1_from']);
                                }
                                $fromtime = strtotime($this->fomatDate($product['data']['product_discount_1_from']));
                                if ($fromtime) {
                                    $product_discount_1_data['from_time'] = date('Y-m-d H:i:s', $fromtime);
                                } else {
                                    $this->debbug('## Warning. product_discount_1_from date not recognized ->' .
                                                  print_r($product['data']['product_discount_1_from'], 1), 'syncdata');
                                }
                            }
                        }
                        unset($product['data']['product_discount_1_from']);

                        /**
                         * check time to discount
                         */
                        if (isset($product['data']['product_discount_1_to'])) {
                            if (!empty($product['data']['product_discount_1_to'])) {
                                if (is_array($product['data']['product_discount_1_to'])) {
                                    $product['data']['product_discount_1_to'] =
                                        reset($product['data']['product_discount_1_to']);
                                }
                                $totime = strtotime($this->fomatDate($product['data']['product_discount_1_to']));
                                if ($totime) {
                                    $product_discount_1_data['to_time'] = date('Y-m-d H:i:s', $totime);
                                } else {
                                    $this->debbug('## Warning. product_discount_1_to date not recognized ->' .
                                                  print_r($product['data']['product_discount_1_to'], 1), 'syncdata');
                                }
                            }
                        }
                        unset($product['data']['product_discount_1_to']);
                    }

                    /**
                     * check special price 2
                     */

                    $product_discount_2_data = array();

                    /**
                     * Check special price type for check context store parameter
                     */
                    if (isset($product['data']['product_discount_2_type'])) {
                        $product_discount_2_type = '';
                        if (is_array(
                            $product['data']['product_discount_2_type']
                        )
                            && !empty($product['data']['product_discount_2_type'])
                        ) {
                            $product_discount_2_type = Tools::strtolower(
                                trim(reset($product['data']['product_discount_2_type']))
                            );
                        } else {
                            if (!is_array(
                                $product['data']['product_discount_2_type']
                            )
                                && $product['data']['product_discount_2_type'] != ''
                            ) {
                                $product_discount_2_type = Tools::strtolower(
                                    trim($product['data']['product_discount_2_type'])
                                );
                            }
                        }

                        if ($product_discount_2_type != '') {
                            if (preg_match('/:(all|global)/i', $product_discount_2_type)) {
                                $clear_data  = str_replace(
                                    array( ':all', ':global' , ':GLOBAL' , ':ALL' ),
                                    '',
                                    $product_discount_2_type
                                );
                                $product_discount_2_type = trim($clear_data);
                                $context_store =  0;
                            }

                            if (in_array(
                                $product_discount_2_type,
                                array('%', 'porcentaje', 'percentage')
                            )) {
                                $product_discount_2_data['type_reduction'] = 'percentage';
                            }

                            if (in_array(
                                $product_discount_2_type,
                                array('$', 'euro', '€', 'dollar', 'amount', 'importe')
                            )
                            ) {
                                $product_discount_2_data['type_reduction'] = 'amount';
                            }
                        }
                    }


                    if ($isset_Product_discount_2 && $product['data']['product_discount_2'] != '') {
                        $product_discount_2_value = $this->discauntFormat($product['data']['product_discount_2']);

                        if (is_numeric($product_discount_2_value) && $product_discount_2_value > 0) {
                            $product_discount_2_data = array(
                                'reduction' => $product_discount_2_value,
                                'type_reduction' => (isset($product_discount_2_data['type_reduction']) ?
                                    $product_discount_2_data['type_reduction'] : 'amount'),
                                'from_quantity' => 1,
                                'from_time' => '0000-00-00 00:00:00',
                                'to_time' => '0000-00-00 00:00:00'
                            );


                            if (isset($product['data']['product_discount_2_quantity'])
                                && $product['data']['product_discount_2_quantity'] != ''
                                && is_numeric(
                                    $product['data']['product_discount_2_quantity']
                                )
                            ) {
                                $product_discount_2_data['from_quantity'] =
                                    $product['data']['product_discount_2_quantity'];

                                if ($product_discount_2_data['from_quantity'] == 0) {
                                    $product_discount_2_data['from_quantity'] = 1;
                                }
                                unset($product_discount_2_data['from_quantity']);
                            } else {
                                $product_discount_2_data['from_quantity'] = 1;
                            }
                        }

                        /**
                         * check time from discount
                         */
                        if (isset($product['data']['product_discount_2_from'])) {
                            if (!empty($product['data']['product_discount_2_from'])) {
                                if (is_array($product['data']['product_discount_2_from'])) {
                                    $product['data']['product_discount_2_from'] =
                                        reset($product['data']['product_discount_2_from']);
                                }
                                $fromtime = strtotime($this->fomatDate($product['data']['product_discount_2_from']));
                                if ($fromtime) {
                                    $product_discount_2_data['from_time'] = date('Y-m-d H:i:s', $fromtime);
                                } else {
                                    $this->debbug('## Warning. product_discount_2_from ' .
                                                  ' format of date not recognized ->' .
                                                  print_r($product['data']['product_discount_2_from'], 1), 'syncdata');
                                }
                                $this->debbug(' data  ->' .
                                              print_r($product['data']['product_discount_2_from'], 1), 'syncdata');
                            } else {
                                $this->debbug(' empty ->' .
                                              print_r($product_discount_2_data['from_time'], 1), 'syncdata');
                            }
                        }
                        unset($product['data']['product_discount_2_from']);

                        /**
                         * check time to discount
                         */
                        if (isset($product['data']['product_discount_2_to'])) {
                            if (!empty($product['data']['product_discount_2_to'])) {
                                if (is_array($product['data']['product_discount_2_to'])) {
                                    $product['data']['product_discount_2_to'] =
                                        reset($product['data']['product_discount_2_to']);
                                }
                                $totime = strtotime($this->fomatDate($product['data']['product_discount_2_to']));
                                if ($totime) {
                                    $product_discount_2_data['to_time'] = date('Y-m-d H:i:s', $totime);
                                } else {
                                    $this->debbug('## Warning. product_discount_2_to format of date not recognized ->' .
                                                  print_r($product['data']['product_discount_2_to'], 1), 'syncdata');
                                }
                            }
                        }
                        unset($product['data']['product_discount_2_to']);
                    }

                    $this->debbug('Sync special price $context_store :' . $context_store, 'syncdata');

                    try {
                        $this->syncProductDiscount(
                            $product_id,
                            $product_discount_1_data,
                            $product_discount_2_data,
                            $context_store
                        );
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. ' . $occurence . ' Sync syncProductDiscount: ' . $e->getMessage() .
                            ' line-> ' . $e->getLine() . ' trace->' . print_r($e->getTrace(), 1),
                            'syncdata'
                        );
                    }
                    $this->debbug('After sync product discount  : ' . $product['ID'], 'syncdata');
                }


                if (Module::isInstalled('seosaproductlabels')) {
                    if (isset($product['data']['seosaproductlabels'])) {
                        try {
                            $this->syncSeosaProductLabels(
                                $product_id,
                                $product['data']['seosaproductlabels'],
                                $shop_id
                            );
                        } catch (Exception $e) {
                            $this->debbug(
                                '## Error. ' . $occurence . ' Sync syncSeosaProductLabels: ' . $e->getMessage(),
                                'syncdata'
                            );
                        }
                    }
                }

                Db::getInstance()->execute(
                    sprintf(
                        'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl
                        SET sl.date_upd = CURRENT_TIMESTAMP()
                        WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product"',
                        $product['ID'],
                        $comp_id
                    )
                );
            }

            /**
             * Attachment Files upload
             */

            if (isset($product['data']['product_attachment'])) {
                if (is_array($product['data']['product_attachment'])) {
                    $attachments_files = array();
                    foreach ($product['data']['product_attachment'] as $file) {
                        if (isset($file['FILE'])) {
                            $attachments_files[] = $file['FILE'];
                        } else {
                            if (is_array($file)) {
                                $attachments_files[] = reset($file);
                            } else {
                                $attachments_files[] = $file;
                            }
                        }
                    }
                } else {
                    $attachments_files = explode(',', $product['data']['product_attachment']);
                }
                try {
                    $this->syncProductAttachment($attachments_files, $product_id, $mulilanguage);
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. In syncProductAttachment ' . $occurence . ' ->' . print_r(
                            $e->getMessage(),
                            1
                        ) . ' line->' . print_r($e->getLine(), 1),
                        'syncdata'
                    );
                }
            }

            try {
                $this->syncFeatures($product_exists, $product, $schema);
            } catch (Exception $e) {
                $this->debbug('## Error. ' . $occurence . ' Sync Features: ' . $e->getMessage(), 'syncdata');
            }
        }


        unset($productObject);
        $this->debbug(
            $occurence . ' Ending with product result:' . ($syncCat ? 'Success' : 'could not finish'),
            'syncdata'
        );

        if ($syncCat) {
            return array('stat' => 'item_updated', 'additional_output' => $additional_output);
        } else {
            $this->debbug(
                $occurence . '## Error. This product could not be synchronized for any reason  ',
                'syncdata'
            );

            return array('stat' => 'item_not_updated');
        }
    }
    public function syncTagsSL($id_product, $my_tags, $lang, $idShop)
    {
        try {
            $product_tags = Tag::getProductTags($id_product);
            $this->debbug(
                'Search for tags in several languages : ' . print_r($lang, 1) . ' from bd ->' .
                print_r(
                    $product_tags,
                    1
                ) . ' language ->' . print_r($lang['iso_code'], 1),
                'syncdata'
            );

            $update_tag = array();
            $delete_tag = array();

            if (is_array($product_tags) && count($product_tags) && isset($product_tags[$lang['id_lang']])) {
                foreach ($product_tags[$lang['id_lang']] as $saved_tags) {
                    if (in_array($saved_tags, $my_tags, false)) {
                        $update_tag[] = $saved_tags;
                    } else {
                        $delete_tag[] = $saved_tags;
                    }
                }
            }
            $this->debbug(
                'Before array_diff: $update_tag->' . print_r($update_tag, 1) . ' $my_tags->' .
                print_r(
                    $my_tags,
                    1
                ) . ' language ->' . print_r($lang['iso_code'], 1),
                'syncdata'
            );

            $difference = $this->slArrayDiff($my_tags, $update_tag);
            if (count($difference)) {
                $this->debbug(
                    'adding new tags  : ' . print_r($difference, 1),
                    'syncdata'
                );
                Tag::addTags($lang['id_lang'], $id_product, $difference);
            }

            $this->debbug(
                'Add tags after add : diff ->' .
                print_r(
                    $difference,
                    1
                ) . ' language ->' . print_r($lang['iso_code'], 1),
                'syncdata'
            );
            if (count($delete_tag)) {
                $this->debbug(
                    'Received array for delete this tags : ' . print_r($delete_tag, 1)
                     . ' language ->' . print_r($lang['iso_code'], 1),
                    'syncdata'
                );
                $editedtids = array();
                foreach ($delete_tag as $tag_fordelete) {
                    $tagObj = new Tag(null, $tag_fordelete, (int) $lang['id_lang']);
                    if (Validate::isLoadedObject($tagObj)) {
                        $editedtids[] = $tagObj->id;
                        $count = Db::getInstance()->executeS('
                            SELECT tg.id_tag 
                            FROM ' . _DB_PREFIX_ . 'product_tag tg
                            WHERE tg.`id_tag` = ' . (int) $tagObj->id .
                           ' AND tg.`id_product` != ' . $id_product .
                           ' AND tg.`id_lang` != ' . $lang['id_lang'] . '
                        ');
                        $countconter = Db::getInstance()->executeS('
                            SELECT tg.id_tag 
                            FROM ' . _DB_PREFIX_ . 'tag_count tg
                            WHERE tg.`id_tag` = ' . (int) $tagObj->id .
                            ' AND tg.`id_shop` != ' . $idShop .
                            ' AND tg.`id_lang` != ' . $lang['id_lang' ]);
                        if (!count($count) && !count($countconter)) {
                            // delete tag if exist only in this product and store
                            $tagObj->delete();
                            Db::getInstance()->execute(
                                'DELETE  FROM ' . _DB_PREFIX_ . 'product_tag 
                                  WHERE `id_tag` = ' . (int) $tagObj->id
                            );
                            Db::getInstance()->execute(
                                'DELETE  FROM ' . _DB_PREFIX_ . 'tag_count 
                                 WHERE `id_tag` = ' . (int) $tagObj->id
                            );
                        } else {
                            if (!count($countconter)) {
                                Db::getInstance()->execute(
                                    'DELETE  FROM ' . _DB_PREFIX_ . 'product_tag 
                                     WHERE `id_tag` = ' . (int) $tagObj->id .
                                    ' AND `id_product` = ' . $id_product .
                                    ' AND `id_lang` = ' . $lang['id_lang']
                                );
                            }

                            /*  Db::getInstance()->execute(
                                  'UPDATE  ' . _DB_PREFIX_ . 'ps_tag_count
                                   SET counter = counter - 1
                                   WHERE `id_tag` = ' . (int) $tagObj->id .
                                  ' AND `id_lang` = '.$lang['id_lang'] .
                                  ' AND `id_shop` = '.$idShop
                              );*/
                        }
                    }
                    unset($tagObj);
                }
                Tag::updateTagCount($editedtids);
            }
        } catch (Exception $e) {
            $this->debbug('## Error. Occurred in sync tags ->' . $e->getMessage() .
                          ' line->' . $e->getLine() .
                           'trace->' . print_r($e->getTrace(), 1), 'syncdata');
        }
    }
    public function syncProductAttachment($files, $product_id, $mulilanguage)
    {
        $attachment_protected_ids = array();
        if (isset($mulilanguage) && !empty($mulilanguage)) {
            $occurence = ' product name :' . reset($mulilanguage);
        } else {
            $occurence = ' ID :' . $product_id;
        }
        $this->debbug(
            $occurence . '  Beginning to synchronise images. First sync shop for this product->' . print_r(
                $this->first_sync_shop,
                1
            ),
            'syncdata'
        );

        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);

        if (isset($files) && !empty($files)) {
            $newfileArray = array();
            if (!empty($files)) {
                // imagenes elegidos para subir a este formato buscar en producto padre si en ya hay este imagen
                /**
                 * How to a search files cached in SL table for MD5 hash
                 */
                $filereferences = array();

                foreach ($files as $file) {
                    $explode = explode('/', urldecode($file));
                    $filename = end($explode);
                    $filereferences[] = $filename;
                    $newfileArray[$filename] = $file;
                }

                $catch_file_references = "'" . implode("','", $filereferences) . "'";
                $slyr_attachments = Db::getInstance()->executeS(
                    'SELECT * FROM ' . _DB_PREFIX_ . 'slyr_attachment
                    WHERE file_reference IN (' . $catch_file_references . ")
                      AND ps_product_id = '" . $product_id . "' "
                );
                $this->debbug('Searching files cached in SL table for MD5 hash ->' .
                              print_r($slyr_attachments, 1), 'syncdata');
            }

            /**
             * Process files attachments from this connection
             */
            $this->debbug(
                'Before processing prepared files for update stat of array  ->' . print_r($files, 1),
                'syncdata'
            );

            foreach ($newfileArray as $fileReference => $file_url) {
                $this->debbug(
                    'Processing file of product from this connection ->' . print_r(
                        $file_url,
                        1
                    ),
                    'syncdata'
                );
                $time_ini_image = microtime(1);
                $url = trim($file_url);

                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $temp_file = $this->downloadImageToTemp($url, _PS_DOWNLOAD_DIR_);
                    if ($temp_file) {
                        $md5_file = md5_file($temp_file);
                        if (!empty($slyr_attachments)) {
                            foreach ($slyr_attachments as $keySLatt => $slyr_attachment) {
                                $this->debbug(
                                    'Before Verify Processing attachment ->' . print_r(
                                        $fileReference,
                                        1
                                    ),
                                    'syncdata'
                                );
                                if ($slyr_attachment['file_reference'] == $fileReference &&
                                    $slyr_attachment['md5_attachment'] !== '') {
                                    /**
                                     * file is the same
                                     */
                                    unset($slyr_attachments[$keySLatt]);
                                    $this->debbug(
                                        'Before test md5 Processing file ->' . print_r(
                                            $slyr_attachment['md5_attachment'],
                                            1
                                        ) . ' <-> ' . print_r($md5_file, 1),
                                        'syncdata'
                                    );
                                    if ($slyr_attachment['md5_attachment'] !== $md5_file) {
                                        $this->debbug(
                                            'Image is different ->' . print_r(
                                                $slyr_attachment['md5_attachment'],
                                                1
                                            ) . ' <-> ' . print_r($md5_file, 1),
                                            'syncdata'
                                        );
                                        /**
                                         * Image with same name but different md5
                                         */
                                        // if origin if the image is this variant delete it  from product
                                        $file_delete = new Attachment($slyr_attachment['id_attachment']);
                                        $file_delete->delete();
                                        $this->debbug(
                                            'Deleting file  ->' . print_r(
                                                $slyr_attachment['id_attachment'],
                                                1
                                            ) . ' <-> ' . print_r($md5_file, 1),
                                            'syncdata'
                                        );
                                        break;
                                    } else {
                                        //file is the same protect id
                                        $attachment_protected_ids[] = $slyr_attachment['id_attachment'];
                                        continue 2; //jump to another file
                                    }
                                }
                            }
                        }
                    }
                } else {
                    continue;
                }

                /**
                 * Process files that do not exist in the sl cache and have arrived in this connection
                 */
                $this->debbug(
                    'Check attachment befor save->' . print_r(
                        $temp_file,
                        1
                    ),
                    'syncdata'
                );
                $id_attachment =  $this->saveAttachmentSL(
                    $fileReference,
                    $temp_file,
                    $mulilanguage,
                    $product_id,
                    $occurence,
                    filesize($temp_file)
                );

                if ($id_attachment != 0) {
                    $attachment_protected_ids[] = $id_attachment;
                    /**
                     * INSERT INTO SL CACHE TABLE attachment WITH MD5, NAME OF FILE , ID
                     */

                    Db::getInstance()->execute(
                        'INSERT INTO ' . _DB_PREFIX_ . "slyr_attachment
                                        (file_reference, id_attachment, md5_attachment, ps_product_id )
                                        VALUES ('" . $fileReference . "', " . $id_attachment . ", '" .
                        $md5_file . "','" . $product_id . "')
                                        ON DUPLICATE KEY UPDATE id_attachment = '" . $id_attachment .
                        "', md5_attachment = '" . $md5_file . "'"
                    );
                    $this->debbug(
                        'Attachment saved correctly ->' . print_r(
                            $id_attachment,
                            1
                        ),
                        'syncdata'
                    );
                }
                $this->debbug('END processing this attachment. Timing ->' .
                              ($time_ini_image - microtime(1)), 'syncdata');
            }
        }

        /**
         * DELETE ATTACHMENTS
         */

        $this->debbug('We will check if any of the attachments have been imported' .
                      ' in the past with this variant', 'syncdata');

        $slyr_attachments = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . "product_attachment at WHERE  at.id_product = '" . $product_id . "' "
        );

        if (!empty($slyr_attachments)) {
            foreach ($slyr_attachments as $slyr_attachment) {
                try {
                    $this->debbug('Test if it is needed to delete this attachment ' . print_r($slyr_attachment, 1));
                    if (!in_array($slyr_attachment['id_attachment'], $attachment_protected_ids, false)) {
                        $this->debbug('Proceed to delete attachment but not is protected' .
                                      print_r($slyr_attachment, 1));
                        Db::getInstance()->execute(
                            'DELETE FROM ' . _DB_PREFIX_ . "product_attachment
	                            WHERE id_attachment = '" . $slyr_attachment['id_attachment'] . "' "
                        );

                        $attachment_delete = new Attachment($slyr_attachment['id_attachment']);
                        $attachment_delete->delete();

                        Db::getInstance()->execute(
                            'DELETE FROM ' . _DB_PREFIX_ . "slyr_attachment
	                            WHERE id_attachment = '" . $slyr_attachment['id_attachment'] . "' "
                        );
                        unset($attachment_delete);
                    }
                } catch (Exception $e) {
                    $this->debbug('## Error. ' . $occurence .
                                  ' Deleting old attachment ' .
                                  print_r($slyr_attachment, 1));
                }
            }
        }
        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
    }

    public function saveAttachmentSL($fileReference, $file_localpath, $mulilanguage, $id_product, $occurence, $filesize)
    {
        try {
            $explode = explode('.', $fileReference);

            $attachment = new Attachment();

            $languages = Language::getLanguages();
            foreach ($languages as $language) {
                $attachment->name[$language['id_lang']] = (string) $fileReference;
                if (isset($mulilanguage[$language['id_lang']])) {
                    $attachment->description[$language['id_lang']] =
                        Tools::substr($mulilanguage[$language['id_lang']] . ' ' . reset($explode), 128);
                }
            }

            $attachment->file = sha1(urldecode($fileReference));
            $attachment->file_name = urldecode($fileReference);


            $attachment->file_size = $filesize;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $attachment->mime = finfo_file($finfo, $file_localpath);


            $attachment->add(true, true);

            if ($attachment->id) {
                Db::getInstance()->execute('
                        INSERT INTO `' . _DB_PREFIX_ . 'product_attachment` (`id_attachment`, `id_product`)
                        VALUES (' . (int)$attachment->id . ', ' . (int)$id_product . ')
                        ');
            }

            return (int) $attachment->id;
        } catch (Exception $e) {
            $this->debbug(
                '## Error. ' . $occurence . ' Error in creating attachment 
                                    problem found->' . print_r(
                    $e->getMessage() . ' line->' .
                    print_r($e->getLine(), 1) .
                    ' Trace->' . print_r($e->getTrace(), 1),
                    1
                ),
                'syncdata'
            );
            return false;
        }
    }

    public function syncProductImages(
        $images,
        $id_lang,
        $product_id,
        $mulilanguage
    ) {
        if (isset($mulilanguage) && !empty($mulilanguage)) {
            $first_name = reset($mulilanguage);
            if (is_array($first_name)) {
                $first_name = reset($mulilanguage);
            }
            $occurence = ' product name :' . print_r($first_name, 1) ;
        } else {
            $occurence = ' ID :' . $product_id;
        }
        $image_counter_position = 1;

        $this->debbug(
            '$multilanguage array->' . print_r(
                $mulilanguage,
                1
            ),
            'syncdata'
        );


        $this->debbug(
            $occurence . '  Beginning to synchronise images. First sync shop for this product->' . print_r(
                $this->first_sync_shop,
                1
            ),
            'syncdata'
        );

        /**
         * First store only sync images
         */

        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);
        $cover = true;
        $catch_images = array();

        if (isset($images) && !empty($images)) {
            /**
             * Process images from this connection
             */

            foreach ($images as $image_reference => $image_list) {
                if (is_array($image_list)) {
                    /**
                     * Check correct sizes and filter images
                     */
                    $this->debbug(
                        ' check correct sizes of images reference ->' . $image_reference . ' value ->' . print_r(
                            $image_list,
                            1
                        ),
                        'syncdata'
                    );
                    foreach ($this->product_images_sizes as $imgFormat) {
                        if (isset($image_list[$imgFormat]) && !empty($image_list[$imgFormat])) {
                            $catch_images[$image_reference] = $image_list[$imgFormat];
                            break;
                        }
                    }
                }
            }

            $slyr_images = $slyr_images_to_delete = array();

            if (!empty($catch_images)) {

                /**
                 * How to a search images cached in SL table for MD5 hash
                 */
                $this->debbug(' How to a search images cached in SL table for MD5 hash ', 'syncdata');
                // $catch_images_references = implode("','", array_keys($catch_images));

                if (count($catch_images)) {
                    // $catch_images_references = "'" . $catch_images_references . "'";

                    $slyr_images = Db::getInstance()->executeS(
                        'SELECT * FROM ' . _DB_PREFIX_ . "slyr_image im
                        WHERE   im.ps_product_id = '" . $product_id . "'"
                    ); //im.origin = 'prod'  AND  AND im.image_reference IN (" . $catch_images_references . ")  "
                    $this->debbug('Images of product -> ' . print_r($slyr_images, 1), 'syncdata');
                }
            }


            $ps_images = Image::getImages($id_lang, $product_id);

            if (!empty($slyr_images)) {
                /**
                 * Images in SL TABLE
                 */

                if (empty($ps_images)) {
                    /**
                     * Product without images delete records of SL table
                     */
                    $this->debbug('Images exist in cache but product doesnt have any images ', 'syncdata');
                    foreach ($slyr_images as $keySLImg => $slyr_image) {
                        $slyr_images_to_delete[] = $slyr_image['id_image'];
                        unset($slyr_images[$keySLImg]);
                    }
                } else {

                    /**
                     * Stored product images
                     */
                    $this->debbug('loop Stored product images', 'syncdata');
                    foreach ($slyr_images as $keySLImg => $slyr_image) { // from sl cache
                        $image_found = false;
                        foreach ($ps_images as $keyPSImg => $ps_image) { // PS product images
                            $variants = 0;
                            if ($slyr_image['ps_variant_id'] != null) {
                                $variants = count(json_decode($slyr_image['ps_variant_id'], 1));
                            }
                            if ($slyr_image['md5_image'] !== '' && $slyr_image['id_image'] == $ps_image['id_image'] &&
                                (array_key_exists($slyr_image['image_reference'], $catch_images) || $variants > 0)) { // Is image from producto or is image from variant
                                $this->debbug('Protect image->' .
                                              print_r($slyr_image['id_image'], 1), 'syncdata');
                                $image_found = true;
                                unset($ps_images[$keyPSImg]);
                                break;
                            }
                        }

                        if (!$image_found) { // Product image not found send delete it from sl cache
                            $this->debbug('Send for delete id_image->' .
                                          print_r($slyr_image['id_image'], 1), 'syncdata');
                            $slyr_images_to_delete[] = $slyr_image['id_image'];
                            unset($slyr_images[$keySLImg]);
                        }
                    }

                    /**
                     * Images for delete that do not match in SL
                     */
                    $this->debbug('Images to be deleted that do not match in SL id_images->' .
                                  print_r($ps_images, 1), 'syncdata');
                    /*foreach ($ps_images as $ps_image) {
                        $image_delete = new Image($ps_image['id_image']);
                        $image_delete->delete();
                        $slyr_images_to_delete[] = $ps_image['id_image'];
                    }*/
                }
            } //else {
            /**
             * There are no cached images in the SL TABLE for this product
             */
            $this->debbug('There are no cached images in the SL TABLE for this product. id_image->' .
                              print_r($ps_images, 1), 'syncdata');
            if (!empty($ps_images)) {
                /**
                 * Images send for delete
                 */

                foreach ($ps_images as $ps_image) {
                    $image_delete = new Image($ps_image['id_image']);
                    $image_delete->delete();

                    $slyr_images_to_delete[] = $ps_image['id_image'];
                }
            }
            // }

            if (!empty($slyr_images_to_delete)) {

                /**
                 * Clear  images cached in SL Table
                 */
                $this->debbug('Clear  images cached in SL Table ', 'syncdata');
                $slyr_images_to_delete = implode(',', array_unique($slyr_images_to_delete));
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . _DB_PREFIX_ . "slyr_image  WHERE id_image IN (" . $slyr_images_to_delete . ")"
                    )
                );
            }

            /**
             * Process images from this connection
             */
            $this->debbug(
                'Before processing prepared images to update state of array ->' . print_r($catch_images, 1),
                'syncdata'
            );

            foreach ($catch_images as $image_reference => $image_url) {
                $this->debbug(
                    'Processing images from this connection ->' . print_r($image_reference, 1) . ' url ->' . print_r(
                        $image_url,
                        1
                    ),
                    'syncdata'
                );
                $time_ini_image = microtime(1);
                $url = trim($image_url);

                if (!empty($url)) {
                    $temp_image = $this->downloadImageToTemp($url);

                    if ($temp_image) {
                        $md5_image = md5_file($temp_image);

                        if (!empty($slyr_images)) {
                            foreach ($slyr_images as $keySLImg => $slyr_image) {
                                if ($slyr_image['image_reference'] == $image_reference
                                    && $slyr_image['md5_image'] !== '') {
                                    /**
                                     * Image is the same
                                     */

                                    unset($slyr_images[$keySLImg]);
                                    if ($slyr_image['md5_image'] !== $md5_image) {

                                        /**
                                         * Image with same name but different md5
                                         */
                                        $this->debbug(
                                            'Image with same name but different md5. Delete image ->' .
                                            print_r($slyr_image['id_image'], 1),
                                            'syncdata'
                                        );
                                        $image_delete = new Image($slyr_image['id_image']);
                                        $image_delete->delete();
                                        break;
                                    } else {
                                        /**
                                         * Image found / Update this image
                                         */

                                        $image_cover = new Image($slyr_image['id_image']);

                                        try {
                                            foreach ($mulilanguage as $id_lang_multi => $name_of_product) {
                                                if (is_array($name_of_product)) {
                                                    $index_alt = $image_counter_position - 1;
                                                    if (isset($name_of_product[$index_alt])) {
                                                        $name_of_product = $name_of_product[$index_alt];
                                                    } else {
                                                        $name_of_product = reset($name_of_product) .
                                                                           ' (' . $image_counter_position . ')';
                                                    }
                                                }

                                                if ($name_of_product != ''
                                                    && (!isset($image_cover->legend[$id_lang_multi])
                                                        || trim(
                                                            $image_cover->legend[$id_lang_multi]
                                                        ) != trim($name_of_product))
                                                ) {
                                                    $image_cover->legend[$id_lang_multi] = $name_of_product;
                                                    $this->debbug(
                                                        'Recording a new image alt attribute, ' .
                                                         'you need to update this image information ->' .
                                                        print_r(
                                                            $image_cover->legend[$id_lang_multi],
                                                            1
                                                        ) .
                                                        '  !=  ' .
                                                        print_r($name_of_product, 1),
                                                        'syncdata'
                                                    );
                                                } else {
                                                    $this->debbug(
                                                        'The image is the same or empty,' .
                                                        ' the alt attribute of the image ' .
                                                         'is the same. It is not necessary to update the information ' .
                                                         'of this image ->' .
                                                        print_r(
                                                            $image_cover->legend[$id_lang_multi],
                                                            1
                                                        ) .
                                                        '  ==  ' .
                                                        print_r($name_of_product, 1),
                                                        'syncdata'
                                                    );
                                                }
                                            }
                                        } catch (Exception $e) {
                                            $this->debbug(
                                                '## Error. ' . $occurence . ' In updating names of Image->' . print_r(
                                                    $e->getMessage(),
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                        }


                                        if ($cover) { //  is first image  set to cover && Image is already like cover
                                            try {
                                                Image::deleteCover(
                                                    $product_id
                                                ); // delete cover image from this product
                                            } catch (Exception $e) {
                                                $this->debbug(
                                                    '## Error. ' . $occurence . ' Delete cover ->' . print_r(
                                                        $e->getMessage(),
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                            }

                                            $image_cover->cover = $cover; // set this image as cover

                                            $cover = false;
                                        } else {
                                            $image_cover->cover = null;
                                        }

                                        $image_cover->position = $image_counter_position;
                                        $image_counter_position++;
                                        $image_cover->id_product = $product_id;
                                        try {
                                            $this->debbug('updating image information ', 'syncdata');
                                            $image_cover->save();
                                            Db::getInstance()->execute(
                                                "UPDATE " . _DB_PREFIX_ . "slyr_image SET  origin ='prod'
                                                WHERE id_image = '" . $slyr_image['id_image'] . "' "
                                            );

                                            $this->debbug('Saving changes to complete image', 'syncdata');
                                        } catch (Exception $e) {
                                            $this->debbug(
                                                '## Error. ' . $occurence . ' Updating Image info ->' . print_r(
                                                    $e->getMessage(),
                                                    1
                                                ),
                                                'syncdata'
                                            );
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


                        $result_save_image = false;
                        $image = new Image();
                        $image->id_product = (int)$product_id;
                        $image->position = Image::getHighestPosition($product_id) + 1;

                        foreach ($mulilanguage as $id_lang_multi => $name_of_product) {
                            if (is_array($name_of_product)) {
                                $index_alt = $image_counter_position - 1;
                                if (isset($name_of_product[$index_alt])) {
                                    $name_of_product = $name_of_product[$index_alt];
                                } else {
                                    $name_of_product = reset($name_of_product) .
                                                       ' (' . $image_counter_position . ')';
                                }
                            }


                            if ($name_of_product != ''
                                && (!isset($image->legend[$id_lang_multi])
                                    || trim(
                                        $image->legend[$id_lang_multi]
                                    ) != trim($name_of_product))
                            ) {
                                $image->legend[$id_lang_multi] = $name_of_product;
                                $this->debbug(
                                    'Setting image alt attribute. You  to update this image info ->' .
                                    print_r(
                                        $image->legend[$id_lang_multi],
                                        1
                                    ) .
                                    '  !=  ' .
                                    print_r(
                                        $name_of_product,
                                        1
                                    ),
                                    'syncdata'
                                );
                            } else {
                                $this->debbug(
                                    'Image is the same, image alt attribute not is ' .
                                    ' needed, update this image info ->' .
                                    print_r(
                                        $image->legend[$id_lang_multi],
                                        1
                                    ) .
                                    '  ==  ' .
                                    print_r(
                                        $name_of_product,
                                        1
                                    ),
                                    'syncdata'
                                );
                            }
                        }

                        if ($cover) {
                            try {
                                Image::deleteCover($product_id); // delete cover image from this product
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. ' . $occurence . ' Delete cover ->' . print_r($e->getMessage(), 1),
                                    'syncdata'
                                );
                            }

                            $image->cover = $cover;
                            $cover = false;
                        } else {
                            $image->cover = null;
                        }

                        try {
                            $validate_fields = $image->validateFields(false, true);
                        } catch (Exception $e) {
                            $validate_fields = false;
                            $this->debbug(
                                '## Error. ' . $occurence . ' Validate image fields ->' . print_r(
                                    $e->getMessage(),
                                    1
                                ) . ' url->' . $url,
                                'syncdata'
                            );
                        }
                        try {
                            $validate_language = $image->validateFieldsLang(false, true);
                        } catch (Exception $e) {
                            $validate_language = false;
                            $this->debbug(
                                '## Error. ' . $occurence . ' Validating language fields of image ->' . print_r(
                                    $e->getMessage(),
                                    1
                                ) . ' url->' . $url,
                                'syncdata'
                            );
                        }
                        $image->position = $image_counter_position;


                        try {
                            $result_save_image = $image->add();
                        } catch (Exception $e) {
                            $result_save_image = false;
                            $this->debbug(
                                '## Error. ' . $occurence . ' Problem saving image ->' . print_r(
                                    $e->getMessage(),
                                    1
                                ) . ' url->' . $url,
                                'syncdata'
                            );
                        }

                        if ($result_save_image != true) {
                            $this->debbug(
                                '## Warning. ' . $occurence . '. We have tried to create an
                                image but the store has caused problem.' .
                                 'We are going to verify if there are any phantom images' .
                                 'in the table and then try to eliminate them,' .
                                 'so that we can create a new image. We will try to repair it',
                                'syncdata'
                            );
                            try {
                                $this->repairImageStructureOfProduct($product_id);
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. ' . $occurence . ' In repairing structure of images ' . $e->getMessage(),
                                    'syncdata'
                                );
                            }
                            try {
                                $prepare_second_attempt_cover = $image->cover;
                                $prepare_second_attempt_legend = $image->legend;

                                $image->delete();
                                $this->debbug(
                                    'Delete image after repair ' . print_r($image->id, 1),
                                    'syncdata'
                                );
                                $cloned_image = new Image();
                                $cloned_image->cover = $prepare_second_attempt_cover;
                                $cloned_image->legend = $prepare_second_attempt_legend;
                                $cloned_image->position = $image_counter_position;
                                $cloned_image->id_product = (int) $product_id;
                                $result_save_image = $cloned_image->add();
                            } catch (Explode $e) {
                                $this->debbug('## Error. ' . $occurence . ' Second attempt.', 'syncdata');
                            }


                            if ($result_save_image) {
                                $this->debbug(
                                    '## Info. ' . $occurence . ' Second attempt to create
                                    image after repair has been corrected  ',
                                    'syncdata'
                                );
                            } else {
                                $this->debbug(
                                    '## Info. ' . $occurence . ' Could not create the image on second attempt. ',
                                    'syncdata'
                                );
                            }
                        }
                        $image_counter_position++;

                        if ($validate_fields === true && $validate_language === true && $result_save_image) {
                            $this->debbug('Validation ok ', 'syncdata');
                            if (!$this->copyImg(
                                $product_id,
                                $image->id,
                                $temp_image,
                                'products',
                                true,
                                true
                            )
                            ) {  //imageUrl
                                $this->debbug(
                                    'There was a problem copying the image, eliminating 
                                    the temporary image id_image->' . print_r($image->id, 1),
                                    'syncdata'
                                );
                                $image->delete();
                            } else {
                                $all_shops_image = Shop::getShops(true, null, true);
                                $this->debbug(
                                    'Associating image to all stores' . print_r($all_shops_image, 1),
                                    'syncdata'
                                );
                                $image->associateTo($all_shops_image);


                                /**
                                 * INSERT INTO SL CACHE TABLE IMAGE WITH MD5, NAME OF FILE , ID
                                 */
                                Db::getInstance()->execute(
                                    'INSERT INTO ' . _DB_PREFIX_ . "slyr_image
                                    (image_reference, id_image, md5_image, ps_product_id, origin )
                                    VALUES ('" . $image_reference . "', " . $image->id . ", '" . $md5_image .
                                    "','" . $product_id . "','prod')
                                    ON DUPLICATE KEY UPDATE id_image = '" . $image->id . "', md5_image = '" .
                                    $md5_image . "'"
                                );
                            }
                        } else {
                            $this->debbug(
                                '## Error. ' . $occurence . '. Validating image problems in Product ID:'
                                . $product_id . ' validate fields->' . print_r(
                                    $validate_fields,
                                    1
                                ) . ', validate language fields ->' . print_r(
                                    $validate_language,
                                    1
                                ) . ', result save image->' . print_r(
                                    ($result_save_image ? 'true' : 'false'),
                                    1
                                ) . ', url->' . $url . ' id_image->' . print_r($image->id, 1),
                                'syncdata'
                            );
                            //  $this->debbug('## Error. '.$occurence.'. object->'.print_r($image,1), 'syncdata');
                            $image->delete();
                            unlink($temp_image);
                        }
                        unset($image);
                    }
                }
                $this->debbug('END processing this image Timing ->' . ($time_ini_image - microtime(1)), 'syncdata');
            }
        } else {
            $this->debbug('We will check if any of the images have been imported ' .
                          'in the past with this variant', 'syncdata');
            $slyr_images = Db::getInstance()->executeS(
                'SELECT * FROM ' . _DB_PREFIX_ . "slyr_image im WHERE  im.ps_product_id = '" . $product_id . "' "
            );

            if (!empty($slyr_images)) {
                foreach ($slyr_images as $keySLImg => $slyr_image) {
                    $this->debbug('Test if it is needed to delete this image ' . print_r($slyr_image, 1));

                    $variant_ids = array();
                    if ($slyr_image['ps_variant_id'] != null) {
                        $variant_ids = json_decode($slyr_image['ps_variant_id'], 1);
                    }

                    $this->debbug(
                        'Id of this variant is in variants array-> ' . print_r($variant_ids, 1),
                        'syncdata'
                    );

                    if (empty($variant_ids)) {     // this variant  is unique variant in use this file
                        $image_delete = new Image($slyr_image['id_image']);
                        $this->debbug(
                            'Deleting image because it does not belong to any products or variants.' .
                            ' id_image ->' . print_r($slyr_image['id_image'], 1),
                            'syncdata'
                        );
                        $image_delete->delete();
                        Db::getInstance()->execute(
                            'DELETE FROM ' . _DB_PREFIX_ . "slyr_image
                            WHERE id_image = '" . $slyr_image['id_image'] . "' "
                        );
                        unset($image_delete);
                    } else {
                        $variant_ids = json_encode($variant_ids);
                        Db::getInstance()->execute(
                            "UPDATE " . _DB_PREFIX_ . "slyr_image im SET im.ps_variant_id ='" .
                            $variant_ids . "' , im.origin =''  WHERE im.id_image = '" . $slyr_image['id_image'] . "' "
                        );
                    }
                }
            }
        }
        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
    }

    public function deleteProduct(
        $product,
        $comp_id,
        $shops
    ) {
        $this->debbug(
            'Deleting product with id sl_id ' . $product . ' $comp_id ' . $comp_id . ' $shops ->' . print_r(
                $shops,
                1
            ),
            'syncdata'
        );
        $product_ps_id = (int)Db::getInstance()->getValue(
            sprintf(
                'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product"',
                $product,
                $comp_id
            )
        );

        if ($product_ps_id) {
            foreach ($shops as $shop) {
                try {
                    Shop::setContext(Shop::CONTEXT_SHOP, $shop);
                    $prod = new Product($product_ps_id, null, null, $shop);

                    if ($this->deleteProductOnHide) {
                        $prod->deleteImages();
                        $prod->delete();
                    } else {
                        if ($prod->price == null || $prod->price == '') {
                            $prod->price = 0;
                        }
                        if (isset($prod->low_stock_alert) || $prod->low_stock_alert == null) {
                            $prod->low_stock_alert = false;
                        }
                        $prod->active = 0;
                        $prod->save();
                        unset($prod);
                    }
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. Problem hiding product ID:' . $product . ' error->' . print_r(
                            $e->getMessage(),
                            1
                        ) . ' it has not been possible to find a product that we must deactivate,' .
                        'it is possible that it does not exist anymore in prestashop,' .
                        'and thus it can no longer be eliminated. Try deactivating product manually from prestashop.',
                        'syncdata'
                    );
                }
            }


            Db::getInstance()->execute(
                sprintf(
                    'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product
                    WHERE slyr_id = "%s" 
                    AND comp_id = "%s" 
                    AND ps_type = "product"',
                    $product,
                    $comp_id
                )
            );
        }
    }

    /**
     * Synchronize a product
     * @param array $product data of product
     * @return bool|int|string id of product synchronized
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function syncProduct(
        $product,
        $comp_id,
        $schema
    ) {
        $occurrence_found = false;
        if (isset($product['data']['product_reference']) && !empty($product['data']['product_reference'])) {
            $occurrence_found = true;
            $occurence = ' product reference : "' . $product['data']['product_reference'] . '" ';
        } elseif (isset($product['data']['product_name']) && !empty($product['data']['product_name'])) {
            $occurrence_found = true;
            $occurence = ' product name : "' . $product['data']['product_name'] . '"';
        } else {
            $occurence = ' ID :' . $product['ID'];
        }

        //Comprobamos de nuevo si existe el producto en Slyr

        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);
        $productObject = new Product();

        $product_id = false;
        //Eliminamos carácteres especiales del nombre

        if (isset($product['data']['product_reference'])
            && $product['data']['product_reference'] != null
            && $product['data']['product_reference'] != '') {
            $this->debbug('Search product by reference. ' .
                          $occurence, 'syncdata');
            //Eliminamos carácteres especiales de la referencia
            $product_reference = $this->slValidateReference($product['data']['product_reference']);
            //Buscamos producto con referencia idéntica
            $schemaRef = 'SELECT id_product FROM ' . $this->product_table . "
            WHERE reference = '" . $product_reference . "'";
            $regsRef = Db::getInstance()->executeS($schemaRef);
            if (count($regsRef) == 1) {
                $this->debbug('Selected by product first unique id' .
                              $regsRef[0]['id_product'], 'syncdata');
                $product_id = $regsRef[0]['id_product'];
            } else {
                $this->debbug('Exist more than one product with the' .
                              ' same reference. ' .
                              $occurence, 'syncdata');
                if (count($regsRef) > 1) {
                    foreach ($this->shop_languages as $lang) {
                        $product_name_index = '';
                        $product_name_index_search = 'product_name_' . $lang['iso_code'];
                        if (isset($product['data']['product_name'])
                            && !empty($product['data']['product_name'])
                            && !isset($schema['product_name']['language_code'])) {
                            $product_name_index = 'product_name';
                        } elseif (isset(
                            $product['data'][$product_name_index_search],
                            $schema[$product_name_index_search]['language_code']
                        )
                            && !empty($product['data'][$product_name_index_search])
                            && $schema[$product_name_index_search]['language_code'] == $lang['iso_code']) {
                            $product_name_index = 'product_name_' . $lang['iso_code'];
                        }

                        if ($product_name_index != '' && isset($product['data'][$product_name_index])
                            && !empty($product['data'][$product_name_index])) {
                            $product_name = $this->slValidateCatalogName(
                                $product['data'][$product_name_index],
                                'Product'
                            );
                            $this->debbug('Search by name but more have same reference ' .
                                          $occurence, 'syncdata');

                            //Si hay más de una referencia buscamos producto por nombre similar,
                            //si encontramos varios buscamos con referencia igual, si no, nos quedamos con el primero
                            $regsName = $productObject->searchByName($lang['id_lang'], $product_name);
                            if (count($regsName) > 0) {
                                $found = false;
                                foreach ($regsName as $keyName => $regName) {
                                    if ($regName['reference'] == $product_reference) {
                                        $found = $keyName;
                                        break;
                                    }
                                }
                                if ($found === false) {
                                    $this->debbug('Selected by product first' .
                                                  $regsName[$found]['id_product'] . ' reference ->' .
                                                  print_r($regsName, 1), 'syncdata');
                                    $product_id = $regsName[0]['id_product'];
                                } else {
                                    $this->debbug('Selected by product with same name and reference' .
                                                  $regsName[$found]['id_product'] . ' reference ->' .
                                                  print_r($regsName, 1), 'syncdata');
                                    $product_id = $regsName[$found]['id_product'];
                                }
                                break;
                            }
                        }
                    }
                }
            }

            if ($product_id) {
                $product_exists = (int)Db::getInstance()->getValue(
                    sprintf(
                        'SELECT sl.slyr_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                        WHERE sl.ps_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product"',
                        $product_id,
                        $comp_id
                    )
                );

                if ($product_exists) {
                    $product_id = false;
                } else {
                    //Si encontramos el producto insertamos registro en tabla Slyr
                    Db::getInstance()->execute(
                        sprintf(
                            'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                            (ps_id, slyr_id, ps_type, comp_id, date_add)
                            VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                            $product_id,
                            $product['ID'],
                            'product',
                            $comp_id
                        )
                    );

                    return $product_id;
                }
            }
        }

        if (!$product_id) {
            $this->debbug('Find product with the same name in one of the languages.' .
                          $occurence, 'syncdata');
            foreach ($this->shop_languages as $lang) {
                $product_name_index = '';
                $product_name_index_search = 'product_name_' . $lang['iso_code'];
                if (isset($product['data']['product_name'])
                    && !empty($product['data']['product_name'])
                    && !isset($schema['product_name']['language_code'])) {
                    $product_name_index = 'product_name';
                } elseif (isset(
                    $product['data'][$product_name_index_search],
                    $schema[$product_name_index_search]['language_code']
                )
                    && !empty($product['data'][$product_name_index_search])
                    && $schema[$product_name_index_search]['language_code'] == $lang['iso_code']) {
                    $product_name_index = 'product_name_' . $lang['iso_code'];
                }

                if ($product_name_index != '' && isset($product['data'][$product_name_index])
                    && !empty($product['data'][$product_name_index])) {
                    if (!$occurrence_found) {
                        $occurence = ' product name :"' . $product['data'][$product_name_index] . '" ';
                        $occurrence_found = true;
                    }


                    //Buscamos productos con nombre similar
                    $regsName = $productObject->searchByName(
                        $lang['id_lang'],
                        $product['data'][$product_name_index]
                    );

                    if ($regsName && count($regsName) > 0) {
                        $found = false;
                        //Buscamos producto con nombre idéntico, si no lo encontramos nos quedamos con el primero
                        foreach ($regsName as $keyName => $regName) {
                            $this->debbug('Check if it has been created from '.
                             'another sales layer company. ' .
                                          $occurence, 'syncdata');
                            //Verificamos si el producto existe en otra empresa, para no sobreescribir datos.
                            $product_exists_other_comp = (int)Db::getInstance()->getValue(
                                sprintf(
                                    'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                                    WHERE sl.ps_id = "%s" AND sl.comp_id != "%s" AND sl.ps_type = "product"',
                                    $regName['id_product'],
                                    $comp_id
                                )
                            );
                            if (!$product_exists_other_comp) {
                                $found = $keyName;
                            }
                        }

                        if ($found === false) {
                            $product_exists_other_comp = (int)Db::getInstance()->getValue(
                                sprintf(
                                    'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                                    WHERE sl.ps_id = "%s" AND sl.comp_id != "%s" AND sl.ps_type = "product"',
                                    $regsName[0]['id_product'],
                                    $comp_id
                                )
                            );
                            if (!$product_exists_other_comp) {
                                $product_id = $regsName[0]['id_product'];
                            }
                        } else {
                            $product_id = $regsName[$found]['id_product'];
                        }

                        if ($product_id) {
                            $this->debbug('Product found By Name. ' .
                                          $occurence, 'syncdata');
                            $product_exists = (int)Db::getInstance()->getValue(
                                sprintf(
                                    'SELECT sl.slyr_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                                    WHERE sl.ps_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product"',
                                    $product_id,
                                    $comp_id
                                )
                            );

                            if ($product_exists) {
                                $product_id = false;
                            } else {
                                //Si encontramos el producto insertamos registro en tabla Slyr
                                Db::getInstance()->execute(
                                    sprintf(
                                        'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                                        (ps_id, slyr_id, ps_type, comp_id, date_add)
                                        VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                        $product_id,
                                        $product['ID'],
                                        'product',
                                        $comp_id
                                    )
                                );
                                unset($productObject);

                                return $product_id;
                            }
                        }
                    }
                }
            }
        }

        if (!$product_id) {
            $this->debbug('Creating new product. ' . $occurence, 'syncdata');
            //Creamos el producto

            $productObject = new Product();
            $productObject->name = array();
            $productObject->description = array();
            $productObject->description_short = array();
            $productObject->link_rewrite = array();

            foreach ($this->shop_languages as $lang) {
                $product_name_index = '';
                $product_name_index_search = 'product_name_' . $lang['iso_code'];
                if (isset($product['data']['product_name'])
                    && !empty($product['data']['product_name'])
                    && !isset($schema['product_name']['language_code'])) {
                    $product_name_index = 'product_name';
                } elseif (isset(
                    $product['data'][$product_name_index_search],
                    $schema[$product_name_index_search]['language_code']
                )
                    && !empty($product['data'][$product_name_index_search])
                    && $schema[$product_name_index_search]['language_code'] == $lang['iso_code']) {
                    $product_name_index = 'product_name_' . $lang['iso_code'];
                }

                if ($product_name_index != '' && isset($product['data'][$product_name_index])
                    && !empty($product['data'][$product_name_index])) {
                    $product_name = $this->slValidateCatalogName(
                        $product['data'][$product_name_index],
                        'Product'
                    );
                    $productObject->name[$lang['id_lang']] = $product_name;

                    (isset($product['data']['friendly_url'])
                        && $product['data']['friendly_url'] != '') ?
                        $friendly_url = $product['data']['friendly_url'] : $friendly_url = $product_name;

                    $productObject->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($friendly_url);

                    if ($lang['id_lang'] != $this->defaultLanguage) {
                        if (!isset($productObject->name[$this->defaultLanguage]) ||
                            (isset($productObject->name[$this->defaultLanguage])
                                && ($productObject->name[$this->defaultLanguage] == null
                                    || $productObject->name[$this->defaultLanguage] == ''))) {
                            $productObject->name[$this->defaultLanguage] = $product_name;
                        }
                        $productObject->link_rewrite[$this->defaultLanguage] = Tools::link_rewrite($friendly_url);
                    }
                }
            }
            $productObject->active = true;
            $productObject->date_add = date('Y-m-d H:i:s');

            isset($product['data']['product_reference']) ? $product_reference = $this->slValidateReference(
                $product['data']['product_reference']
            ) : $product_reference = '';
            if (Tools::strlen($product_reference) > 32) {
                $product_reference = Tools::substr($product_reference, 0, 31);
            }
            $productObject->reference = $product_reference;

            if (isset($product['data']['minimal_quantity']) && !empty($product['data']['minimal_quantity'])
                && is_numeric(
                    $product['data']['minimal_quantity']
                )
            ) {
                $productObject->minimal_quantity = $product['data']['minimal_quantity'];
            } else {
                $productObject->minimal_quantity = 1;
            }
            if (!isset($product['data']['product_available_for_order'])) {
                $productObject->available_for_order = true;
            }

            try {
                $productObject->save();
            } catch (Exception $e) {
                $this->debbug(
                    '## Error. Cannot Sync product ' . $occurence . ' ->' .
                    print_r($e->getMessage(), 1) .
                    ' line->' . $e->getLine() . ' trace->' . print_r($e->getTrace(), 1),
                    'syncdata'
                );
            }

            if ($productObject->id) {
                Db::getInstance()->execute(
                    sprintf(
                        'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                        (ps_id, slyr_id, ps_type, comp_id, date_add)
                        VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                        $productObject->id,
                        $product['ID'],
                        'product',
                        $comp_id
                    )
                );
            }

            $return_id = $productObject->id;
            unset($productObject);

            return $return_id;
        }
        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
        unset($productObject);

        return $product_id;
    }

    /**
     * synchronize a product discount
     *
     * @param  $product_id              string id of product
     * @param  $product_discount_1_data array array with first product discount data
     * @param  $product_discount_2_data array with second product discount data
     * @param  $shop_id                 int id of the shop for the discounts
     * @return bool if it synchronizes correctly
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    protected function syncProductDiscount(
        $product_id,
        $product_discount_1_data,
        $product_discount_2_data,
        $shop_id
    ) {
        $specificPrice = new SpecificPrice();
        $prodSPsExisting = $specificPrice->getByProductId($product_id);
        $protected_ids = array();
        $keysGen = array(
            'id_specific_price_rule',
            'id_cart',
            'id_shop',
            'id_shop_group',
            'id_currency',
            'id_country',
            'id_group',
            'id_customer',
            'id_product_attribute',
        );
        $id_specific_price_1 = $id_specific_price_2 = 0;


        if (!empty($prodSPsExisting)) {
            foreach ($prodSPsExisting as $keySP => $prodSPExisting) {
                if ($id_specific_price_1 != 0 && $id_specific_price_2 != 0) {
                    break;
                }

                if ($prodSPExisting['id_shop'] == $shop_id ||
                    ($shop_id == 0 && $prodSPExisting['id_shop'] == Shop::getContextShopID())
                    ) {
                    $this->debbug('Id shop for discount edit existing->' .
                                  print_r($prodSPExisting['id_shop'], 1)
                        . ' and id of shop for edit->' . print_r($shop_id, 1), 'syncdata');

                    if ($id_specific_price_1 == 0 && !empty($product_discount_1_data['reduction'])) {
                        $this->debbug('Id shop for discount edit existing->' .
                                      print_r($prodSPExisting['id_shop'], 1)
                                      . ' and id of shop for edit->' . print_r($shop_id, 1), 'syncdata');
                        $id_specific_price_1 = $prodSPExisting['id_specific_price'];
                        unset($prodSPsExisting[$keySP]);
                        continue;
                    }

                    if ($id_specific_price_2 == 0 && !empty($product_discount_2_data['reduction'])) {
                        $id_specific_price_2 = $prodSPExisting['id_specific_price'];
                        unset($prodSPsExisting[$keySP]);
                        continue;
                    }
                }
            }
        }

        if (!empty($product_discount_1_data['reduction'])) {
            $this->debbug('Data for sync discount 1->' .
                          print_r($product_discount_1_data, 1)
                          . ' and id of shop for edit->' . print_r($shop_id, 1), 'syncdata');
            if ($product_discount_1_data['type_reduction'] == 'percentage') {
                $product_discount_1_data['reduction'] /= 100;
            }

            if ($id_specific_price_1 == 0) {
                //We generate a new discount with generic values.
                $specificPrice->id_product = $product_id;
                $specificPrice->reduction = $product_discount_1_data['reduction'];
                $specificPrice->reduction_type = $product_discount_1_data['type_reduction'];
                foreach ($keysGen as $keyGen) {
                    $specificPrice->{"$keyGen"} = 0;
                }

                $specificPrice->price = (float)'-1.000000';
                $specificPrice->reduction_tax = 1;
                $specificPrice->from_quantity = $product_discount_1_data['from_quantity'];
                $specificPrice->from = $product_discount_1_data['from_time'];
                $specificPrice->to   = $product_discount_1_data['to_time'];
                $specificPrice->id_shop = $shop_id;
                $specificPrice->add();
                $protected_ids[] = $specificPrice->id;
                $this->debbug('Adding new special price->' .
                              print_r($product_discount_1_data, 1), 'syncdata');
            } else {
                //We update the data from the first existing discount.
                $specificPriceProduct = new SpecificPrice($id_specific_price_1);
                $specificPriceProduct->reduction = $product_discount_1_data['reduction'];
                $specificPriceProduct->reduction_type = $product_discount_1_data['type_reduction'];
                $specificPriceProduct->from_quantity = $product_discount_1_data['from_quantity'];
                $specificPriceProduct->from    = $product_discount_1_data['from_time'];
                $specificPriceProduct->to      = $product_discount_1_data['to_time'];
                $specificPriceProduct->id_shop = $shop_id;
                $specificPriceProduct->update();
                $protected_ids[] = $id_specific_price_1;

                unset($specificPriceProduct);
            }
        }

        if (!empty($product_discount_2_data['reduction'])) {
            $this->debbug('Data for sync discount 2->' .
                          print_r($product_discount_2_data, 1)
                          . ' and id of shop for edit->' . print_r($shop_id, 1), 'syncdata');

            if ($product_discount_2_data['type_reduction'] == 'percentage') {
                $product_discount_2_data['reduction'] /= 100;
            }

            if ($id_specific_price_2 == 0) {
                //We generate a new discount with generic values.
                $specificPrice->id_product = $product_id;
                $specificPrice->reduction = $product_discount_2_data['reduction'];
                $specificPrice->reduction_type = $product_discount_2_data['type_reduction'];
                foreach ($keysGen as $keyGen) {
                    $specificPrice->{"$keyGen"} = 0;
                }

                $specificPrice->price = (float)"-1.000000";
                $specificPrice->reduction_tax = 1;
                $specificPrice->from_quantity = $product_discount_2_data['from_quantity'];
                $specificPrice->from = $product_discount_2_data['from_time'];
                $specificPrice->to = $product_discount_2_data['to_time'];
                $specificPrice->id_shop = $shop_id;
                $specificPrice->add();
                $protected_ids[] = $specificPrice->id;
            } else {
                //We update the data from the second existing discount.
                $specificPriceProduct = new SpecificPrice($id_specific_price_2);
                $specificPriceProduct->reduction = $product_discount_2_data['reduction'];
                $specificPriceProduct->reduction_type = $product_discount_2_data['type_reduction'];
                $specificPriceProduct->from_quantity = $product_discount_2_data['from_quantity'];
                $specificPriceProduct->from = $product_discount_2_data['from_time'];
                $specificPriceProduct->to   = $product_discount_2_data['to_time'];
                $specificPriceProduct->id_shop = $shop_id;
                $specificPriceProduct->update();
                $protected_ids[] = $id_specific_price_2;
                unset($specificPriceProduct);
            }
        }
        $prodSPsExisting = $specificPrice->getByProductId($product_id);
        // delete specific price
        foreach ($prodSPsExisting as $specific_price) {
            $this->debbug('Entry for a delete special price with same ->' .
                          print_r($specific_price, 1)
                          . ' and id of shop for delete->' . print_r($shop_id, 1), 'syncdata');
            if (($specific_price['id_shop'] == $shop_id ||
                 ($shop_id == 0 && $specific_price['id_shop'] == Shop::getContextShopID())) &&
                !in_array($specific_price['id_specific_price'], $protected_ids, false)) {
                $this->debbug('Deleting special price->' .
                              print_r($specific_price['id_specific_price'], 1), 'syncdata');
                $specificPriceDelete = new SpecificPrice($specific_price['id_specific_price']);
                $specificPriceDelete->delete();
                unset($specificPriceDelete);
            }
        }
        unset($specificPrice);

        return true;
    }

    /**
     * synchronize seosaproductlabels of a product - Custom module
     *
     * @param $id_product         string id of product
     * @param $seosaproductlabels string|array  from product
     * @param $shop_id            string id of the shop
     * @return void|bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    protected function syncSeosaProductLabels(
        $id_product,
        $seosaproductlabels,
        $shop_id
    ) {
        if (is_string($seosaproductlabels) && $seosaproductlabels != '') {
            $seosa_product_labels = explode(',', $seosaproductlabels);
        } else {
            if (is_array($seosaproductlabels) && !empty($seosaproductlabels)) {
                $seosa_product_labels = $seosaproductlabels;
            } else {
                return false;
            }
        }

        foreach ($seosa_product_labels as $keySPL => $seosa_product_label) {
            $seosa_product_labels[$keySPL] = Tools::strtolower(trim($seosa_product_label));
        }

        $seosa_product_existing_labels = Db::getInstance()->executeS(
            sprintf(
                'SELECT so.id_product_label_location,so.id_product_label
FROM ' . $this->seosa_product_labels_location_table . ' so WHERE so.id_product = "%s" and so.id_shop = "%s"',
                $id_product,
                $shop_id
            )
        );

        $seosa_product_existing_labels_rew = array();

        foreach ($seosa_product_existing_labels as $seosa_product_existing_label) {
            $content = $seosa_product_existing_label['id_product_label'];
            $index = $seosa_product_existing_label['id_product_label_location'];
            $seosa_product_existing_labels_rew[$index] = $content;
        }

        $seosa_existing_labels = Db::getInstance()->executeS(
            sprintf('SELECT id_product_label, name FROM ' . $this->seosa_product_labels_table)
        );

        if (!empty($seosa_existing_labels)) {
            foreach ($seosa_existing_labels as $seosa_existing_label) {
                if (in_array(
                    Tools::strtolower(trim($seosa_existing_label['name'])),
                    $seosa_product_labels,
                    false
                )) {
                    if (in_array(
                        $seosa_existing_label['id_product_label'],
                        $seosa_product_existing_labels_rew,
                        false
                    )
                    ) {
                        unset(
                            $seosa_product_existing_labels_rew[array_search(
                                $seosa_existing_label['id_product_label'],
                                $seosa_product_existing_labels_rew
                            )]
                        );
                    } else {
                        Db::getInstance()->execute(
                            sprintf(
                                'INSERT INTO ' . $this->seosa_product_labels_location_table
                                . '(id_product, id_shop, id_product_label, position)
                                VALUES("%s", "%s", "%s", "%s")',
                                $id_product,
                                $shop_id,
                                $seosa_existing_label['id_product_label'],
                                'center-center'
                            )
                        );
                    }
                }
            }
        }

        if (!empty($seosa_product_existing_labels_rew)) {
            foreach (array_keys($seosa_product_existing_labels_rew) as $keySPELR) {
                Db::getInstance()->execute(
                    sprintf(
                        'DELETE FROM ' . $this->seosa_product_labels_location_table . ' 
                        WHERE id_product_label_location = "%s"',
                        $keySPELR
                    )
                );
            }
        }
    }

    /**
     * synchronize features of a product by language
     * @param $id_product string id of product
     * @param $product    array values from product
     * @param $schema     array schema of array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function syncFeatures(
        $id_product,
        &$product,
        $schema
    ) {
        if (isset($product['data']['product_reference']) && !empty($product['data']['product_reference'])) {
            $occurence = ' product reference : "' . $product['data']['product_reference'] . '"';
        } elseif (isset($product['data']['product_name']) && !empty($product['data']['product_name'])) {
            $occurence = ' product name :"' . $product['data']['product_name'] . '"';
        } else {
            $occurence = ' ID :' . $product['ID'];
        }

        $features_founded = array();
        try {
            $product['data'] = $this->removePredefinedFieldsBeforeFeatures($product['data'], $schema);
        } catch (Exception $e) {
            $this->debbug(
                '## Error. ' . $occurence . ' Removing element predefined. Problem found->' . $e->getMessage(),
                'syncdata'
            );
        }

        $this->debbug(
            'Entering to sync Features id_prod->' . $id_product . ' product -> ' . print_r($product['data'], 1),
            'syncdata'
        );
        try {
            foreach ($this->shop_languages as $lang) {
                $this->debbug(
                    'Verifying language ->' . $lang['id_lang'] . ' lg_code -> ' . print_r($lang['iso_code'], 1),
                    'syncdata'
                );

                $featuresFields = Feature::getFeatures($lang['id_lang'], false);
                if (!empty($featuresFields)) {
                    foreach ($featuresFields as $featureField) {
                        $this->debbug(
                            'Verifying feature ->' . $featureField['name'] .
                            ' language ->' . $lang['id_lang'] . ' lg_code -> ' . print_r(
                                $lang['iso_code'],
                                1
                            ),
                            'syncdata'
                        );
                        $id_feature = $featureField['id_feature'];
                        $name = $featureField['name'];
                        $values_for_process = array();
                        //$new_name = str_replace(' ', '_', $name);
                        $new_name = $name;
                        $new_name_index_language = $new_name . '_' . $lang['iso_code'];
                        $sanitized_name_index_search = $this->removeAccents(Tools::strtolower($new_name));
                        $sanitized_name_index_search_languae = $sanitized_name_index_search . '_' . $lang['iso_code'];
                        $sanitized_ant_version = str_replace(
                            '_',
                            ' ',
                            $sanitized_name_index_search
                        ) . '_' . $lang['iso_code'];
                        $sanitized_ant_version_space = $sanitized_name_index_search . '_' . $lang['iso_code'];

                        if (isset($product['data'][$new_name_index_language])) {
                            $feature_index_selected = $new_name_index_language;
                        } elseif (isset($product['data'][$sanitized_name_index_search_languae])
                            && !empty($product['data'][$sanitized_name_index_search_languae])) {
                            $feature_index_selected = $sanitized_name_index_search_languae;
                        } elseif (isset($product['data'][$sanitized_name_index_search])
                            && !empty($product['data'][$sanitized_name_index_search])) {
                            $feature_index_selected = $sanitized_name_index_search;
                        } elseif (isset($product['data'][$sanitized_ant_version])
                            && !empty($product['data'][$sanitized_ant_version])) {
                            $feature_index_selected = $sanitized_ant_version;
                        } elseif (isset($product['data'][$sanitized_ant_version_space])
                            && !empty($product['data'][$sanitized_ant_version_space])) {
                            $feature_index_selected = $sanitized_ant_version_space;
                        } else {
                            $feature_index_selected = $new_name;
                        }

                        $this->debbug(
                            'in feature  $new_name->' . print_r(
                                $new_name,
                                1
                            )/* . ' in $product[data]->' . print_r(
                                $product['data'],
                                1
                            )*/ . ' $feature_index_selected->' . print_r(
                                $feature_index_selected,
                                1
                            ),
                            'syncdata'
                        );

                        if (!array_key_exists($feature_index_selected, $product['data'])) {
                            $this->debbug(
                                'That feature has not been found in the oroduct information->'
                                . $lang['id_lang'] . ' lg_code -> ' . print_r(
                                    $lang['iso_code'],
                                    1
                                ),
                                'syncdata'
                            );
                            //No existe la característica en el producto.
                            continue;
                        } else {
                            $this->debbug('Feature found in product array ', 'syncdata');

                            $count_values = 0;


                            $ids_feature_values = Db::getInstance()->executeS(
                                sprintf(
                                    'SELECT id_feature_value FROM ' . $this->feature_product_table . '
                                    where id_feature = "%s"  AND id_product = "€s" ',
                                    $id_feature,
                                    $id_product
                                )
                            );
                            $this->debbug(
                                'After search feature values ->' . print_r(
                                    $ids_feature_values,
                                    1
                                ),
                                'syncdata'
                            );

                            if (count($ids_feature_values)) {
                                foreach ($ids_feature_values as $num_of_position => $featureValue) {
                                    $id_feature_value = $featureValue['id_feature_value'];
                                    $featureValue = new FeatureValue($id_feature_value);

                                    $this->debbug(
                                        'Feature found by the id and these are its values in all languages ->'
                                        . print_r(
                                            $featureValue->value,
                                            1
                                        ) . ' in the current language ->' .
                                        $featureValue->value[$lang['id_lang']],
                                        'syncdata'
                                    );

                                    foreach ($this->shop_languages as $lang_sub) {
                                        /**
                                         * In the product there is characteristica with the same name
                                         */
                                        $feature_name_index                  = '';
                                        $feature_name_index_search           = $new_name . '_' . $lang_sub['iso_code'];
                                        $sanitized_name_index_search         = $this->removeAccents(
                                            Tools::strtolower($new_name)
                                        );
                                        $sanitized_name_index_search_languae = $sanitized_name_index_search .
                                                                           '_' . $lang_sub['iso_code'];
                                        $sanitized_ant_version               = str_replace(
                                            '_',
                                            ' ',
                                            $sanitized_name_index_search
                                        ) . '_' . $lang['iso_code'];
                                        $sanitized_ant_version_space         = $sanitized_name_index_search .
                                                                           '_' . $lang['iso_code'];
                                        $this->debbug(
                                            'In Search ->' . print_r($feature_name_index_search, 1),
                                            'syncdata'
                                        );
                                        if (isset(
                                            $product['data'][$feature_name_index_search],
                                            $schema[ $feature_name_index_search ]['language_code']
                                        ) &&
                                         $schema[ $feature_name_index_search ]['language_code'] ==
                                         $lang_sub['iso_code']) {
                                            $this->debbug(
                                                'Entering by $feature_name_index_search->' . print_r(
                                                    $feature_name_index_search,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            $feature_name_index = $feature_name_index_search;
                                        } elseif (isset($product['data'][ $new_name ]) &&
                                                  !empty($product['data'][ $new_name ])
                                               && ! isset($schema[ $new_name ]['language_code'])) {
                                            $this->debbug('Entry from $new_name->' .
                                                          print_r($new_name, 1), 'syncdata');
                                            $feature_name_index = $new_name;
                                        } elseif (isset($product['data'][ $sanitized_name_index_search ])
                                               && ! empty($product['data'][ $sanitized_name_index_search ])) {
                                            $feature_name_index = $sanitized_name_index_search;
                                            $this->debbug(
                                                'Entry from $sanitized_name_index_search->' . print_r(
                                                    $sanitized_name_index_search,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                        } elseif (isset($product['data'][ $sanitized_name_index_search_languae ])
                                               && ! empty($product['data'][ $sanitized_name_index_search_languae ])) {
                                            $this->debbug(
                                                'Entry from $sanitized_name_index_search_languae->' . print_r(
                                                    $sanitized_name_index_search_languae,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            $feature_name_index = $sanitized_name_index_search;
                                        } elseif (isset($product['data'][ $sanitized_ant_version ])
                                               && ! empty($product['data'][ $sanitized_ant_version ])) {
                                            $this->debbug(
                                                'Entry from $sanitized_ant_version->' . print_r(
                                                    $sanitized_ant_version,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            $feature_name_index = $sanitized_ant_version;
                                        } elseif (isset($product['data'][ $sanitized_ant_version_space ])
                                               && ! empty($product['data'][ $sanitized_ant_version_space ]) &&
                                               $schema[ $sanitized_ant_version_space ]['language_code'] ==
                                               $lang_sub['iso_code']) {
                                            $this->debbug(
                                                'Entry from $sanitized_ant_version_space->' . print_r(
                                                    $sanitized_ant_version_space,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            $feature_name_index = $sanitized_ant_version_space;
                                        } else {
                                            continue;
                                        }


                                        if (is_array($product['data'][ $feature_name_index ])) {
                                            $this->debbug(
                                                'Content as array print in same line  separated with ' .
                                                '"," $sanitized_ant_version_space->' . print_r(
                                                    $product['data'][ $feature_name_index ],
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                                $values_arr = $product['data'][ $feature_name_index ];
                                            } else {
                                                $values_arr =  array(reset($product['data'][ $feature_name_index ]));
                                            }
                                        } else {
                                            $this->debbug(
                                                'Content as string print in same line ' .
                                                ' $sanitized_ant_version_space->' . print_r(
                                                    $product['data'][ $feature_name_index ],
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                                $values_arr = explode('|', $product['data'][ $feature_name_index ]);
                                            } else {
                                                $values_arr =  array($product['data'][ $feature_name_index ]);
                                            }
                                        }
                                        if (isset($values_arr[$num_of_position])) {
                                            $value = $this->slValidateCatalogName($values_arr[$num_of_position]);
                                        } else {
                                            continue;
                                        }


                                        if (Tools::strlen($value) > 255) {
                                            $value = Tools::substr($value, 0, 250);
                                        }

                                        if ((is_string($value) && $value == '') || $value == null
                                         || (is_numeric(
                                             $value
                                         )
                                              && $value == 0)
                                         ) {
                                            $this->debbug(
                                                'Value of ' . $feature_name_index .
                                                ' is empty, jumping to another feature ->' . print_r(
                                                    $value,
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            //No se puede dejar en blanco un valor custom, se auto-rellena.
                                            // Podría ser un espacio, pero seguiría mostrando la
                                            // característica en el front.
                                            continue;
                                        }

                                        if (preg_match('/:(custom|CUSTOM)/i', $value)) {
                                            // si viene con comando para crear caracteristica
                                            // como custom la quitamos rellenamos como custom

                                            $value = trim(str_replace(array( ':custom', ':CUSTOM' ), '', $value));
                                            $create_as_custom = true;
                                        } else {
                                            $create_as_custom = $this->create_new_features_as_custom;
                                        }

                                        if (!empty($value)) {
                                            $values_for_process[ $lang_sub['id_lang'] ] = $value;
                                            $count_values++;
                                        }

                                        $featureValue->value[ $lang_sub['id_lang'] ] = $value;
                                    }

                                    if ($featureValue->custom == 1) {
                                        $this->debbug(
                                            'Is custom value ' . print_r($featureValue->value, 1),
                                            'syncdata'
                                        );

                                        try {
                                            $this->debbug(
                                                'Saving changes in feature ->' .
                                                $featureValue->value . ' lg_code -> ' . print_r(
                                                    $lang['iso_code'],
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                            $featureValue->save();

                                            if ($count_values == 0) {
                                                //$features_founded[] = $id_feature_value;
                                                /**
                                                 * This value has not been found in any of the languages we will
                                                 * verify if the product exists and if it is not selected
                                                 */

                                                $feature_value_exist = Db::getInstance()->executeS(
                                                    sprintf(
                                                        'SELECT id_feature_value FROM ' . $this->feature_product_table .
                                                        ' WHERE id_feature =  "%s"  AND  id_product = "%s"
                                                        AND id_feature_value = "%s" ',
                                                        $id_feature,
                                                        $id_product,
                                                        $id_feature_value
                                                    )
                                                );

                                                if (empty($feature_value_exist)) {
                                                    $this->debbug(
                                                        'Value is null, removing the feature from ' .
                                                        'this product but is custom ->'
                                                        . $featureValue->value . ' lg_code -> ' . print_r(
                                                            $lang['iso_code'],
                                                            1
                                                        ),
                                                        'syncdata'
                                                    );
                                                    // Si el valor de la característica del producto es nulo,
                                                    //eliminamos la relación.
                                                    Db::getInstance()->execute(
                                                        sprintf(
                                                            'DELETE FROM ' . $this->feature_product_table .
                                                            ' WHERE id_feature = "%s" and id_product = "%s" 
                                                            and id_feature_value = "%s"',
                                                            $id_feature,
                                                            $id_product,
                                                            $id_feature_value
                                                        )
                                                    );
                                                }
                                            }
                                        } catch (Exception $e) {
                                            $this->debbug(
                                                '## Error. ' . $occurence . ' save Feature->' . print_r(
                                                    $e->getMessage(),
                                                    1
                                                ),
                                                'syncdata'
                                            );
                                        }
                                    } else {
                                        $this->debbug(
                                            'It is not a custom value  ' . print_r($featureValue->value, 1),
                                            'syncdata'
                                        );

                                        if ($count_values == 0) {


                                        /**
                                         * This value has not been found in any of the languages we will
                                         * verify if the product exists and if it is not selected
                                         */

                                            $feature_value_exist = Db::getInstance()->executeS(
                                                sprintf(
                                                    'SELECT id_feature_value FROM ' . $this->feature_product_table .
                                                    ' WHERE id_feature =  "%s"  AND  id_product = "%s" 
                                                    AND id_feature_value = "%s" ',
                                                    $id_feature,
                                                    $id_product,
                                                    $id_feature_value
                                                )
                                            );

                                            if (empty($feature_value_exist)) {
                                                $this->debbug(
                                                    'Value is null removing the feature from this product ->' . print_r(
                                                        $featureValue->value,
                                                        1
                                                    ) . ' lg_code -> ' . print_r($lang['iso_code'], 1),
                                                    'syncdata'
                                                );
                                                // Si el valor de la característica del producto es nulo,
                                                //eliminamos la relación.
                                                Db::getInstance()->execute(
                                                    sprintf(
                                                        'DELETE FROM ' . $this->feature_product_table .
                                                        ' WHERE id_feature = "%s" and id_product = "%s" 
                                                        AND id_feature_value = "%s"',
                                                        $id_feature,
                                                        $id_product,
                                                        $id_feature_value
                                                    )
                                                );
                                            }
                                        } else {
                                            $id_feature_value_update = $this->searchFeatureValue(
                                                $id_feature,
                                                null,
                                                $values_for_process,
                                                $create_as_custom
                                            );// $id_product

                                            if ($id_feature_value_update != 0) {
                                                $this->debbug(
                                                    'Update value ' . print_r(
                                                        $id_feature,
                                                        1
                                                    ) . ' value->' . $value . ' id_product ->' . $id_product .
                                                    ' lg_code -> ' . print_r(
                                                        $lang['iso_code'],
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                                try {
                                                    Db::getInstance()->execute(
                                                        sprintf(
                                                            'UPDATE ' . $this->feature_product_table .
                                                            ' SET id_feature_value = "%s" WHERE id_feature = "%s" 
                                                            AND id_product = "%s" AND id_feature_value = "%s"',
                                                            $id_feature_value_update,
                                                            $id_feature,
                                                            $id_product,
                                                            $id_feature_value
                                                        )
                                                    );
                                                } catch (Exception $e) {
                                                    $this->debbug(
                                                        '## Error. ' . $occurence . ' Updating feature value->'
                                                         . $e->getMessage(),
                                                        'syncdata'
                                                    );
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $this->debbug('Feature value id not found, it will be created now ', 'syncdata');

                                foreach ($this->shop_languages as $lang_sub) {

                                    /**
                                     * En prestashop existe characteristica con el mismo nombre
                                     */
                                    try {
                                        try {
                                            $feature_name_index = '';
                                            $feature_name_index_search = $new_name . '_' . $lang_sub['iso_code'];
                                            $sanitized_name_index_search = $this->removeAccents(
                                                Tools::strtolower($new_name)
                                            );
                                            $sanitized_name_index_search_languae = $sanitized_name_index_search .
                                                '_' . $lang_sub['iso_code'];
                                            $sanitized_ant_version = str_replace(
                                                '_',
                                                ' ',
                                                $sanitized_name_index_search
                                            ) . '_' . $lang['iso_code'];
                                            $sanitized_ant_version_space = $sanitized_name_index_search .
                                                '_' . $lang['iso_code'];
                                            $this->debbug(
                                                'Searching ->' . print_r($feature_name_index_search, 1),
                                                'syncdata'
                                            );
                                            if (isset(
                                                $product['data'][$feature_name_index_search],
                                                $schema[$feature_name_index_search]['language_code']
                                            )
                                                &&
                                                $schema[$feature_name_index_search]['language_code'] ==
                                                $lang_sub['iso_code']
                                            ) {
                                                $this->debbug(
                                                    'Entering by $feature_name_index_search->' . print_r(
                                                        $feature_name_index_search,
                                                        1
                                                    ) . ' value->' . print_r(
                                                        $product['data'][$feature_name_index_search],
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                                $feature_name_index = $feature_name_index_search;
                                            } elseif (isset($product['data'][$new_name])
                                                && !empty($product['data'][$new_name])
                                                && !isset($schema[$new_name]['language_code'])) {
                                                $this->debbug(
                                                    'Entering by $new_name->' . print_r($new_name, 1)
                                                    . ' value->' . print_r($product['data'][$new_name], 1),
                                                    'syncdata'
                                                );
                                                $feature_name_index = $new_name;
                                            } elseif (isset($product['data'][$sanitized_name_index_search])
                                                && !empty($product['data'][$sanitized_name_index_search])) {
                                                $feature_name_index = $sanitized_name_index_search;
                                                $this->debbug(
                                                    'Entering by $sanitized_name_index_search->' . print_r(
                                                        $sanitized_name_index_search,
                                                        1
                                                    ) . ' value ->' .
                                                    print_r($product['data'][$sanitized_name_index_search], 1),
                                                    'syncdata'
                                                );
                                            } elseif (isset($product['data'][$sanitized_name_index_search_languae])
                                                && !empty($product['data'][$sanitized_name_index_search_languae])) {
                                                $this->debbug(
                                                    'Entering by $sanitized_name_index_search_languae->' .
                                                    print_r(
                                                        $sanitized_name_index_search_languae,
                                                        1
                                                    ) . ' value ->' .
                                                    print_r($product['data'][$sanitized_name_index_search_languae], 1),
                                                    'syncdata'
                                                );
                                                $feature_name_index = $sanitized_name_index_search_languae;
                                            } elseif (isset($product['data'][$sanitized_ant_version])
                                                && !empty($product['data'][$sanitized_ant_version])) {
                                                $this->debbug(
                                                    'Entering by $sanitized_ant_version->' . print_r(
                                                        $sanitized_ant_version,
                                                        1
                                                    ) . ' value ->' .
                                                    print_r($product['data'][$sanitized_ant_version], 1),
                                                    'syncdata'
                                                );
                                                $feature_name_index = $sanitized_ant_version;
                                            } elseif (isset($product['data'][$sanitized_ant_version_space])
                                                && !empty($product['data'][$sanitized_ant_version_space]) &&
                                                $schema[$sanitized_ant_version_space]['language_code'] ==
                                                $lang_sub['iso_code']) {
                                                $this->debbug(
                                                    'Entering by $sanitized_ant_version_space->' .
                                                    print_r(
                                                        $sanitized_ant_version_space,
                                                        1
                                                    ) . ' value ->' .
                                                    print_r($product['data'][$sanitized_ant_version_space], 1),
                                                    'syncdata'
                                                );
                                                $feature_name_index = $sanitized_ant_version_space;
                                            } else {
                                                continue;
                                            }
                                        } catch (Exception $e) {
                                            $this->debbug(
                                                '## Error. ' . $occurence . ' In existing feature 
                                                $feature_name_index->' . print_r(
                                                    $feature_name_index,
                                                    1
                                                ) . ' problem->' . $e->getMessage(),
                                                'syncdata'
                                            );
                                        }
                                        $this->debbug(
                                            'Before check array of this Language->' . print_r(
                                                $feature_name_index,
                                                1
                                            ) . ' id_lang ->' . print_r($lang_sub['id_lang'], 1),
                                            'syncdata'
                                        );
                                        if (isset($product['data'][$feature_name_index]) &&
                                            is_array($product['data'][$feature_name_index])) {
                                            if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                                $values_arr =  $product['data'][$feature_name_index];
                                            } else {
                                                $values_arr =  array(reset($product['data'][$feature_name_index]));
                                            }
                                        } else {
                                            if (isset($product['data'][$feature_name_index])) {
                                                if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                                    $values_arr = explode('|', $product['data'][$feature_name_index]);
                                                } else {
                                                    $values_arr =  array($product['data'][$feature_name_index]);
                                                }
                                            } else {
                                                $this->debbug(
                                                    'array is empty ' . $feature_name_index . ' ->' . print_r(
                                                        (isset($product['data'][$feature_name_index]) ?
                                                            $product['data'][$feature_name_index]:'empty'),
                                                        1
                                                    ) . ' id_lang ->' . print_r($lang_sub['id_lang'], 1),
                                                    'syncdata'
                                                );
                                                $values_arr = array();
                                            }
                                        }
                                        $this->debbug(
                                            'after check values in this language->' . print_r(
                                                $feature_name_index,
                                                1
                                            ) . ' id_lang ->' . print_r($lang_sub['id_lang'], 1)
                                            . ' value->' . print_r($values_arr, 1),
                                            'syncdata'
                                        );

                                        foreach ($values_arr as $value) {
                                            $this->debbug(
                                                'Check value->' . print_r(
                                                    $value,
                                                    1
                                                ) . ' id_lang ->' . print_r($lang_sub['id_lang'], 1),
                                                'syncdata'
                                            );

                                            if (preg_match('/:(custom|CUSTOM)/', $value)) {
                                                // si viene con comando para crear caracteristica
                                                // como custom la quitamos rellenamos como custom

                                                $value = str_replace(array( ':custom', ':CUSTOM' ), '', $value);
                                                $create_as_custom = true;
                                            } else {
                                                $create_as_custom = $this->create_new_features_as_custom;
                                            }


                                            $value = $this->slValidateCatalogName($value);

                                            if (Tools::strlen($value) > 255) {
                                                $value = Tools::substr($value, 0, 250);
                                            }


                                            if ((is_string($value) && $value == '') || $value == null
                                                || (is_numeric(
                                                    $value
                                                )
                                                    && $value == 0)
                                            ) {
                                                $this->debbug(
                                                    'Value of ' . $feature_name_index . ' is empty, jumping to another
                                                     feature ->' . print_r(
                                                        $value,
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                                //No se puede dejar en blanco un valor custom, se auto-rellena.
                                                // Podría ser un espacio, pero seguiría mostrando la
                                                // característica en el front.
                                                continue;
                                            }

                                            if (!empty($value)) {
                                                $values_for_process[$lang_sub['id_lang']] = $value;
                                                $count_values++;
                                            }
                                            $id_feature_value = 0;
                                            try {
                                                $id_feature_value = $this->searchFeatureValue(
                                                    $id_feature,
                                                    $id_product,
                                                    $values_for_process,
                                                    $create_as_custom
                                                );
                                            } catch (Exception $e) {
                                                $this->debbug(
                                                    '## Error. ' . $occurence . ' In existing feature
                                                     searchFeatureValue. ->' . $e->getMessage() . ' line->' . print_r(
                                                        $e->getLine(),
                                                        1
                                                    ) . ' $id_feature->' . print_r(
                                                        $id_feature,
                                                        1
                                                    ) . ' $id_product->' . print_r(
                                                        $id_product,
                                                        1
                                                    ) . ' $values_for_process->' . print_r($values_for_process, 1),
                                                    'syncdata'
                                                );
                                            }

                                            if ($id_feature_value != 0) {
                                                $features_founded[] = $id_feature_value;

                                                try {
                                                    $query = sprintf(
                                                        'SELECT id_feature_value FROM ' .
                                                        $this->feature_product_table .
                                                        ' WHERE id_feature =  "%s"  AND  id_product = "%s" 
                                                            AND id_feature_value = "%s" ',
                                                        $id_feature,
                                                        $id_product,
                                                        $id_feature_value
                                                    );

                                                    $feature_value_exist = Db::getInstance()->executeS(
                                                        $query
                                                    );
                                                    $this->debbug(
                                                        'After select if exist this feature value in this product  ' .
                                                        print_r($feature_value_exist, 1) .
                                                        ' query used ->' . print_r($query, 1),
                                                        'syncdata'
                                                    );
                                                    if (empty($feature_value_exist)) {
                                                        if (version_compare(_PS_VERSION_, '1.7.0', '<') === true) {
                                                            //in older version Duplicate entry error for key 'PRIMARY'
                                                            Db::getInstance()->execute(
                                                                sprintf(
                                                                    'DELETE FROM ' . $this->feature_product_table .
                                                                    ' WHERE id_feature = "%s" AND id_product = "%s" ',
                                                                    $id_feature,
                                                                    $id_product
                                                                )
                                                            );
                                                        }

                                                        Db::getInstance()->execute(
                                                            sprintf(
                                                                'INSERT INTO ' . $this->feature_product_table .
                                                                '(id_feature, id_product, id_feature_value)
                                                                 VALUES("%s", "%s", "%s")',
                                                                $id_feature,
                                                                $id_product,
                                                                $id_feature_value
                                                            )
                                                        );
                                                    }
                                                } catch (Exception $e) {
                                                    $this->debbug(
                                                        '## Error. ' . $occurence . ' Inserting 
                                                        feature value ->' . $e->getMessage(),
                                                        'syncdata'
                                                    );
                                                }
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. ' . $occurence . ' In existing feature ' . $e->getMessage() .
                                            ' line->' . $e->getLine() .
                                            ' trace->' . print_r($e->getTrace(), 1),
                                            'syncdata'
                                        );
                                    }
                                }
                            }

                            /**
                             * Clean this feature from product array
                             */
                            try {
                                if (isset($product['data'][$feature_index_selected])) {
                                    if (isset($schema[$feature_index_selected]['language_code'])) {
                                        // if is attribute multi language
                                        $basename = $schema[$feature_index_selected]['basename'];
                                        foreach ($schema as $field_name => $values_schema) {
                                            if (isset($values_schema['basename'])
                                                && $values_schema['basename'] == $basename) {
                                                unset($product['data'][$field_name]);
                                            }
                                        }
                                    }
                                    unset($product['data'][$feature_index_selected]);
                                }
                            } catch (Exception $e) {
                                $this->debbug(
                                    '## Error. ' . $occurence . ' Cleaning this feature from product ' .
                                    $e->getMessage(),
                                    'syncdata'
                                );
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->debbug(
                '## Error. ' . $occurence . ' Recognizing existing feature.  problem found->' . print_r(
                    $e->getMessage() . ' line->' . $e->getLine(),
                    1
                ),
                'syncdata'
            );
        }

        /**
         * Create features that have not been recognized
         */
        try {
            if (count($product['data'])) {
                $contextShopID = Shop::getContextShopID();
                Shop::setContext(Shop::CONTEXT_ALL);
                $this->debbug(
                    'Processing elements that were left in the array and creating new features of: ' . print_r(
                        $product['data'],
                        1
                    ),
                    'syncdata'
                );

                do {
                    $first_index_name = '';
                    $first_value = '';

                    foreach ($product['data'] as $first_index_name_elm => $first_index_value) {
                        $first_index_name = $first_index_name_elm;
                        $first_value = $first_index_value;
                        if (!empty($first_value)) {
                            break;
                        } else {
                            unset($product['data'][$first_index_name_elm]);
                        }
                    }

                    if (empty($first_value)) {
                        break;
                    }

                    $new_feature = new Feature();

                    if (isset($schema[$first_index_name]['language_code'])) {
                        $this->debbug(
                            'Feature name in multi-language ' .
                            print_r($schema[$first_index_name], 1),
                            'syncdata'
                        );
                        foreach ($this->shop_languages as $lang_sub) {
                            // create name of feature in all languages needed for shop
                            $index_another_language = $schema[$first_index_name]['basename'] .
                                '_' . $lang_sub['iso_code'];
                            if (isset($schema[$index_another_language]['language_code']) &&
                                $schema[$index_another_language]['language_code'] == $lang_sub['iso_code']) {
                                if (isset($schema[$index_another_language]['title']) &&
                                    !empty($schema[$index_another_language]['title'])) {
                                    $new_feature->name[$lang_sub['id_lang']] = Tools::ucfirst(
                                        $schema[$index_another_language]['title']
                                    );
                                } else {
                                    $new_feature->name[$lang_sub['id_lang']] = Tools::ucfirst(
                                        $schema[$index_another_language]['basename']
                                    );
                                }
                            }
                        }
                    } else {
                        $this->debbug(
                            'Feature name does not have any language code of 
                            index name ->' . $first_index_name . ' -> ' . print_r(
                                (isset($schema[$first_index_name])?
                                $schema[$first_index_name]:'Not exist in schema array'),
                                1
                            ),
                            'syncdata'
                        );
                        if (isset($schema[$first_index_name]['titles'])
                            && !empty($schema[$first_index_name]['titles'])) {
                            foreach ($this->shop_languages as $lang_sub) {
                                if (isset($schema[$first_index_name]['titles'][$lang_sub['iso_code']])
                                    && !empty($schema[$first_index_name]['titles'][$lang_sub['iso_code']])) {
                                    $first_basename = $schema[$first_index_name]['titles'][$lang_sub['iso_code']];
                                } else {
                                    $first_basename = $first_index_name;
                                }
                                $new_feature->name[$lang_sub['id_lang']] = Tools::ucfirst($first_basename);
                            }
                            $this->debbug(
                                'After setting names ->' . $first_index_name . ' -> ' . print_r(
                                    $schema[$first_index_name],
                                    1
                                ),
                                'syncdata'
                            );
                        } else {
                            $first_basename = $first_index_name;
                            foreach ($this->shop_languages as $lang_sub) {
                                // create name of feature in all languages needed for shop
                                $new_feature->name[$lang_sub['id_lang']] = Tools::ucfirst($first_basename);
                            }
                            $this->debbug(
                                'Setting for all languages ->' . $first_index_name . ' -> ' . print_r(
                                    (isset($schema[$first_index_name])?
                                    $schema[$first_index_name]:'Not exist in Schema array'),
                                    1
                                ),
                                'syncdata'
                            );
                        }
                        $this->debbug(
                            'Element to be added ' . $first_basename . ' with the value ' . print_r(
                                $first_value,
                                1
                            ),
                            'syncdata'
                        );
                    }

                    try {
                        $new_feature->add();

                        $this->debbug(
                            'After saving new id of this element  feature_id ->' . print_r($new_feature->id, 1),
                            'syncdata'
                        );
                    } catch (Exception $e) {
                        unset($product['data'][$first_index_name]);

                        $this->debbug(
                            '## Error. ' . $occurence . ' Saving new Feature  ->' . $first_index_name .
                            ' and value->' . $product['data'][$first_index_name] . '  problem found->' . print_r(
                                $e->getMessage(),
                                1
                            ),
                            'syncdata'
                        );
                    }

                    $this->debbug(
                        'After creating feature resynchronising all values  ->' . print_r($features_founded, 1),
                        'syncdata'
                    );
                    if (isset($schema[$first_index_name]['language_code'])) {
                        $this->debbug('This is from multi-language  ->' . print_r($features_founded, 1), 'syncdata');
                        $prepare_values_feature = array();
                        $basename = $schema[$first_index_name]['basename'];
                        foreach ($schema as $field_name => $values_schema) {
                            $this->debbug(
                                'Passing the values ' . $basename . ' search fieldname ->' . $field_name .
                                ' -> ' . print_r(
                                    (isset($product['data'][$field_name]) ? $product['data'][$field_name] : 'empty'),
                                    1
                                ),
                                'syncdata'
                            );
                            if (isset($values_schema['basename'], $product['data'][$field_name])
                                && $values_schema['basename'] == $basename) {
                                foreach ($this->shop_languages as $lang_sub) {
                                    if ($lang_sub['iso_code'] == $values_schema['language_code']) {
                                        $prepare_values_feature[$lang_sub['id_lang']] = $product['data'][$field_name];
                                        break;
                                    }
                                }

                                $this->debbug(
                                    'Eliminating features in languages that have been processed ->' .
                                    $field_name . '  Value->' . print_r(
                                        $product['data'][$field_name],
                                        1
                                    ) . ' id_product ->' . $id_product,
                                    'syncdata'
                                );
                                unset($product['data'][$field_name]);
                            }
                        }

                        if (count($prepare_values_feature)) {
                            $count = 0;
                            $stat = 0;
                            foreach ($prepare_values_feature as $key_line => $lines) {
                                if (is_array($lines)) {
                                    if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                        $values_arr = $lines;
                                    } else {
                                        $values_arr =  array(reset($lines));
                                    }
                                } else {
                                    if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                        $values_arr = explode('|', $lines);
                                    } else {
                                        $values_arr =  array($lines);
                                    }
                                }
                                if ($count < count($values_arr)) {
                                    $count = count($values_arr);
                                }
                                $prepare_values_feature[$key_line] = $values_arr;
                            }

                            for (; $stat < $count; $stat++) {
                                $create_as_custom = $this->create_new_features_as_custom;
                                $filtered = array();
                                foreach ($prepare_values_feature as $id_lang => $line) {
                                    if (isset($line[$stat]) && preg_match('/:(custom|CUSTOM)/', $line[$stat])) {
                                        // si viene con comando para crear caracteristica
                                        // como custom la quitamos rellenamos como custom

                                        $line[$stat] = str_replace(array( ':custom', ':CUSTOM' ), '', $line[$stat]);
                                        $create_as_custom = true;
                                    }
                                    $filtered[$id_lang] = $line[$stat];
                                }

                                $id_feature_value = $this->searchFeatureValue(
                                    $new_feature->id,
                                    null,
                                    $filtered,
                                    $create_as_custom
                                ); // $id_product

                                $features_founded[] = $id_feature_value;
                                $this->debbug(
                                    'After searchFeature $id_feature_value->' .
                                    $id_feature_value . '  Value->' . print_r(
                                        $filtered,
                                        1
                                    ) . ' id_product ->' . $id_product,
                                    'syncdata'
                                );

                                if ($id_feature_value != 0) {
                                    try {
                                        $feature_value_exist = Db::getInstance()->executeS(
                                            sprintf(
                                                'SELECT id_feature_value FROM ' . $this->feature_product_table .
                                                ' WHERE id_feature =  "%s"  AND  id_product = "%s" 
                                                AND id_feature_value = "%s"',
                                                $new_feature->id,
                                                $id_product,
                                                $id_feature_value
                                            )
                                        );

                                        if (empty($feature_value_exist)) {
                                            Db::getInstance()->execute(
                                                sprintf(
                                                    'INSERT INTO ' . $this->feature_product_table .
                                                    '(id_feature, id_product, id_feature_value)
                                                 VALUES ("%s", "%s", "%s")',
                                                    $new_feature->id,
                                                    $id_product,
                                                    $id_feature_value
                                                )
                                            );
                                        }
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. ' . $occurence . ' Inserting feature value in ' .
                                            'unrecognized with another language  ->' . $e->getMessage(),
                                            'syncdata'
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        $this->debbug(
                            'Value simple in ' . $first_index_name . ' value ->' . print_r(
                                $product['data'][$first_index_name],
                                1
                            ),
                            'syncdata'
                        );

                        if (!empty($product['data'][$first_index_name])) {
                            if (is_array($product['data'][ $first_index_name ])) {
                                if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                    $feat_arr = $product['data'][ $first_index_name ];
                                } else {
                                    $feat_arr =  array(reset($product['data'][ $first_index_name ]));
                                }
                            } else {
                                if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
                                    $feat_arr = explode('|', $product['data'][ $first_index_name ]);
                                } else {
                                    $feat_arr = array($product['data'][ $first_index_name ]);
                                }
                            }

                            foreach ($feat_arr as $value) {
                                if (preg_match('/:(custom|CUSTOM)/', $value)) {
                                    // si viene con comando para crear caracteristica
                                    // como custom la quitamos rellenamos como custom
                                    $value = str_replace(array( ':custom', ':CUSTOM' ), '', $value);
                                    $create_as_custom = true;
                                } else {
                                    $create_as_custom = $this->create_new_features_as_custom;
                                }

                                $id_feature_value = $this->searchFeatureValue(
                                    $new_feature->id,
                                    null,
                                    array( $this->defaultLanguage => $value ),
                                    $create_as_custom
                                ); //$id_product

                                $this->debbug(
                                    'After searchFeature test  $id_feature_value->' . $id_feature_value .
                                    ' key->' . $first_index_name . '  Value->' . print_r(
                                        $product['data'][ $first_index_name ],
                                        1
                                    ) . ' id_product ->' . $id_product . ' lg_code language code -> ' . print_r(
                                        $this->defaultLanguage,
                                        1
                                    ),
                                    'syncdata'
                                );

                                if ($id_feature_value != 0) {
                                    $this->debbug(
                                        'Value returned when inserting in products with table id_product ->'
                                        . $id_product . ' $id_feature_value-> ' . print_r($id_feature_value, 1),
                                        'syncdata'
                                    );
                                    try {
                                        $feature_value_exist = Db::getInstance()->executeS(
                                            sprintf(
                                                'SELECT id_feature_value FROM ' . $this->feature_product_table .
                                                ' WHERE id_feature =  "%s"  AND  id_product = "%s"
                                                 AND id_feature_value = "%s" ',
                                                $new_feature->id,
                                                $id_product,
                                                $id_feature_value
                                            )
                                        );

                                        $this->debbug(
                                            'After select id_product ->'
                                            . $id_product . ' $id_feature_value-> ' . print_r($feature_value_exist, 1),
                                            'syncdata'
                                        );

                                        if (empty($feature_value_exist)) {
                                            Db::getInstance()->execute(
                                                sprintf(
                                                    'INSERT INTO ' . $this->feature_product_table . '
                                                (id_feature, id_product, id_feature_value)
                                                VALUES("%s", "%s", "%s")',
                                                    $new_feature->id,
                                                    $id_product,
                                                    $id_feature_value
                                                )
                                            );
                                        }
                                        $features_founded[] = $id_feature_value;
                                    } catch (Exception $e) {
                                        $this->debbug(
                                            '## Error. ' . $occurence .
                                            ' The value of the insert function is not recognized
                                        without the language code ' . $lang_sub['iso_code'] . ' ->' . $e->getMessage(),
                                            'syncdata'
                                        );
                                    }
                                }
                            }
                        } else {
                            $this->debbug(
                                'Value of new feature is empty ->' . print_r(
                                    $product['data'][$first_index_name],
                                    1
                                ),
                                'syncdata'
                            );
                        }
                    }

                    unset($product['data'][$first_index_name]);
                } while (count($product['data']) > 0);

                Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
            }
        } catch (Exception $e) {
            $this->debbug(
                '## Error.  Creating features that have not been recognized. ' .
                $occurence . ' problem found->' . print_r(
                    $e->getMessage(),
                    1
                ) . '  line->' . $e->getLine(),
                'syncdata'
            );
        }


        /**
         * Remove all features that have not been received in the product now
         */
        try {
            $product_features = Db::getInstance()->executeS(
                sprintf(
                    'SELECT id_feature,id_feature_value FROM ' .
                    $this->feature_product_table . ' where  id_product = "%s"',
                    $id_product
                )
            );

            if (count($product_features)) {
                $this->debbug('Test before deleting $features_founded as id_feature_value->' .
                    print_r($features_founded, 1), 'syncdata');
                foreach ($product_features as $feature) {
                    if (!in_array($feature['id_feature_value'], $features_founded, false)) {
                        $this->debbug(
                            'feature not founded in this connection when sending this feature value to be deleted ' .
                            print_r(
                                $feature,
                                1
                            ) . ' $features_founded->' . print_r($features_founded, 1),
                            'syncdata'
                        );

                        Db::getInstance()->execute(
                            sprintf(
                                'DELETE FROM ' . $this->feature_product_table . '
                                WHERE id_feature = "%s" AND id_product = "%s" AND id_feature_value = "%s" ',
                                $feature['id_feature'],
                                $id_product,
                                $feature['id_feature_value']
                            )
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->debbug(
                '## Error.  Removing all features that have not been received in the
                 product. ' . $occurence . '  problem found->' . print_r(
                    $e->getMessage(),
                    1
                ),
                'syncdata'
            );
        }

        unset($featuresFields, $featureValue);
    }

    /**
     * search a value in the features
     * @param $id_feature string id of the feature
     * @param $id_product int id of product
     * @param $values     array value to search
     * @param $create_as_custom bool create new feature as custom
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function searchFeatureValue(
        $id_feature,
        $id_product,
        $values,
        $create_as_custom = false
    ) {
        $id_feature_value = null;
        // $this->debbug('entry to with values $id_feature->' . print_r($id_feature, 1)
        // . ' $id_product->' . $id_product . ' $value->' . print_r($values, 1),  'syncdata');
        try {
            $featureValue = new FeatureValue();

            foreach ($values as $id_language => $value) {
                $feature_values_existing = $featureValue->getFeatureValuesWithLang($id_language, $id_feature, true);

                if (count($feature_values_existing) > 0) {
                    foreach ($feature_values_existing as $feature_value_existing) {
                        if (trim($value) == $feature_value_existing['value']) {
                            $this->debbug(
                                'Feature found, returning id $feature_value_existing ->' . print_r(
                                    $feature_value_existing,
                                    1
                                ),
                                'syncdata'
                            );

                            //Si encontramos un valor igual cambiamos el id de valor en la asignación existente.
                            return $feature_value_existing['id_feature_value'];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->debbug(
                '## Error. In searchFeatureValue in search values ->' . $e->getMessage() . ' line->' . print_r(
                    $e->getLine(),
                    1
                ),
                'syncdata'
            );
        }

        if (is_array($values) && !empty($values)) {
            $value_to_add = trim(reset($values));
            if (is_array($value_to_add) && !empty($value_to_add)) {
                $value_to_add = trim(reset($value_to_add));
            }
        } else {
            $value_to_add = trim($values);
        }

        if ($value_to_add !== '' && $value_to_add !== null) {
            try {
                $id_feature_value = $featureValue->addFeatureValueImport(
                    $id_feature,
                    $value_to_add,
                    $id_product,
                    null,
                    $create_as_custom
                );
            } catch (Exception $e) {
                $this->debbug(
                    '## Error. Saving new Feature addFeatureValueImport:' . print_r($e->getMessage(), 1),
                    'syncdata'
                );
            }
        }

        if ($id_feature_value != null) {
            $feature_value = new FeatureValue($id_feature_value);
            foreach ($values as $id_language => $value) {
                if (is_array($value) && !empty($value)) {
                    $value = trim(reset($value));
                }
                if ($value !== '' && $value !== null) {
                    $feature_value->value[$id_language] = trim($value);
                }
            }
            $feature_value->save();
        }

        unset($featureValue);

        if ($id_feature_value != null) {
            $this->debbug(
                'Return id of created feature value  $id_feature_value ->' . print_r($id_feature_value, 1),
                'syncdata'
            );

            return $id_feature_value;
        } else {
            $this->debbug(
                '##Warning. Return 0 Cannot create feature value $id_feature_value is ->' . print_r(
                    $id_feature_value,
                    1
                ),
                'syncdata'
            );

            return 0;
        }
    }

    /**
     * Delete all the fields already processed, and leave the ones that are unknown so that the rest will create
     * new features
     * @param $product
     * @param $schema
     * @return mixed
     */


    private function removePredefinedFieldsBeforeFeatures(
        $product,
        $schema
    ) {
        foreach (array_keys($product) as $product_field) {
            if (isset($schema[$product_field]['language_code'])) {
                $Basename = $schema[$product_field]['basename'];
            } else {
                $Basename = $product_field;
            }

            // test of dinamic fields
            $delete_partial_field = false;
            foreach ($this->predefined_partial_cut_fields as $partialfield) {
                $without_separator = str_replace('_', ' ', $Basename);
                if (preg_match('/'.$partialfield.'/i', $Basename) ||
                    preg_match('/'.$partialfield.'/i', $without_separator)) {
                    $delete_partial_field = true;
                    break;
                }
            }

            if ($delete_partial_field ||
                in_array(
                    Tools::strtolower($this->removeAccents($Basename)),
                    $this->predefined_product_fields,
                    false
                )) {
                unset($product[$product_field]);
            }
        }

        return $product;
    }
}

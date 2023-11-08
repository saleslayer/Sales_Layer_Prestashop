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

class SlVariants extends SalesLayerPimUpdate
{


    private $general_error = false;
    public function __construct()
    {
        parent::__construct();
    }

    public function loadVariantImageSchema(
        $product_formats_schema
    ) {
        if (empty($this->format_images_sizes)) {
            if (!empty($product_formats_schema)) {
                if (isset($product_formats_schema['fields']['frmt_image'])
                    && $product_formats_schema['fields']['frmt_image']['type'] == 'image'
                ) {
                    $this->product_format_has_frmt_image = true;
                }
                if (!empty($product_formats_schema['fields']['frmt_image']['image_sizes'])) {
                    $product_format_field_images_sizes = $product_formats_schema['fields']['frmt_image']['image_sizes'];
                    $ordered_image_sizes = $this->orderArrayImg($product_format_field_images_sizes);
                    foreach (array_keys($ordered_image_sizes) as $img_size) {
                        $this->format_images_sizes[] = $img_size;
                    }
                    unset($product_format_field_images_sizes, $ordered_image_sizes);
                } else {
                    if (!empty($product_formats_schema['fields']['image_sizes'])) {
                        $product_format_field_images_sizes = $product_formats_schema['fields']['image_sizes'];
                        $ordered_image_sizes = $this->orderArrayImg($product_format_field_images_sizes);
                        foreach (array_keys($ordered_image_sizes) as $img_size) {
                            $this->format_images_sizes[] = $img_size;
                        }
                        unset($product_format_field_images_sizes, $ordered_image_sizes);
                    } else {
                        $this->format_images_sizes[] = array('ORG', 'IMD', 'THM', 'TH');
                    }
                }
                $this->debbug(' load: Format_images_sizes ' . print_r($this->format_images_sizes, 1), 'syncdata');
            }
        }

        $this->debbug(' load: Variant_image_schema', 'syncdata');
    }

    public function syncOneVariant(
        $product_format,
        $schema,
        $connector_id,
        $comp_id,
        $conn_shops,
        $currentLanguage,
        $avoid_stock_update
    ) {
        $syncVariant = true;
        $reference = '';
        $alt_attribute_for_image = array();
        $this->debbug(
            'The information that comes to synchronise variant  ->' . print_r(
                $product_format,
                1
            ) . '  $shops ->' . print_r(
                $conn_shops,
                1
            ) . '   comp_id ->' . print_r($comp_id, 1),
            'syncdata'
        );

        if (empty($connector_id) || empty($comp_id) || empty($currentLanguage) || empty($conn_shops)) {
            $this->debbug('## Error. Some of the information has not been completed corectly', 'syncdata');

            return ['stat' => 'item_updated'];
        }
        $data_clear = [];
        $data_clear['data'] = $product_format['data'];
        unset($data_clear['data']['quantity']);
        $data_clear['shops'] = $conn_shops;
        $json_clear = json_encode(
            $data_clear,
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION
        );
        $data_hash = (string) hash($this->hash_algorithm_comparator, $json_clear);


        /**
         * Check alt attributes for images
         * @var  $leng
         */


        foreach ($this->shop_languages as $leng) {
            $variant_alt_index = '';
            $variant_alt_images_search = 'format_alt_' . $leng['iso_code'];
            if (isset(
                $product_format['data'][$variant_alt_images_search],
                $schema[$variant_alt_images_search]['language_code']
            ) &&
                !empty($product_format['data'][$variant_alt_images_search]) &&
                $schema[$variant_alt_images_search]['language_code'] == $leng['iso_code']
            ) {
                $variant_alt_index = 'format_alt_' . $leng['iso_code'];
            } elseif (isset($product_format['data']['format_alt']) &&
                      !empty($product_format['data']['format_alt'])
            ) {
                $variant_alt_index = 'format_alt';
            }


            if ($variant_alt_index != '' && isset($product_format['data'][$variant_alt_index]) &&
                !empty($product_format['data'][$variant_alt_index])
            ) {
                $alt_images_arr = array();
                $product_format['data'][$variant_alt_index] =
                    $this->clearStructureData($product_format['data'][$variant_alt_index]);
                if (is_array($product_format['data'][$variant_alt_index])) {
                    $alt_images_arr = $this->clearAndOrderArray($product_format['data'][$variant_alt_index]);
                } else {
                    $alt_images_arr = explode(',', $product_format['data'][$variant_alt_index]);
                }
                foreach ($alt_images_arr as $key => $value) {
                    $alt_images_arr[$key] = trim($value);
                }
                $this->debbug('Save data from alt attribute variant. ' . print_r(
                    $alt_images_arr,
                    1
                ) . ' id_lang -> ' . print_r($leng['id_lang'], 1), 'syncdata');
                $alt_attribute_for_image[$leng['id_lang']] =  $alt_images_arr;
                $this->debbug(' after save  ' . print_r(
                    $alt_attribute_for_image,
                    1
                ), 'syncdata');
            }
            if ($variant_alt_index != '') {
                unset($product_format['data'][$variant_alt_index]);
            }

            /**
             * Place your custom multi-language code here
             */
        }


        /**
         * Place your custom non multi-language code here
         */






        /**
         * Clear all remove leftover fields of alt attribute (languages that do not match those of prestashop)
         */

        $array_alt_attributes = preg_grep('/format_alt?/', array_keys($product_format['data']));
        if (!empty($array_alt_attributes)) {
            foreach ($array_alt_attributes as $alt_field) {
                if (isset($product_format['data'][$alt_field])) {
                    unset($product_format['data'][$alt_field]);
                }
            }
        }

        $number_of_images = 0;
        if (isset($product_format['data']['frmt_image']) || !empty($product_format['data']['frmt_image'])) {
            $number_of_images = count(is_array($product_format['data']['frmt_image']) ?
                $product_format['data']['frmt_image'] : []);
            $this->debbug('Number of images in array ->  ' . print_r(
                $number_of_images,
                1
            ), 'syncdata');
        }
        if (isset($product_format['data']['reference']) && !empty($product_format['data']['reference'])) {
            $reference = $product_format['data']['reference'];
            foreach ($this->shop_languages as $leng) {
                $show_number = '';
                for ($counter = 0; $counter < $number_of_images; $counter++) {
                    if ($counter != 0) {
                        $show_number = ' (' . $counter . ')';
                    }
                    if (empty($alt_attribute_for_image[$leng['id_lang']][$counter])) {
                        $alt_attribute_for_image[$leng['id_lang']][$counter] = $reference . $show_number;
                        $this->debbug(
                            'Complete array with reference->' .
                                      $leng['iso_code'] .
                                      ' key->' . $counter . ' ->' .
                                      print_r(
                                          $alt_attribute_for_image[$leng['id_lang']][$counter],
                                          1
                                      ),
                            'syncdata'
                        );
                    }
                }
            }
            $occurrence = ' variant reference :"' . $reference . '" ';
        } else {
            foreach ($this->shop_languages as $leng) {
                $show_number = '';
                for ($counter = 0; $counter < $number_of_images; $counter++) {
                    if ($counter != 0) {
                        $show_number = ' (' . $counter . ')';
                    }
                    if (empty($alt_attribute_for_image[$leng['id_lang']][$counter])) {
                        $alt_attribute_for_image[$leng['id_lang']][$counter] = 'variant ' . $product_format['ID'] .
                                                                               $show_number;
                        $this->debbug(
                            'Complete array with variant and ID_sl lang->' .
                                      $leng['iso_code'] .
                                      ' key->' . $counter . ' ->' . print_r(
                                          $alt_attribute_for_image[$leng['id_lang']][$counter],
                                          1
                                      ),
                            'syncdata'
                        );
                    }
                }
            }
            $occurrence = ' variant ID :' . $product_format['ID'] . ' of Product ID:' . $product_format['ID_products'];
        }

        $product_format_id = $product_format['ID'];
        $slyr_product_id = $product_format['ID_products'];

        $product_id = (int) Db::getInstance()->getValue(
            sprintf(
                'SELECT sl.ps_id FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
                 WHERE sl.comp_id = "%s" AND sl.slyr_id = "%s" AND sl.ps_type = "product"',
                $comp_id,
                $slyr_product_id
            )
        );

        if (isset($product_format['data']['enabled']) &&
            $product_format['data']['enabled'] !== '' &&
            $product_format['data']['enabled'] !== null
        ) {
            $toactivate = $this->slValidateBoolean($product_format['data']['enabled']);
            if (Validate::isBool($toactivate)) {
                $this->debbug('Set Variant active? ' . $occurrence .
                              ' to ' .
                              print_r(
                                  $toactivate,
                                  1
                              ) . ' in $comp_id id->' .
                              print_r($comp_id, 1), 'syncdata');
                if ($toactivate == false) {
                    try {
                        $this->deleteVariant($product_format['ID'], $comp_id, $conn_shops, $reference, $product_id);
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. ' . $occurrence . ' send delete by field enabled to false' .
                            print_r($e->getMessage(), 1) . ' in line->' . print_r($e->getLine(), 1),
                            'syncdata'
                        );
                    }
                    return ['stat' => 'item_updated'];
                }
            } else {
                $this->debbug('## Warning. ' . $occurrence .
                              ' Field Active. Value is not a boolean -> ' .
                              print_r(
                                  $toactivate,
                                  1
                              ), 'syncdata');
            }
        }
        unset($product_format['data']['enabled']);


        /*  if (isset($product_format['data']['reference']) && empty($product_format['data']['reference'])) {
              $this->debbug(
                  '## Error. ' . $occurrence . ' Variant reference is required to be able to identify the variant ->' .
                  print_r($product_format['data']['reference'], 1) .
                  'Variant ID:' . $product_format_id . ',  ID product: ' .
                  $slyr_product_id . ',  company ID: ' . $comp_id,
                  'syncdata'
              );

              return 'item_updated';
          }*/

        if ($product_id == null || $product_id == '') {
            $this->debbug(
                '## Error. ' . $occurrence . ' It has not been possible to find the Product ID of the variant,' .
                 'It may necessary to make the parent product of this variant invisible and' .
                  'visible again so that its synchronization is possible.' .
                  'Variant ID:' . $product_format_id . ',  ID product: ' .
                $slyr_product_id . ',  company ID: ' . $comp_id,
                'syncdata'
            );

            return ['stat' => 'item_updated'];
        } else {
            $product_count_pack = Db::getInstance()->getValue(
                sprintf(
                    'SELECT COUNT(*) AS count FROM `' . $this->pack_table . '` WHERE id_product_pack = "%s"',
                    $product_id
                )
            );

            $product_type_data = Db::getInstance()->executeS(
                sprintf(
                    'SELECT is_virtual,cache_is_pack FROM ' . $this->product_table . ' WHERE id_product = "%s"',
                    $product_id
                )
            );
            $product_type_data = $product_type_data[0];

            if ($product_count_pack > 0 || $product_type_data['cache_is_pack'] == 1
                || $product_type_data['is_virtual'] == 1
            ) {
                $this->debbug(
                    '## Error.' . $occurrence . '.  Product is a type pack or virtual and can not have variants ',
                    'syncdata'
                );

                //continue;
                return ['stat' => 'item_updated'];
            }
        }

        $fieldsBase = array_fill_keys($this->product_format_base_fields, '');

        if (isset($product_format['data'])) {
            foreach ($fieldsBase as $key => $value) {
                if ($key == 'format_supplier' || $key == 'format_supplier_reference') {
                    $array_supplier = preg_grep('/' . $key . '_\+?\d+$/', array_keys($product_format['data']));

                    if (!empty($array_supplier)) {
                        foreach ($array_supplier as $supplier_field) {
                            /* if ($product_format['data'][$supplier_field] != ''
                                 && $product_format['data'][$supplier_field] != null) {*/
                            $fieldsBase[$supplier_field] = $product_format['data'][$supplier_field];
                            unset($product_format['data'][$supplier_field]);
                            // }
                        }
                    }
                    unset($fieldsBase[$key]);
                } else {
                    if (array_key_exists(
                        $key,
                        $product_format['data']
                    )
                        && $product_format['data'][$key] != ''
                        && $product_format['data'][$key] != null
                    ) {
                        $fieldsBase[$key] = $product_format['data'][$key];
                        unset($product_format['data'][$key]);
                    } else {
                        unset($fieldsBase[$key]);
                    }
                }
            }


            $attributes = array();
            $processed_Keys = array();
            do {
                $attributeGroupName = '';
                $attributeValue = '';

                foreach ($product_format['data'] as $first_index_name_elm => $first_index_value) {
                    $attributeGroupName = $first_index_name_elm;
                    $attributeValue = $first_index_value;
                    if (!empty($attributeValue)) {
                        break;
                    } else {
                        unset($product_format['data'][$first_index_name_elm]);
                    }
                }

                if (empty($attributeValue)) {
                    break;
                }

                // $this->debbug('Elemento encontrado para procesar como attributo
                // '.$attributeGroupName.' con value ->'.print_r($attributeValue,1) ,'syncdata');
                //($product_format['data'] as $attributeGroupName => $attributeValue) {
                $currentLanguage_for_set = $currentLanguage;
                $mulilanguage = array();

                if ($attributeGroupName != '' && !empty($attributeValue)
                    && !in_array(
                        $attributeGroupName,
                        $processed_Keys,
                        false
                    )
                ) {
                    if (isset($schema[$attributeGroupName]['language_code'])
                        && !empty($schema[$attributeGroupName]['language_code'])
                    ) {
                        /**
                         *
                         * Attribute is multi-Language
                         */
                        try {
                            $currentLanguage_for_set = Language::getIdByIso(
                                $schema[$attributeGroupName]['language_code']
                            );
                            foreach ($this->shop_languages as $leng) {
                                $attribute_index = $schema[$attributeGroupName]['basename'] . '_' . $leng['iso_code'];
                                if (isset($product_format['data'][$attribute_index])) {
                                    $this->debbug(
                                        $occurrence . 'Is the same attribute but in other languages ' . print_r(
                                            $attribute_index,
                                            1
                                        ) . ' Tag language ->' . print_r($leng['iso_code'], 1),
                                        'syncdata'
                                    );
                                    if (!empty($product_format['data'][$attribute_index])) {
                                        if (is_array($product_format['data'][$attribute_index])) {
                                            $product_format['data'][$attribute_index] =
                                                reset($product_format['data'][$attribute_index]);
                                        }
                                        $mulilanguage[$leng['id_lang']] = $product_format['data'][$attribute_index];
                                    }
                                    $processed_Keys[] = $attribute_index;
                                    unset($product_format['data'][$attribute_index]);
                                }
                            }

                            /**
                             * Delete the same attribute but in language that is not
                             * installed in prestashop but comes from sales layer
                             */
                            foreach ($schema as $nameOfAttribute => $valuesOfAttribute) {
                                if (isset($schema[$attributeGroupName]['basename'], $valuesOfAttribute['basename'])
                                    && $schema[$attributeGroupName]['basename'] == $valuesOfAttribute['basename']
                                ) {
                                    unset($product_format['data'][$nameOfAttribute]);
                                }
                            }
                        } catch (Exception $e) {
                            $this->debbug(
                                '## Error. ' . $occurrence . ' Language::getIdByIso' . print_r($e->getMessage(), 1),
                                'syncdata'
                            );
                        }

                        unset($product_format['data'][$attributeGroupName]);
                        $attributeGroupName = $schema[$attributeGroupName]['basename'];
                    }
                    $attribute_group_id = null;
                    try {
                        $attribute_group_id = $this->getAttributeGroupId($attributeGroupName, $comp_id);
                    } catch (Exception $e) {
                        unset($product_format['data'][$attributeGroupName]);
                        $this->debbug(
                            '## Error. ' . $occurrence . ' getAttributeGroupId' . print_r($e->getMessage(), 1),
                            'syncdata'
                        );
                    }
                    if ($attribute_group_id == null || $attribute_group_id == '') {
                        $this->debbug(
                            '## Error. ' . $occurrence . ' When you get the group ID of attribute
                             ' . $attributeGroupName . ' for the company with  ID: ' . $comp_id,
                            'syncdata'
                        );
                        unset($product_format['data'][$attributeGroupName]);
                        continue;
                    }

                    if (is_array($attributeValue)) {
                        if (count($attributeValue) > 1) {
                            $this->debbug(
                                $occurrence . ' Only first value is accepted from
                             ' . $attributeGroupName .
                                ' value ->: ' . print_r($attributeValue, 1),
                                'syncdata'
                            );
                            $attributeValue = reset($attributeValue);
                        } else {
                            $attributeValue = reset($attributeValue);
                        }
                    }

                    if (is_array($attributeValue)) {

                        /**
                         * Value is a array value
                         */
                        $this->debbug('attribute is array');
                        foreach ($attributeValue as $attributeVal) {
                            try {
                                $attribute_id = $this->synchronizeAttribute(
                                    $attribute_group_id,
                                    $attributeVal,
                                    $product_format_id,
                                    $connector_id,
                                    $comp_id,
                                    $conn_shops,
                                    $currentLanguage_for_set,
                                    $mulilanguage
                                );

                                if ($attribute_id == null || $attribute_id == '') {
                                    $this->debbug(
                                        '## Error. ' . $occurrence . ' When synchronizing the attribute
                                        ' . $attributeGroupName . ' for the variant with ID: ' . $product_format_id,
                                        'syncdata'
                                    );
                                    continue;
                                } else {
                                    if (!in_array($attribute_id, $attributes, false)) {
                                        $attributes[] = $attribute_id;
                                    }
                                }
                            } catch (Exception $e) {
                                unset($product_format['data'][$attributeGroupName]);
                                $this->general_error = true;
                                $this->debbug(
                                    '## Error. ' . $occurrence . ' synchronizeAttribute ' . print_r(
                                        $e->getMessage(),
                                        1
                                    ) . ' line ->' . print_r($e->getLine(), 1),
                                    'syncdata'
                                );
                            }
                        }
                    } elseif (!is_array($attributeValue) && $attributeValue != '') {
                        /**
                         * Value is string
                         */
                        $this->debbug('attribute is string ->' . print_r($attributeValue, 1), 'syncdata');
                        try {
                            $attribute_id = $this->synchronizeAttribute(
                                $attribute_group_id,
                                (string) $attributeValue,
                                $product_format_id,
                                $connector_id,
                                $comp_id,
                                $conn_shops,
                                $currentLanguage_for_set,
                                $mulilanguage
                            );

                            if ($attribute_id == null || $attribute_id == '') {
                                unset($product_format['data'][$attributeGroupName]);
                                $this->general_error = true;
                                $this->debbug(
                                    '## Error. ' . $occurrence . ' When synchronizing the attribute ' .
                                     $attributeGroupName . ' for the variant with ID: ' . $product_format_id,
                                    'syncdata'
                                );
                                continue;
                            } else {
                                if (!in_array($attribute_id, $attributes, false)) {
                                    $this->debbug(
                                        $occurrence . 'Attribute id saved with ' .
                                        $attributeGroupName . ' valor -> ' . print_r(
                                            $attribute_id,
                                            1
                                        ),
                                        'syncdata'
                                    );
                                    $attributes[] = $attribute_id;
                                }
                            }
                        } catch (Exception $e) {
                            unset($product_format['data'][$attributeGroupName]);
                            $this->general_error = true;
                            $this->debbug(
                                '## Error. ' . $occurrence . ' synchronizeAttribute as string: ' . print_r(
                                    $e->getMessage(),
                                    1
                                ) . ' line->' . print_r($e->getLine(), 1),
                                'syncdata'
                            );
                        }
                    }
                }
                unset($product_format['data'][$attributeGroupName]);
            } while (count($product_format['data']) > 0);

            $this->debbug(
                $occurrence . 'Attributes ids selected->' . $attributeGroupName . ' valor -> ' . print_r(
                    $attributes,
                    1
                ),
                'syncdata'
            );

            if (empty($attributes)) {
                $this->general_error = true;
                $this->debbug(
                    '## Error. ' . $occurrence . ' There are no configurable attributes. ' .
                     'Please continue to the cloud of Sales Layer >> Channels ' .
                     '>> Edit Prestashop Connector >> Output data >> Variants >> Include new field ' .
                      'and insert field type (color,size,..) ',
                    'syncdata'
                );

                //continue;
                return ['stat' => 'item_updated'];
            }


            $schemaProdAttrs = 'SELECT `pa`.`id_product_attribute` ' .
                ' FROM ' . $this->product_attribute_table . ' pa ' .
                ' WHERE id_product = ' . $product_id .
                ' GROUP BY `pa`.`id_product_attribute` ' .
                ' ORDER BY `pa`.`id_product_attribute` ';
            $productAttributes = Db::getInstance()->executeS($schemaProdAttrs);

            $this->debbug(
                'Product attributes ids ' .
                 '  id_product_attribute ->' . print_r(
                     $productAttributes,
                     1
                 ),
                'syncdata'
            );

            $sl_product_format_id = 0;
            /**
             * Check if exist in sl cache
             */
            $query_search_variant =  sprintf(
                'SELECT sl.ps_id FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
                         WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                $product_format_id,
                $comp_id
            );
            $check_sl_product_format_id = (int) Db::getInstance()->getValue($query_search_variant);

            if ($check_sl_product_format_id) {
                $this->debbug(
                    'Exist combination in cache sl in DB -> ' .
                     print_r(
                         $check_sl_product_format_id,
                         1
                     ) . ' query->' . print_r($query_search_variant, 1),
                    'syncdata'
                );
                /**
                 * Check duplicates in sl cache
                 */
                $query_search_duplicates =  sprintf(
                    'SELECT * FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
                         WHERE sl.ps_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                    $check_sl_product_format_id,
                    $comp_id
                );
                $Variant_cache_duplicates = Db::getInstance()->executeS($query_search_duplicates);
                if (count($Variant_cache_duplicates) > 1) {
                    //delete duplicates in cache of SL
                    foreach ($Variant_cache_duplicates as $value) {
                        if ($value['slyr_id'] != $product_format_id) {
                            $this->debbug(
                                'Delete duplicate in cache sl ' .
                                '  $value ->' . print_r(
                                    $value,
                                    1
                                ),
                                'syncdata'
                            );
                            Db::getInstance()->execute(
                                sprintf(
                                    'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product
                                             WHERE slyr_id = "%s"
                                             AND comp_id = "%s"
                                             AND ps_type = "combination"',
                                    $value['slyr_id'],
                                    $value['comp_id']
                                )
                            );
                        }
                    }
                }
                $sl_product_format_id = $check_sl_product_format_id;
            }

            /**
             * Combination does not exist in the SL cache
             * Check prestashop reference
             */


            if (!empty($reference)) {
                $check_sl_product_format_array = Db::getInstance()->executeS(
                    sprintf(
                        'SELECT ps.id_product_attribute FROM `' . _DB_PREFIX_ . 'product_attribute` ps
                         WHERE ps.reference = "%s" AND  ps.id_product = "%s" GROUP BY  id_product_attribute',
                        $reference,
                        $product_id
                    )
                );

                $this->debbug(
                    'Founded rows  ->' . print_r($check_sl_product_format_array, 1),
                    'syncdata'
                );
                $in_cart = array();
                if (count($check_sl_product_format_array)) {
                    /**
                     * Search variant in cart if exist order for buy this variants
                     */

                    foreach ($check_sl_product_format_array as $variant) {
                        $query = sprintf(
                            'SELECT ps.id_cart FROM `' . _DB_PREFIX_ . 'cart_product` ps
                         WHERE ps.id_product_attribute = "%s" AND  ps.id_product = "%s" GROUP BY  id_product_attribute',
                            $variant['id_product_attribute'],
                            $product_id
                        );

                        $order_row = Db::getInstance()->getRow($query);
                        $this->debbug(
                            $occurrence . ' Variant -> ' . $variant['id_product_attribute'] .
                            ' Test if in cart exist ->' . print_r($order_row, 1) .
                            'query -> ' . print_r($query, 1),
                            'syncdata'
                        );

                        if (isset($order_row['id_cart']) && !empty($order_row['id_cart'])) {
                            $this->debbug(
                                $occurrence . 'Exist in cart->' . print_r($order_row, 1),
                                'syncdata'
                            );

                            $in_cart[] = $variant['id_product_attribute'];
                        }
                    }
                    if ($sl_product_format_id == 0) {
                        if (!count($in_cart)) {
                            //no items found in the shopping cart
                            $reset_value = reset($check_sl_product_format_array);
                            $check_sl_product_format_id =  $reset_value['id_product_attribute'];
                            $this->debbug(
                                $occurrence . ' Selected from first with the same sku ->' .
                                print_r($check_sl_product_format_id, 1) .
                                '. Duplicate variant removal will proceed.',
                                'syncdata'
                            );
                        } else {
                            //select first with registers in cart
                            $check_sl_product_format_id = reset($in_cart);
                            $this->debbug(
                                $occurrence . ' Selected from in_cart ->' . print_r($in_cart, 1) .
                                '. Duplicate variant removal will proceed.',
                                'syncdata'
                            );
                        }

                        if ($check_sl_product_format_id != 0) {
                            $this->debbug(
                                'Variant found by Reference ->' . $reference,
                                'syncdata'
                            );

                            Db::getInstance()->execute(
                                sprintf(
                                    'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                                    (ps_id, slyr_id, ps_type, comp_id, date_add)
                                    VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                    $check_sl_product_format_id,
                                    $product_format_id,
                                    'combination',
                                    $comp_id
                                )
                            );
                        }
                    }
                }


                if (count($check_sl_product_format_array) > 1) {
                    $this->debbug(
                        '## Warning. More than one Variant with same sku detected  ->' . $reference .
                        '  count ->' . count($check_sl_product_format_array) .
                        'with cart ids ->' . print_r($in_cart, 1) .
                        ' Duplicate variant removal will proceed.',
                        'syncdata'
                    );
                    // unset($check_sl_product_format_array[key($check_sl_product_format_array)]);
                    /**
                     * Delete all variant and save ony one with the same code
                     */

                    foreach ($check_sl_product_format_array as $id_combination) {
                        if ($id_combination['id_product_attribute'] == $check_sl_product_format_id) {
                            $this->debbug(
                                $occurrence . ' Jump to another this is variant selected' .
                                $id_combination['id_product_attribute'],
                                'syncdata'
                            );
                            continue;
                        }
                        if (in_array($id_combination['id_product_attribute'], $in_cart, false)) {
                            $this->debbug(
                                $occurrence . '## Warning. Duplicate variant with that' .
                                ' sku cannot be deleted because ' .
                                ' it is already stored in the shopping cart of some user. ' .
                                'Please resolve this conflict manually.->' .
                                $id_combination['id_product_attribute'],
                                'syncdata'
                            );
                            continue;
                        }

                        $this->debbug(
                            $occurrence . 'Deleting Variant  id_product_attribute ->' .
                            $id_combination['id_product_attribute'],
                            'syncdata'
                        );
                        try {
                            $existing_combination = new CombinationCore(
                                $id_combination['id_product_attribute'],
                                null
                            );
                            $existing_combination->delete();
                        } catch (Exception $e) {
                            $this->debbug(
                                '## Error. ' . $occurrence . ' In delete duplicates for the variant id->' .
                                print_r($id_combination['id_product_attribute'], 1) .
                                print_r($e->getMessage(), 1) . ' track->' .
                                print_r($id_combination['id_product_attribute'], 1),
                                'syncdata'
                            );
                        }
                    }
                    $check_sl_product_format_array = array();
                }
            }

            $this->debbug(
                $occurrence . ' before check by attributes ->'
                . print_r($productAttributes, 1) . '  $this->shop_languages->' . print_r(
                    $this->shop_languages,
                    1
                ),
                'syncdata'
            );


            if (count($productAttributes) > 0) {
                foreach ($productAttributes as $key => $value) {
                    foreach ($this->shop_languages as $lang) {
                        $this->debbug(
                            $occurrence . ' Product attributes in language search in BD iso_code ->'
                            . $lang['iso_code'] . '  id_product_attribute key->' . print_r(
                                $key,
                                1
                            ) . '  value->' . print_r($value, 1),
                            'syncdata'
                        );

                        // $existing_sl_combination_id = (int) Db::getInstance()->getValue(sprintf('SELECT ps_id FROM '.
                        //_DB_PREFIX_.'slyr_category_product  WHERE ps_id = "%s" AND comp_id = "%s"
                        // AND ps_type = "combination"', $value['id_product_attribute'], $comp_id));

                        // if ($existing_sl_combination_id == 0){

                        //  $delete_combination = new CombinationCore($value['id_product_attribute']);
                        //  $delete_combination->delete();
                        //  continue;

                        // }

                        $schemaAttrs = 'SELECT `a`.`id_attribute` ' .
                            ' FROM ' . $this->product_attribute_table . ' pa ' .
                            ' LEFT JOIN ' . $this->product_attribute_combination_table .
                            ' pac ON pac.id_product_attribute = pa.id_product_attribute ' .
                            ' LEFT JOIN ' . $this->attribute_table . ' a ON a.id_attribute = pac.id_attribute ' .
                            ' LEFT JOIN ' . $this->attribute_group_table .
                            ' ag ON ag.id_attribute_group = a.id_attribute_group ' .
                            ' LEFT JOIN ' . $this->attribute_lang_table . " al
                             ON (a.id_attribute = al.id_attribute AND al.id_lang = '" . $lang['id_lang'] . "') " .
                            ' LEFT JOIN ' . $this->attribute_group_lang_table . " agl
                            ON (ag.id_attribute_group = agl.id_attribute_group
                             AND agl.id_lang = '" . $lang['id_lang'] . "') " .
                            ' WHERE `pa`.`id_product` = ' . $product_id .
                            ' and `pa`.`id_product_attribute` = ' . $value['id_product_attribute'] .
                            ' GROUP BY `pa`.`id_product_attribute`, `ag`.`id_attribute_group` ' .
                            ' ORDER BY `pa`.`id_product_attribute`; ';


                        $attributesValues = Db::getInstance()->executeS($schemaAttrs);

                        if (count($attributesValues) > 0) {
                            $attributes_val = array();
                            foreach ($attributesValues as $attrVal) {
                                //array_push($attributes_val, $attrVal['id_attribute']);
                                if (isset($attrVal['id_attribute']) && !empty($attrVal['id_attribute'])) {
                                    $attributes_val[] = $attrVal['id_attribute'];
                                }
                            }

                            if ($sl_product_format_id == 0 && empty(array_diff($attributes, $attributes_val))) {
                                $this->debbug(
                                    'Exist combination from combination of attributes -> ' .
                                    print_r(
                                        $check_sl_product_format_id,
                                        1
                                    ),
                                    'syncdata'
                                );
                                $query_check_exist_union =  sprintf(
                                    'SELECT sl.ps_id FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
                                     WHERE sl.slyr_id != "%s" 
                                     AND sl.comp_id = "%s" 
                                     AND sl.ps_type = "combination" 
                                     AND sl.ps_id = "%s" LIMIT 1 ',
                                    $product_format_id,
                                    $comp_id,
                                    $value['id_product_attribute']
                                );
                                $check_sl_ps_cache =  Db::getInstance()->executeS($query_check_exist_union);
                                $this->debbug(
                                    'Check if already is asigned to another variant this group of attributes -> ' .
                                    print_r(
                                        $check_sl_ps_cache,
                                        1
                                    ) . ' query->' . print_r($query_check_exist_union, 1),
                                    'syncdata'
                                );
                                if (empty($check_sl_ps_cache)) {
                                    $sl_product_format_id = $value['id_product_attribute'];
                                    break 2;
                                }
                            } else {
                                $this->debbug($occurrence . 'Combination not founded by attributes ->' .
                                              print_r($check_sl_product_format_id, 1).' diff attributes ->' .
                                              print_r($attributes, 1) .
                                              ' $attributes_val->' . print_r($attributes_val, 1), 'syncdata');
                            }
                        } else {
                            $this->debbug($occurrence . 'Combination not founded by attributes in query ->'.
                                          print_r($check_sl_product_format_id, 1).'  $schemaAttrs ->'.
                                          print_r($schemaAttrs, 1), 'syncdata');
                        }
                    }// lang
                }
            }
            $this->first_sync_shop = true;
            $is_new_variant = false;
            $processedShop = 0;
            foreach ($conn_shops as $shop_id) {
                Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);


                // $combination_changed = false;
                if ($check_sl_product_format_id != 0) {
                    $this->debbug($occurrence . 'Combination exist ->'.
                                  print_r($check_sl_product_format_id, 1), 'syncdata');
                    $existing_combination = new CombinationCore($check_sl_product_format_id, null, $shop_id);
                    try {
                        $combination_option_values = $existing_combination->getWsProductOptionValues();
                    } catch (Exception $e) {
                        $this->debbug(
                            $occurrence . '## Error. getWsProductOptionValues' . print_r($e->getMessage(), 1),
                            'syncdata'
                        );
                    }

                    $combination_attributes_values = array();
                    $this->debbug($occurrence . 'Combination $combination_option_values ->'.
                                  print_r($combination_option_values, 1), 'syncdata');

                    if (!empty($combination_option_values)) {
                        foreach ($combination_option_values as $combination_option_value) {
                            //array_push($combination_attributes_values, $combination_option_value['id']);
                            $combination_attributes_values[] = $combination_option_value['id'];
                        }
                        $this->debbug(
                            $occurrence . 'Check synchronize data attributes attr->' . print_r(
                                $attributes,
                                1
                            ) . ' diff ->' . print_r(
                                $combination_attributes_values,
                                1
                            ),
                            'syncdata'
                        );
                        if ($this->slArrayDiff($attributes, $combination_attributes_values)) {
                            $this->debbug($occurrence . 'Combination change, Sending refresh', 'syncdata');
                        //  $combination_changed = true;

                        /* if ($sl_product_format_id == '') {
                             Db::getInstance()->execute(
                               sprintf(
                                   'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product ' .
                                   ' WHERE ps_id = "%s" AND slyr_id = "%s" AND ' .
                                   'comp_id = "%s" AND ps_type = "combination"',
                                   $check_sl_product_format_id,
                                   $product_format_id,
                                   $comp_id
                               )
                           );
                         }*/
                        } else {
                            $this->debbug($occurrence . 'The combinations have not changed.', 'syncdata');
                        }
                        unset($existing_combination);
                    }


                    if ($check_sl_product_format_id != $sl_product_format_id && $sl_product_format_id != '') {
                        try {
                            $this->debbug($occurrence . 'Updating register $sl_product_format_id->'
                                          .$sl_product_format_id.
                                          ' $check_sl_product_format_id->'.$check_sl_product_format_id.
                                          ' $product_format_id->'.print_r($product_format_id, 1), 'syncdata');
                            Db::getInstance()->execute(
                                sprintf(
                                    'UPDATE `' . _DB_PREFIX_ . 'slyr_category_product` sl
                                    SET sl.ps_id = "%s" WHERE sl.ps_id = "%s" AND sl.slyr_id = "%s"
                                    AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                                    $sl_product_format_id,
                                    $check_sl_product_format_id,
                                    $product_format_id,
                                    $comp_id
                                )
                            );
                        } catch (Exception $e) {
                            $this->debbug('## Error. ' . $occurrence .
                                ' In update combination table', 'syncdata');
                        }
                    }
                } else {
                    if ($sl_product_format_id !== 0) {
                        $this->debbug($occurrence . 'Combination not exist ->'.
                                      print_r($check_sl_product_format_id, 1), 'syncdata');
                        $old_sl_product_format_id = (int)Db::getInstance()->getValue(
                            sprintf(
                                'SELECT sl.ps_id FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
                                WHERE sl.ps_id = "%s" AND sl.slyr_id = "%s"
                                AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                                $sl_product_format_id,
                                $product_format_id,
                                $comp_id
                            )
                        );

                        if ($old_sl_product_format_id != 0) {
                            Db::getInstance()->execute(
                                sprintf(
                                    'UPDATE `' . _DB_PREFIX_ . 'slyr_category_product` sl  SET sl.slyr_id = "%s"
                                     WHERE sl.ps_id = "%s"
                                    AND sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                                    $product_format_id,
                                    $sl_product_format_id,
                                    $old_sl_product_format_id,
                                    $comp_id
                                )
                            );
                            $this->debbug($occurrence . 'Updating register level 2 $sl_product_format_id->'.
                                          $sl_product_format_id.
                                          ' $check_sl_product_format_id->'.$check_sl_product_format_id.
                                          ' $product_format_id->'.print_r($product_format_id, 1), 'syncdata');
                        } else {
                            Db::getInstance()->execute(
                                sprintf(
                                    'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                                    (ps_id, slyr_id, ps_type, comp_id, date_add)
                                    VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                    $sl_product_format_id,
                                    $product_format_id,
                                    'combination',
                                    $comp_id
                                )
                            );
                            $this->debbug($occurrence . 'Insert register $sl_product_format_id->'
                                          .$sl_product_format_id.
                                          ' $check_sl_product_format_id->'.$check_sl_product_format_id.
                                          ' $product_format_id->'.print_r($product_format_id, 1), 'syncdata');
                        }
                    } else {
                        $this->debbug($occurrence . ' Without create relation $sl_product_format_id is 0 ->'.
                                      print_r($sl_product_format_id, 1), 'syncdata');
                    }
                }
                $sql_combination = 'SELECT sl.ps_id FROM `' . _DB_PREFIX_ . 'slyr_category_product` sl
						WHERE sl.slyr_id = "' . $product_format_id . '" AND sl.comp_id = "' . $comp_id . '"
						AND sl.ps_type = "combination"';
                $id_product_attribute = (int)Db::getInstance()->getValue(
                    $sql_combination
                );

                $stock = 0;

                $combination_generated = false;
                $this->debbug(
                    $occurrence . ' variant founded?->' .
                    ' $id_product_attribute->' . print_r($id_product_attribute, 1) .
                    ' $sql_combination->' . print_r($sql_combination, 1),
                    'syncdata'
                );
                if ($id_product_attribute) {
                    $this->debbug(
                        $occurrence . ' Load existing combination load from' .
                        ' $id_product_attribute->' . print_r($id_product_attribute, 1),
                        'syncdata'
                    );
                    // $comb = new CombinationCore($id_product_attribute, null, $shop_id);
                    $comb = new Combination($id_product_attribute, null, $shop_id);
                    $this->debbug(
                        $occurrence . ' Product before test id before is different product_id ->' .
                        print_r($comb->id_product, 1) .
                        ' product_id-> ' . $product_id,
                        'syncdata'
                    );
                    if ($comb->id_product != $product_id) {
                        $this->debbug(
                            $occurrence . 'Product id  is different,' .
                            ' delete variant and images and resync product_id ->' .
                            print_r($comb->id_product, 1) .
                            ' product_id-> ' . $product_id,
                            'syncdata'
                        );

                        /**
                         * If parent product is different remove old images from parent product
                         */
                        try {
                            $this->syncVariantImageToProduct(
                                array(),
                                $this->defaultLanguage,
                                $comb->id_product,
                                array(''),
                                $id_product_attribute,
                                $product_format_id
                            );
                        } catch (Exception $e) {
                            $this->debbug(
                                '## Error. ' . $occurrence . '. In parent product is different remove' .
                                 'old images from parent product and set to newest  error->' .
                                print_r(
                                    $e->getMessage(),
                                    1
                                ),
                                'syncdata'
                            );
                        }
                    }
                    $comb->id_product = $product_id;
                } else {
                    $combination_generated = true;
                    if ($sl_product_format_id) {
                        $this->debbug(
                            $occurrence . ' Load existing combination load from' .
                            ' $sl_product_format_id->' . print_r($sl_product_format_id, 1),
                            'syncdata'
                        );
                        //$comb = new CombinationCore($sl_product_format_id, null, $shop_id);
                        $comb = new Combination($sl_product_format_id, null, $shop_id);
                    } else {
                        $this->debbug(
                            $occurrence . 'create new combination',
                            'syncdata'
                        );
                        //$comb = new CombinationCore(null, null, $shop_id);
                        $comb = new Combination(null, null, $shop_id);
                        $is_new_variant = true;
                    }

                    $comb->id_product = $product_id;
                    try {
                        if ($sl_product_format_id) {
                            $comb->save();
                        } else {
                            $comb->add();
                        }
                    } catch (Exception $e) {
                        $syncVariant = true; //false if error not retry
                        $this->debbug(
                            '## Error.  In save Variant->' .
                            print_r(
                                $e->getMessage(),
                                1
                            ),
                            'syncdata'
                        );
                    }
                }

                //if (!empty($attributes) && (!$sl_product_format_id || $combination_changed) && $processedShop == 0) {
                /**
                 * Overwrite combinations info if is changed
                 */
                $this->debbug(
                    $occurrence . 'Synchronize attributes before setting attributes array ->' .
                        print_r($attributes, 1),
                    'syncdata'
                );
                try {
                    $comb->setAttributes($attributes);
                } catch (Exception $e) {
                    $syncVariant = true;
                    $this->debbug(
                        '## Error. ' . $occurrence . ' In setAttributes->' . print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }

                /*   $test_query =  sprintf('SELECT ac.id_attribute FROM ' . _DB_PREFIX_ .
                                          'product_attribute_combination ac ' .
                                          ' WHERE ac.id_product_attribute = "%s" ', $comb->id);

                   $test_result = Db::getInstance()->executeS(
                       $test_query
                   );
                   if (count($test_result)) {
                       $attributes_stat = [];
                       foreach ($test_result as $attribute) {
                           $attributes_stat[] = $attribute['id_attribute'];
                       }
                       if ($this->slArrayDiff($attributes, $attributes_stat)) {
                           $this->debbug(
                               $occurrence . ' Warning difference! Test attributes in combination ->' .
                               print_r($attributes, 1) . ' from bd->' . print_r($attributes_stat, 1) .
                               ' query->' . print_r($test_query, 1) .
                               ' result ->' . print_r($test_result, 1),
                               'syncdata'
                           );
                       }
                   }*/

                //}

                $format_img_ids = array();
                $update_mostrar = $mostrar = false;
                $current_supplier_collection = array();
                if (!empty($fieldsBase)) {
                    try {
                        $current_supplier_collection = ProductSupplier::getSupplierCollection($product_id, false);
                    } catch (Exception $e) {
                        $syncVariant = false;
                        $this->debbug(
                            '## Error. ' . $occurrence . ' In ProductSupplier::getSupplierCollection->' . print_r(
                                $e->getMessage(),
                                1
                            ),
                            'syncdata'
                        );
                    }
                    $processed_suppliers = array();
                    $supplier_processed = false;

                    foreach ($fieldsBase as $key => $value) {
                        switch ($key) {
                            case 'quantity':
                                    $stock = $value;

                                break;

                            case (preg_match('/format_supplier_\+?\d+$/', $key) ? true : false):
                                $number_field = str_replace('format_supplier_', '', $key);

                                if ($number_field) {
                                    $supplier_processed = true;
                                    if (!empty($value)) {
                                        if (is_array($value)) {
                                            $value = reset($value);
                                        }

                                        $supplier = new Supplier();

                                        $id_supplier = 0;

                                        if (is_numeric($value)) {
                                            $supplier_exists = $supplier->supplierExists($value);
                                            if ($supplier_exists) {
                                                $id_supplier = (int) $value;
                                            } else {
                                                $supplier_exists = $supplier->getIdByName($value);
                                                if ($supplier_exists) {
                                                    $id_supplier = $supplier_exists;
                                                }
                                            }
                                            if ($supplier_exists) {
                                                $id_supplier = $supplier_exists;
                                            }
                                        } else {
                                            $supplier_exists = $supplier->getIdByName($value);
                                            if ($supplier_exists) {
                                                $id_supplier = $supplier_exists;
                                            } else {
                                                $supplier_exists = $supplier->getIdByName(
                                                    Tools::strtolower(str_replace('_', ' ', $value))
                                                );
                                                if ($supplier_exists) {
                                                    $id_supplier = $supplier_exists;
                                                } else {
                                                    $supplier_exists = $supplier->getIdByName(
                                                        Tools::strtolower(str_replace(' ', '_', $value))
                                                    );
                                                    if ($supplier_exists) {
                                                        $id_supplier = $supplier_exists;
                                                    }
                                                }
                                            }
                                        }

                                        if ($id_supplier == 0) {
                                            try {
                                                $supplier             = new Supplier();
                                                $supplier->name       = $value;
                                                $supplier->active     = 1;
                                                $supplier->add();
                                                $id_supplier =   $supplier->id;
                                            } catch (Exception $e) {
                                                $this->debbug(
                                                    '## Error. ' . $occurrence . ': in create new  supplier->' .
                                                    print_r($e->getMessage(), 1) .
                                                    'line->' . $e->getLine(),
                                                    'syncdata'
                                                );
                                            }
                                        }

                                        if ($id_supplier != 0) {
                                            try {
                                                $format_supplier_reference_index = 'format_supplier_reference_' .
                                                                              $number_field;
                                                if (isset($fieldsBase[$format_supplier_reference_index])
                                                    && $fieldsBase[$format_supplier_reference_index] != ''
                                                ) {
                                                    $productObject = new Product($product_id, null, null, $shop_id);
                                                    $supplier_reference = $fieldsBase['format_supplier_reference_'  .
                                                                                  $number_field];
                                                    $productObject->addSupplierReference(
                                                        $id_supplier,
                                                        $comb->id,
                                                        $supplier_reference
                                                    );
                                                    unset($productObject);
                                                }
                                            } catch (Exception $e) {
                                                $syncVariant = false;
                                                $this->debbug(
                                                    '## Error. ' . $occurrence .
                                                    '  In addSupplierReference->' . print_r(
                                                        $e->getMessage(),
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                            }
                                            $processed_suppliers[$id_supplier] = 0;
                                        }
                                    }
                                }

                                break;

                            case (preg_match('/supplier_reference_\+?\d+$/', $key) ? true : false):
                                break;

                            case 'wholesale_price':
                                $price = (float)abs($value);

                                if (Validate::isPrice($price)) {
                                    $comb->wholesale_price = $price;
                                }

                                break;
                            case 'ecotax':
                                $ecotax = (float)abs($value);
                                if (Validate::isPrice($ecotax)) {
                                    $comb->ecotax = $ecotax;
                                }
                                break;
                            case 'available_date':
                                if (is_array($value)) {
                                    $value = reset($value);
                                }
                                if ($value != null && $value != '' &&
                                    $value != '0000-00-00 00:00:00'
                                ) {
                                    $available_date = $value;
                                    if (is_string($value)) {
                                        $date_val =  $this->strtotime($value);
                                        if (!$date_val) {
                                            $this->debbug($occurrence . ' value converted to timestamp  ->' .
                                                          print_r($value, 1), 'syncdata');
                                            $valuetm = $date_val;
                                        } else {
                                            $this->debbug($occurrence . 'Problem! ' .
                                                          'In available_date convert this time to timestamp.' .
                                                          ' Please try another format of date used by strtotime().' .
                                                          ' Set the original time  ->' .
                                                          print_r($value, 1), 'syncdata');
                                            $valuetm = $value;
                                        }
                                    } else {
                                        $valuetm = $value;
                                    }
                                    if (is_numeric($valuetm) && (int) $valuetm == $valuetm) {
                                        $available_date = date('Y-m-d', $valuetm);
                                    }
                                    $comb->available_date = $available_date;
                                }
                                break;
                            case 'price_tax_excl':
                                $comb->price = (float) $value;

                                break;
                            case 'price_tax_incl':
                                $productObjectTaxes = new Product($product_id, null, null, $shop_id);
                                $price = $this->priceForamat(
                                    str_replace(
                                        ',',
                                        '.',
                                        $value
                                    ) / (1 + ($productObjectTaxes->getTaxesRate() / 100))
                                );
                                unset($productObjectTaxes);
                                $comb->price = $price;

                                break;
                            case 'price':
                                continue 2;

                            case 'default_on':
                                $valida_val = $this->slValidateBoolean($value);
                                if (Validate::isBool($valida_val)) {
                                    if ($valida_val) {
                                        ObjectModel::updateMultishopTable('Combination', array(
                                            'default_on' => null,
                                        ), 'a.`id_product` = ' . (int) $product_id);
                                        $comb->default_on = 1;
                                    }
                                } else {
                                    $this->debbug($occurrence . '## Warning. Default_on is not boolean value ->' .
                                                  print_r($value, 1), 'syncdata');
                                }
                                break;
                            case 'mostrar':
                                $mostrar = $this->slValidateBoolean($value);

                                if ($this->slValidateBoolean($mostrar)) {
                                    $check_column = Db::getInstance()->executeS(
                                        sprintf(
                                            'SELECT * FROM information_schema.COLUMNS
                                                    WHERE TABLE_SCHEMA = "' . _DB_NAME_ . '"
                                                    AND TABLE_NAME = "' . $this->product_attribute_table .
                                                    '" AND COLUMN_NAME = "mostrar"'
                                        )
                                    );
                                } else {
                                    $this->debbug($occurrence . '## Warning. Mostrar is not boolean value ->' .
                                                  print_r($value, 1), 'syncdata');
                                }
                                if (!empty($check_column)) {
                                    $update_mostrar = true;
                                }

                                break;
                            case 'frmt_image':
                                // $this->debbug('Processing frmt_image of shop '.$shop_id.'
                                // content -> '.print_r($value,1),'syncdata');

                                if ($this->product_format_has_frmt_image && $this->first_sync_shop) {
                                    // entry only in first shop
                                    if (!empty($value)) {
                                        $this->debbug($occurrence . ' Entering to process images', 'syncdata');

                                        try {
                                            $format_img_ids = $this->syncVariantImageToProduct(
                                                $value,
                                                $currentLanguage,
                                                $product_id,
                                                $alt_attribute_for_image,
                                                $comb->id,
                                                $product_format_id
                                            );
                                        } catch (Exception $e) {
                                            $syncVariant = true;
                                            $this->debbug(
                                                '## Error. ' . $occurrence . ' In syncVariantImageToProduct->' .
                                                print_r(
                                                    $e->getMessage() . ' line->' . print_r($e->getLine(), 1) .
                                                    ' track->' . print_r($e->getTrace(), 1),
                                                    1
                                                ),
                                                'syncdata'
                                            );
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

                                      $check_img_ids = Db::getInstance()->executeS(sprintf('
                            SELECT si.id_image FROM '._DB_PREFIX_.'slyr_image AS si
                             RIGHT JOIN '.$this->image_table.' AS i on (i.id_image = si.id_image)
                             WHERE si.image_reference in ('.$images_references_string.')
                            ORDER BY field(si.image_reference, '.$images_references_string.');'));

                                      if (!empty($check_img_ids)){

                                          foreach ($check_img_ids as $check_img_id) {

                                              $format_img_ids[] = $check_img_id['id_image'];

                                          }

                                      }

                                  }

                                  break;*/
                            default:
                                $comb->{$key} = $value;
                                break;
                        }
                    }

                    if ($supplier_processed) {
                        foreach ($current_supplier_collection as $current_supplier_item) {
                            if ($current_supplier_item->id_product_attribute == $comb->id
                                && !isset($processed_suppliers[$current_supplier_item->id_supplier])
                            ) {
                                $current_supplier_item->delete();
                            }
                        }
                    }
                }

                if (isset($fieldsBase['minimal_quantity']) && !empty($fieldsBase['minimal_quantity'])) {
                    $comb->minimal_quantity = (int)$fieldsBase['minimal_quantity'];
                } else {
                    $comb->minimal_quantity = 1;
                }

                try {
                    if (!isset($comb->low_stock_alert) || $comb->low_stock_alert == null) {
                        $comb->low_stock_alert = false;
                    }

                    $comb->save();
                } catch (Exception $e) {
                    $syncVariant = true;
                    $this->debbug(
                        '## Error. ' . $occurrence . ' Update Variant->' .
                        print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }


                if ($update_mostrar) {
                    Db::getInstance()->execute(
                        sprintf(
                            'UPDATE ' . $this->product_attribute_table . '
                            SET mostrar = "%s" WHERE id_product_attribute = "%s"',
                            ($mostrar) ? 1 : 0,
                            $comb->id
                        )
                    );
                }

                if ($avoid_stock_update || $is_new_variant) {
                    /**
                     * Set Stock of Variant
                     */

                    StockAvailableCore::setQuantity($product_id, $comb->id, $stock, $shop_id);
                }


                if (!empty($format_img_ids)) {

                    /**
                     * UPDATE IMAGES IDS
                     */
                    $this->debbug(
                        $occurrence . ' Assigning images to variant with the Prestashop ID  ->'
                        . $comb->id . ' calling to set images ids for images-> ' . print_r(
                            $format_img_ids,
                            1
                        ),
                        'syncdata'
                    );
                    $all_shops_image = Shop::getShops(true, null, true);

                    try {
                        $comb->setImages($format_img_ids);
                    } catch (Exception $e) {
                        $this->general_error = true;
                        $this->debbug(
                            '##Error. ' . $occurrence . ' Variant image in set image ->'
                            . $comb->id . 'set images to variants $format_img_ids ->' .
                            print_r($format_img_ids, 1) . ' errormessage->' . $e->getMessage() .
                            ' line->' . $e->getLine() .
                            ' Trace->' . print_r($e->getTrace(), 1),
                            'syncdata'
                        );
                    }

                    try {
                        $this->debbug(
                            $occurrence . ' How to a set this image to shops  variant id->'
                            . $comb->id . ' All shops -> ' . print_r(
                                $all_shops_image,
                                1
                            ),
                            'syncdata'
                        );
                        $set_to_shops = array();

                        $associated_shops = $comb->getAssociatedShops();
                        $this->debbug(
                            $occurrence . ' Associated shops  variant id->'
                            . $comb->id . ' $associated_shops -> ' . print_r(
                                $associated_shops,
                                1
                            ),
                            'syncdata'
                        );
                        if (count($associated_shops)) {//filter associated stores
                            foreach ($all_shops_image as $shop_id_forset) {
                                if (!in_array($shop_id_forset, $associated_shops, false)) {
                                    $set_to_shops[] = $shop_id_forset;
                                }
                            }
                        } else {
                            $set_to_shops = $all_shops_image;
                        }

                        $comb->associateTo($set_to_shops);
                    } catch (Exception $e) {
                        $this->general_error = true;
                        $this->debbug(
                            '##Error. ' . $occurrence . ' Variant image in associate to ->'
                            . $comb->id . ' calling to set images to all active shops-> ' . print_r(
                                $all_shops_image,
                                1
                            ) . ' errormessage->' . $e->getMessage() . ' line->' . $e->getLine() .
                            ' Trace->' . print_r($e->getTrace(), 1),
                            'syncdata'
                        );
                    }
                } else {
                    $this->debbug(
                        $occurrence . ' This Variant does not have any images  ->' .
                        $comb->id . ' status of ids images-> ' . print_r(
                            $format_img_ids,
                            1
                        ),
                        'syncdata'
                    );
                }

                if (!$combination_generated) {
                    $this->debbug(
                        $occurrence . ' Working update in slyr table of combination  ' . print_r(
                            array($id_product_attribute, $product_format_id, $comp_id),
                            1
                        ),
                        'syncdata'
                    );
                    Db::getInstance()->execute(
                        sprintf(
                            'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl  SET sl.date_upd = CURRENT_TIMESTAMP()
                             WHERE sl.ps_id = "%s" AND sl.slyr_id = "%s" AND sl.comp_id = "%s"
                             AND sl.ps_type = "combination"',
                            $id_product_attribute,
                            $product_format_id,
                            $comp_id
                        )
                    );
                } else {
                    $this->debbug(
                        $occurrence . ' Working on inserting in the slyr table of combination  ' . print_r(
                            array($comb->id, $product_format_id, 'combination', $comp_id),
                            1
                        ),
                        'syncdata'
                    );
                    Db::getInstance()->execute(
                        sprintf(
                            'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                            (ps_id, slyr_id, ps_type, comp_id, date_add)
                             VALUES ("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                            $comb->id,
                            $product_format_id,
                            'combination',
                            $comp_id
                        )
                    );
                }
                $processedShop++;
            }
        }

        if ($syncVariant) {
            if ($this->general_error) {
                $data_hash = null;
            }
            $prepare_input_compare = [];
            $prepare_input_compare['sl_id']               = $product_format_id;
            $prepare_input_compare['ps_type']             = 'product_format';
            $prepare_input_compare['conn_id']             = $connector_id;
            $prepare_input_compare['ps_id']               = $comb->id;
            $prepare_input_compare['hash']                = $data_hash;

            $query_insert =   SalesLayerImport::setRegisterInputCompare($prepare_input_compare);
            $this->debbug(
                $occurrence . ' Inserting data hash:' . print_r($query_insert, 1),
                'syncdata'
            );
            unset($comb);
            $this->saveProductIdForIndex($product_id, $connector_id);
            return ['stat' => 'item_updated'];
        } else {
            return ['stat' => 'item_not_updated'];
        }
    }

    public function deleteVariant(
        $product_format,
        $comp_id,
        $shops,
        $reference = null,
        $product_id = null
    ) {
        $format_ps_id = (int)Db::getInstance()->getValue(
            sprintf(
                'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                 WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "combination"',
                $product_format,
                $comp_id
            )
        );

        if (!$format_ps_id && $reference != null &&
            count($shops) == count(Shop::getShops(true, null, true)) &&
                                   $product_id != null
        ) {
            $format_ps_id = (int) Db::getInstance()->getValue(
                sprintf(
                    'SELECT pa.id_product_attribute FROM ' . _DB_PREFIX_ . 'product_attribute pa
                 WHERE pa.reference = "%s" AND pa.id_product = "%s" ',
                    $reference,
                    $product_id
                )
            );
        }
        if ($format_ps_id) {
            $ps_product_id = '';
            foreach ($shops as $shop) {
                try {
                    Shop::setContext(Shop::CONTEXT_SHOP, $shop);
                    $form = new CombinationCore($format_ps_id, null, $shop);
                    if ($ps_product_id == '') {
                        $ps_product_id = $form->id_product;
                    }
                    $form->delete();
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. Deleting variant ID : ' . $product_format . '  error->' . print_r(
                            $e->getMessage(),
                            1
                        ),
                        'syncdata'
                    );
                }
            }
            $this->debbug(' Deleting variant : ' . $format_ps_id . '  ', 'syncdata');
            if ($ps_product_id != '') {
                try {
                    $this->syncVariantImageToProduct(
                        array(),
                        $this->defaultLanguage,
                        $ps_product_id,
                        array(''),
                        $format_ps_id,
                        $product_format
                    );
                } catch (Exception $e) {
                    $this->debbug(
                        '## Error. In removing variant image from product : ' . $format_ps_id . '  error->' . print_r(
                            $e->getMessage() . ' line->' .
                            $e->getLine() .
                            ' track ->' .
                            print_r($e->getTrace(), 1),
                            1
                        ),
                        'syncdata'
                    );
                }
            }
            Db::getInstance()->execute(
                sprintf(
                    'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product
                 WHERE slyr_id = "%s"
                  AND comp_id = "%s"
                  AND ps_type = "combination"',
                    $product_format,
                    $comp_id
                )
            );
        }
    }

    /**
     * Synchronize an attribute
     *
     * @param        $attribute_group_id int  attribute_group_id
     * @param        $attributeValue     string value of attribute
     * @param        $product_format_id       string if of attribute
     * @param string $connector_id
     * @param        $comp_id
     * @param        $conn_shops
     * @param        $currentLanguage
     * @param        $multilanguage      array of multi-language values
     *
     * @return array|bool|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    protected function synchronizeAttribute(
        $attribute_group_id,
        $attributeValue,
        $product_format_id,
        $connector_id,
        $comp_id,
        $conn_shops,
        $currentLanguage,
        $multilanguage
    ) {
        $this->debbug(
            'Entering to  $attribute_group_id->' . print_r($attribute_group_id, 1) . '  $attributeValue ->' . print_r(
                $attributeValue,
                1
            ) . ' $product_format_id->' . print_r($product_format_id, 1) . '  $this->comp_id ->' . print_r(
                $comp_id,
                1
            ) . '  $conn_shops->' . print_r($conn_shops, 1) . '  $currentLanguage ->' . print_r(
                $currentLanguage,
                1
            ) . '  $this->defaultLanguage ->' . print_r($this->defaultLanguage, 1) . ' $multilanguage->' . print_r(
                $multilanguage,
                1
            ),
            'syncdata'
        );

        //Buscamos registro en tabla Slyr con cualquier idioma
        $schema = 'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
        WHERE sl.slyr_id = "' . $product_format_id . '" AND sl.comp_id = "' . $comp_id . '"
        AND sl.ps_attribute_group_id = "' . $attribute_group_id . '" AND sl.ps_type = "product_format_value"';
        $attribute_exists = Db::getInstance()->executeS($schema);

        try {

            /**
             * Verify if is a color
             */

            $schemaGroupAttributeColor = ' SELECT `is_color_group` ' .
                                         ' FROM ' . $this->attribute_group_table .
                                         " WHERE `id_attribute_group` = '" . $attribute_group_id . "' ";

            $isColorGroupAttribute = Db::getInstance()->executeS($schemaGroupAttributeColor);
            $this->debbug('before test Attribute is a color group ->' .
                          print_r($isColorGroupAttribute[0]['is_color_group'], 1), 'syncdata');

            $is_color = false;
            if (count($isColorGroupAttribute) > 0) {
                if ($isColorGroupAttribute[0]['is_color_group'] == 1) {
                    $this->debbug('Attribute is a color group ->' .
                                  print_r($isColorGroupAttribute[0]['is_color_group'], 1), 'syncdata');
                    $is_color = true;
                }
            }


            $sql_hexatag = '';
            $color_hex = array();
            if (empty($multilanguage)) {
                $left_group_lang = " = '" . $currentLanguage . "'";

                $separate = explode(':', $attributeValue);
                if (count($separate) > 2) {
                    $valid_values = [];
                    foreach ($separate as $value) {
                        if (!preg_match('/#/', $value)) {
                            $valid_values[] = trim($value);
                        } else {
                            $color_hex[] = trim($this->clearHexcolor($value));
                        }
                    }
                    $att_value = implode(': ', $valid_values);
                } else {
                    $att_value = reset($separate);
                    if (isset($separate[1]) && preg_match('/#/', $separate[1])) {
                        $color_hex[] = trim($this->clearHexcolor($separate[1]));
                    }
                }

                $left_group_value = " al.`name` LIKE '" . addslashes($att_value) . "'";
                $this->debbug(
                    'Search attribute query from  L1 name LIKE ->' . print_r($left_group_value, 1),
                    'syncdata'
                );
            } else {
                $left_group_lang = " IN('" . implode("','", array_keys($multilanguage)) . "') ";

                if (count($multilanguage) > 1) {
                    $left_group_value = ' ( ';
                    $counter = 1;
                    //  $atr_values =   array_values($multilenguage);
                    $count_end = count($multilanguage);
                    foreach ($multilanguage as $id_lang => $col_like) {
                        $separate = explode(':', $col_like);
                        if (count($separate) > 2) {
                            $valid_values = [];
                            foreach ($separate as $value) {
                                if (!preg_match('/#/', $value)) {
                                    $valid_values[] = trim($value);
                                } else {
                                    $color_hex[] = trim($this->clearHexcolor($value));
                                }
                            }
                            $att_value = implode(': ', $valid_values);
                        } else {
                            $att_value = reset($separate);
                            if (isset($separate[1]) && preg_match('/#/', $separate[1])) {
                                $color_hex[] = trim($this->clearHexcolor($separate[1]));
                            }
                        }

                        if ($count_end == $counter) {
                            $left_group_value .= " ( al.`id_lang` = '" . $id_lang . "' AND  " .
                                                " al.`name` LIKE '" . addslashes($att_value)."' ) ";
                        } else {
                            $left_group_value .= " (al.`id_lang` = '" . $id_lang . "' AND  .
                                                al.`name` LIKE '" . addslashes($att_value). "' ) OR ";
                        }
                        $counter++;
                    }
                    $left_group_value .= ' ) ';
                    $this->debbug(
                        'Search attribute L2 query from name LIKE ' . print_r($left_group_value, 1),
                        'syncdata'
                    );
                } else {
                    $separate = explode(':', reset($multilanguage));
                    if (count($separate) > 2) {
                        $valid_values = [];
                        foreach ($separate as $value) {
                            if (!preg_match('/#/', $value)) {
                                $valid_values[] = trim($value);
                            } else {
                                $color_hex[] = trim($this->clearHexcolor($value));
                            }
                        }
                        $att_value = implode(': ', $valid_values);
                    } else {
                        $att_value = reset($separate);
                        if (isset($separate[1]) && preg_match('/#/', $separate[1])) {
                            $color_hex[] = trim($this->clearHexcolor($separate[1]));
                        }
                    }

                    $left_group_value = " al.`name` LIKE '" . addslashes($att_value) . "' ";
                    $this->debbug(
                        'Search attribute query from  L3 name LIKE ->' . print_r($left_group_value, 1),
                        'syncdata'
                    );
                }
            }

            $color_hex_values = array_count_values($color_hex);
            $count_var = count($color_hex_values);
            $this->debbug(
                'counter de hex ->' . print_r($count_var, 1),
                'syncdata'
            );
            if ($count_var > 1) {
                $this->debbug(
                    '## Warning. ' . print_r($multilanguage, 1) .
                    ' Several different values for the color have been detected. ' .
                    'He needs your attention to fix it  ->' .
                    print_r($color_hex, 1),
                    'syncdata'
                );
                $more_colors = key($color_hex_values);
                $sql_hexatag = ' AND a.`color` LIKE "' . $more_colors . '"';
            } else {
                if ($count_var) {
                    $this->debbug(
                        'Strict query for search this color ->' . print_r($color_hex, 1),
                        'syncdata'
                    );
                    $sql_hexatag = ' AND a.`color` LIKE "' . reset($color_hex) . '"';
                }
            }
            /**
             * Ignore hex color code
             */
            if ($this->ignore_hex_color_code && $sql_hexatag != '') {
                $this->debbug(
                    'Hex color code will be ignored for respecting the set configuration.' .
                            '-> ignore_hex_color_code ->' . print_r($this->ignore_hex_color_code, 1),
                    'syncdata'
                );
                $sql_hexatag = '';
            }
            if (!$is_color && $sql_hexatag != '') {
                $this->debbug(
                    '## Warning. If the group is not a color group,' .
                    ' all colors will be ignored. ->' . print_r(reset($color_hex), 1),
                    'syncdata'
                );
                $sql_hexatag = '';
            }


            //Buscamos id de atributo con padre,nombre e idioma ya existente
            $schemaAttribute = 'SELECT al.`id_attribute` ' .
                'FROM ' . $this->attribute_group_table . ' ag ' .
                'LEFT JOIN ' . $this->attribute_group_lang_table . ' agl ' .
                '	ON (ag.`id_attribute_group` = agl.`id_attribute_group`
                 AND agl.`id_lang` ' . $left_group_lang . ' ) ' .
                'LEFT JOIN ' . $this->attribute_table . ' a ' .
                '	ON a.`id_attribute_group` = ag.`id_attribute_group` ' .
                'LEFT JOIN ' . $this->attribute_lang_table . ' al ' .
                '	ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` ' . $left_group_lang . ') ' .
                "WHERE  ag.`id_attribute_group` = '" . $attribute_group_id . "'  AND " . $left_group_value .
                 $sql_hexatag . '  ' . ' GROUP BY al.`id_attribute` , agl.`name`, a.`position`' .
                'ORDER BY agl.`name` ASC, a.`position` ASC';
            try {
                $isAttribute = Db::getInstance()->executeS($schemaAttribute);
                $this->debbug(
                    'Search attribute query ->' . print_r($schemaAttribute, 1) .
                    ' and result ->' . print_r($isAttribute, 1),
                    'syncdata'
                );
            } catch (Exception $e) {
                $this->debbug(
                    '## Error. in query for search attribute  ->' . print_r($schemaAttribute, 1),
                    'syncdata'
                );
            }
            $this->debbug('## in search  attribute query->' . print_r($schemaAttribute, 1), 'syncdata');
        } catch (Exception $e) {
            $isAttribute = [];
            $this->debbug(
                '## Error. ' . print_r($multilanguage, 1) . '  line->' . $e->getLine(),
                'syncdata'
            );
        }

        // $this->debbug('Attribute after search ->'.print_r($isAttribute,1),'syncdata');
        $attribute_value_id = 0;
        if (!$isAttribute || count($isAttribute) == 0) {
            /**
             * ######################################################################################
             * CREATION OF NEW ATTRIBUTES IF NOT EXIST
             * ######################################################################################
             * To disable creation of new attributes if they do not exist.
             * For deactivate change $create_new_attributes to false in the saleslayerimport.php file
             * ######################################################################################
             */
            if ($this->create_new_attributes) {
                $this->debbug('Attribute does not exist ->' . print_r($isAttribute, 1), 'syncdata');
                /**
                 * Not exist this attribute
                 */

                $schemaGroupAttributeColor = 'SELECT `is_color_group` ' .
                    ' FROM ' . $this->attribute_group_table .
                    " WHERE `id_attribute_group` = '" . $attribute_group_id . "' ";

                $isColorGroupAttribute = Db::getInstance()->executeS($schemaGroupAttributeColor);

                $is_color = false;
                $this->debbug('Before test Attribute is a color group ->' .
                              print_r($isColorGroupAttribute[0]['is_color_group'] .
                                        ' query->' . print_r($schemaGroupAttributeColor, 1), 1), 'syncdata');
                if (count($isColorGroupAttribute) > 0) {
                    if ($isColorGroupAttribute[0]['is_color_group'] == 1) {
                        $this->debbug('Attribute is a color group ->' .
                                      print_r($isColorGroupAttribute[0]['is_color_group'], 1), 'syncdata');
                        $is_color = true;
                    }
                }
                $show_color = '#ffffff';
                //Creamos nuevo atributo

                if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                    // PrestaShop 8.0.0 y versiones posteriores
                    $attribute = new ProductAttributeCore();
                } else {
                    // Versiones anteriores a PrestaShop 8.0.0
                    $attribute = new AttributeCore();
                }
                $attribute->name = array();
                try {
                    if (!empty($multilanguage)) {
                        /**
                         * Is multi-language create attribute with all Values with more languages
                         */

                        foreach ($multilanguage as $id_lang => $att_value) {
                            if ($is_color && $show_color == '#ffffff') {
                                $picked = $this->stringToColorCode($att_value, $id_lang);
                                if ($picked != null) {
                                    $this->debbug('color encontrado ->' . print_r($picked, 1), 'syncdata');
                                    $show_color = $picked;
                                }
                            }
                            if (preg_match('/:#/', $att_value)) {
                                $separate = explode(':', $att_value);
                                if (count($separate) > 2) {
                                    $valid_values = [];
                                    foreach ($separate as $value) {
                                        if (!preg_match('/#/', $value)) {
                                            $valid_values[] = trim($value);
                                        } else {
                                            $show_color = trim($this->clearHexcolor($value));
                                        }
                                    }
                                    $att_value = implode(': ', $valid_values);
                                } else {
                                    if (preg_match('/#/', $separate[1])) {
                                        $show_color = trim($this->clearHexcolor(end($separate)));
                                    }
                                    $att_value = reset($separate);
                                }
                            }
                            $attribute->name[$id_lang] = $att_value;
                            if ($id_lang != $this->defaultLanguage) {
                                if (!isset($attribute->name[$this->defaultLanguage]) ||
                                    $attribute->name[$this->defaultLanguage] == null ||
                                    $attribute->name[$this->defaultLanguage] == ''
                                ) {
                                    $attribute->name[$this->defaultLanguage] = $att_value;
                                }
                            }
                        }
                    } else {
                        /**
                         * Only one language
                         */

                        if ($is_color && $show_color == '#ffffff') { // is color? how to pick color from word
                            $picked = $this->stringToColorCode($attributeValue, $currentLanguage);
                            if ($picked != null) {
                                $show_color = $picked;
                            }
                        }
                        if (preg_match('/:#/', $attributeValue)) {
                            $separate = explode(':', $attributeValue);

                            if (count($separate) > 2) {
                                $valid_values = [];
                                foreach ($separate as $value) {
                                    if (!preg_match('/#/', $value)) {
                                        $valid_values[] = trim($value);
                                    } else {
                                        $show_color = trim($this->clearHexcolor($value));
                                    }
                                }
                                $attributeValue = implode(': ', $valid_values);
                            } else {
                                if (preg_match('/#/', $separate[1])) {
                                    $show_color = trim($this->clearHexcolor(end($separate)));
                                }
                                $attributeValue = reset($separate);
                            }
                        }
                        $attribute->name[$currentLanguage] = $attributeValue;
                        if ($currentLanguage != $this->defaultLanguage) {
                            if (!isset($attribute->name[$this->defaultLanguage])
                                || $attribute->name[$this->defaultLanguage] == null
                                || $attribute->name[$this->defaultLanguage] == ''
                            ) {
                                $attribute->name[$this->defaultLanguage] = $attributeValue;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->general_error = true;
                    $this->debbug(
                        '## Error. ' . print_r($multilanguage, 1) . ' Creating attribute->' . print_r(
                            $e->getMessage(),
                            1
                        ) . ' line->' . print_r($e->getLine(), 1) . ' track->' .
                        print_r($e->getTrace(), 1),
                        'syncdata'
                    );
                }

                $attribute->id_attribute_group = $attribute_group_id;
                if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                    // PrestaShop 8.0.0 y versiones posteriores
                    $position = ProductAttributeCore::getHigherPosition($attribute_group_id);
                } else {
                    // Versiones anteriores a PrestaShop 8.0.0
                    $position = AttributeCore::getHigherPosition($attribute_group_id);
                }
                $attribute->position = $position == null ? 0 : $position + 1;

                if ($is_color) {
                    $attribute->color = $show_color;
                    $this->debbug('Set color ->' . print_r($show_color, 1), 'syncdata');
                } else {
                    if ($show_color != '#ffffff' && !$is_color) {
                        $this->debbug('## Warning. A color cannot be added to this group because it is not a ' .
                                      ' Color or Texture group ->' . print_r($show_color, 1), 'syncdata');
                    }
                }

                try {
                    $attribute->add();
                } catch (Exception $e) {
                    $this->general_error = true;
                    $this->debbug(
                        '## Error. ' . print_r($multilanguage, 1) . '  Creating New attribute ->' . print_r(
                            $e->getMessage(),
                            1
                        ) . ' line->' . print_r($e->getLine(), 1) .
                            ' trace->' . print_r($e->getTrace(), 1),
                        'syncdata'
                    );
                }
                $attribute_value_id = $attribute->id;
            } else {
                $this->general_error = true;
                $this->debbug('## Error. The attribute not found and ' .
                              'creation of new attributes is disabled. ->' .
                              print_r($multilanguage, 1) . print_r($attributeValue, 1), 'syncdata');
                return null;
            }
        } else {
            $this->debbug('Attribute found send only id' . print_r($isAttribute, 1), 'syncdata');
            //Obtenemos id de atributo ya existente

            $isAttribute_first_reg = reset($isAttribute);
            $attribute_value_id = $isAttribute_first_reg['id_attribute'];

            if (!empty($multilanguage)) {
                $this->debbug(
                    'field is a multi-language' . print_r(
                        $isAttribute,
                        1
                    ) . ', values of multi-language ->' . print_r(
                        $multilanguage,
                        1
                    ),
                    'syncdata'
                );
                /**
                 * Update if is an Language more possible to set or overwrite
                 */
                $update_needed = false;
                if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                    // PrestaShop 8.0.0 y versiones posteriores
                    $attribute = new ProductAttributeCore($attribute_value_id);
                } else {
                    // Versiones anteriores a PrestaShop 8.0.0
                    $attribute = new AttributeCore($attribute_value_id);
                }

                foreach ($multilanguage as $id_lang => $Value) {
                    if (($attribute->name[$id_lang] == null
                            || $attribute->name[$id_lang] == '') && $Value != '' && $Value != null
                    ) {
                        $this->debbug('set name of $Value->' . print_r($Value, 1), 'syncdata');
                        /**
                         * Any translate is diferent?
                         */
                        if (preg_match('/:/', $Value)) {
                            $separate = explode(':', $Value);

                            if (count($separate) > 2) {
                                $valid_values = [];
                                foreach ($separate as $value) {
                                    if (!preg_match('/#/', $value)) {
                                        $valid_values[] = trim($value);
                                    }
                                }
                                $Value = implode(': ', $valid_values);
                            } else {
                                $Value = reset($separate);
                            }
                        }

                        $attribute->name[$id_lang] = $Value;

                        $update_needed = true;
                        $this->debbug(
                            'Setting name for attribute in another language need update...' . print_r(
                                $Value,
                                1
                            ) . ' array->' . print_r($attribute->name, 1),
                            'syncdata'
                        );
                    }
                }
                if ($update_needed) {
                    try {
                        $attribute->save();
                    } catch (Exception $e) {
                        $this->general_error = true;
                        $this->debbug(
                            '## Error. ' . print_r($multilanguage, 1) . ' Save changes to Attribute->' . print_r(
                                $e->getMessage(),
                                1
                            ),
                            'syncdata'
                        );
                    }
                }
            }
        }


        if (empty($multilanguage)) {  // if is simple Value  set lenguage to all values
            /**
             * If is empty set language current selected
             */

            $multilanguage[$currentLanguage] = $attributeValue;
        }


        // foreach ($multilanguage as $id_lang => $value){

        if (count($attribute_exists) > 0) {
            $attribute_exists = reset($attribute_exists);
            $attribute_exists_id = $attribute_exists['ps_id'];

            //Buscamos registro de lenguaje en tabla Slyr
            $attribute_lang_exists = (int)Db::getInstance()->getValue(
                sprintf(
                    'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                    WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_attribute_group_id = "%s"
                    AND sl.ps_type = "product_format_value" ',
                    $product_format_id,
                    $comp_id,
                    $attribute_group_id
                )
            ); //  AND id_lang = "%s"  $id_lang

            if (($attribute_lang_exists == 0 || !$attribute_lang_exists)) {
                //|| $attribute_exists_id != $attribute_value_id
                $this->debbug(
                    '  register not founded $attribute_lang_exists->' . print_r($attribute_lang_exists, 1),
                    'syncdata'
                );
                //Inserta registro de lenguaje en tabla Slyr
                Db::getInstance()->execute(
                    sprintf(
                        'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                        (ps_id, slyr_id, ps_type, ps_attribute_group_id, comp_id,  date_add)
                        VALUES("%s", "%s", "%s", "%s", "%s",  CURRENT_TIMESTAMP())',
                        $attribute_value_id,
                        $product_format_id,
                        'product_format_value',
                        $attribute_group_id,
                        $comp_id
                    )
                );
            } else {
                $this->debbug(
                    ' Register found, updating $attribute_lang_exists->' . print_r($attribute_lang_exists, 1),
                    'syncdata'
                );
                //Actualiza registro de lenguaje en tabla Slyr
                Db::getInstance()->execute(
                    sprintf(
                        'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl
                        SET sl.date_upd = CURRENT_TIMESTAMP()
                        WHERE sl.ps_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product_format_value"
                        AND sl.ps_attribute_group_id = "%s" ',
                        $attribute_value_id,
                        $comp_id,
                        $attribute_group_id
                    )
                );
            }

            if ($attribute_exists_id != $attribute_value_id) {
                $update_cache_query = '';
                try {
                    $update_cache_query = sprintf(
                        'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl
                        SET sl.date_upd = CURRENT_TIMESTAMP(), sl.ps_id = "%s"
                         WHERE sl.ps_id = "%s" AND sl.slyr_id = "%s"
                         AND sl.comp_id = "%s" AND sl.ps_type = "product_format_value"
                         AND sl.ps_attribute_group_id = "%s"',
                        $attribute_value_id,
                        $attribute_exists_id,
                        $product_format_id,
                        $comp_id,
                        $attribute_group_id
                    );
                    $this->debbug(
                        'Register  founded, updating $attribute_exists_id != $attribute_value_id,' .
                        '  $attribute_exists_id ->' . print_r(
                            $attribute_exists_id,
                            1
                        ) . '  $attribute_value_id->' .
                        print_r($attribute_value_id, 1) . ' query ->'
                        . print_r($update_cache_query, 1),
                        'syncdata'
                    );
                    Db::getInstance()->execute(
                        $update_cache_query
                    );
                } catch (Exception $e) {
                    $this->general_error = true;
                    $this->debbug(
                        'Error in change id of attribute value in cache $attribute_exists_id != $attribute_value_id,' .
                        '  $attribute_exists_id ->' . print_r(
                            $attribute_exists_id,
                            1
                        ) . '  $attribute_value_id->' .
                        print_r($attribute_value_id, 1) .
                        ' query ->' . print_r($update_cache_query, 1),
                        'syncdata'
                    );
                }
            }
        } else {
            if ($attribute_value_id > 0) {
                $this->debbug(' Inserting record of language in the table Slyr $attribute_exists == 0', 'syncdata');
                //Inserta registro de lenguaje en tabla Slyr
                Db::getInstance()->execute(
                    sprintf(
                        'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                    (ps_id, slyr_id, ps_type, ps_attribute_group_id, comp_id, date_add)
                    VALUES("%s", "%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                        $attribute_value_id,
                        $product_format_id,
                        'product_format_value',
                        $attribute_group_id,
                        $comp_id
                    )
                ); // $id_lang
            } else {
                $this->debbug(
                    'The format cannot be saved correctly because some of the values are incorrect->' .
                    print_r($multilanguage, 1),
                    'syncdata'
                );
            }
        }

        //}

        if (count($conn_shops) > 0 || $attribute_value_id > 0) {
            $this->debbug(
                'Updating shop records ->  count($conn_shops) > 0 ->' . print_r(
                    $conn_shops,
                    1
                ) . ', $attribute_group_id->' . $attribute_group_id,
                'syncdata'
            );
            //Actualizamos tiendas
            $schemaAttrExtra = ' SELECT sla.id, sla.shops_info  FROM ' . _DB_PREFIX_ . 'slyr_category_product sla' .
                ' WHERE sla.ps_id = ' . $attribute_value_id  . '
                 AND sla.ps_attribute_group_id = ' . $attribute_group_id . '
                 AND sla.comp_id = ' . $comp_id . " AND sla.ps_type = 'product_format_value' ";

            $attrsInfo = Db::getInstance()->executeS($schemaAttrExtra);

            $schemaAttrShops = ' SELECT sh.id_shop FROM ' . $this->attribute_shop_table .
                ' sh WHERE sh.id_attribute = ' . $attribute_value_id;

            $attr_shops = Db::getInstance()->executeS($schemaAttrShops);

            foreach ($conn_shops as $shop_id) {
                $found = false;
                //Primero buscamos en las existentes
                if (count($attr_shops) > 0) {
                    foreach ($attr_shops as $key => $attr_shop) {
                        if ($shop_id == $attr_shop['id_shop']) {
                            $found = true;
                            //Eliminamos para obtener sobrantes
                            unset($attr_shops[$key]);
                            break;
                        }
                    }
                }

                if (!$found) {
                    Db::getInstance()->execute(
                        sprintf(
                            'INSERT INTO ' . $this->attribute_shop_table . '(id_attribute, id_shop) VALUES("%s", "%s")',
                            $attribute_value_id,
                            $shop_id
                        )
                    );
                }
            }

            $shopsConnectors = array();
            foreach ($attrsInfo as $attrInfo) {
                if (isset($attrInfo['shops_info'])) {
                    $shops_info = json_decode($attrInfo['shops_info'], true);
                    if (is_array($shops_info) && count($shops_info) > 0) {
                        foreach ($shops_info as $conn_id => $shops) {
                            if (!isset($shopsConnectors[$conn_id])) {
                                $shopsConnectors[$conn_id] = array();
                            }
                            foreach ($shops as $shop) {
                                if (!in_array($shop, $shopsConnectors[$conn_id], false)) {
                                    $shopsConnectors[$conn_id][] = $shop;
                                }
                            }
                        }
                    }
                }
            }



            foreach ($attrsInfo as $attrInfo) {
                if (isset($attrInfo['shops_info'])) {
                    $sl_attr_info_conns = json_decode($attrInfo['shops_info'], true);

                    // Revisamos las sobrantes
                    if (count($attr_shops) > 0) {
                        // Buscamos en conectores
                        foreach ($attr_shops as $attr_shop) {
                            if (is_array($sl_attr_info_conns) && count($sl_attr_info_conns) > 0) {
                                foreach ($sl_attr_info_conns as $sl_attr_info_conn => $sl_attr_info_conn_shops) {
                                    if ($sl_attr_info_conn != $connector_id) {
                                        if (in_array($attr_shop['id_shop'], $sl_attr_info_conn_shops, false)) {
                                            // $found = true;
                                            break;
                                        }
                                    }
                                }

                                if (count($shopsConnectors) > 0) {
                                    foreach ($shopsConnectors as $conn_id => $shopsConnector) {
                                        if ($connector_id != $conn_id && in_array($attr_shop['id_shop'], $shopsConnector, false)) {
                                            // $found = true;
                                        }
                                    }
                                }
                            }
                        }

                        //          if (!$found){
                        //              Db::getInstance()->execute(
                        //                  sprintf('DELETE FROM '.$this->attribute_shop_table.'
                        // WHERE id_attribute = "%s" AND id_shop = "%s"',
                        //                  $attribute_value_id,
                        //                  $attr_shop['id_shop']
                        //              ));
                        // }
                    }
                }
            }

            // foreach ($multilanguage as $id_lang => $value){
            //Actualizamos unicamente el registro de este atributo
            $schemaAttrExtra = ' SELECT sl.id, sl.shops_info FROM ' . _DB_PREFIX_ . 'slyr_category_product sl' .
                ' WHERE sl.ps_id = ' . $attribute_value_id . ' AND sl.ps_attribute_group_id = ' . $attribute_group_id .
                ' AND sl.slyr_id = ' . $product_format_id . ' AND sl.comp_id = ' . $comp_id .
                               " AND sl.ps_type = 'product_format_value'  "; //   AND id_lang = '".$id_lang ."'

            $attrInfo = Db::getInstance()->executeS($schemaAttrExtra);

            if (isset($attrInfo[0]['id'])) {
                if (isset($attrInfo[0]['shops_info'])) {
                    //Actualizamos el registro
                    $sl_attr_info_conns = json_decode($attrInfo[0]['shops_info'], 1);
                    $sl_attr_info_conns[$connector_id] = $conn_shops;
                    $shopsInfo = json_encode($sl_attr_info_conns);
                } else {
                    //Creamos el registro
                    $sl_attr_info_conns[$connector_id] = $conn_shops;
                    $shopsInfo = json_encode($sl_attr_info_conns);
                }

                $schemaUpdateShops = 'UPDATE ' . _DB_PREFIX_ . "slyr_category_product
                SET shops_info = '" . $shopsInfo . "' WHERE id = " . $attrInfo[0]['id'];
                Db::getInstance()->execute($schemaUpdateShops);
            } else {
                $this->debbug(
                    '## warning. ' . print_r(
                        $multilanguage,
                        1
                    ) . ' Register of attribute in this language does not exist $attribute_group_id->'
                    . $attribute_group_id . '
                     $attribute_id = slyr_id->' . $product_format_id . ' comp_id->' . $comp_id .
                    '. This is not a serious problem but working with several connectors' .
                    ' and stores at the same time could lose track. ->' . print_r($attrInfo, 1) .
                    ' query ->' . print_r($schemaAttrExtra, 1),
                    'syncdata'
                );
            }

            //}
        }
        unset($attribute);

        return $attribute_value_id;
    }

    private function clearHexcolor($value)
    {
        $value = str_replace(array( '# ', ':# ' ), array( '#', ':#' ), $value);
        return $value;
    }

    /**
     *Load the predefined colors in the colors folder and look for color name to attach the hex code
     * @param $str string name of color as string
     * @param string $language_code
     *
     * @return string|null
     * @throws PrestaShopDatabaseException
     */
    private function stringToColorCode(
        $str,
        $language_code
    ) {
        $language_code = Language::getIsoById($language_code);
        $colors_arr = array();
        if (preg_match('/:#/', $str)) {
            $valid_values = '#fffff';
            $separate = explode(':', $str);
            if (count($separate) > 2) {
                foreach ($separate as $value) {
                    if (preg_match('/#/', $value)) {
                        $valid_values = trim($value);
                        break;
                    }
                }
            } else {
                if (preg_match('/#/', end($separate))) {
                    $valid_values = trim(end($separate));
                }
            }
            return  $valid_values;
        }
        $color_file = DEBBUG_PATH_LOG . '../colors/' . $language_code . '.txt';
        $this->debbug(
            'Checking if file exists ->' . $color_file,
            'syncdata'
        );

        if (file_exists($color_file)) {
            $opencolors = file($color_file, FILE_SKIP_EMPTY_LINES);
            if ($opencolors && count($opencolors)) {
                foreach ($opencolors as $line) {
                    $line = str_replace(array("'",','), '', $line);
                    $linearr = explode(':', $line);
                    $colors_arr[str_replace(' ', '', trim($linearr[0]))] = trim($linearr[1]);
                }
            }
        } else {
            $this->debbug(
                '## warning Color - hex  link file does not exist for language as file->' . $language_code . '.txt' .
                'you can create a custom one in language that you need on the route ->' .
                _PS_MODULE_DIR_ . 'saleslayerimport/colors/',
                'syncdata'
            );
        }

        $str =  str_replace(array(' ', '  '), '', Tools::strtolower($this->removeAccents($str)));

        return isset($colors_arr[$str]) ?
            (string) $colors_arr[$str] : null;
    }

    /**
     * Move images from variant from SL to parent product of prestashop
     * @param $images
     * @param $id_lang
     * @param $product_id
     * @param $product_name
     * @param $variant_id
     *
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function syncVariantImageToProduct(
        $images,
        $id_lang,
        $product_id,
        $product_name,
        $variant_id,
        $sl_id
    ) {
        if (isset($product_name) && !empty($product_name)) {
            $first_name = reset($product_name);
            if (is_array($first_name)) {
                $first_name = reset($product_name);
            }
            $occurence = ' Variant name :' . print_r($first_name, 1) ;
        } else {
            $occurence = ' ID :' . $product_id;
        }
        $all_shops_image = Shop::getShops(true, null, true);
        $counter_images = 0;

        $this->debbug(
            '$multilanguage array->' . print_r(
                $product_name,
                1
            ),
            'syncdata'
        );

        $image_ids = array();
        $this->debbug(
            'Variant ' . $occurence . ' Entering to a synchronize images from variant ' .
            'to product image array for this $images->' . print_r(
                $images,
                1
            ) . '  $id_lang->' . print_r($id_lang, 1) . ' $product_name-> ' . print_r(
                $product_name,
                1
            ) . ' $variant_id->' . print_r($variant_id, 1),
            'syncdata'
        );
        /**
         *
         * First store only sync images
         */
        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);

        $catch_images = array();

        if (isset($images) && count($images)) {
            /**
             * Process images from this connection
             * Imagenes de formato que han entrado
             */

            foreach ($images as $image_reference => $image_list) {
                if (is_array($image_list)) {
                    /**
                     * Check correct sizes and filter images
                     * Revisar correctos y filtrar
                     */
                    $this->debbug(
                        'Checking correct sizes of image references ->' .
                        $image_reference . ' value ->' . print_r(
                            $image_list,
                            1
                        ),
                        'syncdata'
                    );
                    foreach ($this->format_images_sizes as $imgFormat) {
                        if (isset($image_list[$imgFormat]) && !empty($image_list[$imgFormat])) {
                            $catch_images[$image_reference] = $image_list[$imgFormat];
                            break;
                        }
                    }
                }
            }


            $slyr_images = array();

            if (!empty($catch_images)) {
                /**
                 * How to a search images cached in SL table for MD5 hash
                 */

                // $catch_images_references = "'" . implode("','", array_keys($catch_images)) . "'";
                $prepare_sql = 'SELECT * FROM ' . _DB_PREFIX_ . "slyr_image im
                        WHERE  im.ps_product_id = '" . $product_id . "' " ;
                //     AND im.image_reference IN (" . $catch_images_references . ")  ";

                $this->debbug('Prepared sql for search images  ->' .
                            //  print_r($catch_images_references, 1)
                               ' query->' .
                              print_r($prepare_sql, 1), 'syncdata');
                $slyr_images = Db::getInstance()->executeS($prepare_sql, true, false);
                $this->debbug('Searching images cached in SL table for MD5 hash ->' .
                              print_r($slyr_images, 1), 'syncdata');

                if (!empty($slyr_images)) { // unset nonexistent images
                    foreach ($slyr_images as $slyr_key => $slyr_cache_image) {
                        $test_image = 'SELECT * FROM ' . _DB_PREFIX_ . 'image i
                        WHERE i.id_image =' . $slyr_cache_image['id_image'] . ' ';
                        $test_cache = Db::getInstance()->getValue($test_image);

                        if (!$test_cache) {
                            $this->debbug('##Warning. nonexistent image identified in cache of images->' .
                                          print_r($slyr_cache_image, 1) . ' result ->' .
                                          print_r($test_cache, 1) . 'query ->' .
                                          print_r($test_image, 1), 'syncdata');
                            unset($slyr_images[$slyr_key]);
                            $delete_im_cache = 'DELETE FROM ' . _DB_PREFIX_ .
                                               'slyr_image WHERE id_image ="' .
                                               $slyr_cache_image['id_image'] . '"';
                            Db::getInstance()->execute($delete_im_cache);
                        }
                    }
                }
            }

            /**
             * Process images from this connection
             */
            $this->debbug(
                'Before processing prepared images for update stat of array  ->' . print_r($catch_images, 1),
                'syncdata'
            );
            $ps_images = Image::getImages($id_lang, $product_id);
            if (count($ps_images) == 0) {
                $cover = true;
            } else {
                $this->debbug(
                    'In images exist one image if is cover->' .
                    print_r($ps_images, 1),
                    'syncdata'
                );
                $cover = false;
            }
            /**
             * Get minimal position of images for this variant
             */
            $min_position_query =  'SELECT MIN(`position`) AS min ' .
                    ' FROM ' . _DB_PREFIX_ . 'image i ' .
                    ' INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_image pai ' .
                    ' ON i.id_image = pai.id_image ' .
                    ' INNER JOIN ' . _DB_PREFIX_ . 'product_attribute pa ' .
                    ' ON pai.id_product_attribute = pa.id_product_attribute ' .
                    ' WHERE  pa.id_product = "' . $product_id . '"';

            $min_position = Db::getInstance()->getRow($min_position_query);
            $position_image = 0;

            if (isset($min_position['min']) && !empty($min_position['min'])) {
                if ($min_position['min'] > 0) {
                    $this->debbug('Set min image position of this variant  -> ' .
                                  print_r($min_position['min'], 1), 'syncdata');
                    $position_image = (int) $min_position['min'];
                }
            }
            if ($position_image == 0) { //if not exist position of images for this variant save highest position
                $position_image = Image::getHighestPosition($product_id) + 1;
            }

            foreach ($catch_images as $image_reference => $image_url) {
                $this->debbug(
                    'Processing images of variant and setting it to a product from this connection ->' . print_r(
                        $image_reference,
                        1
                    ) . ' image url-> ' . print_r($image_url, 1) . ' is cover->' . print_r($cover, 1),
                    'syncdata'
                );
                $time_ini_image = microtime(1);
                $url = trim($image_url);

                if (!empty($url)) {
                    $cached = SalesLayerImport::getPreloadedImage($url, 'product_format', $sl_id, true);

                    if ($cached) {
                        $temp_image = stripslashes($cached['local_path']);
                    } else {
                        $temp_image = $this->downloadImageToTemp($url);
                    }
                    if ($temp_image) {
                        if ($cached) {
                            $this->debbug(
                                'Image has been preloaded ->' . print_r(
                                    $cached,
                                    1
                                ),
                                'syncdata'
                            );
                            $md5_image = $cached['md5_image'];
                        } else {
                            $md5_image = md5_file($temp_image);
                        }
                        if (!empty($slyr_images)) {
                            foreach ($slyr_images as $keySLImg => $slyr_image) {
                                $variant_ids = array();
                                if ($slyr_image['ps_variant_id'] != null) {
                                    $variant_ids = json_decode($slyr_image['ps_variant_id'], 1);
                                }
                                $this->debbug(
                                    'Before Verify Processing image ->' . print_r(
                                        $slyr_image,
                                        1
                                    ),
                                    'syncdata'
                                );
                                if ($slyr_image['image_reference'] == $image_reference &&
                                    $slyr_image['md5_image'] !== ''
                                ) {
                                    /**
                                     * Image is the same
                                     */

                                    unset($slyr_images[$keySLImg]);
                                    $this->debbug(
                                        'Before test md5 Processing image ->' . print_r(
                                            $slyr_image['md5_image'],
                                            1
                                        ) . ' <-> ' . print_r($md5_image, 1),
                                        'syncdata'
                                    );
                                    if ($slyr_image['md5_image'] !== $md5_image) {
                                        $this->debbug(
                                            'Image is different ->' . print_r(
                                                $slyr_image['md5_image'],
                                                1
                                            ) . ' <-> ' . print_r($md5_image, 1),
                                            'syncdata'
                                        );
                                        /**
                                         * Image with same name but different md5
                                         */
                                        $this->debbug(
                                            'Before check variants ids ->' . print_r(
                                                $variant_id,
                                                1
                                            ) . ' <-> ' . print_r($variant_ids, 1),
                                            'syncdata'
                                        );
                                        if (in_array((string)$variant_id, $variant_ids, false)) {
                                            /**
                                             * Verify if is needed delete this file
                                             */

                                            $new_array = array();
                                            foreach ($variant_ids as $variant_id_in_search) {
                                                if ($variant_id_in_search != $variant_id) {
                                                    $new_array[] = (string) $variant_id_in_search;
                                                }
                                            }
                                            $this->debbug(
                                                'Verify if is needed delete this file  ->' . print_r(
                                                    $slyr_image['id_image'],
                                                    1
                                                ),
                                                'syncdata'
                                            );

                                            $variant_ids =  $new_array;
                                            if (empty($variant_ids)) {
                                                // this variant  is unique variant in use this file
                                                if ($slyr_image['origin'] == 'frmt' || empty($slyr_image['origin'])) {
                                                    // if origin if the image is this variant delete it  from product
                                                    $image_delete = new Image($slyr_image['id_image']);
                                                    $image_delete->delete();
                                                    $this->debbug(
                                                        'Deleting Image  ->' . print_r(
                                                            $slyr_image['id_image'],
                                                            1
                                                        ) . ' <-> ' . print_r($md5_image, 1),
                                                        'syncdata'
                                                    );
                                                    break;
                                                }
                                            }
                                        } else {
                                            $this->debbug(
                                                'Image is from different variant id_image ->' .
                                                print_r($slyr_image['id_image'], 1),
                                                'syncdata'
                                            );
                                        }
                                    } else {
                                        /**
                                         * Image found / Update this image if is needed
                                         */
                                        $this->debbug(
                                            'Image is the same check only information ' .
                                            ' of alt attribute if $variant_id->' .
                                            print_r($variant_id, 1) .
                                            ' in variants ids -> ' . print_r($variant_ids, 1),
                                            'syncdata'
                                        );
                                        /*   if (in_array(
                                               (string) $variant_id,
                                               $variant_ids,
                                               false
                                           )
                                           ) { // image is the same and this variant is in the array
                                               $this->debbug(
                                                   'Image is the same and this variant is in the array->' .
                                                   print_r($variant_id, 1) .
                                                   ' in variants ids -> ' . print_r($variant_ids, 1),
                                                   'syncdata'
                                               );
                                               $image_ids[] = $slyr_image['id_image'];

                                           } else {*/

                                        // set this variant it to the foto
                                        /**
                                         * aqui continuar con editcion de imagen
                                         */
                                        if (!in_array(
                                            (string) $variant_id,
                                            $variant_ids,
                                            false
                                        )
                                        ) {
                                            $variant_ids[] = (string) $variant_id;
                                        } else {
                                            $this->debbug(
                                                'Not in the array ->' .
                                                print_r($variant_id, 1) .
                                                ' in variants ids -> ' . print_r($variant_ids, 1),
                                                'syncdata'
                                            );
                                        }
                                        $need_update = false;

                                        $image_cover = new Image($slyr_image['id_image']);
                                        $this->debbug('After load image status->' .
                                                      print_r($image_cover, 1), 'syncdata');

                                        $old_position = $image_cover->position;
                                        foreach ($this->shop_languages as $shop_language) {
                                            $name_of_product_save = '';
                                            if (is_array($product_name[$shop_language['id_lang']])) {
                                                $index_alt = $counter_images;

                                                if (isset($product_name[$shop_language['id_lang']][$index_alt])) {
                                                    $name_of_product_save =
                                                            $product_name[$shop_language['id_lang']][$index_alt];
                                                } else {
                                                    $name_of_product_save =
                                                            reset($product_name[$shop_language['id_lang']]) .
                                                            '_(' . $counter_images . ')';
                                                }
                                            }

                                            if ($name_of_product_save != '' &&
                                                isset($image_cover->legend[$shop_language['id_lang']]) &&
                                                         trim(
                                                             $image_cover->legend[$shop_language['id_lang']]
                                                         ) != trim($name_of_product_save)
                                            ) {
                                                $need_update = true;
                                                $image_cover->legend[$shop_language['id_lang']] =
                                                            $name_of_product_save;
                                                $this->debbug(
                                                    'Set image alt attribute need update this image ' .
                                                            'info ->' .
                                                            print_r(
                                                                $image_cover->legend[$shop_language['id_lang']],
                                                                1
                                                            ) .
                                                            '  !=  ' .
                                                            print_r($name_of_product_save, 1),
                                                    'syncdata'
                                                );
                                            } else {
                                                $this->debbug(
                                                    'Image is the same, image alt attribute not is not required ' .
                                                    ' update this image info ->' .
                                                            print_r(
                                                                (isset($image_cover->legend[$shop_language['id_lang']])?
                                                                    $image_cover->legend[$shop_language['id_lang']]:''),
                                                                1
                                                            ) .
                                                            '  ==  ' .
                                                            print_r($name_of_product_save, 1),
                                                    'syncdata'
                                                );
                                            }
                                        }
                                        if (!$cover && $image_cover->cover && $old_position == 1) {
                                            $this->debbug(
                                                'Image is forced set as cover',
                                                'syncdata'
                                            );
                                            $cover = true;
                                            $position_image = 1;
                                        }
                                        if ($cover
                                                && (count(
                                                    $ps_images
                                                ) == 1 || ($image_cover->cover && $old_position == 1))
                                        ) { //  is first image  set to cover && Image is already like cover
                                            //&& !$image_cover->cover
                                            try {
                                                if (!$image_cover->cover) {
                                                    Image::deleteCover(
                                                        $product_id
                                                    ); // delete cover image from this product
                                                }
                                            } catch (Exception $e) {
                                                $this->general_error = true;
                                                $this->debbug(
                                                    '## Error. ' . $occurence . ' Delete cover ->' . print_r(
                                                        $e->getMessage(),
                                                        1
                                                    ),
                                                    'syncdata'
                                                );
                                            }
                                            if ($image_cover->cover) {
                                                $need_update = true;
                                            }
                                            $image_cover->cover = $cover; // set this image as cover
                                            $cover = false;
                                            $this->debbug(
                                                'Image is the only one for this product, setting it as cover ',
                                                'syncdata'
                                            );
                                        } else {
                                            $image_cover->cover = null;
                                        }
                                        $this->debbug('set position to image -> ' .
                                                      print_r($position_image, 1), 'syncdata');
                                        $image_cover->position = $position_image;
                                        $position_image++;
                                        $counter_images++;

                                        if ($need_update) {
                                            $this->debbug('Image needs to update this is object ->' .
                                                          print_r($image_cover, 1), 'syncdata');

                                            try {
                                                $image_cover->save();
                                                //recalcula pisotion for all images
                                                $image_cover->updatePosition($old_position, $image_cover->position);
                                                $image_cover->associateTo($all_shops_image);
                                            } catch (Exception $e) {
                                                $this->general_error = true;
                                                $this->debbug(
                                                    '## Error. ' . $occurence . ' Updating Image info product->' .
                                                        print_r(
                                                            $e->getMessage(),
                                                            1
                                                        ),
                                                    'syncdata'
                                                );
                                            }
                                        }
                                        $variant_ids = array_unique($variant_ids);
                                        $variant_ids = json_encode($variant_ids);
                                        $this->debbug('Updating variant ids of image', 'syncdata');
                                        Db::getInstance()->execute(
                                            'UPDATE ' . _DB_PREFIX_ . "slyr_image SET ps_variant_id ='" .
                                                $variant_ids .
                                                "' WHERE id_image = '" . $image_cover->id . "' "
                                        );

                                        $image_ids[] = $image_cover->id;

                                        //  }
                                        if (file_exists($temp_image)) {
                                            unlink($temp_image);
                                        }
                                        unset($image_cover);
                                        $this->debbug('Before continue to level 2', 'syncdata');

                                        if ($cached) {
                                            $this->debbug('send delete form cache preload->'.
                                                          print_r($cached, 1), 'syncdata');
                                            SalesLayerImport::deletePreloadImageByCacheData($cached);
                                        } else {
                                            $this->debbug('send delete form cache preload->' .
                                                          print_r($url, 1), 'syncdata');
                                            SalesLayerImport::deletePreloadImage($url, 'product_format', $sl_id);
                                        }

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
                            $variant_ids = array((string) $variant_id);
                            $image->id_product = (int)$product_id;
                            $image->position = $position_image;// Image::getHighestPosition($product_id) + 1
                            $position_image++;

                            foreach ($this->shop_languages as $shop_language) {
                                if (!isset($image->legend[$shop_language['id_lang']])) {   // if is empty
                                    $name_of_product_save = '';
                                    if (is_array($product_name[$shop_language['id_lang']])) {
                                        $index_alt = $counter_images;

                                        if (isset($product_name[$shop_language['id_lang']][$index_alt])) {
                                            $name_of_product_save =
                                                $product_name[$shop_language['id_lang']][$index_alt];
                                        } else {
                                            $name_of_product_save =
                                                reset($product_name[$shop_language['id_lang']]) .
                                                ' (' . $counter_images . ')';
                                        }
                                    }
                                    if ($name_of_product_save != ''
                                        && (!isset($image->legend[$shop_language['id_lang']])
                                            && (empty($image->legend[$shop_language['id_lang']])
                                                || trim(
                                                    $image->legend[$shop_language['id_lang']]
                                                ) != trim($name_of_product_save)))
                                    ) {
                                        $image->legend[$shop_language['id_lang']] =
                                            $name_of_product_save;
                                    //$this->debbug('Set image alt atribute  need update this image info ->' .
                                        // print_r($image->legend[$shop_language['id_lang']], 1) . '  !=  ' .
                                        // print_r($name_of_product_save, 1), 'syncdata');
                                    } else {
                                        $this->debbug(
                                            'The image is the same and the alt attribute of the image has not
                                            changed. so it is not necessary to update the information of this image. ->'
                                            .
                                            print_r(
                                                $image->legend[$shop_language['id_lang']],
                                                1
                                            ) . '  ==  ' . print_r($name_of_product_save, 1),
                                            'syncdata'
                                        );
                                    }
                                }
                            }

                            if (count($ps_images) == 0 && $cover) {// is first image of this product
                                try {
                                    Image::deleteCover($product_id); // delete cover image from this product
                                } catch (Exception $e) {
                                    $this->general_error = true;
                                    $this->debbug(
                                        '## Error. ' . $occurence . ' Deleting cover ->' . print_r(
                                            $e->getMessage(),
                                            1
                                        ),
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
                                    '## Error. ' . $occurence . ' Validating fields of image in Variant ->' . print_r(
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
                                    '## Error. ' . $occurence . ' Validating language fields of
                                    image in Variant ->' . print_r(
                                        $e->getMessage(),
                                        1
                                    ) . ' url->' . $url,
                                    'syncdata'
                                );
                            }
                            $counter_images++;
                            try {
                                $result_save_image = $image->add();
                            } catch (Exception $e) {
                                $result_save_image = false;
                                $this->debbug(
                                    '## Error. ' . $occurence . ' Problem saving image variant ->' . print_r(
                                        $e->getMessage(),
                                        1
                                    ) . ' url->' . $url,
                                    'syncdata'
                                );
                            }

                            if ($result_save_image != true) {
                                $this->debbug(
                                    '## Warning. Problem when creating image template for image. ' .
                                     'Prestashop may have broken the table of images, we will try to repair it.',
                                    'syncdata'
                                );
                                try {
                                    $this->repairImageStructureOfProduct($product_id);
                                } catch (Exception $e) {
                                    $this->debbug(
                                        '## Error. ' . $occurence . ' In repairing structure of
                                        images ' . $e->getMessage(),
                                        'syncdata'
                                    );
                                }

                                $result_save_image = $image->add();
                            }


                            if ($validate_fields === true && $validate_language === true && $result_save_image) {
                                if (!$this->copyImg($product_id, $image->id, $temp_image, 'products', true, true)) {
                                    $this->debbug(
                                        '## Error. in copy file, send image to delete id_image->' . $image->id,
                                        'syncdata'
                                    );
                                    $image->delete();
                                } else {
                                    $image->associateTo($all_shops_image);
                                    $variant_ids = json_encode($variant_ids);

                                    /**
                                     * INSERT INTO SL CACHE TABLE IMAGE WITH MD5, NAME OF FILE , ID
                                     */

                                    Db::getInstance()->execute(
                                        'INSERT INTO ' . _DB_PREFIX_ . "slyr_image
                                        (image_reference, id_image, md5_image, ps_product_id, ps_variant_id)
                                        VALUES ('" . $image_reference . "', " . $image->id . ", '" .
                                        $md5_image . "','" . $product_id . "','" . $variant_ids . "')
                                        ON DUPLICATE KEY UPDATE id_image = '" . $image->id .
                                        "', md5_image = '" . $md5_image . "'"
                                    );

                                    $image_ids[] = $image->id;
                                }
                            } else {
                                $this->general_error = true;
                                unlink($temp_image);
                                $this->debbug('## Warning. Image of Variant not accepted as Valid ', 'syncdata');
                            }
                        } catch (Exception $e) {
                            $this->general_error = true;
                            $this->debbug(
                                '## Error. ' . $occurence . ' Error in creating new format
                                image problem found->' . print_r(
                                    $e->getMessage() . ' line->' .
                                    print_r($e->getLine(), 1) .
                                    ' track->' . print_r($e->getTrace(), 1),
                                    1
                                ),
                                'syncdata'
                            );
                        }
                        unset($image);
                    }
                    if (file_exists($temp_image)) {
                        unlink($temp_image);
                    }

                    if ($cached) {
                        $this->debbug('send delete form cache preload ->' .
                                      print_r($cached, 1), 'syncdata');
                        SalesLayerImport::deletePreloadImageByCacheData($cached);
                    } else {
                        $this->debbug('send delete form cache preload url->' .
                                      print_r($url, 1), 'syncdata');
                        SalesLayerImport::deletePreloadImage($url, 'product_format', $sl_id);
                    }
                }
                $this->debbug('END processing this image. Timing ->' . ($time_ini_image - microtime(1)), 'syncdata');
            }
            unset($image);
        }

        // el formato ya no tiene imagenes debemos eliminar si tiene en prestashop asignada alguna imagen
        $this->debbug(
            'We will check if any of the images have been imported in the past with this variant',
            'syncdata'
        );
        $slyr_images = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . "slyr_image im
                WHERE  im.ps_product_id = '" . $product_id . "' "
        );

        if (!empty($slyr_images)) {
            foreach ($slyr_images as $keySLImg => $slyr_image) {
                $this->debbug('Testing if it is needed to delete this image ' .
                        print_r($slyr_image, 1) . ' variants ids ->' .
                        print_r($slyr_image['ps_variant_id'], 1) . 'id_image->' .
                        print_r($slyr_image['id_image'], 1), 'syncdata');

                $variant_ids = array();
                if ($slyr_image['ps_variant_id'] != null) {
                    $variant_ids_ch = json_decode((string) $slyr_image['ps_variant_id'], 1);
                    $variant_ids = (is_array($variant_ids_ch) ? $variant_ids_ch : []);
                }

                if (in_array((string) $variant_id, $variant_ids, false)) {
                    $this->debbug(
                        'Image->' . $slyr_image['id_image'] .
                        ' Id of this variant ' . $variant_id . ' is in variants array-> ' . print_r(
                            $variant_ids,
                            1
                        ) . ' protected ids ->' . print_r($image_ids, 1),
                        'syncdata'
                    );
                    /**
                     * Verify if is needed delete this file
                     */

                    $new_array = array();
                    foreach ($variant_ids as $variant_id_in_search) {
                        if ($variant_id_in_search != $variant_id ||
                            in_array($slyr_image['id_image'], $image_ids, false)
                        ) {
                            $new_array[] = (string) $variant_id_in_search;
                        }
                    }
                    $this->debbug(
                        'Image->' . $slyr_image['id_image'] .
                        ' Variant ids after filter ' . print_r(
                            $new_array,
                            1
                        ) . ' protected ids ->' . print_r($image_ids, 1),
                        'syncdata'
                    );
                    $variant_ids = $new_array;
                    if (empty($variant_ids)) {
                        // this variant  is unique variant in use this file
                        $this->debbug(
                            'Image->' . $slyr_image['id_image'] .
                            ' Array is empty how to a send this image to delete, ' .
                              'but before test if is upload from product or variant  -> ' .
                                          print_r($variant_ids, 1),
                            'syncdata'
                        );
                        if (($slyr_image['origin'] == 'frmt' || empty($slyr_image['origin'])) &&
                            !in_array($slyr_image['id_image'], $image_ids, false)
                        ) {
                            //if origin if the image is this variant delete it  from product
                            $this->debbug('Image->' . $slyr_image['id_image'] .
                                          ' Deleting image because the image has been ' .
                                 'sent from this format and now no longer has this format no photo', 'syncdata');
                            $image_delete = new Image($slyr_image['id_image']);
                            $image_delete->delete();
                            Db::getInstance()->execute(
                                'DELETE FROM ' . _DB_PREFIX_ . "slyr_image
                                    WHERE id_image = '" . $slyr_image['id_image'] . "' "
                            );
                            unset($image_delete);
                        }
                    }

                    $variant_ids = json_encode($variant_ids);
                    $this->debbug(
                        'Image->' . $slyr_image['id_image'] .
                        'Before save variants ids array-> ' . print_r(
                            $variant_ids,
                            1
                        ) . ' protected ids ->' . print_r($image_ids, 1),
                        'syncdata'
                    );
                    Db::getInstance()->execute(
                        'UPDATE ' . _DB_PREFIX_ . "slyr_image SET ps_variant_id ='" . $variant_ids
                            . "' WHERE id_image = '" . $slyr_image['id_image'] . "' "
                    );
                }
            }
        }
        /**
         * Delete unknown images from this variant
         */
        $query_select = 'SELECT im.id_image FROM ' . _DB_PREFIX_ . "image im
	             INNER JOIN " . _DB_PREFIX_ . "product_attribute_image pai ON (im.id_image = pai.id_image)
	             LEFT JOIN " . _DB_PREFIX_ . "slyr_image si ON (im.id_image = si.id_image )
                WHERE  im.id_product = '" . $product_id . "'                
                AND pai.id_product_attribute = '" . $variant_id . "' ";
        $ps_variant_images = Db::getInstance()->executeS($query_select);

        $this->debbug('Delete unknown images query->' . print_r($query_select, 1) .
                      ' result ->' . print_r($ps_variant_images, 1), 'syncdata');

        if (!empty($ps_variant_images)) {
            foreach ($ps_variant_images as $image_variant) {
                $image_delete = new Image($image_variant['id_image']);
                $image_delete->delete();
            }
        }

        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);

        return $image_ids;
    }
}

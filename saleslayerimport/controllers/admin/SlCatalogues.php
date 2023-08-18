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

class SlCatalogues extends SalesLayerPimUpdate
{
    private $general_error = false;
    public $categories_collection;


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Load category image schema
     * @param $catalogue_schema
     */

    public function loadCategoryImageSchema(
        $catalogue_schema
    ) {
        /**
         * Load schema of category
         */
        if (empty($this->category_images_sizes)) {
            if (!empty($catalogue_schema['fields']['section_image']['image_sizes'])) {
                $category_field_images_sizes = $catalogue_schema['fields']['section_image']['image_sizes'];
                $ordered_image_sizes = $this->orderArrayImg($category_field_images_sizes);
                foreach (array_keys($ordered_image_sizes) as $img_size) {
                    $this->category_images_sizes[] = $img_size;
                }
                unset($category_field_images_sizes, $ordered_image_sizes);
            } else {
                if (!empty($catalogue_schema['fields']['image_sizes'])) {
                    $category_field_images_sizes = $catalogue_schema['fields']['image_sizes'];
                    $ordered_image_sizes = $this->orderArrayImg($category_field_images_sizes);
                    foreach (array_keys($ordered_image_sizes) as $img_size) {
                        $this->category_images_sizes[] = $img_size;
                    }
                    unset($category_field_images_sizes, $ordered_image_sizes);
                } else {
                    $this->category_images_sizes[] = array('ORG', 'IMD', 'THM', 'TH');
                }
            }
        }
        // $this->debbug('Load: category_images_sizes '.print_r($this->category_images_sizes,1),'syncdata');
    }

    /**
     * Synchronize category information
     * @param $catalog
     * @param $schema
     * @param $connector_id
     * @param $comp_id
     * @param $currentLanguage
     * @param $shops
     * @param $defaultCategory
     * @return bool|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    public function syncOneCategory(
        $catalog,
        $schema,
        $connector_id,
        $comp_id,
        $currentLanguage,
        $shops,
        $defaultCategory
    ) {
        $syncCat = true;
        $occurence = '';
        $this->debbug(
            ' >>>>>>>>>>>>>>>>>>>>> Start Category ->' . ($catalog['data']['section_reference'] ??
                                                          $catalog['ID_PARENT']) .
            ' time->' . date("H:i:s") . ' micro-time->' . microtime(true) . ' <<<<<<<<<<<<<<<<<<<<<<<<<<<',
            'syncdata'
        );
        $this->debbug(
            'Entry to synchronize->' . print_r($catalog, 1) . ' Connector id-> ' . print_r(
                $connector_id,
                1
            ) . '  $shops ->' . print_r($shops, 1) . '  $defaultCategory -> ' . print_r(
                $defaultCategory,
                1
            ) . ' comp_id ->' . print_r($comp_id, 1),
            'syncdata'
        );
        if (empty($connector_id) ||
            empty($comp_id) ||
            empty($currentLanguage) ||
            empty($shops) ||
            empty($defaultCategory)
        ) {
            $this->debbug('## Error. Some data has not been completed correctly ', 'syncdata');

            return 'item_not_updated';
        }
        $data_clear = [];
        $data_clear['ID_PARENT'] = $catalog['ID_PARENT'];
        $data_clear['data'] = $catalog['data'];

        $data_clear = json_encode($data_clear);
        $data_hash = (string) hash($this->hash_algorithm_comparator, $data_clear);

        $catalog_exists = (int)Db::getInstance()->getValue(
            sprintf(
                'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                 WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                $catalog['ID'],
                $comp_id
            )
        );
        /**
         * Test of duplicates in cache
         */
        if ($catalog_exists) {
            $catalog_exists_duplicates = Db::getInstance()->executeS(
                sprintf(
                    'SELECT * FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                 WHERE sl.ps_id = "%s" 
                 AND sl.comp_id = "%s" 
                 AND sl.ps_type = "slCatalogue" 
                 AND sl.slyr_id != "%s"',
                    $catalog_exists,
                    $comp_id,
                    $catalog['ID']
                )
            );
            if (!empty($catalog_exists_duplicates)) { // delete duplicates
                foreach ($catalog_exists_duplicates as $duplicate) {
                    Db::getInstance()->execute(
                        sprintf(
                            'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product
		                       WHERE slyr_id = "%s"
		                       AND comp_id = "%s" 
		                       AND ps_id = "%s" 
		                       AND ps_type = "slCatalogue"',
                            $duplicate['slyr_id'],
                            $duplicate['comp_id'],
                            $duplicate['ps_id']
                        )
                    );
                }
            }
        }





        if (!$catalog_exists) {
            /**
             * Category record not found in the table of SL
             */
            $found = false;

            if (isset($catalog['data']['section_reference']) && $catalog['data']['section_reference'] != '') {
                $section_reference = $catalog['data']['section_reference'];
                $search_first = '';
                $registros = array();
                if (is_numeric($section_reference)) { // buscar posible id de categoría de exportación
                    $this->debbug(
                        'Reference is numeric check if is a id_of category',
                        'syncdata'
                    );

                    $searchid_category = array();
                    $test_id = (int) $section_reference;
                    $name_multi_idioma = array();
                    foreach ($this->shop_languages as $lang) {
                        $section_name_index = 'section_name_' . $lang['iso_code'];
                        if (isset($catalog['data'][$section_name_index]) &&
                            !empty($catalog['data'][$section_name_index])
                        ) { // is multilenguage
                            $this->debbug(
                                'Set name from name ->' .
                                print_r($section_name_index, 1) . ' value->' .
                                print_r($catalog['data'][$section_name_index], 1),
                                'syncdata'
                            );
                            $name_multi_idioma[$lang['id_lang']] = $catalog['data'][$section_name_index];
                        }
                    }
                    if (!count($name_multi_idioma)) { // is not a multi language
                        if (isset($catalog['data']['section_name']) &&
                            !empty($catalog['data']['section_name']) &&
                             !isset($schema['section_name']['language_code'])
                        ) {
                            $name_multi_idioma[$currentLanguage] = $catalog['data']['section_name'];
                        }
                    }
                    if ($test_id > 0 && count($name_multi_idioma)) {
                        $this->debbug(
                            'Go search by id , name, id_lang',
                            'syncdata'
                        );

                        foreach ($name_multi_idioma as $id_lang => $cat_name) {
                            $searchid_category[] = '(id_category = "' . $test_id . '" AND name="' . $cat_name .
                                                   '" AND id_lang = "' . $id_lang . '" ) ';
                        }
                        $search_first = '(' . implode(' OR ', $searchid_category) . ')  ';

                        $schemaCats_name = 'SELECT id_category FROM ' . $this->category_lang_table .
                                      ' WHERE ' . $search_first .
                                      ' GROUP BY id_category, id_lang, name ';
                        $registros = Db::getInstance()->executeS($schemaCats_name);
                    }
                }


                if (!count($registros)) { // not found any categories with the same id and name for languages
                    $this->debbug(
                        'The category id and name has not been recognized, go search by Keyword',
                        'syncdata'
                    );
                    /**
                     * Check Meta keywords as references
                     */
                    $check_kewordreferencess = array();
                    foreach ($this->shop_languages as $lang) {
                        $check_kewordreferencess[] = '( id_lang = "' . $lang['id_lang'] .
                                                     '"  AND  meta_keywords LIKE "%' . $section_reference . '%" )';
                    }

                    $schemaCats = 'SELECT id_category FROM ' . $this->category_lang_table .
                                  ' WHERE  ' . implode(' OR ', $check_kewordreferencess) .
                                  ' GROUP BY id_category, id_lang, name ';
                    $registros = Db::getInstance()->executeS($schemaCats);
                }



                if (count($registros) > 0) {
                    $clearedids = array();
                    foreach ($registros as $id_categories) {
                        $clearedids[] = $id_categories['id_category'];
                    }
                    $this->debbug(
                        'Categories returned as possible categories ->' .
                        print_r($clearedids, 1) . ' Query->' . print_r($schemaCats, 1),
                        'syncdata'
                    );
                    $register_order = array_count_values($clearedids);
                    $this->debbug(
                        'Counted and sorted by most likely from highest to lowest ->' .
                        print_r($register_order, 1),
                        'syncdata'
                    );
                    foreach (array_keys($register_order) as $id_category) {
                        $this->debbug(
                            'Most probably category for check ->' .
                            print_r($id_category, 1),
                            'syncdata'
                        );
                        $query_data_cat = 'SELECT id_category,id_lang,name,meta_keywords FROM ' .
                                          $this->category_lang_table .
                                          ' WHERE  id_category = "' . $id_category . '"  ' .
                                          ' GROUP BY id_category, id_lang, name ';
                        $registro = Db::getInstance()->getRow($query_data_cat);

                        $this->debbug(
                            'Register by most hits recognition ->' .
                            print_r($registro, 1),
                            'syncdata'
                        );
                        // buscar categoria con registros por meta_keywords y la misma referencia y id idioma
                        $cat_meta_keywords = $registro['meta_keywords'];
                        $check_category_id = '';

                        if ($cat_meta_keywords != '') {
                            $check_category_id = $registro['id_category'];
                        }

                        if ($check_category_id != '') {
                            $catalog_exists = (int) Db::getInstance()->getValue(
                                sprintf(
                                    'SELECT sl.slyr_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                                     WHERE sl.ps_id = "%s" AND sl.ps_type = "slCatalogue"',
                                    $check_category_id
                                )
                            );

                            if ($catalog_exists) {
                                //categoria  ya tiene un registro en nuestra base de datos pero se sigue

                                /**
                                 * Category we already have it but it can be linked to another
                                 * category so it will not be linked with this
                                 * Categoria  ya la tenemos pero  puede estar vinculada a otra categoria
                                 * asi que no se va a vincular con esta
                                 */
                                $this->debbug(
                                    'Category we already have it but it can be linked ' .
                                    ' to another vsaegory from SL->' .
                                    print_r($catalog_exists, 1)   .
                                    ' comp_id ->' . print_r($comp_id, 1),
                                    'syncdata'
                                );

                                continue;
                            } else {
                                //categoria no existe en nuestra tabla agregarla  y hacer vinculo SL con PS
                                // category does not exist in our table add it and make link SL with PS

                                $found = true;

                                $this->debbug(
                                    'Category found create association to this category->' .
                                    print_r($catalog_exists, 1)   .
                                    ' comp_id ->' . print_r($comp_id, 1),
                                    'syncdata'
                                );


                                Db::getInstance()->execute(
                                    sprintf(
                                        'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                                         (ps_id, slyr_id, ps_type, comp_id, date_add)
                                          VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                        $registro['id_category'],
                                        $catalog['ID'],
                                        'slCatalogue',
                                        $comp_id
                                    )
                                );

                                break;
                            }
                        }
                    }
                }
            }

            if (!$found) {
                /**
                 * Categoría no Instalada recientemente buscar por nombre de Categoría en idioma adecuada
                 * Category not Installed recently search by Category name in appropriate language
                 */
                $this->debbug(
                    'Category not found. Search by Category name in appropriate language->' .
                    print_r($catalog_exists, 1)   .
                    ' comp_id ->' . print_r($comp_id, 1),
                    'syncdata'
                );


                foreach ($this->shop_languages as $lang) {
                    if (isset($catalog['data']['section_name']) &&
                        !empty($catalog['data']['section_name']) &&
                        !isset($schema['section_name']['language_code'])
                    ) {
                        $section_name_index = 'section_name';
                    } else { // is multilenguage
                        $section_name_index = 'section_name_' . $lang['iso_code'];
                        if (!isset($catalog['data'][$section_name_index]) &&
                            empty($catalog['data'][$section_name_index])
                        ) {
                            // no hay registro en ese idioma vamos a saltar la búsqueda en esta idioma
                            continue; //there is no record in that language we will skip the search in this language
                        }
                    }
                    $catalog_name = $this->slValidateCatalogName(
                        $catalog['data'][$section_name_index],
                        'Catalog'
                    );

                    // $catalogObject = new Category();

                    // $regsName = $catalogObject->searchByName($currentLanguage, $catalog_name);
                    $regsName = Category::searchByName($lang['id_lang'], $catalog_name);

                    if ($regsName && count($regsName) > 0) { // categorias encontradas
                        $category_id = false;
                        //Buscamos categoría con nombre idéntico, si no la encontramos nos quedamos con la primera
                        foreach ($regsName as $keyName => $regName) {
                            if ($regName['name'] == $catalog_name) {
                                // categoria con el mismo nombre

                                $catalog_exists = (int) Db::getInstance()->getValue(
                                    sprintf(
                                        'SELECT sl.slyr_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                                         WHERE sl.ps_id = "%s" AND sl.ps_type = "slCatalogue"',
                                        $regName['id_category']
                                    )
                                );

                                if ($catalog_exists) {
                                    /**
                                     * Categoria  ya la tenemos pero  puede estar vinculada a otra categoria
                                     * se va búscar otra disponible
                                     * Category we already have it but it can be linked to another category
                                     * you are looking for another available
                                     */

                                    $this->debbug(
                                        'Category we already have it but it can be linked' .
                                        ' to another category from SL->' .
                                        print_r($catalog_exists, 1)   .
                                        ' comp_id ->' . print_r($comp_id, 1),
                                        'syncdata'
                                    );

                                    continue;
                                } else {
                                    $this->debbug(
                                        'The category selected because it is not yet assigned to any ' .
                                        'other with the same name is not linked to another category id->' .
                                        print_r($regsName[$keyName]['id_category'], 1)   .
                                        ' comp_id ->' . print_r($comp_id, 1),
                                        'syncdata'
                                    );


                                    $category_id = $regsName[$keyName]['id_category'];
                                    break;
                                }
                            }
                        }

                        if ($category_id) {
                            //Si encontramos la categoría insertamos registro en tabla
                            // Slyr y creamos vinculo con esta categoria
                            //If we find the category we insert a record in the Slyr
                            // table and we create a link with this category
                            $this->debbug(
                                'Inserted association before create link->' .
                                print_r($catalog, 1),
                                'syncdata'
                            );
                            Db::getInstance()->execute(
                                sprintf(
                                    'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                                    (ps_id, slyr_id, ps_type, comp_id, date_add)
                                     VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                                    $category_id,
                                    $catalog['ID'],
                                    'slCatalogue',
                                    $comp_id
                                )
                            );

                            $this->debbug(
                                'Inserted association of the category with the cache->' .
                                print_r($category_id, 1)   .
                                ' comp_id ->' . print_r($comp_id, 1),
                                'syncdata'
                            );

                            $found = true;
                            break;
                        }
                    }
                }
            }

            if (!$found) {
                $this->debbug(
                    'Category has not been found is going to create ->' .
                    ' comp_id ->' . print_r($comp_id, 1),
                    'syncdata'
                );


                /**
                 * Category cannot be found, creating new category for all shops
                 */
                $contextShopID = Shop::getContextShopID();
                //Shop::setContext(Shop::CONTEXT_ALL);
                $id_shop = (is_array($shops) ? reset($shops) : $contextShopID);
                Shop::setContext(Shop::CONTEXT_SHOP, $id_shop);
                $cat = new Category(null, null, $id_shop);

                $cat->name                = array();
                $cat->link_rewrite        = array();
                $cat->meta_title          = array();
                $cat->meta_description    = array();
                $cat->active              = 0;
                $cat->id_parent           = $defaultCategory;
                $cat->id_category_default = $defaultCategory;

                $section_reference = '';

                if (isset($section_reference) && ! empty($section_reference)) {
                    $occurence = ' section reference :' . $section_reference;
                } elseif (isset($catalog_name) && ! empty($catalog_name)) {
                    $occurence = ' category name :' . $catalog_name;
                } else {
                    $occurence = ' ID :' . $catalog['ID'];
                }

                if (isset($catalog['data']['section_reference']) && $catalog['data']['section_reference'] != '') {
                    //Reload the category because after save Prestashop generates the Meta arrays.
                    // $cat = new Category($cat->id);

                    $section_reference = $catalog['data']['section_reference'];
                }




                foreach ($this->shop_languages as $lang) {

                    /**
                     * Set multi-language  name and description...
                     */


                    $catalog_name              = '';
                    $section_name_index        = '';
                    $section_name_index_search = 'section_name_' . $lang['iso_code'];

                    if (isset(
                        $catalog['data'][ $section_name_index_search ],
                        $schema[ $section_name_index_search ]['language_code']
                    ) &&
                         ! empty($catalog['data'][ $section_name_index_search ]) &&
                         $schema[ $section_name_index_search ]['language_code'] == $lang['iso_code']
                    ) {
                        $section_name_index = 'section_name_' . $lang['iso_code'];
                    } elseif (isset($catalog['data']['section_name']) &&
                               ! empty($catalog['data']['section_name']) &&
                               ! isset($schema['section_name']['language_code'])
                    ) {
                        $section_name_index = 'section_name';
                    }

                    if (isset($catalog['data'][ $section_name_index ]) &&
                        ! empty($catalog['data'][ $section_name_index ])
                    ) {
                        $catalog_name = $this->slValidateCatalogName(
                            html_entity_decode($catalog['data'][ $section_name_index ]),
                            'Catalog'
                        );
                        $this->debbug(
                            'Assigning category name of ' . print_r($section_name_index, 1)
                            . ' value->' . print_r(
                                $catalog['data'][ $section_name_index ],
                                1
                            )
                        );

                        (isset($catalog['data']['friendly_url']) &&
                          $catalog['data']['friendly_url'] != '') ?
                            $friendly_url = $catalog['data']['friendly_url'] :
                            $friendly_url = $catalog_name;
                        $cat->name[ $lang['id_lang'] ] = $catalog_name;

                        $cat->link_rewrite[ $lang['id_lang'] ] = Tools::link_rewrite($friendly_url);
                    }

                    /**
                     * Set Description
                     */
                    /*  $section_description = '';
                      $section_description_index = '';
                      $section_description_index_search = 'section_description_' . $lang['iso_code'];

                      if (isset(
                          $catalog['data'][$section_description_index_search],
                          $schema[$section_description_index_search]['language_code']
                      ) &&
                          !empty($catalog['data'][$section_description_index_search]) &&
                          $schema[$section_description_index_search]['language_code'] == $lang['iso_code']) {
                          $section_description_index = 'section_description_' . $lang['iso_code'];
                      } elseif (isset($catalog['data']['section_description']) &&
                          !empty($catalog['data']['section_description']) &&
                          !isset($schema['section_description']['language_code'])) {
                          $section_description_index = 'section_description';
                      }

                      if (isset($catalog['data'][$section_description_index]) &&
                          !empty($catalog['data'][$section_description_index])) {
                          $section_description = html_entity_decode($catalog['data'][$section_description_index]);
                          $cat->description[$lang['id_lang']] = $section_description;
                          $this->debbug(
                              $occurence  . 'Assigning section_description category of ' . print_r(
                                  $section_description_index,
                                  1
                              ) . ' value->' . print_r($catalog['data'][$section_description_index], 1)
                          );
                      }*/

                    /**
                     * reference
                     */
                    if ($section_reference != '') {
                        $cat->meta_keywords[$lang['id_lang']] = $section_reference;
                    }

                    /**
                     * Meta title
                     */
                    /*  $meta_title = '';
                      $meta_title_index = '';
                      $meta_title_index_search = 'meta_title_' . $lang['iso_code'];

                      if (isset(
                          $catalog['data'][$meta_title_index_search],
                          $schema[$meta_title_index_search]['language_code']
                      ) &&
                          !empty($catalog['data'][$meta_title_index_search]) &&
                          $schema[$meta_title_index_search]['language_code'] == $lang['iso_code']) {
                          $meta_title_index = 'meta_title_' . $lang['iso_code'];
                      } elseif (isset($catalog['data']['meta_title']) &&
                          !empty($catalog['data']['meta_title']) &&
                          !isset($schema['section_name']['meta_title'])) {
                          $meta_title_index = 'meta_title';
                      }

                      if (isset($catalog['data'][$meta_title_index]) && $catalog['data'][$meta_title_index] != '') {
                          $meta_title = html_entity_decode($catalog['data'][$meta_title_index]);
                      } else {
                          if (isset($catalog['data'][$section_name_index])) {
                              $meta_title = $this->clearForMetaData($catalog['data'][$section_name_index]);
                          }
                      }
                      if ($meta_title != '') {
                          if (Tools::strlen($meta_title) > 249) {
                    */
                    /* $this->debbug('## Warning. ' . $occurence . ' Meta title has been cut->' .
                                   print_r(Tools::strlen($meta_title), 1), 'syncdata');*/
                    /*   $meta_title = Tools::substr($meta_title, 0, 249);
                        }
                        $cat->meta_title[$lang['id_lang']] = $meta_title;
                    }*/

                    /**
                     * Meta description
                     */
                    /*   $meta_description = '';
                       $meta_description_index = '';
                       $meta_description_index_search = 'meta_description_' . $lang['iso_code'];

                       if (isset(
                           $catalog['data'][$meta_description_index_search],
                           $schema[$meta_description_index_search]['language_code']
                       ) &&
                           !empty($catalog['data'][$meta_description_index_search]) &&
                           $schema[$meta_description_index_search]['language_code'] == $lang['iso_code']) {
                           $meta_description_index = 'meta_description_' . $lang['iso_code'];
                       } elseif (isset($catalog['data']['meta_description']) &&
                           !empty($catalog['data']['meta_description']) &&
                           !isset($schema['meta_description']['meta_title'])) {
                           $meta_description_index = 'meta_description';
                       }

                       if (isset($catalog['data'][$meta_description_index]) &&
                           $catalog['data'][$meta_description_index] != '') {
                           $meta_description = html_entity_decode($catalog['data'][$meta_description_index]);
                       } else {
                           if (isset($catalog['data'][$section_description_index])) {
                               $meta_description =
                    $this->clearForMetaData($catalog['data'][$section_description_index]);
                           }
                       }

                       if ($meta_description != '') {
                           if (Tools::strlen($meta_description) > 249) {*/
                    /*  $this->debbug('## Warning. ' . $occurence .
                                    ' Meta description has been cut->' .
                                    print_r(Tools::strlen($meta_description), 1), 'syncdata');*/
                    /*  $meta_description = Tools::substr($meta_description, 0, 249);
                        }
                        $cat->meta_description[$lang['id_lang']] = $meta_description;
                    }*/


                    /**
                     * Set Frindly url
                     */
                    $friendly_url              = '';
                    $friendly_url_index        = '';
                    $friendly_url_index_search = 'friendly_url_' . $lang['iso_code'];

                    if (isset(
                        $catalog['data'][ $friendly_url_index_search ],
                        $schema[ $friendly_url_index_search ]['language_code']
                    ) &&
                         ! empty($catalog['data'][ $friendly_url_index_search ]) &&
                         $schema[ $friendly_url_index_search ]['language_code'] == $lang['iso_code']
                    ) {
                        $friendly_url_index = 'friendly_url_' . $lang['iso_code'];
                    } elseif (isset($catalog['data']['friendly_url']) &&
                               ! empty($catalog['data']['friendly_url']) &&
                               ! isset($schema['friendly_url']['language_code'])
                    ) {
                        $friendly_url_index = 'friendly_url';
                    }

                    if (isset($catalog['data'][ $friendly_url_index ]) &&
                        $catalog['data'][ $friendly_url_index ] != ''
                    ) {
                        $friendly_url = $catalog['data'][ $friendly_url_index ];
                    } else {
                        if (isset($catalog['data'][ $section_name_index ])) {
                            $friendly_url = $catalog['data'][ $section_name_index ];
                        }
                    }

                    if ($friendly_url != '') {
                        $friendly_url                          = Tools::link_rewrite($friendly_url);
                        $cat->link_rewrite[ $lang['id_lang'] ] = $friendly_url;
                    }

                    /**
                     * Set default values if is a null
                     */

                    if ($lang['id_lang'] != $this->defaultLanguage) {
                        if ($catalog_name != '' && (! isset($cat->name[ $this->defaultLanguage ]) ||
                                                      ($cat->name[ $this->defaultLanguage ] == null ||
                                                        $cat->name[ $this->defaultLanguage ] == ''))
                        ) {
                            $cat->name[ $this->defaultLanguage ] = $catalog_name;
                        }

                        /*  if ($section_description != '' && (!isset($cat->description[$this->defaultLanguage]) ||
                                  ($cat->description[$this->defaultLanguage] == null ||
                                      $cat->description[$this->defaultLanguage] == ''))) {
                              $cat->description[$this->defaultLanguage] = $section_description;
                          }
                          if ($meta_title != '' && (!isset($cat->meta_title[$this->defaultLanguage]) ||
                                  ($cat->meta_title[$this->defaultLanguage] == null ||
                                      $cat->meta_title[$this->defaultLanguage] == ''))) {
                              $cat->meta_title[$this->defaultLanguage] = $meta_title;
                          }
                         if ($meta_description != '' && (!isset($cat->meta_description[$this->defaultLanguage]) ||
                                  ($cat->meta_description[$this->defaultLanguage] == null ||
                                      $cat->meta_description[$this->defaultLanguage] == ''))) {
                              $cat->meta_description[$this->defaultLanguage] = $meta_description;
                          }*/
                        if ($friendly_url != '' && (! isset($cat->link_rewrite[ $this->defaultLanguage ]) ||
                                                      ($cat->link_rewrite[ $this->defaultLanguage ] == null ||
                                                        $cat->link_rewrite[ $this->defaultLanguage ] == ''))
                        ) {
                            $cat->link_rewrite[ $this->defaultLanguage ] = $friendly_url;
                        }
                    }
                }


                try {
                    $cat->save();
                    $this->debbug(
                        'Category stat category id->' . print_r($cat->id, 1) . ' id_category-> ' . print_r(
                            $cat->id_category,
                            1
                        ) . ' id_parent ' . $cat->id_parent,
                        'syncdata'
                    );
                } catch (Exception $e) {
                    /**
                     * An error occurred
                     */
	                $this->general_error = true;
                    $syncCat = true;
                    $this->debbug(
                        '## Error. ' . $occurence .
                        ' Creating category  ID:' . $catalog['ID'] . ' ' . print_r($e->getMessage(), 1),
                        'syncdata'
                    );
                }

                if ($cat->id) {
                    Db::getInstance()->execute(
                        sprintf(
                            'INSERT INTO ' . _DB_PREFIX_ . 'slyr_category_product
                            (ps_id, slyr_id, ps_type, comp_id, date_add)
                             VALUES("%s", "%s", "%s", "%s", CURRENT_TIMESTAMP())',
                            $cat->id,
                            $catalog['ID'],
                            'slCatalogue',
                            $comp_id
                        )
                    );
                }

                Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
            }

            $catalog_exists = (int) Db::getInstance()->getValue(
                sprintf(
                    'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                     WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                    $catalog['ID'],
                    $comp_id
                )
            );

            if (!$catalog_exists) {
                unset($cat);
                return false;
            }
        }
        /**
         *
         * Update Existing Category
         *
         */
        $this->first_sync_shop = true;
        foreach ($shops as $shop_id) {
            $section_reference = '';
            Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);

            $cat = new Category($catalog_exists, null, $shop_id);
            $this->debbug(
                'Category stat category before update id->' . print_r($cat->id, 1) . ' id_category ' . print_r(
                    $cat->id_category,
                    1
                ) . ' id_parent ' . print_r($cat->id_parent, 1) . ' shop_id->' . print_r($shop_id, 1),
                'syncdata'
            );


            $catalog_parent_id = (int)Db::getInstance()->getValue(
                sprintf(
                    'SELECT sl.ps_id FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                    WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                    $catalog['ID_PARENT'],
                    $comp_id
                )
            );
            //detect root category by shop id
            $shop = new Shop($shop_id);
            $cat->id_parent = ($catalog['ID_PARENT'] == '0') ? ($shop->id_category ?? $defaultCategory) : $catalog_parent_id;
            // $cat->id_category_default = ($catalog['ID_PARENT'] == '0') ? $defaultCategory : $catalog_exists;
            $cat->id_category_default = ($catalog['ID_PARENT'] == '0') ? $defaultCategory : $catalog_parent_id;
            $this->debbug(
                'Catalogue id_category_default after from sl bd ->' . print_r($cat->id_category_default, 1),
                'syncdata'
            );

            if (isset($catalog['data']['section_reference']) && !empty($catalog['data']['section_reference'])) {
                $section_reference = trim($catalog['data']['section_reference']);
            }


            foreach ($this->shop_languages as $lang) {
                $this->debbug('check attributes in language -> ' . print_r($lang['iso_code'], 1), 'syncdata');
                /**
                 *
                 * Update names
                 *
                 */
                $meta_title = '';
                $catalog_name = '';
                $section_name_index = '';
                $section_name_index_search = 'section_name_' . $lang['iso_code'];

                if (isset($catalog['data'][$section_name_index_search]) &&
                    !empty($catalog['data'][$section_name_index_search]) &&
                    isset($schema[$section_name_index_search]['language_code']) &&
                    $schema[$section_name_index_search]['language_code'] == $lang['iso_code']
                ) {
                    $section_name_index = 'section_name_' . $lang['iso_code'];
                } elseif (isset($catalog['data']['section_name']) &&
                    !empty($catalog['data']['section_name']) &&
                    !isset($schema['section_name']['language_code'])
                ) {
                    $section_name_index = 'section_name';
                }

                if (isset($catalog['data'][$section_name_index]) && !empty($catalog['data'][$section_name_index])) {
                    $catalog_name = $this->slValidateCatalogName(
                        $catalog['data'][$section_name_index],
                        'Catalog'
                    );
                    if (!isset($cat->name[$lang['id_lang']]) || $catalog_name != $cat->name[$lang['id_lang']]) {
                        $cat->name[$lang['id_lang']] = html_entity_decode($catalog_name);
                    }

                    $this->debbug(
                        'Assign category name of ' . print_r($section_name_index, 1) . ' value-> ' . print_r(
                            $catalog['data'][$section_name_index],
                            1
                        ),
                        'syncdata'
                    );
                } else {
                    $this->debbug(
                        'Section name not found in category and language ' . $lang['iso_code'] . '  ',
                        'syncdata'
                    );
                }

                /**
                 * Update Description
                 */
                $section_description = '';
                $section_description_index = '';
                $section_description_index_search = 'section_description_' . $lang['iso_code'];

                if (isset(
                    $catalog['data'][$section_description_index_search],
                    $schema[$section_description_index_search]['language_code']
                ) &&
                    !empty($catalog['data'][$section_description_index_search]) &&
                    $schema[$section_description_index_search]['language_code'] == $lang['iso_code']
                ) {
                    $section_description_index = 'section_description_' . $lang['iso_code'];
                } elseif (isset($catalog['data']['section_description']) &&
                    !empty($catalog['data']['section_description']) &&
                    !isset($schema['section_description']['language_code'])
                ) {
                    $section_description_index = 'section_description';
                }

                if (isset($catalog['data'][$section_description_index]) &&
                    !empty($catalog['data'][$section_description_index])
                ) {
                    $section_description = html_entity_decode($catalog['data'][$section_description_index]);

                    if (!isset($cat->description[$lang['id_lang']]) ||
                        $cat->description[$lang['id_lang']] != $section_description
                    ) {
                        $cat->description[$lang['id_lang']] = $section_description;
                    }

                    $this->debbug(
                        'Assign section_description category of ' . print_r(
                            $section_description_index,
                            1
                        ) . ' value ->' . print_r($catalog['data'][$section_description_index], 1),
                        'syncdata'
                    );
                }

                /**
                 * Meta title
                 */
                $meta_title_index = '';
                $meta_title_index_search = 'meta_title_' . $lang['iso_code'];

                if (isset(
                    $catalog['data'][$meta_title_index_search],
                    $schema[$meta_title_index_search]['language_code']
                ) &&
                    !empty($catalog['data'][$meta_title_index_search]) &&
                    $schema[$meta_title_index_search]['language_code'] == $lang['iso_code']
                ) {
                    $meta_title_index = 'meta_title_' . $lang['iso_code'];
                } elseif (isset($catalog['data']['meta_title']) &&
                    !empty($catalog['data']['meta_title']) &&
                    !isset($schema['section_name']['meta_title'])
                ) {
                    $meta_title_index = 'meta_title';
                }

                if (isset($catalog['data'][$meta_title_index]) && $catalog['data'][$meta_title_index] != '') {
                    $meta_title = $catalog['data'][$meta_title_index];
                } else {
                    if (isset($catalog['data'][$section_name_index]) &&
                        !empty($catalog['data'][$section_name_index]) &&
                        isset($cat->meta_title[$lang['id_lang']]) &&
                        $cat->meta_title[$lang['id_lang']] == ''
                    ) {
                        $meta_title = $this->clearForMetaData($catalog['data'][$section_name_index]);
                    }
                }

                if ($meta_title != '') {
                    if (!isset($cat->meta_title[$lang['id_lang']]) ||
                        $cat->meta_title[$lang['id_lang']] != $meta_title
                    ) {
                        if (Tools::strlen($meta_title) > 249) {
                            /* $this->debbug('## Warning. ' . $occurence . ' Meta title has been cut->' .
                                           print_r(Tools::strlen($meta_title), 1), 'syncdata');*/
                            $meta_title = Tools::substr($meta_title, 0, 249);
                        }
                        $cat->meta_title[$lang['id_lang']] = strip_tags($meta_title);
                    }
                    // $need_update = true;
                }

                /**
                 * Meta description
                 */
                $meta_description = '';
                $meta_description_index = '';
                $meta_description_index_search = 'meta_description_' . $lang['iso_code'];

                if (isset(
                    $catalog['data'][$meta_description_index_search],
                    $schema[$meta_description_index_search]['language_code']
                ) &&
                    !empty($catalog['data'][$meta_description_index_search]) &&
                    $schema[$meta_description_index_search]['language_code'] == $lang['iso_code']
                ) {
                    $meta_description_index = 'meta_description_' . $lang['iso_code'];
                } elseif (isset($catalog['data']['meta_description']) &&
                    !empty($catalog['data']['meta_description']) &&
                    !isset($schema['meta_description']['meta_title'])
                ) {
                    $meta_description_index = 'meta_description';
                }

                if (isset($catalog['data'][$meta_description_index]) &&
                    $catalog['data'][$meta_description_index] != ''
                ) {
                    $meta_description = html_entity_decode($catalog['data'][$meta_description_index]);
                } else {
                    if ($section_description_index != '' && isset($catalog['data'][$section_description_index]) &&
                        $cat->meta_description[$lang['id_lang']] == ''
                    ) {
                        $meta_description = $this->clearForMetaData($catalog['data'][$section_description_index]);
                    }
                }

                if ($meta_description != '' && (!isset($cat->meta_description[$lang['id_lang']]) ||
                                                $cat->meta_description[$lang['id_lang']] != $meta_description)
                ) {
                    if (Tools::strlen($meta_description) > 255) {
                        /* $this->debbug('## Warning. ' . $occurence . ' Meta description has been cut->' .
                                       print_r(Tools::strlen($meta_description), 1), 'syncdata');*/

                        $meta_description = Tools::substr($meta_description, 0, 255);
                    }
                    $cat->meta_description[$lang['id_lang']] = $meta_description;
                    // $need_update = true;
                }

                /**
                 * Reference
                 */
                if ($section_reference != '') {
                    $cat->meta_keywords[$lang['id_lang']] = $section_reference;
                    $this->debbug(
                        'Updating reference->' . print_r($section_reference, 1),
                        'syncdata'
                    );
                } else {
                    $this->debbug(
                        'Category reference is empty->' . print_r($section_reference, 1),
                        'syncdata'
                    );
                }

                /**
                 * Update Frindly url
                 */

                $friendly_url_index = '';
                $friendly_url_index_search = 'friendly_url_' . $lang['iso_code'];

                if (isset(
                    $catalog['data'][$friendly_url_index_search],
                    $schema[$friendly_url_index_search]['language_code']
                ) &&
                    !empty($catalog['data'][$friendly_url_index_search]) &&
                    $schema[$friendly_url_index_search]['language_code'] == $lang['iso_code']
                ) {
                    $friendly_url_index = 'friendly_url_' . $lang['iso_code'];
                } elseif (isset($catalog['data']['friendly_url']) &&
                    !empty($catalog['data']['friendly_url']) &&
                    !isset($schema['friendly_url']['language_code'])
                ) {
                    $friendly_url_index = 'friendly_url';
                }

                if (isset($catalog['data'][$friendly_url_index]) && $catalog['data'][$friendly_url_index] != '') {
                    $friendly_url = $catalog['data'][$friendly_url_index];
                } else {
                    if (isset($catalog['data'][$section_name_index]) && !empty($catalog['data'][$section_name_index])) {
                        $friendly_url = $catalog['data'][$section_name_index];
                    } else {
                        $friendly_url = '';
                    }
                }

                if ($friendly_url != '') {
                    $friendly_url = Tools::link_rewrite($friendly_url);
                    if (!isset($cat->link_rewrite[$lang['id_lang']]) ||
                        $friendly_url != $cat->link_rewrite[$lang['id_lang']]
                    ) {
                        $cat->link_rewrite[$lang['id_lang']] = $friendly_url;
                        // $need_update = true;
                    }
                }

                /**
                 * Set default values if is a null
                 */

                if ($lang['id_lang'] != $this->defaultLanguage) {
                    if ($catalog_name != '' && (!isset($cat->name[$this->defaultLanguage]) ||
                            ($cat->name[$this->defaultLanguage] == null || $cat->name[$this->defaultLanguage] == ''))
                    ) {
                        $cat->name[$this->defaultLanguage] = $catalog_name;
                        // $need_update = true;
                    }
                    if ($section_description != '' && (!isset($cat->description[$this->defaultLanguage]) ||
                            ($cat->description[$this->defaultLanguage] == null ||
                                $cat->description[$this->defaultLanguage] == ''))
                    ) {
                        $cat->description[$this->defaultLanguage] = $section_description;
                        // $need_update = true;
                    }
                    if ($meta_title != '' && (!isset($cat->meta_title[$this->defaultLanguage]) ||
                            ($cat->meta_title[$this->defaultLanguage] == null ||
                                $cat->meta_title[$this->defaultLanguage] == ''))
                    ) {
                        $cat->meta_title[$this->defaultLanguage] = $meta_title;
                        // $need_update = true;
                    }
                    if ($meta_description != '' && (!isset($cat->meta_description[$this->defaultLanguage]) ||
                            ($cat->meta_description[$this->defaultLanguage] == null ||
                                $cat->meta_description[$this->defaultLanguage] == ''))
                    ) {
                        $cat->meta_description[$this->defaultLanguage] = $meta_description;
                        //  $need_update = true;
                    }
                    if ($friendly_url != '' && (!isset($cat->link_rewrite[$this->defaultLanguage]) ||
                            ($cat->link_rewrite[$this->defaultLanguage] == null ||
                                $cat->link_rewrite[$this->defaultLanguage] == ''))
                    ) {
                        $cat->link_rewrite[$this->defaultLanguage] = $friendly_url;
                        // $need_update = true;
                    }
                }
            }

            if (isset($catalog['data']['active']) && $catalog['data']['active'] != '') {
                $cat->active = $this->slValidateBoolean($catalog['data']['active']);
            } else {
                $cat->active = 1;
            }

            // $cat->save();

            /* if (isset($catalog['data']['section_reference']) && $catalog['data']['section_reference'] != '') {
                 //Reload the category because after save Prestashop generates the Meta arrays.
                 // $cat = new Category($cat->id);

                 $section_reference = $catalog['data']['section_reference'];

                 foreach ($cat->meta_keywords as $key => $meta_keywords) {
                     if ($meta_keywords === '') {
                         $cat->meta_keywords[$key] = $section_reference;
                     // $need_update = true;
                     } else {
                         $mk = explode(',', $meta_keywords);
                         if (!in_array($section_reference, $mk, false)) {
                             $cat->meta_keywords[$key] = $section_reference . ',' . $meta_keywords;
                             //   $need_update = true;
                         }
                     }
                 }
             }*/


            try {
                $this->debbug('Active stat before save ->' . print_r($cat->active, 1), 'syncdata');
                $cat->save();

                if ($cat->id) {
                    $catalogue_row = Db::getInstance()->getRow(
                        sprintf(
                            'SELECT sl.ps_id,sl.shops_info FROM ' . _DB_PREFIX_ .
                            'slyr_category_product sl ' .
                            ' WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" ' .
                            ' AND sl.ps_type = "slCatalogue"  AND sl.ps_id = "%s" ',
                            $catalog['ID'],
                            $comp_id,
                            $cat->id
                        )
                    );
                    if ($catalogue_row && isset($catalogue_row['shops_info'])) {
                        $shops_info = json_decode(stripslashes($catalogue_row['shops_info']), 1);
                        if ($shops_info && !empty($shops_info)) {
                            $shops_info[$connector_id] = $shops;
                        } else {
                            $shops_info = [];
                            $shops_info[$connector_id] = $shops;
                        }
                    } else {
                        $shops_info = [];
                        $shops_info[$connector_id] = $shops;
                    }
                    $update_query = sprintf(
                        'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl
                         SET sl.date_upd = CURRENT_TIMESTAMP() , sl.shops_info ="' .
                            addslashes(json_encode($shops_info)) . '"
                         WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                        $catalog['ID'],
                        $comp_id
                    );

                    if (!Db::getInstance()->execute($update_query)) {
	                    $this->general_error = true;
                        $this->debbug(
                            '## Error. in save changes to cache ' .
                            $occurence .
                            ' query->' . print_r($update_query, 1),
                            'syncdata'
                        );
                    }
                }
            } catch (Exception $e) {
                if (isset($section_reference) && !empty($section_reference)) {
                    $occurence = ' section reference :' . $section_reference;
                } elseif (isset($catalog_name) && !empty($catalog_name)) {
                    $occurence = ' category name :' . $catalog_name;
                } else {
                    $occurence = ' ID :' . $catalog['ID'];
                }
	            $this->general_error = true;
                $syncCat = true;
                $this->debbug(
                    '## Error. Save change to ' . $occurence . ' ->' . print_r($e->getMessage(), 1),
                    'syncdata'
                );
            }

            if ($this->first_sync_shop) {
                $catch_images = array();
                /**
                 * Place your custom  non multi-language code here what is needed to be executed (update query)
                 */



                if (isset($catalog['data']['section_image'])) {
                    foreach ($catalog['data']['section_image'] as $image_list) {
                        if (is_array($image_list)) {
                            foreach ($this->category_images_sizes as $imgFormat) {
                                if (isset($image_list[$imgFormat]) && !empty($image_list[$imgFormat])) {
                                    $catch_images[] = $image_list[$imgFormat];
                                    break;
                                }
                            }
                        }
                    }
                    $protected_ids = array();
                    foreach ($catch_images as $key => $image_url) {
                        //$shops = Shop::getShops(true, null, true);
                        $url = trim($image_url);

                        if (!empty($url)) {
                            $cached = SalesLayerImport::getPreloadedImage($url, 'category', $catalog['ID']);
                            if ($cached) {
                                $temp_file = stripslashes($cached['local_path']);
                                $this->debbug('Image founded in cache as preloaded->' .
                                              print_r($temp_file, 1) . 'and before used  befor clear ->' .
                                              print_r($cached, 1), 'syncdata');
                            } else {
                                $temp_file = $this->downloadImageToTemp($url);
                                $this->debbug('Image downloaded->' .
                                              print_r($temp_file, 1), 'syncdata');
                            }
                            if ($temp_file) {
                                $cat->deleteImage(true);
                                $this->debbug('Uploading image from ->' . print_r($temp_file, 1), 'syncdata');
                                // $url = str_replace(' ', '%20', $url);
                                $this->copyImg($cat->id, $cat->id, $temp_file, 'categories', true, true);
                                $protected_ids[] = $cat->id;
                            }
                            if ($cached) {
                                if (file_exists($temp_file)) {
                                    unlink($temp_file);
                                }
                                SalesLayerImport::deletePreloadImage($url, 'category', $catalog['ID']);
                            }
                        }
                    }
                    if (empty($protected_ids)) {
                        $this->debbug('In the category should not be images is launched image removal', 'syncdata');
                        $cat->deleteImage(true);
                    }
                }
                $this->first_sync_shop = false;
            }


            $category_shops = $cat->getShopsByCategory($cat->id);

            $category_shops_info_schema = "SELECT sl.id, sl.shops_info FROM "
                . _DB_PREFIX_ . "slyr_category_product sl" .
                " WHERE sl.ps_id = " . $cat->id . " AND sl.comp_id = " . $comp_id . " AND sl.ps_type = 'slCatalogue'";

            $category_shops_info = Db::getInstance()->executeS($category_shops_info_schema);

            $found = false;
            //Primero buscamos en las existentes
            if (count($category_shops) > 0) {
                foreach ($category_shops as $key => $category_shop) {
                    if ($shop_id == $category_shop['id_shop']) {
                        $found = true;
                        //Eliminamos para obtener sobrantes
                        unset($category_shops[$key]);
                        break;
                    }
                }
            }

            if (!$found) {
                $this->debbug('Add shop ID to this category ->' . print_r($shop_id, 1), 'syncdata');
                $cat->addShop($shop_id);
            }

            $shopsOtherComps = array();
            if (!empty($category_shops_info)) {
                /**
                 * Load connector shops info for verify shops for unset from this category
                 */


                if (is_array($category_shops_info) && count($category_shops_info)) {
                    foreach ($category_shops_info as $shopsConn) {
                        $shops_info = json_decode($shopsConn['shops_info'], 1);
                        if (is_array($shops_info) && count($shops_info) > 0) {
                            foreach ($shops_info as $conn_id => $shops_cat) {
                                if (!isset($shopsOtherComps[$conn_id])) {
                                    $shopsOtherComps[$conn_id] = array();
                                }
                                foreach ($shops_cat as $shop) {
                                    if (!in_array($shop, $shopsOtherComps[$conn_id], false)) {
                                        //array_push($shopsOtherComps[$conn_id], $shop);
                                        $shopsOtherComps[$conn_id][] = $shop;
                                    }
                                }
                            }
                        }
                    }
                }
                $sl_category_info_conns = json_decode($category_shops_info[0]['shops_info'], 1);
            } else {
                $sl_category_info_conns = array();

                if (isset($section_reference) && !empty($section_reference)) {
                    $occurence = ' section reference :' . $section_reference;
                } elseif (isset($catalog_name) && !empty($catalog_name)) {
                    $occurence = ' category name :' . $catalog_name;
                } else {
                    $occurence = ' ID :' . $catalog['ID'];
                }
	            $this->general_error = true;

                $this->debbug(
                    '## Error. Invalid slyr registration when updating stores for
                    the category with ' . $occurence . ' prestashop id :' . $cat->id,
                    'syncdata'
                );
            }

            //Revisamos las sobrantes
            if (count($category_shops) > 0) {
                $this->debbug(
                    'go to shop elimination review $category_shops->' . print_r(
                        $category_shops,
                        1
                    ) . ' $sl_category_info_conns-> ' . print_r(
                        $sl_category_info_conns,
                        1
                    ) . ' $shopsOtherComps->' . print_r(
                        $shopsOtherComps,
                        1
                    ),
                    'syncdata'
                );
                //Buscamos en conectores
                foreach ($category_shops as $key => $category_shop) {
                    $this->debbug('check shop->' . print_r($category_shop, 1), 'syncdata');
                    $found = false;
                    if (is_array($sl_category_info_conns) && count($sl_category_info_conns) > 0) {
                        foreach ($sl_category_info_conns as $sl_category_info_conn => $sl_category_info_conn_shops) {
                            $this->debbug(
                                'passing to  $sl_category_info_conn_shops->' . print_r(
                                    $sl_category_info_conn_shops,
                                    1
                                ) . '  $sl_category_info_conn->' . $sl_category_info_conn . '  != ' . print_r(
                                    $connector_id,
                                    1
                                ),
                                'syncdata'
                            );
                            if ($sl_category_info_conn != $connector_id) {
                                if (in_array($category_shop['id_shop'], $sl_category_info_conn_shops, false)) {
                                    //  $this->debbug('Encontrado shop->'.print_r($category_shop['id_shop'],1).'
                                    //  $sl_category_info_conn_shops->'.print_r($category_shop['id_shop'],1)
                                    // ,'syncdata');
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (count($shopsOtherComps) > 0) {
                        foreach ($shopsOtherComps as $conn_id => $shopsOtherComp) {
                            if ($connector_id != $conn_id
                                && in_array(
                                    $category_shop['id_shop'],
                                    $shopsOtherComp,
                                    false
                                )
                            ) {
                                // $this->debbug('Encontrado shop_id->'.print_r($category_shop['id_shop'],1).
                                //' $shopsOtherComps->'.print_r($category_shop['id_shop'],1),'syncdata');
                                $found = true;
                            }
                        }
                    }


                    if (count($shops)) { // of this shop and latest update
                        if (in_array($category_shop['id_shop'], $shops, false)) {
                            // $this->debbug('Shop of this  shop_id->'.print_r($category_shop['id_shop'],1).
                            //' $shopsOtherComps->'.print_r($category_shop['id_shop'],1),'syncdata');
                            $found = true;
                        }
                    }


                    if (!$found) {
                        $this->debbug(
                            'Deleting shop ID from category->' . print_r($category_shop['id_shop'], 1),
                            'syncdata'
                        );
                        $cat->deleteFromShop($category_shop['id_shop']);
                    }
                }
            }


            //Actualizamos el registro
            // $sl_category_info_conns[$connector_id] = array($shop_id);
            $sl_category_info_conns[$connector_id] = $shops;
            $shopsInfo = json_encode($sl_category_info_conns);

            $schemaUpdateShops = " UPDATE " . _DB_PREFIX_ . "slyr_category_product
            SET shops_info = '" . $shopsInfo . "' WHERE id = " . $category_shops_info[0]['id'];
            Db::getInstance()->execute($schemaUpdateShops);
        }
        $this->debbug(
            ' >>>>>>>>>>>>>>>>>>>>> End Category ->' . $occurence .
            ' time->' . date("H:i:s") . ' micro-time->' . microtime(true) . ' <<<<<<<<<<<<<<<<<<<<<<<<<<<',
            'syncdata'
        );
        if ($syncCat) {
            if ($this->general_error) {
                $data_hash = null;
            }

            $prepare_input_compare = [];
            $prepare_input_compare['sl_id']               = $catalog['ID'];
            $prepare_input_compare['ps_type']             = 'category';
            $prepare_input_compare['conn_id']             = $connector_id;
            $prepare_input_compare['ps_id']               = $cat->id;
            $prepare_input_compare['hash']                = $data_hash;

            $query_insert =   SalesLayerImport::setRegisterInputCompare($prepare_input_compare);
            $this->debbug(
                $occurence . ' Inserting data hash:' . print_r($query_insert, 1),
                'syncdata'
            );
            unset($cat);
            return 'item_updated';
        } else {
            unset($cat);
            return 'item_not_updated';
        }
    }

    /**
     * Function to rorganize categories path and parents.
     *
     * @param $shop_ids
     *
     * @return string               product deleted or not
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function reorganizeCategories($shop_ids)
    {
        // $microtime = microtime(1);
        $this->debbug('Entry to reorganize for shops ->' .
                      print_r($shop_ids, 1), 'syncdata');
        $contextShopID = Shop::getContextShopID();
        Shop::setContext(Shop::CONTEXT_ALL);
        //Process to reorganize the category tree avoiding.
        $this->loadCategoryTree();

        foreach ($this->categories_collection as $category_id => $category_col) {
            if ($category_col['active'] == 1) {
                $new_parent_id = $category_col['id_parent'];
                if ($new_parent_id != 0) {
                    $this->debbug('Before search parent ' . print_r($new_parent_id, 1), 'syncdata');
                    if (!isset($this->categories_collection[$new_parent_id]) ||
                        $this->categories_collection[$new_parent_id]['active'] == 0
                    ) {
                        do {
                            // $this->debbug('do while revisando ->'.print_r($new_parent_id,1).
                            //' y contenido de parent '.print_r($this->categories_collection[$new_parent_id],1)
                            //,'syncdata');
                            if (!isset($this->categories_collection[$new_parent_id])) {
                                $new_parent_id = (int)Configuration::get('PS_HOME_CATEGORY');
                            } elseif ($this->categories_collection[$new_parent_id]['active'] == 0) {
                                $new_parent_id = $this->categories_collection[$new_parent_id]['id_parent'];
                            }

                            if (!isset($this->categories_collection[$new_parent_id]['id_parent']) ||
                                $this->categories_collection[$new_parent_id]['id_parent'] == 0
                            ) {
                                break;
                            }
                        } while ($this->categories_collection[$new_parent_id]['active'] == 0);
                    }
                }

                try {
                    foreach ($shop_ids as $shop) {
                        Shop::setContext(Shop::CONTEXT_SHOP, $shop);
                        $cat = new Category($category_id, null, $shop);
                        if ($cat->id_parent != $new_parent_id && !empty($new_parent_id)) {
                            if ($new_parent_id == 1) {
                                $cat->is_root_category = 1;
                            } else {
                                $cat->is_root_category = 0;
                            }
                            $cat->id_parent = $new_parent_id;

                            if (!isset($cat->name[$this->defaultLanguage]) ||
                                empty($cat->name[$this->defaultLanguage])
                            ) {
                                $cat->name[$this->defaultLanguage] = 'Default Category ' . $category_id;
                            }

                            $cat->recalculateLevelDepth($cat->id);
                            $this->debbug(
                                'Active stat before save updating in regenerate ' . $cat->id . ' category ->' . print_r(
                                    $cat->active,
                                    1
                                ),
                                'syncdata'
                            );
                            try {
                                $cat->save();
                            } catch (Exception $e) {
                                $this->general_error = true;
                                $this->debbug('## Error. Reorganizing category tree: ' . $e->getMessage() .
                                              ' line ->' . print_r($e->getLine(), 1) .
                                              ' $cat->' . print_r($cat, 1));
                            }

                            $this->categories_collection[$category_id]['parent_id'] = $new_parent_id;
                        }
                    }
                } catch (Exception $e) {
	                $this->general_error = true;
                    $this->debbug('## Error. Reorganizing category tree: ' . $e->getMessage() .
                                  ' line ->' . print_r($e->getLine(), 1) .
                                  ' trace->' . print_r($e->getTrace(), 1));
                }
            }
        }

        try {
            // foreach ($shop_ids as $shop) {
            // Shop::setContext(Shop::CONTEXT_SHOP,$shop);
            Shop::setContext(Shop::CONTEXT_ALL);
            $category_regenerate = new Category(
                (int) Configuration::get('PS_HOME_CATEGORY'),
                (int) Configuration::get('PS_LANG_DEFAULT')
            );
            $category_regenerate->regenerateEntireNtree();
            //  }
            //$category_regenerate->save();
        } catch (Exception $e) {
	        $this->general_error = true;
            $this->debbug('## Error. Reorganizing category tree regenerateEntireNtree: ' . $e->getMessage() .
                          ' print->' . print_r($category_regenerate, 1));
        }

        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopID);
        $this->debbug('End reorganize categories ', 'syncdata');
    }

    /**
     * Load category tree for restructure active categories after modify categories
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function loadCategoryTree()
    {
        $schemaCats = "SELECT id_category, id_parent, active  FROM " . $this->category_table .
            " ORDER BY id_category ASC";

        $categories = Db::getInstance()->executeS($schemaCats);

        if (count($categories)) {
            foreach ($categories as $category_arr) {
                $prepare_data = array();
                $prepare_data['active'] = $category_arr['active'];
                $prepare_data['id_parent'] = $category_arr['id_parent'];
                $this->categories_collection[$category_arr['id_category']] = $prepare_data;
            }
        }

        unset($categories);
    }

    /**
     * Delete Category
     *
     * @param $catalog
     * @param $comp_id
     * @param $shops
     * @param $connector
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */


    public function deleteCategory(
        $catalog,
        $comp_id,
        $shops,
        $connector
    ) {
        $product_ps_arr = Db::getInstance()->executeS(
            sprintf(
                'SELECT sl.id,sl.ps_id,sl.shops_info FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                  WHERE sl.slyr_id = "%s" ANd sl.comp_id = "%s" AND sl.ps_type = "slCatalogue"',
                $catalog,
                $comp_id
            )
        );

        $shops_used_by_other_connector = [];
        if ($product_ps_arr && count($product_ps_arr) && !empty($product_ps_arr)) {
            $element_to_delete = reset($product_ps_arr);
            $catalog_ps_id = (int) $element_to_delete['ps_id'];
            $shops_active = json_decode(stripslashes($element_to_delete['shops_info']), 1);

            if (isset($shops_active[$connector])) {
                foreach ($shops_active[$connector] as $key => $shop_id) {
                    if (in_array($shop_id, $shops, false)) {
                        unset($shops_active[$connector][$key]);
                    }
                }
                if (empty($shops_active[$connector])) {
                    unset($shops_active[$connector]);
                }
                if (!empty($shops_active)) {
                    Db::getInstance()->execute(
                        'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl SET' .
                        " sl.shops_info ='" .
                        addslashes(json_encode($shops_active)) .
                        "'  WHERE sl.id = '" . $element_to_delete['id'] . "' "
                    );
                    foreach ($shops_active as $shops_used) {
                        foreach ($shops_used as $store_used) {
                            $shops_used_by_other_connector[$store_used] = $store_used;
                        }
                    }
                } else {
                    Db::getInstance()->execute(
                        sprintf(
                            'DELETE FROM ' . _DB_PREFIX_ . 'slyr_category_product
                       WHERE slyr_id = "%s" AND comp_id = "%s"
                       AND ps_type = "slCatalogue"',
                            $catalog,
                            $comp_id
                        )
                    );
                }
            }

            foreach ($shops as $shop) {
                $query = "SELECT id_shop FROM " . _DB_PREFIX_ . 'category_shop ' .
                         'WHERE id_category = "' . $catalog_ps_id .
                         '" AND id_shop = "' . $shop . '" GROUP BY id_shop';
                $registers = Db::getInstance()->executeS($query);

                if (empty($shops_used_by_other_connector) && count($registers)) {
                    //if (!in_array($shop, $shops_used_by_other_connector, false)) {
                    try {
                        Shop::setContext(Shop::CONTEXT_SHOP, $shop);
                        $cat         = new Category($catalog_ps_id, null, $shop);
                        $cat->active = 0;
                        $this->debbug(
                            'Deactivate only Category ID: ' . $catalog . ' $shop->' .
                            print_r($shop, 1) .
                            ' Is posible deactivate category for all stores.',
                            'syncdata'
                        );
                        /*  $cat->cleanGroups();
                          $cat->cleanAssoProducts();
                          $children = $cat->getAllChildren();

                          foreach ($children as $categories) {
                              CartRule::cleanProductRuleIntegrity('categories', array( $categories->id ));
                              // Category::cleanPositions($categories->id_parent);
                              /* Delete Categories in GroupReduction */
                        /*
                            if (GroupReduction::getGroupsReductionByCategoryId((int) $categories->id)) {
                                GroupReduction::deleteCategory($categories->id);
                            }
                        }*/

                        $cat->save();
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. Deleting Category ID: ' . $catalog . ' error->' . print_r($e->getMessage(), 1),
                            'syncdata'
                        );
                    }
                } else {
                    $this->debbug(
                        ' It is not possible to deactivate Category with id sl_id ' . $catalog .
                        ' $comp_id ' . $comp_id . ' $shops ->' . print_r(
                            $shops,
                            1
                        ) . ' Category has been uploaded by several routes and there is still' .
                        ' a connector in which the product has been received as visible: ' .
                        print_r($shops_active, 1),
                        'syncdata'
                    );
                }
            }
        }
    }
}

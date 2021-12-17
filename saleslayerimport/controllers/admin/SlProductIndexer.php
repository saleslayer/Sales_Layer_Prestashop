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

class SlProductIndexer extends SalesLayerPimUpdate
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $products_regisers
     */

    public function indexProducts(
        $products_regisers
    ) {
        $this->debbug(
            'entry to index product id_product ->' . print_r($products_regisers, 1),
            'indexer'
        );
        $ids_for_delete = [];
        foreach ($products_regisers as $product) {
            $product_id = $product['id_product'];
            try {
                $query = "SELECT id_shop FROM " . _DB_PREFIX_ . 'product_shop ' .
                         'WHERE id_product = "' . $product_id .
                         '" AND active = "1" GROUP BY id_shop';
                $registers = Db::getInstance()->executeS($query);
                $all_shops_image = [];
                foreach ($registers as $shops) {
                    $all_shops_image[] = $shops['id_shop'];
                }
                //$all_shops_image = json_decode(stripslashes($product['data']), 1);
                /* $this->debbug('Send to indexing  id_product->' .
                               $product_id . ' shops->' . print_r($all_shops_image, 1) .
                               ' query ->' . print_r($query, 1), 'syncdata');*/
                foreach ($all_shops_image as $shop_id_in) {
                    Shop::setContext(shop::CONTEXT_SHOP, $shop_id_in);
                    $prod_index = new Product($product_id, false, null, $shop_id_in);
                    $prod_index->indexed = 0;
                    if ($prod_index->price === null || $prod_index->price === '') {
                        $prod_index->price = 0;
                    }
                    $prod_index->save();
                }
            } catch (Exception $e) {
                $this->debbug('## Error. id_product -> ' . $product_id .
                          ' Set indexer to 0: ' .
                          $e->getMessage(), 'indexer');
            }
            try {
                Shop::setContext(shop::CONTEXT_ALL);
                Search::indexation(false, $product_id);
            } catch (Exception $e) {
                $this->debbug('## Error. id_product->' . $product_id .
                          ' indexer error: ' .
                          $e->getMessage(), 'indexer');
            }

            $up_las_modified = 'UPDATE ' . _DB_PREFIX_ . 'slyr_input_compare  SET timestamp_modified ="' .
                               date('Y-m-d H:i:s') .
                               '" WHERE ' .
                               ' ps_type ="product" ' .
                               'AND ps_id = ' . $product_id .
                               '  AND conn_id = "' . $product['conn_id'] . '"';
            try {
                Db::getInstance()->execute($up_las_modified);
            } catch (Exception $e) {
                $this->debbug('## Error. in query for update las modify of item  id_product->' . $product_id .
                              ' indexer error: ' .
                              $e->getMessage() . ' query->' . print_r($up_las_modified, 1), 'indexer');
            }

            $ids_for_delete[] = $product['id'];
        }
        return $ids_for_delete;
    }
}

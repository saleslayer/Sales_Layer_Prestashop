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
     * @param $all_shops_image
     */

    public function indexProducts(
        $products_regisers,
        $all_shops_image
    ) {
        $this->debbug(
            'entry to index product id_product ->' . $products_regisers,
            'syncdata'
        );
        $ids_for_delete = [];
        foreach ($products_regisers as $product) {
            $product_id = $product['id_product'];
            try {
                $this->debbug('Send to indexing  id_product->' .
                              $product_id, 'syncdata');
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
                          $e->getMessage(), 'syncdata');
            }
            try {
                Shop::setContext(shop::CONTEXT_ALL);
                Search::indexation(false, $product_id);
            } catch (Exception $e) {
                $this->debbug('## Error. id_product->' . $product_id .
                          ' indexer error: ' .
                          $e->getMessage(), 'syncdata');
            }
            $ids_for_delete[] = $product['id'];
        }
        return $ids_for_delete;
    }
}

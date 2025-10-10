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

class SlStockUpdater extends SalesLayerPimUpdate
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $products_regisers
     */

    public function updateStock(
        $products_regisers,
        $store_id,
        $items_type
    ) {
        $this->debbug(
            'entry to Update_stock product id_product ->' . print_r($products_regisers, 1),
            'update-stock'
        );
        $ids_for_stock_update = [];
        $update_stock_to = 0;
        $ps_id = [];

        foreach ($products_regisers as $register) {
            $ps_id[] = $register['ps_id'];
            $update_stock_to = $register['stock'];
            $ids_for_stock_update[] = $register['id'];
        }
        try {
            if ($items_type == 'product') {
                $query_update_product = "UPDATE " . _DB_PREFIX_ .
                                        "stock_available set quantity = '" .
                                        $update_stock_to .
                                        "' where id_product IN('" .
                                        implode("','", $ps_id) .
                                        "') AND id_shop = '" . $store_id . "' AND  id_product_attribute = 0";
                $this->debbug('update product query -> ' . print_r($query_update_product, 1), 'update-stock');
                try {
                    Db::getInstance()->execute($query_update_product);
                } catch (Exception $e) {
                    $this->debbug('## Error. id_product -> ' . print_r(implode(',', $ps_id), 1) .
                                  'end edite stock product: query-> ' . print_r($query_update_product, 1) .
                                  $e->getMessage(), 'update-stock');
                }
            } else {
                $recount_query = "SELECT id_product FROM " . _DB_PREFIX_ . "product_attribute" .
                                 " WHERE id_product_attribute IN('" . implode(',', $ps_id) . "') GROUP BY id_product";
                $product_ids_recount = Db::getInstance()->executeS($recount_query);

                $query_update_cambinations = "UPDATE " . _DB_PREFIX_ . "product_attribute " .
                        " INNER JOIN " . _DB_PREFIX_ . "stock_available on " . _DB_PREFIX_ .
                                             "stock_available.id_product_attribute = "
                                             . _DB_PREFIX_ . "product_attribute.id_product_attribute " .
                        " INNER JOIN " . _DB_PREFIX_ .
                                         "product_attribute_shop on " . _DB_PREFIX_ .
                                             "product_attribute_shop.id_product_attribute = "
                                             . _DB_PREFIX_ .
                                             "product_attribute.id_product_attribute" .
                        " INNER JOIN " . _DB_PREFIX_ . "product on " . _DB_PREFIX_ . "product_attribute.id_product=" .
                                             _DB_PREFIX_ .
                                             "product.id_product SET " . _DB_PREFIX_ .
                                             "stock_available.quantity = '" . $update_stock_to .
                                             "' WHERE " . _DB_PREFIX_ .
                                             "product_attribute_shop.id_shop ='" . $store_id . "' AND "
                                             . _DB_PREFIX_ .
                                             "product_attribute.id_product_attribute IN( '" .
                                             implode("','", $ps_id) . "')";
                $this->debbug('update variants query -> ' . print_r($query_update_cambinations, 1), 'update-stock');
                try {
                    Db::getInstance()->execute($query_update_cambinations);

                    if (count($product_ids_recount)) {
                        /*  foreach ($product_ids_recount as $product) {
                              $update_quantiti_prod = "UPDATE " . _DB_PREFIX_ . "stock_available " .
                                                      " set quantity = ( SELECT SUM(quantity)  FROM  (select * from " .
                                                      _DB_PREFIX_ . "stock_available) AS m2  WHERE m2.id_product = '" .
                                                      $product['id_product'] . "' AND m2.id_product_attribute !=0 )
                                                  WHERE id_product = '" . $product['id_product'] .
                                                      "' AND id_product_attribute = 0 ";
                              try {
                                  Db::getInstance()->execute($update_quantiti_prod);
                              } catch (Exception $e) {
                                  $this->debbug('## Error. id_product -> ' . print_r($product, 1) .
                                               'count quantity of all attributes query-> ' .
                                                print_r($update_quantiti_prod, 1) .
                                               $e->getMessage(), 'update-stock');
                              }
                          }*/
                    }
                } catch (Exception $e) {
                    $this->debbug('## Error. id_product -> ' . print_r(implode(',', $ps_id), 1) .
                                  'end edite stock combination: query-> ' . print_r($query_update_cambinations, 1) .
                                  $e->getMessage() . ' line->' . $e->getLine(), 'update-stock');
                }
            }
        } catch (Exception $e) {
            $this->debbug('## Error. $store_id -> ' . print_r($store_id, 1) .
                          ' Set indexer to 0: ' .
                          $e->getMessage(), 'update-stock');
        }
        return $ids_for_stock_update;
    }
}

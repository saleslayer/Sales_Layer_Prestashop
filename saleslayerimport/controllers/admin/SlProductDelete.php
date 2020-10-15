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

class SlProductDelete extends SalesLayerPimUpdate
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * @param $product
     * @param $comp_id
     * @param $shops
     * @param $connector
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */

    public function deleteProduct(
        $product,
        $comp_id,
        $shops,
        $connector
    ) {
        $this->debbug(
            'Deleting product with id sl_id ' . $product . ' $comp_id ' .
            $comp_id . ' $shops ->' . print_r(
                $shops,
                1
            ) . ' connector ->' .
            print_r($connector, 1),
            'syncdata'
        );
        $product_ps_arr = Db::getInstance()->executeS(
            sprintf(
                'SELECT sl.id,sl.ps_id,sl.shops_info FROM ' . _DB_PREFIX_ . 'slyr_category_product sl
                WHERE sl.slyr_id = "%s" AND sl.comp_id = "%s" AND sl.ps_type = "product"',
                $product,
                $comp_id
            )
        );

        $shops_used_by_other_connector = [];
        if ($product_ps_arr && count($product_ps_arr) && !empty($product_ps_arr)) {
            $element_to_delete = reset($product_ps_arr);
            $product_ps_id = (int) $element_to_delete['ps_id'];
            $shops_active = json_decode(Tools::stripslashes($element_to_delete['shops_info']), 1);

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
                    $update_query = 'UPDATE ' . _DB_PREFIX_ . 'slyr_category_product sl ' .
                                    " SET sl.shops_info ='" .
                                    addslashes(json_encode($shops_active)) .
                                    "'  WHERE sl.id = '" . $element_to_delete['id'] . "' ";

                    $return_update = Db::getInstance()->execute($update_query);

                    if (!$return_update) {
                        $this->debbug(
                            '## Error. In update cache data ->' . print_r(
                                $return_update,
                                1
                            ) . 'query ->' . $update_query,
                            'syncdata'
                        );
                    }
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
                       AND ps_type = "product"',
                            $product,
                            $comp_id
                        )
                    );
                }
            }

            foreach ($shops as $shop) {
                if (!in_array($shop, $shops_used_by_other_connector, false)) {
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
                            $this->debbug(
                                'Product hide success ID:' . $product .
                                '->' . print_r(
                                    $shops_used_by_other_connector,
                                    1
                                ),
                                'syncdata'
                            );
                            unset($prod);
                        }
                    } catch (Exception $e) {
                        $this->debbug(
                            '## Error. Problem hiding product ID:' . $product . ' error->' . print_r(
                                $e->getMessage(),
                                1
                            ) . ' it has not been possible to find a product that we must deactivate,' .
                            'it is possible that it does not exist anymore in prestashop,' .
                            'and thus it can no longer be eliminated.' .
                            ' Try deactivating product manually from prestashop.',
                            'syncdata'
                        );
                    }
                } else {
                    $this->debbug(
                        'It is not possible to deactivate product with id sl_id ' . $product .
                        ' $comp_id ' . $comp_id . ' $shops ->' . print_r(
                            $shops,
                            1
                        ) . ' Product has been uploaded by several routes and there is still' .
                        ' a connector in which the product has been received as visible: ' .
                        print_r($shops_active, 1),
                        'syncdata'
                    );
                }
            }
        }
    }
}

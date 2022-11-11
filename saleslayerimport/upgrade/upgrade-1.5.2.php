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

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_5_2()
{
    $SLimport = new SalesLayerImport();

    //Create all the necessary tables empty
    $SLimport->checkDB();

    $tables = [
        /* from version 1.3 */
        'slyr_image',
        /* from version 1.4.0 */
        'slyr___api_config' ,
        'slyr_category_product',
        'slyr_syncdata',
        'slyr_syncdata_flag',
        'slyr_additional_config',
        /*from version 1.4.20*/
        'slyr_indexer',
        'slyr_accessories',
        /*from version 1.5*/
        'slyr_input_compare',
        'slyr_stock_update',
        'slyr_image_preloader',
        'slyr_process'
    ];

    foreach ($tables as $table) {
        try {
            Db::getInstance()->execute('ALTER TABLE  `'.
                                       _DB_PREFIX_.$table .'` ENGINE=MyISAM; ');
        } catch (Exception $e) {
        }
    }

    $SLimport->debbug('Updating to version 1.5.2 success', '', true);

    return true;
}

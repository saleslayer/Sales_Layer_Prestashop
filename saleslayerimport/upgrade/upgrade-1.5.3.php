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

function upgrade_module_1_5_3()
{
    $SLimport = new SalesLayerImport();

    //Create all the necessary tables empty

    $tables = [
        'slyr_process'
    ];

    foreach ($tables as $table) {
        try {
            Db::getInstance()->execute('DROP TABLE `'. _DB_PREFIX_ . $table .'`; ');
        } catch (Exception $e) {
            $SLimport->debbug('Error un drop table', '', true);
        }
    }
    $SLimport->checkDB();
    Db::getInstance()->execute('ALTER TABLE `'. _DB_PREFIX_.'slyr_category_product` '.
                               ' ADD INDEX `indice_1` (`ps_id` ASC, `slyr_id` ASC, `ps_type` ASC),	'.
                               ' ADD INDEX `indice_2` (`ps_id` ASC, `comp_id` ASC, `ps_type` ASC,`ps_attribute_group_id` ASC);');
    $SLimport->debbug('Updating to version 1.5.3 success', '', true);

    return true;
}

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

function upgrade_module_1_4_16()
{
    $SLimport = new SalesLayerImport();

    //Create all the necessary tables empty
    $SLimport->checkDB();
    $plugin_dir = _PS_MODULE_DIR_  . $SLimport->name . '/';
    $integrity_file = $plugin_dir . '/integrity/';
    if (!file_exists($integrity_file)) {
        if (!mkdir($integrity_file, 0775, true) && ! is_dir($integrity_file)) {
            $SLimport->debbug("## Error. Directory was not created -> $integrity_file");
        }
    }
    // $SLimport->debbug('Updating to version 1.4.16 success', '', true);

    return true;
}

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

function upgrade_module_1_4_20()
{
    $SLimport = new SalesLayerImport();

    //Create all the necessary tables empty
    $SLimport->checkDB();

    return true;
}

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

function upgrade_module_1_4_1()
{
    $SLimport = new SalesLayerImport();

    //force delete stored information and table
    Db::getInstance()->execute('TRUNCATE TABLE IF EXISTS ' . _DB_PREFIX_ . 'slyr_syncdata');
    Db::getInstance()->execute('TRUNCATE TABLE IF EXISTS ' . $SLimport->saleslayer_syncdata_flag_table);

    //Create all the necessary tables empty
    $SLimport->checkDB();

    $connectors = $SLimport->sl_updater->getConnectorsInfo(null, true);
    if (!empty($connectors)) {
        foreach ($connectors as $connector) {
            $SLimport->setConnectorData($connector['conn_code'], 'last_update', 0);
        }
    }
    $SLimport->debbug('Updating to version 1.4.1 success', '', true);

    return true;
}

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

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');
$process_name = 'image-preloader';
/* Check security token */

if (!Module::isInstalled('saleslayerimport')
    || Tools::substr(
        Tools::encrypt('saleslayerimport'),
        0,
        10
    ) != Tools::getValue('token')
) {
    die('Bad token');
}

try {
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'saleslayerimport.php';
    $SLimport = new SalesLayerImport();
    $SLimport->errorSetup();
} catch (Exception $e) {
    die('Exception in load plugin file->' . $e->getMessage());
}
 $ps_type = Tools::getValue('ps_type');

$get = getmypid();
if ($SLimport->checkRegistersForProccess(false, 'image_preloader')) {
    ini_set('max_execution_time', 144000);
    $query_ps_type = '';
    $limit_for_check_status = 100; // 500
    $sleep_interval = 20;
    $prepared_limit_for_sleep = 300;
    $death_limit_images = 7000;
    if ($ps_type != '') {
        $query_ps_type = " ps_type = '" . $ps_type . "' AND ";
    }
    $count_prepared_query = "SELECT COUNT(*) as count FROM " . _DB_PREFIX_ .
                            'slyr_image_preloader WHERE status = "co" ';
    $count_result =  Db::getInstance()->getValue($count_prepared_query);


    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '/controllers/admin/SlImagePreloader.php';
    $preloader = new SlImagePreloader();
    $SLimport->debbug(
        'check count images  ->' . print_r($count_result, 1),
        'image_preloader'
    );
    if ($count_result >= $death_limit_images) {// default
        exit();
    }
    if (!$SLimport->getRunProceses($process_name, $get)) {
        exit();
    }

    try {
        $performance_limit = $SLimport->getConfiguration('PERFORMANCE_LIMIT');
        if (!$performance_limit) {
            $performance_limit = 3.00;
        }
        $registers = [];
        $counter = 0;
        do {
            try {
                $sqlpre = ' SET @id = null,@url = null';
                $sqlpre2 =  'UPDATE ' . _DB_PREFIX_ .
                            'slyr_image_preloader dest, (SELECT MIN(A.id) AS id ,@id := A.id,' .
                            '@url := A.url ' .
                            ' FROM ' . _DB_PREFIX_ . 'slyr_image_preloader A ' .
                            " WHERE " .
                            " " . $query_ps_type . "  status ='no' GROUP BY id LIMIT 1 ) src " .
                            " SET " .
                            " dest.status = 'pr'  WHERE   dest.id = src.id  ";
                $sqlpre3 = ' SELECT @id AS id,@url AS url  ';

                $SLimport->slConnectionQuery('-', $sqlpre);
                $SLimport->slConnectionQuery('-', $sqlpre2);
                $image = $SLimport->slConnectionQuery('read', $sqlpre3);


                if (!empty($image)
                    && isset($image[0]['id'])
                    && $image[0]['id'] != null
                ) {
                    $response = $preloader->preloadImage(Tools::stripslashes($image[0]['url']));
                    if ($SLimport->debugmode > 2) {
                        $SLimport->debbug(
                            'After executed preloader return ->' . print_r($response, 1),
                            'image_preloader'
                        );
                    }
                    if (!empty($response)) {
                        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'slyr_image_preloader  SET ' .
                                                   ' md5_image= "' . $response['md5_image'] . '"' .
                                                   ', local_path="' . $response['local_path'] . '"' .
                                                   ', status = "co" ' .
                                                   ' WHERE id =' . $image[0]['id']);
                    } else {
                        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'slyr_image_preloader  SET ' .
                                                   ' status = "er" ' .
                                                   ' WHERE id =' . $image[0]['id']);
                    }
                    $SLimport->clearDebugContent();
                } else {
                    $SLimport->debbug('Break 2 from but is end the process', 'image_preloader');
                    unset($image);
                    break;
                }
                if ($counter >= $limit_for_check_status) {
                    $SLimport->debbug('checking counter->' . print_r($counter, 1), 'image_preloader');
                    $count_prepared_query = "SELECT COUNT(*) as count FROM " . _DB_PREFIX_ .
                                            'slyr_image_preloader WHERE status = "co" ';
                    $count_result =  Db::getInstance()->getValue($count_prepared_query);
                    $SLimport->debbug('Counter checked prepared_items.->' .
                                      print_r($count_result, 1), 'image_preloader');
                    if ($count_result) {
                        if ($count_result >= $prepared_limit_for_sleep) {
                            if ($count_result >= $death_limit_images) {
                                $SLimport->debbug('Have limit images preloaded->' . $count_result .
                                                  ' limit->' .
                                                  print_r($death_limit_images, 1), 'image_preloader');
                                break;
                            }
                            $load = sys_getloadavg();
                            if ($load[0] <= ($performance_limit - 1)) {
                                sleep($sleep_interval);
                                $SLimport->loadDebugMode();
                            } else {
                                $SLimport->debbug('Break but is overloaded->' . $load[0], 'image_preloader');
                                break;
                            }
                        } else {
                            $counter = 0;
                        }
                    }
                }
            } catch (Exception $e) {
                $SLimport->debbug('## Error. Preloader error : ' . $e->getMessage() .
                                  ' line->' . $e->getLine(), 'image_preloader');
            }
            $SLimport->clearDebugContent();
            $counter++;
        } while (!empty($image));
    } catch (Exception $e) {
        $SLimport->debbug(
            '## Error. in load Preloader file ->' . print_r($e->getMessage(), 1),
            'image_preloader'
        );
    }
    $SLimport->clearWorkProcess($process_name);
}

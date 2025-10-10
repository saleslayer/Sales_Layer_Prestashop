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

class SlImagePreloader extends SalesLayerPimUpdate
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $ImageToPreload
     */

    public function preloadImage(
        $ImageToPreload
    ) {
        /*  $this->debbug(
              'entry to ImagePreloader product url ->' . print_r($ImageToPreload, 1),
              'syncdata'
          );*/
        $output = [];
        $url = trim($ImageToPreload);

        if (!empty($url)) {
            $temp_image = $this->downloadImageToTemp($url);
            if ($temp_image) {
                $file =  $temp_image;
                if (file_exists($file)) {
                    $output['md5_image'] = md5_file($file);
                    $output['local_path'] = $file;
                } else {
                    $this->debbug(
                        '## Error. file not exist ->' . print_r($file, 1),
                        'image_preloader'
                    );
                }
            } else {
                /* $this->debbug(
                     'entry to ImagePreloader product url ->' . print_r($ImageToPreload, 1),
                     'image_preloader'
                 );*/
            }
        }
        return $output;
    }
}

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


class SaleslayerimportdiagtoolsModuleFrontController extends ModuleFrontController
{
    public $SLimport;


    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
        $this->SLimport = new SalesLayerImport();
        if (!file_exists(DEBBUG_PATH_LOG)) {
            mkdir(DEBBUG_PATH_LOG, 0777, true);
        }
    }

    public function displayAjax()
    {
        //  $microtime_load_process = microtime(1);
        if (Tools::substr(Tools::encrypt('saleslayerimport'), 0, 10) != Tools::getValue('token')) {
            $return = array();
            $return['message_type'] = 'error';
            $return['message'] = 'Invalid Token.';
            die(json_encode($return));
        }
        $command = Tools::getValue('logcommand');
        $array_return = array();
        $response_function = array();
        switch ($command) {
            case 'showlogfiles':
                $response_function = $this->checkFilesLogs();
                break;
            case 'cleardatahash':
                $this->SLimport->cleardatahash(false);
                $response_function[0] = 1;
                $response_function[1] = '';
                $response_function['function'] = 'debugmode';
                break;
            case 'debugmode':
                $value = Tools::getValue('value');
                if ($value != null) {
                    $this->SLimport->setDebugMode($value);
                    $response_function[0] = 1;
                    $response_function[1] = '';
                    $response_function['function'] = 'debugmode';
                } else {
                    $response_function[0] = 0;
                }
                break;
            case 'performance':
                $value = Tools::getValue('value');
                if ($value != null) {
                    $value = (int) $value . '.00';
                    $this->SLimport->setPerformanceLimit($value);
                    $response_function[0] = 1;
                    $response_function[1] = '';
                    $response_function['function'] = 'performance';
                } else {
                    $response_function[0] = 0;
                }
                break;
            default:
                $command = Tools::getValue('logcommand');
                $line = Tools::getValue('value');
                $response_function = $this->showContentFile($command, $line);
                break;
        }

        // $this->SLimport->debbug('Stat of memory before prepare send->' .
        // (microtime( 1) - $microtime_load_process),'syncdata');
        if ($response_function[0] == 1) {
            $array_return['message_type'] = 'success';
            $array_return['function'] = $response_function['function'];
            $array_return['content'] = $response_function[1];
            unset($response_function[1]);
            if (!in_array($command, ['showlogfiles','debugmode','performance','cleardatahash'], false)) {
                $array_return['lines'] = $response_function[2];
                //  $array_return['warnings'] = $response_function[3];
                //  $array_return['errors'] = $response_function[4];
                $array_return['statline'] = $response_function['stat_line'];
            }
        } else {//showlogfiles
            $array_return['message_type'] = 'error';
            $array_return['function'] = $response_function['function'];
            $array_return['content'] = $response_function[1];
            unset($response_function[1]);
            $array_return['lines'] = $response_function['info'];
            $array_return['warnings'] = $response_function['warnings'];
            $array_return['errors'] = $response_function['errors'];
        }
        unset($response_function);
        //  $microtime_load_process = microtime(1);
        $array_return = json_encode($array_return, JSON_HEX_QUOT, 100);
        // $this->SLimport->debbug('Stat of memory after JSON_HEX_QUOT ->' .
        // (microtime( 1) - $microtime_load_process),'syncdata');

        die($array_return);
    }

    /**
     * Function to check files Sales Layer logs.
     * @return array
     */

    public function checkFilesLogs()
    {
        $ignore_files = array('index.php','.','..');
        $files = array();
        $table = array('file' => array(), 'lines' => array(), 'warnings' => array(), 'errors' => array());
        $response = array();
        $response[1] = array();
        $log_dir_path = $this->SLimport->log_module_path;

        $log_folder_files = array_slice(scandir($log_dir_path), 2);

        $files_found = false;

        if (!empty($log_folder_files)) {
            foreach ($log_folder_files as $log_folder_file) {
                if (in_array($log_folder_file, $ignore_files, false)) {
                    continue;
                }
                $files[] = $log_folder_file;
            }

            if (count($files) >= 1) {
                foreach ($files as $file) {
                    $errors = 0;
                    $warnings = 0;
                    $lines = 0;
                    $file_path = $log_dir_path . '/' . $file;

                    if (file_exists($file_path)) {
                        $table['file'][] = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');

                        //$fileopened = file($log_dir_path.'/'.$file);
                        $fileopened = new SplFileObject($file_path);


                        //  foreach ($fileopened as  $line){
                        while (!$fileopened->eof()) {
                            $line = $fileopened->current();
                            $fileopened->next();

                            if (Tools::strlen(trim($line)) >= 1) {
                                if (stripos($line, "## Error.") !== false || stripos($line, "error") !== false) {
                                    $errors++;
                                } elseif (stripos($line, "warning") !== false) {
                                    $warnings++;
                                }
                            }
                            $lines++;
                        }


                        $table['lines'][] = $lines - 1;
                        $table['warnings'][] = $warnings;
                        $table['errors'][] = $errors;
                    }

                    $files_found = true;
                }
            }
        }

        if ($files_found) {
            $response[0] = 1;
            $response[1] = $table;
        } else {
            $response[0] = 1;
            $response[1] = $table;
        }

        $response['function'] = 'showlogfiles';

        return $response;
    }

    /**
     *  Function to show content log file.
     * @return array
     */

    public function showContentFile(
        $logfile,
        $lineNumber
    ) {
        $logfile = html_entity_decode($logfile);
        $file_array = explode('/', $logfile);
        $logfile = end($file_array);

        if (preg_match('/[A-Za-z0-9]*.[A-Za-z0-9]{3}/', $logfile)) {
            $response     = array();
            $response[1]  = array();
            $log_dir_path = $this->SLimport->log_module_path;

            $exportlines = array();


            if (file_exists($log_dir_path . $logfile)) {
                $total_lines = $this->countLines($log_dir_path . $logfile);

                $max_lines_conection = 20000;
                //  $listed = 0;
                //  $warnings = 0;
                //  $numerrors = 0;
                $currentLine = 0;


                $file = new SplFileObject($log_dir_path . $logfile);
                if ($total_lines > $lineNumber) {
                    $file->seek($lineNumber);
                    $currentLine = $lineNumber;
                }
                if ($total_lines > $lineNumber) {
                    $restant = $total_lines - $lineNumber;
                } else {
                    $restant = 0;
                }

                if ($restant > $max_lines_conection) {
                    $stopLimit = $currentLine + $max_lines_conection;
                } else {
                    $stopLimit = $currentLine + $restant;
                }

                if ($stopLimit > $total_lines) {
                    $stopLimit = $total_lines;
                }


                $spacingarray = array();
                for (; ! $file->eof() && $currentLine < $stopLimit; $currentLine++) {
                    $line = $file->current();
                    $file->next();


                    if (count($spacingarray) >= 1 && (strpos($line, ")") !== false)) {
                        array_pop($spacingarray);
                    }

                    if (count($spacingarray) >= 1) {
                        if (strpos($line, "(") !== false) {
                            array_pop($spacingarray);
                            $spacing        = implode('', $spacingarray);
                            $spacingarray[] = '&emsp;&emsp;';
                        } else {
                            $spacing = implode('', $spacingarray);
                        }
                    } else {
                        $spacing = '';
                    }
                    //  $listed++;
                    if (stripos($line, 'error') !== false) {
                        $exportlines[ 'l-' . $currentLine ]['stat']    = 'error';
                        $exportlines[ 'l-' . $currentLine ]['spacing'] = $spacing;
                        $exportlines[ 'l-' . $currentLine ]['content'] = $line;
                    // $numerrors++;
                    } elseif (stripos($line, 'warning') !== false) {
                        $exportlines[ 'l-' . $currentLine ]['stat']    = 'warning';
                        $exportlines[ 'l-' . $currentLine ]['spacing'] = $spacing;
                        $exportlines[ 'l-' . $currentLine ]['content'] = $line;
                    //  $warnings++;
                    } elseif (stripos($line, '## Info.') !== false) {
                        $exportlines[ 'l-' . $currentLine ]['stat']    = 'info';
                        $exportlines[ 'l-' . $currentLine ]['spacing'] = $spacing;
                        $exportlines[ 'l-' . $currentLine ]['content'] = $line;
                    } else {
                        $exportlines[ 'l-' . $currentLine ]['stat']    = '';
                        $exportlines[ 'l-' . $currentLine ]['spacing'] = $spacing;
                        $exportlines[ 'l-' . $currentLine ]['content'] = $line;
                    }
                    if (stripos($line, 'Array') !== false) {
                        $spacingarray[] = '&emsp;&emsp;';
                    }
                }

                $file = '';
                unset($file);


                $response[0] = 1;
                $response[1] = $exportlines;
                unset($exportlines);
                $response[2]           = $total_lines;
                $response['stat_line'] = $currentLine;
            } else {
                $response[0]          = 1;
                $response[1]          = array( 'Log file does not exist.' );
                $response['function'] = '';
            }
        } else {
            $response[0]          = 1;
            $response[1]          = array( 'Log file does not accepted.' );
            $response['function'] = '';
        }

        $response['function'] = 'showlogfilecontent';

        return $response;
    }

    private function countLines(
        $file
    ) {
        $linecount = 0;
        $handle = fopen($file, "rb");
        while (!feof($handle)) {
            fgets($handle);
            $linecount++;
        }
        fclose($handle);

        return $linecount;
    }
}

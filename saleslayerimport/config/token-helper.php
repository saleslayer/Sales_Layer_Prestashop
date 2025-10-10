<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * Sales-layer PIM Plugin for Prestashop - Token Helper
 * Compatible con PrestaShop 1.6.x - 9.x
 *
 * @author    Sales Layer
 * @copyright 2019 Sales Layer
 * @license   License: GPLv3  License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Genera un token de seguridad compatible con todas las versiones de PrestaShop
 * 
 * Esta función proporciona compatibilidad retroactiva y hacia adelante:
 * - PrestaShop 1.6.x - 8.x: Usa Tools::encrypt() si está disponible
 * - PrestaShop 9.x+: Usa hash() ya que Tools::encrypt() fue eliminado
 * 
 * @param string $string String base para generar el token
 * @param int $length Longitud del token (por defecto 10)
 * @return string Token generado
 */
function slyr_generate_token($string = 'saleslayerimport', $length = 10)
{
    // Método 1: Intentar usar Tools::encrypt() si existe (PS 1.6 - 8.x)
    if (class_exists('Tools') && method_exists('Tools', 'encrypt')) {
        try {
            return Tools::substr(Tools::encrypt($string), 0, $length);
        } catch (Exception $e) {
            // Si falla, continuar con el método alternativo
        }
    }
    
    // Método 2: Usar hash() para PS 9.x+ o como fallback
    return substr(hash('sha256', $string), 0, $length);
}

/**
 * Verifica si un token es válido
 * 
 * @param string $token Token a verificar
 * @param string $string String base usado para generar el token
 * @param int $length Longitud esperada del token
 * @return bool True si el token es válido
 */
function slyr_verify_token($token, $string = 'saleslayerimport', $length = 10)
{
    if (empty($token)) {
        return false;
    }
    
    $expected_token = slyr_generate_token($string, $length);
    
    // Comparación segura contra timing attacks
    if (function_exists('hash_equals')) {
        return hash_equals($expected_token, $token);
    }
    
    // Fallback para versiones antiguas de PHP
    return $expected_token === $token;
}

/**
 * Obtiene el token desde la petición (GET o POST)
 * 
 * @return string|null Token recibido o null si no existe
 */
function slyr_get_request_token()
{
    if (class_exists('Tools')) {
        return Tools::getValue('token');
    }
    
    // Fallback manual si Tools no está disponible
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }
    
    if (isset($_POST['token'])) {
        return $_POST['token'];
    }
    
    return null;
}

/**
 * Verifica la seguridad del módulo (instalación + token)
 * 
 * @param string $module_name Nombre del módulo (por defecto 'saleslayerimport')
 * @param bool $die_on_fail Si debe terminar la ejecución en caso de fallo
 * @return bool True si la verificación es exitosa
 */
function slyr_check_security($module_name = 'saleslayerimport', $die_on_fail = true)
{
    // Verificar que el módulo está instalado
    if (!Module::isInstalled($module_name)) {
        if ($die_on_fail) {
            die('Module not installed');
        }
        return false;
    }
    
    // Obtener token de la petición
    $request_token = slyr_get_request_token();
    
    // Verificar token
    if (!slyr_verify_token($request_token, $module_name, 10)) {
        if ($die_on_fail) {
            die('Bad token');
        }
        return false;
    }
    
    return true;
}


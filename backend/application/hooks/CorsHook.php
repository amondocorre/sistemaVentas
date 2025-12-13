<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CORS Hook
 * Maneja las cabeceras CORS para permitir peticiones desde el frontend
 */
class CorsHook
{
    public function handle()
    {
        // Permitir origen (en producción, especificar el dominio exacto)
        header('Access-Control-Allow-Origin: *');
        
        // Métodos permitidos
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        
        // Headers permitidos
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // Tiempo de cache para preflight
        header('Access-Control-Max-Age: 3600');
        
        // Manejar preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit();
        }
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Hooks
|--------------------------------------------------------------------------
*/

// Hook para manejar CORS antes de que se procese la solicitud
$hook['pre_system'][] = array(
    'class'    => 'CorsHook',
    'function' => 'handle',
    'filename' => 'CorsHook.php',
    'filepath' => 'hooks'
);

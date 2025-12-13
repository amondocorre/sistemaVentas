<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| MIME TYPES
|--------------------------------------------------------------------------
| Archivo requerido por CodeIgniter 3 para validar MIME en uploads.
| Si falta, la librería Upload puede rechazar archivos válidos.
*/

return array(
    'gif'  => array('image/gif'),
    'jpg'  => array('image/jpeg', 'image/pjpeg'),
    'jpeg' => array('image/jpeg', 'image/pjpeg'),
    'jpe'  => array('image/jpeg', 'image/pjpeg'),
    'png'  => array('image/png', 'image/x-png'),
    'webp' => array('image/webp'),
);

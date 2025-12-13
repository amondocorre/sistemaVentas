<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Welcome extends Public_Controller
{
    public function index()
    {
        $this->response(array(
            'success' => true,
            'message' => 'API Sistema de Ventas e Inventarios',
            'version' => '1.0.0',
            'endpoints' => array(
                'auth' => '/api/auth/login',
                'dashboard' => '/api/dashboard',
                'productos' => '/api/productos',
                'ventas' => '/api/ventas',
                'inventario' => '/api/inventario',
                'reportes' => '/api/reportes'
            )
        ));
    }
}

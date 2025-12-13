<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Configuracion extends MY_Controller
{
    protected $allowed_roles = array('admin');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Configuracion_model');
    }

    public function index()
    {
        $this->response(array(
            'success' => true,
            'data' => $this->Configuracion_model->get_all()
        ));
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input)) {
            $this->response(array('success' => false, 'message' => 'Datos requeridos'), 400);
        }
        
        $this->Configuracion_model->update_multiple($input);
        
        $this->response(array(
            'success' => true,
            'message' => 'Configuración actualizada'
        ));
    }
}

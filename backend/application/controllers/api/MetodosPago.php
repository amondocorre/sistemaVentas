<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class MetodosPago extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('MetodoPago_model');
    }

    public function index()
    {
        $estado = $this->input->get('estado');

        // Si no viene el parámetro: por defecto mostrar activos (1)
        // Si viene vacío o 'all': mostrar todos (sin filtro)
        if ($estado === null) {
            $filters = array('estado' => 1);
        } elseif ($estado === '' || $estado === 'all') {
            $filters = array();
        } else {
            $filters = array('estado' => $estado);
        }

        $this->response(array('success' => true, 'data' => $this->MetodoPago_model->get_all($filters)));
    }

    public function show($id)
    {
        $metodo = $this->MetodoPago_model->get_by_id($id);
        if (!$metodo) {
            $this->response(array('success' => false, 'message' => 'Método de pago no encontrado'), 404);
        }
        $this->response(array('success' => true, 'data' => $metodo));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('metodos_pago');
        $input = $this->get_json_input();
        
        if (empty($input['nombre'])) {
            $this->response(array('success' => false, 'message' => 'El nombre es requerido'), 400);
        }
        
        $id = $this->MetodoPago_model->create(array(
            'nombre' => $input['nombre'],
            'tipo' => isset($input['tipo']) ? $input['tipo'] : 'otro',
            'descripcion' => isset($input['descripcion']) ? $input['descripcion'] : null,
            'configuracion' => isset($input['configuracion']) ? $input['configuracion'] : null,
            'requiere_referencia' => isset($input['requiere_referencia']) ? $input['requiere_referencia'] : 0,
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        ));
        
        $this->response(array('success' => true, 'message' => 'Método de pago creado', 'data' => array('id' => $id)), 201);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('metodos_pago');
        
        if (!$this->MetodoPago_model->get_by_id($id)) {
            $this->response(array('success' => false, 'message' => 'Método de pago no encontrado'), 404);
        }
        
        $input = $this->get_json_input();
        $data = array();
        foreach (array('nombre', 'tipo', 'descripcion', 'configuracion', 'requiere_referencia', 'estado') as $campo) {
            if (isset($input[$campo])) $data[$campo] = $input[$campo];
        }
        
        if (!empty($data)) $this->MetodoPago_model->update($id, $data);
        $this->response(array('success' => true, 'message' => 'Método de pago actualizado'));
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('metodos_pago');
        
        if (!$this->MetodoPago_model->get_by_id($id)) {
            $this->response(array('success' => false, 'message' => 'Método de pago no encontrado'), 404);
        }
        
        $this->MetodoPago_model->delete($id);
        $this->response(array('success' => true, 'message' => 'Método de pago eliminado'));
    }

    public function upload_qr($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('metodos_pago');
        
        $metodo = $this->MetodoPago_model->get_by_id($id);
        if (!$metodo) {
            $this->response(array('success' => false, 'message' => 'Método de pago no encontrado'), 404);
        }
        
        $config['upload_path'] = FCPATH . 'uploads/qr/';
        $config['allowed_types'] = 'gif|jpg|jpeg|png|webp';
        $config['max_size'] = 1024;
        $config['encrypt_name'] = TRUE;
        $config['file_ext_tolower'] = TRUE;
        
        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0755, TRUE);
        }
        
        $this->load->library('upload', $config);
        
        if (!$this->upload->do_upload('imagen')) {
            $this->response(array('success' => false, 'message' => $this->upload->display_errors('', '')), 400);
        }
        
        $upload_data = $this->upload->data();
        $imagen = 'uploads/qr/' . $upload_data['file_name'];
        
        $this->MetodoPago_model->update_qr($id, $imagen);
        
        $this->response(array('success' => true, 'message' => 'QR subido', 'data' => array('imagen_qr' => $imagen)));
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Roles extends MY_Controller
{
    protected $allowed_roles = array('admin');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Rol_model');
    }

    public function index()
    {
        $filters = array('estado' => $this->input->get('estado') !== null ? $this->input->get('estado') : 1);
        $this->response(array('success' => true, 'data' => $this->Rol_model->get_all($filters)));
    }

    public function show($id)
    {
        $rol = $this->Rol_model->get_by_id($id);
        if (!$rol) {
            $this->response(array('success' => false, 'message' => 'Rol no encontrado'), 404);
        }
        $this->response(array('success' => true, 'data' => $rol));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input['nombre'])) {
            $this->response(array('success' => false, 'message' => 'El nombre es requerido'), 400);
        }
        
        $id = $this->Rol_model->create(array(
            'nombre' => $input['nombre'],
            'descripcion' => isset($input['descripcion']) ? $input['descripcion'] : null,
            'permisos' => isset($input['permisos']) ? $input['permisos'] : array(),
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        ));
        
        $this->response(array('success' => true, 'message' => 'Rol creado', 'data' => array('id' => $id)), 201);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        if (!$this->Rol_model->get_by_id($id)) {
            $this->response(array('success' => false, 'message' => 'Rol no encontrado'), 404);
        }
        
        $input = $this->get_json_input();
        $data = array();
        foreach (array('nombre', 'descripcion', 'permisos', 'estado') as $campo) {
            if (isset($input[$campo])) $data[$campo] = $input[$campo];
        }
        
        if (!empty($data)) $this->Rol_model->update($id, $data);
        $this->response(array('success' => true, 'message' => 'Rol actualizado'));
    }
}

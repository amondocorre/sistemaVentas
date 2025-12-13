<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Marcas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Marca_model');
    }

    public function index()
    {
        $filters = array('estado' => $this->input->get('estado') !== null ? $this->input->get('estado') : 1);
        $this->response(array('success' => true, 'data' => $this->Marca_model->get_all($filters)));
    }

    public function show($id)
    {
        $marca = $this->Marca_model->get_by_id($id);
        if (!$marca) {
            $this->response(array('success' => false, 'message' => 'Marca no encontrada'), 404);
        }
        $this->response(array('success' => true, 'data' => $marca));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('productos_crear');
        $input = $this->get_json_input();
        
        if (empty($input['nombre'])) {
            $this->response(array('success' => false, 'message' => 'El nombre es requerido'), 400);
        }
        
        $id = $this->Marca_model->create(array(
            'nombre' => $input['nombre'],
            'descripcion' => isset($input['descripcion']) ? $input['descripcion'] : null,
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        ));
        
        $this->response(array('success' => true, 'message' => 'Marca creada', 'data' => array('id' => $id)), 201);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('productos_editar');
        
        if (!$this->Marca_model->get_by_id($id)) {
            $this->response(array('success' => false, 'message' => 'Marca no encontrada'), 404);
        }
        
        $input = $this->get_json_input();
        $data = array();
        foreach (array('nombre', 'descripcion', 'estado') as $campo) {
            if (isset($input[$campo])) $data[$campo] = $input[$campo];
        }
        
        if (!empty($data)) $this->Marca_model->update($id, $data);
        $this->response(array('success' => true, 'message' => 'Marca actualizada'));
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        $this->require_permission('productos_editar');
        
        if (!$this->Marca_model->get_by_id($id)) {
            $this->response(array('success' => false, 'message' => 'Marca no encontrada'), 404);
        }
        
        $this->Marca_model->delete($id);
        $this->response(array('success' => true, 'message' => 'Marca eliminada'));
    }
}

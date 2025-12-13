<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Sucursales extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Sucursal_model');
    }

    public function index()
    {
        $estado = $this->input->get('estado');

        // Si no viene el parámetro o viene vacío, por defecto mostrar sucursales activas (1)
        if ($estado === null || $estado === '') {
            $filters = array('estado' => 1);
        } else {
            $filters = array('estado' => $estado);
        }

        $sucursales = $this->Sucursal_model->get_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $sucursales
        ));
    }

    public function show($id)
    {
        $sucursal = $this->Sucursal_model->get_by_id($id);
        
        if (!$sucursal) {
            $this->response(array('success' => false, 'message' => 'Sucursal no encontrada'), 404);
        }
        
        $sucursal['total_usuarios'] = $this->Sucursal_model->count_usuarios($id);
        
        $this->response(array('success' => true, 'data' => $sucursal));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('sucursales');
        
        $input = $this->get_json_input();
        
        if (empty($input['nombre'])) {
            $this->response(array('success' => false, 'message' => 'El nombre es requerido'), 400);
        }
        
        $data = array(
            'nombre' => $input['nombre'],
            'direccion' => isset($input['direccion']) ? $input['direccion'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'email' => isset($input['email']) ? $input['email'] : null,
            'ciudad' => isset($input['ciudad']) ? $input['ciudad'] : null,
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        );
        
        $id = $this->Sucursal_model->create($data);
        
        $this->log_audit('crear_sucursal', 'sucursales', $id, null, $data);
        
        $this->response(array('success' => true, 'message' => 'Sucursal creada', 'data' => array('id' => $id)), 201);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('sucursales');
        
        $sucursal = $this->Sucursal_model->get_by_id($id);
        
        if (!$sucursal) {
            $this->response(array('success' => false, 'message' => 'Sucursal no encontrada'), 404);
        }
        
        $input = $this->get_json_input();
        $data = array();
        $campos = array('nombre', 'direccion', 'telefono', 'email', 'ciudad', 'estado');
        
        foreach ($campos as $campo) {
            if (isset($input[$campo])) {
                $data[$campo] = $input[$campo];
            }
        }
        
        if (!empty($data)) {
            $this->Sucursal_model->update($id, $data);
            $this->log_audit('actualizar_sucursal', 'sucursales', $id, $sucursal, $data);
        }
        
        $this->response(array('success' => true, 'message' => 'Sucursal actualizada'));
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('sucursales');
        
        $sucursal = $this->Sucursal_model->get_by_id($id);
        
        if (!$sucursal) {
            $this->response(array('success' => false, 'message' => 'Sucursal no encontrada'), 404);
        }
        
        $this->Sucursal_model->delete($id);
        $this->log_audit('eliminar_sucursal', 'sucursales', $id, $sucursal, null);
        
        $this->response(array('success' => true, 'message' => 'Sucursal eliminada'));
    }
}

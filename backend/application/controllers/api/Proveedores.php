<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Proveedores extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Proveedor_model');
    }

    public function index()
    {
        $this->require_permission('proveedores_ver');
        
        $filters = array(
            'estado' => $this->input->get('estado') !== null ? $this->input->get('estado') : 1,
            'search' => $this->input->get('search'),
            'limit' => $this->input->get('limit'),
            'offset' => $this->input->get('offset')
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $proveedores = $this->Proveedor_model->get_all($filters);
        $total = $this->Proveedor_model->count_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $proveedores,
            'total' => $total
        ));
    }

    public function show($id)
    {
        $this->require_permission('proveedores_ver');
        
        $proveedor = $this->Proveedor_model->get_by_id($id);
        
        if (!$proveedor) {
            $this->response(array(
                'success' => false,
                'message' => 'Proveedor no encontrado'
            ), 404);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $proveedor
        ));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('proveedores_crear');
        
        $input = $this->get_json_input();
        
        if (empty($input['nombre'])) {
            $this->response(array(
                'success' => false,
                'message' => 'El nombre es requerido'
            ), 400);
        }
        
        if (!empty($input['nit_ci'])) {
            if ($this->Proveedor_model->nit_exists($input['nit_ci'])) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El NIT/CI ya está registrado'
                ), 400);
            }
        }
        
        $data = array(
            'nombre' => $input['nombre'],
            'nit_ci' => isset($input['nit_ci']) ? $input['nit_ci'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'direccion' => isset($input['direccion']) ? $input['direccion'] : null,
            'email' => isset($input['email']) ? $input['email'] : null,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null,
            'estado' => 1
        );
        
        $id = $this->Proveedor_model->create($data);
        
        if ($id) {
            $this->log_audit('crear_proveedor', 'proveedores', $id, null, $data);
            
            $this->response(array(
                'success' => true,
                'message' => 'Proveedor creado exitosamente',
                'data' => array('id' => $id)
            ), 201);
        } else {
            $this->response(array(
                'success' => false,
                'message' => 'Error al crear el proveedor'
            ), 500);
        }
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('proveedores_editar');
        
        $proveedor = $this->Proveedor_model->get_by_id($id);
        
        if (!$proveedor) {
            $this->response(array(
                'success' => false,
                'message' => 'Proveedor no encontrado'
            ), 404);
        }
        
        $input = $this->get_json_input();
        
        if (!empty($input['nit_ci'])) {
            if ($this->Proveedor_model->nit_exists($input['nit_ci'], $id)) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El NIT/CI ya está registrado'
                ), 400);
            }
        }
        
        $data = array();
        
        if (isset($input['nombre'])) $data['nombre'] = $input['nombre'];
        if (isset($input['nit_ci'])) $data['nit_ci'] = $input['nit_ci'];
        if (isset($input['telefono'])) $data['telefono'] = $input['telefono'];
        if (isset($input['direccion'])) $data['direccion'] = $input['direccion'];
        if (isset($input['email'])) $data['email'] = $input['email'];
        if (isset($input['observaciones'])) $data['observaciones'] = $input['observaciones'];
        if (isset($input['estado'])) $data['estado'] = $input['estado'];
        
        if (empty($data)) {
            $this->response(array(
                'success' => false,
                'message' => 'No hay datos para actualizar'
            ), 400);
        }
        
        $success = $this->Proveedor_model->update($id, $data);
        
        if ($success) {
            $this->log_audit('actualizar_proveedor', 'proveedores', $id, $proveedor, $data);
            
            $this->response(array(
                'success' => true,
                'message' => 'Proveedor actualizado exitosamente'
            ));
        } else {
            $this->response(array(
                'success' => false,
                'message' => 'Error al actualizar el proveedor'
            ), 500);
        }
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('proveedores_editar');
        
        $proveedor = $this->Proveedor_model->get_by_id($id);
        
        if (!$proveedor) {
            $this->response(array(
                'success' => false,
                'message' => 'Proveedor no encontrado'
            ), 404);
        }
        
        $success = $this->Proveedor_model->delete($id);
        
        if ($success) {
            $this->log_audit('eliminar_proveedor', 'proveedores', $id, $proveedor, null);
            
            $this->response(array(
                'success' => true,
                'message' => 'Proveedor eliminado exitosamente'
            ));
        } else {
            $this->response(array(
                'success' => false,
                'message' => 'Error al eliminar el proveedor'
            ), 500);
        }
    }

    public function activos()
    {
        $this->require_permission('proveedores_ver');
        
        $proveedores = $this->Proveedor_model->get_activos();
        
        $this->response(array(
            'success' => true,
            'data' => $proveedores
        ));
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Clientes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Cliente_model');
    }

    public function index()
    {
        $filters = array(
            'estado' => $this->input->get('estado'),
            'search' => $this->input->get('search'),
        );

        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });

        $clientes = $this->Cliente_model->get_all($filters);

        $this->response(array(
            'success' => true,
            'data' => $clientes,
        ));
    }

    public function show($id)
    {
        $cliente = $this->Cliente_model->get_by_id($id);

        if (!$cliente) {
            $this->response(array(
                'success' => false,
                'message' => 'Cliente no encontrado',
            ), 404);
        }

        $this->response(array(
            'success' => true,
            'data' => $cliente,
        ));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $input = $this->get_json_input();

        if (empty($input['nombre'])) {
            $this->response(array(
                'success' => false,
                'message' => 'El nombre del cliente es requerido',
            ), 400);
        }

        $data = array(
            'nombre' => $input['nombre'],
            'nit_ci' => isset($input['nit_ci']) ? $input['nit_ci'] : null,
            'email' => isset($input['email']) ? $input['email'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'direccion' => isset($input['direccion']) ? $input['direccion'] : null,
            'estado' => isset($input['estado']) ? $input['estado'] : 1,
        );

        $id = $this->Cliente_model->create($data);

        $this->log_audit('crear_cliente', 'clientes', $id, null, $data);

        $this->response(array(
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data' => array('id' => $id),
        ), 201);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $cliente = $this->Cliente_model->get_by_id($id);

        if (!$cliente) {
            $this->response(array(
                'success' => false,
                'message' => 'Cliente no encontrado',
            ), 404);
        }

        $input = $this->get_json_input();

        $data = array();
        $campos = array('nombre', 'nit_ci', 'email', 'telefono', 'direccion', 'estado');

        foreach ($campos as $campo) {
            if (isset($input[$campo])) {
                $data[$campo] = $input[$campo];
            }
        }

        if (!empty($data)) {
            $this->Cliente_model->update($id, $data);
            $this->log_audit('actualizar_cliente', 'clientes', $id, $cliente, $data);
        }

        $this->response(array(
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
        ));
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $cliente = $this->Cliente_model->get_by_id($id);

        if (!$cliente) {
            $this->response(array(
                'success' => false,
                'message' => 'Cliente no encontrado',
            ), 404);
        }

        $this->Cliente_model->delete($id);
        $this->log_audit('eliminar_cliente', 'clientes', $id, $cliente, null);

        $this->response(array(
            'success' => true,
            'message' => 'Cliente eliminado exitosamente',
        ));
    }
}

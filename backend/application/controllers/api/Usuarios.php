<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Usuarios
 */
class Usuarios extends MY_Controller
{
    protected $allowed_roles = array('admin');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuario_model');
    }

    /**
     * GET /api/usuarios
     */
    public function index()
    {
        $filters = array(
            'estado' => $this->input->get('estado'),
            'id_sucursal' => $this->input->get('id_sucursal'),
            'id_rol' => $this->input->get('id_rol'),
            'search' => $this->input->get('search')
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $usuarios = $this->Usuario_model->get_all($filters);
        
        // Limpiar datos sensibles
        foreach ($usuarios as &$user) {
            unset($user['password']);
            unset($user['refresh_token']);
            unset($user['refresh_token_expires']);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $usuarios
        ));
    }

    /**
     * GET /api/usuarios/:id
     */
    public function show($id)
    {
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado'
            ), 404);
        }
        
        unset($usuario['password']);
        unset($usuario['refresh_token']);
        unset($usuario['refresh_token_expires']);
        
        $this->response(array(
            'success' => true,
            'data' => $usuario
        ));
    }

    /**
     * POST /api/usuarios
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $input = $this->get_json_input();
        
        // Validar campos requeridos
        if (empty($input['nombre']) || empty($input['email']) || empty($input['password']) || empty($input['id_rol'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Nombre, email, contraseña y rol son requeridos'
            ), 400);
        }
        
        // Verificar email único
        if ($this->Usuario_model->email_exists($input['email'])) {
            $this->response(array(
                'success' => false,
                'message' => 'El email ya está registrado'
            ), 400);
        }
        
        $data = array(
            'nombre' => $input['nombre'],
            'email' => $input['email'],
            'password' => $input['password'],
            'id_rol' => $input['id_rol'],
            'id_sucursal' => isset($input['id_sucursal']) ? $input['id_sucursal'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        );
        
        $id = $this->Usuario_model->create($data);
        
        $this->log_audit('crear_usuario', 'usuarios', $id, null, array_diff_key($data, array('password' => '')));
        
        $this->response(array(
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => array('id' => $id)
        ), 201);
    }

    /**
     * PUT /api/usuarios/:id
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado'
            ), 404);
        }
        
        $input = $this->get_json_input();
        
        // Verificar email único
        if (!empty($input['email']) && $input['email'] !== $usuario['email']) {
            if ($this->Usuario_model->email_exists($input['email'], $id)) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El email ya está registrado'
                ), 400);
            }
        }
        
        $data = array();
        $campos = array('nombre', 'email', 'password', 'id_rol', 'id_sucursal', 'telefono', 'estado');
        
        foreach ($campos as $campo) {
            if (isset($input[$campo])) {
                $data[$campo] = $input[$campo];
            }
        }
        
        if (!empty($data)) {
            $this->Usuario_model->update($id, $data);
            $this->log_audit('actualizar_usuario', 'usuarios', $id, $usuario, array_diff_key($data, array('password' => '')));
        }
        
        $this->response(array(
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ));
    }

    /**
     * DELETE /api/usuarios/:id
     */
    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        // No permitir eliminar el propio usuario
        if ($id == $this->user['id']) {
            $this->response(array(
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario'
            ), 400);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado'
            ), 404);
        }
        
        $this->Usuario_model->delete($id);
        $this->log_audit('eliminar_usuario', 'usuarios', $id, $usuario, null);
        
        $this->response(array(
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ));
    }

    public function reset_password($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }

        $usuario = $this->Usuario_model->get_by_id($id);
        if (!$usuario) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado'
            ), 404);
        }

        $tempPassword = $this->generate_temp_password();

        $this->Usuario_model->update($id, array(
            'password' => $tempPassword,
        ));

        $this->log_audit('reset_password_usuario', 'usuarios', $id, $usuario, array('password' => '***'));

        $this->response(array(
            'success' => true,
            'message' => 'Contraseña reseteada. Entrega la contraseña temporal al usuario y recomienda cambiarla al ingresar.',
            'data' => array(
                'id_usuario' => (int)$id,
                'password_temporal' => $tempPassword,
            )
        ));
    }

    protected function generate_temp_password($length = 10)
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Autenticación
 * Maneja login, logout, refresh token y datos del usuario
 */
class Auth extends Public_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuario_model');
    }

    /**
     * POST /api/auth/login
     * Inicia sesión y retorna tokens
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $input = $this->get_json_input();
        
        // Validar campos requeridos
        $usuario = isset($input['usuario']) ? trim((string)$input['usuario']) : '';

        if (empty($usuario) || empty($input['password'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario y contraseña son requeridos'
            ), 400);
        }

        // Verificar credenciales
        $user = $this->Usuario_model->verify_credentials($usuario, $input['password']);
        
        if (!$user) {
            $this->response(array(
                'success' => false,
                'message' => 'Credenciales inválidas'
            ), 401);
        }

        // Generar tokens
        $access_token = $this->jwt->generate_access_token($user);
        $refresh_token = $this->jwt->generate_refresh_token($user['id']);
        
        // Guardar refresh token en BD
        $this->Usuario_model->save_refresh_token(
            $user['id'],
            $refresh_token,
            $this->jwt->get_refresh_expire_time()
        );
        
        // Actualizar último login
        $this->Usuario_model->update_last_login($user['id']);

        // Preparar datos del usuario (sin password)
        unset($user['password']);
        unset($user['refresh_token']);
        unset($user['refresh_token_expires']);

        $this->response(array(
            'success' => true,
            'message' => 'Login exitoso',
            'data' => array(
                'user' => $user,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'token_type' => 'Bearer',
                // Leer directamente la config definida en application/config/jwt.php
                'expires_in' => (int) $this->config->item('jwt_access_token_expire')
            )
        ));
    }

    /**
     * POST /api/auth/refresh
     * Renueva el access token usando el refresh token
     */
    public function refresh()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $input = $this->get_json_input();
        
        if (empty($input['refresh_token'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Refresh token requerido'
            ), 400);
        }

        // Validar refresh token
        $result = $this->jwt->validate_refresh_token($input['refresh_token']);
        
        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 401);
        }

        // Verificar que el token esté en la BD
        $user_db = $this->Usuario_model->verify_refresh_token($result['user_id'], $input['refresh_token']);
        
        if (!$user_db) {
            $this->response(array(
                'success' => false,
                'message' => 'Refresh token inválido o expirado'
            ), 401);
        }

        // Obtener datos completos del usuario
        $user = $this->Usuario_model->get_by_id($result['user_id']);
        
        if (!$user || $user['estado'] != 1) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado o inactivo'
            ), 401);
        }

        // Generar nuevo access token
        $access_token = $this->jwt->generate_access_token($user);
        
        // Generar nuevo refresh token
        $new_refresh_token = $this->jwt->generate_refresh_token($user['id']);
        
        // Actualizar refresh token en BD
        $this->Usuario_model->save_refresh_token(
            $user['id'],
            $new_refresh_token,
            $this->jwt->get_refresh_expire_time()
        );

        $this->response(array(
            'success' => true,
            'message' => 'Token renovado exitosamente',
            'data' => array(
                'access_token' => $access_token,
                'refresh_token' => $new_refresh_token,
                'token_type' => 'Bearer',
                // Leer directamente la config definida en application/config/jwt.php
                'expires_in' => (int) $this->config->item('jwt_access_token_expire')
            )
        ));
    }

    /**
     * POST /api/auth/logout
     * Cierra sesión invalidando el refresh token
     */
    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        // Obtener token del header
        $token = $this->get_bearer_token();
        
        if ($token) {
            $result = $this->jwt->validate_access_token($token);
            
            if ($result['success']) {
                // Invalidar refresh token
                $this->Usuario_model->invalidate_refresh_token($result['user']['id']);
            }
        }

        $this->response(array(
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ));
    }

    /**
     * GET /api/auth/me
     * Obtiene los datos del usuario autenticado
     */
    public function me()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        // Autenticar manualmente para este endpoint
        $token = $this->get_bearer_token();
        
        if (!$token) {
            $this->response(array(
                'success' => false,
                'message' => 'Token requerido'
            ), 401);
        }

        $result = $this->jwt->validate_access_token($token);
        
        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 401);
        }

        // Obtener datos actualizados del usuario
        $user = $this->Usuario_model->get_by_id($result['user']['id']);
        
        if (!$user) {
            $this->response(array(
                'success' => false,
                'message' => 'Usuario no encontrado'
            ), 404);
        }

        // Limpiar datos sensibles
        unset($user['password']);
        unset($user['refresh_token']);
        unset($user['refresh_token_expires']);

        $this->response(array(
            'success' => true,
            'data' => $user
        ));
    }

    /**
     * POST /api/auth/change-password
     * Cambia la contraseña del usuario
     */
    public function change_password()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        // Autenticar
        $token = $this->get_bearer_token();
        
        if (!$token) {
            $this->response(array('success' => false, 'message' => 'Token requerido'), 401);
        }

        $result = $this->jwt->validate_access_token($token);
        
        if (!$result['success']) {
            $this->response(array('success' => false, 'message' => $result['message']), 401);
        }

        $input = $this->get_json_input();
        
        if (empty($input['current_password']) || empty($input['new_password'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Contraseña actual y nueva son requeridas'
            ), 400);
        }

        // Verificar contraseña actual
        $user = $this->Usuario_model->get_by_id($result['user']['id']);
        
        if (!password_verify($input['current_password'], $user['password'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Contraseña actual incorrecta'
            ), 400);
        }

        // Actualizar contraseña
        $this->Usuario_model->update($user['id'], array(
            'password' => $input['new_password']
        ));

        $this->response(array(
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ));
    }
}

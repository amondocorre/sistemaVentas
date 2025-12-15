<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base Controller para API REST
 */
class MY_Controller extends CI_Controller
{
    protected $user = null;
    protected $require_auth = true;
    protected $allowed_roles = array();

    public function __construct()
    {
        parent::__construct();
        
        // Configurar respuesta JSON
        header('Content-Type: application/json; charset=UTF-8');
        
        // Cargar librería JWT
        $this->load->library('JWT_Library', null, 'jwt');
        
        // Validar autenticación si es requerida
        if ($this->require_auth) {
            $this->authenticate();
        }
    }

    /**
     * Autentica el request usando JWT
     */
    protected function authenticate()
    {
        $token = $this->get_bearer_token();
        
        if (!$token) {
            $this->response(array(
                'success' => false,
                'message' => 'Token de autenticación requerido'
            ), 401);
            return;
        }

        $result = $this->jwt->validate_access_token($token);
        
        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 401);
            return;
        }

        $this->user = $result['user'];

        // Verificar roles permitidos
        if (!empty($this->allowed_roles) && !in_array($this->user['rol'], $this->allowed_roles)) {
            $this->response(array(
                'success' => false,
                'message' => 'No tienes permisos para acceder a este recurso'
            ), 403);
            return;
        }
    }

    /**
     * Obtiene el token Bearer del header Authorization
     */
    protected function get_bearer_token()
    {
        $headers = $this->get_authorization_header();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Obtiene el header Authorization
     */
    protected function get_authorization_header()
    {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $request_headers = apache_request_headers();
            $request_headers = array_combine(
                array_map('ucwords', array_keys($request_headers)),
                array_values($request_headers)
            );
            if (isset($request_headers['Authorization'])) {
                $headers = trim($request_headers['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * Envía respuesta JSON
     */
    protected function response($data, $status_code = 200)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        http_response_code($status_code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    /**
     * Obtiene datos JSON del body del request
     */
    protected function get_json_input()
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?: array();
    }

    /**
     * Verifica si el usuario tiene un permiso específico
     */
    protected function has_permission($permission)
    {
        if (!$this->user || !isset($this->user['permisos'])) {
            return false;
        }
        
        return isset($this->user['permisos'][$permission]) && $this->user['permisos'][$permission] === true;
    }

    /**
     * Requiere un permiso específico
     */
    protected function require_permission($permission)
    {
        if (!$this->has_permission($permission)) {
            $this->response(array(
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción'
            ), 403);
        }
    }

    /**
     * Verifica si el usuario es admin
     */
    protected function is_admin()
    {
        return $this->user && $this->user['rol'] === 'admin';
    }

    /**
     * Verifica si el usuario es supervisor
     */
    protected function is_supervisor()
    {
        return $this->user && $this->user['rol'] === 'supervisor';
    }

    /**
     * Verifica si el usuario es cajero
     */
    protected function is_cajero()
    {
        return $this->user && $this->user['rol'] === 'cajero';
    }

    /**
     * Registra una acción en la auditoría
     */
    protected function log_audit($accion, $tabla = null, $id_registro = null, $datos_anteriores = null, $datos_nuevos = null)
    {
        $this->load->model('Auditoria_model');
        
        $this->Auditoria_model->registrar(array(
            'id_usuario' => $this->user ? $this->user['id'] : null,
            'accion' => $accion,
            'tabla' => $tabla,
            'id_registro' => $id_registro,
            'datos_anteriores' => $datos_anteriores ? json_encode($datos_anteriores) : null,
            'datos_nuevos' => $datos_nuevos ? json_encode($datos_nuevos) : null,
            'ip' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent()
        ));
    }
}

/**
 * Controller público (sin autenticación requerida)
 */
class Public_Controller extends MY_Controller
{
    protected $require_auth = false;
}

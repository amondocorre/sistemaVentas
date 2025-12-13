<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Usuario_model extends CI_Model
{
    protected $table = 'usuarios';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene todos los usuarios con sus relaciones
     */
    public function get_all($filters = array())
    {
        $this->db->select('u.*, r.nombre as rol, s.nombre as sucursal');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.id_rol', 'left');
        $this->db->join('sucursales s', 's.id = u.id_sucursal', 'left');
        
        if (isset($filters['estado'])) {
            $this->db->where('u.estado', $filters['estado']);
        }
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('u.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['id_rol'])) {
            $this->db->where('u.id_rol', $filters['id_rol']);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('u.nombre', $filters['search']);
            $this->db->or_like('u.email', $filters['search']);
            $this->db->group_end();
        }
        
        $this->db->order_by('u.nombre', 'ASC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene un usuario por ID
     */
    public function get_by_id($id)
    {
        $this->db->select('u.*, r.nombre as rol, r.permisos, s.nombre as sucursal');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.id_rol', 'left');
        $this->db->join('sucursales s', 's.id = u.id_sucursal', 'left');
        $this->db->where('u.id', $id);
        
        $user = $this->db->get()->row_array();
        
        if ($user && $user['permisos']) {
            $user['permisos'] = json_decode($user['permisos'], true);
        }
        
        return $user;
    }

    /**
     * Obtiene un usuario por email
     */
    public function get_by_email($email)
    {
        $this->db->select('u.*, r.nombre as rol, r.permisos, s.nombre as sucursal');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.id_rol', 'left');
        $this->db->join('sucursales s', 's.id = u.id_sucursal', 'left');
        $this->db->where('u.email', $email);
        
        $user = $this->db->get()->row_array();
        
        if ($user && $user['permisos']) {
            $user['permisos'] = json_decode($user['permisos'], true);
        }
        
        return $user;
    }

    /**
     * Crea un nuevo usuario
     */
    public function create($data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Actualiza un usuario
     */
    public function update($id, $data)
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Elimina (desactiva) un usuario
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('estado' => 0));
    }

    /**
     * Verifica credenciales
     */
    public function verify_credentials($email, $password)
    {
        $user = $this->get_by_email($email);
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        if ($user['estado'] != 1) {
            return false;
        }
        
        return $user;
    }

    /**
     * Actualiza el último login
     */
    public function update_last_login($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('ultimo_login' => date('Y-m-d H:i:s')));
    }

    /**
     * Guarda el refresh token
     */
    public function save_refresh_token($id, $token, $expires)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array(
            'refresh_token' => $token,
            'refresh_token_expires' => date('Y-m-d H:i:s', $expires)
        ));
    }

    /**
     * Invalida el refresh token
     */
    public function invalidate_refresh_token($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array(
            'refresh_token' => null,
            'refresh_token_expires' => null
        ));
    }

    /**
     * Verifica si el refresh token es válido
     */
    public function verify_refresh_token($id, $token)
    {
        $this->db->where('id', $id);
        $this->db->where('refresh_token', $token);
        $this->db->where('refresh_token_expires >', date('Y-m-d H:i:s'));
        
        return $this->db->get($this->table)->row_array();
    }

    /**
     * Verifica si el email ya existe
     */
    public function email_exists($email, $exclude_id = null)
    {
        $this->db->where('email', $email);
        
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        
        return $this->db->count_all_results($this->table) > 0;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rol_model extends CI_Model
{
    protected $table = 'roles';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all($filters = array())
    {
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        
        $this->db->order_by('nombre', 'ASC');
        
        $roles = $this->db->get($this->table)->result_array();
        
        foreach ($roles as &$rol) {
            if ($rol['permisos']) {
                $rol['permisos'] = json_decode($rol['permisos'], true);
            }
        }
        
        return $roles;
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        $rol = $this->db->get($this->table)->row_array();
        
        if ($rol && $rol['permisos']) {
            $rol['permisos'] = json_decode($rol['permisos'], true);
        }
        
        return $rol;
    }

    public function create($data)
    {
        if (isset($data['permisos']) && is_array($data['permisos'])) {
            $data['permisos'] = json_encode($data['permisos']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        if (isset($data['permisos']) && is_array($data['permisos'])) {
            $data['permisos'] = json_encode($data['permisos']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
}

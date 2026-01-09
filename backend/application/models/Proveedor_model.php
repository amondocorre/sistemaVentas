<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Proveedor_model extends CI_Model
{
    protected $table = 'proveedores';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all($filters = array())
    {
        $this->db->select('*');
        $this->db->from($this->table);
        
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('nombre', $filters['search']);
            $this->db->or_like('nit_ci', $filters['search']);
            $this->db->or_like('telefono', $filters['search']);
            $this->db->or_like('email', $filters['search']);
            $this->db->group_end();
        }
        
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('nombre', 'ASC');
        
        return $this->db->get()->result_array();
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table)->row_array();
    }

    public function create($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('estado' => 0));
    }

    public function count_all($filters = array())
    {
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('nombre', $filters['search']);
            $this->db->or_like('nit_ci', $filters['search']);
            $this->db->or_like('telefono', $filters['search']);
            $this->db->or_like('email', $filters['search']);
            $this->db->group_end();
        }
        
        return $this->db->count_all_results($this->table);
    }

    public function get_activos()
    {
        $this->db->where('estado', 1);
        $this->db->order_by('nombre', 'ASC');
        return $this->db->get($this->table)->result_array();
    }

    public function nit_exists($nit, $exclude_id = null)
    {
        if (empty($nit)) {
            return false;
        }
        
        $this->db->where('nit_ci', $nit);
        
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        
        return $this->db->count_all_results($this->table) > 0;
    }
}

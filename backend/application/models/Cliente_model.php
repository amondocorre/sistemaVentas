<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cliente_model extends CI_Model
{
    protected $table = 'clientes';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all($filters = array())
    {
        $this->db->from($this->table);

        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }

        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('nombre', $filters['search']);
            $this->db->or_like('nit_ci', $filters['search']);
            $this->db->or_like('email', $filters['search']);
            $this->db->group_end();
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
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sucursal_model extends CI_Model
{
    protected $table = 'sucursales';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene todas las sucursales
     */
    public function get_all($filters = array())
    {
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        
        $this->db->order_by('nombre', 'ASC');
        
        return $this->db->get($this->table)->result_array();
    }

    /**
     * Obtiene una sucursal por ID
     */
    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table)->row_array();
    }

    /**
     * Crea una nueva sucursal
     */
    public function create($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Actualiza una sucursal
     */
    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Elimina (desactiva) una sucursal
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('estado' => 0));
    }

    /**
     * Cuenta usuarios por sucursal
     */
    public function count_usuarios($id)
    {
        $this->db->where('id_sucursal', $id);
        $this->db->where('estado', 1);
        return $this->db->count_all_results('usuarios');
    }
}

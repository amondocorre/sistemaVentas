<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MetodoPago_model extends CI_Model
{
    protected $table = 'metodos_pago';

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
        
        $metodos = $this->db->get($this->table)->result_array();
        
        // Decodificar configuración JSON
        foreach ($metodos as &$metodo) {
            if ($metodo['configuracion']) {
                $metodo['configuracion'] = json_decode($metodo['configuracion'], true);
            }
        }
        
        return $metodos;
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        $metodo = $this->db->get($this->table)->row_array();
        
        if ($metodo && $metodo['configuracion']) {
            $metodo['configuracion'] = json_decode($metodo['configuracion'], true);
        }
        
        return $metodo;
    }

    public function create($data)
    {
        if (isset($data['configuracion']) && is_array($data['configuracion'])) {
            $data['configuracion'] = json_encode($data['configuracion']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        if (isset($data['configuracion']) && is_array($data['configuracion'])) {
            $data['configuracion'] = json_encode($data['configuracion']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('estado' => 0));
    }

    public function update_qr($id, $imagen)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('imagen_qr' => $imagen));
    }
}

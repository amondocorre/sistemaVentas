<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class VentaCobro_model extends CI_Model
{
    protected $table = 'ventas_cobros';

    public function __construct()
    {
        parent::__construct();
    }

    public function create($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function get_by_venta($id_venta)
    {
        $this->db->select('vc.*, u.nombre as usuario, mp.nombre as metodo_pago');
        $this->db->from($this->table . ' vc');
        $this->db->join('usuarios u', 'u.id = vc.id_usuario', 'left');
        $this->db->join('metodos_pago mp', 'mp.id = vc.id_metodo_pago', 'left');
        $this->db->where('vc.id_venta', $id_venta);
        $this->db->order_by('vc.created_at', 'DESC');
        return $this->db->get()->result_array();
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auditoria_model extends CI_Model
{
    protected $table = 'auditoria';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Registra una acción en la auditoría
     */
    public function registrar($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Obtiene registros de auditoría
     */
    public function get_all($filters = array())
    {
        $this->db->select('a.*, u.nombre as usuario, u.email');
        $this->db->from($this->table . ' a');
        $this->db->join('usuarios u', 'u.id = a.id_usuario', 'left');
        
        if (isset($filters['id_usuario'])) {
            $this->db->where('a.id_usuario', $filters['id_usuario']);
        }
        
        if (isset($filters['accion'])) {
            $this->db->where('a.accion', $filters['accion']);
        }
        
        if (isset($filters['tabla'])) {
            $this->db->where('a.tabla', $filters['tabla']);
        }
        
        if (isset($filters['fecha_inicio'])) {
            $this->db->where('DATE(a.created_at) >=', $filters['fecha_inicio']);
        }
        
        if (isset($filters['fecha_fin'])) {
            $this->db->where('DATE(a.created_at) <=', $filters['fecha_fin']);
        }
        
        // Paginación
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('a.created_at', 'DESC');
        
        $registros = $this->db->get()->result_array();
        
        foreach ($registros as &$registro) {
            if ($registro['datos_anteriores']) {
                $registro['datos_anteriores'] = json_decode($registro['datos_anteriores'], true);
            }
            if ($registro['datos_nuevos']) {
                $registro['datos_nuevos'] = json_decode($registro['datos_nuevos'], true);
            }
        }
        
        return $registros;
    }
}

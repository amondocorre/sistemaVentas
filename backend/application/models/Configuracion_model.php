<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Configuracion_model extends CI_Model
{
    protected $table = 'configuracion';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene todas las configuraciones
     */
    public function get_all()
    {
        $configs = $this->db->get($this->table)->result_array();
        
        $result = array();
        foreach ($configs as $config) {
            $value = $config['valor'];
            
            // Convertir según tipo
            switch ($config['tipo']) {
                case 'number':
                    $value = is_numeric($value) ? (float)$value : 0;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $result[$config['clave']] = $value;
        }
        
        return $result;
    }

    /**
     * Obtiene una configuración por clave
     */
    public function get($clave)
    {
        $this->db->where('clave', $clave);
        $config = $this->db->get($this->table)->row_array();
        
        if (!$config) {
            return null;
        }
        
        $value = $config['valor'];
        
        switch ($config['tipo']) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Actualiza o crea una configuración
     */
    public function set($clave, $valor, $tipo = 'string')
    {
        if ($tipo === 'json' && is_array($valor)) {
            $valor = json_encode($valor);
        } elseif ($tipo === 'boolean') {
            $valor = $valor ? '1' : '0';
        }
        
        $this->db->where('clave', $clave);
        $exists = $this->db->count_all_results($this->table) > 0;
        
        if ($exists) {
            $this->db->where('clave', $clave);
            return $this->db->update($this->table, array(
                'valor' => $valor,
                'tipo' => $tipo,
                'updated_at' => date('Y-m-d H:i:s')
            ));
        } else {
            return $this->db->insert($this->table, array(
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => $tipo
            ));
        }
    }

    /**
     * Actualiza múltiples configuraciones
     */
    public function update_multiple($configs)
    {
        foreach ($configs as $clave => $valor) {
            $tipo = 'string';
            
            if (is_numeric($valor)) {
                $tipo = 'number';
            } elseif (is_bool($valor)) {
                $tipo = 'boolean';
            } elseif (is_array($valor)) {
                $tipo = 'json';
            }
            
            $this->set($clave, $valor, $tipo);
        }
        
        return true;
    }
}

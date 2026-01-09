<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Compra_model extends CI_Model
{
    protected $table = 'compras';
    protected $table_detalle = 'compras_detalle';
    protected $table_pagos = 'compras_pagos';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Inventario_model');
    }

    public function get_all($filters = array())
    {
        $this->db->select('c.*, p.nombre as proveedor, u.nombre as usuario, s.nombre as sucursal');
        $this->db->from($this->table . ' c');
        $this->db->join('proveedores p', 'p.id = c.id_proveedor', 'left');
        $this->db->join('usuarios u', 'u.id = c.id_usuario', 'left');
        $this->db->join('sucursales s', 's.id = c.id_sucursal', 'left');
        
        if (isset($filters['estado'])) {
            $this->db->where('c.estado', $filters['estado']);
        }
        
        if (isset($filters['id_proveedor'])) {
            $this->db->where('c.id_proveedor', $filters['id_proveedor']);
        }
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('c.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['tipo_pago'])) {
            $this->db->where('c.tipo_pago', $filters['tipo_pago']);
        }
        
        if (isset($filters['estado_pago'])) {
            $this->db->where('c.estado_pago', $filters['estado_pago']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('DATE(c.fecha_compra) >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('DATE(c.fecha_compra) <=', $filters['fecha_hasta']);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('c.numero_compra', $filters['search']);
            $this->db->or_like('p.nombre', $filters['search']);
            $this->db->group_end();
        }
        
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('c.fecha_compra', 'DESC');
        
        return $this->db->get()->result_array();
    }

    public function get_by_id($id)
    {
        $this->db->select('c.*, p.nombre as proveedor, p.nit_ci, p.telefono as proveedor_telefono, 
                          u.nombre as usuario, s.nombre as sucursal');
        $this->db->from($this->table . ' c');
        $this->db->join('proveedores p', 'p.id = c.id_proveedor', 'left');
        $this->db->join('usuarios u', 'u.id = c.id_usuario', 'left');
        $this->db->join('sucursales s', 's.id = c.id_sucursal', 'left');
        $this->db->where('c.id', $id);
        
        $compra = $this->db->get()->row_array();
        
        if ($compra) {
            $compra['detalle'] = $this->get_detalle($id);
            $compra['pagos'] = $this->get_pagos($id);
        }
        
        return $compra;
    }

    public function get_detalle($id_compra)
    {
        $this->db->select('cd.*, p.nombre as producto, p.codigo_barras');
        $this->db->from($this->table_detalle . ' cd');
        $this->db->join('productos p', 'p.id = cd.id_producto', 'left');
        $this->db->where('cd.id_compra', $id_compra);
        $this->db->order_by('cd.id', 'ASC');
        
        return $this->db->get()->result_array();
    }

    public function get_pagos($id_compra)
    {
        $this->db->select('cp.*, u.nombre as usuario');
        $this->db->from($this->table_pagos . ' cp');
        $this->db->join('usuarios u', 'u.id = cp.id_usuario', 'left');
        $this->db->where('cp.id_compra', $id_compra);
        $this->db->order_by('cp.fecha_pago', 'DESC');
        
        return $this->db->get()->result_array();
    }

    public function create($data)
    {
        $this->db->trans_start();
        
        $numero_compra = $this->generar_numero_compra();
        
        $compra_data = array(
            'numero_compra' => $numero_compra,
            'id_proveedor' => $data['id_proveedor'],
            'id_usuario' => $data['id_usuario'],
            'id_sucursal' => $data['id_sucursal'],
            'fecha_compra' => isset($data['fecha_compra']) ? $data['fecha_compra'] : date('Y-m-d H:i:s'),
            'tipo_pago' => $data['tipo_pago'],
            'subtotal' => $data['subtotal'],
            'total' => $data['total'],
            'monto_pagado' => isset($data['monto_pagado']) ? $data['monto_pagado'] : 0,
            'saldo_pendiente' => $data['total'] - (isset($data['monto_pagado']) ? $data['monto_pagado'] : 0),
            'observaciones' => isset($data['observaciones']) ? $data['observaciones'] : null,
            'created_at' => date('Y-m-d H:i:s')
        );
        
        if ($data['tipo_pago'] === 'contado') {
            if (!empty($data['pagos_contado'])) {
                $total_pagado = 0;
                foreach ($data['pagos_contado'] as $pago) {
                    $total_pagado += $pago['monto'];
                }
                $compra_data['monto_pagado'] = $total_pagado;
                $compra_data['saldo_pendiente'] = max(0, $data['total'] - $total_pagado);
                $compra_data['estado_pago'] = $compra_data['saldo_pendiente'] <= 0 ? 'pagado' : 'parcial';
            } else {
                $compra_data['monto_pagado'] = $data['total'];
                $compra_data['saldo_pendiente'] = 0;
                $compra_data['estado_pago'] = 'pagado';
            }
        } else {
            if ($compra_data['monto_pagado'] >= $data['total']) {
                $compra_data['estado_pago'] = 'pagado';
                $compra_data['saldo_pendiente'] = 0;
            } elseif ($compra_data['monto_pagado'] > 0) {
                $compra_data['estado_pago'] = 'parcial';
            } else {
                $compra_data['estado_pago'] = 'pendiente';
            }
        }
        
        $this->db->insert($this->table, $compra_data);
        $id_compra = $this->db->insert_id();
        
        foreach ($data['detalle'] as $item) {
            $detalle_data = array(
                'id_compra' => $id_compra,
                'id_producto' => $item['id_producto'],
                'cantidad' => $item['cantidad'],
                'costo_unitario' => $item['costo_unitario'],
                'subtotal' => $item['subtotal'],
                'created_at' => date('Y-m-d H:i:s')
            );
            
            $this->db->insert($this->table_detalle, $detalle_data);
            
            $this->actualizar_inventario_y_costo($item['id_producto'], $item['cantidad'], 
                                                 $item['costo_unitario'], $data['id_sucursal'], 
                                                 $data['id_usuario'], $id_compra);
        }
        
        $pagos_iniciales = array();
        if ($data['tipo_pago'] === 'contado') {
            if (!empty($data['pagos_contado'])) {
                foreach ($data['pagos_contado'] as $pago) {
                    $pagos_iniciales[] = array(
                        'monto' => $pago['monto'],
                        'metodo_pago' => $pago['metodo_pago'],
                        'referencia' => isset($pago['referencia']) ? $pago['referencia'] : null,
                        'observaciones' => isset($pago['observaciones']) ? $pago['observaciones'] : 'Pago al contado'
                    );
                }
            } else {
                $pagos_iniciales[] = array(
                    'monto' => $compra_data['monto_pagado'],
                    'metodo_pago' => isset($data['metodo_pago']) ? $data['metodo_pago'] : 'Efectivo',
                    'observaciones' => 'Pago al contado'
                );
            }
        } elseif (isset($data['monto_pagado']) && $data['monto_pagado'] > 0) {
            $pagos_iniciales[] = array(
                'monto' => $data['monto_pagado'],
                'metodo_pago' => isset($data['metodo_pago']) ? $data['metodo_pago'] : null,
                'observaciones' => 'Pago inicial'
            );
        }

        foreach ($pagos_iniciales as $pago_inicial) {
            $pago_data = array(
                'id_compra' => $id_compra,
                'id_usuario' => $data['id_usuario'],
                'monto' => $pago_inicial['monto'],
                'fecha_pago' => date('Y-m-d H:i:s'),
                'metodo_pago' => $pago_inicial['metodo_pago'],
                'referencia' => isset($pago_inicial['referencia']) ? $pago_inicial['referencia'] : null,
                'observaciones' => $pago_inicial['observaciones'],
                'created_at' => date('Y-m-d H:i:s')
            );

            $this->db->insert($this->table_pagos, $pago_data);
        }
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return false;
        }
        
        return $id_compra;
    }

    public function registrar_pago($id_compra, $data)
    {
        $this->db->trans_start();
        
        $compra = $this->get_by_id($id_compra);
        
        if (!$compra) {
            return false;
        }
        
        $pago_data = array(
            'id_compra' => $id_compra,
            'id_usuario' => $data['id_usuario'],
            'monto' => $data['monto'],
            'fecha_pago' => isset($data['fecha_pago']) ? $data['fecha_pago'] : date('Y-m-d H:i:s'),
            'metodo_pago' => isset($data['metodo_pago']) ? $data['metodo_pago'] : null,
            'referencia' => isset($data['referencia']) ? $data['referencia'] : null,
            'observaciones' => isset($data['observaciones']) ? $data['observaciones'] : null,
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->insert($this->table_pagos, $pago_data);
        
        $nuevo_monto_pagado = $compra['monto_pagado'] + $data['monto'];
        $nuevo_saldo = $compra['total'] - $nuevo_monto_pagado;
        
        if ($nuevo_saldo <= 0) {
            $estado_pago = 'pagado';
            $nuevo_saldo = 0;
        } elseif ($nuevo_monto_pagado > 0) {
            $estado_pago = 'parcial';
        } else {
            $estado_pago = 'pendiente';
        }
        
        $this->db->where('id', $id_compra);
        $this->db->update($this->table, array(
            'monto_pagado' => $nuevo_monto_pagado,
            'saldo_pendiente' => $nuevo_saldo,
            'estado_pago' => $estado_pago,
            'updated_at' => date('Y-m-d H:i:s')
        ));
        
        $this->db->trans_complete();
        
        return $this->db->trans_status() !== FALSE;
    }

    private function actualizar_inventario_y_costo($id_producto, $cantidad, $costo_unitario, $id_sucursal, $id_usuario, $id_compra)
    {
        $producto = $this->db->where('id', $id_producto)->get('productos')->row_array();
        
        if (!$producto) {
            return false;
        }
        
        $costo_anterior = $producto['precio_compra'];
        
        if ($costo_anterior != $costo_unitario) {
            $this->db->insert('productos_historial_costos', array(
                'id_producto' => $id_producto,
                'id_compra' => $id_compra,
                'costo_anterior' => $costo_anterior,
                'costo_nuevo' => $costo_unitario,
                'cantidad_comprada' => $cantidad,
                'id_usuario' => $id_usuario,
                'created_at' => date('Y-m-d H:i:s')
            ));
            
            $this->db->where('id', $id_producto);
            $this->db->update('productos', array(
                'precio_compra' => $costo_unitario,
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }
        
        $stock_actual = $this->Inventario_model->get_stock($id_producto, $id_sucursal);
        
        $this->Inventario_model->actualizar_stock($id_producto, $id_sucursal, $stock_actual + $cantidad);
        
        $this->db->insert('movimientos_inventario', array(
            'id_producto' => $id_producto,
            'id_sucursal' => $id_sucursal,
            'id_usuario' => $id_usuario,
            'tipo' => 'entrada',
            'cantidad' => $cantidad,
            'stock_anterior' => $stock_actual,
            'stock_nuevo' => $stock_actual + $cantidad,
            'precio_compra' => $costo_unitario,
            'motivo' => 'Compra #' . $id_compra,
            'created_at' => date('Y-m-d H:i:s')
        ));
        
        return true;
    }

    private function generar_numero_compra()
    {
        $fecha = date('Ymd');
        
        $this->db->select('numero_compra');
        $this->db->like('numero_compra', 'COM-' . $fecha, 'after');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $ultima = $this->db->get($this->table)->row();
        
        if ($ultima) {
            $partes = explode('-', $ultima->numero_compra);
            $consecutivo = isset($partes[2]) ? intval($partes[2]) + 1 : 1;
        } else {
            $consecutivo = 1;
        }
        
        return 'COM-' . $fecha . '-' . str_pad($consecutivo, 4, '0', STR_PAD_LEFT);
    }

    public function count_all($filters = array())
    {
        $this->db->from($this->table . ' c');
        
        if (isset($filters['estado'])) {
            $this->db->where('c.estado', $filters['estado']);
        }
        
        if (isset($filters['id_proveedor'])) {
            $this->db->where('c.id_proveedor', $filters['id_proveedor']);
        }
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('c.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['tipo_pago'])) {
            $this->db->where('c.tipo_pago', $filters['tipo_pago']);
        }
        
        if (isset($filters['estado_pago'])) {
            $this->db->where('c.estado_pago', $filters['estado_pago']);
        }
        
        if (isset($filters['search'])) {
            $this->db->join('proveedores p', 'p.id = c.id_proveedor', 'left');
            $this->db->group_start();
            $this->db->like('c.numero_compra', $filters['search']);
            $this->db->or_like('p.nombre', $filters['search']);
            $this->db->group_end();
        }
        
        return $this->db->count_all_results();
    }

    public function get_compras_pendientes($id_proveedor = null)
    {
        $this->db->select('c.*, p.nombre as proveedor');
        $this->db->from($this->table . ' c');
        $this->db->join('proveedores p', 'p.id = c.id_proveedor', 'left');
        $this->db->where('c.estado', 1);
        $this->db->where('c.tipo_pago', 'credito');
        $this->db->where_in('c.estado_pago', array('pendiente', 'parcial'));
        
        if ($id_proveedor) {
            $this->db->where('c.id_proveedor', $id_proveedor);
        }
        
        $this->db->order_by('c.fecha_compra', 'ASC');
        
        return $this->db->get()->result_array();
    }

    public function get_total_deuda_proveedor($id_proveedor)
    {
        $this->db->select_sum('saldo_pendiente');
        $this->db->where('id_proveedor', $id_proveedor);
        $this->db->where('estado', 1);
        $this->db->where_in('estado_pago', array('pendiente', 'parcial'));
        
        $result = $this->db->get($this->table)->row();
        
        return $result ? floatval($result->saldo_pendiente) : 0;
    }
}

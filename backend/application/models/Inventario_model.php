<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario_model extends CI_Model
{
    protected $table = 'inventario_sucursal';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene inventario con filtros
     */
    public function get_all($filters = array())
    {
        $this->db->select('i.*, p.nombre as producto, p.codigo_barras, p.precio_venta, p.stock_minimo, s.nombre as sucursal, c.nombre as categoria');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.id_producto');
        $this->db->join('sucursales s', 's.id = i.id_sucursal');
        $this->db->join('categorias c', 'c.id = p.id_categoria', 'left');
        $this->db->where('p.estado', 1);
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('i.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['stock_critico']) && $filters['stock_critico']) {
            $this->db->where('i.stock <=', 'p.stock_minimo', FALSE);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('p.nombre', $filters['search']);
            $this->db->or_like('p.codigo_barras', $filters['search']);
            $this->db->group_end();
        }
        
        $this->db->order_by('p.nombre', 'ASC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene inventario por sucursal
     */
    public function get_por_sucursal($id_sucursal)
    {
        return $this->get_all(array('id_sucursal' => $id_sucursal));
    }

    /**
     * Obtiene inventario de un producto en todas las sucursales
     */
    public function get_por_producto($id_producto)
    {
        $this->db->select('i.*, s.nombre as sucursal');
        $this->db->from($this->table . ' i');
        $this->db->join('sucursales s', 's.id = i.id_sucursal');
        $this->db->where('i.id_producto', $id_producto);
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene o crea registro de inventario
     */
    public function get_or_create($id_producto, $id_sucursal)
    {
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal);
        $inventario = $this->db->get($this->table)->row_array();
        
        if (!$inventario) {
            $this->db->insert($this->table, array(
                'id_producto' => $id_producto,
                'id_sucursal' => $id_sucursal,
                'stock' => 0
            ));
            
            return array(
                'id' => $this->db->insert_id(),
                'id_producto' => $id_producto,
                'id_sucursal' => $id_sucursal,
                'stock' => 0
            );
        }
        
        return $inventario;
    }

    /**
     * Ajusta el stock de un producto
     */
    public function ajustar($id_producto, $id_sucursal, $cantidad, $id_usuario, $motivo = null, $precio_compra = null, $precio_venta = null)
    {
        $inventario = $this->get_or_create($id_producto, $id_sucursal);
        $stock_anterior = $inventario['stock'];
        $stock_nuevo = $stock_anterior + $cantidad;
        
        if ($stock_nuevo < 0) {
            return array('success' => false, 'message' => 'Stock insuficiente');
        }
        
        // Actualizar stock
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal);
        $this->db->update($this->table, array('stock' => $stock_nuevo));
        
        // Registrar movimiento
        $tipo = $cantidad > 0 ? 'entrada' : 'salida';
        $this->registrar_movimiento($id_producto, $id_sucursal, $id_usuario, $tipo, abs($cantidad), $stock_anterior, $stock_nuevo, $motivo, null, null, $precio_compra, $precio_venta);
        
        return array('success' => true, 'stock_nuevo' => $stock_nuevo);
    }

    /**
     * Entrada de stock
     */
    public function entrada($id_producto, $id_sucursal, $cantidad, $id_usuario, $motivo = null, $precio_compra = null, $precio_venta = null)
    {
        return $this->ajustar($id_producto, $id_sucursal, abs($cantidad), $id_usuario, $motivo, $precio_compra, $precio_venta);
    }

    /**
     * Salida de stock
     */
    public function salida($id_producto, $id_sucursal, $cantidad, $id_usuario, $motivo = null, $precio_compra = null, $precio_venta = null)
    {
        return $this->ajustar($id_producto, $id_sucursal, -abs($cantidad), $id_usuario, $motivo, $precio_compra, $precio_venta);
    }

    /**
     * Transferencia entre sucursales
     */
    public function transferir($id_producto, $id_sucursal_origen, $id_sucursal_destino, $cantidad, $id_usuario, $motivo = null, $precio_compra = null, $use_transaction = true)
    {
        if ($use_transaction) {
            $this->db->trans_start();
        }
        
        // Verificar stock en origen
        $inventario_origen = $this->get_or_create($id_producto, $id_sucursal_origen);
        
        if ($inventario_origen['stock'] < $cantidad) {
            return array('success' => false, 'message' => 'Stock insuficiente en sucursal origen');
        }
        
        // Obtener o crear inventario destino
        $inventario_destino = $this->get_or_create($id_producto, $id_sucursal_destino);
        
        // Actualizar origen
        $stock_anterior_origen = $inventario_origen['stock'];
        $stock_nuevo_origen = $stock_anterior_origen - $cantidad;
        
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal_origen);
        $this->db->update($this->table, array('stock' => $stock_nuevo_origen));
        
        // Actualizar destino
        $stock_anterior_destino = $inventario_destino['stock'];
        $stock_nuevo_destino = $stock_anterior_destino + $cantidad;
        
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal_destino);
        $this->db->update($this->table, array('stock' => $stock_nuevo_destino));
        
        // Registrar movimientos
        $this->registrar_movimiento($id_producto, $id_sucursal_origen, $id_usuario, 'transferencia', $cantidad, $stock_anterior_origen, $stock_nuevo_origen, $motivo, null, $id_sucursal_destino, $precio_compra, null);
        $this->registrar_movimiento($id_producto, $id_sucursal_destino, $id_usuario, 'transferencia', $cantidad, $stock_anterior_destino, $stock_nuevo_destino, $motivo, null, null, $precio_compra, null);
        
        if ($use_transaction) {
            $this->db->trans_complete();
            
            if ($this->db->trans_status() === FALSE) {
                return array('success' => false, 'message' => 'Error al realizar la transferencia');
            }
        }
        
        return array('success' => true);
    }

    /**
     * Transferencia masiva entre sucursales
     */
    public function transferir_masivo($id_sucursal_origen, $id_sucursal_destino, $items, $id_usuario, $motivo = null)
    {
        $this->db->trans_start();

        foreach ($items as $item) {
            $id_producto = isset($item['id_producto']) ? (int)$item['id_producto'] : 0;
            $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;
            $precio_compra = array_key_exists('precio_compra', $item) ? $item['precio_compra'] : null;

            if ($id_producto <= 0 || $cantidad <= 0) {
                $this->db->trans_complete();
                return array('success' => false, 'message' => 'Datos inválidos en items de transferencia');
            }

            $res = $this->transferir($id_producto, $id_sucursal_origen, $id_sucursal_destino, $cantidad, $id_usuario, $motivo, $precio_compra, false);
            if (!$res['success']) {
                $this->db->trans_complete();
                return $res;
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            return array('success' => false, 'message' => 'Error al realizar la transferencia masiva');
        }

        return array('success' => true);
    }

    /**
     * Registra un movimiento de inventario
     */
    public function registrar_movimiento($id_producto, $id_sucursal, $id_usuario, $tipo, $cantidad, $stock_anterior, $stock_nuevo, $motivo = null, $id_venta = null, $id_sucursal_destino = null, $precio_compra = null, $precio_venta = null)
    {
        $this->db->insert('movimientos_inventario', array(
            'id_producto' => $id_producto,
            'id_sucursal' => $id_sucursal,
            'id_usuario' => $id_usuario,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stock_anterior,
            'stock_nuevo' => $stock_nuevo,
            'precio_compra' => $precio_compra,
            'precio_venta' => $precio_venta,
            'id_venta' => $id_venta,
            'id_sucursal_destino' => $id_sucursal_destino,
            'motivo' => $motivo,
            'created_at' => date('Y-m-d H:i:s')
        ));
        
        return $this->db->insert_id();
    }

    /**
     * Obtiene movimientos de inventario
     */
    public function get_movimientos($filters = array())
    {
        $this->db->select('m.*, p.nombre as producto, p.codigo_barras, s.nombre as sucursal, u.nombre as usuario, sd.nombre as sucursal_destino');
        $this->db->from('movimientos_inventario m');
        $this->db->join('productos p', 'p.id = m.id_producto');
        $this->db->join('sucursales s', 's.id = m.id_sucursal');
        $this->db->join('usuarios u', 'u.id = m.id_usuario');
        $this->db->join('sucursales sd', 'sd.id = m.id_sucursal_destino', 'left');
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('m.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['id_producto'])) {
            $this->db->where('m.id_producto', $filters['id_producto']);
        }
        
        if (isset($filters['tipo'])) {
            $this->db->where('m.tipo', $filters['tipo']);
        }
        
        if (isset($filters['fecha_inicio'])) {
            $this->db->where('DATE(m.created_at) >=', $filters['fecha_inicio']);
        }
        
        if (isset($filters['fecha_fin'])) {
            $this->db->where('DATE(m.created_at) <=', $filters['fecha_fin']);
        }
        
        // Paginación
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('m.created_at', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene resumen de inventario global
     */
    public function get_resumen_global()
    {
        $this->db->select('COUNT(DISTINCT i.id_producto) as total_productos, SUM(i.stock) as total_stock');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.id_producto');
        $this->db->where('p.estado', 1);
        
        $resumen = $this->db->get()->row_array();
        
        // Productos con stock crítico
        $this->db->select('COUNT(*) as stock_critico');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.id_producto');
        $this->db->where('p.estado', 1);
        $this->db->where('i.stock <=', 'p.stock_minimo', FALSE);
        
        $critico = $this->db->get()->row();
        $resumen['stock_critico'] = $critico->stock_critico;
        
        // Valor del inventario
        $this->db->select('SUM(i.stock * p.precio_compra) as valor_costo, SUM(i.stock * p.precio_venta) as valor_venta');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.id_producto');
        $this->db->where('p.estado', 1);
        
        $valor = $this->db->get()->row_array();
        $resumen['valor_costo'] = $valor['valor_costo'] ?: 0;
        $resumen['valor_venta'] = $valor['valor_venta'] ?: 0;
        
        return $resumen;
    }

    /**
     * Obtiene productos con stock crítico
     */
    public function get_stock_critico($id_sucursal = null, $limit = 20)
    {
        $this->db->select('i.*, p.nombre as producto, p.codigo_barras, p.stock_minimo, s.nombre as sucursal');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.id_producto');
        $this->db->join('sucursales s', 's.id = i.id_sucursal');
        $this->db->where('p.estado', 1);
        $this->db->where('i.stock <=', 'p.stock_minimo', FALSE);
        
        if ($id_sucursal) {
            $this->db->where('i.id_sucursal', $id_sucursal);
        }
        
        $this->db->order_by('i.stock', 'ASC');
        $this->db->limit($limit);
        
        return $this->db->get()->result_array();
    }
}

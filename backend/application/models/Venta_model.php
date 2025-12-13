<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Venta_model extends CI_Model
{
    protected $table = 'ventas';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene todas las ventas con filtros
     */
    public function get_all($filters = array())
    {
        $this->db->select('v.*, u.nombre as usuario, s.nombre as sucursal, mp.nombre as metodo_pago, c.nombre as cliente');
        $this->db->from($this->table . ' v');
        $this->db->join('usuarios u', 'u.id = v.id_usuario', 'left');
        $this->db->join('sucursales s', 's.id = v.id_sucursal', 'left');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->join('clientes c', 'c.id = v.id_cliente', 'left');
        
        if (isset($filters['id_sucursal'])) {
            $this->db->where('v.id_sucursal', $filters['id_sucursal']);
        }
        
        if (isset($filters['id_usuario'])) {
            $this->db->where('v.id_usuario', $filters['id_usuario']);
        }
        
        if (isset($filters['estado'])) {
            $this->db->where('v.estado', $filters['estado']);
        }
        
        if (isset($filters['fecha_inicio'])) {
            $this->db->where('DATE(v.fecha_venta) >=', $filters['fecha_inicio']);
        }
        
        if (isset($filters['fecha_fin'])) {
            $this->db->where('DATE(v.fecha_venta) <=', $filters['fecha_fin']);
        }
        
        if (isset($filters['id_metodo_pago'])) {
            $this->db->where('v.id_metodo_pago', $filters['id_metodo_pago']);
        }

        if (isset($filters['tipo_venta'])) {
            $this->db->where('v.tipo_venta', $filters['tipo_venta']);
        }

        if (isset($filters['estado_cobro'])) {
            $this->db->where('v.estado_cobro', $filters['estado_cobro']);
        }

        if (isset($filters['id_cliente'])) {
            $this->db->where('v.id_cliente', $filters['id_cliente']);
        }
        
        // Paginación
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('v.fecha_venta', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene una venta por ID con detalle
     */
    public function get_by_id($id)
    {
        $this->db->select('v.*, u.nombre as usuario, s.nombre as sucursal, mp.nombre as metodo_pago, c.nombre as cliente');
        $this->db->from($this->table . ' v');
        $this->db->join('usuarios u', 'u.id = v.id_usuario', 'left');
        $this->db->join('sucursales s', 's.id = v.id_sucursal', 'left');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->join('clientes c', 'c.id = v.id_cliente', 'left');
        $this->db->where('v.id', $id);
        
        $venta = $this->db->get()->row_array();
        
        if ($venta) {
            $venta['detalle'] = $this->get_detalle($id);
        }
        
        return $venta;
    }

    public function get_creditos($filters = array())
    {
        $filters['tipo_venta'] = 'credito';
        return $this->get_all($filters);
    }

    public function count_creditos($filters = array())
    {
        $this->db->from($this->table . ' v');
        $this->db->where('v.tipo_venta', 'credito');

        if (isset($filters['id_sucursal'])) {
            $this->db->where('v.id_sucursal', $filters['id_sucursal']);
        }
        if (isset($filters['estado_cobro'])) {
            $this->db->where('v.estado_cobro', $filters['estado_cobro']);
        }
        if (isset($filters['id_cliente'])) {
            $this->db->where('v.id_cliente', $filters['id_cliente']);
        }
        if (isset($filters['fecha_inicio'])) {
            $this->db->where('DATE(v.fecha_venta) >=', $filters['fecha_inicio']);
        }
        if (isset($filters['fecha_fin'])) {
            $this->db->where('DATE(v.fecha_venta) <=', $filters['fecha_fin']);
        }

        return $this->db->count_all_results();
    }

    /**
     * Obtiene el detalle de una venta
     */
    public function get_detalle($id_venta)
    {
        $this->db->select('vd.*, p.nombre as producto, p.codigo_barras');
        $this->db->from('ventas_detalle vd');
        $this->db->join('productos p', 'p.id = vd.id_producto', 'left');
        $this->db->where('vd.id_venta', $id_venta);
        
        return $this->db->get()->result_array();
    }

    /**
     * Crea una nueva venta
     */
    public function create($data, $detalle)
    {
        $this->db->trans_start();
        
        // Generar número de venta
        $data['numero_venta'] = $this->generate_numero_venta($data['id_sucursal']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['fecha_venta'] = date('Y-m-d H:i:s');
        
        // Insertar venta
        $this->db->insert($this->table, $data);
        $id_venta = $this->db->insert_id();
        
        // Insertar detalle y actualizar inventario
        foreach ($detalle as $item) {
            // Insertar detalle
            $this->db->insert('ventas_detalle', array(
                'id_venta' => $id_venta,
                'id_producto' => $item['id_producto'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'precio_compra' => isset($item['precio_compra']) ? $item['precio_compra'] : 0,
                'descuento' => isset($item['descuento']) ? $item['descuento'] : 0,
                'subtotal' => $item['subtotal'],
                'created_at' => date('Y-m-d H:i:s')
            ));
            
            // Actualizar inventario
            $this->actualizar_inventario($item['id_producto'], $data['id_sucursal'], -$item['cantidad'], $id_venta, $data['id_usuario']);
        }
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return false;
        }
        
        return $id_venta;
    }

    /**
     * Anula una venta
     */
    public function anular($id, $id_usuario)
    {
        $venta = $this->get_by_id($id);
        
        if (!$venta || $venta['estado'] !== 'completada') {
            return false;
        }
        
        $this->db->trans_start();
        
        // Actualizar estado
        $this->db->where('id', $id);
        $this->db->update($this->table, array(
            'estado' => 'anulada',
            'updated_at' => date('Y-m-d H:i:s')
        ));
        
        // Devolver inventario
        foreach ($venta['detalle'] as $item) {
            $this->actualizar_inventario(
                $item['id_producto'], 
                $venta['id_sucursal'], 
                $item['cantidad'], 
                $id, 
                $id_usuario,
                'devolucion'
            );
        }
        
        $this->db->trans_complete();
        
        return $this->db->trans_status();
    }

    /**
     * Actualiza el inventario
     */
    protected function actualizar_inventario($id_producto, $id_sucursal, $cantidad, $id_venta, $id_usuario, $tipo = 'venta')
    {
        // Obtener stock actual
        $this->db->select('stock');
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal);
        $inventario = $this->db->get('inventario_sucursal')->row();
        
        $stock_anterior = $inventario ? $inventario->stock : 0;
        $stock_nuevo = $stock_anterior + $cantidad;
        
        // Actualizar o insertar inventario
        if ($inventario) {
            $this->db->where('id_producto', $id_producto);
            $this->db->where('id_sucursal', $id_sucursal);
            $this->db->update('inventario_sucursal', array('stock' => $stock_nuevo));
        } else {
            $this->db->insert('inventario_sucursal', array(
                'id_producto' => $id_producto,
                'id_sucursal' => $id_sucursal,
                'stock' => $stock_nuevo
            ));
        }
        
        // Registrar movimiento
        $this->db->insert('movimientos_inventario', array(
            'id_producto' => $id_producto,
            'id_sucursal' => $id_sucursal,
            'id_usuario' => $id_usuario,
            'tipo' => $tipo,
            'cantidad' => abs($cantidad),
            'stock_anterior' => $stock_anterior,
            'stock_nuevo' => $stock_nuevo,
            'id_venta' => $id_venta,
            'created_at' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Genera número de venta único
     */
    protected function generate_numero_venta($id_sucursal)
    {
        $prefix = 'V' . str_pad($id_sucursal, 2, '0', STR_PAD_LEFT);
        $date = date('Ymd');
        
        // Obtener último número del día
        $this->db->select('numero_venta');
        $this->db->like('numero_venta', $prefix . $date, 'after');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        
        $last = $this->db->get($this->table)->row();
        
        if ($last) {
            $last_num = (int)substr($last->numero_venta, -4);
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }
        
        return $prefix . $date . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene ventas del día
     */
    public function get_ventas_dia($id_sucursal = null)
    {
        $this->db->select('COUNT(*) as cantidad, SUM(total) as total');
        $this->db->where('DATE(fecha_venta)', date('Y-m-d'));
        $this->db->where('estado', 'completada');
        
        if ($id_sucursal) {
            $this->db->where('id_sucursal', $id_sucursal);
        }
        
        return $this->db->get($this->table)->row_array();
    }

    /**
     * Obtiene ventas por sucursal
     */
    public function get_ventas_por_sucursal($fecha_inicio = null, $fecha_fin = null)
    {
        $this->db->select('s.nombre as sucursal, COUNT(v.id) as cantidad, SUM(v.total) as total');
        $this->db->from($this->table . ' v');
        $this->db->join('sucursales s', 's.id = v.id_sucursal');
        $this->db->where('v.estado', 'completada');
        
        if ($fecha_inicio) {
            $this->db->where('DATE(v.fecha_venta) >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('DATE(v.fecha_venta) <=', $fecha_fin);
        }
        
        $this->db->group_by('v.id_sucursal');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene top productos vendidos
     */
    public function get_top_productos($limit = 10, $id_sucursal = null, $fecha_inicio = null, $fecha_fin = null)
    {
        $this->db->select('p.id, p.nombre, p.codigo_barras, SUM(vd.cantidad) as cantidad_vendida, SUM(vd.subtotal) as total_vendido');
        $this->db->from('ventas_detalle vd');
        $this->db->join('ventas v', 'v.id = vd.id_venta');
        $this->db->join('productos p', 'p.id = vd.id_producto');
        $this->db->where('v.estado', 'completada');
        
        if ($id_sucursal) {
            $this->db->where('v.id_sucursal', $id_sucursal);
        }
        if ($fecha_inicio) {
            $this->db->where('DATE(v.fecha_venta) >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('DATE(v.fecha_venta) <=', $fecha_fin);
        }
        
        $this->db->group_by('vd.id_producto');
        $this->db->order_by('cantidad_vendida', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene ventas por período (para gráficas)
     */
    public function get_ventas_periodo($dias = 7, $id_sucursal = null)
    {
        $dias = (int)$dias;
        if ($dias <= 0) {
            $dias = 7;
        }

        // Últimos N días incluyendo hoy
        $fecha_fin = date('Y-m-d');
        $fecha_inicio = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));

        $this->db->select('DATE(fecha_venta) as fecha, COUNT(*) as cantidad, SUM(total) as total');
        $this->db->where('DATE(fecha_venta) >=', $fecha_inicio);
        $this->db->where('DATE(fecha_venta) <=', $fecha_fin);
        $this->db->where('estado', 'completada');

        if ($id_sucursal) {
            $this->db->where('id_sucursal', $id_sucursal);
        }

        $this->db->group_by('DATE(fecha_venta)');
        $this->db->order_by('fecha', 'ASC');

        $rows = $this->db->get($this->table)->result_array();

        // Indexar por fecha para rellenar días sin ventas y evitar gráficos incorrectos.
        $byFecha = array();
        foreach ($rows as $r) {
            $fecha = isset($r['fecha']) ? $r['fecha'] : null;
            if (!$fecha) {
                continue;
            }
            $byFecha[$fecha] = array(
                'fecha' => $fecha,
                'cantidad' => (int)($r['cantidad'] ?: 0),
                'total' => (float)($r['total'] ?: 0),
            );
        }

        $result = array();
        for ($i = 0; $i < $dias; $i++) {
            $fecha = date('Y-m-d', strtotime($fecha_inicio . ' +' . $i . ' days'));
            if (isset($byFecha[$fecha])) {
                $result[] = $byFecha[$fecha];
            } else {
                $result[] = array(
                    'fecha' => $fecha,
                    'cantidad' => 0,
                    'total' => 0,
                );
            }
        }

        return $result;
    }

    /**
     * Obtiene ingresos por método de pago
     */
    public function get_ingresos_metodo_pago($fecha_inicio = null, $fecha_fin = null, $id_sucursal = null)
    {
        $this->db->select("COALESCE(mp.nombre, 'Crédito') as metodo_pago, COUNT(v.id) as cantidad, SUM(v.total) as total", false);
        $this->db->from($this->table . ' v');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('v.estado', 'completada');
        
        if ($id_sucursal) {
            $this->db->where('v.id_sucursal', $id_sucursal);
        }
        if ($fecha_inicio) {
            $this->db->where('DATE(v.fecha_venta) >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('DATE(v.fecha_venta) <=', $fecha_fin);
        }

        // Agrupar por método (incluye NULL como "Crédito")
        $this->db->group_by("COALESCE(v.id_metodo_pago, 0)", false);
        $this->db->order_by('total', 'DESC');

        $rows = $this->db->get()->result_array();

        // Normalizar tipos para que el frontend grafique correctamente
        foreach ($rows as &$r) {
            $r['cantidad'] = (int)($r['cantidad'] ?: 0);
            $r['total'] = (float)($r['total'] ?: 0);
        }

        return $rows;
    }

    /**
     * Calcula utilidad
     */
    public function get_utilidad($fecha_inicio = null, $fecha_fin = null, $id_sucursal = null)
    {
        $this->db->select('SUM(vd.subtotal) as ingresos, SUM(vd.precio_compra * vd.cantidad) as costos');
        $this->db->from('ventas_detalle vd');
        $this->db->join('ventas v', 'v.id = vd.id_venta');
        $this->db->where('v.estado', 'completada');
        
        if ($id_sucursal) {
            $this->db->where('v.id_sucursal', $id_sucursal);
        }
        if ($fecha_inicio) {
            $this->db->where('DATE(v.fecha_venta) >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('DATE(v.fecha_venta) <=', $fecha_fin);
        }
        
        $result = $this->db->get()->row_array();
        
        $ingresos = $result['ingresos'] ?: 0;
        $costos = $result['costos'] ?: 0;
        
        return array(
            'ingresos' => $ingresos,
            'costos' => $costos,
            'utilidad' => $ingresos - $costos
        );
    }

    /**
     * Cuenta ventas de un usuario
     */
    public function count_ventas_usuario($id_usuario, $fecha = null)
    {
        $this->db->where('id_usuario', $id_usuario);
        $this->db->where('estado', 'completada');
        
        if ($fecha) {
            $this->db->where('DATE(fecha_venta)', $fecha);
        }
        
        return $this->db->count_all_results($this->table);
    }

    /**
     * Cuenta total de ventas
     */
    public function count_all($filters = array())
    {
        if (isset($filters['id_sucursal'])) {
            $this->db->where('id_sucursal', $filters['id_sucursal']);
        }
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        if (isset($filters['tipo_venta'])) {
            $this->db->where('tipo_venta', $filters['tipo_venta']);
        }
        if (isset($filters['estado_cobro'])) {
            $this->db->where('estado_cobro', $filters['estado_cobro']);
        }
        if (isset($filters['id_cliente'])) {
            $this->db->where('id_cliente', $filters['id_cliente']);
        }
        if (isset($filters['fecha_inicio'])) {
            $this->db->where('DATE(fecha_venta) >=', $filters['fecha_inicio']);
        }
        if (isset($filters['fecha_fin'])) {
            $this->db->where('DATE(fecha_venta) <=', $filters['fecha_fin']);
        }
        
        return $this->db->count_all_results($this->table);
    }
}

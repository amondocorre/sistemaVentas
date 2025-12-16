<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caja_model extends CI_Model
{
    protected $table = 'caja_turnos';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_turno_abierto($id_usuario, $id_sucursal)
    {
        $this->db->where('id_usuario', (int)$id_usuario);
        $this->db->where('id_sucursal', (int)$id_sucursal);
        $this->db->where('estado', 'abierto');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        return $this->db->get($this->table)->row_array();
    }

    public function abrir_turno($id_usuario, $id_sucursal, $monto_inicial)
    {
        $data = array(
            'id_usuario' => (int)$id_usuario,
            'id_sucursal' => (int)$id_sucursal,
            'monto_inicial' => (float)$monto_inicial,
            'estado' => 'abierto',
            'fecha_apertura' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        );

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function cerrar_turno($id_turno, $monto_cierre_real = null)
    {
        $data = array(
            'estado' => 'cerrado',
            'fecha_cierre' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if ($monto_cierre_real !== null) {
            $data['monto_cierre_real'] = (float)$monto_cierre_real;
        }

        $this->db->where('id', (int)$id_turno);
        return $this->db->update($this->table, $data);
    }

    public function get_by_id($id)
    {
        $this->db->where('id', (int)$id);
        return $this->db->get($this->table)->row_array();
    }

    public function get_resumen_metodos_pago($fecha_inicio, $fecha_fin, $id_sucursal, $id_usuario)
    {
        $this->db->select("COALESCE(mp.nombre, 'Crédito') as metodo_pago, COALESCE(mp.tipo, 'credito') as metodo_tipo, COUNT(v.id) as cantidad, SUM(v.total) as total", false);
        $this->db->from('ventas v');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('v.estado', 'completada');
        $this->db->where('v.id_sucursal', (int)$id_sucursal);
        $this->db->where('v.id_usuario', (int)$id_usuario);

        if ($fecha_inicio) {
            $this->db->where('v.fecha_venta >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('v.fecha_venta <=', $fecha_fin);
        }

        $this->db->group_by("COALESCE(v.id_metodo_pago, 0)", false);
        $this->db->order_by('total', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['cantidad'] = (int)($r['cantidad'] ?: 0);
            $r['total'] = (float)($r['total'] ?: 0);
        }

        return $rows;
    }

    public function get_resumen_metodos_pago_turno($fecha_inicio, $fecha_fin, $id_sucursal, $id_usuario)
    {
        $whereVentasFecha = '';
        $whereCobrosFecha = '';
        $params = array(
            (int)$id_sucursal,
            (int)$id_usuario,
        );

        if ($fecha_inicio) {
            $whereVentasFecha .= ' AND v.fecha_venta >= ?';
            $whereCobrosFecha .= ' AND vc.created_at >= ?';
            $params[] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $whereVentasFecha .= ' AND v.fecha_venta <= ?';
            $whereCobrosFecha .= ' AND vc.created_at <= ?';
            $params[] = $fecha_fin;
        }

        $paramsCobros = array(
            (int)$id_sucursal,
            (int)$id_usuario,
        );
        if ($fecha_inicio) {
            $paramsCobros[] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $paramsCobros[] = $fecha_fin;
        }

        $sql = "
            SELECT
                t.metodo_id,
                t.metodo_pago,
                t.metodo_tipo,
                SUM(t.cantidad) as cantidad,
                SUM(t.total) as total
            FROM (
                SELECT
                    v.id_metodo_pago as metodo_id,
                    COALESCE(mp.nombre, 'Sin método') as metodo_pago,
                    COALESCE(mp.tipo, '') as metodo_tipo,
                    COUNT(v.id) as cantidad,
                    COALESCE(SUM(v.total), 0) as total
                FROM ventas v
                LEFT JOIN metodos_pago mp ON mp.id = v.id_metodo_pago
                WHERE v.estado = 'completada'
                  AND v.tipo_venta = 'contado'
                  AND v.id_sucursal = ?
                  AND v.id_usuario = ?
                  {$whereVentasFecha}
                GROUP BY v.id_metodo_pago

                UNION ALL

                SELECT
                    vc.id_metodo_pago as metodo_id,
                    COALESCE(mp.nombre, 'Sin método') as metodo_pago,
                    COALESCE(mp.tipo, '') as metodo_tipo,
                    COUNT(vc.id) as cantidad,
                    COALESCE(SUM(vc.monto), 0) as total
                FROM ventas_cobros vc
                INNER JOIN ventas v ON v.id = vc.id_venta
                LEFT JOIN metodos_pago mp ON mp.id = vc.id_metodo_pago
                WHERE v.id_sucursal = ?
                  AND vc.id_usuario = ?
                  {$whereCobrosFecha}
                GROUP BY vc.id_metodo_pago
            ) t
            GROUP BY t.metodo_id, t.metodo_pago, t.metodo_tipo
            ORDER BY total DESC
        ";

        $allParams = array_merge($params, $paramsCobros);
        $rows = $this->db->query($sql, $allParams)->result_array();

        foreach ($rows as &$r) {
            $r['cantidad'] = (int)($r['cantidad'] ?: 0);
            $r['total'] = (float)($r['total'] ?: 0);
        }

        return $rows;
    }

    public function get_resumen_efectivo_turno($fecha_inicio, $fecha_fin, $id_sucursal, $id_usuario)
    {
        // Ventas contado: efectivo puro + componente efectivo de mixto
        $this->db->select("COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN v.total ELSE 0 END), 0) as total_efectivo_ventas, COALESCE(SUM(COALESCE(v.monto_efectivo, 0)), 0) as total_efectivo_mixto", false);
        $this->db->from('ventas v');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('v.estado', 'completada');
        $this->db->where('v.id_sucursal', (int)$id_sucursal);
        $this->db->where('v.id_usuario', (int)$id_usuario);

        if ($fecha_inicio) {
            $this->db->where('v.fecha_venta >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('v.fecha_venta <=', $fecha_fin);
        }

        $ventasRow = $this->db->get()->row_array();

        $total_efectivo_ventas = isset($ventasRow['total_efectivo_ventas']) ? (float)$ventasRow['total_efectivo_ventas'] : 0;
        $total_efectivo_mixto = isset($ventasRow['total_efectivo_mixto']) ? (float)$ventasRow['total_efectivo_mixto'] : 0;

        // Cobros de créditos: efectivo puro + componente efectivo de mixto
        $this->db->select("COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN vc.monto ELSE 0 END), 0) as total_efectivo_cobros, COALESCE(SUM(COALESCE(vc.monto_efectivo, 0)), 0) as total_efectivo_cobros_mixto", false);
        $this->db->from('ventas_cobros vc');
        $this->db->join('ventas v', 'v.id = vc.id_venta', 'inner');
        $this->db->join('metodos_pago mp', 'mp.id = vc.id_metodo_pago', 'left');
        $this->db->where('v.id_sucursal', (int)$id_sucursal);
        $this->db->where('vc.id_usuario', (int)$id_usuario);

        if ($fecha_inicio) {
            $this->db->where('vc.created_at >=', $fecha_inicio);
        }
        if ($fecha_fin) {
            $this->db->where('vc.created_at <=', $fecha_fin);
        }

        $cobrosRow = $this->db->get()->row_array();

        $total_efectivo_cobros = isset($cobrosRow['total_efectivo_cobros']) ? (float)$cobrosRow['total_efectivo_cobros'] : 0;
        $total_efectivo_cobros_mixto = isset($cobrosRow['total_efectivo_cobros_mixto']) ? (float)$cobrosRow['total_efectivo_cobros_mixto'] : 0;

        return array(
            'total_efectivo_ventas' => $total_efectivo_ventas + $total_efectivo_cobros,
            'total_efectivo_mixto' => $total_efectivo_mixto + $total_efectivo_cobros_mixto,
        );
    }

    public function get_turnos_cerrados($id_usuario, $id_sucursal, $filters = array())
    {
        $this->db->select("t.*, COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN v.total ELSE 0 END), 0) as total_efectivo_ventas, COALESCE(SUM(COALESCE(v.monto_efectivo, 0)), 0) as total_efectivo_mixto", false);
        $this->db->from($this->table . ' t');
        $this->db->join(
            'ventas v',
            "v.id_usuario = t.id_usuario AND v.id_sucursal = t.id_sucursal AND v.estado = 'completada' AND v.fecha_venta >= t.fecha_apertura AND v.fecha_venta <= t.fecha_cierre",
            'left',
            false
        );
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('t.id_usuario', (int)$id_usuario);
        $this->db->where('t.id_sucursal', (int)$id_sucursal);
        $this->db->where('t.estado', 'cerrado');

        if (!empty($filters['fecha_inicio'])) {
            $this->db->where('DATE(t.fecha_apertura) >=', $filters['fecha_inicio']);
        }
        if (!empty($filters['fecha_fin'])) {
            $this->db->where('DATE(t.fecha_apertura) <=', $filters['fecha_fin']);
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $this->db->group_by('t.id');
        $this->db->order_by('t.fecha_apertura', 'DESC');
        $this->db->limit($limit, $offset);

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$t) {
            $monto_inicial = isset($t['monto_inicial']) ? (float)$t['monto_inicial'] : 0;
            $total_efectivo_ventas = isset($t['total_efectivo_ventas']) ? (float)$t['total_efectivo_ventas'] : 0;
            $total_efectivo_mixto = isset($t['total_efectivo_mixto']) ? (float)$t['total_efectivo_mixto'] : 0;
            $efectivo_esperado = $monto_inicial + $total_efectivo_ventas + $total_efectivo_mixto;
            $t['total_efectivo_ventas'] = $total_efectivo_ventas;
            $t['total_efectivo_mixto'] = $total_efectivo_mixto;
            $t['efectivo_esperado'] = $efectivo_esperado;

            if (isset($t['monto_cierre_real']) && $t['monto_cierre_real'] !== null) {
                $t['diferencia_cierre'] = (float)$t['monto_cierre_real'] - $efectivo_esperado;
            } else {
                $t['diferencia_cierre'] = null;
            }
        }

        return $rows;
    }

    public function count_turnos_cerrados($id_usuario, $id_sucursal, $filters = array())
    {
        $this->db->from($this->table);
        $this->db->where('id_usuario', (int)$id_usuario);
        $this->db->where('id_sucursal', (int)$id_sucursal);
        $this->db->where('estado', 'cerrado');

        if (!empty($filters['fecha_inicio'])) {
            $this->db->where('DATE(fecha_apertura) >=', $filters['fecha_inicio']);
        }
        if (!empty($filters['fecha_fin'])) {
            $this->db->where('DATE(fecha_apertura) <=', $filters['fecha_fin']);
        }

        return (int)$this->db->count_all_results();
    }

    public function get_turno_detalle($id_turno, $id_usuario, $id_sucursal)
    {
        $turno = $this->get_by_id($id_turno);
        if (!$turno) {
            return null;
        }

        if ((int)$turno['id_usuario'] !== (int)$id_usuario || (int)$turno['id_sucursal'] !== (int)$id_sucursal) {
            return null;
        }

        if ($turno['estado'] !== 'cerrado') {
            return null;
        }

        $fecha_inicio = $turno['fecha_apertura'];
        $fecha_fin = $turno['fecha_cierre'];

        $resumen = $this->get_resumen_metodos_pago_turno($fecha_inicio, $fecha_fin, $id_sucursal, $id_usuario);

        $this->db->select("COALESCE(SUM(CASE WHEN mp.tipo = 'efectivo' THEN v.total ELSE 0 END), 0) as total_efectivo_ventas, COALESCE(SUM(COALESCE(v.monto_efectivo, 0)), 0) as total_efectivo_mixto", false);
        $this->db->from('ventas v');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('v.estado', 'completada');
        $this->db->where('v.id_sucursal', (int)$id_sucursal);
        $this->db->where('v.id_usuario', (int)$id_usuario);
        $this->db->where('v.fecha_venta >=', $fecha_inicio);
        $this->db->where('v.fecha_venta <=', $fecha_fin);
        $sumRow = $this->db->get()->row_array();

        $total_efectivo_ventas = isset($sumRow['total_efectivo_ventas']) ? (float)$sumRow['total_efectivo_ventas'] : 0;
        $total_efectivo_mixto = isset($sumRow['total_efectivo_mixto']) ? (float)$sumRow['total_efectivo_mixto'] : 0;

        $monto_inicial = isset($turno['monto_inicial']) ? (float)$turno['monto_inicial'] : 0;
        $efectivo_esperado = $monto_inicial + $total_efectivo_ventas + $total_efectivo_mixto;
        $diferencia_cierre = null;
        if (isset($turno['monto_cierre_real']) && $turno['monto_cierre_real'] !== null) {
            $diferencia_cierre = (float)$turno['monto_cierre_real'] - $efectivo_esperado;
        }

        $this->db->select("v.id, v.numero_venta, v.total, v.fecha_venta, COALESCE(mp.nombre, 'Crédito') as metodo_pago", false);
        $this->db->from('ventas v');
        $this->db->join('metodos_pago mp', 'mp.id = v.id_metodo_pago', 'left');
        $this->db->where('v.estado', 'completada');
        $this->db->where('v.id_sucursal', (int)$id_sucursal);
        $this->db->where('v.id_usuario', (int)$id_usuario);
        $this->db->where('v.fecha_venta >=', $fecha_inicio);
        $this->db->where('v.fecha_venta <=', $fecha_fin);
        $this->db->order_by('v.fecha_venta', 'DESC');
        $ventas = $this->db->get()->result_array();

        foreach ($ventas as &$v) {
            $v['id'] = (int)$v['id'];
            $v['total'] = (float)($v['total'] ?: 0);
        }

        return array(
            'turno' => $turno,
            'resumen_metodos_pago' => $resumen,
            'total_efectivo_ventas' => (float)$total_efectivo_ventas,
            'total_efectivo_mixto' => (float)$total_efectivo_mixto,
            'efectivo_esperado' => (float)$efectivo_esperado,
            'diferencia_cierre' => $diferencia_cierre,
            'ventas' => $ventas,
        );
    }
}

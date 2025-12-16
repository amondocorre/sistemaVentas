<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Creditos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Venta_model');
        $this->load->model('VentaCobro_model');
        $this->load->model('MetodoPago_model');
        $this->load->model('Caja_model');
    }

    public function cobros($id_venta)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('creditos_ver');

        $venta = $this->Venta_model->get_by_id($id_venta);
        if (!$venta) {
            $this->response(array(
                'success' => false,
                'message' => 'Venta no encontrada'
            ), 404);
        }

        if (isset($venta['tipo_venta']) && $venta['tipo_venta'] !== 'credito') {
            $this->response(array(
                'success' => false,
                'message' => 'La venta no es a crédito'
            ), 400);
        }

        if (!$this->is_admin() && (int)$venta['id_sucursal'] !== (int)$this->user['id_sucursal']) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }

        $cobros = $this->VentaCobro_model->get_by_venta($id_venta);

        $this->response(array(
            'success' => true,
            'data' => $cobros,
        ));
    }

    public function index()
    {
        $this->require_permission('creditos_ver');

        $filters = array(
            'id_sucursal' => $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'],
            'estado_cobro' => $this->input->get('estado_cobro') ?: 'pendiente',
            'id_cliente' => $this->input->get('id_cliente'),
            'fecha_inicio' => $this->input->get('fecha_inicio'),
            'fecha_fin' => $this->input->get('fecha_fin'),
            'limit' => $this->input->get('limit') ?: 50,
            'offset' => $this->input->get('offset') ?: 0,
        );

        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });

        if (isset($filters['estado_cobro']) && !in_array($filters['estado_cobro'], array('pendiente', 'pagado'), true)) {
            $this->response(array(
                'success' => false,
                'message' => 'Estado de cobro inválido'
            ), 400);
        }

        $creditos = $this->Venta_model->get_creditos($filters);
        $total = $this->Venta_model->count_creditos($filters);

        $this->response(array(
            'success' => true,
            'data' => $creditos,
            'total' => $total,
        ));
    }

    public function registrar_cobro($id_venta)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('creditos_cobrar');

        $turno = $this->Caja_model->get_turno_abierto($this->user['id'], $this->user['id_sucursal']);
        if (!$turno) {
            $this->response(array(
                'success' => false,
                'message' => 'Debe realizar apertura de caja antes de registrar cobros'
            ), 400);
        }

        $input = $this->get_json_input();

        $monto = isset($input['monto']) ? (float)$input['monto'] : 0;
        $id_metodo_pago = isset($input['id_metodo_pago']) ? (int)$input['id_metodo_pago'] : 0;
        $referencia = isset($input['referencia']) ? trim((string)$input['referencia']) : '';
        $monto_efectivo = null;
        if (array_key_exists('monto_efectivo', $input)) {
            $raw_monto_efectivo = is_string($input['monto_efectivo']) ? trim($input['monto_efectivo']) : $input['monto_efectivo'];
            if ($raw_monto_efectivo !== '' && $raw_monto_efectivo !== null) {
                $monto_efectivo = (float)$raw_monto_efectivo;
            }
        }

        if ($monto <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Monto inválido'
            ), 400);
        }

        if ($id_metodo_pago <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Método de pago es requerido'
            ), 400);
        }

        if ($referencia === '') {
            $this->response(array(
                'success' => false,
                'message' => 'La referencia es requerida'
            ), 400);
        }

        $venta = $this->Venta_model->get_by_id($id_venta);
        if (!$venta) {
            $this->response(array(
                'success' => false,
                'message' => 'Venta no encontrada'
            ), 404);
        }

        if (isset($venta['tipo_venta']) && $venta['tipo_venta'] !== 'credito') {
            $this->response(array(
                'success' => false,
                'message' => 'La venta no es a crédito'
            ), 400);
        }

        if (isset($venta['estado']) && $venta['estado'] !== 'completada') {
            $this->response(array(
                'success' => false,
                'message' => 'La venta no está completada'
            ), 400);
        }

        // Validar acceso por sucursal
        if (!$this->is_admin() && (int)$venta['id_sucursal'] !== (int)$this->user['id_sucursal']) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }

        $saldo_actual = isset($venta['saldo']) ? (float)$venta['saldo'] : 0;
        if ($saldo_actual <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'La venta ya está pagada'
            ), 400);
        }

        if ($monto > $saldo_actual) {
            $this->response(array(
                'success' => false,
                'message' => 'El monto no puede ser mayor al saldo'
            ), 400);
        }

        $metodo = $this->MetodoPago_model->get_by_id($id_metodo_pago);
        if (!$metodo || (int)$metodo['estado'] !== 1) {
            $this->response(array(
                'success' => false,
                'message' => 'Método de pago no encontrado o inactivo'
            ), 400);
        }

        $isMixtoEfectivoQr = false;
        if (!empty($metodo['configuracion'])) {
            $cfg = json_decode($metodo['configuracion'], true);
            if (is_array($cfg) && !empty($cfg['mixto'])) {
                $isMixtoEfectivoQr = true;
            }
        }

        if (!$isMixtoEfectivoQr && !empty($metodo['nombre'])) {
            if (trim((string)$metodo['nombre']) === 'Mixto (Efectivo + QR)') {
                $isMixtoEfectivoQr = true;
            }
        }

        if ($isMixtoEfectivoQr) {
            if ($monto_efectivo === null) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Monto en efectivo es requerido para pago mixto'
                ), 400);
            }
            if ($monto_efectivo < 0 || $monto_efectivo > $monto) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Monto en efectivo inválido para pago mixto'
                ), 400);
            }
        } else {
            $monto_efectivo = null;
        }

        $this->db->trans_start();

        $cobro_data = array(
            'id_venta' => (int)$id_venta,
            'id_usuario' => (int)$this->user['id'],
            'id_metodo_pago' => (int)$id_metodo_pago,
            'monto' => $monto,
            'monto_efectivo' => $monto_efectivo,
            'referencia' => $referencia,
        );

        $id_cobro = $this->VentaCobro_model->create($cobro_data);

        $nuevo_saldo = $saldo_actual - $monto;
        if ($nuevo_saldo < 0) {
            $nuevo_saldo = 0;
        }

        $estado_cobro = $nuevo_saldo <= 0 ? 'pagado' : 'pendiente';

        $this->db->where('id', (int)$id_venta);
        $this->db->update('ventas', array(
            'saldo' => $nuevo_saldo,
            'estado_cobro' => $estado_cobro,
            'updated_at' => date('Y-m-d H:i:s')
        ));

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->response(array(
                'success' => false,
                'message' => 'Error al registrar el cobro'
            ), 500);
        }

        $venta_actualizada = $this->Venta_model->get_by_id($id_venta);
        $cobros = $this->VentaCobro_model->get_by_venta($id_venta);

        $this->log_audit('registrar_cobro_credito', 'ventas_cobros', $id_cobro, null, $cobro_data);

        $this->response(array(
            'success' => true,
            'message' => 'Cobro registrado exitosamente',
            'data' => array(
                'venta' => $venta_actualizada,
                'cobros' => $cobros,
            )
        ), 201);
    }
}

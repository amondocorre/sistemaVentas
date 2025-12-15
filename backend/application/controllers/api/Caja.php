<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Caja / Turnos
 */
class Caja extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Caja_model');
    }

    /**
     * GET /api/caja/turnos-cerrados
     */
    public function turnos_cerrados()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('ventas_registrar');

        $filters = array(
            'fecha_inicio' => $this->input->get('fecha_inicio'),
            'fecha_fin' => $this->input->get('fecha_fin'),
            'limit' => $this->input->get('limit') ?: 50,
            'offset' => $this->input->get('offset') ?: 0,
        );

        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });

        $rows = $this->Caja_model->get_turnos_cerrados($this->user['id'], $this->user['id_sucursal'], $filters);
        $total = $this->Caja_model->count_turnos_cerrados($this->user['id'], $this->user['id_sucursal'], $filters);

        $this->response(array(
            'success' => true,
            'data' => $rows,
            'total' => $total
        ));
    }

    /**
     * GET /api/caja/turnos/:id
     */
    public function turno($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('ventas_registrar');

        $detalle = $this->Caja_model->get_turno_detalle((int)$id, $this->user['id'], $this->user['id_sucursal']);
        if (!$detalle) {
            $this->response(array(
                'success' => false,
                'message' => 'Turno no encontrado'
            ), 404);
        }

        $this->response(array(
            'success' => true,
            'data' => $detalle
        ));
    }

    /**
     * GET /api/caja/turno-abierto
     */
    public function turno_abierto()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $turno = $this->Caja_model->get_turno_abierto($this->user['id'], $this->user['id_sucursal']);

        $this->response(array(
            'success' => true,
            'data' => $turno
        ));
    }

    /**
     * POST /api/caja/apertura
     */
    public function apertura()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('ventas_registrar');

        $input = $this->get_json_input();
        $monto_inicial = isset($input['monto_inicial']) ? (float)$input['monto_inicial'] : null;

        if ($monto_inicial === null || $monto_inicial < 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Monto inicial inválido'
            ), 400);
        }

        $turno_abierto = $this->Caja_model->get_turno_abierto($this->user['id'], $this->user['id_sucursal']);
        if ($turno_abierto) {
            $this->response(array(
                'success' => false,
                'message' => 'Ya existe un turno abierto'
            ), 400);
        }

        $id_turno = $this->Caja_model->abrir_turno($this->user['id'], $this->user['id_sucursal'], $monto_inicial);
        $turno = $this->Caja_model->get_by_id($id_turno);

        $this->log_audit('apertura_caja', 'caja_turnos', $id_turno, null, $turno);

        $this->response(array(
            'success' => true,
            'message' => 'Apertura de caja registrada',
            'data' => $turno
        ), 201);
    }

    /**
     * POST /api/caja/cierre
     */
    public function cierre()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        $this->require_permission('ventas_registrar');

        $turno = $this->Caja_model->get_turno_abierto($this->user['id'], $this->user['id_sucursal']);
        if (!$turno) {
            $this->response(array(
                'success' => false,
                'message' => 'No hay un turno abierto'
            ), 400);
        }

        $input = $this->get_json_input();
        $monto_cierre_real = isset($input['monto_cierre_real']) ? (float)$input['monto_cierre_real'] : null;

        $fecha_inicio = $turno['fecha_apertura'];
        $fecha_fin = date('Y-m-d H:i:s');

        $resumen = $this->Caja_model->get_resumen_metodos_pago(
            $fecha_inicio,
            $fecha_fin,
            $this->user['id_sucursal'],
            $this->user['id']
        );

        $this->Caja_model->cerrar_turno($turno['id'], $monto_cierre_real);
        $turno_cerrado = $this->Caja_model->get_by_id($turno['id']);

        $this->log_audit('cierre_caja', 'caja_turnos', $turno['id'], $turno, $turno_cerrado);

        $this->response(array(
            'success' => true,
            'message' => 'Turno cerrado',
            'data' => array(
                'turno' => $turno_cerrado,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'resumen_metodos_pago' => $resumen
            )
        ));
    }
}

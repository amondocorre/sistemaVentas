<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Inventario
 */
class Inventario extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Inventario_model');
    }

    /**
     * GET /api/inventario
     * Lista inventario
     */
    public function index()
    {
        $filters = array(
            'id_sucursal' => $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'],
            'stock_critico' => $this->input->get('stock_critico'),
            'search' => $this->input->get('search')
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $inventario = $this->Inventario_model->get_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $inventario
        ));
    }

    /**
     * GET /api/inventario/sucursal/:id
     * Inventario por sucursal
     */
    public function por_sucursal($id_sucursal)
    {
        // Verificar acceso
        if (!$this->is_admin() && $id_sucursal != $this->user['id_sucursal']) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $inventario = $this->Inventario_model->get_por_sucursal($id_sucursal);
        
        $this->response(array(
            'success' => true,
            'data' => $inventario
        ));
    }

    /**
     * GET /api/inventario/producto/:id
     * Inventario de un producto en todas las sucursales
     */
    public function por_producto($id_producto)
    {
        $inventario = $this->Inventario_model->get_por_producto($id_producto);
        
        $this->response(array(
            'success' => true,
            'data' => $inventario
        ));
    }

    /**
     * POST /api/inventario/ajustar
     * Ajusta el stock de un producto
     */
    public function ajustar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        // Solo admin y supervisor pueden ajustar
        if (!$this->is_admin() && !$this->is_supervisor()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input['id_producto']) || !isset($input['cantidad'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto y cantidad son requeridos'
            ), 400);
        }
        
        $id_sucursal = $this->is_admin() && isset($input['id_sucursal']) 
            ? $input['id_sucursal'] 
            : $this->user['id_sucursal'];
        
        $result = $this->Inventario_model->ajustar(
            $input['id_producto'],
            $id_sucursal,
            $input['cantidad'],
            $this->user['id'],
            isset($input['motivo']) ? $input['motivo'] : null
        );
        
        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 400);
        }
        
        $this->log_audit('ajustar_inventario', 'inventario_sucursal', null, null, $input);
        
        $this->response(array(
            'success' => true,
            'message' => 'Stock ajustado exitosamente',
            'data' => array('stock_nuevo' => $result['stock_nuevo'])
        ));
    }

    /**
     * POST /api/inventario/entrada
     * Registra entrada de stock
     */
    public function entrada()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        if (!$this->is_admin() && !$this->is_supervisor()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input['id_producto']) || empty($input['cantidad']) || $input['cantidad'] <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto y cantidad válida son requeridos'
            ), 400);
        }
        
        $id_sucursal = $this->is_admin() && isset($input['id_sucursal']) 
            ? $input['id_sucursal'] 
            : $this->user['id_sucursal'];
        
        $result = $this->Inventario_model->entrada(
            $input['id_producto'],
            $id_sucursal,
            $input['cantidad'],
            $this->user['id'],
            isset($input['motivo']) ? $input['motivo'] : 'Entrada de mercadería'
        );
        
        $this->log_audit('entrada_inventario', 'inventario_sucursal', null, null, $input);
        
        $this->response(array(
            'success' => true,
            'message' => 'Entrada registrada exitosamente',
            'data' => array('stock_nuevo' => $result['stock_nuevo'])
        ));
    }

    /**
     * POST /api/inventario/transferir
     * Transfiere stock entre sucursales
     */
    public function transferir()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        // Solo admin puede transferir
        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input['id_producto']) || empty($input['id_sucursal_origen']) || 
            empty($input['id_sucursal_destino']) || empty($input['cantidad']) || $input['cantidad'] <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Todos los campos son requeridos'
            ), 400);
        }
        
        if ($input['id_sucursal_origen'] == $input['id_sucursal_destino']) {
            $this->response(array(
                'success' => false,
                'message' => 'Las sucursales deben ser diferentes'
            ), 400);
        }
        
        $result = $this->Inventario_model->transferir(
            $input['id_producto'],
            $input['id_sucursal_origen'],
            $input['id_sucursal_destino'],
            $input['cantidad'],
            $this->user['id'],
            isset($input['motivo']) ? $input['motivo'] : 'Transferencia entre sucursales'
        );
        
        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 400);
        }
        
        $this->log_audit('transferir_inventario', 'inventario_sucursal', null, null, $input);
        
        $this->response(array(
            'success' => true,
            'message' => 'Transferencia realizada exitosamente'
        ));
    }

    /**
     * GET /api/inventario/movimientos
     * Lista movimientos de inventario
     */
    public function movimientos()
    {
        $filters = array(
            'id_sucursal' => $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'],
            'id_producto' => $this->input->get('id_producto'),
            'tipo' => $this->input->get('tipo'),
            'fecha_inicio' => $this->input->get('fecha_inicio'),
            'fecha_fin' => $this->input->get('fecha_fin'),
            'limit' => $this->input->get('limit') ?: 50,
            'offset' => $this->input->get('offset') ?: 0
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $movimientos = $this->Inventario_model->get_movimientos($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $movimientos
        ));
    }
}

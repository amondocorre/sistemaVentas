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
        $this->load->model('Producto_model');
        $this->load->model('Sucursal_model');
    }

    /**
     * GET /api/inventario
     * Lista inventario
     */
    public function index()
    {
        $idSucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];

        // Si es admin y no envía sucursal, por defecto usar su sucursal (o 1)
        if ($this->is_admin() && ($idSucursal === null || $idSucursal === '')) {
            if (!empty($this->user['id_sucursal'])) {
                $idSucursal = $this->user['id_sucursal'];
            } else {
                $first = $this->Sucursal_model->get_all(array('estado' => 1));
                $idSucursal = !empty($first) ? $first[0]['id'] : null;
            }
        }

        if ($idSucursal === null || $idSucursal === '') {
            $this->response(array(
                'success' => true,
                'data' => array()
            ));
        }

        $filters = array(
            'id_sucursal' => $idSucursal,
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

        $precio_compra = isset($input['precio_compra']) && $input['precio_compra'] !== '' ? (float)$input['precio_compra'] : null;
        $precio_venta = isset($input['precio_venta']) && $input['precio_venta'] !== '' ? (float)$input['precio_venta'] : null;

        if ($precio_compra !== null && $precio_compra < 0) {
            $this->response(array('success' => false, 'message' => 'Precio de compra inválido'), 400);
        }
        if ($precio_venta !== null && $precio_venta < 0) {
            $this->response(array('success' => false, 'message' => 'Precio de venta inválido'), 400);
        }
        
        $id_sucursal = $this->is_admin() && isset($input['id_sucursal']) 
            ? $input['id_sucursal'] 
            : $this->user['id_sucursal'];
        
        $result = $this->Inventario_model->entrada(
            $input['id_producto'],
            $id_sucursal,
            $input['cantidad'],
            $this->user['id'],
            isset($input['motivo']) ? $input['motivo'] : 'Entrada de mercadería',
            $precio_compra,
            $precio_venta
        );

        if ($precio_compra !== null || $precio_venta !== null) {
            $producto = $this->Producto_model->get_by_id($input['id_producto']);
            if ($producto) {
                $data_update = array();

                if ($precio_compra !== null) {
                    $data_update['precio_compra'] = $precio_compra;
                }

                if ($precio_venta !== null) {
                    $precio_actual = isset($producto['precio_venta']) ? (float)$producto['precio_venta'] : 0;
                    $data_update['precio_venta'] = max($precio_actual, $precio_venta);
                }

                if (!empty($data_update)) {
                    $this->Producto_model->update($input['id_producto'], $data_update);
                }
            }
        }
        
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

        $producto = $this->Producto_model->get_by_id((int)$input['id_producto']);
        if (!$producto || (int)$producto['estado'] !== 1) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado o inactivo'
            ), 400);
        }

        $precio_compra = isset($producto['precio_compra']) ? (float)$producto['precio_compra'] : null;
        
        $result = $this->Inventario_model->transferir(
            $input['id_producto'],
            $input['id_sucursal_origen'],
            $input['id_sucursal_destino'],
            $input['cantidad'],
            $this->user['id'],
            isset($input['motivo']) ? $input['motivo'] : 'Transferencia entre sucursales',
            $precio_compra
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
     * POST /api/inventario/transferir-masivo
     * Transfiere múltiples productos entre sucursales
     */
    public function transferir_masivo()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }

        // Solo admin puede transferir
        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }

        $input = $this->get_json_input();

        $id_sucursal_origen = isset($input['id_sucursal_origen']) ? (int)$input['id_sucursal_origen'] : 0;
        $id_sucursal_destino = isset($input['id_sucursal_destino']) ? (int)$input['id_sucursal_destino'] : 0;
        $items = isset($input['items']) ? $input['items'] : null;
        $motivo = isset($input['motivo']) ? $input['motivo'] : 'Transferencia masiva entre sucursales';

        if ($id_sucursal_origen <= 0 || $id_sucursal_destino <= 0 || !is_array($items) || count($items) === 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Origen, destino e items son requeridos'
            ), 400);
        }

        if ($id_sucursal_origen === $id_sucursal_destino) {
            $this->response(array(
                'success' => false,
                'message' => 'Las sucursales deben ser diferentes'
            ), 400);
        }

        $prepared_items = array();
        foreach ($items as $item) {
            $id_producto = isset($item['id_producto']) ? (int)$item['id_producto'] : 0;
            $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;

            if ($id_producto <= 0 || $cantidad <= 0) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Items inválidos'
                ), 400);
            }

            $producto = $this->Producto_model->get_by_id($id_producto);
            if (!$producto || (int)$producto['estado'] !== 1) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Producto no encontrado o inactivo: ' . $id_producto
                ), 400);
            }

            $precio_compra = isset($producto['precio_compra']) ? (float)$producto['precio_compra'] : null;

            $prepared_items[] = array(
                'id_producto' => $id_producto,
                'cantidad' => $cantidad,
                'precio_compra' => $precio_compra,
            );
        }

        $result = $this->Inventario_model->transferir_masivo(
            $id_sucursal_origen,
            $id_sucursal_destino,
            $prepared_items,
            $this->user['id'],
            $motivo
        );

        if (!$result['success']) {
            $this->response(array(
                'success' => false,
                'message' => $result['message']
            ), 400);
        }

        $this->log_audit('transferir_inventario_masivo', 'inventario_sucursal', null, null, $input);

        $this->response(array(
            'success' => true,
            'message' => 'Transferencia masiva realizada exitosamente'
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

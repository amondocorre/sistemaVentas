<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Ventas
 */
class Ventas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Venta_model');
        $this->load->model('Producto_model');
        $this->load->model('Cliente_model');
        $this->load->model('Caja_model');
    }

    /**
     * GET /api/ventas
     * Lista todas las ventas
     */
    public function index()
    {
        // Verificar permisos
        if ($this->is_cajero()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $filters = array(
            'id_sucursal' => $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'],
            'id_usuario' => $this->input->get('id_usuario'),
            'estado' => $this->input->get('estado'),
            'fecha_inicio' => $this->input->get('fecha_inicio'),
            'fecha_fin' => $this->input->get('fecha_fin'),
            'id_metodo_pago' => $this->input->get('id_metodo_pago'),
            'limit' => $this->input->get('limit') ?: 50,
            'offset' => $this->input->get('offset') ?: 0
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $ventas = $this->Venta_model->get_all($filters);
        $total = $this->Venta_model->count_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $ventas,
            'total' => $total
        ));
    }

    /**
     * GET /api/ventas/:id
     * Obtiene una venta por ID
     */
    public function show($id)
    {
        $venta = $this->Venta_model->get_by_id($id);
        
        if (!$venta) {
            $this->response(array(
                'success' => false,
                'message' => 'Venta no encontrada'
            ), 404);
        }
        
        // Verificar acceso
        if (!$this->is_admin() && $venta['id_sucursal'] != $this->user['id_sucursal']) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        // Cajero solo puede ver sus propias ventas
        if ($this->is_cajero() && $venta['id_usuario'] != $this->user['id']) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $venta
        ));
    }

    /**
     * POST /api/ventas
     * Crea una nueva venta
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('ventas_registrar');

        $turno = $this->Caja_model->get_turno_abierto($this->user['id'], $this->user['id_sucursal']);
        if (!$turno) {
            $this->response(array(
                'success' => false,
                'message' => 'Debe realizar apertura de caja antes de registrar ventas'
            ), 400);
        }
        
        $input = $this->get_json_input();

        $tipo_venta = isset($input['tipo_venta']) ? $input['tipo_venta'] : 'contado';
        if (!in_array($tipo_venta, array('contado', 'credito'), true)) {
            $this->response(array(
                'success' => false,
                'message' => 'Tipo de venta inválido'
            ), 400);
        }

        if ($tipo_venta === 'contado' && empty($input['id_metodo_pago'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Método de pago es requerido'
            ), 400);
        }

        $metodoPago = null;
        $isMixtoEfectivoQr = false;
        if ($tipo_venta === 'contado' && !empty($input['id_metodo_pago'])) {
            $metodoPago = $this->db->get_where('metodos_pago', array('id' => (int)$input['id_metodo_pago']))->row_array();
            if ($metodoPago && !empty($metodoPago['configuracion'])) {
                $cfg = json_decode($metodoPago['configuracion'], true);
                if (is_array($cfg) && !empty($cfg['mixto'])) {
                    $isMixtoEfectivoQr = true;
                }
            }
        }

        if ($tipo_venta === 'credito' && empty($input['id_cliente'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Cliente es requerido para venta a crédito'
            ), 400);
        }

        if ($tipo_venta === 'credito') {
            $cliente = $this->Cliente_model->get_by_id((int)$input['id_cliente']);
            if (!$cliente || (int)$cliente['estado'] !== 1) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Cliente no encontrado o inactivo'
                ), 400);
            }
        }

        // Validar campos requeridos
        if (empty($input['items']) || !is_array($input['items'])) {
            $this->response(array(
                'success' => false,
                'message' => 'Items son requeridos'
            ), 400);
        }
        
        if (count($input['items']) === 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Debe agregar al menos un producto'
            ), 400);
        }
        
        // Calcular totales y preparar detalle
        $subtotal = 0;
        $detalle = array();
        
        foreach ($input['items'] as $item) {
            if (empty($item['id_producto']) || empty($item['cantidad']) || $item['cantidad'] <= 0) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Datos de producto inválidos'
                ), 400);
            }
            
            // Obtener producto
            $producto = $this->Producto_model->get_by_id($item['id_producto']);
            
            if (!$producto || $producto['estado'] != 1) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Producto no encontrado o inactivo: ' . $item['id_producto']
                ), 400);
            }
            
            // Verificar stock
            $stock = $this->Producto_model->get_stock_sucursal($item['id_producto'], $this->user['id_sucursal']);
            
            if ($stock < $item['cantidad']) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Stock insuficiente para: ' . $producto['nombre']
                ), 400);
            }
            
            $precio_sugerido = (float)$producto['precio_venta'];
            $precio_unitario = isset($item['precio_unitario']) && $item['precio_unitario'] !== ''
                ? (float)$item['precio_unitario']
                : $precio_sugerido;

            if ($precio_unitario < 0) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Precio unitario inválido para: ' . $producto['nombre']
                ), 400);
            }

            $precio_compra = isset($producto['precio_compra']) ? (float)$producto['precio_compra'] : 0;

            if ($precio_compra > 0 && $precio_unitario < $precio_compra) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El precio de venta no puede ser menor al precio de costo para: ' . $producto['nombre']
                ), 400);
            }

            $descuento = isset($item['descuento']) ? $item['descuento'] : 0;
            $item_subtotal = ($precio_unitario * $item['cantidad']) - $descuento;
            
            $detalle[] = array(
                'id_producto' => $item['id_producto'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $precio_unitario,
                'precio_compra' => $precio_compra,
                'descuento' => $descuento,
                'subtotal' => $item_subtotal
            );
            
            $subtotal += $item_subtotal;
        }
        
        // Calcular totales
        $descuento_general = isset($input['descuento']) ? $input['descuento'] : 0;
        $impuesto = isset($input['impuesto']) ? $input['impuesto'] : 0;
        $total = $subtotal - $descuento_general + $impuesto;

        $monto_efectivo = null;
        if ($tipo_venta === 'contado' && $isMixtoEfectivoQr) {
            if (!isset($input['monto_efectivo'])) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Monto en efectivo es requerido para pago mixto'
                ), 400);
            }

            $monto_efectivo = (float)$input['monto_efectivo'];
            if ($monto_efectivo < 0 || $monto_efectivo > $total) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Monto en efectivo inválido para pago mixto'
                ), 400);
            }
        }
        
        // Preparar datos de venta
        $data = array(
            'id_usuario' => $this->user['id'],
            'id_sucursal' => $this->user['id_sucursal'],
            'id_metodo_pago' => $tipo_venta === 'contado' ? $input['id_metodo_pago'] : null,
            'id_cliente' => $tipo_venta === 'credito' ? (int)$input['id_cliente'] : null,
            'tipo_venta' => $tipo_venta,
            'subtotal' => $subtotal,
            'descuento' => $descuento_general,
            'impuesto' => $impuesto,
            'total' => $total,
            'monto_efectivo' => $monto_efectivo,
            'referencia_pago' => $tipo_venta === 'contado' && isset($input['referencia_pago']) ? $input['referencia_pago'] : null,
            'cliente_nombre' => isset($input['cliente_nombre']) ? $input['cliente_nombre'] : null,
            'cliente_nit' => isset($input['cliente_nit']) ? $input['cliente_nit'] : null,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null,
            'estado' => 'completada',
            'saldo' => $tipo_venta === 'credito' ? $total : 0,
            'estado_cobro' => $tipo_venta === 'credito' ? 'pendiente' : 'pagado'
        );
        
        // Crear venta
        $id_venta = $this->Venta_model->create($data, $detalle);
        
        if (!$id_venta) {
            $this->response(array(
                'success' => false,
                'message' => 'Error al crear la venta'
            ), 500);
        }
        
        // Obtener venta creada
        $venta = $this->Venta_model->get_by_id($id_venta);
        
        // Registrar auditoría
        $this->log_audit('crear_venta', 'ventas', $id_venta, null, $data);
        
        $this->response(array(
            'success' => true,
            'message' => 'Venta registrada exitosamente',
            'data' => $venta
        ), 201);
    }

    /**
     * POST /api/ventas/:id/anular
     * Anula una venta
     */
    public function anular($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        // Solo admin puede anular
        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $venta = $this->Venta_model->get_by_id($id);
        
        if (!$venta) {
            $this->response(array(
                'success' => false,
                'message' => 'Venta no encontrada'
            ), 404);
        }
        
        if ($venta['estado'] !== 'completada') {
            $this->response(array(
                'success' => false,
                'message' => 'Solo se pueden anular ventas completadas'
            ), 400);
        }
        
        $result = $this->Venta_model->anular($id, $this->user['id']);
        
        if (!$result) {
            $this->response(array(
                'success' => false,
                'message' => 'Error al anular la venta'
            ), 500);
        }
        
        $this->log_audit('anular_venta', 'ventas', $id, $venta, array('estado' => 'anulada'));
        
        $this->response(array(
            'success' => true,
            'message' => 'Venta anulada exitosamente'
        ));
    }

    /**
     * GET /api/ventas/mis-ventas
     * Obtiene las ventas del usuario actual
     */
    public function mis_ventas()
    {
        $filters = array(
            'id_usuario' => $this->user['id'],
            'fecha_inicio' => $this->input->get('fecha_inicio') ?: date('Y-m-d'),
            'fecha_fin' => $this->input->get('fecha_fin') ?: date('Y-m-d'),
            'limit' => $this->input->get('limit') ?: 50,
            'offset' => $this->input->get('offset') ?: 0
        );
        
        $ventas = $this->Venta_model->get_all($filters);
        $total = $this->Venta_model->count_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $ventas,
            'total' => $total
        ));
    }
}

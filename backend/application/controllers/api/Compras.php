<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Compras extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Compra_model');
        $this->load->model('Proveedor_model');
        $this->load->model('Producto_model');
    }

    public function index()
    {
        $this->require_permission('compras_ver');
        
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        
        $filters = array(
            'estado' => $this->input->get('estado') !== null ? $this->input->get('estado') : 1,
            'id_proveedor' => $this->input->get('id_proveedor'),
            'id_sucursal' => $id_sucursal,
            'tipo_pago' => $this->input->get('tipo_pago'),
            'estado_pago' => $this->input->get('estado_pago'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta'),
            'search' => $this->input->get('search'),
            'limit' => $this->input->get('limit'),
            'offset' => $this->input->get('offset')
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $compras = $this->Compra_model->get_all($filters);
        $total = $this->Compra_model->count_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $compras,
            'total' => $total
        ));
    }

    public function show($id)
    {
        $this->require_permission('compras_ver');
        
        $compra = $this->Compra_model->get_by_id($id);
        
        if (!$compra) {
            $this->response(array(
                'success' => false,
                'message' => 'Compra no encontrada'
            ), 404);
        }
        
        if (!$this->is_admin() && $compra['id_sucursal'] != $this->user['id_sucursal']) {
            $this->response(array(
                'success' => false,
                'message' => 'No tiene permisos para ver esta compra'
            ), 403);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $compra
        ));
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('compras_crear');
        
        $input = $this->get_json_input();
        
        if (empty($input['id_proveedor'])) {
            $this->response(array(
                'success' => false,
                'message' => 'El proveedor es requerido'
            ), 400);
        }
        
        if (empty($input['tipo_pago']) || !in_array($input['tipo_pago'], array('contado', 'credito'))) {
            $this->response(array(
                'success' => false,
                'message' => 'El tipo de pago es requerido (contado o credito)'
            ), 400);
        }
        
        if (empty($input['detalle']) || !is_array($input['detalle']) || count($input['detalle']) === 0) {
            $this->response(array(
                'success' => false,
                'message' => 'Debe agregar al menos un producto'
            ), 400);
        }
        
        $proveedor = $this->Proveedor_model->get_by_id($input['id_proveedor']);
        if (!$proveedor) {
            $this->response(array(
                'success' => false,
                'message' => 'Proveedor no encontrado'
            ), 404);
        }
        
        $subtotal = 0;
        $detalle_validado = array();
        
        foreach ($input['detalle'] as $item) {
            if (empty($item['id_producto']) || empty($item['cantidad']) || empty($item['costo_unitario'])) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Datos incompletos en el detalle de productos'
                ), 400);
            }
            
            $producto = $this->Producto_model->get_by_id($item['id_producto']);
            if (!$producto) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Producto no encontrado: ' . $item['id_producto']
                ), 404);
            }
            
            $cantidad = intval($item['cantidad']);
            $costo_unitario = floatval($item['costo_unitario']);
            $item_subtotal = $cantidad * $costo_unitario;
            
            $detalle_validado[] = array(
                'id_producto' => $item['id_producto'],
                'cantidad' => $cantidad,
                'costo_unitario' => $costo_unitario,
                'subtotal' => $item_subtotal
            );
            
            $subtotal += $item_subtotal;
        }
        
        $pagos_contado = array();
        if ($input['tipo_pago'] === 'contado' && isset($input['pagos_contado'])) {
            if (!is_array($input['pagos_contado']) || count($input['pagos_contado']) === 0) {
                $this->response(array(
                    'success' => false,
                    'message' => 'Debe detallar los pagos cuando utiliza el método mixto'
                ), 400);
            }
            
            $total_pagos = 0;
            foreach ($input['pagos_contado'] as $pago) {
                if (empty($pago['metodo_pago'])) {
                    $this->response(array(
                        'success' => false,
                        'message' => 'Cada pago debe indicar el método utilizado'
                    ), 400);
                }
                
                if (!isset($pago['monto'])) {
                    $this->response(array(
                        'success' => false,
                        'message' => 'Cada pago debe indicar el monto'
                    ), 400);
                }
                
                $monto_pago = floatval($pago['monto']);
                if ($monto_pago <= 0) {
                    $this->response(array(
                        'success' => false,
                        'message' => 'Los montos de pago deben ser mayores a cero'
                    ), 400);
                }
                
                $total_pagos += $monto_pago;
                
                $pagos_contado[] = array(
                    'metodo_pago' => $pago['metodo_pago'],
                    'monto' => $monto_pago,
                    'referencia' => isset($pago['referencia']) ? $pago['referencia'] : null,
                    'observaciones' => isset($pago['observaciones']) ? $pago['observaciones'] : null
                );
            }
            
            if (round($total_pagos, 2) !== round($subtotal, 2)) {
                $this->response(array(
                    'success' => false,
                    'message' => 'La suma de los pagos debe coincidir con el total de la compra'
                ), 400);
            }
        }
        
        $id_sucursal = $this->is_admin() && !empty($input['id_sucursal']) 
            ? $input['id_sucursal'] 
            : $this->user['id_sucursal'];
        
        $data = array(
            'id_proveedor' => $input['id_proveedor'],
            'id_usuario' => $this->user['id'],
            'id_sucursal' => $id_sucursal,
            'fecha_compra' => isset($input['fecha_compra']) ? $input['fecha_compra'] : date('Y-m-d H:i:s'),
            'tipo_pago' => $input['tipo_pago'],
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null,
            'detalle' => $detalle_validado
        );
        
        if ($input['tipo_pago'] === 'credito' && isset($input['monto_pagado'])) {
            $data['monto_pagado'] = floatval($input['monto_pagado']);
            $data['metodo_pago'] = isset($input['metodo_pago']) ? $input['metodo_pago'] : null;
        }
        
        if ($input['tipo_pago'] === 'contado' && !empty($pagos_contado)) {
            $data['pagos_contado'] = $pagos_contado;
        } elseif ($input['tipo_pago'] === 'contado' && isset($input['metodo_pago'])) {
            $data['metodo_pago'] = $input['metodo_pago'];
        }
        
        $id_compra = $this->Compra_model->create($data);
        
        if ($id_compra) {
            $this->log_audit('crear_compra', 'compras', $id_compra, null, $data);
            
            $compra = $this->Compra_model->get_by_id($id_compra);
            
            $this->response(array(
                'success' => true,
                'message' => 'Compra registrada exitosamente',
                'data' => $compra
            ), 201);
        } else {
            $this->response(array(
                'success' => false,
                'message' => 'Error al registrar la compra'
            ), 500);
        }
    }

    public function registrar_pago($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('compras_pagar');
        
        $compra = $this->Compra_model->get_by_id($id);
        
        if (!$compra) {
            $this->response(array(
                'success' => false,
                'message' => 'Compra no encontrada'
            ), 404);
        }
        
        if ($compra['tipo_pago'] !== 'credito') {
            $this->response(array(
                'success' => false,
                'message' => 'Esta compra no es a crédito'
            ), 400);
        }
        
        if ($compra['estado_pago'] === 'pagado') {
            $this->response(array(
                'success' => false,
                'message' => 'Esta compra ya está completamente pagada'
            ), 400);
        }
        
        $input = $this->get_json_input();
        
        if (empty($input['monto']) || floatval($input['monto']) <= 0) {
            $this->response(array(
                'success' => false,
                'message' => 'El monto debe ser mayor a cero'
            ), 400);
        }
        
        $monto = floatval($input['monto']);
        
        if ($monto > $compra['saldo_pendiente']) {
            $this->response(array(
                'success' => false,
                'message' => 'El monto excede el saldo pendiente'
            ), 400);
        }
        
        $data = array(
            'id_usuario' => $this->user['id'],
            'monto' => $monto,
            'fecha_pago' => isset($input['fecha_pago']) ? $input['fecha_pago'] : date('Y-m-d H:i:s'),
            'metodo_pago' => isset($input['metodo_pago']) ? $input['metodo_pago'] : null,
            'referencia' => isset($input['referencia']) ? $input['referencia'] : null,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null
        );
        
        $success = $this->Compra_model->registrar_pago($id, $data);
        
        if ($success) {
            $this->log_audit('registrar_pago_compra', 'compras_pagos', $id, null, $data);
            
            $compra_actualizada = $this->Compra_model->get_by_id($id);
            
            $this->response(array(
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => $compra_actualizada
            ));
        } else {
            $this->response(array(
                'success' => false,
                'message' => 'Error al registrar el pago'
            ), 500);
        }
    }

    public function pendientes()
    {
        $this->require_permission('compras_ver');
        
        $id_proveedor = $this->input->get('id_proveedor');
        
        $compras = $this->Compra_model->get_compras_pendientes($id_proveedor);
        
        $this->response(array(
            'success' => true,
            'data' => $compras
        ));
    }

    public function deuda_proveedor($id_proveedor)
    {
        $this->require_permission('compras_ver');
        
        $total_deuda = $this->Compra_model->get_total_deuda_proveedor($id_proveedor);
        
        $this->response(array(
            'success' => true,
            'data' => array(
                'id_proveedor' => $id_proveedor,
                'total_deuda' => $total_deuda
            )
        ));
    }
}

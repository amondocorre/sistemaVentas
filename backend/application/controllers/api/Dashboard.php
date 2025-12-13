<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Dashboard
 * Retorna datos según el rol del usuario
 */
class Dashboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Venta_model');
        $this->load->model('Inventario_model');
        $this->load->model('Producto_model');
    }

    /**
     * GET /api/dashboard
     * Retorna datos del dashboard según el rol
     */
    public function index()
    {
        $rol = $this->user['rol'];
        $id_sucursal = $this->user['id_sucursal'];
        
        $data = array();
        
        switch ($rol) {
            case 'admin':
                $data = $this->get_dashboard_admin();
                break;
            case 'supervisor':
                $data = $this->get_dashboard_supervisor($id_sucursal);
                break;
            case 'cajero':
                $data = $this->get_dashboard_cajero($id_sucursal);
                break;
            default:
                $data = $this->get_dashboard_basico($id_sucursal);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $data
        ));
    }

    /**
     * Dashboard completo para Admin
     */
    protected function get_dashboard_admin()
    {
        return array(
            'ventas_dia' => $this->Venta_model->get_ventas_dia(),
            'ventas_sucursal' => $this->Venta_model->get_ventas_por_sucursal(date('Y-m-d'), date('Y-m-d')),
            'top_productos' => $this->Venta_model->get_top_productos(5),
            'ventas_7_dias' => $this->Venta_model->get_ventas_periodo(7),
            'ventas_30_dias' => $this->Venta_model->get_ventas_periodo(30),
            'ingresos_metodo_pago' => $this->Venta_model->get_ingresos_metodo_pago(date('Y-m-01'), date('Y-m-d')),
            'utilidad' => $this->Venta_model->get_utilidad(date('Y-m-01'), date('Y-m-d')),
            'inventario' => $this->Inventario_model->get_resumen_global(),
            'stock_critico' => $this->Inventario_model->get_stock_critico(null, 10)
        );
    }

    /**
     * Dashboard para Supervisor
     */
    protected function get_dashboard_supervisor($id_sucursal)
    {
        return array(
            'ventas_dia' => $this->Venta_model->get_ventas_dia($id_sucursal),
            'ventas_7_dias' => $this->Venta_model->get_ventas_periodo(7, $id_sucursal),
            'top_productos' => $this->Venta_model->get_top_productos(5, $id_sucursal),
            'inventario' => $this->Inventario_model->get_por_sucursal($id_sucursal),
            'stock_critico' => $this->Inventario_model->get_stock_critico($id_sucursal, 10),
            'movimientos' => $this->Inventario_model->get_movimientos(array(
                'id_sucursal' => $id_sucursal,
                'limit' => 10
            ))
        );
    }

    /**
     * Dashboard básico para Cajero
     */
    protected function get_dashboard_cajero($id_sucursal)
    {
        return array(
            'stock_sucursal' => $this->Inventario_model->get_por_sucursal($id_sucursal),
            'stock_critico' => $this->Inventario_model->get_stock_critico($id_sucursal, 10),
            'mis_ventas_hoy' => $this->Venta_model->count_ventas_usuario($this->user['id'], date('Y-m-d'))
        );
    }

    /**
     * Dashboard básico genérico
     */
    protected function get_dashboard_basico($id_sucursal)
    {
        return array(
            'stock_critico' => $this->Inventario_model->get_stock_critico($id_sucursal, 10)
        );
    }

    /**
     * GET /api/dashboard/ventas-dia
     */
    public function ventas_dia()
    {
        $id_sucursal = $this->is_admin() ? null : $this->user['id_sucursal'];
        
        $this->response(array(
            'success' => true,
            'data' => $this->Venta_model->get_ventas_dia($id_sucursal)
        ));
    }

    /**
     * GET /api/dashboard/ventas-sucursal
     */
    public function ventas_sucursal()
    {
        if (!$this->is_admin() && !$this->is_supervisor()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        
        $this->response(array(
            'success' => true,
            'data' => $this->Venta_model->get_ventas_por_sucursal($fecha_inicio, $fecha_fin)
        ));
    }

    /**
     * GET /api/dashboard/top-productos
     */
    public function top_productos()
    {
        $limit = $this->input->get('limit') ?: 10;
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        
        $this->response(array(
            'success' => true,
            'data' => $this->Venta_model->get_top_productos($limit, $id_sucursal)
        ));
    }

    /**
     * GET /api/dashboard/ventas-periodo
     */
    public function ventas_periodo()
    {
        if (!$this->is_admin() && !$this->is_supervisor()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $dias = $this->input->get('dias') ?: 7;
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        
        $this->response(array(
            'success' => true,
            'data' => $this->Venta_model->get_ventas_periodo($dias, $id_sucursal)
        ));
    }

    /**
     * GET /api/dashboard/ingresos-metodo-pago
     */
    public function ingresos_metodo_pago()
    {
        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        
        $this->response(array(
            'success' => true,
            'data' => $this->Venta_model->get_ingresos_metodo_pago($fecha_inicio, $fecha_fin)
        ));
    }

    /**
     * GET /api/dashboard/stock-critico
     */
    public function stock_critico()
    {
        $limit = $this->input->get('limit') ?: 20;
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        
        $this->response(array(
            'success' => true,
            'data' => $this->Inventario_model->get_stock_critico($id_sucursal, $limit)
        ));
    }

    /**
     * GET /api/dashboard/resumen-inventario
     */
    public function resumen_inventario()
    {
        if (!$this->is_admin()) {
            $this->response(array('success' => false, 'message' => 'No autorizado'), 403);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $this->Inventario_model->get_resumen_global()
        ));
    }
}

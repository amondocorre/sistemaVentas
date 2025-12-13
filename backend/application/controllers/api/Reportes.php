<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Reportes
 * Genera reportes en JSON, Excel y PDF
 */
class Reportes extends MY_Controller
{
    protected $allowed_roles = array('admin');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Venta_model');
        $this->load->model('Producto_model');
        $this->load->model('Inventario_model');
    }

    /**
     * GET /api/reportes/ventas
     */
    public function ventas()
    {
        $filters = array(
            'id_sucursal' => $this->input->get('id_sucursal'),
            'fecha_inicio' => $this->input->get('fecha_inicio') ?: date('Y-m-01'),
            'fecha_fin' => $this->input->get('fecha_fin') ?: date('Y-m-d'),
            'estado' => $this->input->get('estado') ?: 'completada'
        );
        
        $ventas = $this->Venta_model->get_all($filters);
        $totales = $this->calcular_totales_ventas($ventas);
        
        $this->response(array(
            'success' => true,
            'data' => array(
                'ventas' => $ventas,
                'totales' => $totales,
                'filtros' => $filters
            )
        ));
    }

    /**
     * GET /api/reportes/ventas/excel
     */
    public function ventas_excel()
    {
        $filters = array(
            'id_sucursal' => $this->input->get('id_sucursal'),
            'fecha_inicio' => $this->input->get('fecha_inicio') ?: date('Y-m-01'),
            'fecha_fin' => $this->input->get('fecha_fin') ?: date('Y-m-d'),
            'estado' => 'completada'
        );
        
        $ventas = $this->Venta_model->get_all($filters);
        
        // Generar CSV (compatible con Excel)
        $filename = 'reporte_ventas_' . date('Y-m-d_His') . '.csv';
        
        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, array('Nº Venta', 'Fecha', 'Sucursal', 'Usuario', 'Método Pago', 'Subtotal', 'Descuento', 'Total', 'Estado'), ';');
        
        foreach ($ventas as $venta) {
            fputcsv($output, array(
                $venta['numero_venta'],
                $venta['fecha_venta'],
                $venta['sucursal'],
                $venta['usuario'],
                $venta['metodo_pago'],
                $venta['subtotal'],
                $venta['descuento'],
                $venta['total'],
                $venta['estado']
            ), ';');
        }
        
        fclose($output);
        exit();
    }

    /**
     * GET /api/reportes/ventas/pdf
     */
    public function ventas_pdf()
    {
        $filters = array(
            'id_sucursal' => $this->input->get('id_sucursal'),
            'fecha_inicio' => $this->input->get('fecha_inicio') ?: date('Y-m-01'),
            'fecha_fin' => $this->input->get('fecha_fin') ?: date('Y-m-d'),
            'estado' => 'completada'
        );
        
        $ventas = $this->Venta_model->get_all($filters);
        $totales = $this->calcular_totales_ventas($ventas);
        
        // Generar HTML para PDF
        $html = $this->generar_html_reporte_ventas($ventas, $totales, $filters);
        
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit();
    }

    /**
     * GET /api/reportes/productos
     */
    public function productos()
    {
        $filters = array(
            'estado' => $this->input->get('estado'),
            'id_categoria' => $this->input->get('id_categoria'),
            'id_marca' => $this->input->get('id_marca')
        );
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $productos = $this->Producto_model->get_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => array(
                'productos' => $productos,
                'total' => count($productos)
            )
        ));
    }

    /**
     * GET /api/reportes/productos/excel
     */
    public function productos_excel()
    {
        $productos = $this->Producto_model->get_all(array('estado' => 1));
        
        $filename = 'reporte_productos_' . date('Y-m-d_His') . '.csv';
        
        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array('Código', 'Nombre', 'Categoría', 'Marca', 'Precio Compra', 'Precio Venta', 'Stock Mínimo'), ';');
        
        foreach ($productos as $producto) {
            fputcsv($output, array(
                $producto['codigo_barras'],
                $producto['nombre'],
                $producto['categoria'],
                $producto['marca'],
                $producto['precio_compra'],
                $producto['precio_venta'],
                $producto['stock_minimo']
            ), ';');
        }
        
        fclose($output);
        exit();
    }

    /**
     * GET /api/reportes/stock
     */
    public function stock()
    {
        $id_sucursal = $this->input->get('id_sucursal');
        
        $inventario = $this->Inventario_model->get_all(array('id_sucursal' => $id_sucursal));
        $resumen = $this->Inventario_model->get_resumen_global();
        
        $this->response(array(
            'success' => true,
            'data' => array(
                'inventario' => $inventario,
                'resumen' => $resumen
            )
        ));
    }

    /**
     * GET /api/reportes/stock/excel
     */
    public function stock_excel()
    {
        $id_sucursal = $this->input->get('id_sucursal');
        $inventario = $this->Inventario_model->get_all(array('id_sucursal' => $id_sucursal));
        
        $filename = 'reporte_stock_' . date('Y-m-d_His') . '.csv';
        
        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array('Código', 'Producto', 'Categoría', 'Sucursal', 'Stock', 'Stock Mínimo', 'Precio Venta'), ';');
        
        foreach ($inventario as $item) {
            fputcsv($output, array(
                $item['codigo_barras'],
                $item['producto'],
                $item['categoria'],
                $item['sucursal'],
                $item['stock'],
                $item['stock_minimo'],
                $item['precio_venta']
            ), ';');
        }
        
        fclose($output);
        exit();
    }

    /**
     * GET /api/reportes/top-productos
     */
    public function top_productos()
    {
        $limit = $this->input->get('limit') ?: 20;
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        $id_sucursal = $this->input->get('id_sucursal');
        
        $top = $this->Venta_model->get_top_productos($limit, $id_sucursal, $fecha_inicio, $fecha_fin);
        
        $this->response(array(
            'success' => true,
            'data' => $top
        ));
    }

    /**
     * GET /api/reportes/top-productos/excel
     */
    public function top_productos_excel()
    {
        $limit = $this->input->get('limit') ?: 20;
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        $id_sucursal = $this->input->get('id_sucursal');

        $top = $this->Venta_model->get_top_productos($limit, $id_sucursal, $fecha_inicio, $fecha_fin);

        $filename = 'reporte_top_productos_' . date('Y-m-d_His') . '.csv';

        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, array('Producto', 'Código', 'Cantidad Vendida', 'Total Vendido'), ';');

        foreach ($top as $item) {
            fputcsv($output, array(
                $item['nombre'],
                $item['codigo_barras'],
                $item['cantidad_vendida'],
                $item['total_vendido']
            ), ';');
        }

        fclose($output);
        exit();
    }

    /**
     * GET /api/reportes/metodos-pago
     */
    public function metodos_pago()
    {
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        $id_sucursal = $this->input->get('id_sucursal');
        
        $ingresos = $this->Venta_model->get_ingresos_metodo_pago($fecha_inicio, $fecha_fin, $id_sucursal);
        
        $this->response(array(
            'success' => true,
            'data' => $ingresos
        ));
    }

    /**
     * GET /api/reportes/metodos-pago/excel
     */
    public function metodos_pago_excel()
    {
        $fecha_inicio = $this->input->get('fecha_inicio') ?: date('Y-m-01');
        $fecha_fin = $this->input->get('fecha_fin') ?: date('Y-m-d');
        $id_sucursal = $this->input->get('id_sucursal');

        $ingresos = $this->Venta_model->get_ingresos_metodo_pago($fecha_inicio, $fecha_fin, $id_sucursal);

        $filename = 'reporte_metodos_pago_' . date('Y-m-d_His') . '.csv';

        header('Access-Control-Expose-Headers: Content-Disposition');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, array('Método de Pago', 'Cantidad', 'Total'), ';');

        foreach ($ingresos as $item) {
            fputcsv($output, array(
                $item['metodo_pago'],
                $item['cantidad'],
                $item['total'],
            ), ';');
        }

        fclose($output);
        exit();
    }

    /**
     * Calcula totales de ventas
     */
    protected function calcular_totales_ventas($ventas)
    {
        $total_ventas = count($ventas);
        $total_monto = 0;
        $total_descuentos = 0;
        
        foreach ($ventas as $venta) {
            $total_monto += $venta['total'];
            $total_descuentos += $venta['descuento'];
        }
        
        return array(
            'cantidad' => $total_ventas,
            'monto' => $total_monto,
            'descuentos' => $total_descuentos
        );
    }

    /**
     * Genera HTML para reporte de ventas (para imprimir/PDF)
     */
    protected function generar_html_reporte_ventas($ventas, $totales, $filters)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4a5568; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .totales { margin-top: 20px; font-weight: bold; }
        .fecha { text-align: center; color: #666; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Reporte de Ventas</h1>
    <p class="fecha">Período: ' . $filters['fecha_inicio'] . ' al ' . $filters['fecha_fin'] . '</p>
    
    <table>
        <thead>
            <tr>
                <th>Nº Venta</th>
                <th>Fecha</th>
                <th>Sucursal</th>
                <th>Usuario</th>
                <th>Método Pago</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($ventas as $venta) {
            $html .= '<tr>
                <td>' . htmlspecialchars($venta['numero_venta']) . '</td>
                <td>' . $venta['fecha_venta'] . '</td>
                <td>' . htmlspecialchars($venta['sucursal']) . '</td>
                <td>' . htmlspecialchars($venta['usuario']) . '</td>
                <td>' . htmlspecialchars($venta['metodo_pago']) . '</td>
                <td>' . number_format($venta['total'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
    </table>
    
    <div class="totales">
        <p>Total de ventas: ' . $totales['cantidad'] . '</p>
        <p>Monto total: ' . number_format($totales['monto'], 2) . '</p>
    </div>
    
    <script>window.print();</script>
</body>
</html>';
        
        return $html;
    }
}

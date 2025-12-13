<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Controlador de Productos
 */
class Productos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Producto_model');
    }

    /**
     * GET /api/productos
     * Lista todos los productos
     */
    public function index()
    {
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        if ($this->is_admin() && ($id_sucursal === null || $id_sucursal === '')) {
            $id_sucursal = isset($this->user['id_sucursal']) ? $this->user['id_sucursal'] : null;
        }

        $filters = array(
            'estado' => $this->input->get('estado') !== null ? $this->input->get('estado') : 1,
            'id_categoria' => $this->input->get('id_categoria'),
            'id_marca' => $this->input->get('id_marca'),
            'search' => $this->input->get('search'),
            'limit' => $this->input->get('limit'),
            'offset' => $this->input->get('offset'),
            'id_sucursal' => $id_sucursal
        );
        
        // Limpiar filtros vacíos
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
        $productos = $this->Producto_model->get_all($filters);
        $total = $this->Producto_model->count_all($filters);
        
        $this->response(array(
            'success' => true,
            'data' => $productos,
            'total' => $total
        ));
    }

    /**
     * GET /api/productos/:id
     * Obtiene un producto por ID
     */
    public function show($id)
    {
        $producto = $this->Producto_model->get_by_id($id);
        
        if (!$producto) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado'
            ), 404);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $producto
        ));
    }

    /**
     * POST /api/productos
     * Crea un nuevo producto
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('productos_crear');
        
        $input = $this->get_json_input();
        
        // Validar campos requeridos
        if (empty($input['nombre'])) {
            $this->response(array(
                'success' => false,
                'message' => 'El nombre es requerido'
            ), 400);
        }
        
        // Verificar código de barras único
        if (!empty($input['codigo_barras'])) {
            if ($this->Producto_model->codigo_barras_exists($input['codigo_barras'])) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El código de barras ya existe'
                ), 400);
            }
        }
        
        $data = array(
            'codigo_barras' => isset($input['codigo_barras']) ? $input['codigo_barras'] : null,
            'nombre' => $input['nombre'],
            'descripcion' => isset($input['descripcion']) ? $input['descripcion'] : null,
            'id_categoria' => isset($input['id_categoria']) ? $input['id_categoria'] : null,
            'id_marca' => isset($input['id_marca']) ? $input['id_marca'] : null,
            'precio_compra' => isset($input['precio_compra']) ? $input['precio_compra'] : 0,
            'precio_venta' => isset($input['precio_venta']) ? $input['precio_venta'] : 0,
            'stock_minimo' => isset($input['stock_minimo']) ? $input['stock_minimo'] : 5,
            'imagen_principal' => isset($input['imagen_principal']) ? $input['imagen_principal'] : null,
            'estado' => isset($input['estado']) ? $input['estado'] : 1
        );
        
        $id = $this->Producto_model->create($data);
        
        // Registrar auditoría
        $this->log_audit('crear_producto', 'productos', $id, null, $data);
        
        $this->response(array(
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'data' => array('id' => $id)
        ), 201);
    }

    /**
     * PUT /api/productos/:id
     * Actualiza un producto
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('productos_editar');
        
        $producto = $this->Producto_model->get_by_id($id);
        
        if (!$producto) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado'
            ), 404);
        }
        
        $input = $this->get_json_input();
        
        // Verificar código de barras único
        if (!empty($input['codigo_barras']) && $input['codigo_barras'] !== $producto['codigo_barras']) {
            if ($this->Producto_model->codigo_barras_exists($input['codigo_barras'], $id)) {
                $this->response(array(
                    'success' => false,
                    'message' => 'El código de barras ya existe'
                ), 400);
            }
        }
        
        $data = array();
        $campos = array('codigo_barras', 'nombre', 'descripcion', 'id_categoria', 'id_marca', 
                       'precio_compra', 'precio_venta', 'stock_minimo', 'imagen_principal', 'estado');
        
        foreach ($campos as $campo) {
            if (isset($input[$campo])) {
                $data[$campo] = $input[$campo];
            }
        }
        
        if (!empty($data)) {
            $this->Producto_model->update($id, $data);
            $this->log_audit('actualizar_producto', 'productos', $id, $producto, $data);
        }
        
        $this->response(array(
            'success' => true,
            'message' => 'Producto actualizado exitosamente'
        ));
    }

    /**
     * DELETE /api/productos/:id
     * Elimina (desactiva) un producto
     */
    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('productos_editar');
        
        $producto = $this->Producto_model->get_by_id($id);
        
        if (!$producto) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado'
            ), 404);
        }
        
        $this->Producto_model->delete($id);
        $this->log_audit('eliminar_producto', 'productos', $id, $producto, null);
        
        $this->response(array(
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ));
    }

    /**
     * GET /api/productos/buscar
     * Busca productos por nombre o código
     */
    public function buscar()
    {
        $term = $this->input->get('q');
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        $limit = $this->input->get('limit') ?: 20;
        
        if (empty($term)) {
            $this->response(array(
                'success' => false,
                'message' => 'Término de búsqueda requerido'
            ), 400);
        }
        
        $productos = $this->Producto_model->buscar($term, $id_sucursal, $limit);
        
        $this->response(array(
            'success' => true,
            'data' => $productos
        ));
    }

    /**
     * GET /api/productos/codigo-barras/:codigo
     * Busca producto por código de barras
     */
    public function buscar_codigo_barras($codigo)
    {
        $id_sucursal = $this->is_admin() ? $this->input->get('id_sucursal') : $this->user['id_sucursal'];
        
        $producto = $this->Producto_model->get_by_codigo_barras($codigo, $id_sucursal);
        
        if (!$producto) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado'
            ), 404);
        }
        
        $this->response(array(
            'success' => true,
            'data' => $producto
        ));
    }

    /**
     * POST /api/productos/:id/imagenes
     * Sube una imagen para el producto
     */
    public function upload_imagen($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('productos_editar');
        
        $producto = $this->Producto_model->get_by_id($id);
        
        if (!$producto) {
            $this->response(array(
                'success' => false,
                'message' => 'Producto no encontrado'
            ), 404);
        }
        
        // Configurar upload
        $config['upload_path'] = FCPATH . 'uploads/productos/';
        $config['allowed_types'] = 'gif|jpg|jpeg|png|webp';
        $config['max_size'] = 2048; // 2MB
        $config['encrypt_name'] = TRUE;
        
        // Crear directorio si no existe
        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0755, TRUE);
        }
        
        $this->load->library('upload', $config);
        
        if (!$this->upload->do_upload('imagen')) {
            $this->response(array(
                'success' => false,
                'message' => $this->upload->display_errors('', '')
            ), 400);
        }
        
        $upload_data = $this->upload->data();
        $imagen = 'uploads/productos/' . $upload_data['file_name'];
        
        // Agregar imagen al producto
        $id_imagen = $this->Producto_model->add_imagen($id, $imagen);
        
        // Si es la primera imagen, establecerla como principal
        if (empty($producto['imagen_principal'])) {
            $this->Producto_model->update($id, array('imagen_principal' => $imagen));
        }
        
        $this->response(array(
            'success' => true,
            'message' => 'Imagen subida exitosamente',
            'data' => array(
                'id' => $id_imagen,
                'imagen' => $imagen
            )
        ));
    }

    /**
     * DELETE /api/productos/:id/imagenes/:id_imagen
     * Elimina una imagen del producto
     */
    public function delete_imagen($id, $id_imagen)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->response(array('success' => false, 'message' => 'Método no permitido'), 405);
        }
        
        $this->require_permission('productos_editar');
        
        $this->Producto_model->delete_imagen($id_imagen);
        
        $this->response(array(
            'success' => true,
            'message' => 'Imagen eliminada exitosamente'
        ));
    }
}

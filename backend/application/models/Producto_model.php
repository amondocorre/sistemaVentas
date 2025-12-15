<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Producto_model extends CI_Model
{
    protected $table = 'productos';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtiene todos los productos con filtros
     */
    public function get_all($filters = array())
    {
        $this->db->select('p.*, c.nombre as categoria, m.nombre as marca');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias c', 'c.id = p.id_categoria', 'left');
        $this->db->join('marcas m', 'm.id = p.id_marca', 'left');
        
        if (isset($filters['estado'])) {
            $this->db->where('p.estado', $filters['estado']);
        }
        
        if (isset($filters['id_categoria'])) {
            $this->db->where('p.id_categoria', $filters['id_categoria']);
        }
        
        if (isset($filters['id_marca'])) {
            $this->db->where('p.id_marca', $filters['id_marca']);
        }
        
        if (isset($filters['search'])) {
            $this->db->group_start();
            $this->db->like('p.nombre', $filters['search']);
            $this->db->or_like('p.codigo_barras', $filters['search']);
            $this->db->or_like('p.descripcion', $filters['search']);
            $this->db->group_end();
        }
        
        // Paginación
        if (isset($filters['limit'])) {
            $offset = isset($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        $this->db->order_by('p.nombre', 'ASC');
        
        $productos = $this->db->get()->result_array();
        
        // Agregar stock por sucursal si se especifica
        if (isset($filters['id_sucursal'])) {
            foreach ($productos as &$producto) {
                $producto['stock'] = $this->get_stock_sucursal($producto['id'], $filters['id_sucursal']);
            }
        }
        
        return $productos;
    }

    /**
     * Obtiene un producto por ID
     */
    public function get_by_id($id)
    {
        $this->db->select('p.*, c.nombre as categoria, m.nombre as marca');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias c', 'c.id = p.id_categoria', 'left');
        $this->db->join('marcas m', 'm.id = p.id_marca', 'left');
        $this->db->where('p.id', $id);
        
        $producto = $this->db->get()->row_array();
        
        if ($producto) {
            $producto['imagenes'] = $this->get_imagenes($id);
            $producto['stock_sucursales'] = $this->get_stock_todas_sucursales($id);
        }
        
        return $producto;
    }

    /**
     * Busca producto por código de barras
     */
    public function get_by_codigo_barras($codigo, $id_sucursal = null)
    {
        $this->db->select('p.*, c.nombre as categoria, m.nombre as marca');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias c', 'c.id = p.id_categoria', 'left');
        $this->db->join('marcas m', 'm.id = p.id_marca', 'left');
        $this->db->where('p.codigo_barras', $codigo);
        $this->db->where('p.estado', 1);
        
        $producto = $this->db->get()->row_array();
        
        if ($producto && $id_sucursal) {
            $producto['stock'] = $this->get_stock_sucursal($producto['id'], $id_sucursal);
        }
        
        return $producto;
    }

    /**
     * Busca productos por término
     */
    public function buscar($term, $id_sucursal = null, $limit = 20)
    {
        $this->db->select('p.*, c.nombre as categoria, m.nombre as marca');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias c', 'c.id = p.id_categoria', 'left');
        $this->db->join('marcas m', 'm.id = p.id_marca', 'left');
        $this->db->where('p.estado', 1);
        $this->db->group_start();
        $this->db->like('p.nombre', $term);
        $this->db->or_like('p.codigo_barras', $term);
        $this->db->group_end();
        $this->db->limit($limit);
        
        $productos = $this->db->get()->result_array();
        
        if ($id_sucursal) {
            foreach ($productos as &$producto) {
                $producto['stock'] = $this->get_stock_sucursal($producto['id'], $id_sucursal);
            }
        }
        
        return $productos;
    }

    /**
     * Crea un nuevo producto
     */
    public function create($data)
    {
        if (array_key_exists('codigo_barras', $data)) {
            $codigo = trim((string)$data['codigo_barras']);
            $data['codigo_barras'] = ($codigo === '') ? null : $codigo;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Actualiza un producto
     */
    public function update($id, $data)
    {
        if (array_key_exists('codigo_barras', $data)) {
            $codigo = trim((string)$data['codigo_barras']);
            $data['codigo_barras'] = ($codigo === '') ? null : $codigo;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Elimina (desactiva) un producto
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, array('estado' => 0));
    }

    /**
     * Obtiene el stock de un producto en una sucursal
     */
    public function get_stock_sucursal($id_producto, $id_sucursal)
    {
        $this->db->select('stock');
        $this->db->where('id_producto', $id_producto);
        $this->db->where('id_sucursal', $id_sucursal);
        
        $result = $this->db->get('inventario_sucursal')->row();
        
        return $result ? (int)$result->stock : 0;
    }

    /**
     * Obtiene el stock de un producto en todas las sucursales
     */
    public function get_stock_todas_sucursales($id_producto)
    {
        $this->db->select('i.*, s.nombre as sucursal');
        $this->db->from('inventario_sucursal i');
        $this->db->join('sucursales s', 's.id = i.id_sucursal');
        $this->db->where('i.id_producto', $id_producto);
        
        return $this->db->get()->result_array();
    }

    /**
     * Obtiene las imágenes de un producto
     */
    public function get_imagenes($id_producto)
    {
        $this->db->where('id_producto', $id_producto);
        $this->db->order_by('orden', 'ASC');
        
        return $this->db->get('productos_imagenes')->result_array();
    }

    /**
     * Agrega una imagen al producto
     */
    public function add_imagen($id_producto, $imagen, $orden = 0)
    {
        $this->db->insert('productos_imagenes', array(
            'id_producto' => $id_producto,
            'imagen' => $imagen,
            'orden' => $orden,
            'created_at' => date('Y-m-d H:i:s')
        ));
        
        return $this->db->insert_id();
    }

    /**
     * Elimina una imagen
     */
    public function delete_imagen($id_imagen)
    {
        $this->db->where('id', $id_imagen);
        return $this->db->delete('productos_imagenes');
    }

    /**
     * Verifica si el código de barras ya existe
     */
    public function codigo_barras_exists($codigo, $exclude_id = null)
    {
        $this->db->where('codigo_barras', $codigo);
        
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Obtiene productos con stock crítico
     */
    public function get_stock_critico($id_sucursal = null, $limit = 10)
    {
        $this->db->select('p.*, i.stock, s.nombre as sucursal');
        $this->db->from($this->table . ' p');
        $this->db->join('inventario_sucursal i', 'i.id_producto = p.id');
        $this->db->join('sucursales s', 's.id = i.id_sucursal');
        $this->db->where('p.estado', 1);
        $this->db->where('i.stock <=', 'p.stock_minimo', FALSE);
        
        if ($id_sucursal) {
            $this->db->where('i.id_sucursal', $id_sucursal);
        }
        
        $this->db->order_by('i.stock', 'ASC');
        $this->db->limit($limit);
        
        return $this->db->get()->result_array();
    }

    /**
     * Cuenta total de productos
     */
    public function count_all($filters = array())
    {
        if (isset($filters['estado'])) {
            $this->db->where('estado', $filters['estado']);
        }
        
        return $this->db->count_all_results($this->table);
    }
}

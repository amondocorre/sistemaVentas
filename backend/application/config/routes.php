<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Default Controller
|--------------------------------------------------------------------------
*/
$route['default_controller'] = 'api/welcome';

/*
|--------------------------------------------------------------------------
| 404 Override
|--------------------------------------------------------------------------
*/
$route['404_override'] = '';

/*
|--------------------------------------------------------------------------
| Translate URI Dashes
|--------------------------------------------------------------------------
*/
$route['translate_uri_dashes'] = FALSE;

/*
|--------------------------------------------------------------------------
| API Routes - Authentication
|--------------------------------------------------------------------------
*/
$route['api/auth/login']['POST'] = 'api/Auth/login';
$route['api/auth/refresh']['POST'] = 'api/Auth/refresh';
$route['api/auth/logout']['POST'] = 'api/Auth/logout';
$route['api/auth/me']['GET'] = 'api/Auth/me';
$route['api/auth/change-password']['POST'] = 'api/Auth/change_password';

/*
|--------------------------------------------------------------------------
| API Routes - Dashboard
|--------------------------------------------------------------------------
*/
$route['api/dashboard']['GET'] = 'api/Dashboard/index';
$route['api/dashboard/ventas-dia']['GET'] = 'api/Dashboard/ventas_dia';
$route['api/dashboard/ventas-sucursal']['GET'] = 'api/Dashboard/ventas_sucursal';
$route['api/dashboard/top-productos']['GET'] = 'api/Dashboard/top_productos';
$route['api/dashboard/ventas-periodo']['GET'] = 'api/Dashboard/ventas_periodo';
$route['api/dashboard/ingresos-metodo-pago']['GET'] = 'api/Dashboard/ingresos_metodo_pago';
$route['api/dashboard/stock-critico']['GET'] = 'api/Dashboard/stock_critico';
$route['api/dashboard/resumen-inventario']['GET'] = 'api/Dashboard/resumen_inventario';

/*
|--------------------------------------------------------------------------
| API Routes - Usuarios
|--------------------------------------------------------------------------
*/
$route['api/usuarios']['GET'] = 'api/Usuarios/index';
$route['api/usuarios']['POST'] = 'api/Usuarios/create';
$route['api/usuarios/(:num)']['GET'] = 'api/Usuarios/show/$1';
$route['api/usuarios/(:num)']['PUT'] = 'api/Usuarios/update/$1';
$route['api/usuarios/(:num)']['DELETE'] = 'api/Usuarios/delete/$1';
$route['api/usuarios/(:num)/reset-password']['POST'] = 'api/Usuarios/reset_password/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Roles
|--------------------------------------------------------------------------
*/
$route['api/roles']['GET'] = 'api/Roles/index';
$route['api/roles']['POST'] = 'api/Roles/create';
$route['api/roles/(:num)']['GET'] = 'api/Roles/show/$1';
$route['api/roles/(:num)']['PUT'] = 'api/Roles/update/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Sucursales
|--------------------------------------------------------------------------
*/
$route['api/sucursales']['GET'] = 'api/Sucursales/index';
$route['api/sucursales']['POST'] = 'api/Sucursales/create';
$route['api/sucursales/(:num)']['GET'] = 'api/Sucursales/show/$1';
$route['api/sucursales/(:num)']['PUT'] = 'api/Sucursales/update/$1';
$route['api/sucursales/(:num)']['DELETE'] = 'api/Sucursales/delete/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Clientes
|--------------------------------------------------------------------------
*/
$route['api/clientes']['GET'] = 'api/Clientes/index';
$route['api/clientes']['POST'] = 'api/Clientes/create';
$route['api/clientes/(:num)']['GET'] = 'api/Clientes/show/$1';
$route['api/clientes/(:num)']['PUT'] = 'api/Clientes/update/$1';
$route['api/clientes/(:num)']['DELETE'] = 'api/Clientes/delete/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Categorías
|--------------------------------------------------------------------------
*/
$route['api/categorias']['GET'] = 'api/Categorias/index';
$route['api/categorias']['POST'] = 'api/Categorias/create';
$route['api/categorias/(:num)']['GET'] = 'api/Categorias/show/$1';
$route['api/categorias/(:num)']['PUT'] = 'api/Categorias/update/$1';
$route['api/categorias/(:num)']['DELETE'] = 'api/Categorias/delete/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Marcas
|--------------------------------------------------------------------------
*/
$route['api/marcas']['GET'] = 'api/Marcas/index';
$route['api/marcas']['POST'] = 'api/Marcas/create';
$route['api/marcas/(:num)']['GET'] = 'api/Marcas/show/$1';
$route['api/marcas/(:num)']['PUT'] = 'api/Marcas/update/$1';
$route['api/marcas/(:num)']['DELETE'] = 'api/Marcas/delete/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Productos
|--------------------------------------------------------------------------
*/
$route['api/productos']['GET'] = 'api/Productos/index';
$route['api/productos']['POST'] = 'api/Productos/create';
$route['api/productos/(:num)']['GET'] = 'api/Productos/show/$1';
$route['api/productos/(:num)']['PUT'] = 'api/Productos/update/$1';
$route['api/productos/(:num)']['DELETE'] = 'api/Productos/delete/$1';
$route['api/productos/buscar']['GET'] = 'api/Productos/buscar';
$route['api/productos/codigo-barras/(:any)']['GET'] = 'api/Productos/buscar_codigo_barras/$1';
$route['api/productos/(:num)/imagenes']['POST'] = 'api/Productos/upload_imagen/$1';
$route['api/productos/(:num)/imagenes/(:num)']['DELETE'] = 'api/Productos/delete_imagen/$1/$2';

/*
|--------------------------------------------------------------------------
| API Routes - Inventario
|--------------------------------------------------------------------------
*/
$route['api/inventario']['GET'] = 'api/Inventario/index';
$route['api/inventario/sucursal/(:num)']['GET'] = 'api/Inventario/por_sucursal/$1';
$route['api/inventario/producto/(:num)']['GET'] = 'api/Inventario/por_producto/$1';
$route['api/inventario/ajustar']['POST'] = 'api/Inventario/ajustar';
$route['api/inventario/transferir']['POST'] = 'api/Inventario/transferir';
$route['api/inventario/movimientos']['GET'] = 'api/Inventario/movimientos';
$route['api/inventario/entrada']['POST'] = 'api/Inventario/entrada';

/*
|--------------------------------------------------------------------------
| API Routes - Métodos de Pago
|--------------------------------------------------------------------------
*/
$route['api/metodos-pago']['GET'] = 'api/MetodosPago/index';
$route['api/metodos-pago']['POST'] = 'api/MetodosPago/create';
$route['api/metodos-pago/(:num)']['GET'] = 'api/MetodosPago/show/$1';
$route['api/metodos-pago/(:num)']['PUT'] = 'api/MetodosPago/update/$1';
$route['api/metodos-pago/(:num)']['DELETE'] = 'api/MetodosPago/delete/$1';
$route['api/metodos-pago/(:num)/qr']['POST'] = 'api/MetodosPago/upload_qr/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Ventas
|--------------------------------------------------------------------------
*/
$route['api/ventas']['GET'] = 'api/Ventas/index';
$route['api/ventas']['POST'] = 'api/Ventas/create';
$route['api/ventas/(:num)']['GET'] = 'api/Ventas/show/$1';
$route['api/ventas/(:num)/anular']['POST'] = 'api/Ventas/anular/$1';
$route['api/ventas/mis-ventas']['GET'] = 'api/Ventas/mis_ventas';

/*
|--------------------------------------------------------------------------
| API Routes - Créditos
|--------------------------------------------------------------------------
*/
$route['api/creditos']['GET'] = 'api/Creditos/index';
$route['api/creditos/(:num)/cobros']['GET'] = 'api/Creditos/cobros/$1';
$route['api/creditos/(:num)/cobros']['POST'] = 'api/Creditos/registrar_cobro/$1';

/*
|--------------------------------------------------------------------------
| API Routes - Reportes
|--------------------------------------------------------------------------
*/
$route['api/reportes/ventas']['GET'] = 'api/Reportes/ventas';
$route['api/reportes/ventas/excel']['GET'] = 'api/Reportes/ventas_excel';
$route['api/reportes/ventas/pdf']['GET'] = 'api/Reportes/ventas_pdf';
$route['api/reportes/productos']['GET'] = 'api/Reportes/productos';
$route['api/reportes/productos/excel']['GET'] = 'api/Reportes/productos_excel';
$route['api/reportes/productos/pdf']['GET'] = 'api/Reportes/productos_pdf';
$route['api/reportes/stock']['GET'] = 'api/Reportes/stock';
$route['api/reportes/stock/excel']['GET'] = 'api/Reportes/stock_excel';
$route['api/reportes/stock/pdf']['GET'] = 'api/Reportes/stock_pdf';
$route['api/reportes/top-productos']['GET'] = 'api/Reportes/top_productos';
$route['api/reportes/top-productos/excel']['GET'] = 'api/Reportes/top_productos_excel';
$route['api/reportes/metodos-pago']['GET'] = 'api/Reportes/metodos_pago';
$route['api/reportes/metodos-pago/excel']['GET'] = 'api/Reportes/metodos_pago_excel';

/*
|--------------------------------------------------------------------------
| API Routes - Configuración
|--------------------------------------------------------------------------
*/
$route['api/configuracion']['GET'] = 'api/Configuracion/index';
$route['api/configuracion']['POST'] = 'api/Configuracion/update';

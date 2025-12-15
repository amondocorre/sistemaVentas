<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['db_invalid_connection_str'] = 'No es posible determinar la configuración de la base de datos basada en la cadena de conexión enviada.';
$lang['db_unable_to_connect'] = 'No es posible conectarse al servidor de base de datos usando la configuración provista.';
$lang['db_unable_to_select'] = 'No es posible seleccionar la base de datos especificada: %s';
$lang['db_unable_to_create'] = 'No es posible crear la base de datos especificada: %s';
$lang['db_invalid_query'] = 'La consulta enviada no es válida.';
$lang['db_must_set_table'] = 'Debe establecer la tabla de base de datos a utilizar con su consulta.';
$lang['db_must_use_set'] = 'Debe usar el método "set" para actualizar un registro.';
$lang['db_must_use_index'] = 'Debe especificar un índice para coincidir en actualizaciones por lotes.';
$lang['db_batch_missing_index'] = 'Una o más filas enviadas para actualización por lotes no incluye el índice especificado.';
$lang['db_must_use_where'] = 'No se permiten actualizaciones a menos que contengan una cláusula "where".';
$lang['db_del_must_use_where'] = 'No se permiten eliminaciones a menos que contengan una cláusula "where" o "like".';
$lang['db_field_param_missing'] = 'Para obtener campos se requiere el nombre de la tabla como parámetro.';
$lang['db_unsupported_function'] = 'Esta función no está disponible para la base de datos que está usando.';
$lang['db_transaction_failure'] = 'Falla de transacción: se realizó rollback.';
$lang['db_unable_to_drop'] = 'No es posible eliminar la base de datos especificada.';
$lang['db_unsupported_feature'] = 'Funcionalidad no soportada por la plataforma de base de datos que está usando.';
$lang['db_unsupported_compression'] = 'El formato de compresión elegido no es soportado por su servidor.';
$lang['db_filepath_error'] = 'No es posible escribir datos en la ruta de archivo especificada.';
$lang['db_invalid_cache_path'] = 'La ruta de caché especificada no es válida o no tiene permisos de escritura.';
$lang['db_table_name_required'] = 'Se requiere un nombre de tabla para esta operación.';
$lang['db_column_name_required'] = 'Se requiere un nombre de columna para esta operación.';
$lang['db_column_definition_required'] = 'Se requiere una definición de columna para esta operación.';
$lang['db_unable_to_set_charset'] = 'No es posible configurar el conjunto de caracteres del cliente: %s';
$lang['db_error_heading'] = 'Ocurrió un error de base de datos';

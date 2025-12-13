<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| JWT Configuration
|--------------------------------------------------------------------------
*/

// Clave secreta para firmar los tokens
$config['jwt_secret'] = 'SistemaVentasInventarios2024JWT!@#$SecretKey';

// Algoritmo de firma
$config['jwt_algorithm'] = 'HS256';

// Duración del access token (24 horas en segundos)
$config['jwt_access_token_expire'] = 86400;

// Duración del refresh token (30 días en segundos)
$config['jwt_refresh_token_expire'] = 2592000;

// Issuer del token
$config['jwt_issuer'] = 'sistema-ventas-api';

// Audience del token
$config['jwt_audience'] = 'sistema-ventas-app';

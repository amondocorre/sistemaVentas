<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * JWT Library para CodeIgniter 3
 * Maneja la creación y validación de tokens JWT
 */
class JWT_Library
{
    protected $CI;
    protected $secret;
    protected $algorithm;
    protected $access_expire;
    protected $refresh_expire;
    protected $issuer;
    protected $audience;

    public function __construct()
    {
        $this->CI =& get_instance();
        // Cargar configuración jwt.php en el espacio de config global
        $this->CI->config->load('jwt');
        
        // Leer directamente los valores de $config definidos en jwt.php
        $this->secret        = $this->CI->config->item('jwt_secret');
        $this->algorithm     = $this->CI->config->item('jwt_algorithm');
        $this->access_expire = (int) $this->CI->config->item('jwt_access_token_expire');
        $this->refresh_expire = (int) $this->CI->config->item('jwt_refresh_token_expire');
        $this->issuer        = $this->CI->config->item('jwt_issuer');
        $this->audience      = $this->CI->config->item('jwt_audience');
    }

    /**
     * Genera un access token
     */
    public function generate_access_token($user_data)
    {
        $issued_at = time();
        $expire = $issued_at + $this->access_expire;

        $payload = array(
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issued_at,
            'exp' => $expire,
            'type' => 'access',
            'data' => array(
                'id' => $user_data['id'],
                'usuario' => isset($user_data['usuario']) ? $user_data['usuario'] : null,
                'email' => $user_data['email'],
                'nombre' => $user_data['nombre'],
                'id_rol' => $user_data['id_rol'],
                'rol' => $user_data['rol'],
                'id_sucursal' => $user_data['id_sucursal'],
                'sucursal' => isset($user_data['sucursal']) ? $user_data['sucursal'] : null,
                'permisos' => isset($user_data['permisos']) ? $user_data['permisos'] : array()
            )
        );

        return $this->encode($payload);
    }

    /**
     * Genera un refresh token
     */
    public function generate_refresh_token($user_id)
    {
        $issued_at = time();
        $expire = $issued_at + $this->refresh_expire;

        $payload = array(
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issued_at,
            'exp' => $expire,
            'type' => 'refresh',
            'user_id' => $user_id,
            'jti' => bin2hex(random_bytes(16))
        );

        return $this->encode($payload);
    }

    /**
     * Decodifica y valida un token
     */
    public function decode_token($token)
    {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return array('success' => false, 'message' => 'Token inválido');
            }

            list($header_b64, $payload_b64, $signature_b64) = $parts;

            // Verificar firma
            $signature_check = $this->base64url_encode(
                hash_hmac('sha256', "$header_b64.$payload_b64", $this->secret, true)
            );

            if (!hash_equals($signature_check, $signature_b64)) {
                return array('success' => false, 'message' => 'Firma inválida');
            }

            // Decodificar payload
            $payload = json_decode($this->base64url_decode($payload_b64), true);

            if (!$payload) {
                return array('success' => false, 'message' => 'Payload inválido');
            }

            // Verificar expiración
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return array('success' => false, 'message' => 'Token expirado');
            }

            return array('success' => true, 'data' => $payload);

        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Error al decodificar token: ' . $e->getMessage());
        }
    }

    /**
     * Valida un access token y retorna los datos del usuario
     */
    public function validate_access_token($token)
    {
        $result = $this->decode_token($token);
        
        if (!$result['success']) {
            return $result;
        }

        if ($result['data']['type'] !== 'access') {
            return array('success' => false, 'message' => 'Tipo de token inválido');
        }

        return array('success' => true, 'user' => $result['data']['data']);
    }

    /**
     * Valida un refresh token
     */
    public function validate_refresh_token($token)
    {
        $result = $this->decode_token($token);
        
        if (!$result['success']) {
            return $result;
        }

        if ($result['data']['type'] !== 'refresh') {
            return array('success' => false, 'message' => 'Tipo de token inválido');
        }

        return array('success' => true, 'user_id' => $result['data']['user_id'], 'jti' => $result['data']['jti']);
    }

    /**
     * Codifica el payload en JWT
     */
    protected function encode($payload)
    {
        $header = array(
            'typ' => 'JWT',
            'alg' => $this->algorithm
        );

        $header_b64 = $this->base64url_encode(json_encode($header));
        $payload_b64 = $this->base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$header_b64.$payload_b64", $this->secret, true);
        $signature_b64 = $this->base64url_encode($signature);

        return "$header_b64.$payload_b64.$signature_b64";
    }

    /**
     * Codifica en base64 URL-safe
     */
    protected function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica base64 URL-safe
     */
    protected function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Obtiene el tiempo de expiración del refresh token
     */
    public function get_refresh_expire_time()
    {
        return time() + $this->refresh_expire;
    }
}

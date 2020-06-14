<?php

class JWT
{
    function create_jwt_token($user_id, $user_name)
    {
        // Create token header as a JSON string
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        // Create token payload as a JSON string
        $payload = json_encode(['user_id' => $user_id, 'user_name' => $user_name, 'exp' => time()]);

        // Encode Header to Base64Url String
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Encode Payload to Base64Url String
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        // Create Signature Hash
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'Kmit123$', true);

        // Encode Signature to Base64Url String
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // Create JWT
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    function validate_jwt_token($token)
    {
        $split = explode('.', $token);
        if (count($split) !== 3) {
            return false;
        }
        $base64UrlHeader = $split[0];
        $base64UrlPayload = $split[1];
        $base64UrlSignature = $split[2];

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'Kmit123$', true);
        $base64UrlSignatureComputed = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if($base64UrlSignature !== $base64UrlSignatureComputed){
            return false;
        }

        $payload = base64_decode($base64UrlPayload);
        $json_payload = json_decode($payload, true);

        if ($json_payload['exp'] + 1200000 < time()){
            return false;
        }
        return true;
    }

    /**
     * Returns false if token is not valid else returns payload.
     * @param string $token
     * @return false|string
     */
    function get_user_details_from_jwt($token){
        if (!$this->validate_jwt_token($token)){
            return false;
        }
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = explode('.', $token);
        $payload = base64_decode($base64UrlPayload);

        return $payload;
    }
}
<?php
// filepath: d:\Semester 4\PEMEROGRAAN WEB LANJUT\stackUas\fullstack\server\app\Helpers\jwt_helper.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function create_jwt($payload, $key, $exp = 3600)
{
    $issuedAt = time();
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $issuedAt + $exp;
    return JWT::encode($payload, $key, 'HS256');
}

function verify_jwt($token, $key)
{
    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (\Exception $e) {
        return false;
    }
}
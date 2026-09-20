<?php
declare(strict_types=1);
namespace TestLab;

final class Http
{
    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Authorization') {
            $value = $_SERVER[$key] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if ($value === '' && function_exists('getallheaders')) {
                foreach (getallheaders() as $k=>$v) { if (strcasecmp($k, $name) === 0) { $value=$v; break; } }
            }
            return is_string($value) ? trim($value) : '';
        }
        return is_string($_SERVER[$key] ?? null) ? trim($_SERVER[$key]) : '';
    }
    public static function body(): array
    {
        $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($type !== 'application/json') { throw new ApiError(415,'JSON_REQUIRED','Požiadavka musí obsahovať application/json.'); }
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) { throw new ApiError(413,'BODY_TOO_LARGE','Príliš veľká požiadavka.'); }
        $body = file_get_contents('php://input', false, null, 0, 32769);
        if ($body === false || strlen($body)>32768) { throw new ApiError(413,'BODY_TOO_LARGE','Príliš veľká požiadavka.'); }
        try { $object=json_decode($body, false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ApiError(400,'INVALID_JSON','Neplatný JSON.'); }
        if (!$object instanceof \stdClass) { throw new ApiError(400,'INVALID_JSON','Koreň JSON musí byť objekt.'); }
        return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    }
    public static function send(array $data, int $status=200, array $meta=[]): never
    {
        http_response_code($status);
        echo json_encode(['data'=>$data,'meta'=>(object)$meta], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        exit;
    }
    public static function fail(ApiError $error, string $requestId): never
    {
        http_response_code($error->status);
        if ($error->status === 429) { header('Retry-After: 900'); }
        echo json_encode(['error'=>['code'=>$error->errorCode,'message'=>$error->getMessage(),
            'details'=>(object)$error->details,'request_id'=>$requestId]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function uuid(): string
    {
        $b=random_bytes(16); $b[6]=chr((ord($b[6])&0x0f)|0x40); $b[8]=chr((ord($b[8])&0x3f)|0x80);
        $h=bin2hex($b); return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
}

<?php
class JWT {
    private static $secret = "wooble_jwt_secret_key_2025_!@#change_in_production";
    private static $algorithm = "HS256";

    /**
     * Generate a JWT token
     */
    public static function generate(array $payload): string {
        $header = self::base64UrlEncode(json_encode([
            "alg" => self::$algorithm,
            "typ" => "JWT"
        ]));

        $payload["iat"] = time();
        $payload["exp"] = time() + (60 * 60 * 24); // 24 hours expiry

        $encodedPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            "sha256",
            "{$header}.{$encodedPayload}",
            self::$secret,
            true
        );

        return "{$header}.{$encodedPayload}." . self::base64UrlEncode($signature);
    }

    /**
     * Validate and decode a JWT token
     */
    public static function validate(string $token): ?array {
        $parts = explode(".", $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        $expectedSignature = self::base64UrlEncode(
            hash_hmac("sha256", "{$header}.{$payload}", self::$secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) return null;

        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data) return null;

        // Check token expiry
        if (isset($data["exp"]) && $data["exp"] < time()) return null;

        return $data;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, "-_", "+/"));
    }
}
?>
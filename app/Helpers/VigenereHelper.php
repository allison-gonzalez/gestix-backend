<?php

namespace App\Helpers;

class VigenereHelper
{
    /**
     * Clave para el cifrado Vigenère
     * Se puede configurar en .env como VIGENERE_KEY
     */
    private static $key;

    /**
     * Obtiene la clave de cifrado
     */
    private static function getKey(): string
    {
        if (!self::$key) {
            self::$key = env('VIGENERE_KEY', 'gestix-default-key-2024');
        }
        return self::$key;
    }

    /**
     * Normaliza la clave a letras mayúsculas
     */
    private static function normalizeKey(string $key): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $key));
    }

    /**
     * Convierte un carácter a su valor ASCII (0-255)
     */
    private static function charToAscii(string $char): int
    {
        return ord($char);
    }

    /**
     * Convierte un valor ASCII (0-255) a carácter
     */
    private static function asciiToChar(int $ascii): string
    {
        return chr($ascii);
    }

    /**
     * Encripta un texto usando Vigenère con soporte para caracteres especiales
     *
     * Método mejorado que maneja:
     * - Letras (A-Z, a-z)
     * - Números (0-9)
     * - Caracteres especiales (!@#$%^&* etc)
     *
     * @param string $plaintext Texto a encriptar
     * @return string Texto encriptado en base64
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::normalizeKey(self::getKey());
        $bytes = array_values(unpack('C*', $plaintext));
        $ciphertext = '';
        $keyIndex = 0;
        $keyLength = strlen($key);

        foreach ($bytes as $byte) {
            $keyCode = ord($key[$keyIndex % $keyLength]) - ord('A'); // 0-25
            // Aplicar Vigenère usando módulo 256 (rango ASCII)
            $encryptedByte = ($byte + $keyCode) % 256;
            $ciphertext .= chr($encryptedByte);
            $keyIndex++;
        }

        return base64_encode($ciphertext);
    }

    /**
     * Desencripta un texto usando Vigenère con soporte para caracteres especiales
     *
     * @param string $ciphertext Texto encriptado en base64
     * @return string Texto desencriptado
     */
    public static function decrypt(string $ciphertext): string
    {
        try {
            $rawKey = self::getKey();
            $key = self::normalizeKey($rawKey);
            $ciphertext = base64_decode($ciphertext, true);
            if ($ciphertext === false) {
                return '';
            }

            $bytes = array_values(unpack('C*', $ciphertext));
            $plaintext = '';
            $keyIndex = 0;
            $keyLength = strlen($key);

            foreach ($bytes as $byte) {
                $keyCode = ord($key[$keyIndex % $keyLength]) - ord('A'); // 0-25
                // Invertir Vigenère usando módulo 256
                $decryptedByte = ($byte - $keyCode + 256) % 256;
                $plaintext .= chr($decryptedByte);
                $keyIndex++;
            }

            return $plaintext;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Verifica si un texto coincide con el texto encriptado
     *
     * @param string $plaintext Texto en claro
     * @param string $encrypted Texto encriptado
     * @return bool
     */
    public static function verify(string $plaintext, string $encrypted): bool
    {
        return self::encrypt($plaintext) === $encrypted;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current' => 'required',
            'new' => 'required|min:8',
        ]);

        $user = Auth::user();
        $key = env('VIGENERE_KEY', 'gestix-secure-key-vigenere-cipher');

        // 1. Cifrar la contraseña actual recibida para comparar con la DB
        $encryptedCurrent = $this->vigenereCipher($request->current, $key);

        if ($user->contrasena !== $encryptedCurrent) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 400);
        }

        // 2. Cifrar la nueva contraseña y guardar
        $user->contrasena = $this->vigenereCipher($request->new, $key);
        $user->save();

        return response()->json(['message' => '¡Contraseña actualizada con éxito!']);
    }

    private function vigenereCipher($text, $key)
    {
        $text = strtoupper($text);
        $key = strtoupper($key);
        $encrypted = '';
        $j = 0;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            if (ctype_alpha($char)) {
                $encrypted .= chr(((ord($char) - 65 + ord($key[$j % strlen($key)]) - 65) % 26) + 65);
                $j++;
            } else {
                $encrypted .= $char;
            }
        }
        return $encrypted;
    }
}
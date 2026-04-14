<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\VigenereHelper;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'correo'   => 'required|email|max:150',
            'telefono' => 'nullable|string|max:20',
        ]);

        $user = Auth::user();
        $user->nombre   = $request->nombre;
        $user->correo   = $request->correo;
        if ($request->has('telefono')) {
            $user->telefono = $request->telefono;
        }
        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user'    => [
                'id'              => $user->id,
                'nombre'          => $user->nombre,
                'correo'          => $user->correo,
                'telefono'        => $user->telefono,
                'estatus'         => $user->estatus,
                'departamento_id' => $user->departamento_id,
                'permisos'        => $user->permisos,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current' => 'required|string',
            'new'     => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[^a-zA-Z0-9]/',
            ],
        ], [
            'new.min'    => 'La contraseña debe tener al menos 8 caracteres.',
            'new.regex'  => 'La contraseña debe contener mayúsculas, minúsculas y un carácter especial.',
        ]);

        $user = Auth::user();

        // 1. Verificar la contraseña actual
        try {
            $decryptedCurrent = VigenereHelper::decrypt($user->contrasena);
            if ($decryptedCurrent !== $request->current) {
                return response()->json(['message' => 'La contraseña actual es incorrecta'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al verificar la contraseña'], 400);
        }

        // 2. Encriptar la nueva contraseña y guardar
        try {
            $user->contrasena = VigenereHelper::encrypt($request->new);
            $user->must_change_password = false;
            $user->save();
            return response()->json(['message' => '¡Contraseña actualizada con éxito!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la contraseña'], 500);
        }
    }
}
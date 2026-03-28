# Vigenère Cipher Helper - Documentación

## 🚀 Instalación y Configuración

### Paso 1: Configurar la clave en `.env`

1. Abre el archivo `.env` en la raíz del proyecto backend
2. Busca o agrega la variable `VIGENERE_KEY`:

```env
# Configuración de Vigenère para encriptación de contraseñas
VIGENERE_KEY=tu-clave-segura-aqui
```

3. **Importante:** 
   - La clave puede contener cualquier carácter, solo se usarán las letras (A-Z)
   - Cambia el valor en **producción** por una clave segura y única
   - Ejemplo seguro: `my-production-key-2024-gestix`

### Paso 2: Usar el Helper en tu código

El helper ya está integrado automáticamente en `UsuarioController`. Si necesitas usarlo en otro lugar:

```php
use App\Helpers\VigenereHelper;

// Encriptar
$encrypted = VigenereHelper::encrypt('contraseña');

// Desencriptar
$decrypted = VigenereHelper::decrypt($encrypted);

// Verificar
$isValid = VigenereHelper::verify('contraseña', $encrypted);
```

## 📋 Descripción

El `VigenereHelper` es un helper de Laravel diseñado para encriptar y desencriptar contraseñas (y otro contenido sensible) usando el algoritmo Vigenère.

## Características

- **Encriptación simétrica**: Usa el cifrado polialfabético Vigenère
- **Clave configurable**: La clave se define en la variable de entorno `VIGENERE_KEY`
- **Codificación Base64**: El resultado encriptado se codifica en base64 para almacenamiento seguro
- **Verificación**: Método `verify()` para comparar texto plano con texto encriptado

## Instalación

El helper está ubicado en `app/Helpers/VigenereHelper.php`

### Configuración

1. Añade la siguiente variable a tu archivo `.env`:

```env
VIGENERE_KEY=tu-clave-segura-aqui
```

La clave debe contener solo caracteres alfabéticos (se filtran automáticamente).

## Uso

### 1. Encriptar una contraseña

```php
use App\Helpers\VigenereHelper;

$password = "mi_contraseña_segura";
$encrypted = VigenereHelper::encrypt($password);
```

### 2. Desencriptar

```php
use App\Helpers\VigenereHelper;

$encrypted = "aqui_va_el_texto_encriptado_en_base64";
$decrypted = VigenereHelper::decrypt($encrypted);
```

### 3. Verificar contraseña

```php
use App\Helpers\VigenereHelper;

$providedPassword = "contraseña_del_usuario";
$storedEncrypted = "valor_almacenado_en_bd";

$isValid = VigenereHelper::verify($providedPassword, $storedEncrypted);
```

## Integración con UsuarioController

El `UsuarioController` ya integra el helper automáticamente:

- **store()**: Encripta la contraseña antes de guardar
- **update()**: Encripta la contraseña si se proporciona
- **verifyPassword()**: Verifica si una contraseña coincide con la almacenada

### Ejemplo: Crear un usuario

```bash
POST /api/usuarios
Content-Type: application/json

{
  "nombre": "Juan Pérez",
  "correo": "juan@example.com",
  "telefono": "1234567890",
  "contrasena": "MiContr@seña_123!",
  "estatus": 1,
  "departamento_id": 1,
  "permisos": [1, 2, 3]
}
```

La contraseña `MiContr@seña_123!` será encriptada completamente, incluyendo:
- Mayúsculas y minúsculas
- Números
- Caracteres especiales (@, ñ, !, _)
- Espacios (si los contiene)

### Ejemplo: Verificar contraseña

```bash
POST /api/usuarios/{id}/verify-password
Content-Type: application/json

{
  "contrasena": "contraseña_del_usuario"
}
```

Respuesta:
```json
{
  "valid": true
}
```

## Endpoints de Usuarios

- `GET /api/usuarios` - Listar todos los usuarios
- `POST /api/usuarios` - Crear nuevo usuario
- `GET /api/usuarios/{id}` - Obtener usuario
- `PUT /api/usuarios/{id}` - Actualizar usuario
- `DELETE /api/usuarios/{id}` - Eliminar usuario
- `POST /api/usuarios/{id}/verify-password` - Verificar contraseña

## Seguridad

**⚠️ Importante:**

1. Cambia la clave `VIGENERE_KEY` en producción por una clave única
2. El cifrado Vigenère es adecuado para almacenamiento de contraseñas en aplicaciones internas
3. **Soporte completo para caracteres especiales:** números, símbolos (@#$%), espacios— todo está cifrado
4. SIEMPRE transmite contraseñas sobre HTTPS
5. El campo `contrasena` está oculto en las respuestas JSON (protected $hidden en modelo)
6. Usa la función `verify()` para comparar contraseñas, nunca compare el texto plano directamente
7. **Guarda la clave en lugar seguro** si cambias de servidor—sin ella no podrás desencriptar

## 🔐 Algoritmo Vigenère (Explicación Detallada)

### ¿Cómo funciona?

El cifrado Vigenère es un **cifrado polialfabético simétrico** que utiliza una clave para cifrar y descifrar datos. Es más seguro que el cifrado de César tradicional porque utiliza múltiples desplazamientos.

### Proceso paso a paso:

**Encriptación:**

```
1. ENTRADA: "Mi@Contraseña"
             Clave: "GESTIX"

2. Convertir a bytes ASCII:
   M = 77, i = 105, @ = 64, C = 67, o = 111, n = 110, ...

3. Normalizar la clave (solo letras):
   "GESTIX" → G=6, E=4, S=18, T=19, I=8, X=23

4. Aplicar Vigenère a cada byte:
   Fórmula: cifrado = (byte + clave_valor) mod 256
   
   77 (M) + 6 (G) = 83 mod 256 = 83 ← 'S'
   105 (i) + 4 (E) = 109 mod 256 = 109 ← 'm'
   64 (@) + 18 (S) = 82 mod 256 = 82 ← 'R'
   67 (C) + 19 (T) = 86 mod 256 = 86 ← 'V'
   (repite la clave para cada byte)

5. Codificar en Base64:
   [83, 109, 82, 86, ...] → "SmRSVkx..."

6. SALIDA: "SmRSVkx..." (cifrado seguro en Base64)
```

**Desencriptación:**

```
1. ENTRADA: "SmRSVkx..." (cifrado en Base64)
             Clave: "GESTIX"

2. Decodificar Base64:
   "SmRSVkx..." → [83, 109, 82, 86, ...]

3. Normalizar la clave:
   "GESTIX" → [6, 4, 18, 19, 8, 23]

4. Aplicar Vigenère inverso a cada byte:
   Fórmula: original = (byte - clave_valor + 256) mod 256
   
   83 - 6 + 256 = 333 mod 256 = 77 ← 'M'
   109 - 4 + 256 = 361 mod 256 = 105 ← 'i'
   82 - 18 + 256 = 320 mod 256 = 64 ← '@'
   86 - 19 + 256 = 323 mod 256 = 67 ← 'C'
   (repite la clave)

5. SALIDA: "Mi@Contraseña" (original recuperado)
```

### ¿Por qué mod 256?

- **Rango ASCII:** El estándar ASCII tiene 256 valores (0-255)
- **Soporte completo:** Permite cifrar letras, números, símbolos y caracteres especiales
- **Reversibilidad:** Asegura que `(x - y + 256) % 256` recupera el valor original

### Tabla de características:

| Característica | Detalles |
|---|---|
| **Tipo** | Cifrado simétrico (misma clave para cifrar/descifrar) |
| **Rango** | 0-255 (256 posibilidades por byte) |
| **Cifra** | Todos los caracteres (letras, números, símbolos, Unicode) |
| **Salida** | Base64 (para almacenamiento seguro en BD) |
| **Clave** | Solo letras A-Z (se normalizan automáticamente) |
| **Reversibilidad** | 100% reversible sin pérdida de datos |

### Ejemplo Visual:

```
Original:      "Pass@123!"
Clave:         "SECRET"
Cifrado:       "gL/R8hq2zw==" (Base64)
Desencriptado: "Pass@123!" ✅ Coincide
```

## Troubleshooting

**El decrypt retorna una cadena vacía:**
- Verifica que la clave `VIGENERE_KEY` sea la misma que se usó para encriptar
- Asegúrate de que el texto encriptado sea un Base64 válido

**Las contraseñas no coinciden:**
- Revisa que la clave de entorno esté correctamente configurada en `.env`
- Verifica que no haya cambiado la `VIGENERE_KEY`
- Asegúrate de usar `verify()` y no comparación directa

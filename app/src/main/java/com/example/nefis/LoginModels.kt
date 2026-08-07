package com.example.nefis

import com.google.gson.annotations.SerializedName

data class LoginRequest(
    val email: String,
    // Aseguramos que se llame 'password' para que coincida con $request->password en Laravel
    val password: String
)

data class LoginResponse(
    val success: Boolean,
    val message: String,
    @SerializedName("access_token") val accessToken: String?,
    @SerializedName("token_type") val tokenType: String?,
    val user: UserInfo?
)

data class UserInfo(
    val id: Int,
    val nombre: String,
    val correo: String,
    @SerializedName("departamento_id") val departamentoId: String?
)

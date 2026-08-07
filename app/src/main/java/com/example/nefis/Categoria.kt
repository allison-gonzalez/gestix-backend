package com.example.nefis

import com.google.gson.annotations.SerializedName

data class Categoria(
    val id: Int,
    val nombre: String,
    @SerializedName("departamento_id") val departamentoId: Int
)

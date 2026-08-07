package com.example.nefis

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.google.gson.annotations.SerializedName

@Parcelize
data class Ticket(
    val id: Int,
    val titulo: String,
    val descripcion: String,
    val prioridad: String,
    @SerializedName("fecha_creacion") val fechaCreacion: String?,
    @SerializedName("fecha_asignacion") val fechaAsignacion: String?,
    @SerializedName("fecha_resolucion") val fechaResolucion: String?,
    @SerializedName("usuario_autor_id") val usuarioAutorId: String?,
    @SerializedName("categoria_id") val categoriaId: String?,
    @SerializedName("categoria_nombre") val categoriaNombre: String,
    @SerializedName("departamento_id") val departamentoId: String?,
    @SerializedName("autor_departamento_id") val autorDepartamentoId: String?, // Añadido este campo
    val comentarios: List<Int>?,
    @SerializedName("archivo_path") val archivoPath: String?,
    val estado: String?
) : Parcelable

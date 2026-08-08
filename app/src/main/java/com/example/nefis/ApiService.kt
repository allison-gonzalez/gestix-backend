package com.example.nefis

import retrofit2.Call
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

interface ApiService {
    @POST("auth/login")
    fun login(@Body request: LoginRequest): Call<LoginResponse>

    @GET("tickets")
    fun getTickets(): Call<TicketResponse>

    @GET("categorias")
    fun getCategorias(): Call<CategoriaResponse>
}

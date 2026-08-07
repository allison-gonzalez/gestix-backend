package com.example.nefis

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.Toast
import androidx.fragment.app.FragmentActivity
import com.google.gson.Gson
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class LoginActivity : FragmentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        val etEmail = findViewById<EditText>(R.id.etEmail)
        val etPassword = findViewById<EditText>(R.id.etPassword)
        val btnLogin = findViewById<Button>(R.id.btnLogin)

        btnLogin.setOnClickListener {
            val email = etEmail.text.toString().trim()
            val password = etPassword.text.toString().trim()

            if (email.isEmpty() || password.isEmpty()) {
                Toast.makeText(this, "Por favor llena todos los campos", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            val request = LoginRequest(email, password)
            
            RetrofitClient.api.login(request).enqueue(object : Callback<LoginResponse> {
                override fun onResponse(call: Call<LoginResponse>, response: Response<LoginResponse>) {
                    if (response.isSuccessful && response.body()?.success == true) {
                        val body = response.body()
                        val user = body?.user
                        val userId = user?.id?.toString() ?: ""
                        val token = body?.accessToken ?: ""
                        
                        // Guardar información del usuario logueado
                        val prefs = getSharedPreferences("gestix_prefs", Context.MODE_PRIVATE)
                        prefs.edit().apply {
                            putString("user_id", userId)
                            putString("user_name", user?.nombre ?: "")
                            putString("user_email", user?.correo ?: "")
                            putString("user_dept", user?.departamentoId ?: "N/A")
                            putString("auth_token", token)
                            apply()
                        }

                        val intent = Intent(this@LoginActivity, MainActivity2::class.java)
                        startActivity(intent)
                        finish()
                    } else {
                        // AQUÍ ESTÁ EL CAMBIO: Leemos el error exacto que manda Laravel
                        val errorBody = response.errorBody()?.string()
                        val errorResponse = Gson().fromJson(errorBody, LoginResponse::class.java)
                        val msg = errorResponse?.message ?: "Datos incorrectos"
                        
                        Toast.makeText(this@LoginActivity, "Laravel dice: $msg", Toast.LENGTH_LONG).show()
                    }
                }

                override fun onFailure(call: Call<LoginResponse>, t: Throwable) {
                    Toast.makeText(this@LoginActivity, "Error de conexión: ${t.message}", Toast.LENGTH_SHORT).show()
                }
            })
        }
    }
}

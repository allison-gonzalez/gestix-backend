package com.example.nefis

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.fragment.app.FragmentActivity

class ProfileActivity : FragmentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_profile)

        val prefs = getSharedPreferences("gestix_prefs", Context.MODE_PRIVATE)
        
        findViewById<TextView>(R.id.tvUserName).text = "Nombre: ${prefs.getString("user_name", "-")}"
        findViewById<TextView>(R.id.tvUserEmail).text = "Correo: ${prefs.getString("user_email", "-")}"
        findViewById<TextView>(R.id.tvUserDept).text = "Departamento: ${prefs.getString("user_dept", "-")}"

        findViewById<Button>(R.id.btnLogout).setOnClickListener {
            // Limpiar sesión
            prefs.edit().clear().apply()
            
            // Regresar al login
            val intent = Intent(this, LoginActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            finish()
        }
    }
}

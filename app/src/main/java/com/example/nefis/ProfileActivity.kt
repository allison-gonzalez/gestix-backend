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
        
        findViewById<TextView>(R.id.tvProfileName).text = prefs.getString("user_name", "Usuario")
        findViewById<TextView>(R.id.tvProfileEmail).text = prefs.getString("user_email", "correo@ejemplo.com")

        findViewById<Button>(R.id.btnLogout).setOnClickListener {
            prefs.edit().clear().apply()
            val intent = Intent(this, LoginActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            finish()
        }
        
        // Efecto de foco para los botones
        val buttons = listOf(R.id.btnLogout, R.id.btnHelp)
        buttons.forEach { id ->
            findViewById<Button>(id).setOnFocusChangeListener { v, hasFocus ->
                if (hasFocus) {
                    v.animate().scaleX(1.05f).scaleY(1.05f).setDuration(200).start()
                } else {
                    v.animate().scaleX(1.0f).scaleY(1.0f).setDuration(200).start()
                }
            }
        }
    }
}

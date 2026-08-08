package com.example.nefis

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.ImageView
import android.widget.TextView
import androidx.fragment.app.FragmentActivity

class MainActivity2 : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        val prefs = getSharedPreferences("gestix_prefs", Context.MODE_PRIVATE)
        val userName = prefs.getString("user_name", "Usuario")
        
        findViewById<TextView>(R.id.tvWelcome).text = "Bienvenido $userName"

        val userIcon = findViewById<ImageView>(R.id.user_icon_main)
        userIcon.setOnClickListener {
            startActivity(Intent(this, ProfileActivity::class.java))
        }
        
        userIcon.setOnFocusChangeListener { v, hasFocus ->
            if (hasFocus) {
                v.animate().scaleX(1.1f).scaleY(1.1f).setDuration(200).start()
            } else {
                v.animate().scaleX(1.0f).scaleY(1.0f).setDuration(200).start()
            }
        }
    }

    fun updateActiveCount(count: Int) {
        findViewById<TextView>(R.id.tvActiveCount)?.text = count.toString()
    }
}

package com.example.nefis

import android.os.Bundle
import androidx.fragment.app.FragmentActivity
import android.widget.TextView

class PlayActivity : FragmentActivity() {

    companion object{
        const val MOVIE_EXTRA="extra:movie"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_play)

        val titleView = findViewById<TextView>(R.id.txtPlayTitle)
        val descriptionView = findViewById<TextView>(R.id.txtPlayDescription)

        val ticket: Ticket? = intent.getParcelableExtra<Ticket>(PlayActivity.MOVIE_EXTRA)

        if (ticket != null) {
            titleView.text = ticket.titulo
            descriptionView.text = """
                Prioridad: ${ticket.prioridad}
                Estado: ${ticket.estado ?: "Pendiente"}
                
                ${ticket.descripcion}
                
                Creado el: ${ticket.fechaCreacion ?: "N/A"}
                Ticket ID: ${ticket.id}
            """.trimIndent()
        }
    }
}
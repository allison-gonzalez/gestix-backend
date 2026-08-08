package com.example.nefis

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.leanback.widget.Presenter

class Card: Presenter(){
    override fun onCreateViewHolder(parent: ViewGroup): ViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.card_item_horizontal, parent, false)
        return ViewHolder(view)
    }

    override fun onBindViewHolder(viewHolder: ViewHolder, item: Any?) {
        val view = viewHolder.view
        val title = view.findViewById<TextView>(R.id.card_title)
        val priority = view.findViewById<TextView>(R.id.card_priority)
        val statusText = view.findViewById<TextView>(R.id.card_status_text)
        val statusBar = view.findViewById<View>(R.id.card_status_bar)

        if (item is Ticket) {
            title.text = item.titulo
            priority.text = "Prioridad: ${item.prioridad}"
            statusText.text = (item.estado ?: "ABIERTO").uppercase()
            
            // Cambiar color de la barra según prioridad
            val color = when(item.prioridad.lowercase()) {
                "alta", "critica" -> 0xFFFF7F7F.toInt()
                "media" -> 0xFFFFC107.toInt()
                else -> 0xFF8BC34A.toInt()
            }
            statusBar.setBackgroundColor(color)
        }
    }

    override fun onUnbindViewHolder(viewHolder: ViewHolder) {
    }
}

package com.example.nefis

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.leanback.widget.Presenter
import com.bumptech.glide.Glide

class Card: Presenter(){
    override fun onCreateViewHolder(parent: ViewGroup): ViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.card_item_horizontal, parent, false)
        return ViewHolder(view)
    }

    override fun onBindViewHolder(viewHolder: ViewHolder, item: Any?) {
        val video = item as Video
        val view = viewHolder.view
        
        val image = view.findViewById<ImageView>(R.id.card_image)
        val title = view.findViewById<TextView>(R.id.card_title)
        val description = view.findViewById<TextView>(R.id.card_description)

        title.text = video.title
        // Limpiamos la descripción para que solo muestre el texto descriptivo
        description.text = video.description

        val uri = "android.resource://${view.context.packageName}/${video.video}"
        Glide.with(view.context)
            .load(uri)
            .centerCrop()
            .into(image)
    }

    override fun onUnbindViewHolder(viewHolder: ViewHolder) {
        val image = viewHolder.view.findViewById<ImageView>(R.id.card_image)
        Glide.with(viewHolder.view.context).clear(image)
    }
}
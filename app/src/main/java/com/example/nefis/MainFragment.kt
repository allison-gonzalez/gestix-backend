package com.example.nefis

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.View
import androidx.leanback.app.BrowseSupportFragment
import androidx.leanback.widget.ArrayObjectAdapter
import androidx.leanback.widget.HeaderItem
import androidx.leanback.widget.ListRow
import androidx.leanback.widget.ListRowPresenter
import androidx.leanback.widget.OnItemViewClickedListener
import androidx.leanback.widget.OnItemViewSelectedListener

class MainFragment: BrowseSupportFragment() {
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        title = "Blockbuster"
        
        // Configuración de colores: Azul para la marca y Amarillo para el fondo
        brandColor = Color.parseColor("#0057B8")
        searchAffordanceColor = Color.parseColor("#FFD200")
        
        // Forzar títulos de la aplicación a negro (cuando sea posible)
        // Nota: El título principal de BrowseSupportFragment a veces requiere personalización extra

        val categories = ArrayObjectAdapter(ListRowPresenter())

        // Categoría Lego
        val legoVideos = ArrayObjectAdapter(Card())
        legoVideos.addAll(0, listOf(
            Video("Ciudad", "Lego", R.mipmap.mishito, "Video de ciudad lego", R.raw.ciudad),
            Video("Ejército", "Lego", R.mipmap.mishito, "Video de ejército lego", R.raw.ejercito),
            Video("Marvel", "Lego", R.mipmap.mishito, "Video de marvel lego", R.raw.marvel),
            Video("Helicóptero", "Lego", R.mipmap.mishito, "Video de helicóptero lego", R.raw.helicoptero),
            Video("City", "Lego", R.mipmap.mishito, "Video de city lego", R.raw.city),
        ))
        categories.add(ListRow(HeaderItem(0, "Lego"), legoVideos))

        // Categoría Dragones
        val dragonVideos = ArrayObjectAdapter(Card())
        dragonVideos.addAll(0, listOf(
            Video("Chimuelo", "Dragones", R.mipmap.mandarino, "Video de Chimuelo", R.raw.chimuelo),
            Video("Dragón", "Dragones", R.mipmap.mandarino, "Video de dragón", R.raw.dragon),
            Video("Playa", "Dragones", R.mipmap.mandarino, "Video de playa", R.raw.playa),
            Video("Vuelo", "Dragones", R.mipmap.mandarino, "Video de vuelo", R.raw.vuelo),
            Video("Grito", "Dragones", R.mipmap.mandarino, "Video de grito", R.raw.grito)
        ))
        categories.add(ListRow(HeaderItem(1, "Dragones"), dragonVideos))

        // Categoría Transformers
        val transformerVideos = ArrayObjectAdapter(Card())
        transformerVideos.addAll(0, listOf(
            Video("Batalla", "Transformers", R.mipmap.mishito, "Video de batalla", R.raw.batalla),
            Video("Jass", "Transformers", R.mipmap.mishito, "Video de Jass", R.raw.jass),
            Video("Optimus", "Transformers", R.mipmap.mishito, "Video de Optimus", R.raw.optimus),
            Video("Hound", "Transformers", R.mipmap.mishito, "Video de Hound", R.raw.hound),
            Video("Transformers", "Transformers", R.mipmap.mishito, "Video de Transformers", R.raw.transformers)
        ))
        categories.add(ListRow(HeaderItem(2, "Transformers"), transformerVideos))

        // Categoría Animales
        val animalVideos = ArrayObjectAdapter(Card())
        animalVideos.addAll(0, listOf(
            Video("Perro", "Animales", R.mipmap.mandarino, "Video de perro", R.raw.perro),
            Video("Gatos", "Animales", R.mipmap.mandarino, "Video de gatos", R.raw.gatos),
            Video("Nutria", "Animales", R.mipmap.mandarino, "Video de nutria", R.raw.nutria),
            Video("León", "Animales", R.mipmap.mandarino, "Video de león", R.raw.leon),
            Video("Pelota", "Animales", R.mipmap.mandarino, "Video de pelota", R.raw.pelota),
        ))
        categories.add(ListRow(HeaderItem(3, "Animales"), animalVideos))

        // Categoría Terror
        val terrorVideos = ArrayObjectAdapter(Card())
        terrorVideos.addAll(0, listOf(
            Video("Camilla", "Terror", R.mipmap.mishito, "Video de camilla", R.raw.camilla),
            Video("Hospital", "Terror", R.mipmap.mishito, "Video de hospital", R.raw.hospital),
            Video("Soldados", "Terror", R.mipmap.mishito, "Video de soldados", R.raw.soldados),
            Video("Muñecos", "Terror", R.mipmap.mishito, "Video de muñecos", R.raw.munecos),
            Video("Pasillo", "Terror", R.mipmap.mishito, "Video de pasillo", R.raw.pasillo),
        ))
        categories.add(ListRow(HeaderItem(4, "Terror"), terrorVideos))

        adapter = categories

        onItemViewClickedListener = OnItemViewClickedListener { _, video, _, _ ->
            val intent = Intent(requireContext(), PlayActivity::class.java).apply {
                putExtra(PlayActivity.MOVIE_EXTRA, video as Video)
            }
            startActivity(intent)
        }
    }
}
package com.example.nefis

import android.os.Bundle
import androidx.fragment.app.FragmentActivity
import android.net.Uri
import android.util.Log
import android.widget.MediaController
import android.widget.TextView
import android.widget.VideoView

class PlayActivity : FragmentActivity() {

    companion object{
        const val MOVIE_EXTRA="extra:movie"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_play)

        val videoView = findViewById<VideoView>(R.id.videoView)
        val titleView = findViewById<TextView>(R.id.txtPlayTitle)
        val descriptionView = findViewById<TextView>(R.id.txtPlayDescription)

        val video: Video? =intent.getParcelableExtra<Video>(PlayActivity.MOVIE_EXTRA)

        if (video != null) {
            val path = "android.resource://" + packageName + "/" + video.video
            val uri = Uri.parse(path)
            
            titleView.text = video.title
            descriptionView.text = video.description

            val mediaController = MediaController(this)
            mediaController.setAnchorView(videoView)
            videoView.setMediaController(mediaController)

            videoView.setVideoURI(uri)
            videoView.requestFocus()
            videoView.start()
        }
    }
}
package com.example.nefis

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.leanback.app.VerticalGridSupportFragment
import androidx.leanback.widget.*
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class MainFragment : VerticalGridSupportFragment() {
    private lateinit var mGridAdapter: ArrayObjectAdapter
    private var userId: String = ""
    private var userDeptId: String = ""

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Configuración de la lista vertical (1 columna ancha)
        val gridPresenter = VerticalGridPresenter(FocusHighlight.ZOOM_FACTOR_SMALL, false)
        gridPresenter.numberOfColumns = 1 
        setGridPresenter(gridPresenter)

        mGridAdapter = ArrayObjectAdapter(Card())
        adapter = mGridAdapter

        val prefs = requireContext().getSharedPreferences("gestix_prefs", Context.MODE_PRIVATE)
        userId = prefs.getString("user_id", "") ?: ""
        userDeptId = prefs.getString("user_dept", "") ?: ""

        loadTickets()
        setupWebSocket()

        onItemViewClickedListener = OnItemViewClickedListener { _, item, _, _ ->
            if (item is Ticket) {
                val intent = Intent(requireContext(), PlayActivity::class.java).apply {
                    putExtra(PlayActivity.MOVIE_EXTRA, item)
                }
                startActivity(intent)
            }
        }
    }

    private fun loadTickets() {
        RetrofitClient.api.getTickets().enqueue(object : Callback<TicketResponse> {
            override fun onResponse(call: Call<TicketResponse>, response: Response<TicketResponse>) {
                if (response.isSuccessful) {
                    val allTickets = response.body()?.data ?: emptyList()
                    updateUI(allTickets)
                }
            }
            override fun onFailure(call: Call<TicketResponse>, t: Throwable) {
                if (isAdded) Toast.makeText(requireContext(), "Error de red", Toast.LENGTH_LONG).show()
            }
        })
    }

    private fun updateUI(allTickets: List<Ticket>) {
        // Filtramos para mostrar lo del usuario y su departamento
        val filteredTickets = allTickets.filter { 
            val autorId = it.usuarioAutorId?.split(".")?.get(0) ?: ""
            val deptoId = it.departamentoId?.split(".")?.get(0) ?: ""
            val userDeptClean = userDeptId.split(".")[0]
            
            autorId == userId || deptoId == userDeptClean
        }

        activity?.runOnUiThread {
            mGridAdapter.clear()
            mGridAdapter.addAll(0, filteredTickets)
            
            // Actualizamos el contador en la cabecera
            (activity as? MainActivity2)?.updateActiveCount(filteredTickets.size)
        }
    }

    private fun setupWebSocket() {
        try {
            SocketHandler.setSocket()
            SocketHandler.establishConnection()
            SocketHandler.getSocket().on("nuevo_ticket") { loadTickets() }
        } catch (e: Exception) { e.printStackTrace() }
    }

    override fun onDestroy() {
        super.onDestroy()
        SocketHandler.closeConnection()
    }
}

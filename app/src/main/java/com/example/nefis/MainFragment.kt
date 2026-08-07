package com.example.nefis

import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.leanback.app.BrowseSupportFragment
import androidx.leanback.widget.*
import io.socket.emitter.Emitter
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class MainFragment : BrowseSupportFragment() {

    private lateinit var mCategoriesAdapter: ArrayObjectAdapter
    private var userId: String = ""
    private var userDeptId: String = ""

    // Mapa categoria_id -> departamento_id, para tickets que no traen departamento_id directo
    private var categoriaADepartamento: Map<Int, Int> = emptyMap()

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        title = "TVtickets"

        searchAffordanceColor = Color.parseColor("#1E1E1E")
        try {
            badgeDrawable = ContextCompat.getDrawable(requireContext(), R.mipmap.ic_user)
        } catch (e: Exception) {}

        setOnSearchClickedListener {
            startActivity(Intent(requireContext(), ProfileActivity::class.java))
        }

        mCategoriesAdapter = ArrayObjectAdapter(ListRowPresenter())
        adapter = mCategoriesAdapter

        val prefs = requireContext().getSharedPreferences("gestix_prefs", Context.MODE_PRIVATE)
        userId = prefs.getString("user_id", "") ?: ""
        userDeptId = prefs.getString("user_dept", "") ?: ""

        loadCategoriasYTickets()
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

    private fun loadCategoriasYTickets() {
        // 1. Primero traemos categorías para armar el mapa categoria_id -> departamento_id
        RetrofitClient.api.getCategorias().enqueue(object : Callback<CategoriaResponse> {
            override fun onResponse(call: Call<CategoriaResponse>, response: Response<CategoriaResponse>) {
                val categorias = response.body()?.data ?: emptyList()
                categoriaADepartamento = categorias.associate { it.id to it.departamentoId }

                // 2. Ya con el mapa listo, traemos los tickets
                loadTickets()
            }

            override fun onFailure(call: Call<CategoriaResponse>, t: Throwable) {
                // Si falla categorías, igual intentamos cargar tickets (sin resolver depto por categoría)
                loadTickets()
            }
        })
    }

    private fun loadTickets() {
        RetrofitClient.api.getTickets().enqueue(object : Callback<TicketResponse> {
            override fun onResponse(call: Call<TicketResponse>, response: Response<TicketResponse>) {
                val allTickets = response.body()?.data ?: emptyList()
                updateUI(allTickets)
            }

            override fun onFailure(call: Call<TicketResponse>, t: Throwable) {
                if (isAdded) Toast.makeText(requireContext(), "Error de red: ${t.message}", Toast.LENGTH_LONG).show()
            }
        })
    }

    // Resuelve el departamento real de un ticket: usa departamento_id si viene bien,
    // si no, lo busca a través de categoria_id -> mapa de categorías
    private fun resolverDepartamentoId(ticket: Ticket): String {
        val directo = ticket.departamentoId?.toIntOrNull()
        if (directo != null && directo != 0) {
            return directo.toString()
        }
        val catId = ticket.categoriaId?.toIntOrNull()
        val porCategoria = catId?.let { categoriaADepartamento[it] }
        return porCategoria?.toString() ?: ""
    }

    private fun updateUI(allTickets: List<Ticket>) {
        android.util.Log.d("GESTIX_DEBUG", "Usuario Logueado ID: $userId | DeptID: $userDeptId")
        android.util.Log.d("GESTIX_DEBUG", "Total Tickets Recibidos: ${allTickets.size}")

        android.util.Log.d("GESTIX_DEBUG", "Mapa categorias->depto tiene ${categoriaADepartamento.size} entradas")
        android.util.Log.d("GESTIX_DEBUG", "Mapa: $categoriaADepartamento")

        allTickets.take(5).forEach { t ->
            val resuelto = resolverDepartamentoId(t)
            android.util.Log.d("GESTIX_DEBUG", "Ticket ${t.id}: categoriaId=${t.categoriaId}, departamentoId=${t.departamentoId}, resuelto=$resuelto, usuarioAutorId=${t.usuarioAutorId}")
        }

        // 1. MIS TICKETS (asignados al usuario logueado)
        val myTickets = allTickets.filter { it.usuarioAutorId == userId }

        // 2. TICKETS DE MI DEPARTAMENTO (resolviendo departamento vía categoría si hace falta)
        val deptTickets = allTickets.filter { ticket ->
            val deptResuelto = resolverDepartamentoId(ticket)
            val aDept = ticket.autorDepartamentoId ?: ""

            (deptResuelto == userDeptId || aDept == userDeptId) && ticket.usuarioAutorId != userId
        }

        android.util.Log.d("GESTIX_DEBUG", "Tickets de depto encontrados: ${deptTickets.size}")

        activity?.runOnUiThread {
            mCategoriesAdapter.clear()

            if (myTickets.isNotEmpty()) {
                val agrupadosMios = myTickets.groupBy { it.prioridad ?: "media" }
                agrupadosMios.entries.forEachIndexed { index, (prioridad, lista) ->
                    val rowAdapter = ArrayObjectAdapter(Card())
                    rowAdapter.addAll(0, lista)
                    mCategoriesAdapter.add(ListRow(HeaderItem(index.toLong(), "Míos - Prioridad: $prioridad"), rowAdapter))
                }
            }

            if (deptTickets.isNotEmpty()) {
                val agrupadosDept = deptTickets.groupBy { it.categoriaNombre }
                agrupadosDept.entries.forEachIndexed { index, (categoria, lista) ->
                    val rowAdapter = ArrayObjectAdapter(Card())
                    rowAdapter.addAll(0, lista)
                    mCategoriesAdapter.add(ListRow(HeaderItem(index.toLong() + 50, "Depto - $categoria"), rowAdapter))
                }
            } else {
                android.util.Log.w("GESTIX_DEBUG", "No se encontraron tickets para el departamento $userDeptId")
            }
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
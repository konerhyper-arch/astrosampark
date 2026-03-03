package com.astrosampark.leads.ui.myleads

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.astrosampark.leads.data.model.PurchasedLead
import com.astrosampark.leads.databinding.FragmentMyLeadsBinding
import com.google.android.material.chip.Chip
import com.astrosampark.leads.util.PhoneUtils
import com.google.android.material.snackbar.Snackbar

class MyLeadsFragment : Fragment() {

    private var _binding: FragmentMyLeadsBinding? = null
    private val binding get() = _binding!!
    private val viewModel: MyLeadsViewModel by viewModels()
    private lateinit var adapter: MyLeadAdapter

    private val statusFilters = listOf("All", "New", "Contacted", "Converted", "Not Interested")

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentMyLeadsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        setupRecyclerView()
        setupFilterChips()
        setupSwipeRefresh()
        observeViewModel()
        viewModel.loadMyLeads()
    }

    private fun setupRecyclerView() {
        adapter = MyLeadAdapter { lead -> showLeadDialog(lead) }
        binding.recyclerView.adapter = adapter
        binding.recyclerView.layoutManager = LinearLayoutManager(requireContext())
    }

    private fun setupFilterChips() {
        statusFilters.forEach { status ->
            val chip = Chip(requireContext()).apply {
                text = status
                isCheckable = true
                isChecked = status == "All"
            }
            chip.setOnCheckedChangeListener { _, checked ->
                if (checked) viewModel.applyFilter(status)
            }
            binding.chipGroupFilter.addView(chip)
        }
    }

    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            viewModel.loadMyLeads()
        }
    }

    private fun observeViewModel() {
        viewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.swipeRefresh.isRefreshing = loading
            if (loading) {
                binding.recyclerView.visibility = View.GONE
                binding.layoutEmpty.visibility = View.GONE
            }
        }

        viewModel.filteredLeads.observe(viewLifecycleOwner) { leads ->
            binding.recyclerView.visibility = if (leads.isEmpty()) View.GONE else View.VISIBLE
            binding.layoutEmpty.visibility = if (leads.isEmpty()) View.VISIBLE else View.GONE
            adapter.submitList(leads)
        }

        viewModel.error.observe(viewLifecycleOwner) { error ->
            error?.let {
                Snackbar.make(binding.root, it, Snackbar.LENGTH_LONG).show()
                viewModel.clearError()
            }
        }
    }

    private fun showLeadDialog(lead: PurchasedLead) {
        val dialog = com.google.android.material.bottomsheet.BottomSheetDialog(requireContext())
        val view = layoutInflater.inflate(
            com.astrosampark.leads.R.layout.bottom_sheet_lead_detail, null
        )
        dialog.setContentView(view)

        view.findViewById<android.widget.TextView>(
            com.astrosampark.leads.R.id.tvDialogName
        )?.text = lead.name
        view.findViewById<android.widget.TextView>(
            com.astrosampark.leads.R.id.tvDialogPhone
        )?.text = lead.phone
        view.findViewById<android.widget.TextView>(
            com.astrosampark.leads.R.id.tvDialogCity
        )?.text = "${lead.city} • ${lead.category}"

        view.findViewById<android.widget.Button>(
            com.astrosampark.leads.R.id.btnDialogCall
        )?.setOnClickListener {
            startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${lead.phone}")))
        }

        view.findViewById<android.widget.Button>(
            com.astrosampark.leads.R.id.btnDialogWhatsApp
        )?.setOnClickListener {
            startActivity(
                Intent(Intent.ACTION_VIEW, Uri.parse(PhoneUtils.whatsAppUrl(lead.phone)))
            )
        }

        dialog.show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}

package com.astrosampark.leads.ui.marketplace

import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ArrayAdapter
import androidx.core.widget.addTextChangedListener
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.astrosampark.leads.R
import com.astrosampark.leads.databinding.FragmentMarketplaceBinding
import com.astrosampark.leads.ui.leaddetail.LeadDetailActivity
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.chip.Chip

class MarketplaceFragment : Fragment() {

    private var _binding: FragmentMarketplaceBinding? = null
    private val binding get() = _binding!!
    private val viewModel: MarketplaceViewModel by viewModels()
    private lateinit var adapter: LeadAdapter

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentMarketplaceBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        setupRecyclerView()
        setupSwipeRefresh()
        setupSearchAndFilter()
        observeViewModel()
        viewModel.loadMarketplace(refresh = true)
    }

    private fun setupRecyclerView() {
        adapter = LeadAdapter { lead ->
            val intent = Intent(requireContext(), LeadDetailActivity::class.java).apply {
                putExtra(LeadDetailActivity.EXTRA_LEAD_ID, lead.id)
                putExtra(LeadDetailActivity.EXTRA_LEAD_PRICE, lead.price)
                putExtra(LeadDetailActivity.EXTRA_LISTING_ID, lead.listingId)
            }
            startActivity(intent)
        }
        binding.recyclerView.adapter = adapter
        val layoutManager = LinearLayoutManager(requireContext())
        binding.recyclerView.layoutManager = layoutManager

        // Infinite scroll
        binding.recyclerView.addOnScrollListener(object : RecyclerView.OnScrollListener() {
            override fun onScrolled(recyclerView: RecyclerView, dx: Int, dy: Int) {
                super.onScrolled(recyclerView, dx, dy)
                val visibleItemCount = layoutManager.childCount
                val totalItemCount = layoutManager.itemCount
                val firstVisibleItem = layoutManager.findFirstVisibleItemPosition()
                if (visibleItemCount + firstVisibleItem >= totalItemCount - 3) {
                    viewModel.loadNextPage()
                }
            }
        })
    }

    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            viewModel.loadMarketplace(refresh = true)
        }
        binding.swipeRefresh.setColorSchemeResources(R.color.purple_primary)
    }

    private fun setupSearchAndFilter() {
        binding.etSearch.addTextChangedListener { text ->
            viewModel.applyFilter("search", text.toString())
        }

        binding.btnFilter.setOnClickListener { showFilterBottomSheet() }
    }

    private fun showFilterBottomSheet() {
        val dialog = BottomSheetDialog(requireContext())
        val sheetView = layoutInflater.inflate(R.layout.bottom_sheet_filter, null)
        dialog.setContentView(sheetView)

        val categories = arrayOf("All", "Love", "Marriage", "Career", "Business", "Health", "Other")
        val spinnerCategory = sheetView.findViewById<android.widget.Spinner>(R.id.spinnerCategory)
        spinnerCategory.adapter = ArrayAdapter(requireContext(), android.R.layout.simple_spinner_item, categories)

        val chipPremium = sheetView.findViewById<Chip>(R.id.chipPremium)
        val chipVerified = sheetView.findViewById<Chip>(R.id.chipVerified)
        val chipBasic = sheetView.findViewById<Chip>(R.id.chipBasic)
        val etCity = sheetView.findViewById<android.widget.EditText>(R.id.etFilterCity)
        val btnApply = sheetView.findViewById<android.widget.Button>(R.id.btnApplyFilter)
        val btnClear = sheetView.findViewById<android.widget.Button>(R.id.btnClearFilter)

        btnApply.setOnClickListener {
            val selectedCategory = spinnerCategory.selectedItem.toString()
            if (selectedCategory != "All") viewModel.applyFilter("category", selectedCategory)

            val city = etCity.text.toString()
            if (city.isNotBlank()) viewModel.applyFilter("city", city)

            val badges = mutableListOf<String>()
            if (chipPremium.isChecked) badges.add("Premium")
            if (chipVerified.isChecked) badges.add("Verified")
            if (chipBasic.isChecked) badges.add("Basic")
            if (badges.isNotEmpty()) viewModel.applyFilter("badge", badges.joinToString(","))

            dialog.dismiss()
        }

        btnClear.setOnClickListener {
            viewModel.clearFilters()
            dialog.dismiss()
        }

        dialog.show()
    }

    private fun observeViewModel() {
        viewModel.state.observe(viewLifecycleOwner) { state ->
            binding.swipeRefresh.isRefreshing = false
            when (state) {
                is MarketplaceState.Loading -> {
                    binding.shimmerLayout.visibility = View.VISIBLE
                    binding.shimmerLayout.startShimmer()
                    binding.recyclerView.visibility = View.GONE
                    binding.layoutEmpty.visibility = View.GONE
                }
                is MarketplaceState.Success -> {
                    binding.shimmerLayout.stopShimmer()
                    binding.shimmerLayout.visibility = View.GONE
                    binding.recyclerView.visibility = View.VISIBLE
                    binding.layoutEmpty.visibility = View.GONE
                    adapter.submitList(state.leads)
                }
                is MarketplaceState.Error -> {
                    binding.shimmerLayout.stopShimmer()
                    binding.shimmerLayout.visibility = View.GONE
                    binding.recyclerView.visibility = View.GONE
                    binding.layoutEmpty.visibility = View.VISIBLE
                    binding.tvEmptyMessage.text = state.message
                }
                is MarketplaceState.Empty -> {
                    binding.shimmerLayout.stopShimmer()
                    binding.shimmerLayout.visibility = View.GONE
                    binding.recyclerView.visibility = View.GONE
                    binding.layoutEmpty.visibility = View.VISIBLE
                    binding.tvEmptyMessage.text = "No leads found"
                }
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}

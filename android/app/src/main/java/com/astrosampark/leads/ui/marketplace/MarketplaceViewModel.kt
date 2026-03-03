package com.astrosampark.leads.ui.marketplace

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.model.Lead
import com.astrosampark.leads.data.repository.LeadsRepository
import kotlinx.coroutines.launch

sealed class MarketplaceState {
    object Loading : MarketplaceState()
    data class Success(val leads: List<Lead>, val hasMore: Boolean) : MarketplaceState()
    data class Error(val message: String) : MarketplaceState()
    object Empty : MarketplaceState()
}

class MarketplaceViewModel : ViewModel() {

    private val repository = LeadsRepository()

    private val _state = MutableLiveData<MarketplaceState>()
    val state: LiveData<MarketplaceState> = _state

    private var currentPage = 1
    private var isLastPage = false
    private val allLeads = mutableListOf<Lead>()

    val filters = mutableMapOf<String, String>()

    fun loadMarketplace(refresh: Boolean = false) {
        if (refresh) {
            currentPage = 1
            isLastPage = false
            allLeads.clear()
        }
        if (isLastPage) return
        if (_state.value is MarketplaceState.Loading) return

        viewModelScope.launch {
            _state.value = MarketplaceState.Loading
            try {
                val params = filters.toMutableMap()
                params["page"] = currentPage.toString()

                val response = repository.getMarketplace(params)
                if (response.isSuccessful) {
                    val body = response.body()!!
                    allLeads.addAll(body.data)
                    isLastPage = (body.meta?.current_page ?: 1) >= (body.meta?.last_page ?: 1)
                    currentPage++

                    if (allLeads.isEmpty()) {
                        _state.value = MarketplaceState.Empty
                    } else {
                        _state.value = MarketplaceState.Success(allLeads.toList(), !isLastPage)
                    }
                } else {
                    _state.value = MarketplaceState.Error("Failed to load leads")
                }
            } catch (e: Exception) {
                _state.value = MarketplaceState.Error(e.message ?: "Network error")
            }
        }
    }

    fun applyFilter(key: String, value: String) {
        if (value.isBlank()) {
            filters.remove(key)
        } else {
            filters[key] = value
        }
        loadMarketplace(refresh = true)
    }

    fun clearFilters() {
        filters.clear()
        loadMarketplace(refresh = true)
    }

    fun loadNextPage() {
        loadMarketplace(refresh = false)
    }
}

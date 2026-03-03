package com.astrosampark.leads.ui.myleads

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.model.PurchasedLead
import com.astrosampark.leads.data.repository.LeadsRepository
import kotlinx.coroutines.launch

class MyLeadsViewModel : ViewModel() {

    private val repository = LeadsRepository()

    private val _leads = MutableLiveData<List<PurchasedLead>>()
    val leads: LiveData<List<PurchasedLead>> = _leads

    private val _filteredLeads = MutableLiveData<List<PurchasedLead>>()
    val filteredLeads: LiveData<List<PurchasedLead>> = _filteredLeads

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private var currentFilter = "All"

    fun loadMyLeads() {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val response = repository.getMyLeads()
                if (response.isSuccessful) {
                    val allLeads = response.body()?.data ?: emptyList()
                    _leads.value = allLeads
                    applyFilter(currentFilter)
                } else {
                    _error.value = "Failed to load your leads"
                }
            } catch (e: Exception) {
                _error.value = e.message ?: "Network error"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun applyFilter(filter: String) {
        currentFilter = filter
        val all = _leads.value ?: emptyList()
        _filteredLeads.value = if (filter == "All") {
            all
        } else {
            all.filter { it.leadState.equals(filter, ignoreCase = true) }
        }
    }

    fun clearError() {
        _error.value = null
    }
}

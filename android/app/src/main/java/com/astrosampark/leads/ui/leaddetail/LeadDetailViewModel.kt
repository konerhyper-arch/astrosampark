package com.astrosampark.leads.ui.leaddetail

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.model.BuyLeadResponse
import com.astrosampark.leads.data.model.LeadPreview
import com.astrosampark.leads.data.repository.LeadsRepository
import kotlinx.coroutines.launch

sealed class LeadDetailState {
    object Loading : LeadDetailState()
    data class PreviewLoaded(val preview: LeadPreview) : LeadDetailState()
    data class Purchased(val data: BuyLeadResponse) : LeadDetailState()
    data class Error(val message: String) : LeadDetailState()
}

sealed class ActionState {
    object Idle : ActionState()
    object Loading : ActionState()
    data class Success(val message: String) : ActionState()
    data class Error(val message: String) : ActionState()
}

class LeadDetailViewModel : ViewModel() {

    private val repository = LeadsRepository()

    private val _state = MutableLiveData<LeadDetailState>()
    val state: LiveData<LeadDetailState> = _state

    private val _actionState = MutableLiveData<ActionState>(ActionState.Idle)
    val actionState: LiveData<ActionState> = _actionState

    fun loadPreview(leadId: Int) {
        viewModelScope.launch {
            _state.value = LeadDetailState.Loading
            try {
                val response = repository.getLeadPreview(leadId)
                if (response.isSuccessful && response.body()?.success == true) {
                    _state.value = LeadDetailState.PreviewLoaded(response.body()!!.data!!)
                } else {
                    _state.value = LeadDetailState.Error(
                        response.body()?.message ?: "Failed to load lead details"
                    )
                }
            } catch (e: Exception) {
                _state.value = LeadDetailState.Error(e.message ?: "Network error")
            }
        }
    }

    fun buyLead(leadId: Int) {
        viewModelScope.launch {
            _state.value = LeadDetailState.Loading
            try {
                val response = repository.buyLead(leadId)
                if (response.isSuccessful && response.body()?.success == true) {
                    _state.value = LeadDetailState.Purchased(response.body()!!.data!!)
                } else {
                    val msg = response.body()?.message ?: "Purchase failed"
                    _state.value = LeadDetailState.Error(msg)
                }
            } catch (e: Exception) {
                _state.value = LeadDetailState.Error(e.message ?: "Network error")
            }
        }
    }

    fun updateStatus(purchaseId: Int, status: String) {
        viewModelScope.launch {
            _actionState.value = ActionState.Loading
            try {
                val response = repository.updateLeadStatus(purchaseId, status)
                if (response.isSuccessful) {
                    _actionState.value = ActionState.Success("Status updated to $status")
                } else {
                    _actionState.value = ActionState.Error("Failed to update status")
                }
            } catch (e: Exception) {
                _actionState.value = ActionState.Error(e.message ?: "Network error")
            }
        }
    }

    fun rateLead(purchaseId: Int, rating: Int) {
        viewModelScope.launch {
            _actionState.value = ActionState.Loading
            try {
                val response = repository.rateLead(purchaseId, rating)
                if (response.isSuccessful) {
                    _actionState.value = ActionState.Success("Thank you for your rating!")
                } else {
                    _actionState.value = ActionState.Error("Failed to submit rating")
                }
            } catch (e: Exception) {
                _actionState.value = ActionState.Error(e.message ?: "Network error")
            }
        }
    }

    fun requestRefund(purchaseId: Int, reason: String) {
        viewModelScope.launch {
            _actionState.value = ActionState.Loading
            try {
                val response = repository.requestRefund(purchaseId, reason)
                if (response.isSuccessful) {
                    _actionState.value = ActionState.Success("Refund request submitted")
                } else {
                    _actionState.value = ActionState.Error("Failed to request refund")
                }
            } catch (e: Exception) {
                _actionState.value = ActionState.Error(e.message ?: "Network error")
            }
        }
    }

    fun resetActionState() {
        _actionState.value = ActionState.Idle
    }
}

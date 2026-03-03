package com.astrosampark.leads.ui.wallet

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.model.RechargeOrderResponse
import com.astrosampark.leads.data.model.Transaction
import com.astrosampark.leads.data.model.WalletBalance
import com.astrosampark.leads.data.repository.WalletRepository
import kotlinx.coroutines.launch

class WalletViewModel : ViewModel() {

    private val repository = WalletRepository()

    private val _balance = MutableLiveData<WalletBalance?>()
    val balance: LiveData<WalletBalance?> = _balance

    private val _transactions = MutableLiveData<List<Transaction>>()
    val transactions: LiveData<List<Transaction>> = _transactions

    private val _rechargeOrder = MutableLiveData<RechargeOrderResponse?>()
    val rechargeOrder: LiveData<RechargeOrderResponse?> = _rechargeOrder

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _successMessage = MutableLiveData<String?>()
    val successMessage: LiveData<String?> = _successMessage

    fun loadWalletData() {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val balanceResponse = repository.getWalletBalance()
                if (balanceResponse.isSuccessful) {
                    _balance.value = balanceResponse.body()?.data
                }

                val txResponse = repository.getTransactions()
                if (txResponse.isSuccessful) {
                    _transactions.value = txResponse.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _error.value = e.message ?: "Network error"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun createRechargeOrder(amount: Int) {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val response = repository.createRechargeOrder(amount)
                if (response.isSuccessful && response.body()?.success == true) {
                    _rechargeOrder.value = response.body()!!.data
                } else {
                    _error.value = response.body()?.message ?: "Failed to create order"
                }
            } catch (e: Exception) {
                _error.value = e.message ?: "Network error"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun verifyRecharge(orderId: String, paymentId: String, signature: String) {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val response = repository.verifyRecharge(orderId, paymentId, signature)
                if (response.isSuccessful && response.body()?.success == true) {
                    _balance.value = response.body()!!.data
                    _successMessage.value = "Wallet recharged successfully!"
                    loadWalletData()
                } else {
                    _error.value = response.body()?.message ?: "Verification failed"
                }
            } catch (e: Exception) {
                _error.value = e.message ?: "Network error"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun clearError() { _error.value = null }
    fun clearSuccess() { _successMessage.value = null }
    fun clearRechargeOrder() { _rechargeOrder.value = null }
}

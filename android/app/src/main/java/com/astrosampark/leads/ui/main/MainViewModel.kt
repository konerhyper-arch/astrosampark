package com.astrosampark.leads.ui.main

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.model.WalletBalance
import com.astrosampark.leads.data.repository.WalletRepository
import kotlinx.coroutines.launch

class MainViewModel : ViewModel() {

    private val walletRepository = WalletRepository()

    private val _walletBalance = MutableLiveData<WalletBalance?>()
    val walletBalance: LiveData<WalletBalance?> = _walletBalance

    fun refreshWalletBalance() {
        viewModelScope.launch {
            try {
                val response = walletRepository.getWalletBalance()
                if (response.isSuccessful) {
                    _walletBalance.value = response.body()?.data
                }
            } catch (_: Exception) { /* silent fail */ }
        }
    }
}

package com.astrosampark.leads.data.repository

import com.astrosampark.leads.data.api.ApiClient
import com.astrosampark.leads.data.model.*

class WalletRepository {

    private val apiService = ApiClient.apiService

    suspend fun getWalletBalance() =
        apiService.getWalletBalance()

    suspend fun getTransactions() =
        apiService.getTransactions()

    suspend fun createRechargeOrder(amount: Int) =
        apiService.createRechargeOrder(RechargeRequest(amount))

    suspend fun verifyRecharge(orderId: String, paymentId: String, signature: String) =
        apiService.verifyRecharge(VerifyRechargeRequest(orderId, paymentId, signature))
}

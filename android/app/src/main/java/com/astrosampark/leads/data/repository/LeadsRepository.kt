package com.astrosampark.leads.data.repository

import com.astrosampark.leads.data.api.ApiClient
import com.astrosampark.leads.data.model.*

class LeadsRepository {

    private val apiService = ApiClient.apiService

    suspend fun getMarketplace(filters: Map<String, String> = emptyMap()) =
        apiService.getMarketplace(filters)

    suspend fun getLeadPreview(id: Int) =
        apiService.getLeadPreview(id)

    suspend fun buyLead(id: Int) =
        apiService.buyLead(id)

    suspend fun getMyLeads() =
        apiService.getMyLeads()

    suspend fun updateLeadStatus(id: Int, status: String) =
        apiService.updateLeadStatus(id, StatusRequest(status))

    suspend fun rateLead(id: Int, rating: Int, feedback: String? = null) =
        apiService.rateLead(id, RateRequest(rating, feedback))

    suspend fun requestRefund(id: Int, reason: String) =
        apiService.requestRefund(id, RefundRequest(reason))
}

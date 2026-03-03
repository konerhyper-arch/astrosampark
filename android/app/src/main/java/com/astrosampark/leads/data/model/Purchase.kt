package com.astrosampark.leads.data.model

import com.google.gson.annotations.SerializedName

data class StatusRequest(
    val status: String
)

data class RateRequest(
    val rating: Int,
    val feedback: String? = null
)

data class RefundRequest(
    val reason: String
)

data class BuyLeadResponse(
    @SerializedName("purchase_id") val purchaseId: Int,
    val name: String,
    val phone: String,
    val email: String?,
    @SerializedName("new_balance") val newBalance: Double,
    val message: String
)

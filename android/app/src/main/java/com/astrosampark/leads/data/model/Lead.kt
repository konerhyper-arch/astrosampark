package com.astrosampark.leads.data.model

import com.google.gson.annotations.SerializedName

data class Lead(
    val id: Int,
    val category: String,
    val city: String,
    val state: String?,
    val language: String?,
    @SerializedName("quality_score") val qualityScore: Int,
    @SerializedName("quality_badge") val qualityBadge: String,
    val price: String,
    @SerializedName("time_ago") val timeAgo: String,
    @SerializedName("masked_name") val maskedName: String,
    @SerializedName("masked_phone") val maskedPhone: String?,
    val notes: String?,
    @SerializedName("listing_id") val listingId: Int,
    val name: String? = null,
    val phone: String? = null,
    val email: String? = null,
    @SerializedName("is_purchased") val isPurchased: Boolean = false
)

data class LeadPreview(
    val id: Int,
    val category: String,
    val city: String,
    @SerializedName("notes_summary") val notesSummary: String,
    @SerializedName("quality_score") val qualityScore: Int,
    @SerializedName("quality_badge") val qualityBadge: String,
    val price: Double,
    @SerializedName("listing_id") val listingId: Int,
    @SerializedName("wallet_balance") val walletBalance: Double,
    @SerializedName("masked_name") val maskedName: String
)

data class PurchasedLead(
    @SerializedName("purchase_id") val purchaseId: Int,
    @SerializedName("lead_id") val leadId: Int,
    val name: String,
    val phone: String,
    val email: String?,
    val category: String,
    val city: String,
    @SerializedName("lead_state") val leadState: String,
    @SerializedName("refund_status") val refundStatus: String,
    @SerializedName("purchased_at") val purchasedAt: String,
    @SerializedName("quality_score") val qualityScore: Int,
    @SerializedName("quality_badge") val qualityBadge: String
)

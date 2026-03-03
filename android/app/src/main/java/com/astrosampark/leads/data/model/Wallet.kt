package com.astrosampark.leads.data.model

import com.google.gson.annotations.SerializedName

data class WalletBalance(
    val balance: Double,
    val currency: String = "INR"
)

data class Transaction(
    val id: Int,
    val type: String,          // credit / debit
    val description: String,
    val amount: Double,
    val currency: String,
    val status: String,
    @SerializedName("created_at") val createdAt: String
)

data class RechargeRequest(
    val amount: Int
)

data class RechargeOrderResponse(
    @SerializedName("order_id") val orderId: String,
    val amount: Int,
    val currency: String,
    @SerializedName("key") val razorpayKeyId: String
)

data class VerifyRechargeRequest(
    @SerializedName("razorpay_order_id") val orderId: String,
    @SerializedName("razorpay_payment_id") val paymentId: String,
    @SerializedName("razorpay_signature") val signature: String
)

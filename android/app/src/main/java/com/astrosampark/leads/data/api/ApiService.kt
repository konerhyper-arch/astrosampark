package com.astrosampark.leads.data.api

import com.astrosampark.leads.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    // Auth
    @POST("auth/login")
    suspend fun login(@Body body: LoginRequest): Response<ApiResponse<AuthResponse>>

    @POST("auth/register")
    suspend fun register(@Body body: RegisterRequest): Response<ApiResponse<AuthResponse>>

    // Marketplace
    @GET("astrologer/marketplace")
    suspend fun getMarketplace(@QueryMap filters: Map<String, String>): Response<PaginatedResponse<Lead>>

    @GET("astrologer/leads/{id}/preview")
    suspend fun getLeadPreview(@Path("id") id: Int): Response<ApiResponse<LeadPreview>>

    @POST("astrologer/leads/{id}/buy")
    suspend fun buyLead(@Path("id") id: Int): Response<ApiResponse<BuyLeadResponse>>

    // My Leads
    @GET("astrologer/my-leads")
    suspend fun getMyLeads(): Response<PaginatedResponse<PurchasedLead>>

    @PATCH("astrologer/my-leads/{id}/status")
    suspend fun updateLeadStatus(
        @Path("id") id: Int,
        @Body body: StatusRequest
    ): Response<ApiResponse<Unit>>

    @POST("astrologer/my-leads/{id}/rate")
    suspend fun rateLead(
        @Path("id") id: Int,
        @Body body: RateRequest
    ): Response<ApiResponse<Unit>>

    @POST("astrologer/my-leads/{id}/refund")
    suspend fun requestRefund(
        @Path("id") id: Int,
        @Body body: RefundRequest
    ): Response<ApiResponse<Unit>>

    // Wallet
    @GET("astrologer/wallet/balance")
    suspend fun getWalletBalance(): Response<ApiResponse<WalletBalance>>

    @GET("astrologer/wallet/transactions")
    suspend fun getTransactions(): Response<PaginatedResponse<Transaction>>

    @POST("astrologer/wallet/recharge")
    suspend fun createRechargeOrder(@Body body: RechargeRequest): Response<ApiResponse<RechargeOrderResponse>>

    @POST("astrologer/wallet/verify-recharge")
    suspend fun verifyRecharge(@Body body: VerifyRechargeRequest): Response<ApiResponse<WalletBalance>>
}

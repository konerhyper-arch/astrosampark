package com.astrosampark.leads.data.model

data class ApiResponse<T>(
    val success: Boolean,
    val message: String?,
    val data: T?,
    val errors: Map<String, List<String>>? = null
)

data class PaginatedResponse<T>(
    val success: Boolean,
    val data: List<T>,
    val meta: PaginationMeta?
)

data class PaginationMeta(
    val current_page: Int,
    val last_page: Int,
    val per_page: Int,
    val total: Int
)

package com.astrosampark.leads.data.model

import com.google.gson.annotations.SerializedName

data class User(
    val id: Int,
    val name: String,
    val email: String?,
    val phone: String?,
    val role: String
)

data class LoginRequest(
    val login: String,
    val password: String
)

data class RegisterRequest(
    val name: String,
    val email: String?,
    val phone: String,
    val password: String
)

data class AuthResponse(
    val token: String,
    val user: User,
    val message: String
)

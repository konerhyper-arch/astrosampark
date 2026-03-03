package com.astrosampark.leads.data.api

import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor : Interceptor {

    var token: String? = null

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()
        val currentToken = token

        val request = if (currentToken != null) {
            originalRequest.newBuilder()
                .header("Authorization", "Bearer $currentToken")
                .header("Accept", "application/json")
                .build()
        } else {
            originalRequest.newBuilder()
                .header("Accept", "application/json")
                .build()
        }

        return chain.proceed(request)
    }
}

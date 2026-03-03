package com.astrosampark.leads.ui.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.astrosampark.leads.data.api.ApiClient
import com.astrosampark.leads.data.model.AuthResponse
import com.astrosampark.leads.data.model.LoginRequest
import com.astrosampark.leads.data.model.RegisterRequest
import kotlinx.coroutines.launch

sealed class AuthState {
    object Idle : AuthState()
    object Loading : AuthState()
    data class Success(val data: AuthResponse) : AuthState()
    data class Error(val message: String) : AuthState()
}

class LoginViewModel : ViewModel() {

    private val _authState = MutableLiveData<AuthState>(AuthState.Idle)
    val authState: LiveData<AuthState> = _authState

    fun login(login: String, password: String) {
        if (login.isBlank() || password.isBlank()) {
            _authState.value = AuthState.Error("Please fill all fields")
            return
        }
        viewModelScope.launch {
            _authState.value = AuthState.Loading
            try {
                val response = ApiClient.apiService.login(LoginRequest(login, password))
                if (response.isSuccessful && response.body()?.success == true) {
                    val authData = response.body()!!.data!!
                    _authState.value = AuthState.Success(authData)
                } else {
                    val errorMsg = response.body()?.message ?: "Login failed"
                    _authState.value = AuthState.Error(errorMsg)
                }
            } catch (e: Exception) {
                _authState.value = AuthState.Error(e.message ?: "Network error")
            }
        }
    }

    fun register(name: String, email: String?, phone: String, password: String) {
        if (name.isBlank() || phone.isBlank() || password.isBlank()) {
            _authState.value = AuthState.Error("Please fill all required fields")
            return
        }
        viewModelScope.launch {
            _authState.value = AuthState.Loading
            try {
                val response = ApiClient.apiService.register(
                    RegisterRequest(name, email?.takeIf { it.isNotBlank() }, phone, password)
                )
                if (response.isSuccessful && response.body()?.success == true) {
                    val authData = response.body()!!.data!!
                    _authState.value = AuthState.Success(authData)
                } else {
                    val errorMsg = response.body()?.message ?: "Registration failed"
                    _authState.value = AuthState.Error(errorMsg)
                }
            } catch (e: Exception) {
                _authState.value = AuthState.Error(e.message ?: "Network error")
            }
        }
    }

    fun resetState() {
        _authState.value = AuthState.Idle
    }
}

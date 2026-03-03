package com.astrosampark.leads.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.astrosampark.leads.MainActivity
import com.astrosampark.leads.data.api.ApiClient
import com.astrosampark.leads.databinding.ActivityLoginBinding
import com.google.android.material.snackbar.Snackbar
import com.google.android.material.tabs.TabLayout

class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private val viewModel: LoginViewModel by viewModels()
    private var isLoginMode = true

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // If already logged in, go to MainActivity
        val prefs = getSharedPreferences("astrosampark", MODE_PRIVATE)
        val token = prefs.getString("token", null)
        if (token != null) {
            ApiClient.authInterceptor.token = token
            startActivity(Intent(this, MainActivity::class.java))
            finish()
            return
        }

        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupTabs()
        setupObservers()
        setupClickListeners()
    }

    private fun setupTabs() {
        binding.tabLayout.addOnTabSelectedListener(object : TabLayout.OnTabSelectedListener {
            override fun onTabSelected(tab: TabLayout.Tab?) {
                isLoginMode = tab?.position == 0
                binding.layoutRegisterExtra.visibility = if (isLoginMode) View.GONE else View.VISIBLE
                binding.btnAuth.text = if (isLoginMode) "Login" else "Register"
            }
            override fun onTabUnselected(tab: TabLayout.Tab?) {}
            override fun onTabReselected(tab: TabLayout.Tab?) {}
        })
    }

    private fun setupObservers() {
        viewModel.authState.observe(this) { state ->
            when (state) {
                is AuthState.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                    binding.btnAuth.isEnabled = false
                }
                is AuthState.Success -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnAuth.isEnabled = true
                    saveTokenAndNavigate(state.data.token)
                }
                is AuthState.Error -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnAuth.isEnabled = true
                    Snackbar.make(binding.root, state.message, Snackbar.LENGTH_LONG).show()
                }
                else -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnAuth.isEnabled = true
                }
            }
        }
    }

    private fun setupClickListeners() {
        binding.btnAuth.setOnClickListener {
            val login = binding.etLogin.text.toString()
            val password = binding.etPassword.text.toString()

            if (isLoginMode) {
                viewModel.login(login, password)
            } else {
                val name = binding.etName.text.toString()
                val email = binding.etEmail.text.toString()
                viewModel.register(name, email, login, password)
            }
        }
    }

    private fun saveTokenAndNavigate(token: String) {
        getSharedPreferences("astrosampark", MODE_PRIVATE)
            .edit()
            .putString("token", token)
            .apply()
        ApiClient.authInterceptor.token = token
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}

package com.astrosampark.leads

import android.content.Intent
import android.os.Bundle
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupWithNavController
import com.astrosampark.leads.data.api.ApiClient
import com.astrosampark.leads.databinding.ActivityMainBinding
import com.astrosampark.leads.ui.auth.LoginActivity
import com.astrosampark.leads.ui.main.MainViewModel

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private val viewModel: MainViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Verify token is set
        val prefs = getSharedPreferences("astrosampark", MODE_PRIVATE)
        val token = prefs.getString("token", null)
        if (token == null) {
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
            return
        }
        ApiClient.authInterceptor.token = token

        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController
        binding.bottomNavigation.setupWithNavController(navController)

        viewModel.refreshWalletBalance()
    }

    fun logout() {
        getSharedPreferences("astrosampark", MODE_PRIVATE).edit().clear().apply()
        ApiClient.authInterceptor.token = null
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}

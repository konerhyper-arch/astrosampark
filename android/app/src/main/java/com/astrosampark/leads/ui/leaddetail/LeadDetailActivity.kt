package com.astrosampark.leads.ui.leaddetail

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.astrosampark.leads.R
import com.astrosampark.leads.data.model.BuyLeadResponse
import com.astrosampark.leads.util.PhoneUtils
import com.astrosampark.leads.data.model.LeadPreview
import com.astrosampark.leads.databinding.ActivityLeadDetailBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.snackbar.Snackbar

class LeadDetailActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_LEAD_ID = "lead_id"
        const val EXTRA_LEAD_PRICE = "lead_price"
        const val EXTRA_LISTING_ID = "listing_id"
    }

    private lateinit var binding: ActivityLeadDetailBinding
    private val viewModel: LeadDetailViewModel by viewModels()
    private var leadId: Int = 0
    private var purchaseId: Int = -1
    private var purchasedPhone: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLeadDetailBinding.inflate(layoutInflater)
        setContentView(binding.root)

        leadId = intent.getIntExtra(EXTRA_LEAD_ID, 0)

        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        binding.toolbar.setNavigationOnClickListener { onBackPressedDispatcher.onBackPressed() }

        observeViewModel()
        viewModel.loadPreview(leadId)
    }

    private fun observeViewModel() {
        viewModel.state.observe(this) { state ->
            when (state) {
                is LeadDetailState.Loading -> showLoading()
                is LeadDetailState.PreviewLoaded -> showPreview(state.preview)
                is LeadDetailState.Purchased -> showPurchased(state.data)
                is LeadDetailState.Error -> showError(state.message)
            }
        }

        viewModel.actionState.observe(this) { state ->
            when (state) {
                is ActionState.Loading -> binding.progressAction.visibility = View.VISIBLE
                is ActionState.Success -> {
                    binding.progressAction.visibility = View.GONE
                    Snackbar.make(binding.root, state.message, Snackbar.LENGTH_SHORT).show()
                    viewModel.resetActionState()
                }
                is ActionState.Error -> {
                    binding.progressAction.visibility = View.GONE
                    Snackbar.make(binding.root, state.message, Snackbar.LENGTH_LONG).show()
                    viewModel.resetActionState()
                }
                else -> binding.progressAction.visibility = View.GONE
            }
        }
    }

    private fun showLoading() {
        binding.progressBar.visibility = View.VISIBLE
        binding.layoutContent.visibility = View.GONE
    }

    private fun showPreview(preview: LeadPreview) {
        binding.progressBar.visibility = View.GONE
        binding.layoutContent.visibility = View.VISIBLE
        binding.layoutPurchased.visibility = View.GONE
        binding.layoutPreview.visibility = View.VISIBLE

        binding.tvCategory.text = preview.category
        binding.tvCity.text = preview.city
        binding.tvMaskedName.text = preview.maskedName
        binding.tvQualityScore.text = "Quality: ${preview.qualityScore}/100"
        binding.tvNotesSummary.text = preview.notesSummary
        binding.tvWalletBalance.text = "Wallet Balance: ₹${preview.walletBalance}"

        val price = preview.price
        binding.btnBuyNow.text = "Buy Now ₹$price"
        setBadgeStyle(preview.qualityBadge)

        binding.btnBuyNow.setOnClickListener {
            if (preview.walletBalance >= price) {
                showBuyConfirmationDialog(leadId, price, preview.walletBalance)
            } else {
                Snackbar.make(
                    binding.root,
                    "Insufficient balance. Please recharge your wallet.",
                    Snackbar.LENGTH_LONG
                ).setAction("Recharge") {
                    // Navigate to wallet
                    finish()
                }.show()
            }
        }
    }

    private fun showBuyConfirmationDialog(leadId: Int, price: Double, balance: Double) {
        MaterialAlertDialogBuilder(this)
            .setTitle("Confirm Purchase")
            .setMessage("Buy this lead for ₹$price from your wallet?\n\nCurrent Balance: ₹$balance")
            .setPositiveButton("Buy Now") { _, _ -> viewModel.buyLead(leadId) }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun showPurchased(data: BuyLeadResponse) {
        binding.progressBar.visibility = View.GONE
        binding.layoutContent.visibility = View.VISIBLE
        binding.layoutPreview.visibility = View.GONE
        binding.layoutPurchased.visibility = View.VISIBLE

        purchaseId = data.purchaseId
        purchasedPhone = data.phone

        binding.tvRevealedName.text = data.name
        binding.tvRevealedPhone.text = data.phone
        binding.tvRevealedEmail.text = data.email ?: "Not provided"
        binding.tvNewBalance.text = "New Balance: ₹${data.newBalance}"

        binding.btnCall.setOnClickListener {
            val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${data.phone}"))
            startActivity(intent)
        }

        binding.btnWhatsApp.setOnClickListener {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(PhoneUtils.whatsAppUrl(data.phone))))
        }

        setupStatusChips()
        setupRating()
        setupRefund()
    }

    private fun setupStatusChips() {
        val statuses = listOf("New", "Contacted", "Converted", "Not Interested")
        binding.chipGroupStatus.removeAllViews()
        statuses.forEach { status ->
            val chip = com.google.android.material.chip.Chip(this).apply {
                text = status
                isCheckable = true
                isChecked = status == "New"
            }
            chip.setOnCheckedChangeListener { _, checked ->
                if (checked && purchaseId != -1) {
                    viewModel.updateStatus(purchaseId, status)
                }
            }
            binding.chipGroupStatus.addView(chip)
        }
    }

    private fun setupRating() {
        binding.ratingBar.setOnRatingBarChangeListener { _, rating, fromUser ->
            if (fromUser && purchaseId != -1) {
                viewModel.rateLead(purchaseId, rating.toInt())
            }
        }
    }

    private fun setupRefund() {
        binding.btnRefund.setOnClickListener {
            if (purchaseId == -1) return@setOnClickListener
            MaterialAlertDialogBuilder(this)
                .setTitle("Request Refund")
                .setMessage("Are you sure you want to request a refund for this lead?")
                .setPositiveButton("Yes, Refund") { _, _ ->
                    viewModel.requestRefund(purchaseId, "Not satisfied with lead quality")
                }
                .setNegativeButton("Cancel", null)
                .show()
        }
    }

    private fun setBadgeStyle(badge: String) {
        binding.chipBadge.text = badge
        when (badge.lowercase()) {
            "premium" -> {
                binding.chipBadge.chipBackgroundColor =
                    ContextCompat.getColorStateList(this, R.color.badge_premium_bg)
                binding.chipBadge.setTextColor(
                    ContextCompat.getColor(this, R.color.badge_premium_text)
                )
            }
            "verified" -> {
                binding.chipBadge.chipBackgroundColor =
                    ContextCompat.getColorStateList(this, R.color.badge_verified_bg)
                binding.chipBadge.setTextColor(
                    ContextCompat.getColor(this, R.color.badge_verified_text)
                )
            }
            else -> {
                binding.chipBadge.chipBackgroundColor =
                    ContextCompat.getColorStateList(this, R.color.badge_basic_bg)
                binding.chipBadge.setTextColor(
                    ContextCompat.getColor(this, R.color.badge_basic_text)
                )
            }
        }
    }

    private fun showError(message: String) {
        binding.progressBar.visibility = View.GONE
        Snackbar.make(binding.root, message, Snackbar.LENGTH_LONG).show()
    }
}

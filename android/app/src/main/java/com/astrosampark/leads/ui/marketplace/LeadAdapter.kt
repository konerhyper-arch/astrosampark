package com.astrosampark.leads.ui.marketplace

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.astrosampark.leads.R
import com.astrosampark.leads.data.model.Lead
import com.astrosampark.leads.databinding.ItemLeadCardBinding

class LeadAdapter(
    private val onLeadClick: (Lead) -> Unit
) : ListAdapter<Lead, LeadAdapter.LeadViewHolder>(LeadDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): LeadViewHolder {
        val binding = ItemLeadCardBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return LeadViewHolder(binding)
    }

    override fun onBindViewHolder(holder: LeadViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class LeadViewHolder(
        private val binding: ItemLeadCardBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(lead: Lead) {
            binding.apply {
                tvMaskedName.text = lead.maskedName
                tvCityCategory.text = "${lead.city} • ${lead.category}"
                tvPrice.text = "₹${lead.price}"
                tvTimeAgo.text = lead.timeAgo
                tvQualityScore.text = "${lead.qualityScore}"
                progressQuality.progress = lead.qualityScore

                // Set badge appearance
                chipBadge.text = lead.qualityBadge
                when (lead.qualityBadge.lowercase()) {
                    "premium" -> {
                        chipBadge.chipBackgroundColor =
                            ContextCompat.getColorStateList(root.context, R.color.badge_premium_bg)
                        chipBadge.setTextColor(
                            ContextCompat.getColor(root.context, R.color.badge_premium_text)
                        )
                    }
                    "verified" -> {
                        chipBadge.chipBackgroundColor =
                            ContextCompat.getColorStateList(root.context, R.color.badge_verified_bg)
                        chipBadge.setTextColor(
                            ContextCompat.getColor(root.context, R.color.badge_verified_text)
                        )
                    }
                    else -> {
                        chipBadge.chipBackgroundColor =
                            ContextCompat.getColorStateList(root.context, R.color.badge_basic_bg)
                        chipBadge.setTextColor(
                            ContextCompat.getColor(root.context, R.color.badge_basic_text)
                        )
                    }
                }

                if (!lead.notes.isNullOrBlank()) {
                    tvNotes.text = lead.notes.take(80) + if (lead.notes.length > 80) "..." else ""
                    tvNotes.visibility = android.view.View.VISIBLE
                } else {
                    tvNotes.visibility = android.view.View.GONE
                }

                btnViewDetails.setOnClickListener { onLeadClick(lead) }
                root.setOnClickListener { onLeadClick(lead) }
            }
        }
    }

    class LeadDiffCallback : DiffUtil.ItemCallback<Lead>() {
        override fun areItemsTheSame(oldItem: Lead, newItem: Lead) = oldItem.id == newItem.id
        override fun areContentsTheSame(oldItem: Lead, newItem: Lead) = oldItem == newItem
    }
}

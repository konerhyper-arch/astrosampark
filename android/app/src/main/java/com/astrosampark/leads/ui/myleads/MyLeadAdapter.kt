package com.astrosampark.leads.ui.myleads

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.astrosampark.leads.R
import com.astrosampark.leads.data.model.PurchasedLead
import com.astrosampark.leads.databinding.ItemMyLeadBinding

class MyLeadAdapter(
    private val onLeadClick: (PurchasedLead) -> Unit
) : ListAdapter<PurchasedLead, MyLeadAdapter.MyLeadViewHolder>(MyLeadDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MyLeadViewHolder {
        val binding = ItemMyLeadBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return MyLeadViewHolder(binding)
    }

    override fun onBindViewHolder(holder: MyLeadViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class MyLeadViewHolder(
        private val binding: ItemMyLeadBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(lead: PurchasedLead) {
            binding.apply {
                tvName.text = lead.name
                tvPhone.text = lead.phone
                tvCityCategory.text = "${lead.city} • ${lead.category}"
                tvStatus.text = lead.leadState
                tvRefundStatus.text = if (lead.refundStatus != "none") "Refund: ${lead.refundStatus}" else ""
                tvPurchasedAt.text = lead.purchasedAt.take(10)

                // Status color
                when (lead.leadState.lowercase()) {
                    "converted" -> tvStatus.setTextColor(
                        ContextCompat.getColor(root.context, R.color.status_converted)
                    )
                    "contacted" -> tvStatus.setTextColor(
                        ContextCompat.getColor(root.context, R.color.status_contacted)
                    )
                    "not interested" -> tvStatus.setTextColor(
                        ContextCompat.getColor(root.context, R.color.status_not_interested)
                    )
                    else -> tvStatus.setTextColor(
                        ContextCompat.getColor(root.context, R.color.status_new)
                    )
                }

                // Badge
                chipBadge.text = lead.qualityBadge
                when (lead.qualityBadge.lowercase()) {
                    "premium" -> chipBadge.chipBackgroundColor =
                        ContextCompat.getColorStateList(root.context, R.color.badge_premium_bg)
                    "verified" -> chipBadge.chipBackgroundColor =
                        ContextCompat.getColorStateList(root.context, R.color.badge_verified_bg)
                    else -> chipBadge.chipBackgroundColor =
                        ContextCompat.getColorStateList(root.context, R.color.badge_basic_bg)
                }

                root.setOnClickListener { onLeadClick(lead) }
            }
        }
    }

    class MyLeadDiffCallback : DiffUtil.ItemCallback<PurchasedLead>() {
        override fun areItemsTheSame(oldItem: PurchasedLead, newItem: PurchasedLead) =
            oldItem.purchaseId == newItem.purchaseId
        override fun areContentsTheSame(oldItem: PurchasedLead, newItem: PurchasedLead) =
            oldItem == newItem
    }
}

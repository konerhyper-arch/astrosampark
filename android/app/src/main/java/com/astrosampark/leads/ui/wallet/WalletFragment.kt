package com.astrosampark.leads.ui.wallet

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.astrosampark.leads.R
import com.astrosampark.leads.data.model.RechargeOrderResponse
import com.astrosampark.leads.data.model.Transaction
import com.astrosampark.leads.databinding.FragmentWalletBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.snackbar.Snackbar
import com.razorpay.Checkout
import com.razorpay.PaymentResultListener
import org.json.JSONObject

class WalletFragment : Fragment(), PaymentResultListener {

    private var _binding: FragmentWalletBinding? = null
    private val binding get() = _binding!!
    private val viewModel: WalletViewModel by viewModels()
    private lateinit var transactionAdapter: TransactionAdapter

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?
    ): View {
        _binding = FragmentWalletBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Checkout.preload(requireContext())
        setupRecyclerView()
        setupClickListeners()
        observeViewModel()
        viewModel.loadWalletData()
    }

    private fun setupRecyclerView() {
        transactionAdapter = TransactionAdapter()
        binding.recyclerTransactions.adapter = transactionAdapter
        binding.recyclerTransactions.layoutManager = LinearLayoutManager(requireContext())
    }

    private fun setupClickListeners() {
        binding.btnAddMoney.setOnClickListener { showRechargeDialog() }
        binding.swipeRefresh.setOnRefreshListener { viewModel.loadWalletData() }
    }

    private fun showRechargeDialog() {
        val amounts = arrayOf("₹100", "₹500", "₹1000", "₹2000", "Custom")
        var selectedAmount = 500

        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Add Money to Wallet")
            .setSingleChoiceItems(amounts, 1) { _, which ->
                selectedAmount = when (which) {
                    0 -> 100
                    1 -> 500
                    2 -> 1000
                    3 -> 2000
                    else -> selectedAmount
                }
            }
            .setPositiveButton("Proceed") { _, _ ->
                viewModel.createRechargeOrder(selectedAmount)
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun observeViewModel() {
        viewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.swipeRefresh.isRefreshing = loading
        }

        viewModel.balance.observe(viewLifecycleOwner) { balance ->
            balance?.let {
                binding.tvBalance.text = "₹${String.format("%.2f", it.balance)}"
            }
        }

        viewModel.transactions.observe(viewLifecycleOwner) { transactions ->
            transactionAdapter.submitList(transactions)
            binding.tvNoTransactions.visibility =
                if (transactions.isEmpty()) View.VISIBLE else View.GONE
        }

        viewModel.rechargeOrder.observe(viewLifecycleOwner) { order ->
            order?.let {
                startRazorpayPayment(it)
                viewModel.clearRechargeOrder()
            }
        }

        viewModel.error.observe(viewLifecycleOwner) { error ->
            error?.let {
                Snackbar.make(binding.root, it, Snackbar.LENGTH_LONG).show()
                viewModel.clearError()
            }
        }

        viewModel.successMessage.observe(viewLifecycleOwner) { msg ->
            msg?.let {
                Snackbar.make(binding.root, it, Snackbar.LENGTH_SHORT).show()
                viewModel.clearSuccess()
            }
        }
    }

    private fun startRazorpayPayment(order: RechargeOrderResponse) {
        val checkout = Checkout()
        checkout.setKeyID(order.razorpayKeyId)

        val options = JSONObject().apply {
            put("name", "AstroSampark")
            put("description", "Wallet Recharge")
            put("order_id", order.orderId)
            put("currency", order.currency)
            put("amount", order.amount)
            val prefill = JSONObject().apply {
                put("contact", "")
                put("email", "")
            }
            put("prefill", prefill)
        }

        try {
            checkout.open(requireActivity(), options)
        } catch (e: Exception) {
            Snackbar.make(binding.root, "Payment error: ${e.message}", Snackbar.LENGTH_LONG).show()
        }
    }

    override fun onPaymentSuccess(razorpayPaymentId: String?, data: com.razorpay.PaymentData?) {
        val orderId = data?.getOrderId() ?: ""
        val signature = data?.getSignature() ?: ""
        if (razorpayPaymentId != null) {
            viewModel.verifyRecharge(orderId, razorpayPaymentId, signature)
        }
    }

    override fun onPaymentError(errorCode: Int, errorDescription: String?, data: com.razorpay.PaymentData?) {
        Snackbar.make(binding.root, "Payment failed: $errorDescription", Snackbar.LENGTH_LONG).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}

class TransactionAdapter :
    androidx.recyclerview.widget.ListAdapter<Transaction,
            TransactionAdapter.TransactionViewHolder>(TransactionDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): TransactionViewHolder {
        val binding = com.astrosampark.leads.databinding.ItemTransactionBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return TransactionViewHolder(binding)
    }

    override fun onBindViewHolder(holder: TransactionViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class TransactionViewHolder(
        private val binding: com.astrosampark.leads.databinding.ItemTransactionBinding
    ) : androidx.recyclerview.widget.RecyclerView.ViewHolder(binding.root) {

        fun bind(tx: Transaction) {
            binding.tvDescription.text = tx.description
            binding.tvDate.text = tx.createdAt.take(10)

            val isCredit = tx.type.lowercase() == "credit"
            val sign = if (isCredit) "+" else "-"
            binding.tvAmount.text = "$sign₹${String.format("%.2f", tx.amount)}"
            binding.tvAmount.setTextColor(
                androidx.core.content.ContextCompat.getColor(
                    binding.root.context,
                    if (isCredit) R.color.amount_credit else R.color.amount_debit
                )
            )

            binding.ivType.setImageResource(
                if (isCredit) R.drawable.ic_credit else R.drawable.ic_debit
            )
        }
    }

    class TransactionDiffCallback : androidx.recyclerview.widget.DiffUtil.ItemCallback<Transaction>() {
        override fun areItemsTheSame(oldItem: Transaction, newItem: Transaction) = oldItem.id == newItem.id
        override fun areContentsTheSame(oldItem: Transaction, newItem: Transaction) = oldItem == newItem
    }
}

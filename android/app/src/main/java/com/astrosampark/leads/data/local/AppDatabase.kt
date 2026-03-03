package com.astrosampark.leads.data.local

import androidx.room.*
import com.astrosampark.leads.data.model.Lead
import com.astrosampark.leads.data.model.PurchasedLead

@Dao
interface LeadDao {
    @Query("SELECT * FROM leads ORDER BY id DESC")
    suspend fun getAllLeads(): List<LeadEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertLeads(leads: List<LeadEntity>)

    @Query("DELETE FROM leads")
    suspend fun clearLeads()
}

@Dao
interface PurchasedLeadDao {
    @Query("SELECT * FROM purchased_leads ORDER BY purchasedAt DESC")
    suspend fun getAllPurchasedLeads(): List<PurchasedLeadEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPurchasedLeads(leads: List<PurchasedLeadEntity>)

    @Query("DELETE FROM purchased_leads")
    suspend fun clearPurchasedLeads()
}

@Entity(tableName = "leads")
data class LeadEntity(
    @PrimaryKey val id: Int,
    val category: String,
    val city: String,
    val state: String?,
    val language: String?,
    val qualityScore: Int,
    val qualityBadge: String,
    val price: String,
    val timeAgo: String,
    val maskedName: String,
    val maskedPhone: String?,
    val notes: String?,
    val listingId: Int
)

@Entity(tableName = "purchased_leads")
data class PurchasedLeadEntity(
    @PrimaryKey val purchaseId: Int,
    val leadId: Int,
    val name: String,
    val phone: String,
    val email: String?,
    val category: String,
    val city: String,
    val leadState: String,
    val refundStatus: String,
    val purchasedAt: String,
    val qualityScore: Int,
    val qualityBadge: String
)

@Database(
    entities = [LeadEntity::class, PurchasedLeadEntity::class],
    version = 1,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun leadDao(): LeadDao
    abstract fun purchasedLeadDao(): PurchasedLeadDao
}

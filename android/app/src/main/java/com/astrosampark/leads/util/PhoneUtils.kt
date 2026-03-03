package com.astrosampark.leads.util

object PhoneUtils {
    /** Strips all non-digit characters from a phone string for use with dial/WhatsApp intents. */
    fun cleanPhone(phone: String): String = phone.replace(Regex("[^0-9]"), "")

    /** Builds a wa.me URL for the given Indian phone number. */
    fun whatsAppUrl(phone: String): String = "https://wa.me/91${cleanPhone(phone)}"
}

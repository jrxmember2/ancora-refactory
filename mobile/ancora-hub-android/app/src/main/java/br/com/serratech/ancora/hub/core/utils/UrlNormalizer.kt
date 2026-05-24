package br.com.serratech.ancora.hub.core.utils

import java.net.URI

class UrlNormalizer {
    fun normalize(input: String): String? {
        val trimmed = input.trim().replace(Regex("/+$"), "")
        if (trimmed.isBlank()) {
            return null
        }

        val withScheme = if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
            trimmed
        } else {
            "https://$trimmed"
        }

        return runCatching {
            val uri = URI(withScheme)
            val scheme = uri.scheme?.lowercase().orEmpty()
            if (scheme !in listOf("http", "https") || uri.host.isNullOrBlank()) {
                null
            } else {
                uri.toString().replace(Regex("/+$"), "")
            }
        }.getOrNull()
    }
}

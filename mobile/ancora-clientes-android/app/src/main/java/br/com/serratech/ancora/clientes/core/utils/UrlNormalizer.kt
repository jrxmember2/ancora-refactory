package br.com.serratech.ancora.clientes.core.utils

import java.net.URI

class UrlNormalizer {
    fun normalize(input: String): String? {
        val trimmed = input.trim().removeSuffix("/")
        if (trimmed.isBlank()) return null

        val withScheme = if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
            trimmed
        } else {
            "https://$trimmed"
        }

        return runCatching {
            val uri = URI(withScheme)
            if (uri.host.isNullOrBlank()) {
                null
            } else {
                uri.toString().replace(Regex("/+$"), "")
            }
        }.getOrNull()
    }
}

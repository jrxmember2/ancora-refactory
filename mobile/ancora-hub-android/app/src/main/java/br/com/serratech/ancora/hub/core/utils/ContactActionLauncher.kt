package br.com.serratech.ancora.hub.core.utils

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri

object ContactActionLauncher {
    fun dial(context: Context, phone: String): Result<Unit> = runCatching {
        context.startActivity(
            Intent(Intent.ACTION_DIAL, Uri.parse("tel:${phone.filter { it.isDigit() || it == '+' }}")).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            },
        )
    }.recoverCatching { throwable ->
        throw when (throwable) {
            is ActivityNotFoundException -> IllegalStateException("Não foi possível iniciar a ligação neste aparelho.")
            else -> throwable
        }
    }

    fun email(context: Context, email: String): Result<Unit> = runCatching {
        context.startActivity(
            Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:${email.trim()}")).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            },
        )
    }.recoverCatching { throwable ->
        throw when (throwable) {
            is ActivityNotFoundException -> IllegalStateException("Não foi possível abrir um aplicativo de e-mail neste aparelho.")
            else -> throwable
        }
    }

    fun whatsapp(context: Context, phone: String): Result<Unit> = runCatching {
        val digits = phone.filter { it.isDigit() }
        val uri = Uri.parse("https://wa.me/$digits")
        val intent = Intent(Intent.ACTION_VIEW, uri).apply {
            setPackage("com.whatsapp")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }

        try {
            context.startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            context.startActivity(
                intent.setPackage("com.whatsapp.w4b"),
            )
        }
    }.recoverCatching { throwable ->
        throw when (throwable) {
            is ActivityNotFoundException -> IllegalStateException("O WhatsApp não está disponível neste aparelho.")
            else -> throwable
        }
    }
}

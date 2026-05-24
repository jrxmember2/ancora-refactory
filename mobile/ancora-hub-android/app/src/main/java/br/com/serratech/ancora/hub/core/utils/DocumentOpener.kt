package br.com.serratech.ancora.hub.core.utils

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument

object DocumentOpener {
    fun open(context: Context, document: DownloadedDocument): Result<Unit> = runCatching {
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            document.file,
        )

        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, document.mimeType)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }

        context.startActivity(
            Intent.createChooser(intent, "Abrir documento").apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            },
        )
    }.recoverCatching { throwable ->
        throw when (throwable) {
            is ActivityNotFoundException -> IllegalStateException("Nenhum aplicativo compatível foi encontrado para abrir este documento.")
            else -> throwable
        }
    }
}

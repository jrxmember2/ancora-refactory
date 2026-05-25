package br.com.serratech.ancora.hub.core.push

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import br.com.serratech.ancora.hub.AncoraHubApplication
import br.com.serratech.ancora.hub.MainActivity
import br.com.serratech.ancora.hub.R
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await

class PushNotifier(
    private val context: Context,
) {
    fun registerCurrentDevice() {
        val app = context.applicationContext as? AncoraHubApplication ?: return
        if (app.container.secureTokenStore.currentToken().isNullOrBlank()) {
            return
        }

        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                val token = FirebaseMessaging.getInstance().token.await()
                registerTokenIfPossible(token, app)
            }
        }
    }

    fun registerTokenIfPossible(
        token: String,
        app: AncoraHubApplication? = context.applicationContext as? AncoraHubApplication,
    ) {
        if (token.isBlank() || app == null || app.container.secureTokenStore.currentToken().isNullOrBlank()) {
            return
        }

        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                app.container.authRepository.registerDevice(token)
            }
        }
    }

    fun unregisterCurrentDevice() {
        val app = context.applicationContext as? AncoraHubApplication ?: return
        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                app.container.authRepository.unregisterCurrentDeviceIfNeeded()
            }
        }
    }

    fun showNotification(title: String, body: String, data: Map<String, String>) {
        if (
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val resolvedTitle = title.ifBlank { context.getString(R.string.notification_fallback_title) }
        val resolvedBody = body.ifBlank { context.getString(R.string.app_name) }
        val route = resolveRoute(data)
        val type = data["type"].orEmpty()
        val module = data["module"].orEmpty()
        val channelId = resolveChannelId(type = type, module = module)
        val notificationId = data["notification_id"]?.toIntOrNull() ?: System.currentTimeMillis().toInt()

        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("route", route)
            if (route.startsWith("hub://", ignoreCase = true)) {
                setData(Uri.parse(route))
            }
            data.forEach { (key, value) -> putExtra(key, value) }
        }

        val pendingIntent = PendingIntent.getActivity(
            context,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(R.drawable.ic_stat_notification)
            .setLargeIcon(BitmapFactory.decodeResource(context.resources, R.drawable.logo_ancora_hub))
            .setColor(ContextCompat.getColor(context, R.color.ancora_hub_primary))
            .setContentTitle(resolvedTitle)
            .setContentText(resolvedBody)
            .setStyle(NotificationCompat.BigTextStyle().bigText(resolvedBody))
            .setPriority(resolvePriority(type = type, module = module))
            .setCategory(resolveCategory(type = type, module = module))
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setDefaults(resolveDefaults(type = type, module = module))
            .build()

        NotificationManagerCompat.from(context).notify(notificationId, notification)
    }

    private fun resolveRoute(data: Map<String, String>): String = when {
        !data["route"].isNullOrBlank() -> data["route"].orEmpty()
        !data["screen"].isNullOrBlank() -> data["screen"].orEmpty()
        !data["module"].isNullOrBlank() -> data["module"].orEmpty()
        !data["notification_id"].isNullOrBlank() -> "hub://notifications/${data["notification_id"]}"
        else -> "hub://notifications"
    }

    companion object {
        private const val GENERAL_CHANNEL_ID = "ancora_hub_general"
        private const val DEMANDS_CHANNEL_ID = "ancora_hub_demands"
        private const val PROCESSES_CHANNEL_ID = "ancora_hub_processes"
        private const val COLLECTIONS_CHANNEL_ID = "ancora_hub_collections"
        private const val FINANCE_CHANNEL_ID = "ancora_hub_finance"
        private const val SIGNATURES_CHANNEL_ID = "ancora_hub_signatures"

        fun createChannels(context: Context) {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
                return
            }

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannels(
                listOf(
                    NotificationChannel(
                        GENERAL_CHANNEL_ID,
                        context.getString(R.string.notification_channel_general_name),
                        NotificationManager.IMPORTANCE_DEFAULT,
                    ).apply {
                        description = context.getString(R.string.notification_channel_general_description)
                    },
                    NotificationChannel(
                        DEMANDS_CHANNEL_ID,
                        context.getString(R.string.notification_channel_demands_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_demands_description)
                    },
                    NotificationChannel(
                        PROCESSES_CHANNEL_ID,
                        context.getString(R.string.notification_channel_processes_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_processes_description)
                    },
                    NotificationChannel(
                        COLLECTIONS_CHANNEL_ID,
                        context.getString(R.string.notification_channel_collections_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_collections_description)
                    },
                    NotificationChannel(
                        FINANCE_CHANNEL_ID,
                        context.getString(R.string.notification_channel_finance_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_finance_description)
                    },
                    NotificationChannel(
                        SIGNATURES_CHANNEL_ID,
                        context.getString(R.string.notification_channel_signatures_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_signatures_description)
                    },
                ),
            )
        }

        private fun resolveChannelId(type: String, module: String): String = when {
            module.equals("demandas", ignoreCase = true) ||
                type.equals("nova_demanda", ignoreCase = true) ||
                type.equals("resposta_demanda", ignoreCase = true) -> DEMANDS_CHANNEL_ID

            module.equals("processos", ignoreCase = true) ||
                type.equals("novo_andamento_processual", ignoreCase = true) ||
                type.equals("processo_atualizado", ignoreCase = true) -> PROCESSES_CHANNEL_ID

            module.equals("cobrancas", ignoreCase = true) ||
                type.equals("cobranca_apta_judicializacao", ignoreCase = true) ||
                type.equals("acordo_vencido", ignoreCase = true) -> COLLECTIONS_CHANNEL_ID

            module.equals("financeiro", ignoreCase = true) ||
                type.equals("conta_vencida", ignoreCase = true) -> FINANCE_CHANNEL_ID

            module.equals("assinador", ignoreCase = true) ||
                module.equals("contratos", ignoreCase = true) ||
                type.equals("assinatura_concluida", ignoreCase = true) ||
                type.equals("contrato_pendente", ignoreCase = true) -> SIGNATURES_CHANNEL_ID

            else -> GENERAL_CHANNEL_ID
        }

        private fun resolvePriority(type: String, module: String): Int = when (resolveChannelId(type, module)) {
            GENERAL_CHANNEL_ID -> NotificationCompat.PRIORITY_DEFAULT
            else -> NotificationCompat.PRIORITY_HIGH
        }

        private fun resolveCategory(type: String, module: String): String = when (resolveChannelId(type, module)) {
            FINANCE_CHANNEL_ID -> NotificationCompat.CATEGORY_REMINDER
            else -> NotificationCompat.CATEGORY_MESSAGE
        }

        private fun resolveDefaults(type: String, module: String): Int = when (resolveChannelId(type, module)) {
            GENERAL_CHANNEL_ID -> Notification.DEFAULT_LIGHTS
            else -> Notification.DEFAULT_ALL
        }
    }
}

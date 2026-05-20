package br.com.serratech.ancora.clientes.core.push

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import br.com.serratech.ancora.clientes.AncoraClientesApplication
import br.com.serratech.ancora.clientes.MainActivity
import br.com.serratech.ancora.clientes.R
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await

class PushNotifier(
    private val context: Context,
) {
    fun registerCurrentDevice() {
        val app = context.applicationContext as? AncoraClientesApplication ?: return
        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                val token = FirebaseMessaging.getInstance().token.await()
                registerTokenIfPossible(token, app)
            }
        }
    }

    fun registerTokenIfPossible(token: String, app: AncoraClientesApplication? = context.applicationContext as? AncoraClientesApplication) {
        if (token.isBlank() || app == null) return

        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                app.container.authRepository.registerDevice(token)
            }
        }
    }

    fun unregisterCurrentDevice() {
        val app = context.applicationContext as? AncoraClientesApplication ?: return
        CoroutineScope(Dispatchers.IO).launch {
            runCatching {
                val token = FirebaseMessaging.getInstance().token.await()
                app.container.authRepository.unregisterDevice(token)
            }
        }
    }

    fun showNotification(title: String, body: String, data: Map<String, String>) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val resolvedTitle = title.ifBlank { context.getString(R.string.notification_fallback_title) }
        val resolvedBody = body.ifBlank { context.getString(R.string.app_name) }
        val notificationType = data["notification_type"]?.takeIf { it.isNotBlank() }
            ?: data["type"].orEmpty()
        val channelId = resolveChannelId(notificationType)
        val notificationId = data["notification_id"]?.toIntOrNull() ?: System.currentTimeMillis().toInt()

        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
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
            .setLargeIcon(BitmapFactory.decodeResource(context.resources, R.drawable.logo_ancora_clientes))
            .setColor(ContextCompat.getColor(context, R.color.ancora_wine))
            .setContentTitle(resolvedTitle)
            .setContentText(resolvedBody)
            .setStyle(NotificationCompat.BigTextStyle().bigText(resolvedBody))
            .setPriority(resolvePriority(notificationType))
            .setCategory(resolveCategory(notificationType))
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setDefaults(resolveDefaults(notificationType))
            .build()

        NotificationManagerCompat.from(context).notify(notificationId, notification)
    }

    companion object {
        private const val LEGACY_CHANNEL_ID = "ancora_clientes_updates"
        private const val GENERAL_CHANNEL_ID = "ancora_clientes_general"
        private const val IMPORTANT_CHANNEL_ID = "ancora_clientes_important"

        fun createChannel(context: Context) {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

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
                        IMPORTANT_CHANNEL_ID,
                        context.getString(R.string.notification_channel_important_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_important_description)
                    },
                    NotificationChannel(
                        LEGACY_CHANNEL_ID,
                        context.getString(R.string.notification_channel_name),
                        NotificationManager.IMPORTANCE_HIGH,
                    ).apply {
                        description = context.getString(R.string.notification_channel_description)
                    },
                )
            )
        }

        private fun resolveChannelId(type: String): String = when (type.lowercase()) {
            "emergency", "assembly" -> IMPORTANT_CHANNEL_ID
            else -> GENERAL_CHANNEL_ID
        }

        private fun resolvePriority(type: String): Int = when (type.lowercase()) {
            "emergency" -> NotificationCompat.PRIORITY_MAX
            "assembly" -> NotificationCompat.PRIORITY_HIGH
            else -> NotificationCompat.PRIORITY_DEFAULT
        }

        private fun resolveCategory(type: String): String = when (type.lowercase()) {
            "emergency" -> NotificationCompat.CATEGORY_ALARM
            "assembly" -> NotificationCompat.CATEGORY_REMINDER
            else -> NotificationCompat.CATEGORY_MESSAGE
        }

        private fun resolveDefaults(type: String): Int = when (type.lowercase()) {
            "emergency", "assembly" -> Notification.DEFAULT_ALL
            else -> Notification.DEFAULT_LIGHTS
        }
    }
}

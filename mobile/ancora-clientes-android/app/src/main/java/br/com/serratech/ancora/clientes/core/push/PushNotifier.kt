package br.com.serratech.ancora.clientes.core.push

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
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

        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            data.forEach { (key, value) -> putExtra(key, value) }
        }

        val pendingIntent = PendingIntent.getActivity(
            context,
            System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

        NotificationManagerCompat.from(context).notify(System.currentTimeMillis().toInt(), notification)
    }

    companion object {
        const val CHANNEL_ID = "ancora_clientes_updates"

        fun createChannel(context: Context) {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

            val channel = NotificationChannel(
                CHANNEL_ID,
                context.getString(R.string.notification_channel_name),
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = context.getString(R.string.notification_channel_description)
            }

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }
}

package br.com.serratech.ancora.clientes.core.push

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class AncoraFirebaseMessagingService : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        val app = application as? br.com.serratech.ancora.clientes.AncoraClientesApplication ?: return
        PushNotifier(app).registerTokenIfPossible(token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        PushNotifier(applicationContext).showNotification(
            title = message.data["title"]?.takeIf { it.isNotBlank() } ?: message.notification?.title.orEmpty(),
            body = message.data["body"]?.takeIf { it.isNotBlank() } ?: message.notification?.body.orEmpty(),
            data = message.data,
        )
    }
}

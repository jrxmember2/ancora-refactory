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
            title = message.notification?.title ?: message.data["title"].orEmpty(),
            body = message.notification?.body ?: message.data["body"].orEmpty(),
            data = message.data,
        )
    }
}

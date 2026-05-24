package br.com.serratech.ancora.hub.core.push

import br.com.serratech.ancora.hub.AncoraHubApplication
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class AncoraHubFirebaseMessagingService : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        val app = application as? AncoraHubApplication ?: return
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

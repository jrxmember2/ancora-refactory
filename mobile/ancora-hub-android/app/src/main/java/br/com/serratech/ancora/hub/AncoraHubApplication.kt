package br.com.serratech.ancora.hub

import android.app.Application
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.push.PushNotifier

class AncoraHubApplication : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        PushNotifier.createChannels(this)
    }
}

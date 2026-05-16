package br.com.serratech.ancora.clientes

import android.app.Application
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.core.push.PushNotifier

class AncoraClientesApplication : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        PushNotifier.createChannel(this)
    }
}

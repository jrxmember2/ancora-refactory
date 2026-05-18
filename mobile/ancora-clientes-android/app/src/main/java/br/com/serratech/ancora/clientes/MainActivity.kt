package br.com.serratech.ancora.clientes

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.ViewModelProvider
import br.com.serratech.ancora.clientes.ui.navigation.AncoraClientesApp
import br.com.serratech.ancora.clientes.ui.navigation.AppViewModel
import br.com.serratech.ancora.clientes.ui.navigation.appViewModelFactory
import br.com.serratech.ancora.clientes.ui.theme.AncoraClientesTheme

class MainActivity : FragmentActivity() {
    private lateinit var appViewModel: AppViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val app = application as AncoraClientesApplication
        appViewModel = ViewModelProvider(
            this,
            appViewModelFactory(app.container, intent?.extras),
        )[AppViewModel::class.java]

        setContent {
            AncoraClientesTheme {
                AncoraClientesApp(
                    appViewModel = appViewModel,
                    container = app.container,
                )
            }
        }
    }

    override fun onNewIntent(intent: android.content.Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        if (::appViewModel.isInitialized) {
            appViewModel.applyNotificationIntent(intent.extras)
        }
    }
}

package br.com.serratech.ancora.hub

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.ViewModelProvider
import br.com.serratech.ancora.hub.ui.navigation.AncoraHubApp
import br.com.serratech.ancora.hub.ui.navigation.AppViewModel
import br.com.serratech.ancora.hub.ui.navigation.appViewModelFactory
import br.com.serratech.ancora.hub.ui.theme.AncoraHubTheme

class MainActivity : FragmentActivity() {
    private lateinit var appViewModel: AppViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val app = application as AncoraHubApplication
        appViewModel = ViewModelProvider(
            this,
            appViewModelFactory(app.container, intent?.extras),
        )[AppViewModel::class.java]

        splashScreen.setKeepOnScreenCondition {
            appViewModel.uiState.value.isSplashVisible
        }

        setContent {
            AncoraHubTheme {
                AncoraHubApp(
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

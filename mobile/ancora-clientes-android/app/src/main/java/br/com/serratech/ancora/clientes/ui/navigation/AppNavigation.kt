package br.com.serratech.ancora.clientes.ui.navigation

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountCircle
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Dashboard
import androidx.compose.material.icons.outlined.FolderOpen
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.SupportAgent
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationRail
import androidx.compose.material3.NavigationRailItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.windowsizeclass.ExperimentalMaterial3WindowSizeClassApi
import androidx.compose.material3.windowsizeclass.WindowWidthSizeClass
import androidx.compose.material3.windowsizeclass.calculateWindowSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.core.session.LaunchDestination
import br.com.serratech.ancora.clientes.domain.model.SessionUser
import br.com.serratech.ancora.clientes.ui.screens.biometric.BiometricScreen
import br.com.serratech.ancora.clientes.ui.screens.dashboard.DashboardScreen
import br.com.serratech.ancora.clientes.ui.screens.demands.DemandCreateScreen
import br.com.serratech.ancora.clientes.ui.screens.demands.DemandDetailScreen
import br.com.serratech.ancora.clientes.ui.screens.demands.DemandsScreen
import br.com.serratech.ancora.clientes.ui.screens.leme.LemeScreen
import br.com.serratech.ancora.clientes.ui.screens.login.LoginScreen
import br.com.serratech.ancora.clientes.ui.screens.notifications.NotificationsScreen
import br.com.serratech.ancora.clientes.ui.screens.processes.ProcessDetailScreen
import br.com.serratech.ancora.clientes.ui.screens.processes.ProcessesScreen
import br.com.serratech.ancora.clientes.ui.screens.profile.ProfileScreen
import br.com.serratech.ancora.clientes.ui.screens.setup.SetupScreen
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

private object AppRoutes {
    const val Setup = "setup"
    const val SetupChange = "setup-change"
    const val Login = "login"
    const val Biometric = "biometric"
    const val Dashboard = "dashboard"
    const val Processes = "processes"
    const val ProcessDetail = "process-detail/{processId}"
    const val Demands = "demands"
    const val DemandCreate = "demand-create"
    const val DemandDetail = "demand-detail/{demandId}"
    const val Leme = "leme"
    const val Notifications = "notifications"
    const val Profile = "profile"

    fun processDetail(processId: Long): String = "process-detail/$processId"

    fun demandDetail(demandId: Long): String = "demand-detail/$demandId"
}

data class NotificationTarget(
    val route: String,
)

data class NavigationTarget(
    val route: String,
    val clearBackStack: Boolean = false,
)

data class AppUiState(
    val isLoading: Boolean = true,
    val launchDestination: LaunchDestination = LaunchDestination.Setup,
    val sessionUser: SessionUser? = null,
    val showBiometricOptIn: Boolean = false,
    val navigationTarget: NavigationTarget? = null,
)

class AppViewModel(
    private val container: AppContainer,
    initialExtras: Bundle?,
) : ViewModel() {
    private val _uiState = MutableStateFlow(
        AppUiState(
            navigationTarget = initialExtras.toNotificationTarget()?.let { NavigationTarget(it.route, clearBackStack = false) },
        )
    )
    val uiState: StateFlow<AppUiState> = _uiState.asStateFlow()

    init {
        bootstrap()
    }

    fun bootstrap() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)
            val destination = container.bootstrapAppUseCase()
            val user = if (destination == LaunchDestination.Home || destination == LaunchDestination.Biometric) {
                runCatching { container.authRepository.me() }.getOrNull()
            } else {
                null
            }

            if ((destination == LaunchDestination.Home || destination == LaunchDestination.Biometric) && user == null) {
                container.sessionManager.clearSession(clearInstance = false)
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    launchDestination = LaunchDestination.Login,
                    sessionUser = null,
                    navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
                )
            } else {
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    launchDestination = destination,
                    sessionUser = user,
                    navigationTarget = NavigationTarget(destination.toRoute(), clearBackStack = true),
                )
            }
        }
    }

    fun onInstanceConfigured() {
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Login,
            sessionUser = null,
            navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
        )
    }

    fun onLoginSuccess(user: SessionUser) {
        viewModelScope.launch {
            val shouldOfferBiometric = !container.preferences.wasBiometricPrompted()
            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Home,
                sessionUser = user,
                showBiometricOptIn = shouldOfferBiometric,
                navigationTarget = NavigationTarget(AppRoutes.Dashboard, clearBackStack = true),
            )
        }
    }

    fun onBiometricOptInDecision(enabled: Boolean) {
        viewModelScope.launch {
            container.sessionManager.enableBiometric(enabled)
            _uiState.value = _uiState.value.copy(showBiometricOptIn = false)
        }
    }

    fun onBiometricFallbackRequested() {
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Login,
            navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
        )
    }

    fun onBiometricUnlocked() {
        container.pushNotifier.registerCurrentDevice()
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Home,
            navigationTarget = NavigationTarget(AppRoutes.Dashboard, clearBackStack = true),
        )
    }

    fun logout() {
        viewModelScope.launch {
            container.authRepository.logout()
            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Login,
                sessionUser = null,
                showBiometricOptIn = false,
                navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
            )
        }
    }

    fun applyNotificationIntent(extras: Bundle?) {
        extras.toNotificationTarget()?.let { target ->
            _uiState.value = _uiState.value.copy(
                navigationTarget = NavigationTarget(target.route, clearBackStack = false),
            )
        }
    }

    fun consumeNavigationTarget() {
        _uiState.value = _uiState.value.copy(navigationTarget = null)
    }

    private fun LaunchDestination.toRoute(): String = when (this) {
        LaunchDestination.Setup -> AppRoutes.Setup
        LaunchDestination.Login -> AppRoutes.Login
        LaunchDestination.Biometric -> AppRoutes.Biometric
        LaunchDestination.Home -> AppRoutes.Dashboard
    }

    private fun Bundle?.toNotificationTarget(): NotificationTarget? {
        if (this == null) return null

        val screen = getString("screen").orEmpty()
        val processId = getString("process_id").orEmpty()
        val demandId = getString("demand_id").orEmpty()

        return when {
            screen == "process_detail" && processId.isNotBlank() -> NotificationTarget(AppRoutes.processDetail(processId.toLongOrNull() ?: return null))
            screen == "demand_detail" && demandId.isNotBlank() -> NotificationTarget(AppRoutes.demandDetail(demandId.toLongOrNull() ?: return null))
            screen == "leme" -> NotificationTarget(AppRoutes.Leme)
            screen == "notifications" || screen.isNotBlank() -> NotificationTarget(AppRoutes.Notifications)
            else -> null
        }
    }
}

fun appViewModelFactory(container: AppContainer, extras: Bundle?): ViewModelProvider.Factory =
    object : ViewModelProvider.Factory {
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            @Suppress("UNCHECKED_CAST")
            return AppViewModel(container, extras) as T
        }
    }

private data class NavItem(
    val route: String,
    val label: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
)

@OptIn(ExperimentalMaterial3WindowSizeClassApi::class)
@Composable
fun AncoraClientesApp(
    appViewModel: AppViewModel,
    container: AppContainer,
) {
    val context = LocalContext.current
    val activity = context.findActivity()
    val widthSizeClass = activity?.let { calculateWindowSizeClass(it).widthSizeClass } ?: WindowWidthSizeClass.Compact
    val uiState by appViewModel.uiState.collectAsState()
    val navController = rememberNavController()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route.orEmpty()
    val startRoute = uiState.launchDestination.toAppRoute()
    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { }

    val navItems = listOf(
        NavItem(AppRoutes.Dashboard, "Início", Icons.Outlined.Dashboard),
        NavItem(AppRoutes.Processes, "Processos", Icons.Outlined.FolderOpen),
        NavItem(AppRoutes.Demands, "Solicitações", Icons.Outlined.SupportAgent),
        NavItem(AppRoutes.Leme, "Leme IA", Icons.Outlined.AutoAwesome),
        NavItem(AppRoutes.Notifications, "Notificações", Icons.Outlined.Notifications),
        NavItem(AppRoutes.Profile, "Perfil", Icons.Outlined.AccountCircle),
    )

    LaunchedEffect(uiState.navigationTarget, currentRoute, uiState.isLoading) {
        if (uiState.isLoading) {
            return@LaunchedEffect
        }

        uiState.navigationTarget?.let { target ->
            if (currentRoute == target.route) {
                appViewModel.consumeNavigationTarget()
            } else {
                navController.navigate(target.route) {
                    launchSingleTop = true
                    restoreState = !target.clearBackStack
                    if (target.clearBackStack) {
                        popUpTo(navController.graph.findStartDestination().id) {
                            inclusive = true
                        }
                    }
                }
                appViewModel.consumeNavigationTarget()
            }
        }
    }

    LaunchedEffect(uiState.launchDestination) {
        if (
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            uiState.launchDestination == LaunchDestination.Home &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    val showNavigationChrome = currentRoute in navItems.map { it.route }

    Scaffold(
        bottomBar = {
            if (showNavigationChrome && widthSizeClass == WindowWidthSizeClass.Compact) {
                NavigationBar {
                    navItems.forEach { item ->
                        NavigationBarItem(
                            selected = currentRoute == item.route,
                            onClick = {
                                navController.navigate(item.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            icon = { Icon(item.icon, contentDescription = item.label) },
                            label = { Text(item.label) },
                        )
                    }
                }
            }
        },
    ) { padding ->
        Row(modifier = Modifier.fillMaxSize()) {
            if (showNavigationChrome && widthSizeClass != WindowWidthSizeClass.Compact) {
                NavigationRail {
                    navItems.forEach { item ->
                        NavigationRailItem(
                            selected = currentRoute == item.route,
                            onClick = {
                                navController.navigate(item.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            icon = { Icon(item.icon, contentDescription = item.label) },
                            label = { Text(item.label) },
                        )
                    }
                }
            }

            AppNavHost(
                modifier = Modifier
                    .weight(1f)
                    .padding(padding),
                navController = navController,
                startDestination = startRoute,
                container = container,
                appViewModel = appViewModel,
                uiState = uiState,
            )
        }
    }
}

@Composable
private fun AppNavHost(
    modifier: Modifier,
    navController: androidx.navigation.NavHostController,
    startDestination: String,
    container: AppContainer,
    appViewModel: AppViewModel,
    uiState: AppUiState,
) {
    NavHost(
        navController = navController,
        startDestination = startDestination,
        modifier = modifier,
    ) {
        composable(AppRoutes.Setup) {
            SetupScreen(
                container = container,
                replaceCurrentInstance = false,
                onConfigured = appViewModel::onInstanceConfigured,
            )
        }

        composable(AppRoutes.SetupChange) {
            SetupScreen(
                container = container,
                replaceCurrentInstance = true,
                onConfigured = appViewModel::onInstanceConfigured,
            )
        }

        composable(AppRoutes.Login) {
            LoginScreen(
                container = container,
                onLoginSuccess = appViewModel::onLoginSuccess,
                onOpenInstanceSettings = { navController.navigate(AppRoutes.SetupChange) },
            )
        }

        composable(AppRoutes.Biometric) {
            BiometricScreen(
                onUnlocked = appViewModel::onBiometricUnlocked,
                onUsePassword = appViewModel::onBiometricFallbackRequested,
            )
        }

        composable(AppRoutes.Dashboard) {
            DashboardScreen(
                container = container,
                showBiometricOptIn = uiState.showBiometricOptIn,
                onBiometricDecision = appViewModel::onBiometricOptInDecision,
                onOpenNewDemand = { navController.navigate(AppRoutes.DemandCreate) },
                onOpenLeme = { navController.navigate(AppRoutes.Leme) },
            )
        }

        composable(AppRoutes.Processes) {
            ProcessesScreen(
                container = container,
                onOpenDetail = { processId -> navController.navigate(AppRoutes.processDetail(processId)) },
            )
        }

        composable(
            route = AppRoutes.ProcessDetail,
            arguments = listOf(navArgument("processId") { type = NavType.LongType }),
        ) { entry ->
            ProcessDetailScreen(
                container = container,
                processId = entry.arguments?.getLong("processId") ?: 0L,
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Demands) {
            DemandsScreen(
                container = container,
                onOpenDetail = { demandId -> navController.navigate(AppRoutes.demandDetail(demandId)) },
                onCreateDemand = { navController.navigate(AppRoutes.DemandCreate) },
            )
        }

        composable(AppRoutes.DemandCreate) {
            DemandCreateScreen(
                container = container,
                onBack = { navController.popBackStack() },
                onCreated = { demandId ->
                    navController.navigate(AppRoutes.demandDetail(demandId)) {
                        popUpTo(AppRoutes.Demands)
                    }
                },
            )
        }

        composable(
            route = AppRoutes.DemandDetail,
            arguments = listOf(navArgument("demandId") { type = NavType.LongType }),
        ) { entry ->
            DemandDetailScreen(
                container = container,
                demandId = entry.arguments?.getLong("demandId") ?: 0L,
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Leme) {
            LemeScreen(
                container = container,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigate(AppRoutes.Dashboard) {
                            popUpTo(navController.graph.findStartDestination().id) {
                                saveState = true
                            }
                            launchSingleTop = true
                            restoreState = true
                        }
                    }
                },
            )
        }

        composable(AppRoutes.Notifications) {
            NotificationsScreen(container = container)
        }

        composable(AppRoutes.Profile) {
            ProfileScreen(
                container = container,
                sessionUser = uiState.sessionUser,
                onLogout = appViewModel::logout,
                onOpenInstanceSettings = { navController.navigate(AppRoutes.SetupChange) },
                onBiometricChanged = appViewModel::onBiometricOptInDecision,
            )
        }
    }
}

private fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}

private fun LaunchDestination.toAppRoute(): String = when (this) {
    LaunchDestination.Setup -> AppRoutes.Setup
    LaunchDestination.Login -> AppRoutes.Login
    LaunchDestination.Biometric -> AppRoutes.Biometric
    LaunchDestination.Home -> AppRoutes.Dashboard
}

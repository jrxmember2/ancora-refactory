package br.com.serratech.ancora.hub.ui.navigation

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Assignment
import androidx.compose.material.icons.outlined.Gavel
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.Widgets
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationRail
import androidx.compose.material3.NavigationRailItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.windowsizeclass.ExperimentalMaterial3WindowSizeClassApi
import androidx.compose.material3.windowsizeclass.WindowWidthSizeClass
import androidx.compose.material3.windowsizeclass.calculateWindowSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import br.com.serratech.ancora.hub.R
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.session.LaunchDestination
import br.com.serratech.ancora.hub.data.repository.SessionValidationResult
import br.com.serratech.ancora.hub.domain.model.NotificationItem
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraBottomBar
import br.com.serratech.ancora.hub.ui.components.AncoraBottomBarItem
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.screens.biometric.BiometricScreen
import br.com.serratech.ancora.hub.ui.screens.collections.CollectionDetailScreen
import br.com.serratech.ancora.hub.ui.screens.collections.CollectionsScreen
import br.com.serratech.ancora.hub.ui.screens.common.ModulePlaceholderScreen
import br.com.serratech.ancora.hub.ui.screens.dashboard.DashboardScreen
import br.com.serratech.ancora.hub.ui.screens.demands.DemandDetailScreen
import br.com.serratech.ancora.hub.ui.screens.demands.DemandsScreen
import br.com.serratech.ancora.hub.ui.screens.login.LoginScreen
import br.com.serratech.ancora.hub.ui.screens.more.MoreScreen
import br.com.serratech.ancora.hub.ui.screens.notifications.NotificationDetailScreen
import br.com.serratech.ancora.hub.ui.screens.notifications.NotificationsScreen
import br.com.serratech.ancora.hub.ui.screens.processes.ProcessDetailScreen
import br.com.serratech.ancora.hub.ui.screens.processes.ProcessesScreen
import br.com.serratech.ancora.hub.ui.screens.profile.ProfileScreen
import br.com.serratech.ancora.hub.ui.screens.setup.SetupScreen
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import java.text.Normalizer
import kotlinx.coroutines.delay
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
    const val Demands = "demands"
    const val DemandDetail = "demands/detail/{demandId}"
    const val DemandDetailBase = "demands/detail"
    const val Processes = "processes"
    const val ProcessDetail = "processes/detail/{processId}"
    const val ProcessDetailBase = "processes/detail"
    const val Collections = "collections"
    const val CollectionDetail = "collections/detail/{collectionId}"
    const val CollectionDetailBase = "collections/detail"
    const val More = "more"
    const val Clients = "clients"
    const val Proposals = "proposals"
    const val Contracts = "contracts"
    const val Signer = "signer"
    const val Finance = "finance"
    const val LemeIa = "leme-ia"
    const val Notifications = "notifications"
    const val NotificationDetail = "notifications/detail/{notificationId}"
    const val NotificationDetailBase = "notifications/detail"
    const val Profile = "profile"
    const val Settings = "settings"
}

data class NavigationTarget(
    val route: String,
    val clearBackStack: Boolean = false,
)

data class AppUiState(
    val isSplashVisible: Boolean = true,
    val isLoading: Boolean = true,
    val launchDestination: LaunchDestination = LaunchDestination.Setup,
    val sessionUser: SessionUser? = null,
    val unreadNotifications: Int = 0,
    val navigationTarget: NavigationTarget? = null,
    val pendingPushRoute: String? = null,
    val feedbackMessage: String? = null,
)

class AppViewModel(
    private val container: AppContainer,
    initialExtras: Bundle?,
) : ViewModel() {
    private val initialPushRoute = initialExtras.toPushRoute()
    private val _uiState = MutableStateFlow(
        AppUiState(
            pendingPushRoute = initialPushRoute,
        ),
    )
    val uiState: StateFlow<AppUiState> = _uiState.asStateFlow()

    init {
        bootstrap()
    }

    fun bootstrap() {
        viewModelScope.launch {
            val startedAt = System.currentTimeMillis()
            val launchState = container.sessionManager.resolveLaunchState()
            var destination = launchState.launchDestination
            var sessionUser: SessionUser? = null
            var unreadCount = 0
            var feedbackMessage: String? = when {
                launchState.secureStorageInvalidated -> "Sessão expirada. Entre novamente."
                launchState.localSessionExpired -> "Sessão expirada. Entre novamente."
                else -> null
            }

            when (destination) {
                LaunchDestination.Home -> {
                    when (val result = container.authRepository.validateSession()) {
                        is SessionValidationResult.Success -> {
                            sessionUser = result.user
                            unreadCount = runCatching {
                                container.notificationRepository.unreadCount()
                            }.getOrDefault(0)
                        }

                        is SessionValidationResult.Expired -> {
                            container.sessionManager.clearSession(clearInstance = false)
                            destination = LaunchDestination.Login
                            feedbackMessage = result.message
                        }

                        is SessionValidationResult.Unavailable -> {
                            sessionUser = container.authRepository.cachedUser()
                            if (sessionUser == null) {
                                destination = LaunchDestination.Login
                                feedbackMessage = result.message
                            }
                        }
                    }
                }

                LaunchDestination.Biometric -> {
                    sessionUser = container.authRepository.cachedUser()
                }

                else -> Unit
            }

            val elapsed = System.currentTimeMillis() - startedAt
            val remaining = (2_000L - elapsed).coerceAtLeast(0L)
            if (remaining > 0) {
                delay(remaining)
            }

            _uiState.value = _uiState.value.copy(
                isSplashVisible = false,
                isLoading = false,
                launchDestination = destination,
                sessionUser = sessionUser,
                unreadNotifications = unreadCount,
                feedbackMessage = feedbackMessage,
                navigationTarget = if (
                    destination == LaunchDestination.Home &&
                    _uiState.value.pendingPushRoute != null
                ) {
                    NavigationTarget(_uiState.value.pendingPushRoute.orEmpty())
                } else {
                    null
                },
            )
        }
    }

    fun onInstanceConfigured() {
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Login,
            sessionUser = null,
            unreadNotifications = 0,
            navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
            pendingPushRoute = null,
        )
    }

    fun onLoginSuccess(user: SessionUser) {
        viewModelScope.launch {
            val unreadCount = runCatching {
                container.notificationRepository.unreadCount()
            }.getOrDefault(0)
            val targetRoute = _uiState.value.pendingPushRoute ?: AppRoutes.Dashboard

            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Home,
                sessionUser = user,
                unreadNotifications = unreadCount,
                navigationTarget = NavigationTarget(targetRoute, clearBackStack = true),
            )
        }
    }

    fun onBiometricPasswordFallbackRequested() {
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Login,
            navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
        )
    }

    fun onBiometricUnavailable() {
        viewModelScope.launch {
            container.sessionManager.clearSession(clearInstance = false, clearBiometricState = true)
            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Login,
                sessionUser = null,
                unreadNotifications = 0,
                feedbackMessage = "Sessão expirada. Entre novamente.",
                navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
                pendingPushRoute = null,
            )
        }
    }

    fun onSessionExpired(message: String = "Sessão expirada. Entre novamente.") {
        viewModelScope.launch {
            container.sessionManager.clearSession(clearInstance = false)
            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Login,
                sessionUser = null,
                unreadNotifications = 0,
                feedbackMessage = message,
                navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
                pendingPushRoute = null,
            )
        }
    }

    fun onBiometricUnlocked(user: SessionUser) {
        val targetRoute = _uiState.value.pendingPushRoute ?: AppRoutes.Dashboard
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Home,
            sessionUser = user,
            navigationTarget = NavigationTarget(targetRoute, clearBackStack = true),
        )
    }

    fun onUnreadCountChanged(count: Int) {
        _uiState.value = _uiState.value.copy(unreadNotifications = count.coerceAtLeast(0))
    }

    fun logout() {
        viewModelScope.launch {
            container.authRepository.logout()
            _uiState.value = _uiState.value.copy(
                launchDestination = LaunchDestination.Login,
                sessionUser = null,
                unreadNotifications = 0,
                navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
                pendingPushRoute = null,
            )
        }
    }

    fun onBiometricDisabled() {
        _uiState.value = _uiState.value.copy(
            launchDestination = LaunchDestination.Login,
            sessionUser = null,
            unreadNotifications = 0,
            feedbackMessage = "Biometria desativada.",
            navigationTarget = NavigationTarget(AppRoutes.Login, clearBackStack = true),
            pendingPushRoute = null,
        )
    }

    fun showFeedbackMessage(message: String) {
        _uiState.value = _uiState.value.copy(feedbackMessage = message)
    }

    fun consumeFeedbackMessage() {
        _uiState.value = _uiState.value.copy(feedbackMessage = null)
    }

    fun applyNotificationIntent(extras: Bundle?) {
        val target = extras.toPushRoute() ?: return
        val canNavigateNow = _uiState.value.launchDestination == LaunchDestination.Home &&
            _uiState.value.sessionUser != null

        _uiState.value = _uiState.value.copy(
            pendingPushRoute = target,
            navigationTarget = if (canNavigateNow) {
                NavigationTarget(target, clearBackStack = false)
            } else {
                _uiState.value.navigationTarget
            },
        )
    }

    fun consumeNavigationTarget(navigatedRoute: String? = null) {
        _uiState.value = _uiState.value.copy(
            navigationTarget = null,
            pendingPushRoute = if (
                navigatedRoute != null &&
                navigatedRoute == _uiState.value.pendingPushRoute
            ) {
                null
            } else {
                _uiState.value.pendingPushRoute
            },
        )
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
fun AncoraHubApp(
    appViewModel: AppViewModel,
    container: AppContainer,
) {
    val context = LocalContext.current
    val activity = context.findActivity()
    val spacing = MaterialTheme.spacing
    val widthSizeClass = activity?.let { calculateWindowSizeClass(it).widthSizeClass }
        ?: WindowWidthSizeClass.Compact
    val uiState by appViewModel.uiState.collectAsState()
    val navController = rememberNavController()
    val snackbarHostState = remember { SnackbarHostState() }
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route.orEmpty()
    val startRoute = uiState.launchDestination.toAppRoute()
    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { }

    val navItems = listOf(
        NavItem(AppRoutes.Dashboard, "Início", Icons.Outlined.Home),
        NavItem(AppRoutes.Demands, "Demandas", Icons.AutoMirrored.Outlined.Assignment),
        NavItem(AppRoutes.Processes, "Processos", Icons.Outlined.Gavel),
        NavItem(AppRoutes.Collections, "Cobranças", Icons.Outlined.Payments),
        NavItem(AppRoutes.More, "Mais", Icons.Outlined.Widgets),
    )

    LaunchedEffect(uiState.feedbackMessage) {
        uiState.feedbackMessage?.let { message ->
            snackbarHostState.showSnackbar(message)
            appViewModel.consumeFeedbackMessage()
        }
    }

    LaunchedEffect(uiState.navigationTarget, currentRoute, uiState.isLoading) {
        if (uiState.isLoading || currentRoute.isBlank()) {
            return@LaunchedEffect
        }

        uiState.navigationTarget?.let { target ->
            if (currentRoute == target.route) {
                appViewModel.consumeNavigationTarget(target.route)
            } else {
                navController.navigateToHubRoute(
                    route = target.route,
                    clearBackStack = target.clearBackStack,
                )
                appViewModel.consumeNavigationTarget(target.route)
            }
        }
    }

    LaunchedEffect(uiState.launchDestination) {
        if (
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            uiState.launchDestination == LaunchDestination.Home &&
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.POST_NOTIFICATIONS,
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    val showNavigationChrome = currentRoute in authenticatedRoutes
    val selectedRootRoute = currentRoute.toNavigationRoot()
    val bottomBarItems = navItems.map { item ->
        AncoraBottomBarItem(
            route = item.route,
            label = item.label,
            icon = item.icon,
            badgeCount = if (item.route == AppRoutes.More) uiState.unreadNotifications else 0,
        )
    }

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        bottomBar = {
            if (!uiState.isLoading && showNavigationChrome && widthSizeClass == WindowWidthSizeClass.Compact) {
                AncoraBottomBar(
                    items = bottomBarItems,
                    currentRoute = selectedRootRoute,
                    onNavigate = { route ->
                        navController.navigateToHubRoute(route)
                    },
                )
            }
        },
    ) { padding ->
        if (uiState.isLoading) {
            AppLaunchLoadingScreen(modifier = Modifier.padding(padding))
            return@Scaffold
        }

        Row(modifier = Modifier.fillMaxSize()) {
            if (showNavigationChrome && widthSizeClass != WindowWidthSizeClass.Compact) {
                NavigationRail(
                    modifier = Modifier.padding(top = spacing.sm),
                ) {
                    navItems.forEach { item ->
                        NavigationRailItem(
                            selected = selectedRootRoute == item.route,
                            onClick = { navController.navigateToHubRoute(item.route) },
                            icon = {
                                NavItemIcon(
                                    item = item,
                                    showBadge = item.route == AppRoutes.More && uiState.unreadNotifications > 0,
                                )
                            },
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
    navController: NavHostController,
    startDestination: String,
    container: AppContainer,
    appViewModel: AppViewModel,
    uiState: AppUiState,
) {
    val coroutineScope = rememberCoroutineScope()

    fun openHubRoute(route: String) {
        navController.navigateToHubRoute(route)
    }

    fun openNotification(notification: NotificationItem) {
        coroutineScope.launch {
            if (notification.readAt == null) {
                runCatching { container.notificationRepository.read(notification.id) }
                appViewModel.onUnreadCountChanged(
                    runCatching { container.notificationRepository.unreadCount() }.getOrDefault(0),
                )
            }

            navController.navigateToHubRoute(notification.toNavigationRoute())
        }
    }

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
                onFeedbackMessage = appViewModel::showFeedbackMessage,
            )
        }

        composable(AppRoutes.Biometric) {
            BiometricScreen(
                container = container,
                onUnlocked = appViewModel::onBiometricUnlocked,
                onUsePassword = appViewModel::onBiometricPasswordFallbackRequested,
                onSessionExpired = appViewModel::onSessionExpired,
                onBiometricUnavailable = appViewModel::onBiometricUnavailable,
            )
        }

        composable(AppRoutes.Dashboard) {
            DashboardScreen(
                container = container,
                sessionUser = uiState.sessionUser,
                onUnreadCountChanged = appViewModel::onUnreadCountChanged,
                onOpenDemands = { openHubRoute(AppRoutes.Demands) },
                onOpenProcesses = { openHubRoute(AppRoutes.Processes) },
                onOpenCollections = { openHubRoute(AppRoutes.Collections) },
                onOpenClients = { openHubRoute(AppRoutes.Clients) },
                onOpenNotifications = { openHubRoute(AppRoutes.Notifications) },
                onOpenProfile = { openHubRoute(AppRoutes.Profile) },
                onOpenMore = { openHubRoute(AppRoutes.More) },
                onOpenRoute = ::openHubRoute,
                onOpenNotification = ::openNotification,
            )
        }

        composable(AppRoutes.Demands) {
            DemandsScreen(
                container = container,
                onOpenDemand = { demandId ->
                    openHubRoute(demandDetailRoute(demandId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.DemandDetail,
            arguments = listOf(
                navArgument("demandId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val demandId = backStackEntry.arguments?.getLong("demandId") ?: 0L
            DemandDetailScreen(
                container = container,
                demandId = demandId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Demands)
                    }
                },
            )
        }

        composable(AppRoutes.Processes) {
            ProcessesScreen(
                container = container,
                onOpenProcess = { processId ->
                    openHubRoute(processDetailRoute(processId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.ProcessDetail,
            arguments = listOf(
                navArgument("processId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val processId = backStackEntry.arguments?.getLong("processId") ?: 0L
            ProcessDetailScreen(
                container = container,
                processId = processId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Processes)
                    }
                },
            )
        }

        composable(AppRoutes.Collections) {
            CollectionsScreen(
                container = container,
                onOpenCollection = { collectionId ->
                    openHubRoute(collectionDetailRoute(collectionId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.CollectionDetail,
            arguments = listOf(
                navArgument("collectionId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val collectionId = backStackEntry.arguments?.getLong("collectionId") ?: 0L
            CollectionDetailScreen(
                container = container,
                collectionId = collectionId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Collections)
                    }
                },
            )
        }

        composable(AppRoutes.More) {
            MoreScreen(
                sessionUser = uiState.sessionUser,
                unreadNotifications = uiState.unreadNotifications,
                onOpenClients = { openHubRoute(AppRoutes.Clients) },
                onOpenProposals = { openHubRoute(AppRoutes.Proposals) },
                onOpenContracts = { openHubRoute(AppRoutes.Contracts) },
                onOpenSigner = { openHubRoute(AppRoutes.Signer) },
                onOpenFinance = { openHubRoute(AppRoutes.Finance) },
                onOpenLemeIa = { openHubRoute(AppRoutes.LemeIa) },
                onOpenNotifications = { openHubRoute(AppRoutes.Notifications) },
                onOpenProfile = { openHubRoute(AppRoutes.Profile) },
                onOpenSettings = { openHubRoute(AppRoutes.Settings) },
            )
        }

        composable(AppRoutes.Clients) {
            ModulePlaceholderScreen(
                title = "Clientes",
                description = "O acesso aos cadastros internos de clientes seguirá evoluindo com telas nativas e filtros próprios.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Proposals) {
            ModulePlaceholderScreen(
                title = "Propostas",
                description = "A visão mobile de Propostas será aprofundada com foco em consulta rápida e acompanhamento comercial.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Contracts) {
            ModulePlaceholderScreen(
                title = "Contratos",
                description = "A central de Contratos continuará evoluindo com navegação nativa e ações úteis para o dia a dia.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Signer) {
            ModulePlaceholderScreen(
                title = "Assinador",
                description = "O módulo de Assinador ganhará experiências próprias para pendências, fluxo de assinatura e acompanhamento.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Finance) {
            ModulePlaceholderScreen(
                title = "Financeiro 360",
                description = "O Financeiro 360 continuará evoluindo com indicadores e atalhos pensados para uso no celular.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.LemeIa) {
            ModulePlaceholderScreen(
                title = "Leme IA",
                description = "Os recursos de Leme IA serão expandidos em telas próprias com foco em produtividade do escritório.",
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Notifications) {
            NotificationsScreen(
                container = container,
                onUnreadCountChanged = appViewModel::onUnreadCountChanged,
                onOpenNotification = ::openNotification,
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.NotificationDetail,
            arguments = listOf(
                navArgument("notificationId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val notificationId = backStackEntry.arguments?.getLong("notificationId") ?: 0L
            NotificationDetailScreen(
                container = container,
                notificationId = notificationId,
                onUnreadCountChanged = appViewModel::onUnreadCountChanged,
                onOpenRoute = ::openHubRoute,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Notifications)
                    }
                },
            )
        }

        composable(AppRoutes.Profile) {
            ProfileScreen(
                container = container,
                sessionUser = uiState.sessionUser,
                onLogout = appViewModel::logout,
                onOpenInstanceSettings = { navController.navigate(AppRoutes.SetupChange) },
                onBiometricDisabled = appViewModel::onBiometricDisabled,
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.Settings) {
            ModulePlaceholderScreen(
                title = "Configurações",
                description = "A área de Configurações receberá preferências do aparelho e do uso do aplicativo nas próximas etapas.",
                onBack = { navController.popBackStack() },
            )
        }
    }
}

private fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}

@Composable
private fun NavItemIcon(
    item: NavItem,
    showBadge: Boolean,
) {
    Box {
        Icon(item.icon, contentDescription = item.label)
        if (showBadge) {
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .size(10.dp)
                    .clip(CircleShape)
                    .background(MaterialTheme.colorScheme.error),
            )
        }
    }
}

@Composable
private fun AppLaunchLoadingScreen(modifier: Modifier = Modifier) {
    val spacing = MaterialTheme.spacing

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        AncoraCard(bordered = true) {
            Column(
                modifier = Modifier.fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(spacing.sm),
            ) {
                Image(
                    painter = painterResource(R.drawable.logo_ancora_hub),
                    contentDescription = null,
                    modifier = Modifier.size(160.dp),
                )
                AncoraStatusChip(
                    label = "Inicializando",
                    tone = AncoraTone.Brand,
                )
                CircularProgressIndicator()
                Spacer(modifier = Modifier.height(spacing.xs))
                Text(
                    text = "Preparando o Âncora Hub...",
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

private fun LaunchDestination.toAppRoute(): String = when (this) {
    LaunchDestination.Setup -> AppRoutes.Setup
    LaunchDestination.Login -> AppRoutes.Login
    LaunchDestination.Biometric -> AppRoutes.Biometric
    LaunchDestination.Home -> AppRoutes.Dashboard
}

private val authenticatedRoutes = setOf(
    AppRoutes.Dashboard,
    AppRoutes.Demands,
    AppRoutes.DemandDetail,
    AppRoutes.Processes,
    AppRoutes.ProcessDetail,
    AppRoutes.Collections,
    AppRoutes.CollectionDetail,
    AppRoutes.More,
    AppRoutes.Clients,
    AppRoutes.Proposals,
    AppRoutes.Contracts,
    AppRoutes.Signer,
    AppRoutes.Finance,
    AppRoutes.LemeIa,
    AppRoutes.Notifications,
    AppRoutes.NotificationDetail,
    AppRoutes.Profile,
    AppRoutes.Settings,
)

private fun String.toNavigationRoot(): String = when (this) {
    AppRoutes.DemandDetail -> AppRoutes.Demands
    AppRoutes.ProcessDetail -> AppRoutes.Processes
    AppRoutes.CollectionDetail -> AppRoutes.Collections
    AppRoutes.Notifications,
    AppRoutes.NotificationDetail,
    AppRoutes.Profile,
    AppRoutes.Clients,
    AppRoutes.Proposals,
    AppRoutes.Contracts,
    AppRoutes.Signer,
    AppRoutes.Finance,
    AppRoutes.LemeIa,
    AppRoutes.Settings,
    -> AppRoutes.More

    else -> this
}

private fun NotificationItem.toNavigationRoute(): String {
    val route = resolveHubRoute(
        route = route,
        demandId = data["demand_id"]?.toLongOrNull(),
        processId = data["process_id"]?.toLongOrNull(),
        collectionId = data["collection_id"]?.toLongOrNull(),
    )

    return if (route != null && route != AppRoutes.Notifications) {
        route
    } else {
        notificationDetailRoute(id)
    }
}

private fun Bundle?.toPushRoute(): String? {
    if (this == null) {
        return null
    }

    val route = resolveHubRoute(
        route = getString("route")
            ?: getString("screen")
            ?: getString("module"),
        demandId = bundleLong("demand_id"),
        processId = bundleLong("process_id"),
        collectionId = bundleLong("collection_id"),
    )
    val notificationId = bundleLong("notification_id")

    return when {
        route != null && route != AppRoutes.Notifications -> route
        notificationId != null -> notificationDetailRoute(notificationId)
        route != null -> route
        else -> null
    }
}

private fun resolveHubRoute(
    route: String?,
    demandId: Long? = null,
    processId: Long? = null,
    collectionId: Long? = null,
): String? {
    val normalized = normalizeAppRoute(route)
    return when (normalized) {
        AppRoutes.Demands -> demandId?.let(::demandDetailRoute) ?: AppRoutes.Demands
        AppRoutes.Processes -> processId?.let(::processDetailRoute) ?: AppRoutes.Processes
        AppRoutes.Collections -> collectionId?.let(::collectionDetailRoute) ?: AppRoutes.Collections
        else -> normalized
    }
}

private fun Bundle.bundleLong(key: String): Long? {
    val stringValue = getString(key)?.toLongOrNull()
    if (stringValue != null && stringValue > 0L) {
        return stringValue
    }

    return getLong(key).takeIf { it > 0L }
}

private fun normalizeAppRoute(value: String?): String? {
    val raw = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    if (
        raw.startsWith(AppRoutes.NotificationDetailBase) ||
        raw.startsWith(AppRoutes.DemandDetailBase) ||
        raw.startsWith(AppRoutes.ProcessDetailBase) ||
        raw.startsWith(AppRoutes.CollectionDetailBase)
    ) {
        return raw
    }

    val candidate = Normalizer.normalize(raw, Normalizer.Form.NFD)
        .replace("\\p{Mn}+".toRegex(), "")
        .lowercase()
        .replace(".", "-")
        .replace("_", "-")

    return when (candidate) {
        "dashboard", "inicio", "home" -> AppRoutes.Dashboard
        "notifications", "notificacoes", "notification" -> AppRoutes.Notifications
        "profile", "perfil" -> AppRoutes.Profile
        "demands", "demandas", "demanda" -> AppRoutes.Demands
        "processes", "processos", "processo" -> AppRoutes.Processes
        "collections", "cobrancas", "cobranca" -> AppRoutes.Collections
        "clients", "clientes", "cliente" -> AppRoutes.Clients
        "proposals", "propostas", "proposta" -> AppRoutes.Proposals
        "contracts", "contratos", "contrato" -> AppRoutes.Contracts
        "signer", "assinador", "assinaturas", "assinatura" -> AppRoutes.Signer
        "finance", "financeiro", "financeiro-360", "financeiro360" -> AppRoutes.Finance
        "leme-ia", "lemeia", "ia" -> AppRoutes.LemeIa
        "settings", "configuracoes", "configuracao", "config" -> AppRoutes.Settings
        "more", "mais" -> AppRoutes.More
        else -> null
    }
}

private fun demandDetailRoute(id: Long): String =
    "${AppRoutes.DemandDetailBase}/$id"

private fun processDetailRoute(id: Long): String =
    "${AppRoutes.ProcessDetailBase}/$id"

private fun collectionDetailRoute(id: Long): String =
    "${AppRoutes.CollectionDetailBase}/$id"

private fun notificationDetailRoute(id: Long): String =
    "${AppRoutes.NotificationDetailBase}/$id"

private fun isDetailRoute(route: String): Boolean =
    route.startsWith(AppRoutes.NotificationDetailBase) ||
        route.startsWith(AppRoutes.DemandDetailBase) ||
        route.startsWith(AppRoutes.ProcessDetailBase) ||
        route.startsWith(AppRoutes.CollectionDetailBase)

private fun NavHostController.navigateToHubRoute(
    route: String,
    clearBackStack: Boolean = false,
) {
    val targetRoute = normalizeAppRoute(route)
        ?: route.takeIf(::isDetailRoute)
        ?: AppRoutes.Notifications

    navigate(targetRoute) {
        launchSingleTop = true
        restoreState = !clearBackStack && !isDetailRoute(targetRoute)
        if (clearBackStack) {
            popUpTo(graph.findStartDestination().id) {
                inclusive = true
            }
        } else if (!isDetailRoute(targetRoute)) {
            popUpTo(graph.findStartDestination().id) {
                saveState = true
            }
        }
    }
}

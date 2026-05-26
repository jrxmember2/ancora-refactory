package br.com.serratech.ancora.hub.ui.navigation

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.pm.PackageManager
import android.net.Uri
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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
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
import br.com.serratech.ancora.hub.ui.screens.collections.CollectionEditorScreen
import br.com.serratech.ancora.hub.ui.screens.collections.CollectionsScreen
import br.com.serratech.ancora.hub.ui.screens.clients.ClientDetailScreen
import br.com.serratech.ancora.hub.ui.screens.clients.ClientsScreen
import br.com.serratech.ancora.hub.ui.screens.clients.CondominiumDetailScreen
import br.com.serratech.ancora.hub.ui.screens.clients.CondominiumUnitsScreen
import br.com.serratech.ancora.hub.ui.screens.clients.UnitDetailScreen
import br.com.serratech.ancora.hub.ui.screens.contracts.ContractDetailScreen
import br.com.serratech.ancora.hub.ui.screens.contracts.ContractsScreen
import br.com.serratech.ancora.hub.ui.screens.dashboard.DashboardScreen
import br.com.serratech.ancora.hub.ui.screens.demands.DemandDetailScreen
import br.com.serratech.ancora.hub.ui.screens.demands.DemandsScreen
import br.com.serratech.ancora.hub.ui.screens.finance.FinanceScreen
import br.com.serratech.ancora.hub.ui.screens.leme.LemeIaScreen
import br.com.serratech.ancora.hub.ui.screens.login.LoginScreen
import br.com.serratech.ancora.hub.ui.screens.more.MoreScreen
import br.com.serratech.ancora.hub.ui.screens.notifications.NotificationDetailScreen
import br.com.serratech.ancora.hub.ui.screens.notifications.NotificationsScreen
import br.com.serratech.ancora.hub.ui.screens.processes.ProcessDetailScreen
import br.com.serratech.ancora.hub.ui.screens.processes.ProcessesScreen
import br.com.serratech.ancora.hub.ui.screens.profile.ProfileScreen
import br.com.serratech.ancora.hub.ui.screens.proposals.ProposalDetailScreen
import br.com.serratech.ancora.hub.ui.screens.proposals.ProposalsScreen
import br.com.serratech.ancora.hub.ui.screens.settings.SettingsScreen
import br.com.serratech.ancora.hub.ui.screens.setup.SetupScreen
import br.com.serratech.ancora.hub.ui.screens.signatures.SignatureDetailScreen
import br.com.serratech.ancora.hub.ui.screens.signatures.SignatureCreateScreen
import br.com.serratech.ancora.hub.ui.screens.signatures.SignaturesScreen
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
    const val CollectionCreate = "collections/create"
    const val CollectionEdit = "collections/edit/{collectionId}"
    const val CollectionEditBase = "collections/edit"
    const val CollectionDetail = "collections/detail/{collectionId}"
    const val CollectionDetailBase = "collections/detail"
    const val More = "more"
    const val Clients = "clients"
    const val ClientDetail = "clients/detail/{clientId}"
    const val ClientDetailBase = "clients/detail"
    const val CondominiumDetail = "condominiums/detail/{condominiumId}"
    const val CondominiumDetailBase = "condominiums/detail"
    const val CondominiumUnits = "condominiums/{condominiumId}/units"
    const val CondominiumUnitsBase = "condominiums"
    const val UnitDetail = "units/detail/{unitId}"
    const val UnitDetailBase = "units/detail"
    const val Proposals = "proposals"
    const val ProposalDetail = "proposals/detail/{proposalId}"
    const val ProposalDetailBase = "proposals/detail"
    const val Contracts = "contracts"
    const val ContractDetail = "contracts/detail/{contractId}"
    const val ContractDetailBase = "contracts/detail"
    const val Signer = "signer"
    const val SignatureCreate = "signatures/create"
    const val SignatureDetail = "signatures/detail/{signatureId}"
    const val SignatureDetailBase = "signatures/detail"
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

    fun onSessionUserUpdated(user: SessionUser) {
        _uiState.value = _uiState.value.copy(sessionUser = user)
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
    var launchLoadingElapsed by rememberSaveable { mutableStateOf(false) }
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
    val showLaunchLoading = uiState.isLoading || !launchLoadingElapsed

    LaunchedEffect(Unit) {
        delay(4_000L)
        launchLoadingElapsed = true
    }

    LaunchedEffect(uiState.feedbackMessage) {
        uiState.feedbackMessage?.let { message ->
            snackbarHostState.showSnackbar(message)
            appViewModel.consumeFeedbackMessage()
        }
    }

    LaunchedEffect(uiState.navigationTarget, currentRoute, showLaunchLoading) {
        if (showLaunchLoading || currentRoute.isBlank()) {
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

    Box(modifier = Modifier.fillMaxSize()) {
        AncoraHubBackgroundLayer()

        Scaffold(
            containerColor = Color.Transparent,
            snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
            bottomBar = {
                if (!showLaunchLoading && showNavigationChrome && widthSizeClass == WindowWidthSizeClass.Compact) {
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
            if (showLaunchLoading) {
                AppLaunchLoadingScreen(modifier = Modifier.padding(padding))
                return@Scaffold
            }

            Row(modifier = Modifier.fillMaxSize()) {
                if (showNavigationChrome && widthSizeClass != WindowWidthSizeClass.Compact) {
                    NavigationRail(
                        modifier = Modifier.padding(top = spacing.sm),
                        containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.78f),
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
                onCreateCollection = { openHubRoute(AppRoutes.CollectionCreate) },
                onOpenCollection = { collectionId ->
                    openHubRoute(collectionDetailRoute(collectionId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.CollectionCreate) {
            CollectionEditorScreen(
                container = container,
                collectionId = null,
                onSaved = { collectionId ->
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
                onEditCollection = { openHubRoute(collectionEditRoute(it)) },
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Collections)
                    }
                },
            )
        }

        composable(
            route = AppRoutes.CollectionEdit,
            arguments = listOf(
                navArgument("collectionId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val collectionId = backStackEntry.arguments?.getLong("collectionId")
            CollectionEditorScreen(
                container = container,
                collectionId = collectionId,
                onSaved = { savedId ->
                    openHubRoute(collectionDetailRoute(savedId))
                },
                onBack = { navController.popBackStack() },
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
            ClientsScreen(
                container = container,
                onOpenClient = { clientId ->
                    openHubRoute(clientDetailRoute(clientId))
                },
                onOpenCondominium = { condominiumId ->
                    openHubRoute(condominiumDetailRoute(condominiumId))
                },
            )
        }

        composable(
            route = AppRoutes.ClientDetail,
            arguments = listOf(
                navArgument("clientId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val clientId = backStackEntry.arguments?.getLong("clientId") ?: 0L
            ClientDetailScreen(
                container = container,
                clientId = clientId,
                onOpenCondominium = { condominiumId ->
                    openHubRoute(condominiumDetailRoute(condominiumId))
                },
                onOpenUnit = { unitId ->
                    openHubRoute(unitDetailRoute(unitId))
                },
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Clients)
                    }
                },
            )
        }

        composable(
            route = AppRoutes.CondominiumDetail,
            arguments = listOf(
                navArgument("condominiumId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val condominiumId = backStackEntry.arguments?.getLong("condominiumId") ?: 0L
            CondominiumDetailScreen(
                container = container,
                condominiumId = condominiumId,
                onOpenUnit = { unitId ->
                    openHubRoute(unitDetailRoute(unitId))
                },
                onOpenUnits = { routeCondominiumId ->
                    openHubRoute(condominiumUnitsRoute(routeCondominiumId))
                },
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Clients)
                    }
                },
            )
        }

        composable(
            route = AppRoutes.CondominiumUnits,
            arguments = listOf(
                navArgument("condominiumId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val condominiumId = backStackEntry.arguments?.getLong("condominiumId") ?: 0L
            CondominiumUnitsScreen(
                container = container,
                condominiumId = condominiumId,
                onOpenUnit = { unitId ->
                    openHubRoute(unitDetailRoute(unitId))
                },
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Clients)
                    }
                },
            )
        }

        composable(
            route = AppRoutes.UnitDetail,
            arguments = listOf(
                navArgument("unitId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val unitId = backStackEntry.arguments?.getLong("unitId") ?: 0L
            UnitDetailScreen(
                container = container,
                unitId = unitId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Clients)
                    }
                },
            )
        }

        composable(AppRoutes.Proposals) {
            ProposalsScreen(
                container = container,
                onOpenProposal = { proposalId ->
                    openHubRoute(proposalDetailRoute(proposalId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.ProposalDetail,
            arguments = listOf(
                navArgument("proposalId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val proposalId = backStackEntry.arguments?.getLong("proposalId") ?: 0L
            ProposalDetailScreen(
                container = container,
                proposalId = proposalId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Proposals)
                    }
                },
            )
        }

        composable(AppRoutes.Contracts) {
            ContractsScreen(
                container = container,
                onOpenContract = { contractId ->
                    openHubRoute(contractDetailRoute(contractId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.ContractDetail,
            arguments = listOf(
                navArgument("contractId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val contractId = backStackEntry.arguments?.getLong("contractId") ?: 0L
            ContractDetailScreen(
                container = container,
                contractId = contractId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Contracts)
                    }
                },
            )
        }

        composable(AppRoutes.Signer) {
            SignaturesScreen(
                container = container,
                onCreateSignature = { openHubRoute(AppRoutes.SignatureCreate) },
                onOpenSignature = { signatureId ->
                    openHubRoute(signatureDetailRoute(signatureId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.SignatureCreate) {
            SignatureCreateScreen(
                container = container,
                onCreated = { signatureId ->
                    openHubRoute(signatureDetailRoute(signatureId))
                },
                onBack = { navController.popBackStack() },
            )
        }

        composable(
            route = AppRoutes.SignatureDetail,
            arguments = listOf(
                navArgument("signatureId") {
                    type = NavType.LongType
                },
            ),
        ) { backStackEntry ->
            val signatureId = backStackEntry.arguments?.getLong("signatureId") ?: 0L
            SignatureDetailScreen(
                container = container,
                signatureId = signatureId,
                onBack = {
                    if (!navController.popBackStack()) {
                        navController.navigateToHubRoute(AppRoutes.Signer)
                    }
                },
            )
        }

        composable(AppRoutes.Finance) {
            FinanceScreen(
                container = container,
                onBack = { navController.popBackStack() },
            )
        }

        composable(AppRoutes.LemeIa) {
            LemeIaScreen(
                container = container,
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
            SettingsScreen(
                container = container,
                sessionUser = uiState.sessionUser,
                onUserUpdated = appViewModel::onSessionUserUpdated,
                onOpenInstanceSettings = { navController.navigate(AppRoutes.SetupChange) },
                onLogout = appViewModel::logout,
                onBiometricDisabled = appViewModel::onBiometricDisabled,
                onSessionExpired = appViewModel::onSessionExpired,
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

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background.copy(alpha = 0.34f)),
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = spacing.xl),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Image(
                painter = painterResource(R.drawable.logo_ancora_rastreado),
                contentDescription = null,
                modifier = Modifier.size(190.dp),
            )
            Spacer(modifier = Modifier.height(spacing.lg))
            Text(
                text = "âncora hub",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.onBackground,
            )
            Text(
                text = "powered by Serratech.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(modifier = Modifier.height(spacing.lg))
            AncoraStatusChip(
                label = "Inicializando",
                tone = AncoraTone.Brand,
            )
            Spacer(modifier = Modifier.height(spacing.sm))
            CircularProgressIndicator()
            Spacer(modifier = Modifier.height(spacing.sm))
            Text(
                text = "Preparando o Âncora Hub...",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun AncoraHubBackgroundLayer(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
    ) {
        Image(
            painter = painterResource(R.drawable.background_ancora_hub),
            contentDescription = null,
            modifier = Modifier.fillMaxSize(),
            contentScale = ContentScale.Crop,
            alpha = 0.44f,
        )
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(MaterialTheme.colorScheme.background.copy(alpha = 0.42f)),
        )
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
    AppRoutes.CollectionCreate,
    AppRoutes.CollectionEdit,
    AppRoutes.CollectionDetail,
    AppRoutes.More,
    AppRoutes.Clients,
    AppRoutes.ClientDetail,
    AppRoutes.CondominiumDetail,
    AppRoutes.CondominiumUnits,
    AppRoutes.UnitDetail,
    AppRoutes.Proposals,
    AppRoutes.ProposalDetail,
    AppRoutes.Contracts,
    AppRoutes.ContractDetail,
    AppRoutes.Signer,
    AppRoutes.SignatureCreate,
    AppRoutes.SignatureDetail,
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
    AppRoutes.CollectionDetail,
    AppRoutes.CollectionCreate,
    AppRoutes.CollectionEdit,
    -> AppRoutes.Collections
    AppRoutes.ClientDetail,
    AppRoutes.CondominiumDetail,
    AppRoutes.CondominiumUnits,
    AppRoutes.UnitDetail,
    AppRoutes.Notifications,
    AppRoutes.NotificationDetail,
    AppRoutes.Profile,
    AppRoutes.Clients,
    AppRoutes.Proposals,
    AppRoutes.ProposalDetail,
    AppRoutes.Contracts,
    AppRoutes.ContractDetail,
    AppRoutes.Signer,
    AppRoutes.SignatureDetail,
    AppRoutes.SignatureCreate,
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
        clientId = data["client_id"]?.toLongOrNull(),
        condominiumId = data["condominium_id"]?.toLongOrNull(),
        unitId = data["unit_id"]?.toLongOrNull(),
        proposalId = data["proposal_id"]?.toLongOrNull(),
        contractId = data["contract_id"]?.toLongOrNull(),
        signatureId = data["signature_id"]?.toLongOrNull(),
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
        clientId = bundleLong("client_id"),
        condominiumId = bundleLong("condominium_id"),
        unitId = bundleLong("unit_id"),
        proposalId = bundleLong("proposal_id"),
        contractId = bundleLong("contract_id"),
        signatureId = bundleLong("signature_id"),
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
    clientId: Long? = null,
    condominiumId: Long? = null,
    unitId: Long? = null,
    proposalId: Long? = null,
    contractId: Long? = null,
    signatureId: Long? = null,
): String? {
    hubDeepLinkToAppRoute(route)?.let { return it }

    val normalized = normalizeAppRoute(route)
    return when (normalized) {
        AppRoutes.Demands -> demandId?.let(::demandDetailRoute) ?: AppRoutes.Demands
        AppRoutes.Processes -> processId?.let(::processDetailRoute) ?: AppRoutes.Processes
        AppRoutes.Collections -> collectionId?.let(::collectionDetailRoute) ?: AppRoutes.Collections
        AppRoutes.Clients -> when {
            clientId != null -> clientDetailRoute(clientId)
            condominiumId != null -> condominiumDetailRoute(condominiumId)
            unitId != null -> unitDetailRoute(unitId)
            else -> AppRoutes.Clients
        }
        AppRoutes.Proposals -> proposalId?.let(::proposalDetailRoute) ?: AppRoutes.Proposals
        AppRoutes.Contracts -> contractId?.let(::contractDetailRoute) ?: AppRoutes.Contracts
        AppRoutes.Signer -> signatureId?.let(::signatureDetailRoute) ?: AppRoutes.Signer
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

private fun hubDeepLinkToAppRoute(value: String?): String? {
    val raw = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    if (!raw.startsWith("hub://", ignoreCase = true)) {
        return null
    }

    val uri = runCatching { Uri.parse(raw) }.getOrNull() ?: return AppRoutes.Notifications
    val host = uri.host?.lowercase().orEmpty()
    val segment = uri.pathSegments.firstOrNull()?.toLongOrNull()

    return when (host) {
        "notifications" -> segment?.let(::notificationDetailRoute) ?: AppRoutes.Notifications
        "demands" -> segment?.let(::demandDetailRoute) ?: AppRoutes.Demands
        "processes" -> segment?.let(::processDetailRoute) ?: AppRoutes.Processes
        "collections" -> segment?.let(::collectionDetailRoute) ?: AppRoutes.Collections
        "clients" -> segment?.let(::clientDetailRoute) ?: AppRoutes.Clients
        "condominiums" -> segment?.let(::condominiumDetailRoute) ?: AppRoutes.Clients
        "units" -> segment?.let(::unitDetailRoute) ?: AppRoutes.Clients
        "proposals" -> segment?.let(::proposalDetailRoute) ?: AppRoutes.Proposals
        "contracts" -> segment?.let(::contractDetailRoute) ?: AppRoutes.Contracts
        "signatures" -> segment?.let(::signatureDetailRoute) ?: AppRoutes.Signer
        "finance" -> AppRoutes.Finance
        "leme", "leme-ia" -> AppRoutes.LemeIa
        "dashboard", "home", "inicio" -> AppRoutes.Dashboard
        "profile", "perfil" -> AppRoutes.Profile
        "settings", "configuracoes", "configuracao" -> AppRoutes.Settings
        else -> AppRoutes.Notifications
    }
}

private fun normalizeAppRoute(value: String?): String? {
    val raw = value?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    if (
        raw.startsWith(AppRoutes.NotificationDetailBase) ||
        raw.startsWith(AppRoutes.DemandDetailBase) ||
        raw.startsWith(AppRoutes.ProcessDetailBase) ||
        raw.startsWith(AppRoutes.CollectionEditBase) ||
        raw.startsWith(AppRoutes.CollectionDetailBase) ||
        raw.startsWith(AppRoutes.ClientDetailBase) ||
        raw.startsWith(AppRoutes.CondominiumDetailBase) ||
        raw.startsWith(AppRoutes.UnitDetailBase) ||
        raw.startsWith(AppRoutes.ProposalDetailBase) ||
        raw.startsWith(AppRoutes.ContractDetailBase) ||
        raw.startsWith(AppRoutes.SignatureDetailBase) ||
        raw.matches(Regex("^${AppRoutes.CondominiumUnitsBase}/\\d+/units$"))
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
        "clients", "clientes", "cliente", "condominios", "condominiums", "condominio", "condominium", "unidades", "units", "unidade", "unit" -> AppRoutes.Clients
        "proposals", "propostas", "proposta" -> AppRoutes.Proposals
        "contracts", "contratos", "contrato" -> AppRoutes.Contracts
        "signer", "signatures", "signature", "assinador", "assinaturas", "assinatura" -> AppRoutes.Signer
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

private fun collectionEditRoute(id: Long): String =
    "${AppRoutes.CollectionEditBase}/$id"

private fun clientDetailRoute(id: Long): String =
    "${AppRoutes.ClientDetailBase}/$id"

private fun condominiumDetailRoute(id: Long): String =
    "${AppRoutes.CondominiumDetailBase}/$id"

private fun condominiumUnitsRoute(id: Long): String =
    "${AppRoutes.CondominiumUnitsBase}/$id/units"

private fun unitDetailRoute(id: Long): String =
    "${AppRoutes.UnitDetailBase}/$id"

private fun proposalDetailRoute(id: Long): String =
    "${AppRoutes.ProposalDetailBase}/$id"

private fun contractDetailRoute(id: Long): String =
    "${AppRoutes.ContractDetailBase}/$id"

private fun signatureDetailRoute(id: Long): String =
    "${AppRoutes.SignatureDetailBase}/$id"

private fun notificationDetailRoute(id: Long): String =
    "${AppRoutes.NotificationDetailBase}/$id"

private fun isDetailRoute(route: String): Boolean =
    route.startsWith(AppRoutes.NotificationDetailBase) ||
        route.startsWith(AppRoutes.DemandDetailBase) ||
    route.startsWith(AppRoutes.ProcessDetailBase) ||
        route.startsWith(AppRoutes.CollectionEditBase) ||
        route.startsWith(AppRoutes.CollectionDetailBase) ||
        route.startsWith(AppRoutes.ClientDetailBase) ||
        route.startsWith(AppRoutes.CondominiumDetailBase) ||
        route.startsWith(AppRoutes.UnitDetailBase) ||
        route.startsWith(AppRoutes.ProposalDetailBase) ||
        route.startsWith(AppRoutes.ContractDetailBase) ||
        route.startsWith(AppRoutes.SignatureDetailBase) ||
        route.matches(Regex("^${AppRoutes.CondominiumUnitsBase}/\\d+/units$"))

private fun NavHostController.navigateToHubRoute(
    route: String,
    clearBackStack: Boolean = false,
) {
    val targetRoute = hubDeepLinkToAppRoute(route)
        ?: normalizeAppRoute(route)
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

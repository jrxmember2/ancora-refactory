package br.com.serratech.ancora.clientes.data.repository

import android.content.Context
import android.net.Uri
import android.os.Build
import androidx.core.content.FileProvider
import br.com.serratech.ancora.clientes.BuildConfig
import br.com.serratech.ancora.clientes.core.session.AppSessionManager
import br.com.serratech.ancora.clientes.core.utils.UrlNormalizer
import br.com.serratech.ancora.clientes.data.api.MobileApiService
import br.com.serratech.ancora.clientes.data.dto.AuthResponseDto
import br.com.serratech.ancora.clientes.data.dto.CondominiumDto
import br.com.serratech.ancora.clientes.data.dto.CondominiumsResponseDto
import br.com.serratech.ancora.clientes.data.dto.DashboardResponseDto
import br.com.serratech.ancora.clientes.data.dto.DemandAttachmentDto
import br.com.serratech.ancora.clientes.data.dto.DemandCategoryDto
import br.com.serratech.ancora.clientes.data.dto.DemandDetailDto
import br.com.serratech.ancora.clientes.data.dto.DemandItemDto
import br.com.serratech.ancora.clientes.data.dto.DemandMessageDto
import br.com.serratech.ancora.clientes.data.dto.DeviceRegistrationRequestDto
import br.com.serratech.ancora.clientes.data.dto.FilterOptionDto
import br.com.serratech.ancora.clientes.data.dto.HealthResponseDto
import br.com.serratech.ancora.clientes.data.dto.LemeHistoryResponseDto
import br.com.serratech.ancora.clientes.data.dto.LoginRequestDto
import br.com.serratech.ancora.clientes.data.dto.NotificationDto
import br.com.serratech.ancora.clientes.data.dto.ProcessListResponseDto
import br.com.serratech.ancora.clientes.data.dto.ProcessDetailDto
import br.com.serratech.ancora.clientes.data.dto.ProcessItemDto
import br.com.serratech.ancora.clientes.data.dto.ProcessPhaseDto
import br.com.serratech.ancora.clientes.data.dto.DemandListResponseDto
import br.com.serratech.ancora.clientes.data.dto.UserDto
import br.com.serratech.ancora.clientes.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.clientes.domain.model.Condominium
import br.com.serratech.ancora.clientes.domain.model.CondominiumContext
import br.com.serratech.ancora.clientes.domain.model.DashboardData
import br.com.serratech.ancora.clientes.domain.model.DashboardSummary
import br.com.serratech.ancora.clientes.domain.model.DemandAttachment
import br.com.serratech.ancora.clientes.domain.model.DemandCategory
import br.com.serratech.ancora.clientes.domain.model.DemandDetail
import br.com.serratech.ancora.clientes.domain.model.DemandItem
import br.com.serratech.ancora.clientes.domain.model.DemandListResult
import br.com.serratech.ancora.clientes.domain.model.DemandMessage
import br.com.serratech.ancora.clientes.domain.model.LemeHistory
import br.com.serratech.ancora.clientes.domain.model.LemeMessage
import br.com.serratech.ancora.clientes.domain.model.MovementItem
import br.com.serratech.ancora.clientes.domain.model.NotificationItem
import br.com.serratech.ancora.clientes.domain.model.ProcessDetail
import br.com.serratech.ancora.clientes.domain.model.ProcessFilterOption
import br.com.serratech.ancora.clientes.domain.model.ProcessItem
import br.com.serratech.ancora.clientes.domain.model.ProcessListResult
import br.com.serratech.ancora.clientes.domain.model.ProcessPhase
import br.com.serratech.ancora.clientes.domain.model.RecentConversation
import br.com.serratech.ancora.clientes.domain.model.SessionUser
import br.com.serratech.ancora.clientes.domain.model.StatusInfo
import br.com.serratech.ancora.clientes.domain.model.UsageStatus
import br.com.serratech.ancora.clientes.domain.model.UserPermissions
import java.io.File
import java.util.Locale
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody

class InstanceRepository(
    private val preferences: AppPreferencesDataSource,
    private val sessionManager: AppSessionManager,
    private val urlNormalizer: UrlNormalizer,
    private val json: Json,
) {
    suspend fun validate(url: String): Result<Pair<String, HealthResponseDto>> = withContext(Dispatchers.IO) {
        val normalized = urlNormalizer.normalize(url) ?: return@withContext Result.failure(IllegalArgumentException("Informe um endereço válido."))
        val request = Request.Builder()
            .url("$normalized/api/mobile/v1/health")
            .get()
            .build()

        val response = OkHttpClient().newCall(request).execute()
        if (!response.isSuccessful) {
            return@withContext Result.failure(IllegalStateException("Não conseguimos conectar ao endereço informado. Verifique se o endereço está correto e tente novamente."))
        }

        val body = response.body?.string().orEmpty()
        val parsed = runCatching { json.decodeFromString(HealthResponseDto.serializer(), body) }.getOrNull()
            ?: return@withContext Result.failure(IllegalStateException("O endpoint de health desta instância não retornou um formato compatível."))

        if (!parsed.mobileApi || parsed.status.lowercase(Locale.ROOT) != "ok") {
            return@withContext Result.failure(IllegalStateException("A instância informada não expôs a API mobile esperada."))
        }

        Result.success(normalized to parsed)
    }

    suspend fun saveBaseUrl(url: String, clearSession: Boolean) {
        if (clearSession) {
            sessionManager.clearSession(clearInstance = false)
        }
        sessionManager.saveBaseUrl(url)
    }

    suspend fun currentBaseUrl(): String = preferences.instanceBaseUrl()
}

class AuthRepository(
    private val api: MobileApiService,
    private val sessionManager: AppSessionManager,
    private val preferences: AppPreferencesDataSource,
) {
    suspend fun login(login: String, password: String): SessionUser {
        val response = api.login(
            LoginRequestDto(
                login = login,
                password = password,
                deviceName = "${Build.MANUFACTURER} ${Build.MODEL}",
                appVersion = BuildConfig.VERSION_NAME,
            )
        )
        sessionManager.saveSession(response.accessToken)
        return response.user.toDomain()
    }

    suspend fun me(): SessionUser = api.me().user.toDomain()

    suspend fun logout() {
        unregisterCurrentDeviceIfNeeded()
        runCatching { api.logout() }
        sessionManager.clearSession(clearInstance = false)
    }

    suspend fun registerDevice(fcmToken: String) {
        api.registerDevice(
            DeviceRegistrationRequestDto(
                fcmToken = fcmToken,
                deviceName = "${Build.MANUFACTURER} ${Build.MODEL}",
                appVersion = BuildConfig.VERSION_NAME,
            )
        )
        preferences.setFcmToken(fcmToken)
    }

    suspend fun unregisterDevice(fcmToken: String) {
        runCatching { api.unregisterDevice(mapOf("fcm_token" to fcmToken)) }
        preferences.clearFcmToken()
    }

    suspend fun unregisterCurrentDeviceIfNeeded() {
        val token = preferences.fcmToken()
        if (token.isBlank()) {
            preferences.clearFcmToken()
            return
        }

        unregisterDevice(token)
    }

    suspend fun setBiometricEnabled(enabled: Boolean) {
        sessionManager.enableBiometric(enabled)
    }
}

class DashboardRepository(
    private val api: MobileApiService,
) {
    suspend fun dashboard(): DashboardData = api.dashboard().toDomain()
}

class CondominiumRepository(
    private val api: MobileApiService,
) {
    suspend fun list(): CondominiumContext = api.condominiums().toDomain()

    suspend fun updateSelected(condominiumId: Long?): CondominiumContext =
        api.updateCondominiumContext(mapOf("client_condominium_id" to condominiumId)).toDomain()
}

class ProcessRepository(
    private val api: MobileApiService,
) {
    suspend fun list(
        query: String,
        statusOptionId: Long?,
        condominiumId: Long?,
    ): ProcessListResult = api.processes(
        query = query.takeIf { it.isNotBlank() },
        statusOptionId = statusOptionId,
        condominiumId = condominiumId,
    ).toDomain()

    suspend fun detail(id: Long): ProcessDetail =
        api.processDetail("api/mobile/v1/processes/$id").item.toDomain()
}

class DemandRepository(
    private val context: Context,
    private val api: MobileApiService,
    private val downloadClient: OkHttpClient,
) {
    suspend fun list(
        query: String,
        status: String?,
        condominiumId: Long?,
    ): DemandListResult = api.demands(
        query = query.takeIf { it.isNotBlank() },
        status = status,
        condominiumId = condominiumId,
    ).toDomain()

    suspend fun detail(id: Long): DemandDetail =
        api.demandDetail("api/mobile/v1/demands/$id").item.toDomain()

    suspend fun categories(): List<DemandCategory> =
        api.demandCategories().items.map { it.toDomain() }

    suspend fun create(categoryId: Long, condominiumId: Long?, subject: String, description: String, files: List<Uri>): DemandDetail {
        val payloadFiles = buildMultipartFiles(files)
        return api.createDemand(
            categoryId = categoryId.toString().toRequestBody(),
            clientCondominiumId = condominiumId?.toString()?.toRequestBody(),
            subject = subject.toRequestBody(),
            description = description.toRequestBody(),
            files = payloadFiles,
        ).item.toDomain()
    }

    suspend fun reply(demandId: Long, message: String, files: List<Uri>) {
        api.replyDemand(
            url = "api/mobile/v1/demands/$demandId/reply",
            message = message.toRequestBody(),
            files = buildMultipartFiles(files),
        )
    }

    suspend fun cancel(demandId: Long, reason: String) {
        api.cancelDemand(
            url = "api/mobile/v1/demands/$demandId/cancel",
            payload = mapOf("cancel_reason" to reason),
        )
    }

    suspend fun downloadAttachment(attachment: DemandAttachment): Uri = withContext(Dispatchers.IO) {
        val file = File(context.cacheDir, attachment.originalName)
        val request = Request.Builder().url(attachment.downloadUrl).build()
        downloadClient.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                throw IllegalStateException("Nao foi possivel baixar o anexo agora.")
            }

            response.body?.byteStream()?.use { input ->
                file.outputStream().use { output -> input.copyTo(output) }
            } ?: throw IllegalStateException("O anexo retornou vazio.")
        }

        if (!file.exists() || file.length() == 0L) {
            throw IllegalStateException("O arquivo baixado esta vazio.")
        }

        FileProvider.getUriForFile(
            context,
            "${BuildConfig.APPLICATION_ID}.fileprovider",
            file,
        )
    }

    suspend fun downloadAttachmentToCache(attachment: DemandAttachment): File = withContext(Dispatchers.IO) {
        val file = File(context.cacheDir, attachment.originalName)
        val request = Request.Builder().url(attachment.downloadUrl).build()
        downloadClient.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                throw IllegalStateException("Nao foi possivel baixar o anexo agora.")
            }

            response.body?.byteStream()?.use { input ->
                file.outputStream().use { output -> input.copyTo(output) }
            } ?: throw IllegalStateException("O anexo retornou vazio.")
        }

        if (!file.exists() || file.length() == 0L) {
            throw IllegalStateException("O arquivo baixado esta vazio.")
        }

        file
    }

    private fun buildMultipartFiles(files: List<Uri>): List<MultipartBody.Part> {
        return files.mapNotNull { uri ->
            val stream = context.contentResolver.openInputStream(uri) ?: return@mapNotNull null
            val fileName = uri.lastPathSegment?.substringAfterLast('/') ?: "anexo"
            val tempFile = File(context.cacheDir, fileName)
            stream.use { input -> tempFile.outputStream().use { output -> input.copyTo(output) } }
            val requestBody = tempFile.asRequestBody("application/octet-stream".toMediaType())
            MultipartBody.Part.createFormData("files[]", tempFile.name, requestBody)
        }
    }
}

class NotificationRepository(
    private val api: MobileApiService,
) {
    suspend fun list(unreadOnly: Boolean = false): List<NotificationItem> =
        api.notifications(unreadOnly).items.map { it.toDomain() }

    suspend fun read(id: Long) {
        api.readNotification("api/mobile/v1/notifications/$id/read")
    }

    suspend fun readAll() {
        api.readAllNotifications()
    }
}

class LemeRepository(
    private val api: MobileApiService,
) {
    suspend fun history(conversationId: Long? = null): LemeHistory =
        api.lemeHistory(conversationId).toDomain()

    suspend fun sendMessage(message: String, conversationId: Long?): LemeHistory {
        val response = api.lemeChat(
            br.com.serratech.ancora.clientes.data.dto.LemeChatRequestDto(
                message = message,
                conversationId = conversationId,
                context = mapOf("screen" to "mobile_app"),
            )
        )
        val history = api.lemeHistory(response.conversationId)
        return history.toDomain()
    }

    suspend fun clear(conversationId: Long?) {
        api.clearLemeHistory(conversationId)
    }
}

private fun AuthResponseDto.toDomain(): SessionUser = user.toDomain()

private fun UserDto.toDomain(): SessionUser = SessionUser(
    id = id,
    name = name,
    loginKey = loginKey,
    email = email,
    mustChangePassword = mustChangePassword,
    permissions = UserPermissions(
        canViewProcesses = permissions.canViewProcesses,
        canOpenDemands = permissions.canOpenDemands,
        canViewDemands = permissions.canViewDemands,
        aiEnabled = permissions.aiEnabled,
    ),
    selectedCondominium = selectedCondominium?.toDomain(),
    accessibleCondominiums = accessibleCondominiums.map { it.toDomain() },
)

private fun CondominiumDto.toDomain(): Condominium = Condominium(
    id = id,
    name = name,
    syndicName = syndicName,
    administradoraName = administradoraName,
    type = type,
)

private fun CondominiumsResponseDto.toDomain(): CondominiumContext = CondominiumContext(
    selected = selectedCondominium?.toDomain(),
    items = items.map { it.toDomain() },
)

private fun DashboardResponseDto.toDomain(): DashboardData = DashboardData(
    greeting = greeting,
    selectedCondominium = selectedCondominium?.toDomain(),
    summary = DashboardSummary(
        processesActive = summary.processesActive,
        demandsOpen = summary.demandsOpen,
        demandsWaitingClient = summary.demandsWaitingClient,
        notificationsUnread = summary.notificationsUnread,
    ),
    latestProcesses = latestProcesses.map { it.toDomain() },
    latestDemands = latestDemands.map { it.toDomain() },
    latestMovements = latestMovements.map {
        MovementItem(
            type = it.type,
            title = it.title,
            description = it.description,
            date = it.date,
        )
    },
)

private fun ProcessListResponseDto.toDomain(): ProcessListResult = ProcessListResult(
    items = items.map { it.toDomain() },
    statuses = statuses.map { it.toDomain() },
)

private fun ProcessItemDto.toDomain(): ProcessItem = ProcessItem(
    id = id,
    processNumber = processNumber,
    status = status.toDomain(),
    type = type,
    nature = nature,
    lastPublicPhase = lastPublicPhase?.toDomain(),
    updatedAt = updatedAt,
)

private fun ProcessDetailDto.toDomain(): ProcessDetail = ProcessDetail(
    id = id,
    processNumber = processNumber,
    status = status.toDomain(),
    type = type,
    nature = nature,
    court = court,
    phases = phases.map { it.toDomain() },
)

private fun ProcessPhaseDto.toDomain(): ProcessPhase = ProcessPhase(
    id = id,
    description = description,
    sourceLabel = sourceLabel,
    phaseDateBr = phaseDateBr,
    createdAt = createdAt,
)

private fun DemandItemDto.toDomain(): DemandItem = DemandItem(
    id = id,
    protocol = protocol,
    subject = subject,
    category = category,
    status = status.toDomain(),
    updatedAtBr = updatedAtBr,
    hasNewResponse = hasNewResponse,
    condominium = clientCondominium?.toDomain(),
)

private fun DemandListResponseDto.toDomain(): DemandListResult = DemandListResult(
    items = items.map { it.toDomain() },
    statusLabels = statusLabels,
)

private fun DemandDetailDto.toDomain(): DemandDetail = DemandDetail(
    id = id,
    protocol = protocol,
    subject = subject,
    description = description,
    category = category,
    status = status.toDomain(),
    condominium = clientCondominium?.toDomain(),
    canManage = canManage,
    canCancel = canCancel,
    canReply = canReply,
    messages = messages.map { it.toDomain() },
    attachments = attachments.map { it.toDomain() },
)

private fun DemandMessageDto.toDomain(): DemandMessage = DemandMessage(
    id = id,
    senderType = senderType,
    senderName = senderName,
    message = message,
    createdAtBr = createdAtBr,
    attachments = attachments.map { it.toDomain() },
)

private fun DemandAttachmentDto.toDomain(): DemandAttachment = DemandAttachment(
    id = id,
    originalName = originalName,
    mimeType = mimeType,
    fileSize = fileSize,
    downloadUrl = downloadUrl,
)

private fun DemandCategoryDto.toDomain(): DemandCategory = DemandCategory(
    id = id,
    name = name,
    color = color,
)

private fun NotificationDto.toDomain(): NotificationItem = NotificationItem(
    id = id,
    type = type,
    title = title,
    body = body,
    data = data,
    readAt = readAt,
    createdAtBr = createdAtBr,
)

private fun LemeHistoryResponseDto.toDomain(): LemeHistory = LemeHistory(
    conversationId = conversationId,
    activeCondominium = activeCondominium?.toDomain(),
    usageStatus = UsageStatus(
        allowed = usageStatus.allowed,
        message = usageStatus.message,
        hasLimit = usageStatus.hasLimit,
        remaining = usageStatus.remaining,
    ),
    messages = messages.map {
        LemeMessage(
            id = it.id,
            role = it.role,
            content = it.content,
            createdAtBr = it.createdAtBr,
        )
    },
    recentConversations = recentConversations.map {
        RecentConversation(
            id = it.id,
            title = it.title,
            lastMessageAt = it.lastMessageAt,
        )
    },
)

private fun br.com.serratech.ancora.clientes.data.dto.StatusInfoDto.toDomain(): StatusInfo = StatusInfo(
    key = key,
    label = label,
    color = color,
    tag = tag,
)

private fun FilterOptionDto.toDomain(): ProcessFilterOption = ProcessFilterOption(
    id = id,
    name = name,
    color = color,
)

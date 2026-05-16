package br.com.serratech.ancora.clientes.data.api

import br.com.serratech.ancora.clientes.data.dto.AuthResponseDto
import br.com.serratech.ancora.clientes.data.dto.ChangePasswordRequestDto
import br.com.serratech.ancora.clientes.data.dto.CondominiumsResponseDto
import br.com.serratech.ancora.clientes.data.dto.DashboardResponseDto
import br.com.serratech.ancora.clientes.data.dto.DemandCategoriesResponseDto
import br.com.serratech.ancora.clientes.data.dto.DemandEnvelopeDto
import br.com.serratech.ancora.clientes.data.dto.DemandListResponseDto
import br.com.serratech.ancora.clientes.data.dto.DeviceRegistrationRequestDto
import br.com.serratech.ancora.clientes.data.dto.DeviceRegistrationResponseDto
import br.com.serratech.ancora.clientes.data.dto.HealthResponseDto
import br.com.serratech.ancora.clientes.data.dto.LemeChatRequestDto
import br.com.serratech.ancora.clientes.data.dto.LemeChatResponseDto
import br.com.serratech.ancora.clientes.data.dto.LemeHistoryResponseDto
import br.com.serratech.ancora.clientes.data.dto.LoginRequestDto
import br.com.serratech.ancora.clientes.data.dto.NotificationListResponseDto
import br.com.serratech.ancora.clientes.data.dto.ProcessItemEnvelopeDto
import br.com.serratech.ancora.clientes.data.dto.ProcessListResponseDto
import br.com.serratech.ancora.clientes.data.dto.SimpleResponseDto
import br.com.serratech.ancora.clientes.data.dto.UserEnvelopeDto
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Query
import retrofit2.http.Streaming
import retrofit2.http.Url

interface MobileApiService {
    @GET("api/mobile/v1/health")
    suspend fun health(): HealthResponseDto

    @POST("api/mobile/v1/auth/login")
    suspend fun login(@Body payload: LoginRequestDto): AuthResponseDto

    @POST("api/mobile/v1/auth/logout")
    suspend fun logout(): SimpleResponseDto

    @POST("api/mobile/v1/auth/change-password")
    suspend fun changePassword(@Body payload: ChangePasswordRequestDto): UserEnvelopeDto

    @GET("api/mobile/v1/me")
    suspend fun me(): UserEnvelopeDto

    @POST("api/mobile/v1/devices/register")
    suspend fun registerDevice(@Body payload: DeviceRegistrationRequestDto): DeviceRegistrationResponseDto

    @POST("api/mobile/v1/devices/unregister")
    suspend fun unregisterDevice(@Body payload: Map<String, String>): SimpleResponseDto

    @GET("api/mobile/v1/dashboard")
    suspend fun dashboard(): DashboardResponseDto

    @GET("api/mobile/v1/condominiums")
    suspend fun condominiums(): CondominiumsResponseDto

    @POST("api/mobile/v1/context/condominium")
    suspend fun updateCondominiumContext(@Body payload: Map<String, Long?>): CondominiumsResponseDto

    @GET("api/mobile/v1/processes")
    suspend fun processes(
        @Query("q") query: String? = null,
        @Query("status_option_id") statusOptionId: Long? = null,
        @Query("client_condominium_id") condominiumId: Long? = null,
    ): ProcessListResponseDto

    @GET
    suspend fun processDetail(@Url url: String): ProcessItemEnvelopeDto

    @GET("api/mobile/v1/demands")
    suspend fun demands(
        @Query("q") query: String? = null,
        @Query("status") status: String? = null,
        @Query("client_condominium_id") condominiumId: Long? = null,
    ): DemandListResponseDto

    @GET
    suspend fun demandDetail(@Url url: String): DemandEnvelopeDto

    @GET("api/mobile/v1/demand-categories")
    suspend fun demandCategories(): DemandCategoriesResponseDto

    @Multipart
    @POST("api/mobile/v1/demands")
    suspend fun createDemand(
        @Part("category_id") categoryId: RequestBody,
        @Part("client_condominium_id") clientCondominiumId: RequestBody?,
        @Part("subject") subject: RequestBody,
        @Part("description") description: RequestBody,
        @Part files: List<MultipartBody.Part>,
    ): DemandEnvelopeDto

    @Multipart
    @POST
    suspend fun replyDemand(
        @Url url: String,
        @Part("message") message: RequestBody,
        @Part files: List<MultipartBody.Part>,
    ): SimpleResponseDto

    @POST
    suspend fun cancelDemand(
        @Url url: String,
        @Body payload: Map<String, String>,
    ): SimpleResponseDto

    @GET("api/mobile/v1/notifications")
    suspend fun notifications(@Query("unread_only") unreadOnly: Boolean = false): NotificationListResponseDto

    @POST("api/mobile/v1/notifications/read-all")
    suspend fun readAllNotifications(): SimpleResponseDto

    @POST
    suspend fun readNotification(@Url url: String): SimpleResponseDto

    @GET("api/mobile/v1/leme/history")
    suspend fun lemeHistory(@Query("conversation_id") conversationId: Long? = null): LemeHistoryResponseDto

    @POST("api/mobile/v1/leme/chat")
    suspend fun lemeChat(@Body payload: LemeChatRequestDto): LemeChatResponseDto

    @DELETE("api/mobile/v1/leme/history")
    suspend fun clearLemeHistory(@Query("conversation_id") conversationId: Long? = null): SimpleResponseDto

    @Streaming
    @GET
    suspend fun downloadFile(@Url url: String): ResponseBody
}

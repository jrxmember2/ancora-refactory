package br.com.serratech.ancora.hub.data.api

import br.com.serratech.ancora.hub.data.dto.AttachmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.AuthResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionInstallmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionListResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionTimelineResponseDto
import br.com.serratech.ancora.hub.data.dto.DashboardResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandActionResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandListResponseDto
import br.com.serratech.ancora.hub.data.dto.DeviceRegistrationRequestDto
import br.com.serratech.ancora.hub.data.dto.DeviceRegistrationResponseDto
import br.com.serratech.ancora.hub.data.dto.HealthResponseDto
import br.com.serratech.ancora.hub.data.dto.LoginRequestDto
import br.com.serratech.ancora.hub.data.dto.NotificationListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessMovementsResponseDto
import br.com.serratech.ancora.hub.data.dto.SessionPayloadDto
import br.com.serratech.ancora.hub.data.dto.SimpleResponseDto
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface HubApiService {
    @GET("api/hub/v1/health")
    suspend fun health(): HealthResponseDto

    @POST("api/hub/v1/auth/login")
    suspend fun login(@Body payload: LoginRequestDto): AuthResponseDto

    @POST("api/hub/v1/auth/logout")
    suspend fun logout(): SimpleResponseDto

    @GET("api/hub/v1/me")
    suspend fun me(): SessionPayloadDto

    @POST("api/hub/v1/devices/register")
    suspend fun registerDevice(@Body payload: DeviceRegistrationRequestDto): DeviceRegistrationResponseDto

    @POST("api/hub/v1/devices/unregister")
    suspend fun unregisterDevice(@Body payload: Map<String, String>): SimpleResponseDto

    @GET("api/hub/v1/dashboard")
    suspend fun dashboard(): DashboardResponseDto

    @GET("api/hub/v1/notifications")
    suspend fun notifications(
        @Query("filter") filter: String? = null,
        @Query("per_page") perPage: Int? = null,
    ): NotificationListResponseDto

    @POST("api/hub/v1/notifications/read-all")
    suspend fun readAllNotifications(): SimpleResponseDto

    @POST("api/hub/v1/notifications/{notificationId}/read")
    suspend fun readNotification(@Path("notificationId") notificationId: Long): SimpleResponseDto

    @GET("api/hub/v1/demands")
    suspend fun demands(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status") status: String? = null,
        @Query("priority") priority: String? = null,
        @Query("assigned_user_id") assignedUserId: Long? = null,
        @Query("q") query: String? = null,
    ): DemandListResponseDto

    @GET("api/hub/v1/demands/{demandId}")
    suspend fun demand(@Path("demandId") demandId: Long): DemandDetailResponseDto

    @POST("api/hub/v1/demands/{demandId}/reply")
    suspend fun replyDemand(
        @Path("demandId") demandId: Long,
        @Body payload: Map<String, String>,
    ): DemandActionResponseDto

    @POST("api/hub/v1/demands/{demandId}/status")
    suspend fun updateDemandStatus(
        @Path("demandId") demandId: Long,
        @Body payload: Map<String, String>,
    ): DemandActionResponseDto

    @POST("api/hub/v1/demands/{demandId}/assign")
    suspend fun assignDemand(
        @Path("demandId") demandId: Long,
        @Body payload: Map<String, Long>,
    ): DemandActionResponseDto

    @GET("api/hub/v1/processes")
    suspend fun processes(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status_option_id") statusOptionId: Long? = null,
        @Query("q") query: String? = null,
    ): ProcessListResponseDto

    @GET("api/hub/v1/processes/{processId}")
    suspend fun process(@Path("processId") processId: Long): ProcessDetailResponseDto

    @GET("api/hub/v1/processes/{processId}/movements")
    suspend fun processMovements(
        @Path("processId") processId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
    ): ProcessMovementsResponseDto

    @GET("api/hub/v1/processes/{processId}/attachments")
    suspend fun processAttachments(
        @Path("processId") processId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
    ): AttachmentsResponseDto

    @GET("api/hub/v1/collections")
    suspend fun collections(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status") status: String? = null,
        @Query("workflow_stage") workflowStage: String? = null,
        @Query("situation") situation: String? = null,
        @Query("billing_status") billingStatus: String? = null,
        @Query("q") query: String? = null,
    ): CollectionListResponseDto

    @GET("api/hub/v1/collections/{collectionId}")
    suspend fun collection(@Path("collectionId") collectionId: Long): CollectionDetailResponseDto

    @GET("api/hub/v1/collections/{collectionId}/installments")
    suspend fun collectionInstallments(
        @Path("collectionId") collectionId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
    ): CollectionInstallmentsResponseDto

    @GET("api/hub/v1/collections/{collectionId}/timeline")
    suspend fun collectionTimeline(
        @Path("collectionId") collectionId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
    ): CollectionTimelineResponseDto

    @GET("api/hub/v1/collections/{collectionId}/attachments")
    suspend fun collectionAttachments(
        @Path("collectionId") collectionId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
    ): AttachmentsResponseDto
}

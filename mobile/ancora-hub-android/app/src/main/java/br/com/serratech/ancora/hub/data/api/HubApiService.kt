package br.com.serratech.ancora.hub.data.api

import br.com.serratech.ancora.hub.data.dto.AttachmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.AuthResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientDocumentsResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientListResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionInstallmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionListResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionTimelineResponseDto
import br.com.serratech.ancora.hub.data.dto.CondominiumDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.CondominiumListResponseDto
import br.com.serratech.ancora.hub.data.dto.CondominiumUnitsResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractDocumentsResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractListResponseDto
import br.com.serratech.ancora.hub.data.dto.DashboardResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandActionResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandListResponseDto
import br.com.serratech.ancora.hub.data.dto.DeviceRegistrationRequestDto
import br.com.serratech.ancora.hub.data.dto.DeviceRegistrationResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceCashflowResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceDashboardResponseDto
import br.com.serratech.ancora.hub.data.dto.FinancePayablesResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceReceivablesResponseDto
import br.com.serratech.ancora.hub.data.dto.HealthResponseDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationResponseDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationsResponseDto
import br.com.serratech.ancora.hub.data.dto.LemeCreateConversationRequestDto
import br.com.serratech.ancora.hub.data.dto.LemeSendMessageRequestDto
import br.com.serratech.ancora.hub.data.dto.LoginRequestDto
import br.com.serratech.ancora.hub.data.dto.NotificationListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessMovementsResponseDto
import br.com.serratech.ancora.hub.data.dto.ProposalDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ProposalListResponseDto
import br.com.serratech.ancora.hub.data.dto.SessionPayloadDto
import br.com.serratech.ancora.hub.data.dto.SignatureActionResponseDto
import br.com.serratech.ancora.hub.data.dto.SignatureDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.SignatureListResponseDto
import br.com.serratech.ancora.hub.data.dto.SimpleResponseDto
import br.com.serratech.ancora.hub.data.dto.UnitDetailResponseDto
import okhttp3.ResponseBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming
import retrofit2.Response

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

    @GET("api/hub/v1/clients")
    suspend fun clients(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("scope") scope: String? = null,
        @Query("status") status: String? = null,
        @Query("q") query: String? = null,
    ): ClientListResponseDto

    @GET("api/hub/v1/clients/{clientId}")
    suspend fun client(@Path("clientId") clientId: Long): ClientDetailResponseDto

    @GET("api/hub/v1/condominiums")
    suspend fun condominiums(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status") status: String? = null,
        @Query("q") query: String? = null,
    ): CondominiumListResponseDto

    @GET("api/hub/v1/condominiums/{condominiumId}")
    suspend fun condominium(@Path("condominiumId") condominiumId: Long): CondominiumDetailResponseDto

    @GET("api/hub/v1/condominiums/{condominiumId}/units")
    suspend fun condominiumUnits(
        @Path("condominiumId") condominiumId: Long,
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("q") query: String? = null,
    ): CondominiumUnitsResponseDto

    @GET("api/hub/v1/units/{unitId}")
    suspend fun unit(@Path("unitId") unitId: Long): UnitDetailResponseDto

    @GET("api/hub/v1/condominiums/{condominiumId}/documents")
    suspend fun condominiumDocuments(@Path("condominiumId") condominiumId: Long): ClientDocumentsResponseDto

    @GET("api/hub/v1/units/{unitId}/documents")
    suspend fun unitDocuments(@Path("unitId") unitId: Long): ClientDocumentsResponseDto

    @Streaming
    @GET("api/hub/v1/documents/{documentId}/download")
    suspend fun downloadDocument(@Path("documentId") documentId: Long): Response<ResponseBody>

    @GET("api/hub/v1/proposals")
    suspend fun proposals(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status_id") statusId: Long? = null,
        @Query("service_id") serviceId: Long? = null,
        @Query("q") query: String? = null,
    ): ProposalListResponseDto

    @GET("api/hub/v1/proposals/{proposalId}")
    suspend fun proposal(@Path("proposalId") proposalId: Long): ProposalDetailResponseDto

    @GET("api/hub/v1/contracts")
    suspend fun contracts(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status") status: String? = null,
        @Query("q") query: String? = null,
    ): ContractListResponseDto

    @GET("api/hub/v1/contracts/{contractId}")
    suspend fun contract(@Path("contractId") contractId: Long): ContractDetailResponseDto

    @GET("api/hub/v1/contracts/{contractId}/documents")
    suspend fun contractDocuments(@Path("contractId") contractId: Long): ContractDocumentsResponseDto

    @Streaming
    @GET("api/hub/v1/contracts/{contractId}/download")
    suspend fun downloadContractDocument(
        @Path("contractId") contractId: Long,
        @Query("kind") kind: String? = null,
        @Query("reference_id") referenceId: Long? = null,
    ): Response<ResponseBody>

    @GET("api/hub/v1/signatures")
    suspend fun signatures(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("status") status: String? = null,
        @Query("origin") origin: String? = null,
        @Query("q") query: String? = null,
    ): SignatureListResponseDto

    @GET("api/hub/v1/signatures/{signatureId}")
    suspend fun signature(@Path("signatureId") signatureId: Long): SignatureDetailResponseDto

    @POST("api/hub/v1/signatures/{signatureId}/sync")
    suspend fun syncSignature(@Path("signatureId") signatureId: Long): SignatureActionResponseDto

    @GET("api/hub/v1/finance/dashboard")
    suspend fun financeDashboard(): FinanceDashboardResponseDto

    @GET("api/hub/v1/finance/receivables")
    suspend fun financeReceivables(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("filter") filter: String? = null,
        @Query("q") query: String? = null,
    ): FinanceReceivablesResponseDto

    @GET("api/hub/v1/finance/payables")
    suspend fun financePayables(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("filter") filter: String? = null,
        @Query("q") query: String? = null,
    ): FinancePayablesResponseDto

    @GET("api/hub/v1/finance/cashflow")
    suspend fun financeCashflow(
        @Query("page") page: Int? = null,
        @Query("per_page") perPage: Int? = null,
        @Query("period") period: String? = null,
    ): FinanceCashflowResponseDto

    @GET("api/hub/v1/leme/conversations")
    suspend fun lemeConversations(): LemeConversationsResponseDto

    @POST("api/hub/v1/leme/conversations")
    suspend fun createLemeConversation(
        @Body payload: LemeCreateConversationRequestDto,
    ): LemeConversationResponseDto

    @GET("api/hub/v1/leme/conversations/{conversationId}")
    suspend fun lemeConversation(
        @Path("conversationId") conversationId: Long,
    ): LemeConversationResponseDto

    @POST("api/hub/v1/leme/conversations/{conversationId}/messages")
    suspend fun sendLemeMessage(
        @Path("conversationId") conversationId: Long,
        @Body payload: LemeSendMessageRequestDto,
    ): LemeConversationResponseDto

    @retrofit2.http.DELETE("api/hub/v1/leme/conversations/{conversationId}")
    suspend fun deleteLemeConversation(
        @Path("conversationId") conversationId: Long,
    ): SimpleResponseDto
}

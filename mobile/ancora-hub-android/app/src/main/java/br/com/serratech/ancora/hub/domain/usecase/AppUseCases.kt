package br.com.serratech.ancora.hub.domain.usecase

import br.com.serratech.ancora.hub.core.session.AppSessionManager
import br.com.serratech.ancora.hub.core.session.LaunchDestination
import br.com.serratech.ancora.hub.data.repository.InstanceRepository

class BootstrapAppUseCase(
    private val sessionManager: AppSessionManager,
) {
    suspend operator fun invoke(): LaunchDestination = sessionManager.resolveLaunchDestination()
}

class ValidateInstanceUseCase(
    private val instanceRepository: InstanceRepository,
) {
    suspend operator fun invoke(url: String) = instanceRepository.validate(url)
}

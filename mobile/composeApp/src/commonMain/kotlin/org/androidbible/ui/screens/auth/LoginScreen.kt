package org.androidbible.ui.screens.auth

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.LoginRequest
import org.androidbible.domain.model.RegisterRequest
import org.androidbible.domain.model.SocialAuthRequest
import org.androidbible.domain.repository.AuthRepository
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class LoginScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { LoginScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text(if (state.isRegisterMode) "Create Account" else "Sign In") },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) {
                            Text("\u2190 Back")
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            },
        ) { padding ->
            Column(
                modifier = Modifier
                    .padding(padding)
                    .fillMaxSize()
                    .padding(24.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                if (state.showForgotPassword) {
                    // Forgot password form
                    ForgotPasswordContent(
                        email = state.forgotEmail,
                        onEmailChange = { screenModel.updateForgotEmail(it) },
                        isLoading = state.isLoading,
                        message = state.forgotPasswordMessage,
                        error = state.error,
                        onSubmit = { screenModel.submitForgotPassword() },
                        onBack = { screenModel.hideForgotPassword() },
                    )
                } else {
                    // Main login/register form
                    if (state.isRegisterMode) {
                        OutlinedTextField(
                            value = state.name,
                            onValueChange = { screenModel.updateName(it) },
                            label = { Text("Name") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                    }

                    OutlinedTextField(
                        value = state.email,
                        onValueChange = { screenModel.updateEmail(it) },
                        label = { Text("Email") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                    )

                    Spacer(modifier = Modifier.height(12.dp))

                    OutlinedTextField(
                        value = state.password,
                        onValueChange = { screenModel.updatePassword(it) },
                        label = { Text("Password") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        visualTransformation = PasswordVisualTransformation(),
                    )

                    if (state.isRegisterMode) {
                        Spacer(modifier = Modifier.height(12.dp))
                        OutlinedTextField(
                            value = state.passwordConfirmation,
                            onValueChange = { screenModel.updatePasswordConfirmation(it) },
                            label = { Text("Confirm Password") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            visualTransformation = PasswordVisualTransformation(),
                        )
                    }

                    if (!state.isRegisterMode) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End,
                        ) {
                            TextButton(onClick = { screenModel.showForgotPassword() }) {
                                Text(
                                    "Forgot Password?",
                                    style = MaterialTheme.typography.bodySmall,
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    if (state.error != null) {
                        Text(
                            text = state.error!!,
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodySmall,
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                    }

                    Button(
                        onClick = {
                            screenModel.submit {
                                navigator.pop()
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !state.isLoading,
                    ) {
                        if (state.isLoading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                strokeWidth = 2.dp,
                            )
                        } else {
                            Text(if (state.isRegisterMode) "Create Account" else "Sign In")
                        }
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    // Divider with "OR"
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        HorizontalDivider(modifier = Modifier.weight(1f))
                        Text(
                            " or continue with ",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        HorizontalDivider(modifier = Modifier.weight(1f))
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Social auth buttons
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        OutlinedButton(
                            onClick = { screenModel.socialLogin("google", navigator::pop) },
                            modifier = Modifier.weight(1f),
                            enabled = !state.isLoading,
                        ) {
                            Text("Google")
                        }
                        OutlinedButton(
                            onClick = { screenModel.socialLogin("apple", navigator::pop) },
                            modifier = Modifier.weight(1f),
                            enabled = !state.isLoading,
                        ) {
                            Text("Apple")
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    TextButton(
                        onClick = { screenModel.toggleMode() },
                    ) {
                        Text(
                            if (state.isRegisterMode) "Already have an account? Sign In"
                            else "Don't have an account? Create one",
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ForgotPasswordContent(
    email: String,
    onEmailChange: (String) -> Unit,
    isLoading: Boolean,
    message: String?,
    error: String?,
    onSubmit: () -> Unit,
    onBack: () -> Unit,
) {
    Text(
        "Reset Password",
        style = MaterialTheme.typography.headlineSmall,
    )
    Spacer(modifier = Modifier.height(8.dp))
    Text(
        "Enter your email address and we'll send you a link to reset your password.",
        style = MaterialTheme.typography.bodyMedium,
        textAlign = TextAlign.Center,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
    Spacer(modifier = Modifier.height(24.dp))

    OutlinedTextField(
        value = email,
        onValueChange = onEmailChange,
        label = { Text("Email") },
        modifier = Modifier.fillMaxWidth(),
        singleLine = true,
    )

    Spacer(modifier = Modifier.height(16.dp))

    if (message != null) {
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer,
            ),
        ) {
            Text(
                message,
                modifier = Modifier.padding(16.dp),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onPrimaryContainer,
            )
        }
        Spacer(modifier = Modifier.height(8.dp))
    }

    if (error != null) {
        Text(
            error,
            color = MaterialTheme.colorScheme.error,
            style = MaterialTheme.typography.bodySmall,
        )
        Spacer(modifier = Modifier.height(8.dp))
    }

    Button(
        onClick = onSubmit,
        modifier = Modifier.fillMaxWidth(),
        enabled = !isLoading,
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                strokeWidth = 2.dp,
            )
        } else {
            Text("Send Reset Link")
        }
    }

    Spacer(modifier = Modifier.height(8.dp))

    TextButton(onClick = onBack) {
        Text("Back to Sign In")
    }
}

data class LoginState(
    val email: String = "",
    val password: String = "",
    val name: String = "",
    val passwordConfirmation: String = "",
    val isRegisterMode: Boolean = false,
    val isLoading: Boolean = false,
    val error: String? = null,
    val showForgotPassword: Boolean = false,
    val forgotEmail: String = "",
    val forgotPasswordMessage: String? = null,
)

class LoginScreenModel : ScreenModel, KoinComponent {

    private val authRepo: AuthRepository by inject()

    private val _state = MutableStateFlow(LoginState())
    val state: StateFlow<LoginState> = _state.asStateFlow()

    fun updateEmail(email: String) {
        _state.value = _state.value.copy(email = email, error = null)
    }

    fun updatePassword(password: String) {
        _state.value = _state.value.copy(password = password, error = null)
    }

    fun updateName(name: String) {
        _state.value = _state.value.copy(name = name, error = null)
    }

    fun updatePasswordConfirmation(confirmation: String) {
        _state.value = _state.value.copy(passwordConfirmation = confirmation, error = null)
    }

    fun toggleMode() {
        _state.value = _state.value.copy(
            isRegisterMode = !_state.value.isRegisterMode,
            error = null,
        )
    }

    fun showForgotPassword() {
        _state.value = _state.value.copy(
            showForgotPassword = true,
            forgotEmail = _state.value.email,
            error = null,
            forgotPasswordMessage = null,
        )
    }

    fun hideForgotPassword() {
        _state.value = _state.value.copy(
            showForgotPassword = false,
            error = null,
        )
    }

    fun updateForgotEmail(email: String) {
        _state.value = _state.value.copy(forgotEmail = email, error = null)
    }

    fun submitForgotPassword() {
        val email = _state.value.forgotEmail
        _state.value = _state.value.copy(isLoading = true, error = null)

        screenModelScope.launch {
            try {
                authRepo.forgotPassword(email)
                _state.value = _state.value.copy(
                    isLoading = false,
                    forgotPasswordMessage = "Password reset link sent to $email",
                )
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Failed to send reset link",
                )
            }
        }
    }

    fun socialLogin(provider: String, onSuccess: () -> Unit) {
        _state.value = _state.value.copy(isLoading = true, error = null)

        screenModelScope.launch {
            try {
                // In a real app, this would trigger the platform OAuth flow.
                // The token would come from Google/Apple sign-in SDK.
                authRepo.socialAuth(
                    SocialAuthRequest(
                        provider = provider,
                        token = "", // Platform SDK provides this
                    ),
                )
                _state.value = _state.value.copy(isLoading = false)
                onSuccess()
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Social login failed",
                )
            }
        }
    }

    fun submit(onSuccess: () -> Unit) {
        val s = _state.value
        _state.value = s.copy(isLoading = true, error = null)

        screenModelScope.launch {
            try {
                if (s.isRegisterMode) {
                    authRepo.register(
                        RegisterRequest(
                            name = s.name,
                            email = s.email,
                            password = s.password,
                            passwordConfirmation = s.passwordConfirmation,
                        ),
                    )
                } else {
                    authRepo.login(
                        LoginRequest(
                            email = s.email,
                            password = s.password,
                        ),
                    )
                }
                _state.value = _state.value.copy(isLoading = false)
                onSuccess()
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    isLoading = false,
                    error = e.message ?: "Authentication failed",
                )
            }
        }
    }
}

package br.com.serratech.ancora.hub.ui.components

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Search
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier

@Composable
fun AncoraSearchBar(
    query: String,
    onQueryChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "Buscar",
) {
    AncoraTextField(
        value = query,
        onValueChange = onQueryChange,
        modifier = modifier,
        label = "Buscar",
        placeholder = placeholder,
        leadingIcon = Icons.Outlined.Search,
    )
}

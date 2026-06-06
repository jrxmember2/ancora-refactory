{{-- Criação rápida de categoria financeira (AJAX) a partir dos formulários de contas a pagar/receber. --}}
<script>
(function () {
    document.querySelectorAll('[data-quick-category]').forEach(function (button) {
        button.addEventListener('click', function () {
            var name = window.prompt('Nome da nova categoria:');
            if (!name || !name.trim()) {
                return;
            }

            var url = button.getAttribute('data-url');
            var type = button.getAttribute('data-type') || 'despesa';
            var token = button.getAttribute('data-token');
            var label = button.closest('label');
            var select = label ? label.querySelector('[data-category-select]') : null;

            button.disabled = true;

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name: name.trim(), type: type }),
            })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(response); })
                .then(function (data) {
                    if (data && data.ok && data.category && select) {
                        var exists = select.querySelector('option[value="' + data.category.id + '"]');
                        if (!exists) {
                            var option = document.createElement('option');
                            option.value = data.category.id;
                            option.textContent = data.category.name;
                            select.appendChild(option);
                        }
                        select.value = data.category.id;
                    }
                })
                .catch(function () {
                    window.alert('Nao foi possivel criar a categoria. Verifique suas permissoes e tente novamente.');
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });
})();
</script>

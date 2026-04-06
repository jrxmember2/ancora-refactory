@php
    $address = $address ?? [];
    $title = $title ?? 'Endereço';
    $showNotes = $showNotes ?? true;
    $disabledExpression = $disabledExpression ?? null;
    $states = config('brazil.states', []);
    $rawState = old($prefix . '_state', $address['state'] ?? '');
    $selectedState = collect($states)->first(function (array $state) use ($rawState) {
        $normalized = mb_strtolower(trim((string) $rawState));
        return $normalized !== ''
            && (
                mb_strtolower($state['sigla']) === $normalized
                || mb_strtolower($state['nome']) === $normalized
            );
    });
    $selectedStateSigla = $selectedState['sigla'] ?? (strlen(trim((string) $rawState)) <= 2 ? strtoupper(trim((string) $rawState)) : '');
    $selectedCity = old($prefix . '_city', $address['city'] ?? '');
    $disabledAttr = $disabledExpression ? " :disabled=\"{$disabledExpression}\"" : '';
    $disabledClass = $disabledExpression ? " x-bind:class=\"{$disabledExpression} ? 'opacity-60' : ''\"" : '';
    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"
     x-data="brazilAddressField({
        prefix: @js($prefix),
        states: @js($states),
        selectedState: @js($selectedStateSigla),
        rawState: @js($rawState),
        selectedCity: @js($selectedCity),
        initialZip: @js(old($prefix . '_zip', $address['zip'] ?? '')),
        initialStreet: @js(old($prefix . '_street', $address['street'] ?? '')),
        initialNumber: @js(old($prefix . '_number', $address['number'] ?? '')),
        initialComplement: @js(old($prefix . '_complement', $address['complement'] ?? '')),
        initialNeighborhood: @js(old($prefix . '_neighborhood', $address['neighborhood'] ?? '')),
        initialNotes: @js(old($prefix . '_notes', $address['notes'] ?? '')),
     })"
     x-init="init()"
     {!! $disabledClass !!}>
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        <div class="text-xs text-gray-500 dark:text-gray-400">UF e município via IBGE • busca por CEP</div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CEP</label>
            <div class="flex gap-2">
                <input :name="`${prefix}_zip`" x-model="zip" @input="maskZip()" class="{{ $fieldClass }}" placeholder="00000-000" inputmode="numeric" {!! $disabledAttr !!}>
                <button type="button" @click="fetchCep()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-brand-300 text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" title="Buscar endereço pelo CEP" {!! $disabledAttr !!}>
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rua</label>
            <input :name="`${prefix}_street`" x-model="street" class="{{ $fieldClass }}" placeholder="Rua / logradouro" {!! $disabledAttr !!}>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Número</label>
            <input :name="`${prefix}_number`" x-model="number" class="{{ $fieldClass }}" placeholder="Número" {!! $disabledAttr !!}>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Complemento</label>
            <input :name="`${prefix}_complement`" x-model="complement" class="{{ $fieldClass }}" placeholder="Complemento" {!! $disabledAttr !!}>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Bairro</label>
            <input :name="`${prefix}_neighborhood`" x-model="neighborhood" class="{{ $fieldClass }}" placeholder="Bairro" {!! $disabledAttr !!}>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado (UF)</label>
            <select :name="`${prefix}_state`" x-model="state" @change="loadCities(state, false)" class="{{ $fieldClass }}" {!! $disabledAttr !!}>
                <option value="">Selecione</option>
                <template x-for="uf in states" :key="uf.sigla">
                    <option :value="uf.sigla" x-text="`${uf.nome} (${uf.sigla})`"></option>
                </template>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Município</label>
            <select :name="`${prefix}_city`" x-model="city" class="{{ $fieldClass }}" :disabled="(!state || loadingCities){{ $disabledExpression ? ' || ' . $disabledExpression : '' }}">
                <option value="" x-text="loadingCities ? 'Carregando municípios...' : (state ? 'Selecione o município' : 'Selecione primeiro o estado')"></option>
                <template x-for="municipio in cities" :key="municipio.nome">
                    <option :value="municipio.nome" x-text="municipio.nome"></option>
                </template>
            </select>
        </div>

        @if($showNotes)
            <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações</label>
                <textarea :name="`${prefix}_notes`" x-model="notes" rows="3" class="{{ $textareaClass }}" placeholder="Ponto de referência, instruções de entrega, etc." {!! $disabledAttr !!}></textarea>
            </div>
        @endif
    </div>

    <p class="mt-3 text-xs" :class="apiError ? 'text-error-600 dark:text-error-400' : 'text-gray-500 dark:text-gray-400'" x-text="apiError || 'Você pode preencher manualmente ou usar a lupa para buscar pelo CEP.'"></p>
</div>

@once
    @push('scripts')
        <script>
            function brazilAddressField(options) {
                return {
                    prefix: options.prefix,
                    states: options.states || [],
                    state: options.selectedState || '',
                    selectedCity: options.selectedCity || '',
                    city: options.selectedCity || '',
                    cities: options.selectedCity ? [{ nome: options.selectedCity }] : [],
                    zip: options.initialZip || '',
                    street: options.initialStreet || '',
                    number: options.initialNumber || '',
                    complement: options.initialComplement || '',
                    neighborhood: options.initialNeighborhood || '',
                    notes: options.initialNotes || '',
                    loadingCities: false,
                    apiError: '',
                    init() {
                        this.state = this.resolveStateSigla(this.state || options.rawState || '');
                        this.city = String(this.city || this.selectedCity || '').trim();
                        this.selectedCity = this.city;
                        this.maskZip();
                        if (this.selectedCity && !this.cities.some((item) => this.normalizeText(item.nome) === this.normalizeText(this.selectedCity))) {
                            this.cities.unshift({ nome: this.selectedCity });
                        }
                        if (this.state) {
                            this.loadCities(this.state, true);
                        }
                    },
                    normalizeText(value) {
                        return String(value || '')
                            .normalize('NFD')
                            .replace(/[̀-ͯ]/g, '')
                            .trim()
                            .toLowerCase();
                    },
                    resolveStateSigla(value) {
                        const normalized = this.normalizeText(value).toUpperCase();
                        if (!normalized) return '';
                        const state = this.states.find((item) => {
                            return this.normalizeText(item.sigla).toUpperCase() === normalized
                                || this.normalizeText(item.nome).toUpperCase() === normalized;
                        });
                        if (state) return state.sigla;
                        return String(value || '').trim().slice(0, 2).toUpperCase();
                    },
                    stateIdBySigla(sigla) {
                        const normalizedSigla = this.resolveStateSigla(sigla);
                        const state = this.states.find((item) => item.sigla === normalizedSigla);
                        return state ? state.id : null;
                    },
                    maskZip() {
                        const digits = String(this.zip || '').replace(/\D/g, '').slice(0, 8);
                        this.zip = digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
                    },
                    ensureSelectedCityOption() {
                        this.city = String(this.city || '').trim();
                        const normalizedCity = this.normalizeText(this.city);
                        if (!normalizedCity) {
                            return;
                        }
                        const match = this.cities.find((item) => this.normalizeText(item.nome) === normalizedCity);
                        if (match) {
                            this.city = match.nome;
                            return;
                        }
                        this.cities.unshift({ nome: this.city });
                    },
                    async loadCities(sigla, preserveCity = true) {
                        this.apiError = '';
                        this.loadingCities = true;
                        this.state = this.resolveStateSigla(sigla);
                        const stateId = this.stateIdBySigla(this.state);

                        try {
                            if (!sigla || !stateId) {
                                this.cities = preserveCity && this.city ? [{ nome: this.city }] : [];
                                if (!preserveCity) this.city = '';
                                return;
                            }

                            const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${stateId}/municipios?orderBy=nome`);
                            if (!response.ok) throw new Error('Não foi possível carregar os municípios agora.');
                            const data = await response.json();
                            this.cities = Array.isArray(data) ? data.map((item) => ({ nome: item.nome })) : [];

                            if (preserveCity) {
                                this.ensureSelectedCityOption();
                            } else {
                                this.city = '';
                            }
                        } catch (error) {
                            this.apiError = 'Não foi possível carregar os municípios automaticamente. Você ainda pode revisar os campos manualmente.';
                            if (preserveCity) {
                                this.ensureSelectedCityOption();
                            }
                        } finally {
                            this.loadingCities = false;
                        }
                    },
                    async fetchCep() {
                        const digits = String(this.zip || '').replace(/\D/g, '');
                        this.apiError = '';

                        if (digits.length !== 8) {
                            this.apiError = 'Informe um CEP com 8 dígitos para buscar o endereço.';
                            return;
                        }

                        try {
                            const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
                            if (!response.ok) throw new Error('Não foi possível consultar o CEP agora.');
                            const data = await response.json();
                            if (data.erro) throw new Error('CEP não encontrado.');

                            this.zip = data.cep || this.zip;
                            this.street = data.logradouro || this.street;
                            this.complement = data.complemento || this.complement;
                            this.neighborhood = data.bairro || this.neighborhood;
                            this.state = this.resolveStateSigla(data.uf || this.state);
                            await this.loadCities(this.state, false);
                            this.city = data.localidade || this.city;
                            this.ensureSelectedCityOption();
                        } catch (error) {
                            this.apiError = error.message || 'Não foi possível consultar o CEP agora.';
                        }
                    },
                }
            }
        </script>
    @endpush
@endonce

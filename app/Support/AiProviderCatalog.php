<?php

namespace App\Support;

class AiProviderCatalog
{
    public static function defaults(): array
    {
        return [
            'ai_default_temperature' => '0.20',
            'ai_default_max_tokens' => '2048',
            'ai_default_system_prompt' => 'Voce e a IA Ancora do Portal do Cliente. Responda em portugues do Brasil, com linguagem clara, objetiva e educada. Use apenas o contexto fornecido. Se faltar base documental suficiente, diga isso com transparencia e oriente o cliente a falar com a equipe humana. Nunca invente fatos, clausulas, prazos, numeros ou conclusoes juridicas.',
            'ai_default_legal_notice' => 'Esta resposta tem carater informativo e nao substitui analise juridica individual pela equipe responsavel.',
            'ai_default_budget_request_url' => '',
            'ai_old_document_alert_years' => '5',
            'openai_chat_model' => 'gpt-5.4-mini',
            'openai_embedding_model' => 'text-embedding-3-small',
            'gemini_chat_model' => 'gemini-2.5-flash',
            'gemini_embedding_model' => 'gemini-embedding-2',
        ];
    }

    public static function temperatureMin(): float
    {
        return 0.0;
    }

    public static function temperatureMax(): float
    {
        return 2.0;
    }

    public static function temperatureStep(): float
    {
        return 0.05;
    }

    public static function tokenMin(): int
    {
        return 64;
    }

    /** @return list<float> */
    public static function temperaturePresets(): array
    {
        return [0.0, 0.2, 0.4, 0.7, 1.0, 1.5, 2.0];
    }

    /** @return list<int> */
    public static function tokenPresets(): array
    {
        return [256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536, 128000];
    }

    /** @return list<array{id:string,name:string,description:string,max_output_tokens:int,recommended?:bool}> */
    public static function openAiChatModels(): array
    {
        return [
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'description' => 'Entrada economica para respostas menores e rotinas simples.', 'max_output_tokens' => 16384],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'Versatil e rapido, bom degrau acima do mini.', 'max_output_tokens' => 16384],
            ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini', 'description' => 'Fallback corporativo mais estavel para texto.', 'max_output_tokens' => 32768],
            ['id' => 'gpt-4.1', 'name' => 'GPT-4.1', 'description' => 'Nao-raciocinante forte, com boa previsibilidade e janela maior.', 'max_output_tokens' => 32768],
            ['id' => 'gpt-5-nano', 'name' => 'GPT-5 Nano', 'description' => 'Opcao ultrabarata para alto volume e respostas curtas.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5-mini', 'name' => 'GPT-5 Mini', 'description' => 'Boa opcao economica da familia GPT-5.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5.4-nano', 'name' => 'GPT-5.4 Nano', 'description' => 'Mais barato da linha 5.4 para tarefas simples e repetitivas.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5.4-mini', 'name' => 'GPT-5.4 Mini', 'description' => 'Melhor equilibrio entre qualidade, custo e latencia para este projeto.', 'max_output_tokens' => 128000, 'recommended' => true],
            ['id' => 'gpt-5', 'name' => 'GPT-5', 'description' => 'Modelo GPT-5 anterior, ainda forte para raciocinio geral.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5.4', 'name' => 'GPT-5.4', 'description' => 'Alta inteligencia para trabalho profissional e fluxos complexos.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5.4-pro', 'name' => 'GPT-5.4 Pro', 'description' => 'Variante premium do GPT-5.4, mais pesada e mais cara.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5.5', 'name' => 'GPT-5.5', 'description' => 'Fronteira atual para tarefas complexas.', 'max_output_tokens' => 128000],
            ['id' => 'gpt-5-pro', 'name' => 'GPT-5 Pro', 'description' => 'Muito forte, mais lento e com saida maxima ainda maior.', 'max_output_tokens' => 272000],
            ['id' => 'gpt-5.5-pro', 'name' => 'GPT-5.5 Pro', 'description' => 'Mais preciso, caro e lento para casos realmente pesados.', 'max_output_tokens' => 128000],
        ];
    }

    /** @return list<array{id:string,name:string,description:string,max_output_tokens:int,recommended?:bool}> */
    public static function geminiChatModels(): array
    {
        return [
            ['id' => 'gemini-2.5-flash-lite', 'name' => 'Gemini 2.5 Flash-Lite', 'description' => 'Opcao estavel mais barata da linha 2.5.', 'max_output_tokens' => 65536],
            ['id' => 'gemini-3.1-flash-lite', 'name' => 'Gemini 3.1 Flash-Lite', 'description' => 'Linha mais nova, estavel e economica, com boa capacidade multimodal.', 'max_output_tokens' => 65536],
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'description' => 'Melhor equilibrio estavel entre custo, velocidade e qualidade para este projeto.', 'max_output_tokens' => 65536, 'recommended' => true],
            ['id' => 'gemini-3-flash-preview', 'name' => 'Gemini 3 Flash Preview', 'description' => 'Preview mais forte para entendimento multimodal e fluxos agenticos.', 'max_output_tokens' => 65536],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'description' => 'Modelo estavel mais forte da familia 2.5 para analise complexa.', 'max_output_tokens' => 65536],
            ['id' => 'gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro Preview', 'description' => 'Ponta de linha atual do Gemini API para raciocinio e execucao complexa.', 'max_output_tokens' => 65536],
        ];
    }

    /** @return list<array{id:string,name:string,description:string,recommended?:bool}> */
    public static function openAiEmbeddingModels(): array
    {
        return [
            ['id' => 'text-embedding-3-small', 'name' => 'text-embedding-3-small', 'description' => 'Melhor custo-beneficio para busca semantica e recuperacao.', 'recommended' => true],
            ['id' => 'text-embedding-3-large', 'name' => 'text-embedding-3-large', 'description' => 'Mais forte para qualidade de busca e similaridade.'],
            ['id' => 'text-embedding-ada-002', 'name' => 'text-embedding-ada-002', 'description' => 'Modelo antigo, mantido por compatibilidade.'],
        ];
    }

    /** @return list<array{id:string,name:string,description:string,recommended?:bool}> */
    public static function geminiEmbeddingModels(): array
    {
        return [
            ['id' => 'gemini-embedding-001', 'name' => 'gemini-embedding-001', 'description' => 'Modelo estavel legado de embedding para texto.'],
            ['id' => 'gemini-embedding-2', 'name' => 'gemini-embedding-2', 'description' => 'Modelo atual mais indicado, com foco em busca semantica e RAG multimodal.', 'recommended' => true],
        ];
    }

    /** @return array<string,string> */
    public static function tooltips(): array
    {
        return [
            'ai_enabled' => 'Liga ou desliga a camada central de IA do sistema. Mesmo desligada, as chaves e modelos podem continuar salvos.',
            'ai_active_provider' => 'Define qual provedor sera usado pelo sistema. Por regra de seguranca desta tela, apenas um provedor pode ficar ativo por vez.',
            'ai_default_temperature' => 'Controla o quanto a resposta varia. Quanto menor, mais previsivel. Quanto maior, mais criativa. Nesta tela a faixa foi limitada de 0,0 a 2,0 para manter um padrao seguro entre OpenAI e Gemini.',
            'ai_default_max_tokens' => 'Limita o tamanho maximo da resposta. Tokens sao pedacos de texto; mais tokens permitem respostas maiores, mas custam mais e podem ficar prolixas.',
            'ai_default_system_prompt' => 'Instrucao global enviada junto com cada chamada. E onde voce define o comportamento base da IA do Portal.',
            'ai_default_legal_notice' => 'Texto juridico padrao que pode ser reaproveitado em respostas e experiencias futuras para reforcar que a IA nao substitui analise humana.',
            'ai_default_budget_request_url' => 'Link padrao para direcionar o cliente quando a conversa precisar virar solicitacao comercial ou atendimento humano.',
            'ai_old_document_alert_enabled' => 'Mantem uma regra pronta para avisar quando documentos do cliente estiverem desatualizados para uso da IA.',
            'ai_old_document_alert_years' => 'Numero de anos usados para marcar um documento como antigo.',
            'openai_api_key' => 'Chave secreta da OpenAI. Ela e gravada criptografada e nao aparece por completo depois de salva.',
            'openai_chat_model' => 'Modelo de chat usado quando o provedor ativo for OpenAI. A lista foi fechada com modelos oficiais de texto compativeis com o AiService, sem audio, realtime ou imagem pura.',
            'openai_embedding_model' => 'Modelo usado para transformar textos em vetores numericos para busca semantica, recuperacao de contexto e ranking de documentos.',
            'gemini_api_key' => 'Chave secreta da Gemini API. Ela e gravada criptografada e nao aparece por completo depois de salva.',
            'gemini_chat_model' => 'Modelo de chat usado quando o provedor ativo for Gemini. A lista foi fechada com modelos oficiais de texto compativeis com o AiService, evitando audio, live, TTS e image-only.',
            'gemini_embedding_model' => 'Modelo de embedding do Gemini API para preparar busca semantica e recuperacao de trechos relevantes no futuro.',
        ];
    }

    /** @return list<string> */
    public static function openAiChatModelIds(): array
    {
        return array_column(self::openAiChatModels(), 'id');
    }

    /** @return list<string> */
    public static function geminiChatModelIds(): array
    {
        return array_column(self::geminiChatModels(), 'id');
    }

    /** @return list<string> */
    public static function openAiEmbeddingModelIds(): array
    {
        return array_column(self::openAiEmbeddingModels(), 'id');
    }

    /** @return list<string> */
    public static function geminiEmbeddingModelIds(): array
    {
        return array_column(self::geminiEmbeddingModels(), 'id');
    }

    public static function maxOutputTokensFor(string $provider, string $modelId): int
    {
        $models = $provider === 'gemini'
            ? self::geminiChatModels()
            : self::openAiChatModels();

        foreach ($models as $model) {
            if ($model['id'] === $modelId) {
                return (int) $model['max_output_tokens'];
            }
        }

        return $provider === 'gemini' ? 65536 : 128000;
    }
}

<?php

namespace App\Support\Financeiro;

use Illuminate\Support\Facades\Schema;

/**
 * Resolve quais colunas realmente existem em uma tabela financeira e filtra payloads
 * de insert/update para conter apenas chaves válidas.
 *
 * Motivação: o schema do Âncora é híbrido (dump legado + migrations incrementais), então
 * em alguns ambientes colunas opcionais adicionadas por migrations tardias — como
 * series_group/series_index/series_total em financial_payables — podem ainda não existir.
 * Sem este filtro, um insert com essas chaves dispara "Unknown column" e a conta a pagar
 * simplesmente não é salva. Filtrando o payload, o lançamento principal é sempre persistido
 * e os campos opcionais entram automaticamente assim que a migration correspondente roda.
 */
class FinancialColumns
{
    /** @var array<string, array<int, string>> */
    private static array $cache = [];

    /**
     * Mantém apenas as chaves do payload que correspondem a colunas existentes na tabela.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function filter(string $table, array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::columns($table)));
    }

    /**
     * @return array<int, string>
     */
    public static function columns(string $table): array
    {
        if (!array_key_exists($table, self::$cache)) {
            self::$cache[$table] = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        }

        return self::$cache[$table];
    }

    public static function has(string $table, string $column): bool
    {
        return in_array($column, self::columns($table), true);
    }

    /**
     * Limpa o cache estático (necessário em testes que recriam o schema entre cenários).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}

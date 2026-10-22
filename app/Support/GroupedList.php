<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Listados agrupados (Provisiones por proveedor, Facturacion por cliente, ambos por RO).
 *
 * Pagina por GRUPOS, no por registros: 20 grupos por pagina. Cada grupo trae
 * cuantos documentos tiene, cuantos siguen abiertos (por pagar / por cobrar),
 * sus totales por moneda y sus registros.
 *
 * Son dos consultas: una para los grupos de la pagina y otra para sus registros.
 */
final class GroupedList
{
    public const PER_PAGE = 20;

    /** Registros que se muestran dentro de un grupo; el resto, en la lista sin agrupar. */
    public const ITEMS_PER_GROUP = 25;

    /**
     * @param  Builder  $query  consulta ya filtrada (vista + busqueda), sin orden
     * @param  string  $keySql  expresion SQL de la clave del grupo
     * @param  Closure(Builder, Collection): Builder  $restrict  limita la consulta a las claves de la pagina
     * @param  Closure(mixed): mixed  $keyOf  clave de grupo de un registro ya cargado
     * @param  Closure(Builder): Collection  $loadItems  carga los registros (con relaciones y orden)
     * @param  Closure(Builder): void|null  $join  une la tabla donde esta la clave (solo para contar grupos)
     * @return LengthAwarePaginator<object{grupo:mixed, documentos:int, abiertos:int, items:Collection, totals:array<string,float>}>
     */
    public static function paginate(
        Builder $query,
        string $keySql,
        Closure $restrict,
        Closure $keyOf,
        string $statusSql,
        array $openStates,
        string $dateSql,
        Closure $loadItems,
        ?Closure $join = null,
    ): LengthAwarePaginator {
        $placeholders = implode(', ', array_fill(0, count($openStates), '?'));
        $openCount = "SUM(CASE WHEN {$statusSql} IN ({$placeholders}) THEN 1 ELSE 0 END)";

        // La clave debe ser una columna (MySQL con only_full_group_by no acepta
        // subconsultas en GROUP BY); si esta en otra tabla, se une con $join.
        $grouped = clone $query;
        if ($join) {
            $join($grouped);
        }

        // Primero los grupos con algo abierto; dentro, el de actividad mas reciente.
        $groups = $grouped->toBase()
            ->selectRaw("{$keySql} as grupo, COUNT(*) as documentos, {$openCount} as abiertos, MAX({$dateSql}) as ultima", $openStates)
            ->groupByRaw($keySql)
            ->orderByRaw("CASE WHEN {$openCount} > 0 THEN 0 ELSE 1 END", $openStates)
            ->orderByRaw("MAX({$dateSql}) DESC")
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $keys = $groups->getCollection()->pluck('grupo');
        $items = $keys->isEmpty()
            ? collect()
            : $loadItems($restrict(clone $query, $keys))->groupBy(fn ($item) => (string) ($keyOf($item) ?? ''));

        $groups->getCollection()->transform(function (object $group) use ($items) {
            $group->documentos = (int) $group->documentos;
            $group->abiertos = (int) $group->abiertos;
            $group->items = $items->get((string) ($group->grupo ?? ''), collect());
            $group->totals = $group->items
                ->groupBy(fn ($row) => $row->moneda ?: 'USD')
                ->map(fn (Collection $rows) => (float) $rows->sum(fn ($row) => (float) $row->monto))
                ->all();

            return $group;
        });

        return $groups;
    }

    /** whereIn sobre una columna que puede ser null (grupo "Sin proveedor"). */
    public static function whereKeyIn(Builder $query, string $column, Collection $keys): Builder
    {
        return $query->where(fn (Builder $match) => $match
            ->whereIn($column, $keys->filter(fn ($key) => $key !== null)->values()->all())
            ->when($keys->contains(fn ($key) => $key === null), fn (Builder $nulls) => $nulls->orWhereNull($column)));
    }
}

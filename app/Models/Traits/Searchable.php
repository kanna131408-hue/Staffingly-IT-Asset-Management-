<?php

namespace App\Models\Traits;

use App\Models\Asset;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * This trait allows for cleaner searching of models,
 * moving from complex queries to an easier declarative syntax.
 *
 * @author Till Deeke <kontakt@tilldeeke.de>
 */
trait Searchable
{
    /**
     * Performs a search on the model, using the provided search terms
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query  The query to start the search on
     * @param  string                                $search
     * @return \Illuminate\Database\Eloquent\Builder A query with added "where" clauses
     */
    public function scopeTextSearch($query, $search)
    {
        $terms = $this->prepeareSearchTerms($search);

        if (method_exists($this, 'advancedTextSearchJoins')) {
            $query = $this->advancedTextSearchJoins($query);
        }

        $query = $query->where(function ($subQuery) use ($terms) {
            /**
             * Search the attributes of this model
             */
            $subQuery = $this->searchAttributes($subQuery, $terms);

            /**
             * Search through the custom fields of the model
             */
            $subQuery = $this->searchCustomFields($subQuery, $terms);

            /**
             * Search through the relations of the model
             */
            $subQuery = $this->searchRelations($subQuery, $terms);

            /**
             * Search for additional attributes defined by the model
             */
            $subQuery = $this->advancedTextSearch($subQuery, $terms);
        });

        try {
            $logPath = public_path('query_log.txt');
            $logContent = date('Y-m-d H:i:s') . " | Search: " . $search . " | SQL: " . $query->toSql() . " | Bindings: " . json_encode($query->getBindings()) . "\n";
            file_put_contents($logPath, $logContent, FILE_APPEND);
        } catch (\Exception $e) {
            // Ignore logging errors so we don't break the app
        }

        return $query;
    }

    /**
     * Prepares the search term, splitting and cleaning it up
     *
     * @param  string $search The search term
     * @return array         An array of search terms
     */
    private function prepeareSearchTerms($search)
    {
        return explode(' OR ', $search);
    }

    /**
     * Searches the models attributes for the search terms
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  array                                $terms
     * @return Illuminate\Database\Eloquent\Builder
     */
    private function searchAttributes(Builder $query, array $terms)
    {
        $table = $this->getTable();
        $operator = $this->getLikeOperator();

        $firstConditionAdded = false;

        foreach ($this->getSearchableAttributes() as $column) {
            foreach ($terms as $term) {
                /**
                 * Making sure to only search in date columns if the search term consists of characters that can make up a MySQL timestamp!
                 *
                 * @see https://github.com/grokability/staffingly/issues/4590
                 */
                if (! preg_match('/^[0-9 :-]++$/', $term) && in_array($column, $this->getDates())) {
                    continue;
                }

                /**
                 * We need to form the query properly, starting with a "where",
                 * otherwise the generated select is wrong.
                 *
                 * @todo This does the job, but is inelegant and fragile
                 */
                if (! $firstConditionAdded) {
                    $query = $query->where($table.'.'.$column, $operator, '%'.$term.'%');

                    $firstConditionAdded = true;
                    continue;
                }

                $query = $query->orWhere($table.'.'.$column, $operator, '%'.$term.'%');
            }
        }

        return $query;
    }

    /**
     * Searches the models custom fields for the search terms
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  array                                $terms
     * @return Illuminate\Database\Eloquent\Builder
     */
    private function searchCustomFields(Builder $query, array $terms)
    {

        /**
         * If we are searching on something other that an asset, skip custom fields.
         */
        if (! $this instanceof Asset) {
            return $query;
        }

        $customFields = CustomField::all();
        $operator = $this->getLikeOperator();

        foreach ($customFields as $field) {
            foreach ($terms as $term) {
                $query->orWhere($this->getTable().'.'.$field->db_column_name(), $operator, '%'.$term.'%');
            }
        }

        return $query;
    }

    /**
     * Searches the models relations for the search terms
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  array                                $terms
     * @return Illuminate\Database\Eloquent\Builder
     */
    private function searchRelations(Builder $query, array $terms)
    {
        $operator = $this->getLikeOperator();
        foreach ($this->getSearchableRelations() as $relation => $columns) {
            $query = $query->orWhereHas(
                $relation, function ($query) use ($relation, $columns, $terms, $operator) {
                    $table = $this->getRelationTable($relation);

                    /**
                     * We need to form the query properly, starting with a "where",
                     * otherwise the generated nested select is wrong.
                     *
                     * @todo This does the job, but is inelegant and fragile
                     */
                    $firstConditionAdded = false;

                    foreach ($columns as $column) {
                        foreach ($terms as $term) {
                            if (! $firstConditionAdded) {
                                $query->where($table.'.'.$column, $operator, '%'.$term.'%');
                                $firstConditionAdded = true;
                                continue;
                            }

                            $query->orWhere($table.'.'.$column, $operator, '%'.$term.'%');
                        }
                    }
                    // I put this here because I only want to add the concat one time in the end of the user relation search
                    if(($relation == 'adminuser') || ($relation == 'user')) {
                        $query->orWhereRaw(
                            $this->buildMultipleColumnSearch(
                                [
                                'users.first_name',
                                'users.last_name',
                                ]
                            ),
                            ["%{$term}%"]
                        );
                    }
                }
            );
        }

        return $query;
    }

    /**
     * Run additional, advanced searches that can't be done using the attributes or relations.
     *
     * This is a noop in this trait, but can be overridden in the implementing model, to allow more advanced searches
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  array                                $terms The search terms
     * @return Illuminate\Database\Eloquent\Builder
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {
        return $query;
    }

    /**
     * Get the searchable attributes, if defined. Otherwise it returns an empty array
     *
     * @return array The attributes to search in
     */
    private function getSearchableAttributes()
    {
        return $this->searchableAttributes ?? [];
    }

    /**
     * Get the searchable relations, if defined. Otherwise it returns an empty array
     *
     * @return array The relations to search in
     */
    private function getSearchableRelations()
    {
        return $this->searchableRelations ?? [];
    }

    /**
     * Get the table name of a relation.
     *
     * This method loops over a relation name,
     * getting the table name of the last relation in the series.
     * So "category" would get the table name for the Category model,
     * "model.manufacturer" would get the tablename for the Manufacturer model.
     *
     * @param  string $relation
     * @return string            The table name
     */
    private function getRelationTable($relation)
    {
        $related = $this;

        foreach (explode('.', $relation) as $relationName) {
            $related = $related->{$relationName}()->getRelated();
        }

        /**
         * Are we referencing the model that called?
         * Then get the internal join-tablename, since laravel
         * has trouble selecting the correct one in this type of
         * parent-child self-join.
         *
         * @todo Does this work with deeply nested resources? Like "category.assets.model.category" or something like that?
         */
        if ($this instanceof $related) {

            /**
             * Since laravel increases the counter on the hash on retrieval, we have to count it down again.
             *
             * This causes side effects! Every time we access this method, laravel increases the counter!
             *
             * Format: laravel_reserved_XXX
             */
            $relationCountHash = $this->{$relationName}()->getRelationCountHash();

            $parts = collect(explode('_', $relationCountHash));

            $counter = $parts->pop();

            $parts->push($counter - 1);

            return implode('_', $parts->toArray());
        }

        return $related->getTable();
    }

    /**
     * Builds a search string for either MySQL or sqlite by separating the provided columns with a space.
     *
     * @param  array $columns Columns to include in search string.
     * @return string
     */
    private function buildMultipleColumnSearch(array $columns): string
    {
        $mappedColumns = collect($columns)->map(fn($column) => DB::getTablePrefix() . $column)->toArray();

        $driver = 'mysql';
        try {
            $driver = DB::connection()->getDriverName();
        } catch (\Exception $e) {
            // fallback
        }
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        if ($driver === 'sqlite') {
            return implode("||' '||", $mappedColumns) . " {$operator} ?";
        }

        // Default to MySQL/Postgres's concatenation method
        return "CONCAT(" . implode(",' ',", $mappedColumns) . ") {$operator} ?";
    }

    /**
     * Search a string across multiple columns separated with a space.
     *
     * @param  Builder $query
     * @param  array   $columns - Columns to include in search string.
     * @param  $term
     * @return Builder
     */
    public function scopeOrWhereMultipleColumns($query, array $columns, $term)
    {
        return $query->orWhereRaw($this->buildMultipleColumnSearch($columns), ["%{$term}%"]);
    }

    /**
     * Returns case-insensitive ILIKE operator for pgsql and standard LIKE for other drivers.
     *
     * @return string
     */
    private function getLikeOperator()
    {
        try {
            $driver = DB::connection()->getDriverName();
            return $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        } catch (\Exception $e) {
            return 'LIKE';
        }
    }
}

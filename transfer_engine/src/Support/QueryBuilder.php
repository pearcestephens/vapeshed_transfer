<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * QueryBuilder.php - Safe SQL Query Builder
 * 
 * Fluent query builder with parameter binding for safe database operations.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class QueryBuilder
{
    private \PDO $pdo;
    private string $table = '';
    private array $selects = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBy = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $groupBy = [];
    private array $having = [];
    
    /**
     * Create query builder instance
     * 
     * @param \PDO $pdo PDO connection
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Set table name
     * 
     * @param string $table Table name
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Set SELECT columns
     * 
     * @param string|array $columns Column(s) to select
     * @return self
     */
    public function select($columns): self
    {
        $this->selects = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    /**
     * Add WHERE condition
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @param string $boolean AND/OR
     * @return self
     */
    public function where(string $column, $operator, $value = null, string $boolean = 'AND'): self
    {
        // If only 2 params, assume = operator
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $placeholder = $this->generatePlaceholder();
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'placeholder' => $placeholder,
            'boolean' => $boolean,
        ];
        $this->bindings[$placeholder] = $value;
        
        return $this;
    }
    
    /**
     * Add WHERE IN condition
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            // Empty IN clause always false
            $this->wheres[] = [
                'raw' => '1 = 0',
                'boolean' => $boolean,
            ];
            return $this;
        }
        
        $placeholders = [];
        foreach ($values as $value) {
            $placeholder = $this->generatePlaceholder();
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }
        
        $this->wheres[] = [
            'raw' => "$column IN (" . implode(', ', $placeholders) . ")",
            'boolean' => $boolean,
        ];
        
        return $this;
    }
    
    /**
     * Add OR WHERE condition
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @return self
     */
    public function orWhere(string $column, $operator, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }
    
    /**
     * Add WHERE NULL condition
     * 
     * @param string $column Column name
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'raw' => "$column IS NULL",
            'boolean' => $boolean,
        ];
        return $this;
    }
    
    /**
     * Add WHERE NOT NULL condition
     * 
     * @param string $column Column name
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'raw' => "$column IS NOT NULL",
            'boolean' => $boolean,
        ];
        return $this;
    }
    
    /**
     * Add JOIN clause
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Operator
     * @param string $second Second column
     * @param string $type Join type (INNER, LEFT, RIGHT)
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Operator
     * @param string $second Second column
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    /**
     * Add ORDER BY clause
     * 
     * @param string $column Column name
     * @param string $direction ASC/DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     * 
     * @param string|array $columns Column(s) to group by
     * @return self
     */
    public function groupBy($columns): self
    {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    /**
     * Add HAVING clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @return self
     */
    public function having(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $placeholder = $this->generatePlaceholder();
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'placeholder' => $placeholder,
        ];
        $this->bindings[$placeholder] = $value;
        
        return $this;
    }
    
    /**
     * Set LIMIT
     * 
     * @param int $limit Limit value
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET
     * 
     * @param int $offset Offset value
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }
    
    /**
     * Execute query and fetch all results
     * 
     * @return array Results
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($this->bindings as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute query and fetch first result
     * 
     * @return array|null First result or null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }
    
    /**
     * Execute query and count results
     * 
     * @return int Count
     */
    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];
        
        $result = $this->first();
        $this->selects = $originalSelects;
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Insert data
     * 
     * @param array $data Data to insert
     * @return int Last insert ID
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }
    
    /**
     * Update data
     * 
     * @param array $data Data to update
     * @return int Affected rows
     */
    public function update(array $data): int
    {
        $sets = [];
        $updateBindings = [];
        
        foreach ($data as $column => $value) {
            $placeholder = ':update_' . $column;
            $sets[] = "$column = $placeholder";
            $updateBindings[$placeholder] = $value;
        }
        
        $sql = sprintf('UPDATE %s SET %s', $this->table, implode(', ', $sets));
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach (array_merge($updateBindings, $this->bindings) as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Delete data
     * 
     * @return int Affected rows
     */
    public function delete(): int
    {
        $sql = sprintf('DELETE FROM %s', $this->table);
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($this->bindings as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Build SELECT SQL
     * 
     * @return string SQL query
     */
    private function buildSelectSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects);
        $sql .= ' FROM ' . $this->table;
        
        // JOINs
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }
        
        // WHERE
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $havingClauses = array_map(
                fn($h) => "{$h['column']} {$h['operator']} {$h['placeholder']}",
                $this->having
            );
            $sql .= ' HAVING ' . implode(' AND ', $havingClauses);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $orderClauses = array_map(
                fn($o) => "{$o['column']} {$o['direction']}",
                $this->orderBy
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }
        
        // LIMIT & OFFSET
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }
        
        return $sql;
    }
    
    /**
     * Build WHERE clause
     * 
     * @return string WHERE clause
     */
    private function buildWhereClause(): string
    {
        $clauses = [];
        
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : $where['boolean'] . ' ';
            
            if (isset($where['raw'])) {
                $clauses[] = $boolean . $where['raw'];
            } else {
                $clauses[] = sprintf(
                    '%s%s %s %s',
                    $boolean,
                    $where['column'],
                    $where['operator'],
                    $where['placeholder']
                );
            }
        }
        
        return implode(' ', $clauses);
    }
    
    /**
     * Generate unique placeholder
     * 
     * @return string Placeholder
     */
    private function generatePlaceholder(): string
    {
        static $counter = 0;
        return ':param_' . (++$counter);
    }
    
    /**
     * Get raw SQL (for debugging)
     * 
     * @return string SQL query
     */
    public function toSql(): string
    {
        return $this->buildSelectSql();
    }
    
    /**
     * Get bindings (for debugging)
     * 
     * @return array Bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}

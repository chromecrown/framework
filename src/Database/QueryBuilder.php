<?php

namespace Flower\Database;

use Flower\Core\Application;
use Flower\Contract\Model as IModel;

/**
 * Class QueryBuilder
 * @package Flower\Database
 */
class QueryBuilder
{
    const LOGICAL_AND = 'AND';
    const LOGICAL_OR = 'OR';

    const EQUALS = '=';
    const NOT_EQUALS = '!=';
    const LESS_THAN = '<';
    const LESS_THAN_OR_EQUAL = '<=';
    const GREATER_THAN = '>';
    const GREATER_THAN_OR_EQUAL = '>=';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';

    const IS = 'IS';
    const IS_NOT = 'IS NOT';
    const ORDER_BY_ASC = 'ASC';
    const ORDER_BY_DESC = 'DESC';

    const BRACKET_OPEN = '(';
    const BRACKET_CLOSE = ')';

    protected $select;
    protected $insert;
    protected $update;
    protected $delete;

    protected $option;
    protected $set;
    protected $values;
    protected $from;
    protected $where;
    protected $orderBy;
    protected $groupBy;
    protected $limit;

    /**
     * @var string|\Flower\Client\Sync\MySQL|null
     */
    protected $bindId = null;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Model
     */
    protected $model;

    /**
     * QueryBuilder constructor.
     * @param Application $app
     * @param IModel $model
     * @param null $queryType
     */
    public function __construct(Application $app, IModel $model, $queryType = null)
    {
        $this->app   = $app;
        $this->model = $model;

        $this->setQueryType($queryType);

        $this->reset();
    }

    /**
     * @param $queryType
     */
    public function setQueryType($queryType)
    {
        if ($queryType) {
            $this->{$queryType} = true;
        }
    }

    /**
     * @param string|\Flower\Client\Sync\MySQL $uuid
     * @return $this
     */
    public function bind($uuid)
    {
        $this->bindId = $uuid;

        return $this;
    }

    /**
     * @param string|array $column
     * @param string $alias
     * @return $this
     */
    public function select($column, string $alias = null)
    {
        if (is_string($column) and strpos($column, ',') !== false) {
            $column = array_map('trim', explode(',', $column));
        }

        if (! is_array($column)) {
            if ($alias) {
                $column = [$alias => $column];
            } else {
                $column = [$column];
            }
        }

        foreach ($column as $alias => $field) {
            is_numeric($alias)
                ? ($this->select[] = $field)
                : ($this->select[$alias] = $field);
        }

        unset($column);

        return $this;
    }

    /**
     * @param string $table
     * @param string $alias
     * @return $this
     */
    public function from(string $table, string $alias = null)
    {
        $this->from['table'] = $table;
        $this->from['alias'] = $alias;

        return $this;
    }

    /**
     * @param callable|string|array $column
     * @param null $value
     * @param string $operator
     * @param string $connector
     * @return $this
     */
    public function where($column, $value = null, $operator = self::EQUALS, $connector = self::LOGICAL_AND)
    {
        if (is_array($column)) {
            foreach ($column as $field => $value) {
                $this->where($field, $value, $operator, $connector);
            }

            return $this;
        }

        if ($column instanceof \Closure) {
            $this->where[] = [
                'bracket'   => self::BRACKET_OPEN,
                'connector' => $connector
            ];

            $column($this);

            $this->where[] = [
                'bracket'   => self::BRACKET_CLOSE,
                'connector' => null
            ];

            return $this;
        }

        if (null === $value) {
            $operator = null;
        }

        if ($value === null and ! in_array($operator, [self::IN, self::IS_NOT])) {
            $operator = self::IS;
        }

        $this->where[] = [
            'column'    => $column,
            'value'     => $value,
            'operator'  => $operator,
            'connector' => $connector
        ];

        return $this;
    }

    /**
     * @param $column
     * @param null $value
     * @param string $operator
     * @return QueryBuilder
     */
    public function orWhere($column, $value = null, $operator = self::EQUALS)
    {
        return $this->where($column, $value, $operator, self::LOGICAL_OR);
    }

    /**
     * @param $column
     * @param array $values
     * @param string $connector
     * @return $this
     */
    public function whereIn($column, array $values, $connector = self::LOGICAL_AND)
    {
        $this->where[] = [
            'column'    => $column,
            'value'     => $values,
            'operator'  => self::IN,
            'connector' => $connector
        ];

        return $this;
    }

    /**
     * @param $column
     * @param array $values
     * @param string $connector
     * @return $this
     */
    public function whereNotIn($column, array $values, $connector = self::LOGICAL_AND)
    {
        $this->where[] = [
            'column'    => $column,
            'value'     => $values,
            'operator'  => self::NOT_IN,
            'connector' => $connector
        ];

        return $this;
    }

    /**
     * @param $column
     * @param string $order
     * @return $this
     */
    public function orderBy($column, $order = self::ORDER_BY_ASC)
    {
        if (! is_array($column)) {
            if (strpos($column, ',') !== false) {
                $tmp    = array_map('trim', explode(',', $column));
                $column = [];
                foreach ($tmp as $v) {
                    if (strpos($v, ' ') !== false) {
                        $v = array_map('trim', explode(' ', $v));
                        $column[$v[0]] = $v[1];
                    }
                    else {
                        $column[$v] = $order;
                    }
                }
            }
            elseif (strpos($column, ' ') !== false) {
                $tmp = array_map('trim', explode(' ', $column));
                $column = [
                    $tmp[0] => $tmp[1]
                ];
            }
            else {
                $column = [$column => $order];
            }
        }

        foreach ($column as $field => $order) {
            $this->orderBy[] = [
                'column' => $field,
                'order'  => $order
            ];
        }

        return $this;
    }

    /**
     * @param null $group
     * @return $this
     */
    public function groupBy($group = null)
    {
        if ($group) {
            if (!is_array($group)) {
                $group = trim($group);
                $group = (strpos($group, ',') !== false)
                    ? array_map('trim', explode(',', $group))
                    : [$group];
            }

            foreach ($group as $v) {
                $this->groupBy[] = [
                    'column' => $v
                ];
            }
        }

        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        $this->limit['limit'] = $limit;

        if (! isset($this->limit['offset']) or $offset > 0) {
            $this->limit['offset'] = $offset;
        }

        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->limit['offset'] = $offset;

        return $this;
    }

    /**
     * @return QueryBuilder
     */
    public function distinct()
    {
        return $this->option('DISTINCT');
    }

    /**
     * @param $option
     * @return $this
     */
    public function option($option)
    {
        $this->option[] = $option;

        return $this;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function values(array $values)
    {
        list(, $val) = each($values);
        reset($values);

        if (!is_array($val)) {
            $values = [$values];
        }
        unset($val);

        foreach ($values as $val) {
            $this->values[] = $val;
        }

        return $this;
    }

    /**
     * @param $column
     * @param null $value
     * @return $this
     */
    public function set($column, $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $field => $value) {
                $this->set($field, $value);
            }
        } else {
            $this->set[] = [
                'column' => $column,
                'value'  => $value,
            ];
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isSelect()
    {
        return !empty($this->select);
    }

    /**
     * @return bool
     */
    public function isInsert()
    {
        return $this->insert == true;
    }

    /**
     * @return bool
     */
    public function isUpdate()
    {
        return $this->update == true;
    }

    /**
     * @return bool
     */
    public function isDelete()
    {
        return $this->delete == true;
    }

    /**
     * @return string
     */
    public function getStatement()
    {
        $statement = '';
        if ($this->isSelect()) {
            $statement = $this->getSelectStatement();
        } elseif ($this->isInsert()) {
            $statement = $this->getInsertStatement();
        } elseif ($this->isUpdate()) {
            $statement = $this->getUpdateStatement();
        } elseif ($this->isDelete()) {
            $statement = $this->getDeleteStatement();
        }

        return $statement;
    }

    /**
     * @param bool $getAll
     * @return string
     */
    public function getSelectStatement($getAll = false)
    {
        $statement = '';

        if (! $getAll and ! $this->isSelect()) {
            return $statement;
        }

        $statement .= $this->getSelectString($getAll);
        $statement .= ' ' . $this->getFromString();

        if ($this->where) {
            $statement .= ' ' . $this->getWhereString();
        }

        if ($this->orderBy) {
            $statement .= ' ' . $this->getOrderByString();
        }

        if ($this->groupBy) {
            $statement .= ' '. $this->getGroupByString();
        }

        if ($this->limit) {
            $statement .= ' ' . $this->getLimitString();
        }

        return $statement;
    }

    /**
     * @param bool $getAll
     * @return string
     */
    private function getSelectString($getAll = false)
    {
        $statement  = 'SELECT ';
        $statement .= $this->getOptionsString();

        if ($getAll) {
            return $statement . '*';
        }

        foreach ($this->select as $alias => $column) {
            $statement .= $this->quoteColumn($column);

            if (! is_numeric($alias)) {
                $statement .= ' AS ' . $alias;
            }

            $statement .= ', ';
        }

        return substr($statement, 0, -2);
    }

    /**
     * @return string
     */
    private function getFromString()
    {
        $statement = '';
        if (!$this->from) {
            $this->from($this->model->getTable());
        }

        if ($this->select or $this->delete) {
            $statement .= 'FROM ';
        }

        $statement .= $this->from['table'];
        if ($this->from['alias']) {
            $statement .= ' AS ' . $this->from['alias'];
        }

        return rtrim($statement);
    }

    /**
     * @return string
     */
    private function getWhereString()
    {
        $statement = '';
        $useConnector = false;
        foreach ($this->where as $i => $criterion) {
            if (array_key_exists('bracket', $criterion)) {
                if (strcmp($criterion['bracket'], self::BRACKET_OPEN) == 0) {
                    if ($useConnector) {
                        $statement .= ' ' . $criterion['connector'] . ' ';
                    }
                    $useConnector = false;
                }
                else {
                    $useConnector = true;
                }

                $statement .= $criterion['bracket'];
            }
            else {
                if ($useConnector) {
                    $statement .= ' ' . $criterion['connector'] . ' ';
                }
                $useConnector = true;

                if (is_object($criterion['column'])) {
                    if ($criterion['column'] instanceof Expression) {
                        $statement .= $criterion['column']->get();
                        continue;
                    }

                    // 出现异常
                    $statement = '0=1';
                    break;
                }

                switch ($criterion['operator']) {
                    case self::IN:
                    case self::NOT_IN:
                        $value = self::BRACKET_OPEN;
                        foreach ($criterion['value'] as $criterionValue) {
                            $value .= $this->quote($criterionValue) . ', ';
                        }

                        $value = substr($value, 0, -2);
                        $value .= self::BRACKET_CLOSE;
                        break;

                    case self::IS:
                    case self::IS_NOT:
                        $value = $criterion['value'] === null ? 'null' : $criterion['value'];
                        break;

                    default:
                        $value = $this->quote($criterion['value']);
                        break;
                }
                $statement .= $criterion['column'] . ' ' . $criterion['operator'] . ' ' . $value;
            }
        }

        return 'WHERE ' . $statement;
    }

    /**
     * @return string
     */
    private function getOrderByString()
    {
        $statement = '';
        foreach ($this->orderBy as $orderBy) {
            $statement .= $orderBy['column'] . ' ' . $orderBy['order'] . ', ';
        }

        $statement = substr($statement, 0, -2);
        if ($statement) {
            $statement = 'ORDER BY ' . $statement;
        }

        return $statement;
    }

    /**
     * @return string
     */
    private function getGroupByString()
    {
        $statement = '';
        foreach ($this->groupBy as $orderBy) {
            $statement .= $orderBy['column'] . ', ';
        }

        $statement = substr($statement, 0, -2);
        if ($statement) {
            $statement = 'GROUP BY ' . $statement;
        }

        return $statement;
    }

    /**
     * @return string
     */
    private function getLimitString()
    {
        $statement = '';
        if (!$this->limit) {
            return $statement;
        }
        $statement .= $this->limit['limit'];
        if ($this->limit['offset'] !== 0) {
            $statement .= ' OFFSET ' . $this->limit['offset'];
        }

        if ($statement) {
            $statement = 'LIMIT ' . $statement;
        }

        return $statement;
    }

    /**
     * @return string
     */
    private function getOptionsString()
    {
        $statement = '';

        if ($this->option) {
            $statement .= implode(' ', $this->option);
            $statement .= ' ';
        }

        return $statement;
    }

    /**
     * @return string
     */
    public function getInsertStatement()
    {
        $statement = '';
        if (!$this->isInsert()) {
            return $statement;
        }

        $statement .= 'INSERT '
            . $this->getOptionsString()
            . 'INTO '
            . $this->getFromString()
            . ' '
            . $this->getValuesString();

        return $statement;
    }

    /**
     * @return string
     */
    private function getValuesString()
    {
        $statement = '';
        if (!$this->values) {
            return $statement;
        }

        $keys = array_keys($this->values[0]);

        $statement .= '(`' . implode('`,`', $keys) . '`) VALUES ';

        foreach ($this->values as $value) {
            $statement .= '(';
            foreach ($value as $val) {
                $statement .= $this->quote($val) . ', ';
            }

            $statement = substr($statement, 0, -2). '), ';
        }
        unset($keys);

        return substr($statement, 0, -2);
    }

    /**
     * @return string
     */
    public function getUpdateStatement()
    {
        $statement = '';
        if (!$this->isUpdate()) {
            return $statement;
        }

        $statement .= 'UPDATE '
            . $this->getOptionsString()
            . $this->getFromString();

        if ($this->set) {
            $statement .= ' ' . $this->getSetString();
        }

        if ($this->where) {
            $statement .= ' ' . $this->getWhereString();
        }

        if ($this->limit) {
            $statement .= ' ' . $this->getLimitString();
        }

        return $statement;
    }

    /**
     * @return string
     */
    private function getSetString()
    {
        $statement = '';
        if (!$this->set) {
            return $statement;
        }

        foreach ($this->set as $set) {
            $set['column'] = $this->quoteColumn($set['column']);

            $statement .= $set['column'] . ' ' . self::EQUALS . ' ';

            $statement .= ($set['value'] instanceof Expression)
                ? $set['value']->get()
                : $this->quote($set['value']);

            $statement .= ', ';
        }

        $statement = substr($statement, 0, -2);
        if ($statement) {
            $statement = 'SET ' . $statement;
        }

        return $statement;
    }

    /**
     * @return string
     */
    public function getDeleteStatement()
    {
        $statement = '';
        if (!$this->isDelete()) {
            return $statement;
        }

        $statement .= 'DELETE '
            . $this->getOptionsString()
            . ' ' . $this->getFromString();

        if ($this->where) {
            $statement .= ' ' . $this->getWhereString();
        }

        if ($this->orderBy) {
            $statement .= ' ' . $this->getOrderByString();
        }

        if ($this->limit) {
            $statement .= ' ' . $this->getLimitString();
        }

        return $statement;
    }

    /**
     * @param array $value
     * @return mixed
     */
    public function insert($value = [])
    {
        $this->insert = true;

        if ($value) {
            $this->values($value);
        }

        return (yield $this->execute())['insert_id'];
    }

    /**
     * @param null $sql
     * @return mixed
     */
    public function execute($sql = null)
    {
        $result = $this->model->query($sql ?: $this, $this->bindId);

        $this->reset();

        return $result;
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        $this->delete = true;

        return (yield $this->execute())['affected_rows'];
    }

    /**
     * @return mixed
     */
    public function get()
    {
        if (! $this->select) {
            $this->select('*');
        }

        return (yield $this->execute())['result'];
    }

    /**
     * @param null $field
     * @return int
     */
    public function count($field = null)
    {
        $field = $field ?: 1;

        return (yield $this->select($this->app->get('expression', "count({$field}) as count"))->pluck('count')) ?: 0;
    }

    /**
     * @param $field
     * @return null
     */
    public function pluck($field)
    {
        return (yield $this->first())[$field] ?? null;
    }

    /**
     * @return null
     */
    public function first()
    {
        $this->limit(1);

        return (yield $this->get())[0] ?? null;
    }

    /**
     * @param array $set
     * @return mixed
     */
    public function update($set = [])
    {
        $this->update = true;

        if ($set) {
            $this->set($set);
        }

        return (yield $this->execute())['affected_rows'];
    }

    /**
     * @param $field
     * @param $number
     * @param array $where
     * @return \Generator
     */
    public function increment($field, $number, $where = [])
    {
        $this->set([$field => $this->app->get('expression', "{$field} + {$number}")]);
        if ($where) {
            $this->where($where);
        }

        return yield $this->update();
    }

    /**
     * @param $field
     * @param $number
     * @param array $where
     * @return \Generator
     */
    public function decrement($field, $number, $where = [])
    {
        $this->set([$field => $this->app->get('expression', "{$field} - {$number}")]);
        if ($where) {
            $this->where($where);
        }

        return yield $this->update();
    }

    /**
     * @param $value
     * @return string
     */
    private function quote($value)
    {
        if (is_int($value) or is_float($value)) {
            return $value;
        } elseif ($value instanceof Expression) {
            return $value->get();
        } elseif (is_null($value)) {
            return 'NULL';
        }

        if (! is_string($value)) {
            return '';
        }

        return "'" . addslashes($value) . "'";
    }

    /**
     * @param $column
     * @return string
     */
    private function quoteColumn($column)
    {
        if ($column == '*') {
            return $column;
        } elseif ($column instanceof Expression) {
            return $column->get();
        }

        return '`' . $column . '`';
    }

    /**
     * @param $pool
     * @return $this
     */
    public function use($pool)
    {
        $this->model->use($pool);

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getStatement();
    }

    protected function reset()
    {
        $this->option = [];
        $this->select = [];
        $this->delete = [];
        $this->set    = [];
        $this->values = [];
        $this->from   = [];
        $this->where  = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit  = [];

        $this->bindId = null;
    }
}

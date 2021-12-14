<?php

namespace Dorkodu\Outstor;

use Closure;
use PDO;

/**
 * SQL - the SQL Query Builder Helper Class
 *
 * @author   Doruk Eray (@dorkodu) <doruk@dorkodu.com>
 * @web      <http://dorkodu.com>
 * @url      <https://github.com/dorukdorkodu/outstor>
 * @license  The MIT License (MIT) - <http://opensource.org/licenses/MIT>
 */
class SQL
{
  /**
   * @var PDO|null
   */
  public $pdo = null;

  /**
   * @var mixed Query variables
   */
  protected $select = '*';
  protected $from = null;
  protected $where = null;
  protected $limit = null;
  protected $offset = null;
  protected $join = null;
  protected $orderBy = null;
  protected $groupBy = null;
  protected $having = null;
  protected $grouped = false;

  protected $query = null;

  protected $prefix = null;

  /**
   * @var array SQL operators
   */
  protected $operators = ['=', '!=', '<', '>', '<=', '>=', '<>'];

  /**
   * @var int Total query count
   */
  protected $queryCount = 0;

  /**
   * Class constructor.
   * @param string $prefix optional prefix for table names.
   */
  public function __construct(PDO $pdo, string $prefix = '')
  {
    $this->pdo = $pdo;
    $this->prefix = $prefix;
  }

  /**
   * @param $table
   *
   * @return $this
   */
  public function table($table)
  {
    if (is_array($table)) {
      $from = '';
      foreach ($table as $key) {
        $from .= $this->prefix . $key . ', ';
      }
      $this->from = rtrim($from, ', ');
    } else {
      if (strpos($table, ',') > 0) {
        $tables = explode(',', $table);
        foreach ($tables as $key => &$value) {
          $value = $this->prefix . ltrim($value);
        }
        $this->from = implode(', ', $tables);
      } else {
        $this->from = $this->prefix . $table;
      }
    }

    return $this;
  }

  /**
   * @param array|string $fields
   *
   * @return $this
   */
  public function select($fields)
  {
    $select = is_array($fields) ? implode(', ', $fields) : $fields;
    $this->optimizeSelect($select);

    return $this;
  }

  /**
   * @param string      $field
   * @param string|null $name
   *
   * @return $this
   */
  public function max($field, $name = null)
  {
    $column = 'MAX(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
    $this->optimizeSelect($column);

    return $this;
  }

  /**
   * @param string      $field
   * @param string|null $name
   *
   * @return $this
   */
  public function min($field, $name = null)
  {
    $column = 'MIN(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
    $this->optimizeSelect($column);

    return $this;
  }

  /**
   * @param string      $field
   * @param string|null $name
   *
   * @return $this
   */
  public function sum($field, $name = null)
  {
    $column = 'SUM(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
    $this->optimizeSelect($column);

    return $this;
  }

  /**
   * @param string      $field
   * @param string|null $name
   *
   * @return $this
   */
  public function count($field, $name = null)
  {
    $column = 'COUNT(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
    $this->optimizeSelect($column);

    return $this;
  }

  /**
   * @param string      $field
   * @param string|null $name
   *
   * @return $this
   */
  public function avg($field, $name = null)
  {
    $column = 'AVG(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
    $this->optimizeSelect($column);

    return $this;
  }

  /**
   * @param string      $table
   * @param string|null $field1
   * @param string|null $operator
   * @param string|null $field2
   * @param string      $type
   *
   * @return $this
   */
  public function join($table, $field1 = null, $operator = null, $field2 = null, $type = '')
  {
    $on = $field1;
    $table = $this->prefix . $table;
    if (!is_null($operator)) {
      $on = !in_array($operator, $this->operators)
        ? $field1 . ' = ' . $operator
        : $field1 . ' ' . $operator . ' ' . $field2;
    }
    $this->join = (is_null($this->join))
      ? ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on
      : $this->join . ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on;

    return $this;
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function innerJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'INNER ');
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function leftJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'LEFT ');
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function rightJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'RIGHT ');
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function fullOuterJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'FULL OUTER ');
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function leftOuterJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'LEFT OUTER ');
  }

  /**
   * @param string $table
   * @param string $field1
   * @param string $operator
   * @param string $field2
   *
   * @return $this
   */
  public function rightOuterJoin($table, $field1, $operator = '', $field2 = '')
  {
    return $this->join($table, $field1, $operator, $field2, 'RIGHT OUTER ');
  }

  /**
   * @param array|string $where
   * @param string       $operator
   * @param string       $val
   * @param string       $type
   * @param string       $andOr
   *
   * @return $this
   */
  public function where($where, $operator = null, $val = null, $type = '', $andOr = 'AND')
  {
    if (is_array($where) && !empty($where)) {
      $_where = [];
      foreach ($where as $column => $data) {
        $_where[] = $type . $column . '=' . $this->escape($data);
      }
      $where = implode(' ' . $andOr . ' ', $_where);
    } else {
      if (is_null($where) || empty($where)) {
        return $this;
      }

      if (is_array($operator)) {
        $params = explode('?', $where);
        $_where = '';
        foreach ($params as $key => $value) {
          if (!empty($value)) {
            $_where .= $type . $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
          }
        }
        $where = $_where;
      } elseif (!in_array($operator, $this->operators) || $operator == false) {
        $where = $type . $where . ' = ' . $this->escape($operator);
      } else {
        $where = $type . $where . ' ' . $operator . ' ' . $this->escape($val);
      }
    }

    if ($this->grouped) {
      $where = '(' . $where;
      $this->grouped = false;
    }

    $this->where = is_null($this->where)
      ? $where
      : $this->where . ' ' . $andOr . ' ' . $where;

    return $this;
  }

  /**
   * @param array|string $where
   * @param string|null  $operator
   * @param string|null  $val
   *
   * @return $this
   */
  public function orWhere($where, $operator = null, $val = null)
  {
    return $this->where($where, $operator, $val, '', 'OR');
  }

  /**
   * @param array|string $where
   * @param string|null  $operator
   * @param string|null  $val
   *
   * @return $this
   */
  public function notWhere($where, $operator = null, $val = null)
  {
    return $this->where($where, $operator, $val, 'NOT ', 'AND');
  }

  /**
   * @param array|string $where
   * @param string|null  $operator
   * @param string|null  $val
   *
   * @return $this
   */
  public function orNotWhere($where, $operator = null, $val = null)
  {
    return $this->where($where, $operator, $val, 'NOT ', 'OR');
  }

  /**
   * @param string $where
   * @param bool   $not
   *
   * @return $this
   */
  public function whereNull($where, $not = false)
  {
    $where = $where . ' IS ' . ($not ? 'NOT' : '') . ' NULL';
    $this->where = is_null($this->where) ? $where : $this->where . ' ' . 'AND ' . $where;

    return $this;
  }

  /**
   * @param string $where
   *
   * @return $this
   */
  public function whereNotNull($where)
  {
    return $this->whereNull($where, true);
  }

  /**
   * @param string $field
   * @param array  $keys
   * @param string $type
   * @param string $andOr
   *
   * @return $this
   */
  public function in($field, array $keys, $type = '', $andOr = 'AND')
  {
    if (is_array($keys)) {
      $_keys = [];
      foreach ($keys as $k => $v) {
        $_keys[] = is_numeric($v) ? $v : $this->escape($v);
      }
      $where = $field . ' ' . $type . 'IN (' . implode(', ', $_keys) . ')';

      if ($this->grouped) {
        $where = '(' . $where;
        $this->grouped = false;
      }

      $this->where = is_null($this->where)
        ? $where
        : $this->where . ' ' . $andOr . ' ' . $where;
    }

    return $this;
  }

  /**
   * @param string $field
   * @param array  $keys
   *
   * @return $this
   */
  public function notIn($field, array $keys)
  {
    return $this->in($field, $keys, 'NOT ', 'AND');
  }

  /**
   * @param string $field
   * @param array  $keys
   *
   * @return $this
   */
  public function orIn($field, array $keys)
  {
    return $this->in($field, $keys, '', 'OR');
  }

  /**
   * @param string $field
   * @param array  $keys
   *
   * @return $this
   */
  public function orNotIn($field, array $keys)
  {
    return $this->in($field, $keys, 'NOT ', 'OR');
  }

  /**
   * @param Closure $obj
   *
   * @return $this
   */
  public function grouped(Closure $obj)
  {
    $this->grouped = true;
    call_user_func_array($obj, [$this]);
    $this->where .= ')';

    return $this;
  }


  /**
   * @param string     $field
   * @param string|int $value1
   * @param string|int $value2
   * @param string     $type
   * @param string     $andOr
   *
   * @return $this
   */
  public function between($field, $value1, $value2, $type = '', $andOr = 'AND')
  {
    $where = '(' . $field . ' ' . $type . 'BETWEEN ' . ($this->escape($value1) . ' AND ' . $this->escape($value2)) . ')';
    if ($this->grouped) {
      $where = '(' . $where;
      $this->grouped = false;
    }

    $this->where = is_null($this->where)
      ? $where
      : $this->where . ' ' . $andOr . ' ' . $where;

    return $this;
  }

  /**
   * @param string     $field
   * @param string|int $value1
   * @param string|int $value2
   *
   * @return $this
   */
  public function notBetween($field, $value1, $value2)
  {
    return $this->between($field, $value1, $value2, 'NOT ', 'AND');
  }

  /**
   * @param string     $field
   * @param string|int $value1
   * @param string|int $value2
   *
   * @return $this
   */
  public function orBetween($field, $value1, $value2)
  {
    return $this->between($field, $value1, $value2, '', 'OR');
  }

  /**
   * @param string     $field
   * @param string|int $value1
   * @param string|int $value2
   *
   * @return $this
   */
  public function orNotBetween($field, $value1, $value2)
  {
    return $this->between($field, $value1, $value2, 'NOT ', 'OR');
  }


  /**
   * @param string $field
   * @param string $data
   * @param string $type
   * @param string $andOr
   *
   * @return $this
   */
  public function like($field, $data, $type = '', $andOr = 'AND')
  {
    $like = $this->escape($data);
    $where = $field . ' ' . $type . 'LIKE ' . $like;

    if ($this->grouped) {
      $where = '(' . $where;
      $this->grouped = false;
    }

    $this->where = is_null($this->where)
      ? $where
      : $this->where . ' ' . $andOr . ' ' . $where;

    return $this;
  }

  /**
   * @param string $field
   * @param string $data
   *
   * @return $this
   */
  public function orLike($field, $data)
  {
    return $this->like($field, $data, '', 'OR');
  }

  /**
   * @param string $field
   * @param string $data
   *
   * @return $this
   */
  public function notLike($field, $data)
  {
    return $this->like($field, $data, 'NOT ', 'AND');
  }

  /**
   * @param string $field
   * @param string $data
   *
   * @return $this
   */
  public function orNotLike($field, $data)
  {
    return $this->like($field, $data, 'NOT ', 'OR');
  }

  /**
   * @param int      $limit
   * @param int|null $limitEnd
   *
   * @return $this
   */
  public function limit($limit, $limitEnd = null)
  {
    $this->limit = !is_null($limitEnd)
      ? $limit . ', ' . $limitEnd
      : $limit;

    return $this;
  }

  /**
   * @param int $offset
   *
   * @return $this
   */
  public function offset($offset)
  {
    $this->offset = $offset;

    return $this;
  }

  /**
   * @param int $perPage
   * @param int $page
   *
   * @return $this
   */
  public function pagination($perPage, $page)
  {
    $this->limit = $perPage;
    $this->offset = (($page > 0 ? $page : 1) - 1) * $perPage;

    return $this;
  }

  /**
   * @param string      $orderBy
   * @param string|null $orderDir
   *
   * @return $this
   */
  public function orderBy($orderBy, $orderDir = null)
  {
    if (!is_null($orderDir)) {
      $this->orderBy = $orderBy . ' ' . strtoupper($orderDir);
    } else {
      $this->orderBy = (stristr($orderBy, ' ') || strtolower($orderBy) === 'rand()')
        ? $orderBy
        : $orderBy . ' ASC';
    }

    return $this;
  }

  /**
   * @param string|array $groupBy
   *
   * @return $this
   */
  public function groupBy($groupBy)
  {
    $this->groupBy = is_array($groupBy) ? implode(', ', $groupBy) : $groupBy;

    return $this;
  }

  /**
   * @param string            $field
   * @param string|array|null $operator
   * @param string|null       $val
   *
   * @return $this
   */
  public function having($field, $operator = null, $val = null)
  {
    if (is_array($operator)) {
      $fields = explode('?', $field);
      $where = '';
      foreach ($fields as $key => $value) {
        if (!empty($value)) {
          $where .= $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
        }
      }
      $this->having = $where;
    } elseif (!in_array($operator, $this->operators)) {
      $this->having = $field . ' > ' . $this->escape($operator);
    } else {
      $this->having = $field . ' ' . $operator . ' ' . $this->escape($val);
    }

    return $this;
  }

  /**
   * @return int
   */
  public function queryCount()
  {
    return $this->queryCount;
  }

  # QUERIES

  /**
   * @param string|bool $type
   * @param string|null $argument
   *
   * @return mixed
   */
  public function get()
  {
    $this->limit = 1;
    $query = $this->getAll();

    return $query;
  }

  /**
   * @return string
   */
  public function getAll()
  {
    $query = 'SELECT ' . $this->select . ' FROM ' . $this->from;

    if (!is_null($this->join)) {
      $query .= $this->join;
    }

    if (!is_null($this->where)) {
      $query .= ' WHERE ' . $this->where;
    }

    if (!is_null($this->groupBy)) {
      $query .= ' GROUP BY ' . $this->groupBy;
    }

    if (!is_null($this->having)) {
      $query .= ' HAVING ' . $this->having;
    }

    if (!is_null($this->orderBy)) {
      $query .= ' ORDER BY ' . $this->orderBy;
    }

    if (!is_null($this->limit)) {
      $query .= ' LIMIT ' . $this->limit;
    }

    if (!is_null($this->offset)) {
      $query .= ' OFFSET ' . $this->offset;
    }

    /**
     * get() means the query is finished. 
     * so will build the query, then will reset.
     */
    $this->reset();

    return $query;
  }

  /**
   * @param $data
   *
   * @return string
   */
  public function escape($data)
  {
    $escaped = '';

    if ($data === null) {
      $escaped = 'NULL';
    } else {
      if (is_int($data) || is_float($data)) {
        $escaped = $data;
      } else {
        /**
         * is $data a placeholder for prepared statements? 
         * if so don't quote it, otherwise SQL ignores that placeholder. treats it like a string.
         */
        if (is_string($data) && preg_match('~^:[a-zA-Z0-9]+$~', $data)) {
          $escaped = $data;
        } else {
          # only a string. so will escape it.
          $escaped = $this->pdo->quote($data);
        }
      }
    }

    return $escaped;
  }

  # EXECUTING COMMANDS

  /**
   * @param array $data
   *
   * @return bool|string|int|null
   */
  public function insert(array $data)
  {
    $query = 'INSERT INTO ' . $this->from;

    $values = array_values($data);
    if (isset($values[0]) && is_array($values[0])) {
      $column = implode(', ', array_keys($values[0]));
      $query .= ' (' . $column . ') VALUES ';
      foreach ($values as $value) {
        $val = implode(', ', array_map([$this, 'escape'], $value));
        $query .= '(' . $val . '), ';
      }
      $query = trim($query, ', ');
    } else {
      $column = implode(', ', array_keys($data));
      $val = implode(', ', array_map([$this, 'escape'], $data));
      $query .= ' (' . $column . ') VALUES (' . $val . ')';
    }

    /**
     * this means an end for the query. 
     * so will build the query, then will reset.
     */
    $this->reset();

    return $query;
  }

  /**
   * @param array $data
   *
   * @return mixed|string
   */
  public function update(array $data)
  {
    $query = 'UPDATE ' . $this->from . ' SET ';
    $values = [];

    foreach ($data as $column => $val) {
      $values[] = $column . '=' . $this->escape($val);
    }
    $query .= implode(',', $values);

    if (!is_null($this->where)) {
      $query .= ' WHERE ' . $this->where;
    }

    if (!is_null($this->orderBy)) {
      $query .= ' ORDER BY ' . $this->orderBy;
    }

    if (!is_null($this->limit)) {
      $query .= ' LIMIT ' . $this->limit;
    }

    /**
     * this means an end for the query. 
     * so will build the query, then will reset.
     */
    $this->reset();

    return $query;
  }

  /**
   * @param bool $type
   *
   * @return mixed|string
   */
  public function delete($type = false)
  {
    $query = 'DELETE FROM ' . $this->from;

    if (!is_null($this->where)) {
      $query .= ' WHERE ' . $this->where;
    }

    if (!is_null($this->orderBy)) {
      $query .= ' ORDER BY ' . $this->orderBy;
    }

    if (!is_null($this->limit)) {
      $query .= ' LIMIT ' . $this->limit;
    }

    if ($query === 'DELETE FROM ' . $this->from) {
      $query = 'TRUNCATE TABLE ' . $this->from;
    }

    /**
     * this means an end for the query. 
     * so will build the query, then will reset.
     */
    $this->reset();

    return $query;
  }

  /**
   * @return string
   */
  public function analyze()
  {
    return 'ANALYZE TABLE ' . $this->from;
  }

  /**
   * @return string
   */
  public function check()
  {
    return 'CHECK TABLE ' . $this->from;
  }

  /**
   * @return string
   */
  public function checksum()
  {
    return 'CHECKSUM TABLE ' . $this->from;
  }

  /**
   * @return string
   */
  public function optimize()
  {
    return 'OPTIMIZE TABLE ' . $this->from;
  }

  /**
   * @return string
   */
  public function repair()
  {
    return 'REPAIR TABLE ' . $this->from;
  }

  /**
   * @return void
   */
  public function reset()
  {
    $this->select = '*';
    $this->from = null;
    $this->where = null;
    $this->limit = null;
    $this->offset = null;
    $this->orderBy = null;
    $this->groupBy = null;
    $this->having = null;
    $this->join = null;
    $this->grouped = false;
    $this->query = null;
  }

  /**
   * Optimize Selected fields for the query
   *
   * @param string $fields
   *
   * @return void
   */
  private function optimizeSelect($fields)
  {
    $this->select = $this->select === '*'
      ? $fields
      : $this->select . ', ' . $fields;
  }
}

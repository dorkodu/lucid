<?php

namespace Dorkodu\Outstor;

use Dorkodu\Outstor\{
  IConnection,
  SqliteConnection,
  MysqlConnection
};

use PDO, PDOException, Exception, PDOStatement;

/**
 * DB - the Database class for Outstor library
 *
 * @author  Doruk Eray (@dorkodu) <doruk@dorkodu.com>
 * @url      <https://github.com/dorukdorkodu/outstor>
 * @license  The MIT License (MIT) - <http://opensource.org/licenses/MIT>
 */
class DB
{
  /**
   * @var PDO|null
   */
  public $pdo = null;

  /**
   * @var IConnection. 
   */
  protected $connection = null;

  /**
   * @var mixed Connection variables
   */
  protected $numRows = 0;
  protected $insertId = null;
  protected $query = null;
  protected $error = null;
  protected $result = [];

  /** 
   * @var PDOStatement $statement The prepared statement after a query is set. 
   */
  protected $statement = null;

  /**
   * @var array SQL operators
   */
  protected $operators = ['=', '!=', '<', '>', '<=', '>=', '<>'];

  /**
   * @var int Total query count
   */
  protected $queryCount = 0;

  /**
   * @var bool Is it in the development environment?
   */
  protected $debug = true;

  /**
   * @var int Total transaction count
   */
  protected $transactionCount = 0;

  /**
   * DB constructor.
   *
   * @param array $config
   */
  public function __construct($isDevEnvironment = false, $prefix = '')
  {
    $this->debug = $isDevEnvironment;
    $this->prefix = $prefix;
  }

  public function connect(IConnection $connection)
  {
    try {
      # create a PDO instance
      if ($connection instanceof SqliteConnection) {
        $this->pdo = new PDO($connection->getDSN());
      } else {
        $this->pdo = new PDO($connection->getDSN(), $connection->user, $connection->password);
      }

      # if using mysql, then use buffered query
      if ($connection instanceof MysqlConnection) {
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
      }

      # setup the connection
      $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      # assign the connection
      $this->connection = $connection;
      return true;
    } catch (Exception $e) {
      throw $e;
      return false;
    }
  }

  /**
   * Disconnects from the database.
   *
   * @return void
   */
  public function disconnect()
  {
    $this->connection = null;
    $this->pdo = null;
  }

  /**
   * @return int
   */
  public function numRows()
  {
    return $this->numRows;
  }

  /**
   * @return int|null
   */
  public function insertId()
  {
    return $this->insertId;
  }

  /**
   * @throw PDOException
   */
  public function error()
  {
    if ($this->debug === true) {
      if (php_sapi_name() === 'cli') {
        die(sprintf("\n.::Database Error::.\nQuery: %s\nError: %s\n", $this->query, $this->error));
      } else {
        die(<<<HTML
          <h1>Database Error</h1>
          <h4>Query: <em style="font-weight:normal;">{$this->query}</em></h4>
          <h4>Error: <em style="font-weight:normal;">{$this->error}</em></h4>
          HTML);
      }
    }
    throw new PDOException($this->error . '. (' . $this->query . ')');
  }

  /**
   * @param $data
   *
   * @return string
   */
  public function escape($data)
  {
    return $data === null ? 'NULL' : (is_int($data) || is_float($data) ? $data : $this->pdo->quote($data));
  }

  /**
   * @return bool
   */
  public function transaction()
  {
    if (!$this->transactionCount++) {
      return $this->pdo->beginTransaction();
    }

    $this->pdo->exec('SAVEPOINT trans' . $this->transactionCount);
    return $this->transactionCount >= 0;
  }

  /**
   * @return bool
   */
  public function commit()
  {
    if (!--$this->transactionCount) {
      return $this->pdo->commit();
    }

    return $this->transactionCount >= 0;
  }

  /**
   * @return bool
   */
  public function rollBack()
  {
    if (--$this->transactionCount) {
      $this->pdo->exec('ROLLBACK TO trans' . ($this->transactionCount + 1));
      return true;
    }

    return $this->pdo->rollBack();
  }

  /**
   * @param string $query SQL query that you want to run
   * @param bool $noPrepare
   *
   * @return $this
   */
  public function query($query, $noPrepare = false)
  {
    $this->reset();

    $this->query = $query;

    # decide if the query won't be executed straightly
    if (!$noPrepare) {
      # will prepare the current query, and it will be reusable.
      $this->statement = $this->pdo->prepare($query);
    } else {
      $this->query = preg_replace('/\s\s+|\t\t+/', ' ', trim($query));
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

  /**
   * Executes the current query, or the statement with the given array
   *
   * @param array $params
   * 
   * @return int number of rows affected, on success
   * @return false on failure
   */
  public function execute(array $params = [])
  {
    if (is_null($this->query) && is_null($this->statement)) {
      return null;
    }

    # if there are params, bind them
    if (isset($this->statement)) {
      $result = $this->statement->execute($params);

      if ($result === false) {
        $this->error = $this->pdo->errorInfo()[2];
        $this->error();
      } else {
        $result = $this->statement->rowCount();
        $this->numRows = $result;
        $this->queryCount++;
      }
    } else {
      # directly execute the query      
      $result = $this->pdo->exec($this->query);
      if ($result === false) {
        $this->error = $this->pdo->errorInfo()[2];
        $this->error();
      } else {
        $this->numRows = $result;
        $this->queryCount++;
      }
    }
    return $result;
  }

  /**
   * @param array $params
   * @param string $type
   * @param string $argument
   * @param bool $all
   *
   * @return array the data returned from a successful query
   * @return false when the query fails
   * @return null when the query is null
   */
  public function fetch(array $params = array(), $type = null, $argument = null, $all = false)
  {
    if (is_null($this->query) && is_null($this->statement)) {
      return null;
    }

    if (isset($this->statement)) {
      $result = $this->statement->execute($params);

      if ($result) {
        $this->numRows = $this->statement->rowCount();

        # set fetch type
        if ($type === PDO::FETCH_CLASS) {
          $this->statement->setFetchMode($type, $argument);
        } else {
          $this->statement->setFetchMode($type);
        }

        # fetch the results
        $result = $all ? $this->statement->fetchAll() : $this->statement->fetch();
        $this->result = $result;
        $this->queryCount++;
      } else {
        $this->error = $this->pdo->errorInfo()[2];
        $this->error();
      }
    } else {
      $stmt = $this->pdo->query($this->query);

      if ($stmt) {
        $this->numRows = $stmt->rowCount();
        if ($this->numRows > 0) {
          # set fetch type
          if ($type === PDO::FETCH_CLASS) {
            $stmt->setFetchMode($type, $argument);
          } else {
            $stmt->setFetchMode($type);
          }
          
          $this->result = $all ? $stmt->fetchAll() : $stmt->fetch();
        }
      } else {
        $this->error = $this->pdo->errorInfo()[2];
        $this->error();
      }
    }

    $this->numRows = is_array($result) ? count($result) : 1;
    return $result;
  }

  /**
   * @param array $params
   * @param string $type
   * @param string $argument
   *
   * @return mixed
   */
  public function fetchAll(array $params = array(), $type = null, $argument = null)
  {
    return $this->fetch($params, $type, $argument, true);
  }


  /**
   * Tells if the query is a command, not a returning operation.
   * Not like those : SELECT, OPTIMIZE, CHECK, REPAIR, CHECKSUM, ANALYZE
   *
   * @return boolean
   */
  protected function isCommand()
  {
    $str = true;

    if (!empty($this->query)) {
      foreach (['select', 'optimize', 'check', 'repair', 'checksum', 'analyze'] as $value) {
        if (stripos($this->query, $value) === 0) {
          $str = false;
          break;
        }
      }
    }

    return $str;
  }

  /**
   * @return string|null
   */
  public function getQuery()
  {
    return $this->query;
  }

  /**
   * @return void
   */
  public function __destruct()
  {
    $this->disconnect();
  }

  /**
   * @return void
   */
  protected function reset()
  {
    $this->numRows = 0;
    $this->insertId = null;
    $this->query = null;
    $this->error = null;
    $this->result = [];
    $this->statement = null;
    $this->transactionCount = 0;
  }

  /**
   * @param  $type
   *
   * @return int
   */
  protected function getFetchType($type)
  {
    return $type === 'class'
      ? PDO::FETCH_CLASS
      : ($type === 'array'
        ? PDO::FETCH_ASSOC
        : PDO::FETCH_OBJ);
  }

  public function __get($name)
  {
    return $this->$name;
  }

  public function __set($name, $value)
  {
  }
}

<?php
  namespace Dorkodu\Outstor;

  use Dorkodu\Outstor\IConnection;

  /**
   * SqliteConnection - SQLite Database Connection Value Object
   *
   * @author Doruk Eray (@dorkodu) <doruk@dorkodu.com>
   * @link <https://github.com/dorukdorkodu/outstor>
   * @license The MIT License (MIT) - <http://opensource.org/licenses/MIT>
   */
  class SqliteConnection extends IConnection
  {
    /**
     * Database Connection Constructor.
     *
     * @param array $config
     */
    public function __construct(string $database, $charset = 'utf8', $collation = 'utf8_general_ci' )
    {
      $this->database = $database;
      $this->charset = $charset;
      $this->collation = $collation;
    }

    public function getDSN()
    {
      return sprintf('sqlite:%s', $this->database);
    }
  }
<?php
  namespace Dorkodu\Outstor;

  /**
   * IConnection - Database Connection Interface
   *
   * @author Doruk Eray (@dorkodu) <doruk@dorkodu.com>
   * @url <https://github.com/dorukdorkodu/outstor>
   * @license The MIT License (MIT) - <http://opensource.org/licenses/MIT>
   */
  abstract class IConnection
  {
    public $database;

    public $charset;
    public $collation;

    /**
     * Returns a DSN for PDO
     *
     * @return void
     */
    abstract public function getDSN();

    public function __get($name)
    {
      if (isset($this->$name)) {
        return $this->$name;
      }
    }

    public function __set($name, $value) { }
  }
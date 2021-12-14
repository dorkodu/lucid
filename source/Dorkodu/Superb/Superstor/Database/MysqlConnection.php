<?php
  namespace Dorkodu\Outstor;

  /**
   * MysqlConnection - MySQL Database Connection Value Object
   *
   * @author Doruk Eray (@dorkodu) <doruk@dorkodu.com>
   * @url <https://github.com/dorukdorkodu/outstor>
   * @license The MIT License (MIT) - <http://opensource.org/licenses/MIT>
   */
  class MysqlConnection extends IConnection
  {
    public $host;

    public $user;
    public $password;
    public $port;
    
    /**
     * MysqlConnection constructor
     *
     * @param string $host
     * @param string $database
     * @param string $user
     * @param string $password
     * @param string $port
     * @param string $charset
     * @param string $collation
     */
    public function __construct( $host = 'localhost',
                                 $database,
                                 $user,
                                 $password,
                                 $port = '',
                                 $charset = 'utf8',
                                 $collation = 'utf8_general_ci' )
    {
      $this->host = $host;
      $this->user = $user;
      $this->password = $password;
      $this->database = $database;

      $this->port = ($port !== '')
                    ? $port
                    : (strstr($host, ':') ? explode(':', $host)[1] : '');
      $this->charset = $charset;
      $this->collation = $collation;
    }

    public function getDSN()
    {
      return sprintf('mysql:host=%s;%sdbname=%s',
        str_replace(':' . $this->port, '', $this->host),
        ($this->port !== '' ? 'port=' . $this->port . ';' : ''),
        $this->database
      );
    }
  }
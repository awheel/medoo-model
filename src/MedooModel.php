<?php

namespace light\MedooModel;

use PDO;
use Medoo\Medoo;

/**
 * @method select($columns, $where = []) Select data from database
 * @method insert($data = []) Insert new records in table
 * @method update($data = [], $where = []) Modify data in table
 * @method delete($where = []) Delete data from table
 * @method replace($column, $search, $replace, $where = []) Replace old data into new one
 *
 * @method get($columns = [], $where = []) Get only one record from table
 * @method has(array $where = []) Determine whether the target data existed
 * @method count($column, $where = []) Counts the number of rows
 * @method max($column, $where = []) Get the maximum value for the column
 * @method min($column, $where = []) Get the minimum value for the column
 * @method avg($column, $where = []) Get the average value for the column
 * @method sum($column, $where = []) Get the total value for the column
 *
 * @method $query($query) Insert new records in a table
 *
 * 模型基类 基于 Medoo 代理封装
 *
 * @package MedooModel
 * @link http://medoo.in
 * @link http://gitlab.hupu.com/hupu/medoo-model
 */
abstract class MedooModel
{
    /**
     * 配置
     *
     * @var array
     */
    protected $config = [];

    /**
     * 数据连接实例
     *
     * @var array
     */
    protected $connect = [];

    /**
     * 库名
     *
     * @var
     */
    public $database;

    /**
     * 表名
     *
     * @var
     */
    public $table;

    /**
     * 表别名
     *
     * @var
     */
    public $tableAlias;

    /**
     * 表主键
     *
     * @var
     */
    public $primary = 'id';

    /**
     * 是否是读操作
     *
     * @var bool
     */
    public $read = false;

    /**
     * 自动维护 created_at 和 updated_at, 或其他指定字段
     *
     * @var array|bool
     */
    public $timestamps = false;

    /**
     * Model constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        if (!$config) {
            throw new \Exception('配置不能为空');
        }

        if (isset($config['driver'])) {
            throw new \Exception('未指定驱动');
        }
        $this->config = $config;

        return $this;
    }

    /**
     * 获取 sql 执行错误信息
     *
     * @return array
     */
    public function error()
    {
        return $this->getConnectInstance()->error();
    }

    /**
     * 获取数据库连接实例
     *
     * @return PDO
     */
    public function pdo()
    {
        return $this->getConnectInstance()->pdo;
    }

    /**
     * 根据主键查询一条数据
     *
     * @param $id
     * @param string $columns
     *
     * @return bool|null
     */
    public function find($id, $columns = '*')
    {
        if (!$id) return null;

        $this->read = true;
        return $this->getConnectInstance()->get($this->table, $columns, [$this->primary => $id]);
    }

    /**
     * 根据主键删除一条数据
     *
     * @param $id
     *
     * @return bool|int
     */
    public function destroy($id)
    {
        if (!$id) return false;

        return $this->getConnectInstance()->delete($this->table, [$this->primary => $id]);
    }

    /**
     * 获取 sql 执行记录
     *
     * @return array
     */
    public function log()
    {
        return $this->getConnectInstance()->log();
    }

    /**
     * 获取最后一条 sql
     *
     * @return mixed
     */
    public function last_query()
    {
        return $this->getConnectInstance()->last();
    }

    /**
     * 获取最新插入的 id
     *
     * @return int|string
     */
    public function id()
    {
        return $this->getConnectInstance()->id();
    }

    /**
     * Medoo 调用代理
     *
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        // 是否是读操作
        $this->read = in_array($method, ['count', 'select', 'has', 'sum', 'max', 'min', 'avg', 'get']);

        // 表缩写, 方便联表
        if ($this->read && $this->tableAlias) {
            $this->table .= "($this->tableAlias)";
        }
        else {
            $this->table = str_replace("($this->tableAlias)", '', $this->table);
        }

        // 第一个是表名
        $arguments = array_merge([$this->table], $arguments);

        // 自动维护数据库 插入更新时间
        $timestamp = date('Y-m-d H:i:s');
        $write = in_array($method, ['update', 'insert', 'replace']);
        if ($write && is_bool($this->timestamps) && $this->timestamps) {
            if ($method == 'insert' || $method == 'replace') {
                $arguments[1] =  array_merge($arguments[1], ['created_at' => $timestamp, 'updated_at' => $timestamp]);
            }

            if ($method == 'update') {
                $arguments[1] = array_merge($arguments[1], ['updated_at' => $timestamp]);
            }
        }
        elseif ($write && $this->timestamps && is_array($this->timestamps)) {
            foreach ($this->timestamps as $item) {
                $arguments[1] = array_merge($arguments[1], [$item => $timestamp]);
            }
        }

        return call_user_func_array([$this->getConnectInstance(), $method], $arguments);
    }

    /**
     * 获取 connect 实例
     *
     * @return Medoo
     */
    protected function getConnectInstance()
    {
        if (!isset($_ENV['lightMM']) || !isset($_ENV['lightMM'][$this->database])) {
            $master = $this->config[$this->database]['master'];
            $master = $master[array_rand($master)];
            $_ENV['lightMM'][$this->database]['master'] = self::connection($master);

            $slave = $this->config[$this->database]['slave'];
            $slave = $slave[array_rand($slave)];
            $_ENV['lightMM'][$this->database]['slave'] = self::connection($slave);
        }

        return $_ENV['lightMM'][$this->database][$this->read ? 'slave' : 'master'];
    }

    /**
     * 创建连接
     *
     * @param array $config
     *
     * @return Medoo
     */
    static public function connection($config = [])
    {
        return new Medoo([
            'database_type' => $config['database_type'],
            'database_name' => $config['database_name'],
            'prefix' => $config['prefix'],
            'server' => $config['server'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => $config['charset'],
            'port' => $config['port'],
            'option' => [PDO::ATTR_CASE => PDO::CASE_NATURAL]
        ]);
    }

    /**
     * 释放链接
     */
    public function __destruct()
    {
        @$_ENV['lightMM'][$this->database]['master']->pdo = null;
        @$_ENV['lightMM'][$this->database]['master'] = null;
        @$_ENV['lightMM'][$this->database]['slave']->pdo = null;
        @$_ENV['lightMM'][$this->database]['slave'] = null;
    }
}

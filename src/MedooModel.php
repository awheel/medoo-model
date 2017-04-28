<?php

namespace awheel\MedooModel;

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
 * 基类 基于 Medoo 代理封装
 *
 * @package MedooModel
 * @link http://medoo.in
 * @link https://github.com/awheel/medoo-model
 */
abstract class MedooModel
{
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
     * 表主键
     *
     * @var
     */
    public $primary = 'id';

    /**
     * 自动维护 created_at 和 updated_at, 或其他指定字段
     *
     * @var string|array|bool
     */
    public $timestamps = false;

    /**
     * 配置
     *
     * @var array
     */
    protected $config = [];

    /**
     * a place of connect
     *
     * @var string
     */
    protected $place = 'MedooModel';

    /**
     * 是否是读操作
     *
     * @var bool
     */
    protected $read = false;

    /**
     * 是否是写操作
     *
     * @var bool
     */
    protected $write = false;

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
     * 判断是否有错误发生
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->pdo()->errorCode() > 0;
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
     * 获取最后一条 sql
     *
     * @return mixed
     */
    public function last()
    {
        return $this->last_query();
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
     * 自定义查询
     *
     * @param $query
     *
     * @return bool|\PDOStatement
     */
    public function query($query)
    {
        return $this->getConnectInstance()->query($query);
    }

    /**
     * 转义字符串, 供 query 使用
     *
     * @param $string
     *
     * @return string
     */
    public function quote($string)
    {
        return $this->getConnectInstance()->quote($string);
    }

    /**
     * 获取数据库信息
     *
     * @return array
     */
    public function dbInfo()
    {
        return $this->getConnectInstance()->info();
    }

    /**
     * 事务
     *
     * @param $callback
     *
     * @return bool
     */
    public function action($callback)
    {
        return $this->getConnectInstance()->action($callback);
    }

    /**
     * 开启 debug 模式
     *
     * @return $this
     */
    public function debug()
    {
        $this->getConnectInstance()->debug();

        return $this;
    }

    /**
     * 获取表名
     *
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 设置表名
     *
     * @param $table
     */
    public function setTable($table)
    {
        $this->table = $table;
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

        // 是否是写操作
        $this->write = in_array($method, ['update', 'insert', 'replace']);

        // 第一个是表名
        $arguments = array_merge([$this->table], $arguments);

        // 自动维护数据库 插入更新时间
        $this->appendTimestamps($method, $arguments[1]);

        return call_user_func_array([$this->getConnectInstance(), $method], $arguments);
    }

    /**
     * 自动维护数据库 插入更新时间
     *
     * @param $method
     * @param $data
     *
     * @return array
     */
    protected function appendTimestamps($method, &$data)
    {
        $timestamp = date('Y-m-d H:i:s');
        $times = [];

        if ($this->write && $this->timestamps) {
            if (is_bool($this->timestamps)) {
                $times = ['updated_at' => $timestamp];
                $method == 'insert' && $times['created_at'] = $timestamp;
            }
            elseif (is_array($this->timestamps)) {
                foreach ($this->timestamps as $item) {
                    $times[$item] = $timestamp;
                }
            }
            elseif (is_string($this->timestamps)) {
                $times[$this->timestamps] = $timestamp;
            }
        }

        $multi = $method == 'insert' && is_array($data) && is_numeric(array_keys($data)[0]);
        if ($times) {
            if ($multi) {
                foreach ($data as &$item) {
                    $item = array_merge($item, $times);
                }
            }
            else {
                $data = array_merge($data, $times);
            }
        }

        return $data;
    }

    /**
     * 获取 connect 实例
     *
     * @return Medoo
     */
    protected function getConnectInstance()
    {
        if (!isset($_ENV[$this->place]) || !isset($_ENV[$this->place][$this->database])) {
            $master = $this->config[$this->database]['master'];
            $master = $master[array_rand($master)];
            $_ENV[$this->place][$this->database]['master'] = $this->connection($master);

            $slave = $this->config[$this->database]['slave'];
            $slave = $slave[array_rand($slave)];
            $_ENV[$this->place][$this->database]['slave'] = $this->connection($slave);
        }

        return $_ENV[$this->place][$this->database][$this->read ? 'slave' : 'master'];
    }

    /**
     * 创建连接
     *
     * @param array $config
     *
     * @return Medoo
     */
    protected function connection($config = [])
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
            'option' => [PDO::ATTR_CASE => PDO::CASE_NATURAL, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        ]);
    }
}

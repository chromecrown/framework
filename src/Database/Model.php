<?php

namespace Flower\Database;

use Ramsey\Uuid\Uuid;
use Flower\Core\Base;
use Flower\Core\Application;
use Flower\Server\Server;

/**
 * Class Model
 *
 * @package Flower\Database
 *
 * @method static QueryBuilder bind($uuid)
 * @method static QueryBuilder select($column, $alias = null)
 * @method static QueryBuilder where($column, $value = null, $operator = '=', $connector = 'AND')
 * @method static QueryBuilder from($table, $alias = null)
 * @method static QueryBuilder orWhere($column, $value = null, $operator = '=')
 * @method static QueryBuilder whereIn($column, array $values, $connector = 'AND')
 * @method static QueryBuilder whereNotIn($column, array $values, $connector = 'AND')
 * @method static QueryBuilder orderBy($column, $order = 'ASC')
 * @method static QueryBuilder groupBy($group = null)
 * @method static QueryBuilder limit(integer $limit, integer $offset = 0)
 * @method static QueryBuilder offset($offset)
 * @method static QueryBuilder distinct()
 * @method static QueryBuilder option(string $option)
 * @method static QueryBuilder values(array $values)
 * @method static QueryBuilder set($column, $value = null)
 * @method static QueryBuilder getStatement()
 * @method static QueryBuilder getDeleteStatement()
 * @method static QueryBuilder getUpdateStatement()
 * @method static QueryBuilder getInsertStatement()
 * @method static QueryBuilder getSelectStatement()
 * @method static QueryBuilder insert($value = [])
 * @method static QueryBuilder delete()
 * @method static QueryBuilder get()
 * @method static QueryBuilder count($field = null)
 * @method static QueryBuilder pluck($field)
 * @method static QueryBuilder first()
 * @method static QueryBuilder update($set = [])
 * @method static QueryBuilder increment($field, $number, $where = [])
 * @method static QueryBuilder decrement($field, $number, $where = [])
 */
class Model extends Base implements ModelInterface
{
    /**
     * 连接池
     *
     * @var string
     */
    private $pool = 'default';

    /**
     * 表名
     *
     * @var string
     */
    protected $table = '';

    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 唯一
     *
     * @var array
     */
    protected $uniqueKey = [];

    /**
     * 是否开启查询缓存
     *
     * @var null
     */
    private $enableQueryCache = null;

    /**
     * 缓存时间
     *
     * @var int|null
     */
    private $cacheTime = 600;

    /**
     * @var string
     */
    private $readWrite = 'auto';

    /**
     * Model constructor.
     *
     * @param Application $app
     * @param Server      $server
     */
    public function __construct(Application $app, Server $server)
    {
        parent::__construct($app, $server);

        $this->enableQueryCache = $app['config']->get('enable_query_cache', false);
        $this->cacheTime = $app['config']->get('query_cache_time', 86400);
    }

    /**
     * 获取表名
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 获取主键
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * 获取唯一
     *
     * @return array
     */
    public function getUniqueKey()
    {
        return $this->uniqueKey ?: [];
    }

    /**
     * 手动开启查询缓存
     *
     * @param  int $cacheTime
     * @return $this
     */
    public function enableCache(int $cacheTime = null)
    {
        $this->enableQueryCache = true;

        if ($cacheTime !== null) {
            $this->cacheTime = $cacheTime;
        }

        return $this;
    }

    /**
     * 手动关闭查询缓存
     */
    public function disableCache()
    {
        $this->enableQueryCache = false;

        return $this;
    }

    /**
     * 是否开启了查询缓存
     *
     * @return bool
     */
    public function isEnableCache()
    {
        return $this->enableQueryCache;
    }

    /**
     * 获取缓存时间
     *
     * @return int
     */
    public function getCacheTime()
    {
        return $this->cacheTime;
    }

    /**
     * 回调方式
     *
     * @param callable                              $callback
     * @param string|QueryBuilder                   $sql
     * @param null|string|\Flower\Client\Sync\MySQL $bindId
     * @param bool                                  $async
     */
    public function call(callable $callback, $sql, $bindId = null, $async = true)
    {
        $sql = $this->getQuerySql($sql);

        if (! $async or ($this->server->getServer()->taskworker ?? false)) {
            $callback($this->syncQuery($sql, $bindId));
        } else {
            $this->app['pool.manager']
                ->get('mysql', $this->getQueryPool(substr($sql, 0, 6)))
                ->call($callback, $sql, $bindId);
        }
    }

    /**
     * 执行查询
     *
     * @param  string|QueryBuilder                   $sql
     * @param  null|string|\Flower\Client\Sync\MySQL $bindId
     * @return array
     */
    public function query($sql, $bindId = null)
    {
        $sql = $this->getQuerySql($sql);

        if ($this->server->getServer()->taskworker ?? false) {
            return $this->syncQuery($sql, $bindId);
        }

        return $this->app['pool.manager']
            ->get('mysql', $this->getQueryPool(substr($sql, 0, 6)))
            ->query($sql, $bindId);
    }

    /**
     * @param $sql
     * @return string
     */
    private function getQuerySql($sql)
    {
        if ($sql instanceof QueryBuilder) {
            $sql = $sql->getStatement();
        }

        return ltrim($sql);
    }

    /**
     * @param $prefix
     * @return string
     */
    private function getQueryPool($prefix)
    {
        $flag = 'master';
        if (strtolower($prefix) === 'select') {
            $flag = $this->readWrite == 'auto' ? 'slave' : $this->readWrite;
        }

        if ($flag == 'slave') {
            $slave = $this->app->getConfig('_mysql.' . $this->pool);
            if (! $slave) {
                return $this->pool;
            }

            return $slave[array_rand($slave)];
        }

        return $this->pool;
    }

    /**
     * @param string                         $sql
     * @param null|\Flower\Client\Sync\MySQL $bindId
     * @return mixed
     */
    private function syncQuery(string $sql, $bindId)
    {
        return $bindId
            ? $bindId->query($sql)
            : $this->app->get('client.mysql.sync', $this->getQueryPool(substr($sql, 0, 6)))->query($sql);
    }

    /**
     * 实例化一个 Query Builder
     *
     * @param  string $queryType
     * @return QueryBuilder|QueryBuilderCache
     */
    public function getQueryBuilder($queryType = 'select')
    {
        return $this->app->get(
            $this->isEnableCache() ? 'query.builder.cache' : 'query.builder',
            $this,
            $queryType
        );
    }

    /**
     * 使用连接池
     *
     * @param  $pool
     * @return $this
     */
    public function use ($pool)
    {
        $this->pool = $pool;

        return $this;
    }

    /**
     * @return $this
     */
    public function master()
    {
        $this->readWrite = 'master';

        return $this;
    }

    /**
     * @return $this
     */
    public function slave()
    {
        $this->readWrite = 'slave';

        return $this;
    }

    /**
     * @return bool|string|\Flower\Client\Sync\MySQL
     */
    public function begin()
    {
        if ($this->server->getServer()->taskworker ?? false) {
            $uuid = $this->app->get('client.mysql.sync', $this->pool);
            $result = $this->query('begin', $uuid)['result'];
        } else {
            $uuid = Uuid::uuid4()->toString();
            $result = (yield $this->query('begin', $uuid))['result'];
        }

        if (! $result) {
            return false;
        }

        return $uuid;
    }

    /**
     * @param $uuid
     * @return bool
     */
    public function commit($uuid)
    {
        $result = (yield $this->query('commit', $uuid))['result'];

        return $result ? true : false;
    }

    /**
     * @param $uuid
     * @return bool
     */
    public function rollback($uuid)
    {
        $result = (yield $this->query('rollback', $uuid))['result'];

        return $result ? true : false;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }

        $object = $this->getQueryBuilder(null);
        if (! method_exists($object, $name)) {
            throw new \Exception('Model method not found : ' . $name);
        }

        return $object->$name(...$arguments);
    }

    /**
     * @param $name
     * @param $arguments
     * @return ModelInterface
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return app()->make(get_called_class())->$name(...$arguments);
    }
}

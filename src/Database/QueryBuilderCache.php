<?php

namespace Flower\Database;

use Flower\Client\Redis;
use Flower\Utility\Console;
use Flower\Core\Application;

/**
 * Class QueryBuilderCache
 *
 * @package Flower\Database
 */
class QueryBuilderCache extends QueryBuilder
{
    private $primaryKey = ':qc:%s:primary:%s'; // data:table:primary:primaryValue
    private $uniqueKey = ':qc:%s:unique:%s';   // data:table:unique:uniqueName:uniqueValue
    private $uniqueKeyCombine = '&&';          // data:table:unique:uniqueName:uniqueValue

    private $cacheKey = null;

    /**
     * QueryBuilderCache constructor.
     *
     * @param Application    $app
     * @param ModelInterface $model
     * @param null           $queryType
     */
    public function __construct(Application $app, ModelInterface $model, $queryType = null)
    {
        parent::__construct($app, $model, $queryType);

        $appName = $this->app['server']->getServerName();

        $this->primaryKey = $appName . $this->primaryKey;
        $this->uniqueKey = $appName . $this->uniqueKey;
    }

    /**
     * 获取数据，缓存不存在或无法缓存则走数据库
     *
     * @return array
     */
    private function getFromCache()
    {
        // 走数据库
        if (! $this->canUseCache()) {
            Console::debug("Can't Cache Sql : " . $this->getStatement(), 'red');

            $result = yield $this->getFromDatabase(false);
            if ($result === nil) {
                $result = [];
            }

            return $result;
        }

        $result = yield $this->getRedis()->get($this->cacheKey);

        // 木找到缓存
        if (! $result) {
            $result = yield $this->getFromDatabase(true);

            if (! $result or $result === nil) {
                return [];
            }

            yield $this->getRedis()->setex($this->cacheKey, $this->model->getCacheTime(),
                $this->app['packet']->pack($result));
        } else {
            $result = $this->app['packet']->unpack($result);
        }

        // 当前Sql获取所有字段
        if ($this->isGetAll()) {
            return $result;
        }

        // 取出需要的字段
        $data = [];
        foreach ($result as $k => $v) {
            foreach ($this->select as $a => $f) {
                $data[$k][is_string($a) ? $a : $f] = $v[$f] ?? null;
            }
        }
        unset($field, $result);

        return $data;
    }

    /**
     * 生成清除缓存的key
     */
    private function makeCleanKey()
    {
        $this->model->disableCache();
        $result = yield (new self($this->app, $this->model, 'select'))->select('*')->overrideWhere($this->where)->get();
        $this->model->enableCache();

        $this->cacheKey = [];

        // 数据不存在或者查询失败，则从 where 条件中生成 cacheKey
        if (! $result or $result === nil) {
            $cacheKey = $this->getCacheKey();
            if ($cacheKey) {
                $this->cacheKey[] = $cacheKey;
            }

            return;
        }

        // 生成所有可能存在的 cacheKey
        $primaryKey = $this->model->getPrimaryKey();
        $uniqueKeys = $this->model->getUniqueKey();

        foreach ($result as & $v) {
            // 主键
            $this->cacheKey[] = sprintf($this->primaryKey, $this->model->getTable(), $v[$primaryKey]);

            if (! $uniqueKeys) {
                continue;
            }

            // 唯一
            foreach ($uniqueKeys as $key => $val) {
                sort($val);

                $uniqueValue = [];
                foreach ($val as $value) {
                    $uniqueValue[] = $v[$value];
                }

                // 生成cacheKey
                $this->cacheKey[] = sprintf(
                    $this->uniqueKey,
                    $this->model->getTable(),
                    $key . ":" . implode($this->uniqueKeyCombine, $uniqueValue)
                );
            }
        }
        unset($v, $result, $primaryKey, $uniqueKeys);
    }

    /**
     * 根据 where 条件生成 cacheKey
     *
     * @param  bool $isSelect 是否查询
     * @return null|string
     */
    private function getCacheKey($isSelect = false)
    {
        $primaryKey = $this->model->getPrimaryKey();
        $uniqueKeys = $this->model->getUniqueKey();

        $count = count($this->where);
        $where = [];

        foreach ($this->where as & $v) {
            if ($isSelect) {
                if ($v['operator'] != self::EQUALS or is_object($v['value']) or is_array($v['value'])) {
                    return null;
                }
            } elseif (is_object($v['value']) or is_array($v['value'])) {
                continue;
            }

            $where[$v['column']] = $v;
        }
        unset($v);

        if (! $where) {
            return null;
        }

        // 主键
        if (isset($where[$primaryKey])) {
            // 存在非主键字段情况下，则不使用缓存
            return (! $isSelect or $count == 1)
                ? sprintf($this->primaryKey, $this->model->getTable(), $where[$primaryKey]['value'])
                : null;
        }

        // 当前表没有唯一
        if (! $uniqueKeys) {
            return null;
        }

        // 存在非唯一字段
        if ($isSelect and count($where) !== $count) {
            return null;
        }

        // 唯一
        foreach ($uniqueKeys as $key => $val) {
            $uniqueValue = [];

            // 排个序，防止修改的时候顺序变了
            sort($val);

            foreach ($val as $value) {
                if (! isset($where[$value])) {
                    $uniqueValue = [];
                    break;
                }

                $uniqueValue[] = $where[$value]['value'];
            }

            if (! $uniqueValue) {
                continue;
            }

            // 存在非唯一字段
            if ($isSelect and count($val) !== $count) {
                return null;
            }

            // 生成 cacheKey
            return sprintf(
                $this->uniqueKey,
                $this->model->getTable(),
                $key . ":" . implode($this->uniqueKeyCombine, $uniqueValue)
            );
        }

        unset($where, $result, $primaryKey, $uniqueKeys);

        return null;
    }

    /**
     * 覆盖where
     *
     * @param  $where
     * @return $this
     */
    public function overrideWhere($where)
    {
        $this->where = $where;

        return $this;
    }

    /**
     * 清除缓存
     *
     * @return \Generator
     */
    private function clearCache()
    {
        if (! $this->cacheKey) {
            return;
        }

        foreach ($this->cacheKey as $v) {
            yield $this->getRedis($v)->del($v);
        }
    }

    /**
     * 是否获取所有字段
     *
     * @return bool
     */
    private function isGetAll()
    {
        if (count($this->select) > 1) {
            return false;
        }

        list(, $field) = each($this->select);
        reset($this->select);

        return $field == '*';
    }

    /**
     * 通过数据库查询
     *
     * @param  bool $getAll
     * @return mixed
     */
    private function getFromDatabase($getAll = true)
    {
        $statement = $this->getSelectStatement($getAll);

        return (yield $this->model->query($statement))['result'];
    }

    /**
     * 判断走缓存还是走数据库，走缓存则生成 cacheKey
     * 只有where条件中只有主键或者唯一索引才走缓存
     *
     * @return bool
     */
    private function canUseCache()
    {
        // hasRaw
        foreach ($this->select as $alias => $column) {
            if ($column instanceof Expression) {
                return false;
            }
        }

        if ($this->option or $this->orderBy or $this->groupBy or ! $this->where) {
            return false;
        }

        $cacheKey = $this->getCacheKey(true);
        if (! $cacheKey) {
            return false;
        }

        $this->cacheKey = $cacheKey;

        return true;
    }

    /**
     * @param null $sql
     * @return \Generator
     */
    public function execute($sql = null)
    {
        if ($sql != null) {
            return yield parent::execute($sql);
        }

        if ($this->isSelect()) {
            $result = yield $this->getFromCache();
        } elseif ($this->isInsert()) {
            $result = yield $this->model->query($this);
        } else {
            yield $this->makeCleanKey();
            yield $this->clearCache();

            $result = yield $this->model->query($this);

            // 并发的时候，有可能在清除完但sql没执行完的时候就又生成了缓存，所以再删除一次
            yield $this->clearCache();
        }

        $this->reset();

        return $result;
    }

    /**
     * @return \Generator
     */
    public function get()
    {
        if (! $this->select) {
            $this->select('*');
        }

        return yield $this->execute();
    }

    /**
     * 重置
     *
     * @return void
     */
    protected function reset()
    {
        $this->cacheKey = null;

        parent::reset();
    }

    /**
     * @param null $cacheKey
     * @return mixed|Redis
     * @throws \Exception
     */
    public function getRedis($cacheKey = null)
    {
        $cacheKey = $cacheKey ?: $this->cacheKey;

        return $this->app->get('redis', 'query_cache', $cacheKey);
    }
}

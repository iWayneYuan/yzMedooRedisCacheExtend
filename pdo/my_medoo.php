<?php
/**
 * Created by PhpStorm.
 * User: yuanzhao
 * Date: 2017/9/13
 * Time: 上午11:24
 */
require_once dirname(__FILE__).'/medoo.php';
class my_medoo extends medoo
{
	public $redis_cache = false;//全局缓存策略,是否缓存
	public $current_cache = false;//当前缓存策略 用于控制get select 方法
	public $last_sql;//最后一次sql
	public $fetch_style;//sql取回数据风格 PDO::FETCH_ASSOC 关联数组
	public $redis_cache_time = 60;//redis 缓存时间 默认60秒
	public $redis;//redis对象
	public function __construct($options = null)
	{
		parent::__construct($options);
		$this->redis_cache = isset($options['redis_cache']) ? $options['redis_cache'] : $this->redis_cache;
		$this->redis_cache_time =  isset($options['redis_cache_time']) ?$options['redis_cache_time'] : $this->redis_cache_time;
	}
	/*
	 * 引入redis
	 */
	public function init_redis(){
		global $_DB;
		$this->redis = !empty($_DB['redis'])?$_DB['redis']:NULL;
	}
	/*
	 * 增加fetch方法
	 */
	public function fetch($fetch_style)
	{
		$this->fetch_style = $fetch_style;
		return $this->redRedis('fetch');
	}

	/*
	 * 增加fetchAll方法
	 */
	public function fetchAll($fetch_style)
	{
		$this->fetch_style = $fetch_style;
		return $this->redRedis('fetchAll');
	}
	/*
	 * redRedis
	 */
	public function redRedis($action){
		$this->init_redis();
		$sql_hash = md5($this->last_sql);
		$rs = $this->redis->get($sql_hash);
		if($rs){
			$this->logs[] = 'cache hit';
			$rs = json_decode($rs,true);
			if($rs == NULL){
				$this->logs[] = 'cache clear';
				$this->redis->del($sql_hash);
			}
		}else{
			$this->logs[] = 'cache not hit';
			$rs = $this->pdo->query($this->last_sql)->$action($this->fetch_style);
			$this->redis->set($sql_hash,json_encode($rs),(int)$this->redis_cache_time);
		}
		return $rs;
	}
	/*
	 * 重写query方法 增加缓存机制
	 * @query string sql
	 * @cache boolean 是否缓存 默认不缓存
	 */
	public function query($query, $cache = false)
	{
		if ($this->debug_mode) {
			echo $query;

			$this->debug_mode = false;

			return false;
		}

		$this->logs[] = $query;
		if($this->redis_cache===true && $cache === true){
			$this->last_sql = $query;
			return $this;
		}else{
			return $this->pdo->query($query);
		}
	}

	/*
	 * 重写get方法
	 */
	public function get($table, $join = null, $columns = null, $where = null)
	{
		$column = $where == null ? $join : $columns;

		$is_single_column = (is_string($column) && $column !== '*');
		if($this->redis_cache===true && $this->current_cache === true){
			$query = $this->query($this->select_context($table, $join, $columns, $where) . ' LIMIT 1',true);
		}else{
			$query = $this->query($this->select_context($table, $join, $columns, $where) . ' LIMIT 1');
		}
		if ($query) {
			$data = $query->fetchAll(PDO::FETCH_ASSOC);

			if (isset($data[0])) {
				if ($is_single_column) {
					return $data[0][preg_replace('/^[\w]*\./i', "", $column)];
				}

				if ($column === '*') {
					return $data[0];
				}

				$stack = array();

				foreach ($columns as $key => $value) {
					if (is_array($value)) {
						$this->data_map(0, $key, $value, $data[0], $stack);
					} else {
						$this->data_map(0, $key, preg_replace('/^[\w]*\./i', "", $value), $data[0], $stack);
					}
				}

				return $stack[0];
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	public function select($table, $join, $columns = null, $where = null)
	{
		$column = $where == null ? $join : $columns;

		$is_single_column = (is_string($column) && $column !== '*');
		if($this->redis_cache===true && $this->current_cache === true){
			$query = $this->query($this->select_context($table, $join, $columns, $where),true);
			return $query->fetchAll(PDO::FETCH_ASSOC);
		}else{
			$query = $this->query($this->select_context($table, $join, $columns, $where));
		}
		$stack = array();

		$index = 0;

		if (!$query)
		{
			return false;
		}

		if ($columns === '*')
		{
			return $query->fetchAll(PDO::FETCH_ASSOC);
		}

		if ($is_single_column)
		{
			return $query->fetchAll(PDO::FETCH_COLUMN);
		}
		while ($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			foreach ($columns as $key => $value)
			{
				if (is_array($value))
				{
					$this->data_map($index, $key, $value, $row, $stack);
				}
				else
				{
					$this->data_map($index, $key, preg_replace('/^[\w]*\./i', "", $value), $row, $stack);
				}
			}

			$index++;
		}

		return $stack;
	}
}

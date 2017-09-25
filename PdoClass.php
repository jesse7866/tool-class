<?php
/**
* mysql PDO类
*/
class PdoClass
{
    protected static $_instance = null;
    protected $dbName = '';
    protected $db;
    protected $option = [];
    protected $log;
    protected $pre;

    /**
     * 构造
     *
     * @return PDO
     */
    private function __construct($db)
    {
        try {
            $this->pre = $db['DB_PREFIX'];
            $this->log = new log();
            $this->dsn = 'mysql:host='.$db['DB_HOST'].';port='.$db['DB_PORT'].';dbname='.$db['DB_NAME'];
            $this->db = new PDO($this->dsn, $db['DB_USER'], $db['DB_PWD'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            //$this->db->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING); //将 NULL 转换成空字符串
        } catch (PDOException $e) {
            $this->outputError('__construct', $e->getMessage());
        }
    }

    /**
     * 返回PDO实例
     *
     * @return Object
     */
    public static function getInstance($db)
    {
        if (self::$_instance === null) {
            self::$_instance = new self($db);
        }
        return self::$_instance;
    }

    /**
     * 方法重载
     * @Date   2017-08-02
     * @param  string     $method 方法名
     * @param  mix        $args   方法的参数
     * @return object     返回类的对象
     */
    public function __call($method, $args) {
        $method = strtolower($method);
        if (in_array($method, array('field','data','where','group','having','order','limit'))) {
            $this->options[$method] = $args[0]; //接收数据
            if(!isset($this->options['field'])) {
                $this->options['field'] = '*';
            }
            return $this;   //返回对象，连贯查询
        } else{
            throw new Exception($method . '方法在Model.class.php类中没有定义');
        }
    }

    /**
     * 设置表，ignorePre为true的时候，不加上默认的表前缀
     * @Date   2017-08-02
     * @param  string     $table     表名
     * @param  boolean    $ignorePre 是否加上表前缀
     * @return object     返回当前对象
     */
    public function table($table, $ignorePre = false) {
        if (empty($table)) {
            $this->outputError('table', "table不能为空");
        }
        if ($ignorePre) {
            $this->options['table'] = $table;
        } else {
            $this->options['table'] = $this->pre . $table;
        }
        return $this;
    }

    /**
     * query 执行原生SQL语句
     * @Date   2017-08-03
     * @param  string     $sql    sql语句
     * @param  string     $params 占位符参数
     * @return 如果是查询条件返回结果集，如果是update、insert、replace、delete返回受影响的记录行数
     */
    public function query($sql, $params = '')
    {
        $res = $this->execute($sql, $params);
        $this->getPDOError();
        //判断当前的sql是否是查询语句
        if (strpos(trim(strtolower($sql)), 'select') === 0 ) {
            return $res->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if ($res->rowCount()) {
                return $res->rowCount();
            } else {
                return false;
            }
        }
    }

    /**
     * select链式操作
     * @Date   2017-08-02
     * @return array    返回结果集(二维数组)
     */
    public function select()
    {
        $table = $this->options['table'];
        $field = isset($this->options['field']) ? $this->options['field'] : '*';
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '1';
        $params = isset($condition['params']) ? $condition['params'] : '';
        $sql = "SELECT " . $field . " FROM $table WHERE $where";
        $res = $this->execute($sql, $params);
        $result = $res->fetchAll(PDO::FETCH_ASSOC);
        $this->getPDOError();
        return $result;
    }

    /**
     * 查询1条数据，可以只通过主键id查询或者其他条件查询
     * @Date   2017-08-02
     * @param  int     $id 主键id
     * @return array   返回结果集(一维数组)
     */
    public function find($id = '')
    {
        $table = $this->options['table'];
        $this->options['limit'] = 1;
        $field = isset($this->options['field']) ? $this->options['field'] : '*';
        if (!empty($id)) {
            $sql = "SELECT " . $field . " FROM $table WHERE id = " . intval($id) . ' LIMIT 1';
            $res = $this->execute($sql);
        } else {
            $condition = $this->parseCondition($this->options);
            $where = isset($condition['where']) ? $condition['where'] : '';
            $params = isset($condition['params']) ? $condition['params'] : '';
            $sql = "SELECT " . $field . " FROM $table WHERE $where";
            $res = $this->execute($sql, $params);
        }
        $result = $res->fetch(PDO::FETCH_ASSOC);
        $this->getPDOError();
        return $result;
    }

    /**
     * 插入一位数组
     * @Date   2017-08-02
     * @param  array     $data  要插入的数据
     * @return int       新增id
     */
    public function insert(array $data = [])
    {
        $table = $this->options['table'];
        $data = !empty($data) ? $data : $this->options['data'];
        if (empty($data)) {
            $this->outputError('insert', "插入数据时data不能为空");
        }
        $sql = "INSERT INTO `$table` (`".implode('`,`', array_keys($data))."`) VALUES ('".implode("','", $data)."')";
        $res = $this->execute($sql);
        $this->getPDOError();
        if ($res->rowCount()) {
            return $this->db->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * Replace 覆盖方式插入
     * @Date   2017-08-02
     * @param  array     $data 插入数据
     * @return int       返回受影响的行数
     */
    public function replace(array $data = [])
    {
        $table = $this->options['table'];
        $data = !empty($data) ? $data : $this->options['data'];
        if (empty($data)) {
            $this->outputError('replace', "插入数据时data不能为空");
        }
        $sql = "REPLACE INTO `$table`(`".implode('`,`', array_keys($data))."`) VALUES ('".implode("','", $data)."')";
        $res = $this->execute($sql);
        $this->getPDOError();
        if ($res->rowCount()) {
            return $this->db->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * update更新，以data()方法数据为准，如果没有where条件，则为replace动作
     * @Date   2017-08-03
     * @param  array     $data 要更新的数据
     * @return int       返回受影响的行数
     */
    public function update(array $data = [])
    {
        $table = $this->options['table'];
        $data = !empty($data) ? $data : $this->options['data'];
        if (empty($data)) {
            $this->outputError('update', "更新数据时data不能为空");
        }
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '';
        if (empty($where)) {
            $this->outputError('update', "更新数据时where不能为空");
        }
        $params = isset($condition['params']) ? $condition['params'] : '';
        if (!empty($where)) {
            $condition = '';
            foreach ($data as $key => $value) {
                $condition .= ", `$key`='$value'";
            }
            $condition = substr($condition, 1);
            $sql = "UPDATE `$table` SET $condition WHERE $where";
        } else {
            $sql = "REPLACE INTO `$table` (`".implode('`,`', array_keys($data))."`) VALUES ('".implode("','", $data)."')";
        }
        $res = $this->execute($sql, $params);
        $this->getPDOError();
        return $res->rowCount();
    }

    /**
     * 删除
     * @Date   2017-08-03
     * @return int       返回受影响的行数
     */
    public function delete()
    {
        $table = $this->options['table'];
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '';
        if (empty($where)) {
            $this->outputError('delete', "删除数据时where不能为空");
        }
        $params = isset($condition['params']) ? $condition['params'] : '';
        $sql = "DELETE FROM `$table` WHERE $where";
        $res = $this->execute($sql, $params);
        $this->getPDOError();
        return $res->rowCount();
    }

    /**
     * 查询总数
     * @Date   2017-08-03
     * @return int     返回记录总数
     */
    public function count()
    {
        $table = $this->options['table'];
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '1';
        $params = isset($condition['params']) ? $condition['params'] : '';
        $sql = "SELECT COUNT(*) AS counts FROM $table WHERE $where";
        $res = $this->execute($sql, $params);
        $counts = $res->fetch(PDO::FETCH_OBJ)->counts;
        return $counts;
    }

    /**
     * 自增
     * @Date   2017-09-07
     * @param  string     $column 自增字段
     * @param  int        $amount 增量
     * @return int        返回记录总数
     */
    public function increment($column, $amount = 1)
    {
        if (!is_numeric($amount)) {
            throw new Exception("增量必须为数值");
        }
        $table = $this->options['table'];
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '';
        if (empty($where)) {
            $this->outputError('update', "更新数据时where不能为空");
        }
        $params = isset($condition['params']) ? $condition['params'] : '';
        $sql = "UPDATE `$table` SET `$column` = `$column` + " . $amount . " WHERE " . $where;
        $res = $this->execute($sql, $params);
        $this->getPDOError();
        return $res->rowCount();
    }

    /**
     * 自减
     * @Date   2017-09-07
     * @param  string     $column 自增字段
     * @param  int        $amount 减量
     * @return int        返回记录总数
     */
    public function decrement($column, $amount = 1)
    {
        if (!is_numeric($amount)) {
            throw new Exception("增量必须为数值");
        }
        $table = $this->options['table'];
        $condition = $this->parseCondition($this->options);
        $where = isset($condition['where']) ? $condition['where'] : '';
        if (empty($where)) {
            $this->outputError('update', "更新数据时where不能为空");
        }
        $params = isset($condition['params']) ? $condition['params'] : '';
        $sql = "UPDATE `$table` SET `$column` = `$column` - " . $amount . " WHERE " . $where;
        $res = $this->execute($sql, $params);
        $this->getPDOError();
        return $res->rowCount();
    }

    /**
     * allowField 检查指定字段是否在指定数据表中存在;
     * allowField()必须在data()后,否则无效;
     * 主要针对update、insert、replace的操作，仅对data()中的参数过滤。
     * @param mix $allow bool或者array
     * @param object     过滤非指定字段，或者非数据表中的字段
     */
    public function allowField($allow)
    {
        $data = $this->options['data'];
        if (empty($data)) {
            return $this;
        }
        if (is_bool($allow)) {
            $table = $this->options['table'];
            $fields = $this->getFields($table);
            $this->options['data'] = array_intersect_key($data, array_fill_keys($fields, 0));
        } elseif (is_array($allow)) {
            $this->options['data'] = array_intersect_key($data, array_fill_keys($allow, 0));
        } else {
            $this->outputError('allowField', "'$allow'参数格式有误！");
        }
        return $this;
    }

    /**
     * pdo属性设置
     */
    public function setAttribute($p,$d){
        $this->db->setAttribute($p,$d);
    }

    /**
     * beginTransaction 事务开始
     */
    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    /**
     * commit 事务提交
     */
    public function commit()
    {
        $this->db->commit();
    }

    /**
     * rollback 事务回滚
     */
    public function rollback()
    {
        $this->db->rollback();
    }

    /**
     * transaction 通过事务处理多条SQL语句
     * 调用前需通过getTableEngine判断表引擎是否支持事务
     *
     * @param array $arraySql
     * @return Boolean
     */
    /*public function execTransaction($arraySql)
    {
        $retval = 1;
        $this->beginTransaction();
        foreach ($arraySql as $sql) {
            if ($this->query($sql) == 0) {
                $retval = 0;
            }
        }
        if ($retval == 0) {
            $this->rollback();
            return false;
        } else {
            $this->commit();
            return true;
        }
    }*/

    /**
     * sql预处理
     * @Date   2017-08-03
     * @param  string     $sql    sql语句
     * @param  array      $params 占位符参数
     * @return object             返回PDO预处理对象
     */
    private function execute($sql, $params = [])
    {
        $pre = $this->db->prepare($sql);
        if (!empty($params)) {
            $pre->execute($params);
        } else {
            $pre->execute();
        }

        return $pre;
    }

    /**
     * 解析where查询条件
     * $where为数组时，只接受相等查询
     * @Date   2017-08-02
     * @param  array     $options 查询条件
     * @return array     where条件和占位符参数
     */
    private function parseCondition($options) {
        $where = '';
        $params = [];
        if(!empty($options['where'])) {
            if(is_string($options['where'])) {
                $where .= $options['where'];
            } elseif (is_array($options['where'])) {
                    foreach($options['where'] as $key => $value) {
                         $where .= " `$key` = ? AND ";
                         $params[] = $value;
                    }
                    $where = substr($where, 0,-4);
            } else {
                $where = "";
            }
        }

        if(!empty($options['group']) && is_string($options['group']) ) {
            $where .= " GROUP BY " . $options['group'];
        }
        if(!empty($options['having']) && is_string($options['having']) ) {
            $where .= " HAVING " .  $options['having'];
        }
        if(!empty($options['order']) && is_string($options['order']) ) {
            $where .= " ORDER BY " .  $options['order'];
        }
        if(!empty($options['limit']) && (is_string($options['limit']) || is_numeric($options['limit'])) ) {
            $where .= " LIMIT " . intval($options['limit']);
        }
        if(empty($where) ) return "";
        $this->options['where'] = '';
        $this->options['group'] = '';
        $this->options['having'] = '';
        $this->options['order'] = '';
        $this->options['limit'] = '';
        $this->options['field'] = '*';
        return ['where' => $where, 'params' => $params];
    }

    /**
     * getFields 获取指定数据表中的全部字段名
     *
     * @param String $table 表名
     * @return array
     */
    private function getFields($table)
    {
        $fields = array();
        $recordset = $this->db->query("SHOW COLUMNS FROM $table");
        $this->getPDOError();
        $result = $recordset->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $rows) {
            $fields[] = $rows['Field'];
        }
        return $fields;
    }

    /**
     * getPDOError 捕获PDO错误信息
     */
    private function getPDOError()
    {
        if ($this->db->errorCode() != '00000') {
            $arrayError = $this->db->errorInfo();
            $this->outputError('getPDOError', $arrayError[2]);
        }
    }

    /**
     * 输出错误信息
     *
     * @param String $strErrMsg
     */
    private function outputError($method, $errMsg)
    {
        $this->log->SQLErrorLog("PDO执行querySql方法失败:" . $errMsg);
        throw new Exception('SQL Error: ' . $method . '->' . $errMsg);
    }

    /**
     * 防止克隆
     *
     */
    private function __clone() {}

    /**
     * destruct 关闭数据库连接
     */
    public function __destruct()
    {
        $this->db = null;
    }
}
